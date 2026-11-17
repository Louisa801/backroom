// M1: update the archive results without reloading the page.
(function () {
  'use strict';

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

  function queryString() {
    const params = new URLSearchParams();
    new FormData(form).forEach((value, key) => {
      if (value !== '') params.append(key, value);
    });
    return params.toString();
  }

  function renderCard(item) {
    const card = template.content.firstElementChild.cloneNode(true);
    const img = card.querySelector('.card__photo');
    img.src = item.photo_url;
    img.alt = item.title;
    card.querySelector('.card__title').textContent = item.title;
    card.querySelector('.card__size').textContent = 'Size ' + item.size;
    card.querySelector('.card__price').textContent = priceFormat.format(Number(item.sale_price));
    return card;
  }

  function render(data) {
    cards.replaceChildren(...data.items.map(renderCard));
    cards.hidden = data.count === 0;
    empty.hidden = data.count !== 0;
    count.textContent = data.count === 1 ? '1 item' : data.count + ' items';
  }

  function showError(message) {
    error.textContent = message;
    error.hidden = false;
  }

  async function update() {
    const qs = queryString();
    history.replaceState(null, '', qs ? '?' + qs : location.pathname);

    if (controller) controller.abort();
    controller = new AbortController();
    form.setAttribute('aria-busy', 'true');

    try {
      const response = await fetch('/api/items.php' + (qs ? '?' + qs : ''), {
        signal: controller.signal,
        headers: { Accept: 'application/json' },
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        showError(data.error || "Couldn't load items. Try again.");
        return;
      }
      error.hidden = true;
      render(data);
    } catch (err) {
      if (err.name !== 'AbortError') showError("Couldn't load items. Check the connection and try again.");
    } finally {
      form.removeAttribute('aria-busy');
    }
  }

  form.addEventListener('change', update);
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
})();
