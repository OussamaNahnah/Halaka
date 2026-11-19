import { state } from './store';
// Retire prior offline workers/caches; only profile and JWT remain persistent.
export async function registerOffline(){
 if('serviceWorker' in navigator){for(const registration of await navigator.serviceWorker.getRegistrations())if(new URL(registration.scope).pathname.startsWith('/teacher/'))await registration.unregister()}
 if(typeof caches!=='undefined')for(const name of await caches.keys())if(name.startsWith('halaka-teacher-shell-')||name.includes('/teacher/'))await caches.delete(name);
 state.offlineReady=false;state.updateReady=false;
}
export function activateUpdate(){location.reload()}
