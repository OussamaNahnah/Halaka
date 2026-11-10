'use strict';

// Choose a group first; its members are loaded on the next screen.
function editor(){
 const s=data.sessions.find(s=>s.id===editing);
 shell(`${screenHeader(s?'تعديل الحصة':'إنشاء حصة','list','الرجوع إلى قائمة الحصص')}<form id="session-form" class="card form"><div class="fields"><label>التاريخ<input name="date" type="date" required value="${s?.date||day(0)}"></label><label>الوقت<input name="time" type="time" required value="${s?.time||new Date().toTimeString().slice(0,5)}"></label></div><label>المجموعة<select name="group" required><option value="">اختر مجموعة</option>${groups.map(g=>`<option value="${g.id}" ${s?.group===g.id?'selected':''}>${escapeHTML(g.name)}</option>`).join('')}</select></label><button class="primary">${s?'حفظ':'إنشاء الحصة'}</button></form>`);
}
