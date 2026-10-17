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
  // Decorative autoplay only: the still always remains a valid fallback.
  const stage = document.querySelector('.hero-film');
  const video = stage?.querySelector('.hero-frame--video');
  if (!stage || !video) return;

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const shouldSkip = () =>
    reducedMotion.matches || navigator.connection?.saveData === true || document.hidden;
  const maxWaitMs = 1500; // Hard deadline measured from DOMContentLoaded.
  let generation = 0;
  let deadline = null;
  let listeners = null;

  const cancelWaiting = () => {
    if (deadline !== null) window.clearTimeout(deadline);
    deadline = null;
    listeners?.abort();
    listeners = null;
  };

  const showStill = () => {
    generation += 1; // Invalidate pending play() promises and late events.
    cancelWaiting();
    video.pause();
    video.removeAttribute('src');
    video.load();
    stage.dataset.state = 'final';
  };

  const start = (timeLeftMs) => {
    if (shouldSkip() || timeLeftMs <= 0) {
      showStill();
      return;
    }
    const source = video.dataset.src;
    if (!source || !video.canPlayType('video/mp4')) {
      showStill();
      return;
    }

    generation += 1;
    const attempt = generation;
    cancelWaiting();
    listeners = new AbortController();
    const { signal } = listeners;
    let playRequested = false;

    video.pause();
    video.muted = true;
    video.playsInline = true;
    video.loop = false;
    stage.dataset.state = 'loading'; // Spinner stays over black, not the mismatched fallback image.

    const active = () => attempt === generation;
    const fail = () => { if (active()) showStill(); };
    const tryPlay = () => {
      if (!active() || playRequested) return;
      if (shouldSkip()) { fail(); return; }
      playRequested = true;
      try {
        Promise.resolve(video.play()).catch(fail);
      } catch {
        fail();
      }
    };

    video.addEventListener('loadeddata', tryPlay, { once: true, signal });
    video.addEventListener('canplay', tryPlay, { once: true, signal });
    video.addEventListener('playing', () => {
      if (!active()) return;
      if (shouldSkip()) { fail(); return; }
      cancelWaiting();
      stage.dataset.state = 'playing';
    }, { signal });
    video.addEventListener('error', fail, { once: true, signal });

    deadline = window.setTimeout(fail, timeLeftMs);
    video.preload = 'auto';
    video.src = source;
    video.load();
    if (video.readyState >= 2) tryPlay();
  };

  video.addEventListener('ended', () => {
    if (stage.dataset.state === 'playing') stage.dataset.state = 'ended';
  });
  reducedMotion.addEventListener?.('change', () => {
    if (reducedMotion.matches && stage.dataset.state !== 'final') showStill();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      if (stage.dataset.state === 'loading') showStill();
      else video.pause();
    } else if (stage.dataset.state === 'playing' && !shouldSkip()) {
      video.play().catch(showStill);
    }
  });

  const onDomReady = () => {
    // Navigation Timing accounts for footer scripts loaded after DOMContentLoaded.
    const navigation = performance.getEntriesByType?.('navigation')?.[0];
    const loadedAt = navigation?.domContentLoadedEventEnd;
    const elapsed = loadedAt > 0 ? Math.max(0, performance.now() - loadedAt) : 0;
    start(Math.max(0, maxWaitMs - elapsed));
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onDomReady, { once: true });
  } else {
    onDomReady();
  }
})();
