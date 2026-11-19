<script setup lang="ts">
import { watch,onMounted } from 'vue';import { useRoute,useRouter } from 'vue-router';
import { state,run,retrySave,hasUnsentSession } from './store';
import { registerOffline } from './pwa';
const importBase=import.meta.env.BASE_URL;
const router=useRouter(),route=useRoute();
watch(()=>state.auth,auth=>{if(!auth)void router.replace('/login')});
onMounted(()=>{void run(registerOffline)});
</script>
<template>
 <div class="app-shell">
  <header class="app-header"><div class="brand"><img :src="`${importBase}halaka-wordmark.svg`" alt="حلقة" width="100" height="35"><span>تطبيق الأستاذ</span></div><span class="connection" :class="{offline:!state.online}"><i aria-hidden="true"></i>{{state.online?'متصل':'دون اتصال'}}</span></header>
  <div v-if="state.error" class="notice error" role="alert"><span>{{state.error}}</span><button aria-label="إغلاق التنبيه" @click="state.error=''">×</button></div>
  <div v-if="state.message" class="notice success" role="status">{{state.message}}<button aria-label="إغلاق الرسالة" @click="state.message=''">×</button></div>
  <main id="main"><RouterView :key="route.fullPath"/></main>
  <template v-if="state.auth">
   <div class="save-status" role="status" :class="{error:state.save==='error'}">{{state.save==='saved'?(hasUnsentSession()?'الحصة الحالية في هذه الصفحة فقط — أرسلها قبل الإغلاق':'الحصص المؤكدة محفوظة على الخادم'):state.save==='saving'?'جارٍ الحفظ…':'لم يُحفظ العمل'}} <button v-if="state.save==='error'" @click="run(retrySave)">إعادة الحفظ</button></div>
   <nav class="bottom-nav" aria-label="التنقل الرئيسي"><RouterLink to="/" :aria-current="route.path==='/'?'page':undefined"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10H3Z M9 20v-7h6v7"/></svg>الرئيسية</RouterLink><RouterLink to="/profile"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>حسابي</RouterLink></nav>
  </template>
 </div>
</template>

