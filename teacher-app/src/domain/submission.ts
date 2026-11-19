import type { Draft,Payload,Ack } from './types';
export function payloadFor(draft:Draft):Payload {
 const students=draft.students.map(w=>{
  const attempts=w.assessments.filter(a=>a.grade!=='' || a.id || a.comment?.trim() || a.needs_repeat===true);
  if(attempts.some(a=>a.grade===''||!Number.isFinite(Number(a.grade))))throw Error('أدخل علامة رقمية لكل تقييم، أو اترك المقطع الجديد فارغًا دون تعليق.');
  if(w.composer?.open&&(w.composer.surah_name||w.composer.to_ayah))throw Error('احفظ الواجب المفتوح أو تخلّ عنه صراحةً قبل الإرسال.');
  if(!w.evaluated_at&&(attempts.length||w.assignments.length||w.removed_assignment_ids.length))throw Error('أنهِ الطلاب الذين لديهم عمل مسجل قبل الإرسال.');
  return {student_id:w.student_id,evaluated_at:w.evaluated_at,assignments:w.assignments.filter(a=>a.assigned_here||(!a.id&&a.client_uuid)||attempts.some(x=>(a.id&&x.quran_assignment_id===a.id)||(a.client_uuid&&x.assignment_client_uuid===a.client_uuid))).map(a=>({...(a.id?{id:a.id}:{}),...(a.client_uuid?{client_uuid:a.client_uuid}:{}),surah_name:a.surah_name,from_ayah:a.from_ayah,to_ayah:a.to_ayah,type:a.type,is_passed:a.is_passed})),assessments:attempts.map(({assignment_archived,is_passed,needs_repeat,...a})=>{const decision=w.assignments.find(x=>(x.id&&x.id===a.quran_assignment_id)||(x.client_uuid&&x.client_uuid===a.assignment_client_uuid));return {...a,grade:Number(a.grade),needs_repeat:needs_repeat??(decision?!decision.is_passed:(a.id&&typeof is_passed==='boolean'?!is_passed:false))}}),removed_assignment_ids:w.removed_assignment_ids};
 });
 return {submission_id:crypto.randomUUID(),client_uuid:draft.client_uuid,base_sync_revision:draft.base_sync_revision,...(draft.base_revision?{base_revision:draft.base_revision}:{}),group_id:draft.group_id,timestamp:draft.timestamp,status:'completed',students};
}
export function applyAck(draft:Draft,ack:Ack,submittedEdit:number):Draft {
 // Never mark edits made after the queued revision as uploaded.
 const result=structuredClone(draft);result.serverId=ack.data.id;result.base_revision=ack.data.revision;
 result.acknowledgedAt=new Date().toISOString();result.sync=draft.edit===submittedEdit?'synced':'local_only';
 result.lastError=undefined;
 for(const w of result.students){
  for(const a of w.assignments)if(a.client_uuid&&ack.id_mapping.assignments[a.client_uuid])a.id=ack.id_mapping.assignments[a.client_uuid];
  for(const a of w.assessments)if(a.client_uuid&&ack.id_mapping.assessments[a.client_uuid])a.id=ack.id_mapping.assessments[a.client_uuid];
 }
 if(result.sync==='synced'){
  result.base_sync_revision=ack.meta.sync_revision;
  for(const w of result.students)for(const a of w.assessments){const status=ack.assignment_states?.find(s=>(a.quran_assignment_id&&s.id===a.quran_assignment_id)||(a.assignment_client_uuid&&s.client_uuid===a.assignment_client_uuid));if(status){a.quran_assignment_id=status.id;a.is_passed=status.is_passed;delete a.needs_repeat}}
 }

 return result;
}
