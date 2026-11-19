import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue';
export default defineConfig({base:'/teacher/',plugins:[vue()],build:{outDir:'../backend/public/teacher',emptyOutDir:true},server:{proxy:{'/api':{target:process.env.TEACHER_API_PROXY||'http://127.0.0.1',changeOrigin:true}}}});
