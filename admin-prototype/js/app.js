'use strict';
const app=document.querySelector('#app');
let logged=false,page='groups',editing=null,mode='list',search='',toastTimer;
function toast(message){const el=document.querySelector('#toast');el.textContent=message;el.style.display='block';clearTimeout(toastTimer);toastTimer=setTimeout(()=>{el.style.display='none';},3500);}
function render(){if(!logged)return login();if(mode==='edit')editor();else listing();}
function applyRecord(values){
 const name=String(values.get('name')||'').trim();
 if(!name)return 'أدخل الاسم.';
 const current=data[page].find(item=>item.id===editing),id=current?.id||uid();
 if(page==='teachers'){
  const email=String(values.get('email')||'').trim().toLowerCase();
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))return 'أدخل بريدًا إلكترونيًا صحيحًا.';
  if(data.users.some(user=>user.id!==current?.user_id&&user.email.toLowerCase()===email))return 'البريد الإلكتروني مسجّل لأستاذ آخر.';
  if(current){Object.assign(teacherUser(current),{name,email});}else{const user_id=uid();data.users.push({id:user_id,name,email});data.teachers.push({id,user_id});}
 }else if(page==='students'){
  const parent_phone=String(values.get('parent_phone')||'').trim();
  if(parent_phone&&!/^\+?[0-9 ()-]{6,30}$/.test(parent_phone))return 'أدخل رقم هاتف صحيحًا لولي الأمر.';
  const selected=new Set(values.getAll('groups').map(Number));
  if(current)Object.assign(current,{name,parent_phone});else data.students.push({id,user_id:null,name,parent_phone});
  data.groups.forEach(group=>{group.studentIds=group.studentIds.filter(studentId=>studentId!==id);if(selected.has(group.id))group.studentIds.push(id);});
 }else{
  if(data.groups.some(item=>item.id!==id&&item.name===name))return 'اسم المجموعة مستخدم بالفعل.';
  const studentIds=[...new Set(values.getAll('students').map(Number))].filter(studentId=>data.students.some(item=>item.id===studentId));
  const updated={id,name,studentIds};if(current)Object.assign(current,updated);else data.groups.push(updated);
 }
 persist();return null;
}
app.addEventListener('click',event=>{
 const button=event.target.closest('[data-action]');if(!button)return;
 const action=button.dataset.action;
 if(action==='demo'){logged=true;mode='list';}
 if(action==='logout'){logged=false;editing=null;mode='list';search='';}
 if(action==='navigate'){page=button.dataset.page;mode='list';editing=null;search='';}
 if(action==='create'){editing=null;mode='edit';}
 if(action==='edit'){editing=Number(button.dataset.id);mode='edit';}
 if(action==='cancel'){mode='list';editing=null;}
 render();
});
app.addEventListener('input',event=>{if(event.target.id==='search'){search=event.target.value;document.querySelector('#results').innerHTML=resultsMarkup();}});
app.addEventListener('submit',event=>{
 event.preventDefault();const form=event.target;
 if(form.id==='login-form'){form.reset();logged=true;render();return;}
 if(form.id==='record-form'){
  const message=applyRecord(new FormData(form));if(message){toast(message);return;}
  mode='list';search='';render();toast(storageOk?'تم الحفظ.':'تعذّر التخزين. التغييرات متاحة خلال هذه الزيارة فقط.');
 }
});
render();
