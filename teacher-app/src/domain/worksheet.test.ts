import { it,expect } from 'vitest';
import { assessmentRows,canFinishEvaluation } from './worksheet';
import type {Assignment,Attempt} from './types';
const passage:Assignment={id:12,student_id:1,surah_name:'الفاتحة',from_ayah:1,to_ayah:5,type:'revision',is_passed:false};
it('shows a direct empty row without creating an assessment or a zero grade',()=>{const attempts:Attempt[]=[];const rows=assessmentRows([passage],attempts);expect(rows).toHaveLength(1);expect(rows[0].attempt).toBeUndefined();expect(attempts).toEqual([])});
it('keeps the row key stable when the teacher starts entering a grade',()=>{const empty=assessmentRows([passage],[]);const attempt:Attempt={client_uuid:'new',quran_assignment_id:12,grade:'-2.5'};const entered=assessmentRows([passage],[attempt]);expect(entered[0].key).toBe(empty[0].key);expect(entered).toHaveLength(1);expect(entered[0].attempt?.grade).toBe('-2.5')});
it('retains saved assessments even when a passage is passed or archived',()=>{const saved:Attempt={id:10,quran_assignment_id:12,grade:7,comment:'احتفظ بالملاحظة'};const rows=assessmentRows([],[saved]);expect(rows[0].attempt).toEqual(saved)});

it('does not finish untouched or cleared evaluations',()=>{expect(canFinishEvaluation([])).toBe(false);expect(canFinishEvaluation([{grade:''},{grade:' ',comment:' '}])).toBe(false)});
it('requires a grade for notes and repeat decisions',()=>{expect(canFinishEvaluation([{grade:'',comment:'مراجعة'}])).toBe(false);expect(canFinishEvaluation([{grade:'',needs_repeat:true}])).toBe(false);expect(canFinishEvaluation([{grade:'',needs_repeat:false}])).toBe(false)});
it('accepts zero and decimal grades without requiring notes or repeat',()=>{expect(canFinishEvaluation([{grade:0}])).toBe(true);expect(canFinishEvaluation([{grade:'-2.75',needs_repeat:false}])).toBe(true);expect(canFinishEvaluation([{grade:'bad'}])).toBe(false);expect(canFinishEvaluation([{grade:2},{grade:'',comment:'أكمل'}])).toBe(false)});
