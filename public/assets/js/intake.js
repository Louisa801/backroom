// M5: buy-in form. Add/remove items, suggest shelf codes, show the payout while typing.
// The server validates everything again and calculates the real payout.
(function () {
  'use strict';

  const form = document.getElementById('intake-form');
  const summary = document.getElementById('error-summary');
  if (summary) summary.focus();
  if (!form) return;

  const itemsEl = document.getElementById('items');
  const template = document.getElementById('item-template');
  const addButton = document.getElementById('add-item');
  const maxNote = document.getElementById('max-items-note');
  const output = document.getElementById('payout-total');
  const suggestions = JSON.parse(form.dataset.suggestions || '[]');
  const maxItems = Number(form.dataset.maxItems || 10);
  const euro = new Intl.NumberFormat('en-IE', { style: 'currency', currency: 'EUR' });

  let nextKey = itemsEl.querySelectorAll('[data-item]').length;

  const blocks = () => Array.from(itemsEl.querySelectorAll('[data-item]'));

  function nextShelfCode() {
    const used = new Set(
      Array.from(itemsEl.querySelectorAll('[data-shelf]')).map((input) => input.value.trim().toUpperCase())
    );
    return suggestions.find((code) => !used.has(code)) || '';
  }

  function addItem() {
    if (blocks().length >= maxItems) return;
    // The template is server-rendered markup without user data; only the placeholders change.
    const html = template.innerHTML
      .replaceAll('__KEY__', String(nextKey))
      .replaceAll('__NUM__', String(blocks().length + 1));
    nextKey += 1;

    const holder = document.createElement('template');
    holder.innerHTML = html.trim();
    const block = holder.content.firstElementChild;
    block.querySelector('[data-shelf]').value = nextShelfCode();
    itemsEl.append(block);
    refresh();
    block.querySelector('input[type="file"]').focus();
  }

  function refresh() {
    const all = blocks();
    all.forEach((block, index) => {
      block.querySelectorAll('[data-item-number]').forEach((el) => { el.textContent = String(index + 1); });
      block.querySelector('[data-remove-item]').hidden = all.length === 1;
    });
    const full = all.length >= maxItems;
    addButton.disabled = full;
    maxNote.hidden = !full;
    updatePayout();
  }

  // "12", "12.5", "12,50" -> cents; null if not a plain amount (same rule as the server).
  function toCents(text) {
    const match = text.trim().replace(',', '.').match(/^(\d{1,9})(?:\.(\d{1,2}))?$/);
    if (!match) return null;
    return Number(match[1]) * 100 + Number((match[2] || '0').padEnd(2, '0'));
  }

  function updatePayout() {
    let cash = 0;
    let any = false;
    itemsEl.querySelectorAll('[data-purchase]').forEach((input) => {
      const cents = toCents(input.value);
      if (cents !== null) {
        cash += cents;
        any = true;
      }
    });
    const checked = form.querySelector('input[name="payout_type"]:checked');
    const type = checked ? checked.value : '';
    const credit = Math.floor((cash * 120 + 50) / 100);

    // The amounts on the two choices themselves (as in the mockup): "Cash · €8.00", "Store credit · €9.60".
    const cashAmount = form.querySelector('[data-payout-amount="cash"]');
    const creditAmount = form.querySelector('[data-payout-amount="store_credit"]');
    if (cashAmount) cashAmount.textContent = any ? ' · ' + euro.format(cash / 100) : '';
    if (creditAmount) creditAmount.textContent = any ? ' · ' + euro.format(credit / 100) : ' (cash × 1.2)';

    if (!any) {
      output.textContent = 'Enter purchase prices and choose cash or store credit.';
    } else if (type === 'cash') {
      output.textContent = 'Pay out ' + euro.format(cash / 100) + ' in cash.';
    } else if (type === 'store_credit') {
      output.textContent = 'Pay out ' + euro.format(credit / 100) + ' in store credit (cash value ' + euro.format(cash / 100) + ').';
    } else {
      output.textContent = 'Cash value ' + euro.format(cash / 100) + '. Choose cash or store credit.';
    }
  }

  addButton.addEventListener('click', addItem);

  itemsEl.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-item]');
    if (!button || blocks().length === 1) return;
    const block = button.closest('[data-item]');
    const index = blocks().indexOf(block);
    block.remove();
    refresh();
    const all = blocks();
    const focusTarget = all[Math.min(index, all.length - 1)];
    if (focusTarget) focusTarget.querySelector('legend').scrollIntoView({ block: 'nearest' });
    addButton.focus();
  });

  form.addEventListener('input', (event) => {
    if (event.target.matches('[data-purchase]')) updatePayout();
  });
  form.addEventListener('change', (event) => {
    if (event.target.name === 'payout_type') updatePayout();
    if (event.target.matches('[data-shelf]')) event.target.value = event.target.value.trim().toUpperCase();
  });

  refresh();
})();
