'use strict';

// Assess earlier work; assignments created today are prepared for a later session.
function assessmentCard(assignment) {
  const assessment = data.assessments.find(item => item.assignment === assignment.id && item.session === sessionId);
  const original = assignment.repeat && data.assessments.find(item => item.id === assignment.repeat);
  return `<article class="assessment-item">
    <form data-assess="${assignment.id}" class="compact-assessment">
      <div class="assessment-entry"><div class="assessment-passage"><strong><span class="passage-type">${typeLabel(assignment.type)}</span> · ${escapeHTML(surahNames[assignment.surah]||assignment.surah)}</strong><span>الآيات <bdi>${assignment.from}–${assignment.to}</bdi>${original ? ` · إعادة · العلامة السابقة ${escapeHTML(original.grade)}` : ''}</span></div><label class="compact-grade">العلامة<input name="grade" type="number" step="any" required value="${assessment?.grade ?? ''}" placeholder="—"></label></div>
      <details class="assessment-comment" ${assessment?.comment?'open':''}><summary>تعليق اختياري</summary><input name="comment" aria-label="تعليق على ${escapeHTML(passage(assignment))}" value="${escapeHTML(assessment?.comment || '')}" placeholder="أضف ملاحظة"></details>

    </form>
    <button class="repeat-action" data-action="repeat-assignment" data-id="${assignment.id}">طلب إعادة تسميع المقطع أو جزء منه</button>
  </article>`;
}

function assignmentCard(assignment) {
  return `<article class="assignment-row"><div><strong><span class="passage-type">${typeLabel(assignment.type)}</span> · ${escapeHTML(passage(assignment))}</strong>${assignment.repeat ? '<span class="muted">إعادة مرتبطة بتقييم</span>' : ''}</div><button class="danger" data-action="remove-assignment" data-id="${assignment.id}" aria-label="حذف واجب ${escapeHTML(passage(assignment))}">حذف</button></article>`;
}

function passageSections(assignments, card) {
  return ['new_memorization', 'revision'].map(type => {
    const items = assignments.filter(item => item.type === type);
    if(!items.length)return '';
    return `<section class="passage-section"><h2>${typeLabel(type)}</h2>${items.length ? `<div class="roster">${items.map(card).join('')}</div>` : '<p class="section-empty">لا توجد مقاطع</p>'}</section>`;
  }).join('');
}

function evaluation() {
  const currentSession = session();
  const assignedToday = data.assignments.filter(item => item.student === studentId && item.session === sessionId);
  const dueToday = data.assignments.filter(item => {
    if (item.student !== studentId) return false;
    const assignedIn = data.sessions.find(previous => previous.id === item.session);
    if (!assignedIn || stamp(assignedIn) >= stamp(currentSession)) return false;
    // Completed work stays in its historical session; explicit repeats are new assignments.
    return !data.assessments.some(assessment => assessment.assignment === item.id && data.sessions.some(previous => previous.id === assessment.session && stamp(previous) < stamp(currentSession)));
  });
  const assessedCount = dueToday.filter(item => data.assessments.some(assessment => assessment.assignment === item.id && assessment.session === sessionId)).length;
  const position = currentSession.students.indexOf(studentId);
  shell(`${screenHeader(studentName(studentId),'details','الرجوع إلى طلاب الحصة',`الطالب ${position+1} من ${currentSession.students.length}`)}
    <div class="evaluation-workspace unified-evaluation">
      <section class="worksheet-section"><h2>تقييم اليوم</h2>${dueToday.length?`<div class="roster">${dueToday.map(assessmentCard).join('')}</div>`:'<p class="section-empty">لا توجد واجبات سابقة للتسميع.</p>'}</section>
      <section class="worksheet-section"><h2>الواجبات التالية <small>${assignedToday.length}</small></h2>${assignedToday.length?`<div class="roster">${assignedToday.map(assignmentCard).join('')}</div>`:'<p class="section-empty">لا توجد واجبات مضافة.</p>'}${assignmentForm()}</section>
    </div>
    <div class="evaluation-bottom"><button class="primary" data-action="finish-student">إنهاء الطالب ✓</button><div class="student-navigation"><button data-action="previous-student" ${position===0?'disabled':''}>→ السابق</button><button data-action="next-student" ${position===currentSession.students.length-1?'disabled':''}>التالي ←</button></div></div>`);
}

// Save entered assessment fields before finishing or navigating; never store passwords.
function saveStudentWork(ignorePending=false){
 const forms=[...app.querySelectorAll('form[data-assess]')];
 const pending=app.querySelector('#assignment-form');
 if(!ignorePending && pending && (pending.elements.from.value!=='1'||pending.elements.to.value!=='7'||pending.elements.surah.value!=='Al-Fatiha'||pending.elements.type.value!=='new_memorization')){toast('اضغط «إضافة» لحفظ الواجب أولًا.');return false;}
 if(!ignorePending && app.querySelector('form[data-repeat]')){toast('احفظ واجب الإعادة أولًا.');return false;}
 for(const form of forms){if(form.elements.grade.value!==''&&!form.reportValidity())return false;}
 for(const form of forms){
  if(form.elements.grade.value===''){if(form.elements.comment.value.trim()){toast('أدخل علامة المقطع لحفظ التعليق.');return false;}continue;}
  const assignment=Number(form.dataset.assess),grade=Number(form.elements.grade.value),comment=form.elements.comment.value;
  if(!Number.isFinite(grade))return false;
  const existing=data.assessments.find(item=>item.assignment===assignment&&item.session===sessionId);
  if(existing){existing.grade=grade;existing.comment=comment;}else data.assessments.push({id:id(),assignment,session:sessionId,grade,comment});
 }
 save();return true;
}
