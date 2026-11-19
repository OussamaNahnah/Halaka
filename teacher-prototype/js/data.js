'use strict';

// Sample records and local storage. Never store credentials here.
const key='halaqa-teacher-demo-v1';
const students=[{id:1,name:'يوسف أحمد'},{id:2,name:'مريم علي'},{id:3,name:'إبراهيم حسن'},{id:4,name:'آمنة عمر'}];
const groups=[{id:'a',name:'النور · صباحًا',students:[1,2,3]},{id:'b',name:'الهدى · مساءً',students:[2,4]}];
const day=(offset)=>{const d=new Date();d.setDate(d.getDate()+offset);return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;};
function seed(){return {sessions:[{id:1,date:day(-7),time:'09:00',status:'completed',group:'a',students:[1,2,3]},{id:2,date:day(0),time:'09:00',group:'a',students:[1,2,3]},{id:3,date:day(2),time:'15:30',group:'b',students:[2,4]}],attendance:{},assignments:[{id:10,session:1,student:1,surah:'Al-Mulk',from:1,to:10,type:'new_memorization',repeat:null},{id:11,session:1,student:1,surah:'An-Naba',from:1,to:20,type:'revision',repeat:null},{id:12,session:1,student:2,surah:'Al-Mulk',from:1,to:10,type:'revision',repeat:null},{id:13,session:1,student:3,surah:'Ar-Rahman',from:1,to:13,type:'new_memorization',repeat:null}],assessments:[]};}
let data;try{data=JSON.parse(localStorage.getItem(key))||seed();}catch{data=seed();}
let storageStatus='saved';
function updateSaveIndicator(){
 const indicator=document.querySelector('#save-indicator');
 if(!indicator)return;
 indicator.textContent=storageStatus==='saved'?'محفوظ على هذا الجهاز':storageStatus==='saving'?'جارٍ الحفظ…':'لم يُحفظ · أبقِ الصفحة مفتوحة';
 indicator.dataset.state=storageStatus;
}
function save(){
 storageStatus='saving';updateSaveIndicator();
 try{localStorage.setItem(key,JSON.stringify(data));storageStatus='saved';}
 catch{storageStatus='error';}
 updateSaveIndicator();
}
for(const entry of data.sessions){entry.status??='in_progress';}
// Persist initial sample data as well; failure remains visible instead of claiming success.
save();

function id(){return Date.now()+Math.floor(Math.random()*1000);}
const surahs=[["الفاتحة",7],["البقرة",286],["آل عمران",200],["النساء",176],["المائدة",120],["الأنعام",165],["الأعراف",206],["الأنفال",75],["التوبة",129],["يونس",109],["هود",123],["يوسف",111],["الرعد",43],["ابراهيم",52],["الحجر",99],["النحل",128],["الإسراء",111],["الكهف",110],["مريم",98],["طه",135],["الأنبياء",112],["الحج",78],["المؤمنون",118],["النور",64],["الفرقان",77],["الشعراء",227],["النمل",93],["القصص",88],["العنكبوت",69],["الروم",60],["لقمان",34],["السجدة",30],["الأحزاب",73],["سبإ",54],["فاطر",45],["يس",83],["الصافات",182],["ص",88],["الزمر",75],["غافر",85],["فصلت",54],["الشورى",53],["الزخرف",89],["الدخان",59],["الجاثية",37],["الأحقاف",35],["محمد",38],["الفتح",29],["الحجرات",18],["ق",45],["الذاريات",60],["الطور",49],["النجم",62],["القمر",55],["الرحمن",78],["الواقعة",96],["الحديد",29],["المجادلة",22],["الحشر",24],["الممتحنة",13],["الصف",14],["الجمعة",11],["المنافقون",11],["التغابن",18],["الطلاق",12],["التحريم",12],["الملك",30],["القلم",52],["الحاقة",52],["المعارج",44],["نوح",28],["الجن",28],["المزمل",20],["المدثر",56],["القيامة",40],["الانسان",31],["المرسلات",50],["النبإ",40],["النازعات",46],["عبس",42],["التكوير",29],["الإنفطار",19],["المطففين",36],["الإنشقاق",25],["البروج",22],["الطارق",17],["الأعلى",19],["الغاشية",26],["الفجر",30],["البلد",20],["الشمس",15],["الليل",21],["الضحى",11],["الشرح",8],["التين",8],["العلق",19],["القدر",5],["البينة",8],["الزلزلة",8],["العاديات",11],["القارعة",11],["التكاثر",8],["العصر",3],["الهمزة",9],["الفيل",5],["قريش",4],["الماعون",7],["الكوثر",3],["الكافرون",6],["النصر",3],["المسد",5],["الإخلاص",4],["الفلق",5],["الناس",6]];
