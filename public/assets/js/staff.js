// M4/M6: keep the staff board up to date (every 10 seconds) and block double taps.
// The server refuses double taps anyway; this only avoids the extra round trip.
(function () {
  'use strict';

  const REFRESH_MS = 10000;
  let sending = false;

  document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-staff-action]');
    if (!form) return;
    if (sending) {
      event.preventDefault();
      return;
    }
    sending = true;
    // Disable after the browser has read the form, so the POST still goes out.
    setTimeout(() => {
      form.querySelectorAll('button').forEach((button) => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
      });
    }, 0);
  });

  async function refresh() {
    const board = document.getElementById('board');
    if (!board || document.hidden || sending) return;
    try {
      const response = await fetch('/staff/board.php', { cache: 'no-store', headers: { Accept: 'text/html' } });
      if (response.status === 401) {
        window.location.reload(); // logged out (e.g. after 8 hours): the page sends us to the login
        return;
      }
      if (!response.ok || sending) return;
      const holder = document.createElement('template');
      holder.innerHTML = (await response.text()).trim(); // markup from our own server, already escaped
      const fresh = holder.content.getElementById('board');
      if (fresh && fresh.dataset.version !== board.dataset.version) {
        board.replaceWith(fresh);
      }
    } catch (err) {
      // Network hiccup: try again at the next refresh.
    }
  }

  // Coming back with the browser's Back button restores the old page: allow taps again.
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      sending = false;
      document.querySelectorAll('form[data-staff-action] button').forEach((button) => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
      });
    }
  });

  setInterval(refresh, REFRESH_MS);
})();
