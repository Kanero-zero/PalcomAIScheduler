/* SCHEDORA AI Login V2: progressive enhancement only, no auth logic. */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#login-form');
  const password = form?.querySelector('#password');
  const toggle = form?.querySelector('[data-password-toggle]');
  const submit = form?.querySelector('[data-test="login-button"]');
  if (toggle && password) {
    toggle.addEventListener('click', () => {
      const visible = password.type === 'password';
      password.type = visible ? 'text' : 'password';
      toggle.setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
      toggle.setAttribute('aria-pressed', String(visible));
    });
  }
  form?.addEventListener('submit', () => {
    if (!form.checkValidity() || !submit) return;
    submit.disabled = true;
    submit.setAttribute('aria-busy', 'true');
    // Native POST remains owned by Laravel Fortify.
  });
});
