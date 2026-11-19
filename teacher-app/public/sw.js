// Cleanup worker for previous installations. The teacher app no longer caches offline data.
self.addEventListener('install',event=>event.waitUntil(self.skipWaiting()));
self.addEventListener('activate',event=>event.waitUntil((async()=>{
 for(const name of await caches.keys())if(name.startsWith('halaka-teacher-shell-')||name.includes('/teacher/'))await caches.delete(name);
 await self.clients.claim();await self.registration.unregister();
})()));
