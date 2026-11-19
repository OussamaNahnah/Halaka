import { createApp } from 'vue';
import { createRouter,createWebHashHistory } from 'vue-router';
import '@fontsource/tajawal/arabic-400.css';import '@fontsource/tajawal/arabic-500.css';import '@fontsource/tajawal/arabic-700.css';import '@fontsource/tajawal/arabic-800.css';
import './style.css';import App from './App.vue';
import Login from './screens/Login.vue';import Home from './screens/Home.vue';import Editor from './screens/Editor.vue';import Roster from './screens/Roster.vue';import Worksheet from './screens/Worksheet.vue';import Profile from './screens/Profile.vue';
import { state,boot,flush,changed,flushRevocations,run,call,startAutoUploads,uploadPendingAutomatically,prepare,hasUnsentSession,stopAutoUploads } from './store';
export const router=createRouter({history:createWebHashHistory(import.meta.env.BASE_URL),routes:[{path:'/',component:Home},{path:'/login',component:Login},{path:'/new',component:Editor},{path:'/session/:key',component:Roster},{path:'/session/:key/edit',component:Editor},{path:'/session/:key/student/:student',component:Worksheet},{path:'/profile',component:Profile}]});
router.beforeEach(async(to,from)=>{
 if(!state.auth&&to.path!='/login')return '/login';
 if(state.auth&&to.path==='/login')return '/';
 if(to.path==='/new'){const active=hasUnsentSession();if(active){state.message='أكمل الحصة الحالية وأرسلها أولًا.';return '/session/'+active.key}}
 if(from.params.student){const d=state.drafts.find(x=>x.key===from.params.key),w=d?.students.find(x=>x.student_id===Number(from.params.student));if(d&&w?.composer?.open&&(w.composer.surah_name||w.composer.to_ayah)){if(!confirm('يوجد واجب لم تضغط إضافة لحفظه. هل تريد التخلّي عنه والانتقال؟'))return false;w.composer=undefined;changed(d)}}
 try{await flush()}catch{return false}
 return true;
});
window.addEventListener('beforeunload',e=>{if(state.save!=='saved'||hasUnsentSession()||state.queue.length){e.preventDefault();e.returnValue=''}});
window.addEventListener('offline',()=>{state.online=false;stopAutoUploads()});
window.addEventListener('online',()=>{state.online=true;void run(async()=>{await flushRevocations();if(state.auth){await call('/profile');await uploadPendingAutomatically();if(state.auth)await prepare();startAutoUploads()}})});
void boot().then(async()=>{createApp(App).use(router).mount('#app');startAutoUploads();if(state.auth&&navigator.onLine)await run(async()=>{await uploadPendingAutomatically();if(state.auth)await prepare()})});
