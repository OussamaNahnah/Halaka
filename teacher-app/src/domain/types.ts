export interface Profile { id:number;teacher_id:number;name:string;email:string }
export interface Auth { profile:Profile;token:string }
export interface Group { id:number;name:string;students_count:number;suggested:boolean }
export interface Student { id:number;name:string }
export interface Surah { id:number;name:string;verses:number }
export interface Assignment { assigned_here?:boolean;id?:number;client_uuid?:string|null;student_id:number;surah_name:string;from_ayah:number;to_ayah:number;type:'new_memorization'|'revision';is_passed:boolean;assessments?:{grade:number;timestamp:string;session_id:number}[] }
export interface Attempt { needs_repeat?:boolean;is_passed?:boolean; id?:number;client_uuid?:string|null;quran_assignment_id?:number;assignment_client_uuid?:string;assignment_archived?:boolean;grade:number|string;comment?:string|null }
export interface Work { student_id:number;name:string;archived?:boolean;evaluated_at:string|null;attendance_status?:string;assignments:Assignment[];assessments:Attempt[];removed_assignment_ids:number[];composer?:{open:boolean;surah_name:string;from_ayah:string;to_ayah:string;type:'new_memorization'|'revision'} }
export interface ServerSession { id:number;client_uuid:string|null;revision:number;group:{id:number;name:string;archived:boolean}|null;timestamp:string;status:'in_progress'|'completed';students:Work[] }
export interface Cache { owner:number;revision:number;preparedAt:string;status_revision?:number;groups:Group[];suggestedGroupId:number|null;rosters:Record<number,Student[]>;assignments:Record<number,Assignment[]>;surahs:Surah[];sessions:ServerSession[] }
export interface Payload { submission_id:string;client_uuid:string;base_sync_revision:number;base_revision?:number;group_id:number;timestamp:string;status:'completed';students:{student_id:number;evaluated_at:string|null;assignments:Omit<Assignment,'student_id'|'assessments'>[];assessments:Omit<Attempt,'assignment_archived'>[];removed_assignment_ids:number[]}[] }
export interface Draft { key:string;owner:number;serverId?:number;client_uuid:string;base_revision?:number;base_sync_revision:number;group_id:number;timestamp:string;status:'in_progress'|'completed';sync:'local_only'|'pending_upload'|'uploading'|'synced'|'conflict';students:Work[];edit:number;lastError?:string;updatedAt:string;acknowledgedAt?:string }
export interface QueueItem { id:string;owner:number;draftKey:string;edit:number;serverId?:number;payload:Payload;state:'pending_upload'|'uploading'|'conflict' }
export interface Ack { assignment_states?:{id:number;student_id:number;client_uuid?:string|null;is_passed:boolean}[]; data:ServerSession;meta:{sync_revision:number};id_mapping:{session_id:number;assignments:Record<string,number>;assessments:Record<string,number>} }
