'use strict';

// Shared layout, navigation, theme, toast, and assignment form.
function toast(msg){const el=document.querySelector('#toast');el.textContent=msg;el.style.display='block';clearTimeout(timer);timer=setTimeout(()=>el.style.display='none',3200);}
let darkMode=false;
try{darkMode=localStorage.getItem('halaqa-theme')==='dark';}catch{}
function applyTheme(){document.documentElement.dataset.theme=darkMode?'dark':'light';}
applyTheme();
const icons={home:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 10 9-7 9 7v10H3Z"/><path d="M9 20v-7h6v7"/></svg>',profile:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>'};
function shell(content){app.innerHTML=`<main class="shell ${view==='list'?'sessions-shell':''}">${content}</main><div id="save-indicator" class="save-indicator" role="status" aria-live="polite"></div><nav class="bottom-nav" aria-label="التنقل الرئيسي"><button data-action="list" ${view!=='profile'?'aria-current="page"':''}>${icons.home}<span>الرئيسية</span></button><button data-action="profile" ${view==='profile'?'aria-current="page"':''}>${icons.profile}<span>حسابي</span></button></nav>`;updateSaveIndicator();}
function assignmentForm(){return `<details class="card assignment-composer"><summary>+ إضافة واجب</summary><form id="assignment-form"><label>النوع<select name="type"><option value="new_memorization">حفظ جديد</option><option value="revision">مراجعة</option></select></label><label>السورة<select name="surah">${surahs.map(([name])=>`<option value="${name}">${surahNames[name]||name}</option>`).join('')}</select></label><div class="fields"><label>من الآية<input name="from" type="number" min="1" required value="1"></label><label>إلى الآية<input name="to" type="number" min="1" required value="7"></label></div><button class="primary">إضافة</button></form></details>`;}

function appLogo(){return '<span class="compact-logo" aria-label="حلقة">حلقة<span>.</span></span>';}

// Consistent compact heading for nested screens.
function screenHeader(title, backAction, backLabel, subtitle='') {
  return `<header class="screen-header"><div class="session-title-row"><button class="icon-back" data-action="${backAction}" aria-label="${escapeHTML(backLabel)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14 5 7 7-7 7M21 12H3"/></svg></button><h1>${escapeHTML(title)}</h1>${appLogo()}</div>${subtitle?`<p class="screen-subtitle">${escapeHTML(subtitle)}</p>`:''}</header>`;
}
