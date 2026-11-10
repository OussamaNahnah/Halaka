'use strict';

// Profile reuses the shared shell, branding, and local theme preference.
function profileIcon(name) {
  const paths = {
    moon: '<path d="M20.7 13A9 9 0 0 1 11 3.3 9 9 0 1 0 20.7 13Z"/>',
    lock: '<rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
    logout: '<path d="M9 4H5v16h4M10 12h11m-4-4 4 4-4 4"/>'
  };
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name]}</svg>`;
}

function profile() {
  shell(`<div class="profile-title-row"><h1>حسابي</h1>${appLogo()}</div>
    <section class="profile-identity" aria-label="معلومات الحساب">
      <div class="profile-avatar" aria-hidden="true">ع</div>
      <div><h2>الأستاذ عمر</h2><span class="muted">أستاذ القرآن الكريم</span></div>
      <span class="badge">تجريبي</span>
    </section>
    <h2 class="settings-heading">الإعدادات</h2>
    <section class="card profile-settings polished-settings">
      <label class="theme-row" for="dark-mode"><span class="setting-icon">${profileIcon('moon')}</span><span class="setting-copy">الوضع الداكن</span><input id="dark-mode" class="theme-switch" type="checkbox" role="switch" ${darkMode ? 'checked' : ''}></label>
      <details class="password-settings">
        <summary><span class="setting-icon">${profileIcon('lock')}</span><span class="setting-copy">تغيير كلمة المرور</span><span class="setting-chevron" aria-hidden="true">‹</span></summary>
        <form id="password-form" class="profile-password-form">
          <p class="profile-demo-note">محاكاة فقط؛ لا تستخدم كلمة مرور حقيقية.</p>
          <label>كلمة المرور الجديدة<input name="new_password" type="password" autocomplete="new-password" minlength="8" required placeholder="8 أحرف على الأقل" aria-describedby="password-help"></label>
          <label>تأكيد كلمة المرور<input name="confirm_password" type="password" autocomplete="new-password" minlength="8" required placeholder="أعد كتابة كلمة المرور"></label>
          <span id="password-help" class="muted">لن تُحفظ كلمة المرور في هذه النسخة.</span>
          <button class="primary">حفظ التغيير</button>
        </form>
      </details>
    </section>
    <button class="profile-signout danger" data-action="logout">${profileIcon('logout')}<span>تسجيل الخروج</span></button>`);
}
