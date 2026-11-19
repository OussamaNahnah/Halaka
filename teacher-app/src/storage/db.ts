import { openDB,deleteDB } from 'idb';
import type { Auth,Cache,Draft,QueueItem } from '../domain/types';
const AUTH_KEY='halaka-teacher-auth';
const clone=<T>(value:T):T=>JSON.parse(JSON.stringify(value));
// Current-page state only. Reloading creates empty maps; no school data is persisted.
const drafts=new Map<string,Draft>(),queue=new Map<string,QueueItem>(),backups=new Map<string,any>(),classrooms=new Map<number,Cache>(),revocations=new Set<string>();
let theme='light',migrated=false;
async function migrateAuthAndRemoveOldStorage():Promise<Auth|undefined>{
 let current:Auth|undefined;
 try{const raw=localStorage.getItem(AUTH_KEY);if(raw)current=JSON.parse(raw)}catch{localStorage.removeItem(AUTH_KEY)}
 if(migrated)return current;
 if(typeof indexedDB!=='undefined'){
  const databases=typeof indexedDB.databases==='function'?await indexedDB.databases():null;
  if(!databases||databases.some(db=>db.name==='halaka-teacher-v1')){
   const db=await openDB('halaka-teacher-v1');
   try{
    const legacy=db.objectStoreNames.contains('auth')?await db.get('auth','current'):undefined;
    if(!current&&legacy?.profile&&legacy?.token){current=legacy;localStorage.setItem(AUTH_KEY,JSON.stringify(current))}
    const stores=Array.from(db.objectStoreNames);
    if(stores.length){const tx=db.transaction(stores,'readwrite');for(const name of stores)await tx.objectStore(name).clear();await tx.done}
   }finally{db.close()}
   // Other old tabs may still hold a connection. Their data is already cleared;
   // deleting the empty database completes when those tabs close.
   void deleteDB('halaka-teacher-v1').catch(()=>{});
  }
 }
 migrated=true;return current;
}
export const storage={
 async auth():Promise<Auth|undefined>{return migrateAuthAndRemoveOldStorage()},
 async setAuth(value:Auth){localStorage.setItem(AUTH_KEY,JSON.stringify(value))},
 async clearAuth(token:string){localStorage.removeItem(AUTH_KEY);revocations.add(token)},
 async backup(draft:Draft){const id=crypto.randomUUID();backups.set(id,{id,owner:draft.owner,createdAt:new Date().toISOString(),draft:clone(draft)})},
 async backups(owner:number):Promise<any[]>{return [...backups.values()].filter(x=>x.owner===owner).map(clone)},
 async cache(owner:number):Promise<Cache|undefined>{return classrooms.has(owner)?clone(classrooms.get(owner)!):undefined},
 async putCache(value:Cache){classrooms.set(value.owner,clone(value))},
 async clearCache(owner:number){classrooms.delete(owner)},
 async pruneSynced(owner:number){for(const d of drafts.values())if(d.owner===owner&&d.sync==='synced'&&![...queue.values()].some(q=>q.draftKey===d.key))drafts.delete(d.key)},
 async drafts(owner:number):Promise<Draft[]>{return [...drafts.values()].filter(d=>d.owner===owner).map(clone)},
 async draft(key:string,owner:number):Promise<Draft|undefined>{const d=drafts.get(key);return d?.owner===owner?clone(d):undefined},
 async putDraft(value:Draft){if(value.sync==='synced')drafts.delete(value.key);else drafts.set(value.key,clone(value))},
 async queue(owner:number):Promise<QueueItem[]>{return [...queue.values()].filter(q=>q.owner===owner).map(clone)},
 async enqueue(draft:Draft,item:QueueItem){drafts.set(draft.key,clone(draft));queue.set(item.id,clone(item))},
 async putQueue(item:QueueItem){queue.set(item.id,clone(item))},
 async acknowledge(draft:Draft,id:string){if(draft.sync==='synced')drafts.delete(draft.key);else drafts.set(draft.key,clone(draft));queue.delete(id)},
 async dropQueue(id:string){queue.delete(id)},
 async revocations():Promise<{token:string}[]>{return [...revocations].map(token=>({token}))},
 async revoked(token:string){revocations.delete(token)},
 async theme():Promise<string>{return theme},
 async setTheme(value:string){theme=value}
};
