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

(() => {
  const hero = document.querySelector('.hero-media');
  if (!hero) return;

  const mobileQuery = window.matchMedia('(max-width: 800px)');
  const reducedQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  let loadToken = 0;

  const quoteUrl = (url) => `url("${String(url).replace(/"/g, '%22')}")`;
  const selectAssets = () => {
    const suffix = mobileQuery.matches ? 'Mobile' : 'Desktop';
    return {
      scene: hero.dataset[`heroScene${suffix}`] || hero.dataset.heroSceneDesktop,
      curtain: hero.dataset[`heroCurtain${suffix}`] || hero.dataset.heroCurtainDesktop,
    };
  };

  const preloadImage = (url) => new Promise((resolve) => {
    if (!url) { resolve(); return; }
    const image = new Image();
    image.onload = image.onerror = resolve;
    image.src = url;
  });

  const applyAssets = ({ scene, curtain }) => {
    if (scene) hero.style.setProperty('--hero-scene-image', quoteUrl(scene));
    if (curtain) hero.style.setProperty('--hero-curtain-image', quoteUrl(curtain));
  };

  const loadHero = () => {
    const token = ++loadToken;
    const assets = selectAssets();
    hero.classList.remove('is-ready');
    hero.classList.toggle('is-reduced', reducedQuery.matches);
    applyAssets(assets);

    const timeout = new Promise((resolve) => window.setTimeout(resolve, 1800));
    Promise.race([
      Promise.all([preloadImage(assets.scene), preloadImage(assets.curtain)]),
      timeout,
    ]).then(() => {
      if (token !== loadToken) return;
      hero.classList.add('is-ready');
      hero.classList.toggle('is-reduced', reducedQuery.matches);
    });
  };

  loadHero();
  mobileQuery.addEventListener?.('change', loadHero);
  reducedQuery.addEventListener?.('change', loadHero);
})();
