// M2: the try-on list on the archive page.
// Kept in localStorage until it is sent; cleared after 10 minutes without interaction,
// because the tablet is shared by many customers. The server checks everything again.
(function () {
  'use strict';

  const KEY = 'backroom.tryonList';
  const IDLE_MS = 10 * 60 * 1000;
  const IDLE_CHECK_MS = 30 * 1000;
  const TOUCH_SAVE_MS = 5 * 1000;

  const root = document.getElementById('tryon');
  if (!root) return;

  const max = Number(root.dataset.max || 5);
  const csrf = root.dataset.csrf || '';
  const panel = document.getElementById('tryon-panel');
  const itemsEl = document.getElementById('tryon-items');
  const fullNote = document.getElementById('tryon-full');
  const rooms = document.getElementById('tryon-rooms');
  const submit = document.getElementById('tryon-submit');
  const message = document.getElementById('tryon-message');
  const toggle = document.getElementById('tryon-toggle');
  const countEl = document.getElementById('tryon-count');
  const sendButton = document.getElementById('tryon-send');

  // ---------- storage (falls back to memory if localStorage is blocked) ----------

  let memory = { items: [], touched: 0 };

  function isItem(item) {
    return item && Number.isInteger(item.id) && item.id > 0 && typeof item.title === 'string';
  }

  function load() {
    try {
      const data = JSON.parse(localStorage.getItem(KEY) || 'null');
      if (data && Array.isArray(data.items)) {
        return { items: data.items.filter(isItem).slice(0, max), touched: Number(data.touched) || 0 };
      }
      return { items: [], touched: 0 };
    } catch (err) {
      return memory;
    }
  }

  function save() {
    memory = state;
    try {
      if (state.items.length) localStorage.setItem(KEY, JSON.stringify(state));
      else localStorage.removeItem(KEY);
    } catch (err) {
      // Private mode or storage disabled: the list lives in memory for this page only.
    }
  }

  let state = load();
  const isIdle = () => state.items.length > 0 && Date.now() - state.touched > IDLE_MS;
  if (isIdle()) {
    state = { items: [], touched: 0 };
    save();
  }

  // ---------- list changes ----------

  const has = (id) => state.items.some((item) => item.id === id);

  function add(item) {
    if (has(item.id) || state.items.length >= max) return;
    state.items.push(item);
    state.touched = Date.now();
    save();
    hideMessage();
    render();
  }

  function remove(id) {
    state.items = state.items.filter((item) => item.id !== id);
    state.touched = Date.now();
    save();
    render();
  }

  function clear() {
    state = { items: [], touched: 0 };
    save();
    hideMessage();
    render();
  }

  // Any interaction keeps the list alive; saved at most every few seconds.
  function touch() {
    if (!state.items.length) return;
    const now = Date.now();
    if (now - state.touched > TOUCH_SAVE_MS) {
      state.touched = now;
      save();
    }
  }
  ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach((type) => {
    window.addEventListener(type, touch, { passive: true });
  });

  setInterval(() => {
    state = load();
    if (isIdle()) clear();
  }, IDLE_CHECK_MS);

  // Another tab on the same tablet changed the list.
  window.addEventListener('storage', (event) => {
    if (event.key === KEY) {
      state = load();
      render();
    }
  });

  // ---------- drawing ----------

  function itemRow(item) {
    const li = document.createElement('li');
    li.className = 'tryon__item';

    const img = document.createElement('img');
    img.src = item.photo_url;
    img.alt = item.title;
    img.width = 40;
    img.height = 50;

    const text = document.createElement('span');
    text.className = 'tryon__item-text';
    const title = document.createElement('strong');
    title.textContent = item.title;
    text.append(title, document.createTextNode(' · Size ' + item.size));

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button--secondary button--small';
    button.dataset.removeId = String(item.id);
    button.textContent = 'Remove';
    button.setAttribute('aria-label', 'Remove ' + item.title + ' from your list');

    li.append(img, text, button);
    return li;
  }

  function render() {
    const n = state.items.length;
    const full = n >= max;
    // Stay visible while a message is showing, even if the list just became empty.
    const visible = n > 0 || !message.hidden;

    root.hidden = !visible;
    document.body.classList.toggle('has-tryon', visible);
    countEl.textContent = n === 1 ? '1 item' : n + ' items';
    itemsEl.replaceChildren(...state.items.map(itemRow));
    fullNote.hidden = !full;
    sendButton.disabled = n === 0;
    if (n === 0) rooms.hidden = true;
    if (!visible) closePanel();

    document.querySelectorAll('[data-tryon]').forEach((button) => {
      const on = has(Number(button.dataset.id));
      button.setAttribute('aria-pressed', on ? 'true' : 'false');
      button.disabled = !on && full;
      button.textContent = on ? 'On your list ✓' : (full ? 'List full' : 'Try this on');
    });
  }

  function openPanel(withRooms) {
    panel.hidden = false;
    rooms.hidden = !withRooms;
    toggle.setAttribute('aria-expanded', 'true');
    if (withRooms) {
      const first = rooms.querySelector('input:checked') || rooms.querySelector('input');
      if (first) first.focus({ preventScroll: true });
      submit.scrollIntoView({ block: 'nearest' }); // the rooms and "Send request" in view
    }
  }

  function closePanel() {
    panel.hidden = true;
    rooms.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
  }

  function showMessage(text) {
    message.textContent = text;
    message.hidden = false;
  }

  function hideMessage() {
    message.hidden = true;
  }

  // ---------- events ----------

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-tryon]');
    if (button) {
      const id = Number(button.dataset.id);
      if (has(id)) {
        remove(id);
      } else {
        add({ id, title: button.dataset.title, size: button.dataset.size, photo_url: button.dataset.photo });
      }
      return;
    }
    const removeButton = event.target.closest('[data-remove-id]');
    if (removeButton) remove(Number(removeButton.dataset.removeId));
  });

  toggle.addEventListener('click', () => {
    if (panel.hidden) {
      openPanel(false);
    } else {
      hideMessage();
      closePanel();
      render();
    }
  });

  sendButton.addEventListener('click', () => {
    hideMessage();
    openPanel(true);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) {
      hideMessage();
      closePanel();
      render();
      if (!root.hidden) toggle.focus();
    }
  });

  submit.addEventListener('click', async () => {
    const room = rooms.querySelector('input[name="fitting_room"]:checked');
    if (!room) {
      showMessage('Please choose a fitting room.');
      return;
    }

    submit.disabled = true;
    submit.textContent = 'Sending…';
    let leaving = false;

    try {
      const response = await fetch('/api/requests.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ items: state.items.map((item) => item.id), fitting_room: Number(room.value) }),
      });
      const data = await response.json().catch(() => ({}));

      if (response.status === 201 && data.request_id) {
        leaving = true;
        clear();
        window.location.href = '/request.php?id=' + encodeURIComponent(data.request_id);
        return;
      }
      showMessage(data.error || "Your request couldn't be sent. Please try again.");
      if (response.status === 409 && Array.isArray(data.taken)) {
        data.taken.forEach((item) => remove(item.id));
        document.dispatchEvent(new CustomEvent('tryon:changed-archive'));
      }
    } catch (err) {
      showMessage("Your request couldn't be sent. Check the connection and try again.");
    } finally {
      if (!leaving) {
        submit.disabled = false;
        submit.textContent = 'Send request';
      }
    }
  });

  // archive.js redrew the cards: show which ones are on the list.
  document.addEventListener('archive:rendered', render);

  render();
})();
