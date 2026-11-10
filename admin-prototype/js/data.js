'use strict';
const storageKey='halaka-admin-prototype-v1';
function initialData(){return {
 users:[{id:1,name:'عمر أحمد',email:'omar@example.com'},{id:2,name:'خالد حسن',email:'khalid@example.com'}],
 teachers:[{id:1,user_id:1},{id:2,user_id:2}],
 students:[{id:1,name:'يوسف أحمد',user_id:null,parent_phone:'+212600000001'},{id:2,name:'مريم علي',user_id:null,parent_phone:'+212600000002'},{id:3,name:'إبراهيم حسن',user_id:null,parent_phone:'+212600000001'},{id:4,name:'آمنة عمر',user_id:null,parent_phone:''}],
 groups:[{id:1,name:'النور · صباحًا',studentIds:[1,2,3]},{id:2,name:'الهدى · مساءً',studentIds:[2,4]}]
};}
let data,storageOk=true;
try{data=JSON.parse(localStorage.getItem(storageKey))||initialData();}catch{data=initialData();storageOk=false;}
function persist(){try{localStorage.setItem(storageKey,JSON.stringify(data));storageOk=true;}catch{storageOk=false;}return storageOk;}
const uid=()=>Date.now()+Math.floor(Math.random()*10000);
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
// Migrate previous demo records without discarding names or memberships.
data.users??=[];
for(const teacher of data.teachers){
 if(!teacher.user_id){const userId=teacher.id;data.users.push({id:userId,name:teacher.name,email:teacher.email});teacher.user_id=userId;}
 delete teacher.name;delete teacher.email;
}
for(const student of data.students){student.user_id??=null;student.parent_phone??='';delete student.number;delete student.student_number;}
for(const group of data.groups)delete group.teacherId;
function teacherUser(teacher){return data.users.find(user=>user.id===teacher.user_id);}
function recordsFor(page){return page==='teachers'?data.teachers.map(teacher=>({...teacher,name:teacherUser(teacher)?.name||'',email:teacherUser(teacher)?.email||''})):data[page];}
function groupsForStudent(id){return data.groups.filter(g=>g.studentIds.includes(id)).map(g=>g.name).join('، ')||'لم يُضف إلى مجموعة';}
