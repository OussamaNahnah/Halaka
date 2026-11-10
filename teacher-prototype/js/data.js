'use strict';

// Sample records and local storage. Never store credentials here.
const key='halaqa-teacher-demo-v1';
const students=[{id:1,name:'يوسف أحمد'},{id:2,name:'مريم علي'},{id:3,name:'إبراهيم حسن'},{id:4,name:'آمنة عمر'}];
const groups=[{id:'a',name:'النور · صباحًا',students:[1,2,3]},{id:'b',name:'الهدى · مساءً',students:[2,4]}];
const day=(offset)=>{const d=new Date();d.setDate(d.getDate()+offset);return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;};
function seed(){return {sessions:[{id:1,date:day(-7),time:'09:00',group:'a',students:[1,2,3]},{id:2,date:day(0),time:'09:00',group:'a',students:[1,2,3]},{id:3,date:day(2),time:'15:30',group:'b',students:[2,4]}],attendance:{},assignments:[{id:10,session:1,student:1,surah:'Al-Mulk',from:1,to:10,type:'new_memorization',repeat:null},{id:11,session:1,student:1,surah:'An-Naba',from:1,to:20,type:'revision',repeat:null},{id:12,session:1,student:2,surah:'Al-Mulk',from:1,to:10,type:'revision',repeat:null},{id:13,session:1,student:3,surah:'Ar-Rahman',from:1,to:13,type:'new_memorization',repeat:null}],assessments:[]};}
let data;try{data=JSON.parse(localStorage.getItem(key))||seed();}catch{data=seed();}
function save(){try{localStorage.setItem(key,JSON.stringify(data));}catch{toast('التخزين في المتصفح غير متاح. ستبقى التغييرات خلال هذه الزيارة فقط.');}}
function id(){return Date.now()+Math.floor(Math.random()*1000);}
const surahs=[['Al-Fatiha',7],['Al-Mulk',30],['An-Naba',40],['Ar-Rahman',78],['Al-Ikhlas',4],['Al-Falaq',5],['An-Nas',6]];
