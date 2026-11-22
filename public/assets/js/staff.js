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

  // ---------- The toast with the result of the last tap ----------
  // Shown at the bottom of the screen, announced to screen readers, hidden after 10 seconds
  // (not while the pointer or the keyboard focus is on it) or with its close button.
  const TOAST_MS = 10000;
  const toast = document.querySelector('[data-toast]');
  if (toast) {
    const text = toast.querySelector('.toast__text');
    const html = text.innerHTML; // markup from our own server, already escaped
    // A live region that is filled when the page loads isn't always announced:
    // empty it and put the message back a moment later.
    text.innerHTML = '';
    setTimeout(() => { text.innerHTML = html; }, 100);

    let timer = null;
    const hide = () => { toast.hidden = true; };
    const start = () => { clearTimeout(timer); timer = setTimeout(hide, TOAST_MS); };
    const pause = () => clearTimeout(timer);

    toast.querySelector('[data-toast-close]').addEventListener('click', hide);
    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', start);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', start);
    start();
  }
})();
