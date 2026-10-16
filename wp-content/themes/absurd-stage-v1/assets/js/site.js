(() => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.getElementById('primary-navigation');
  if (!toggle || !nav) return;
  let lastFocused = null;
  const setOpen = (open) => {
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    document.body.classList.toggle('menu-open', open);
    if (open) { lastFocused = document.activeElement; nav.querySelector('a')?.focus(); }
    else if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
  };
  toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
  nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    if (toggle.getAttribute('aria-expanded') === 'true') setOpen(false);
  }));
  document.addEventListener('keydown', e => {
    if (toggle.getAttribute('aria-expanded') !== 'true') return;
    if (e.key === 'Escape') { e.preventDefault(); setOpen(false); return; }
    if (e.key === 'Tab') {
      const items = Array.from(nav.querySelectorAll('a[href]')).filter(x => x.getClientRects().length);
      items.push(toggle);
      const first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
  document.addEventListener('pointerdown', e => {
    if (toggle.getAttribute('aria-expanded') === 'true' && !nav.contains(e.target) && !toggle.contains(e.target)) setOpen(false);
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth > 800 && toggle.getAttribute('aria-expanded') === 'true') setOpen(false);
  });
})();
