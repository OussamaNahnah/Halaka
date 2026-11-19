import type { Assignment,Attempt } from './types';
export interface AssessmentRow {key:string;assignment?:Assignment;attempt?:Attempt}
export function matchesAssignment(attempt:Attempt,assignment:Assignment):boolean {
 return Boolean((assignment.id&&attempt.quran_assignment_id===assignment.id)||(assignment.client_uuid&&attempt.assignment_client_uuid===assignment.client_uuid));
}
// Empty rows are presentation only: looking at a worksheet never creates grades.
export function assessmentRows(assignments:Assignment[],attempts:Attempt[]):AssessmentRow[]{
 const rows:AssessmentRow[]=[],seen=new Set<Attempt>();
 for(const assignment of assignments){
  const found=attempts.filter(a=>matchesAssignment(a,assignment));
  const key='assignment-'+(assignment.id||assignment.client_uuid);
  if(!found.length)rows.push({key,assignment});
  found.forEach((attempt,index)=>{seen.add(attempt);rows.push({key:index?key+'-'+(attempt.id||attempt.client_uuid):key,assignment,attempt})});
 }
 for(const attempt of attempts)if(!seen.has(attempt))rows.push({key:'saved-'+(attempt.id||attempt.client_uuid),attempt});
 return rows;
}

// Looking at a worksheet or adding tomorrow's homework is not an evaluation.
export function canFinishEvaluation(attempts:Attempt[]):boolean {
 const entered=attempts.filter(a=>String(a.grade).trim()!==''||Boolean(a.id)||Boolean(a.comment?.trim())||a.needs_repeat===true);
 return entered.length>0&&entered.every(a=>String(a.grade).trim()!==''&&Number.isFinite(Number(a.grade)));
}
