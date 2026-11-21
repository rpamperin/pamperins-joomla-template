/* Pamperins SSO: "Log in with Pamperins" buttons on the site and admin login forms */
document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-ptsso-url]');
  if (!button) {
    return;
  }
  event.preventDefault();
  window.location.href = button.getAttribute('data-ptsso-url');
});
