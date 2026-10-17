/* A2ice: deterministic DOM/media playback tests, no external dependencies. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('wp-content/themes/absurd-stage-v1/assets/js/site.js', 'utf8');

function createHarness({ reduced = false, saveData = false, elapsed = 0, readyState = 'complete', rejectsPlay = false } = {}) {
  let now = 100 + elapsed;
  let nextTimer = 0;
  const timers = new Map();
  const events = new Map();
  const documentEvents = new Map();
  const video = {
    dataset: { src: '/alt-heavy-burgundy.mp4' },
    readyState: 0,
    paused: true,
    src: '',
    playCalls: 0,
    pause() { this.paused = true; },
    load() {},
    canPlayType(type) { return type === 'video/mp4' ? 'probably' : ''; },
    removeAttribute(key) { if (key === 'src') this.src = ''; },
    play() {
      this.playCalls++;
      if (rejectsPlay) return Promise.reject(new Error('autoplay disabled'));
      this.paused = false;
      return Promise.resolve();
    },
    addEventListener(type, callback, options = {}) {
      const listeners = events.get(type) || [];
      const entry = { callback };
      listeners.push(entry);
      events.set(type, listeners);
      if (options.signal) {
        options.signal.addEventListener('abort', () => {
          const index = listeners.indexOf(entry);
          if (index >= 0) listeners.splice(index, 1);
        }, { once: true });
      }
    },
    fire(type) {
      for (const entry of [...(events.get(type) || [])]) entry.callback();
    }
  };
  const stage = { dataset: { state: 'poster' }, querySelector(selector) { return selector === '.hero-frame--video' ? video : null; } };
  const reducedQuery = {
    matches: reduced,
    addEventListener(type, cb) { if (type === 'change') this.onChange = cb; },
    set(value) { this.matches = value; this.onChange?.(); }
  };
  const document = {
    readyState,
    hidden: false,
    querySelector(selector) { return selector === '.hero-film' ? stage : null; },
    addEventListener(type, callback) { documentEvents.set(type, callback); },
    fire(type) { documentEvents.get(type)?.(); }
  };
  const window = {
    matchMedia() { return reducedQuery; },
    setTimeout(callback, milliseconds) {
      const id = ++nextTimer;
      timers.set(id, { at: now + milliseconds, callback });
      return id;
    },
    clearTimeout(id) { timers.delete(id); }
  };
  vm.runInNewContext(source, {
    document, window, navigator: { connection: { saveData } },
    performance: {
      now: () => now,
      getEntriesByType: () => [{ domContentLoadedEventEnd: 100 }]
    },
    Promise, AbortController
  });
  const advance = (milliseconds) => {
    now += milliseconds;
    for (const [id, timer] of [...timers]) {
      if (timer.at <= now) {
        timers.delete(id);
        timer.callback();
      }
    }
  };
  return { video, stage, document, reducedQuery, advance, pending: () => timers.size };
}

(async () => {
  {
    const h = createHarness();
    assert.equal(h.stage.dataset.state, 'loading');
    assert.equal(h.video.src, '/alt-heavy-burgundy.mp4');
    h.advance(700);
    h.video.fire('loadeddata');
    h.video.fire('playing');
    assert.equal(h.stage.dataset.state, 'playing');
    assert.equal(h.pending(), 0);
    h.advance(2000);
    assert.equal(h.stage.dataset.state, 'playing', 'No late fallback after successful start');
    h.video.fire('ended');
    assert.equal(h.stage.dataset.state, 'ended');
  }
  {
    const h = createHarness();
    h.advance(1500);
    assert.equal(h.stage.dataset.state, 'final', 'Fallback exactly 1500ms after DCL');
    assert.equal(h.video.src, '');
    h.video.fire('loadeddata');
    h.video.fire('playing');
    assert.equal(h.video.playCalls, 0, 'Late media events cannot restart video');
    assert.equal(h.stage.dataset.state, 'final');
  }
  {
    const h = createHarness({ elapsed: 1000 });
    assert.equal(h.stage.dataset.state, 'loading');
    h.advance(499);
    assert.equal(h.stage.dataset.state, 'loading');
    h.advance(1);
    assert.equal(h.stage.dataset.state, 'final', 'Late footer execution does not extend DCL deadline');
  }
  {
    const h = createHarness({ reduced: true });
    assert.equal(h.stage.dataset.state, 'final');
    assert.equal(h.video.src, '');
    assert.equal(h.pending(), 0);
  }
  {
    const h = createHarness({ saveData: true });
    assert.equal(h.stage.dataset.state, 'final');
    assert.equal(h.video.playCalls, 0);
  }
  {
    const h = createHarness();
    h.reducedQuery.set(true);
    assert.equal(h.stage.dataset.state, 'final', 'Preference change during loading cancels video');
  }
  {
    const h = createHarness({ readyState: 'loading' });
    assert.equal(h.stage.dataset.state, 'poster', 'Wait for DOMContentLoaded');
    h.document.readyState = 'complete';
    h.document.fire('DOMContentLoaded');
    assert.equal(h.stage.dataset.state, 'loading');
  }
  {
    const h = createHarness({ rejectsPlay: true });
    h.video.fire('canplay');
    await Promise.resolve();
    await Promise.resolve();
    assert.equal(h.stage.dataset.state, 'final', 'Rejected autoplay keeps still image');
  }
  console.log('hero video/loading/fallback behavior: PASS (8 scenarios)');
})().catch(err => { console.error(err); process.exitCode = 1; });
