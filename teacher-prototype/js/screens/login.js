'use strict';

// Screen: login. Use shared components and styles.
function login(){app.innerHTML=`<main class="login login-minimal"><section class="card"><div class="brand">حلقة<span style="color:#b89958">.</span></div><h1>دخول الأستاذ</h1><form id="login"><label>البريد الإلكتروني<input name="email" type="email" autocomplete="username" required placeholder="teacher@example.com"></label><label>كلمة المرور<input name="password" type="password" autocomplete="current-password" required placeholder="كلمة مرور تجريبية"></label><button class="primary">دخول</button></form><button class="quiet demo-button" data-action="demo">جرّب دون تسجيل</button><p class="muted login-hint">نسخة تجريبية · لا تستخدم كلمة مرور حقيقية</p></section></main>`;}
