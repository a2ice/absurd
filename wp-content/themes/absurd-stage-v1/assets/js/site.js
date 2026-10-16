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
  // Decorative film: always render a still if playback is unavailable.
  const stage = document.querySelector('.hero-film');
  const video = stage?.querySelector('.hero-frame--video');
  if (!stage || !video) return;

  const mobileQuery = window.matchMedia('(max-width: 800px)');
  const reducedQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  const dataSaver = () => navigator.connection?.saveData === true;
  let generation = 0;
  let deadline = null;

  const clearDeadline = () => {
    if (deadline !== null) window.clearTimeout(deadline);
    deadline = null;
  };

  const showStill = () => {
    ++generation;
    clearDeadline();
    video.pause();
    video.removeAttribute('src');
    video.load();
    stage.dataset.state = 'final';
  };

  const shouldSkip = () =>
    mobileQuery.matches || reducedQuery.matches || dataSaver() || document.hidden;

  const initialize = () => {
    if (shouldSkip()) {
      showStill();
      return;
    }

    ++generation;
    const attempt = generation;
    clearDeadline();
    stage.dataset.state = 'poster';
    video.pause();
    video.muted = true;
    video.playsInline = true;
    video.loop = false;

    const source = video.dataset.src;
    if (!source || !video.canPlayType('video/mp4')) {
      showStill();
      return;
    }

    const fail = () => {
      if (attempt === generation) showStill();
    };

    const play = async () => {
      if (attempt !== generation) return;
      if (shouldSkip()) {
        fail();
        return;
      }
      clearDeadline();
      try {
        await video.play();
        if (attempt === generation) stage.dataset.state = 'playing';
      } catch {
        fail();
      }
    };

    video.addEventListener('canplay', play, { once: true });
    video.addEventListener('error', fail, { once: true });
    deadline = window.setTimeout(fail, 5500);
    video.src = source;
    video.preload = 'auto';
    video.load();
  };

  video.addEventListener('ended', () => {
    if (stage.dataset.state === 'playing') stage.dataset.state = 'ended';
  });

  reducedQuery.addEventListener?.('change', initialize);
  mobileQuery.addEventListener?.('change', initialize);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      video.pause();
    } else if (stage.dataset.state === 'playing' && !shouldSkip()) {
      video.play().catch(showStill);
    }
  });

  initialize();
})();
