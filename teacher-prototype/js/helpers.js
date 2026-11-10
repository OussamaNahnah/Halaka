'use strict';

// Shared display helpers. Persisted values remain language-independent.
const escapeHTML=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const session=()=>data.sessions.find(s=>s.id===sessionId);
const groupName=id=>groups.find(g=>g.id===id)?.name||'دون مجموعة';
const studentName=id=>students.find(s=>s.id===id)?.name||'طالب';
const stamp=s=>`${s.date}T${s.time}`;
const format=s=>new Date(stamp(s)).toLocaleDateString('ar',{weekday:'short',month:'short',day:'numeric',year:'numeric'});
const surahNames={'Al-Fatiha':'الفاتحة','Al-Mulk':'الملك','An-Naba':'النبأ','Ar-Rahman':'الرحمن','Al-Ikhlas':'الإخلاص','Al-Falaq':'الفلق','An-Nas':'الناس'};
const passage=a=>`${surahNames[a.surah]||a.surah} · ${a.from}–${a.to}`;
const typeLabel=t=>t==='revision'?'مراجعة':'حفظ جديد';
