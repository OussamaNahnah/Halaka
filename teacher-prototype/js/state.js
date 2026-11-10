'use strict';

// Shared UI state; classic scripts support direct file:// opening.
const app=document.querySelector('#app');
let logged=false,view='list',sessionId=null,studentId=null,tab='today',editing=null,timer;
