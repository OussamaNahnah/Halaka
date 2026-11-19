import { reactive } from 'vue';
import { request,ApiError } from './api/client';
import { storage } from './storage/db';
import { payloadFor,applyAck } from './domain/submission';
import type { Auth,Cache,Draft,QueueItem,Assignment,ServerSession,Work,Ack } from './domain/types';
export const state=reactive({sessionPage:1,ready:false,auth:null as Auth|null,cache:null as Cache|null,drafts:[] as Draft[],queue:[] as QueueItem[],online:navigator.onLine,busy:false,message:'',error:'',save:'saved' as 'saved'|'saving'|'error',theme:'light',offlineReady:false,updateReady:false});
const copy=<T>(v:T):T=>JSON.parse(JSON.stringify(v));
let pending:Promise<unknown>=Promise.resolve(),saveTicket=0;
const unsaved=new Map<string,number>(),failed=new Set<string>();
export function errorMessage(e:unknown):string{if(e instanceof DOMException&&['QuotaExceededError','SecurityError','InvalidStateError','UnknownError'].includes(e.name))return 'تعذر الوصول إلى بيانات الدخول. تحقق من صلاحيات تخزين الموقع.';return e instanceof ApiError?Object.values(e.errors).flat().join(' · ')||e.message:(e instanceof TypeError||(e instanceof DOMException&&['TimeoutError','AbortError'].includes(e.name)))?'تعذر الاتصال. العمل موجود في الصفحة الحالية؛ حاول مجددًا عند توفر الشبكة.':e instanceof Error?e.message:'تعذر إتمام العملية.'}
export async function run(action:()=>Promise<unknown>){state.error='';state.message='';try{await action()}catch(e){state.error=errorMessage(e);if(e instanceof ApiError&&e.status===401){await lockAccount()}}}
export async function call<T=any>(path:string,method='GET',body?:unknown):Promise<T>{const auth=state.auth;if(!auth)throw Error('يلزم تسجيل الدخول.');const result=await request<T>(path,auth.token,method,body);if(state.auth?.token!==auth.token)throw Error('تغير الحساب.');return result}
export async function boot(){try{state.theme=await storage.theme();document.documentElement.dataset.theme=state.theme;const auth=await storage.auth();if(auth){state.auth=auth;await loadOwner();if(navigator.onLine){try{await call('/profile')}catch(e){if(e instanceof ApiError&&[401,403].includes(e.status))await lockAccount()}}}await flushRevocations()}catch(e){state.error='تعذر قراءة بيانات الدخول. فعّل تخزين الموقع لتسجيل الدخول.'}finally{state.ready=true}}
async function loadOwner(){if(!state.auth)return;const owner=state.auth.profile.id;await storage.pruneSynced(owner);state.drafts=await storage.drafts(owner);const saved=await storage.cache(owner);state.cache=saved||null;state.queue=await storage.queue(owner);for(const item of state.queue)if(item.state==='uploading'){item.state='pending_upload';await storage.putQueue(copy(item))}for(const draft of state.drafts)if(draft.sync==='uploading'){draft.sync='pending_upload';await storage.putDraft(copy(draft))}await storage.clearCache(owner)}
export async function login(email:string,password:string){const response=await request('/login',undefined,'POST',{email,password});const auth={profile:response.data,token:response.access_token};await storage.setAuth(auth);state.auth=auth;state.sessionPage=1;state.cache=null;state.drafts=[];state.queue=[];await loadOwner();startAutoUploads()}
export async function flushRevocations(){if(!navigator.onLine)return;for(const {token}of await storage.revocations()){try{await request('/logout',token,'POST');await storage.revoked(token)}catch(e){if(e instanceof ApiError&&[401,403].includes(e.status))await storage.revoked(token);else break}}}
async function lockAccount(){stopAutoUploads();if(state.auth)await storage.clearAuth(state.auth.token);state.auth=null;state.cache=null;state.drafts=[];state.queue=[]}
export async function logout(){await flush();await lockAccount();await flushRevocations()}
export async function setTheme(value:string){await storage.setTheme(value);state.theme=value;document.documentElement.dataset.theme=value}
export async function prepare(){
 if(!navigator.onLine)throw Error('تجهيز البيانات يحتاج اتصالًا بالإنترنت.');
 state.busy=true;try{
  for(let retry=0;retry<3;retry++){
   const groups=await call('/groups');const revision=groups.meta.sync_revision;let consistent=true;
   const check=(response:any)=>{if(response.meta?.sync_revision!==revision)consistent=false;return response};
   const cache:Cache={owner:state.auth!.profile.id,revision,preparedAt:new Date().toISOString(),groups:groups.data,suggestedGroupId:groups.meta.suggested_group_id,rosters:{},assignments:{},surahs:(await call('/quran/surahs')).data,sessions:[]};
   for(const group of cache.groups){const roster=check(await call(`/groups/${group.id}/students`));cache.rosters[group.id]=roster.data;for(const student of roster.data){if(!cache.assignments[student.id])cache.assignments[student.id]=check(await call(`/students/${student.id}/assignments`)).data}}
   let page=1,last=1;
   do{const response=check(await call(`/sessions?per_page=100&page=${page}`));last=response.meta.last_page;for(const row of response.data){const detail=check(await call(`/sessions/${row.id}`));cache.sessions.push(detail.data);for(const w of detail.data.students){if(!w.archived&&!cache.assignments[w.student_id])cache.assignments[w.student_id]=check(await call(`/students/${w.student_id}/assignments`)).data}}page++}while(page<=last);
   if(!consistent)continue;
   state.cache=cache;await syncAssignmentStates({meta:{sync_revision:cache.revision},assignment_states:Object.values(cache.assignments).flat().filter(a=>a.id).map(a=>({id:a.id!,student_id:a.student_id,client_uuid:a.client_uuid,is_passed:a.is_passed}))});state.message='تم تحديث البيانات من الخادم.';return;
  }
  throw Error('تغيرت بيانات المدرسة أثناء التنزيل. حاول تجهيزها مجددًا.');
 }finally{state.busy=false;scheduleAutoUpload()}
}
export function hasUnsentSession(){return state.drafts.find(d=>d.sync!=='synced'||state.queue.some(q=>q.draftKey===d.key))}
class PendingDependencyError extends Error {}
let autoEnabled=false,autoRunning=false,retryDelay=5000,uploadTimer:ReturnType<typeof setTimeout>|undefined;
export function stopAutoUploads(){autoEnabled=false;clearTimeout(uploadTimer);uploadTimer=undefined}
export function startAutoUploads(){autoEnabled=true;retryDelay=5000;scheduleAutoUpload(0)}
function scheduleAutoUpload(delay=retryDelay){
 clearTimeout(uploadTimer);uploadTimer=undefined;
 if(!autoEnabled||!state.auth||!navigator.onLine||!state.queue.some(q=>q.owner===state.auth!.profile.id&&q.state==='pending_upload'))return;
 uploadTimer=setTimeout(()=>{void uploadPendingAutomatically()},delay);(uploadTimer as any).unref?.();
}
function requiresReview(e:unknown){return e instanceof ApiError&&e.status>=400&&e.status<500&&![401,403,408,429].includes(e.status)}
export async function uploadPendingAutomatically(){
 if(autoRunning||!state.auth||!navigator.onLine)return;
 if(state.busy){scheduleAutoUpload(2000);return}
 autoRunning=true;
 try{
  for(const item of [...state.queue]){
   if(!state.auth||!navigator.onLine)break;
   if(item.owner!==state.auth.profile.id||item.state!=='pending_upload')continue;
   const draft=state.drafts.find(d=>d.key===item.draftKey);if(!draft)continue;
   try{await send(draft);retryDelay=5000}catch(e){
    if(e instanceof PendingDependencyError){state.error=e.message;continue}
    if(e instanceof ApiError&&[401,403].includes(e.status)){state.error='سجّل الدخول مجددًا لإرسال العمل المحفوظ.';await lockAccount();break}
    if(requiresReview(e)){state.error='تحتاج الحصة إلى مراجعة قبل الإرسال. بقي العمل في الصفحة الحالية.';continue}
    state.error='الاتصال غير مستقر. الحصة في هذه الصفحة وسيعاد إرسالها تلقائيًا؛ أبقِ الصفحة مفتوحة.';retryDelay=Math.min(retryDelay*2,60000);break;
   }
  }
 }finally{autoRunning=false;scheduleAutoUpload()}
}
function scheduleSave(draft:Draft){
 const ticket=++saveTicket,snapshot=copy(draft);unsaved.set(draft.key,ticket);failed.delete(draft.key);state.save='saving';
 pending=pending.catch(()=>{}).then(async()=>{await storage.putDraft(snapshot);}).then(()=>{
  if(unsaved.get(draft.key)===ticket){unsaved.delete(draft.key);failed.delete(draft.key)}
  state.save=failed.size?'error':unsaved.size?'saving':'saved';
 }).catch(e=>{if(unsaved.get(draft.key)===ticket)failed.add(draft.key);state.save='error';state.error='تعذر تجهيز العمل للإرسال. أبقِ الصفحة مفتوحة وأعد المحاولة.';throw e});void pending.catch(()=>{});
}
export function changed(draft:Draft){draft.edit++;draft.updatedAt=new Date().toISOString();if(draft.sync==='synced')draft.sync='local_only';scheduleSave(draft)}
export async function flush(){await pending;if(unsaved.size||state.save==='error')throw Error('تعذر حفظ العمل المحلي.')}
export async function retrySave(){for(const draft of state.drafts)scheduleSave(draft);await flush()}
export function fromServer(row:ServerSession):Draft{return {key:crypto.randomUUID(),owner:state.auth!.profile.id,serverId:row.id,client_uuid:row.client_uuid||crypto.randomUUID(),base_revision:row.revision,base_sync_revision:state.cache!.revision,group_id:row.group?.id||0,timestamp:row.timestamp,status:row.status,sync:'synced',students:row.students.map(w=>({...w,assignments:[],removed_assignment_ids:[],assessments:w.assessments.map(a=>({...a,client_uuid:a.client_uuid||crypto.randomUUID()}))})),edit:0,updatedAt:row.timestamp}}
export async function openServer(id:number){const local=state.drafts.find(d=>d.serverId===id);if(local)return local;const row=state.cache?.sessions.find(s=>s.id===id);if(!row)throw Error('حمّل بيانات الحصة أولًا.');const draft=fromServer(row);await storage.putDraft(draft);if(state.auth?.profile.id!==draft.owner)throw Error('تغير الحساب؛ بقيت الحصة في ذاكرة الصفحة لصاحبها.');state.drafts.push(draft);return draft}
export async function createDraft(group_id:number,timestamp:string){if(!navigator.onLine)throw Error('بدء حصة جديدة يحتاج اتصالًا لتحميل بياناتها.');if(state.drafts.some(d=>d.sync!=='synced')||state.queue.length)throw Error('أكمل الحصة الحالية وأرسلها قبل إنشاء حصة جديدة.');if(state.cache&&(state.cache.status_revision||0)>state.cache.revision)await prepare();const cache=state.cache;if(!cache)throw Error('جهّز بيانات المدرسة أولًا.');const roster=cache.rosters[group_id];if(!roster?.length)throw Error('المجموعة لا تحتوي طلابًا متاحين.');const draft:Draft={key:crypto.randomUUID(),owner:cache.owner,client_uuid:crypto.randomUUID(),group_id,timestamp,status:'in_progress',sync:'local_only',base_sync_revision:cache.revision,students:roster.map(s=>({student_id:s.id,name:s.name,evaluated_at:null,assignments:[],assessments:[],removed_assignment_ids:[]})),edit:0,updatedAt:new Date().toISOString()};await storage.putDraft(draft);if(state.auth?.profile.id!==draft.owner)throw Error('تغير الحساب؛ بقيت الحصة في ذاكرة الصفحة لصاحبها.');state.drafts.push(draft);state.sessionPage=1;return draft}
export function assignmentFor(draft:Draft,work:Work,id?:number,uuid?:string):Assignment|undefined{return [...(state.cache?.assignments[work.student_id]||[]),...state.drafts.flatMap(d=>d.students.filter(w=>w.student_id===work.student_id).flatMap(w=>w.assignments))].find(a=>(id&&a.id===id)||(uuid&&a.client_uuid===uuid))}
export function dueAssignments(draft:Draft,work:Work):Assignment[]{const all=[...(state.cache?.assignments[work.student_id]||[]),...state.drafts.filter(d=>d.key!==draft.key&&new Date(d.timestamp)<=new Date(draft.timestamp)).flatMap(d=>d.students.filter(w=>w.student_id===work.student_id).flatMap(w=>w.assignments))];const seen=new Set();return all.filter(a=>{const k=a.id||a.client_uuid;if(seen.has(k))return false;seen.add(k);return !a.is_passed})}
export function discardEmptyDuplicates(work:Work):boolean{
 const original=work.assessments;
 work.assessments=original.filter((a,index)=>{
  if(a.id||a.grade!==''||a.comment?.trim())return true;
  const same=(b:typeof a)=>Boolean((a.quran_assignment_id&&b.quran_assignment_id===a.quran_assignment_id)||(a.assignment_client_uuid&&b.assignment_client_uuid===a.assignment_client_uuid));
  return !original.some((b,j)=>j!==index&&same(b)&&(b.id||b.grade!==''||b.comment?.trim()||j<index));
 });return work.assessments.length!==original.length;
}
export function hasAttempt(work:Work,assignment:Assignment):boolean{return work.assessments.some(a=>Boolean((assignment.id&&a.quran_assignment_id===assignment.id)||(assignment.client_uuid&&a.assignment_client_uuid===assignment.client_uuid)))}
export function addAttempt(draft:Draft,work:Work,assignment:Assignment){if(hasAttempt(work,assignment))return;work.assessments.push({client_uuid:crypto.randomUUID(),...(assignment.id?{quran_assignment_id:assignment.id}:{assignment_client_uuid:assignment.client_uuid!}),grade:'',comment:''});changed(draft)}
export function needsRepeat(work:Work,assignment:Assignment,attempt?:import('./domain/types').Attempt):boolean{
 if(typeof attempt?.needs_repeat==='boolean')return attempt.needs_repeat;
 if(attempt?.id&&typeof attempt.is_passed==='boolean')return !attempt.is_passed;
 const decision=work.assignments.find(a=>(assignment.id&&a.id===assignment.id)||(assignment.client_uuid&&a.client_uuid===assignment.client_uuid));
 if(decision)return !decision.is_passed;
 return attempt?.id ? !assignment.is_passed : false;
}
export function pendingItem(draft:Draft){return state.queue.find(q=>q.draftKey===draft.key)}
export async function queueDraft(draft:Draft){await flush();if(pendingItem(draft))throw Error('توجد نسخة في قائمة الإرسال. أعد إرسالها أولًا؛ التعديلات الجديدة تبقى محفوظة منفصلة.');const payload=payloadFor(draft);const item:QueueItem={id:payload.submission_id,owner:draft.owner,draftKey:draft.key,edit:draft.edit,serverId:draft.serverId,payload,state:'pending_upload'};const queued=copy(draft);queued.status='completed';queued.sync='pending_upload';await storage.enqueue(queued,item);Object.assign(draft,queued);state.queue.push(item);scheduleAutoUpload(0)}
export async function send(draft:Draft){
 await flush();const item=pendingItem(draft);if(!item)throw Error('جهّز نسخة للإرسال أولًا.');if(item.state==='conflict')throw Error('راجع التعارض قبل إنشاء نسخة إرسال جديدة.');if(!navigator.onLine)throw Error('النسخة في هذه الصفحة وتنتظر الاتصال؛ أبقِ الصفحة مفتوحة.');
 // Block only a known, unacknowledged local dependency. A missing cache entry
 // alone is not evidence that an assignment is absent on the server.
 for(const work of item.payload.students)for(const attempt of work.assessments){
  const uuid=attempt.assignment_client_uuid;if(!uuid||attempt.quran_assignment_id)continue;
  if(work.assignments.some(a=>a.client_uuid===uuid))continue;
  if((state.cache?.assignments[work.student_id]||[]).some(a=>a.client_uuid===uuid&&a.id))continue;
  const sources=state.drafts.filter(d=>d.owner===draft.owner).flatMap(d=>d.students.filter(w=>w.student_id===work.student_id).flatMap(w=>w.assignments.map(a=>({draft:d,assignment:a})))).filter(x=>x.assignment.client_uuid===uuid);
  if(sources.some(x=>x.assignment.id))continue;
  const source=sources.find(x=>!x.assignment.id);
  if(source){const group=state.cache?.groups.find(g=>g.id===source.draft.group_id)?.name||'الحصة الأصلية';throw new PendingDependencyError(`هذا الواجب أُضيف في «${group}» بتاريخ ${new Date(source.draft.timestamp).toLocaleDateString('ar')} ولم يُؤكد إرساله بعد. أرسل تلك الحصة أو أعد إرسال نسختها المحفوظة أولًا.`)}
 }
 state.busy=true;try{item.state='uploading';draft.sync='uploading';await storage.enqueue(copy(draft),copy(item));
  const ack=await call<Ack>(item.serverId?`/sessions/${item.serverId}`:'/sessions',item.serverId?'PUT':'POST',item.payload);
  await flush();const latest=await storage.draft(draft.key,draft.owner);if(!latest)throw Error('تعذر العثور على العمل المحلي.');
  const accepted=applyAck(latest,ack,item.edit);await storage.acknowledge(accepted,item.id);Object.assign(draft,accepted);state.queue=state.queue.filter(q=>q.id!==item.id);await syncAssignmentStates(ack);state.message=accepted.sync==='synced'?'تم إرسال الحصة بنجاح.':'أرسلت النسخة السابقة؛ لديك تعديلات أحدث موجودة في الصفحة الحالية.';
 }catch(e){item.state=requiresReview(e)?'conflict':'pending_upload';draft.sync=item.state;draft.lastError=errorMessage(e);await storage.enqueue(copy(draft),copy(item));throw e}finally{state.busy=false;scheduleAutoUpload()}
}
async function syncAssignmentStates(ack:Pick<Ack,'meta'|'assignment_states'>){
 if(!state.cache||!ack.assignment_states)return;
 if(ack.meta.sync_revision<Math.max(state.cache.revision,state.cache.status_revision||0))return;
 state.cache.status_revision=ack.meta.sync_revision;
 const statuses=new Map(ack.assignment_states.map(s=>[s.id,s]));
 for(const assignments of Object.values(state.cache.assignments))for(const assignment of assignments){const status=assignment.id?statuses.get(assignment.id):undefined;if(status)assignment.is_passed=status.is_passed}
 for(const session of state.cache.sessions)for(const work of session.students)for(const attempt of work.assessments){const status=attempt.quran_assignment_id?statuses.get(attempt.quran_assignment_id):undefined;if(status)attempt.is_passed=status.is_passed}
 for(const draft of state.drafts.filter(d=>d.sync==='synced')){
  let touched=false;
  for(const work of draft.students){
   for(const assignment of work.assignments){const status=assignment.id?statuses.get(assignment.id):undefined;if(status){assignment.is_passed=status.is_passed;touched=true}}
   for(const attempt of work.assessments){const status=attempt.quran_assignment_id?statuses.get(attempt.quran_assignment_id):ack.assignment_states.find(s=>s.client_uuid&&s.client_uuid===attempt.assignment_client_uuid);if(status){attempt.quran_assignment_id=status.id;attempt.is_passed=status.is_passed;delete attempt.needs_repeat;touched=true}}
  }
  if(touched)await storage.putDraft(copy(draft));
 }
 
}
export async function adoptServer(draft:Draft){if(!state.cache)throw Error('جهّز البيانات أولًا.');const server=state.cache.sessions.find(s=>s.id===draft.serverId);if(!server)throw Error('الحصة لم تعد متاحة. احتفظ بنسختك المحلية وتواصل مع الإدارة.');const fresh=fromServer(server);fresh.key=draft.key;await storage.backup(copy(draft));const queued=pendingItem(draft);if(queued)await storage.acknowledge(fresh,queued.id);else await storage.putDraft(fresh);Object.assign(draft,fresh);state.queue=state.queue.filter(q=>q.draftKey!==draft.key);}
export async function rebase(draft:Draft){
 const cache=state.cache;if(!cache)throw Error('جهّز البيانات أولًا.');const server=cache.sessions.find(s=>s.id===draft.serverId);
 if(draft.serverId&&!server)throw Error('الحصة مؤرشفة أو لم تعد تابعة لك. لا يمكن إرسالها.');
 const fresh=server?fromServer(server):copy(draft);
 const expected=(server?server.students.map(w=>w.student_id):cache.rosters[draft.group_id]?.map(s=>s.id)||[]).sort((a,b)=>a-b);
 if(JSON.stringify(expected)!==JSON.stringify(draft.students.map(w=>w.student_id).sort((a,b)=>a-b)))throw Error('تغير سجل الطلاب. احتفظ بالعمل المحلي واعتمد نسخة الخادم أو أنشئ حصة جديدة؛ لن يتغير السجل تلقائيًا.');
 if(!cache.groups.some(g=>g.id===draft.group_id))throw Error('المجموعة لم تعد متاحة.');
 for(const local of draft.students){
  const target=fresh.students.find(w=>w.student_id===local.student_id)!;if(target.archived)throw Error('أحد الطلاب مؤرشف. لا يمكن الكتابة فوق أرشيفه.');
  const current=cache.assignments[local.student_id]||[];
  for(const a of local.assignments){if(a.id){const saved=current.find(x=>x.id===a.id);if(!saved)throw Error('أحد الواجبات مؤرشف أو غير متاح.');if(saved.assessments?.length&&['surah_name','from_ayah','to_ayah','type'].some(k=>String((saved as any)[k])!==String((a as any)[k])))throw Error('تغير مقطع واجب مقيم. أنشئ واجبًا جديدًا بدل تعديل تاريخه.')}}
  if(local.removed_assignment_ids.some(id=>current.find(a=>a.id===id)?.assessments?.length))throw Error('واجب مطلوب حذفه أصبح مقيمًا.');
  for(const a of local.assessments){if(a.id&&!target.assessments.some(x=>x.id===a.id))throw Error('أحد التقييمات مؤرشف أو نُقل. اعتمد نسخة الخادم للحفاظ على تعديل الإدارة.');if(a.quran_assignment_id&&!current.some(x=>x.id===a.quran_assignment_id))throw Error('واجب التقييم لم يعد متاحًا.');}
  target.evaluated_at=local.evaluated_at;target.assignments=copy(local.assignments);target.removed_assignment_ids=copy(local.removed_assignment_ids);target.composer=copy(local.composer||{open:false,surah_name:'',from_ayah:'1',to_ayah:'',type:'new_memorization'});
  for(const a of local.assessments){const index=target.assessments.findIndex(x=>(a.id&&x.id===a.id)||(a.client_uuid&&x.client_uuid===a.client_uuid));if(index>=0)target.assessments[index]=copy(a);else target.assessments.push(copy(a))}
 }
 fresh.key=draft.key;fresh.client_uuid=draft.client_uuid;fresh.timestamp=draft.timestamp;fresh.group_id=draft.group_id;fresh.base_sync_revision=cache.revision;fresh.edit=draft.edit+1;fresh.sync='local_only';fresh.lastError=undefined;fresh.updatedAt=new Date().toISOString();
 await storage.backup(copy(draft));const queued=pendingItem(draft);if(queued)await storage.acknowledge(fresh,queued.id);else await storage.putDraft(fresh);Object.assign(draft,fresh);state.queue=state.queue.filter(q=>q.draftKey!==draft.key);state.message='حُفظت النسخة السابقة للاسترجاع. راجع الحصة ثم أنشئ إرسالًا جديدًا.';
}
