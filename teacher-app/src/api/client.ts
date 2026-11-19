export class ApiError extends Error {
 constructor(public status:number,message:string,public errors:Record<string,string[]>={}){super(message)}
}
export async function request<T=any>(path:string,token?:string,method='GET',body?:unknown):Promise<T>{
 const response=await fetch('/api'+path,{signal:AbortSignal.timeout(20000),method,credentials:'omit',cache:'no-store',headers:{Accept:'application/json',...(token?{Authorization:'Bearer '+token}:{}),...(body?{'Content-Type':'application/json'}:{})},body:body?JSON.stringify(body):undefined});
 const json=response.status===204?null:await response.json().catch(()=>null);
 if(!response.ok)throw new ApiError(response.status,json?.message||'تعذر إتمام الطلب.',json?.errors||{});
 return json;
}
