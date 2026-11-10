'use strict';

// Shared layout, navigation, theme, toast, and assignment form.
function toast(msg){const el=document.querySelector('#toast');el.textContent=msg;el.style.display='block';clearTimeout(timer);timer=setTimeout(()=>el.style.display='none',3200);}
let darkMode=false;
try{darkMode=localStorage.getItem('halaqa-theme')==='dark';}catch{}
function applyTheme(){document.documentElement.dataset.theme=darkMode?'dark':'light';}
applyTheme();
const icons={home:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 10 9-7 9 7v10H3Z"/><path d="M9 20v-7h6v7"/></svg>',profile:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>'};
function shell(content){app.innerHTML=`<header class="topbar"><div class="brand">حلقة<span style="color:#b89958">.</span><small>مساحة الأستاذ</small></div></header><main class="shell">${content}</main><nav class="bottom-nav" aria-label="التنقل الرئيسي"><button data-action="list" ${view!=='profile'?'aria-current="page"':''}>${icons.home}<span>الرئيسية</span></button><button data-action="profile" ${view==='profile'?'aria-current="page"':''}>${icons.profile}<span>حسابي</span></button></nav>`;}
function assignmentForm(){return `<form id="assignment-form" class="card"><h3>إضافة مقطع</h3><p class="muted">أضف مقاطع الحفظ والمراجعة، مقطعًا بعد آخر.</p><label>السورة<select name="surah">${surahs.map(([n])=>`<option value="${n}">${surahNames[n]||n}</option>`).join('')}</select></label><div class="fields"><label>من الآية<input name="from" type="number" min="1" required value="1"></label><label>إلى الآية<input name="to" type="number" min="1" required value="7"></label></div><label>النوع<select name="type"><option value="new_memorization">حفظ جديد</option><option value="revision">مراجعة</option></select></label><button class="primary">+ إضافة واجب</button><p class="muted">يُحفظ كل واجب فورًا في هذا المتصفح. تتضمن التجربة سبع سور فقط.</p></form>`;}
