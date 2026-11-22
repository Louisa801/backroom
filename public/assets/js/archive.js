// M1: update the archive results without reloading the page.
// M2: refresh quietly every 10 seconds, so items requested on another tablet disappear.
(function () {
  'use strict';

  const REFRESH_MS = 10000;

  const form = document.getElementById('filters');
  const cards = document.getElementById('cards');
  const empty = document.getElementById('empty');
  const count = document.getElementById('result-count');
  const error = document.getElementById('error');
  const template = document.getElementById('card-template');
  if (!form || !cards || !template) return;

  // With JS the results update on every change, so the submit button is not needed.
  form.querySelectorAll('[data-no-js]').forEach((el) => { el.hidden = true; });

  const priceFormat = new Intl.NumberFormat('en-IE', { style: 'currency', currency: 'EUR' });
  let controller = null;
  let shownIds = Array.from(cards.querySelectorAll('[data-tryon]')).map((b) => b.dataset.id).join(',');

  function queryString() {
    const params = new URLSearchParams();
    new FormData(form).forEach((value, key) => {
      if (value !== '') params.append(key, value);
    });
    return params.toString();
  }

  // Link to the detail page, carrying the current filters for its "Back to the archive".
  function itemUrl(id) {
    const qs = queryString();
    return '/item.php?id=' + encodeURIComponent(id) + (qs ? '&' + qs : '');
  }

  function renderCard(item) {
    const card = template.content.firstElementChild.cloneNode(true);
    const img = card.querySelector('.card__photo');
    img.src = item.photo_url;
    img.alt = item.title;
    const url = itemUrl(item.id);
    card.querySelector('.card__photo-link').href = url;
    const link = card.querySelector('.card__link');
    link.href = url;
    link.textContent = item.title;
    card.querySelector('.card__size').textContent = 'Size ' + item.size;
    card.querySelector('.card__price').textContent = priceFormat.format(Number(item.sale_price));
    const button = card.querySelector('[data-tryon]');
    button.dataset.id = String(item.id);
    button.dataset.title = item.title;
    button.dataset.size = item.size;
    button.dataset.photo = item.photo_url;
    return card;
  }

  function render(data, quiet) {
    const ids = data.items.map((item) => item.id).join(',');
    // A quiet refresh only redraws when the results changed, so focus and scroll stay put.
    if (quiet && ids === shownIds) return;
    shownIds = ids;
    cards.replaceChildren(...data.items.map(renderCard));
    cards.hidden = data.count === 0;
    empty.hidden = data.count !== 0;
    count.textContent = data.count === 1 ? '1 item' : data.count + ' items';
    document.dispatchEvent(new CustomEvent('archive:rendered'));
  }

  function showError(message) {
    error.textContent = message;
    error.hidden = false;
  }

  async function update(options) {
    const quiet = Boolean(options && options.quiet);
    if (quiet && controller) return; // never interrupt a search the customer started

    const qs = queryString();
    if (!quiet) history.replaceState(null, '', qs ? '?' + qs : location.pathname);

    if (controller) controller.abort();
    const current = new AbortController();
    controller = current;
    if (!quiet) form.setAttribute('aria-busy', 'true');

    try {
      const response = await fetch('/api/items.php' + (qs ? '?' + qs : ''), {
        signal: current.signal,
        headers: { Accept: 'application/json' },
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        if (!quiet) showError(data.error || "Couldn't load items. Try again.");
        return;
      }
      error.hidden = true;
      render(data, quiet);
    } catch (err) {
      if (err.name !== 'AbortError' && !quiet) showError("Couldn't load items. Check the connection and try again.");
    } finally {
      if (controller === current) controller = null;
      if (!quiet) form.removeAttribute('aria-busy');
    }
  }

  form.addEventListener('change', () => update());
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    update();
  });

  document.querySelectorAll('[data-clear]').forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      form.reset();
      form.querySelectorAll('select').forEach((select) => { select.value = ''; });
      update();
      form.querySelector('select').focus();
    });
  });

  setInterval(() => {
    if (!document.hidden) update({ quiet: true });
  }, REFRESH_MS);

  // After a try-on request (or a refusal), show the archive as it is now.
  document.addEventListener('tryon:changed-archive', () => update({ quiet: true }));
})();
