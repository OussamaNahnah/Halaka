import 'fake-indexeddb/auto';
import { IDBFactory } from 'fake-indexeddb';
import {openDB} from 'idb';
import {beforeEach,afterEach,it,expect,vi} from 'vitest';
import {fixture,acknowledgement} from '../domain/fixtures';
import type {Cache} from '../domain/types';
class LocalMemoryStorage {
 data=new Map<string,string>();get length(){return this.data.size}getItem(k:string){return this.data.get(k)??null}setItem(k:string,v:string){this.data.set(k,String(v))}removeItem(k:string){this.data.delete(k)}clear(){this.data.clear()}key(i:number){return [...this.data.keys()][i]??null}
}
let storage:typeof import('./db')['storage'],app:typeof import('../store');
const auth={token:'test-token',profile:{id:1,teacher_id:1,name:'Teacher',email:'teacher@example.test'}};
const classroom=():Cache=>({owner:1,revision:5,preparedAt:'now',groups:[{id:1,name:'النور',students_count:1,suggested:true}],suggestedGroupId:1,rosters:{1:[{id:1,name:'يوسف'}]},assignments:{1:[{id:12,student_id:1,surah_name:'الملك',from_ayah:1,to_ayah:100,type:'revision',is_passed:false}]},surahs:[],sessions:[]});
beforeEach(async()=>{vi.resetModules();vi.stubGlobal('indexedDB',new IDBFactory());vi.stubGlobal('localStorage',new LocalMemoryStorage());vi.stubGlobal('navigator',{onLine:true});vi.stubGlobal('fetch',vi.fn());storage=(await import('./db')).storage;app=await import('../store');Object.assign(app.state,{auth:structuredClone(auth),cache:classroom()})});
afterEach(()=>app.stopAutoUploads());
it('persists only profile and JWT; page reload loses temporary school data',async()=>{
 await storage.setAuth(auth);await storage.putDraft(fixture());await storage.putCache(classroom());await storage.backup(fixture());await storage.setTheme('dark');
 expect(localStorage.length).toBe(1);expect(localStorage.getItem('halaka-teacher-auth')).not.toContain('يوسف');
 vi.resetModules();const fresh=(await import('./db')).storage;
 expect(await fresh.auth()).toEqual(auth);expect(await fresh.drafts(1)).toEqual([]);expect(await fresh.cache(1)).toBeUndefined();expect(await fresh.queue(1)).toEqual([]);expect(await fresh.backups(1)).toEqual([]);expect(await fresh.theme()).toBe('light');
});
it('migrates login and deletes the existing IndexedDB school data',async()=>{
 const db=await openDB('halaka-teacher-v1',1,{upgrade(db){for(const name of ['auth','drafts','queue','cache','backups','preferences'])db.createObjectStore(name)}});
 await db.put('auth',auth,'current');await db.put('drafts',fixture(),'old-work');await db.put('cache',classroom(),'school');db.close();
 expect(await storage.auth()).toEqual(auth);expect(localStorage.length).toBe(1);
 await vi.waitFor(async()=>expect((await indexedDB.databases()).some(db=>db.name==='halaka-teacher-v1')).toBe(false));
});
it('keeps the current login when removing an older account’s stored data',async()=>{
 await storage.setAuth(auth);const db=await openDB('halaka-teacher-v1',1,{upgrade(db){db.createObjectStore('auth')}});await db.put('auth',{...auth,token:'old-token'},'current');db.close();expect(await storage.auth()).toEqual(auth);
});
it('retains unfinished work in page memory and isolates accounts',async()=>{await storage.putDraft(fixture());expect(await storage.draft('draft-one',2)).toBeUndefined();expect(await storage.drafts(1)).toHaveLength(1);expect(localStorage.length).toBe(0)});
it('automatically retries the same upload after a slow connection without writing school data to disk',async()=>{
 const draft=fixture();app.state.drafts=[draft];await storage.setAuth(auth);await app.queueDraft(draft);const bodies:string[]=[];
 vi.mocked(fetch).mockImplementation(async(_url,init)=>{bodies.push(init!.body as string);if(bodies.length===1)throw new TypeError('slow');return new Response(JSON.stringify(acknowledgement()),{status:201})});
 await app.uploadPendingAutomatically();expect(await storage.queue(1)).toHaveLength(1);await app.uploadPendingAutomatically();expect(bodies[0]).toBe(bodies[1]);expect(await storage.queue(1)).toHaveLength(0);expect(await storage.drafts(1)).toHaveLength(0);expect(localStorage.length).toBe(1);expect(await indexedDB.databases()).toEqual([]);
});
it('does not auto-finish an unfinished session or overwrite conflicts',async()=>{const draft=fixture();app.state.drafts=[draft];await storage.putDraft(draft);await app.uploadPendingAutomatically();expect(fetch).not.toHaveBeenCalled();await app.queueDraft(draft);vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify({message:'conflict'}),{status:409}));await app.uploadPendingAutomatically();expect((await storage.queue(1))[0].state).toBe('conflict');await app.uploadPendingAutomatically();expect(fetch).toHaveBeenCalledTimes(1)});
it('clears the persistent login on logout while keeping only current-page recovery state',async()=>{await storage.setAuth(auth);await storage.putDraft(fixture());await storage.clearAuth(auth.token);expect(localStorage.length).toBe(0);expect(await storage.auth()).toBeUndefined();expect(await storage.drafts(1)).toHaveLength(1)});
