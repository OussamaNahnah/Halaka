'use strict';

// Assess earlier work; assignments created today are prepared for a later session.
function assessmentCard(assignment) {
  const assessment = data.assessments.find(item => item.assignment === assignment.id && item.session === sessionId);
  const original = assignment.repeat && data.assessments.find(item => item.id === assignment.repeat);
  return `<article class="assessment-item">
    <form data-assess="${assignment.id}" class="compact-assessment">
      <div class="assessment-entry"><div class="assessment-passage"><strong>${escapeHTML(surahNames[assignment.surah]||assignment.surah)}</strong><span>الآيات <bdi>${assignment.from}–${assignment.to}</bdi>${original ? ` · إعادة · العلامة السابقة ${escapeHTML(original.grade)}` : ''}</span></div><label class="compact-grade">العلامة<input name="grade" type="number" step="any" required value="${assessment?.grade ?? ''}" placeholder="—"></label></div>
      <details class="assessment-comment" ${assessment?.comment?'open':''}><summary>تعليق اختياري</summary><input name="comment" aria-label="تعليق على ${escapeHTML(passage(assignment))}" value="${escapeHTML(assessment?.comment || '')}" placeholder="أضف ملاحظة"></details>

    </form>
    ${assessment ? `<button class="repeat-action" data-action="repeat" data-id="${assessment.id}">+ تكليف بإعادة المقطع</button>` : ''}
  </article>`;
}

function assignmentCard(assignment) {
  return `<article class="assignment-row"><div><strong>${escapeHTML(passage(assignment))}</strong>${assignment.repeat ? '<span class="muted">إعادة مرتبطة بتقييم</span>' : ''}</div><button class="danger" data-action="remove-assignment" data-id="${assignment.id}" aria-label="حذف واجب ${escapeHTML(passage(assignment))}">حذف</button></article>`;
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
  const isAssessment = tab !== 'next';
  shell(`${screenHeader(studentName(studentId),'details','الرجوع إلى طلاب الحصة',`الطالب ${position+1} من ${currentSession.students.length}`)}
    <div class="evaluation-switch" aria-label="خطوات تسجيل الطالب"><button data-action="evaluation-step" data-step="today" ${isAssessment?'aria-current="step"':''}><span>1</span> التقييم</button><button data-action="evaluation-step" data-step="next" ${!isAssessment?'aria-current="step"':''}><span>2</span> الواجبات</button></div>
    <div class="evaluation-workspace">
    ${isAssessment ? `<p class="workspace-caption">تسميع الواجب السابق</p>${dueToday.length?passageSections(dueToday,assessmentCard):'<p class="empty">لا توجد مقاطع سابقة للتقييم.<br>يمكنك الانتقال لإضافة الواجبات.</p>'}` : `<p class="workspace-caption">ما الذي سيحضّره الطالب؟</p>${assignedToday.length?passageSections(assignedToday,assignmentCard):'<p class="section-empty">لم تُضف واجبات بعد.</p>'}${assignmentForm()}`}
    </div>
    <div class="evaluation-bottom"><button class="primary" data-action="${isAssessment?'continue-assignments':'finish-student'}">${isAssessment?'حفظ ومتابعة للواجبات ←':'إنهاء الطالب ✓'}</button><div class="student-navigation"><button data-action="previous-student" ${position===0?'disabled':''}>→ الطالب السابق</button><button data-action="next-student" ${position===currentSession.students.length-1?'disabled':''}>الطالب التالي ←</button></div></div>`);
}

// Save entered assessment fields before finishing or navigating; never store passwords.
function saveStudentWork(){
 const forms=[...app.querySelectorAll('form[data-assess]')];
 const pending=app.querySelector('#assignment-form');
 if(pending && (pending.elements.from.value!=='1'||pending.elements.to.value!=='7'||pending.elements.surah.value!=='Al-Fatiha'||pending.elements.type.value!=='new_memorization')){toast('اضغط «إضافة» لحفظ الواجب أولًا.');return false;}
 if(app.querySelector('form[data-repeat]')){toast('احفظ واجب الإعادة أولًا.');return false;}
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
