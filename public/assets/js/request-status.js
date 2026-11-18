// M3: keep the request status up to date (every 5 seconds) and return the shared
// tablet to the archive 60 seconds after the request is ready or closed.
(function () {
  'use strict';

  const POLL_MS = 5000;
  const RETURN_AFTER_S = 60;
  const STEPS = ['new', 'fetching', 'ready'];

  const root = document.getElementById('request');
  if (!root) return;

  const id = root.dataset.requestId;
  const label = document.getElementById('request-label');
  const room = document.getElementById('request-room');
  const countdown = document.getElementById('request-countdown');
  let status = root.dataset.status;
  let pollTimer = null;
  let countdownTimer = null;

  function showSteps() {
    const current = STEPS.indexOf(status) === -1 ? STEPS.length : STEPS.indexOf(status);
    root.querySelectorAll('[data-step]').forEach((li, index) => {
      li.classList.toggle('steps__step--done', index < current);
      li.classList.toggle('steps__step--current', index === current);
      if (index === current) li.setAttribute('aria-current', 'step');
      else li.removeAttribute('aria-current');
    });
  }

  function startCountdown() {
    if (countdownTimer !== null) return;
    let left = RETURN_AFTER_S;
    const tick = () => {
      countdown.textContent = 'Back to the archive in ' + left + ' s';
      if (left <= 0) {
        window.location.href = '/';
        return;
      }
      left -= 1;
    };
    countdown.hidden = false;
    tick();
    countdownTimer = setInterval(tick, 1000);
  }

  function apply(data) {
    status = data.status;
    label.textContent = data.label;
    room.textContent = String(data.fitting_room);
    showSteps();
    if (status === 'ready' || status === 'closed') startCountdown();
  }

  // One polling loop; while the tab is hidden it skips the request but keeps ticking.
  async function poll() {
    pollTimer = null;
    if (!document.hidden) {
      try {
        const response = await fetch('/api/requests.php?id=' + encodeURIComponent(id), {
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });
        if (response.ok) apply(await response.json());
      } catch (err) {
        // Network hiccup: try again at the next poll.
      }
    }
    if (status !== 'closed') pollTimer = setTimeout(poll, POLL_MS);
  }

  if (status === 'ready' || status === 'closed') startCountdown();
  if (status !== 'closed') pollTimer = setTimeout(poll, POLL_MS);
})();
