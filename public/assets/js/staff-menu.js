// Staff header: the ☰ dropdown on small screens (below 900px).
// Opens and closes with the button; closes with Escape, a tap outside, or after choosing a page.
(function () {
  'use strict';

  const button = document.querySelector('.staff-menu-button');
  const menu = document.getElementById('staff-menu');
  if (!button || !menu) return;

  const wide = window.matchMedia('(min-width: 900px)');
  const isOpen = () => button.getAttribute('aria-expanded') === 'true';

  function open() {
    menu.classList.add('is-open');
    button.setAttribute('aria-expanded', 'true');
    const first = menu.querySelector('a, button');
    if (first) first.focus();
  }

  function close(returnFocus) {
    if (!isOpen()) return;
    menu.classList.remove('is-open');
    button.setAttribute('aria-expanded', 'false');
    if (returnFocus) button.focus();
  }

  button.addEventListener('click', () => {
    if (isOpen()) close(false);
    else open();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && isOpen()) {
      event.preventDefault();
      close(true);
    }
  });

  // A tap or click anywhere outside the menu and its button closes it.
  document.addEventListener('pointerdown', (event) => {
    if (isOpen() && !menu.contains(event.target) && !button.contains(event.target)) close(false);
  });

  // Moving the keyboard focus out of the menu (Tab past Log out) closes it too.
  menu.addEventListener('focusout', (event) => {
    if (isOpen() && event.relatedTarget && !menu.contains(event.relatedTarget) && event.relatedTarget !== button) close(false);
  });

  // Choosing a page closes it (the page then loads).
  menu.addEventListener('click', (event) => {
    if (event.target.closest('a')) close(false);
  });

  // Widening the window to the one-row layout: the dropdown is not needed any more.
  wide.addEventListener('change', (event) => {
    if (event.matches) close(false);
  });
})();
