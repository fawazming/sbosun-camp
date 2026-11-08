/* SB Osun Camp Portal — vanilla JS, no dependencies. */
(function () {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* Mobile navigation */
  $$('[data-mobile-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.getAttribute('data-mobile-toggle'));
      if (target) target.classList.toggle('open');
    });
  });

  /* Admin sidebar */
  $$('[data-admin-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => $('.admin-sidebar')?.classList.toggle('open'));
  });

  /* Flash auto-hide */
  $$('.flash').forEach((el) => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s ease, transform .4s ease';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(() => el.remove(), 450);
    }, 6500);
  });

  /* Reveal on scroll */
  const revealEls = $$('[data-reveal]');
  if ('IntersectionObserver' in window && revealEls.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach((el) => io.observe(el));
  } else {
    revealEls.forEach((el) => el.classList.add('in'));
  }

  /* Gallery */
  const lightbox = $('#lightbox');
  const lightboxMedia = $('#lightboxMedia');
  const lightboxTitle = $('#lightboxTitle');
  const lightboxDesc = $('#lightboxDesc');
  const lightboxMeta = $('#lightboxMeta');
  $$('[data-gallery]').forEach((item) => {
    item.addEventListener('click', () => {
      if (!lightbox) return;
      const src = item.getAttribute('data-src');
      lightboxMedia.innerHTML = '<img src="' + src + '" alt="">';
      lightboxTitle.textContent = item.getAttribute('data-title') || '';
      lightboxDesc.textContent = item.getAttribute('data-desc') || '';
      lightboxMeta.innerHTML = '<span>' + (item.getAttribute('data-year') || '') + '</span><span>' + (item.getAttribute('data-location') || '') + '</span>';
      lightbox.classList.add('open');
      document.body.style.overflow = 'hidden';
    });
  });
  const closeLightbox = () => {
    if (!lightbox) return;
    lightbox.classList.remove('open');
    document.body.style.overflow = '';
  };
  $('#lightboxClose')?.addEventListener('click', closeLightbox);
  lightbox?.addEventListener('click', (e) => { if (e.target === lightbox) closeLightbox(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeLightbox(); });

  /* Scrollytelling highlights */
  const highlightCards = $$('[data-highlight]');
  const panels = $$('[data-panel]');
  const activate = (id) => {
    highlightCards.forEach((c) => c.classList.toggle('active', c.getAttribute('data-highlight') === id));
    panels.forEach((p) => { p.hidden = p.getAttribute('data-panel') !== id; });
    document.dispatchEvent(new CustomEvent('highlight:change', { detail: id }));
  };
  highlightCards.forEach((card) => {
    card.addEventListener('click', () => {
      const id = card.getAttribute('data-highlight');
      activate(id);
      card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });
  if ('IntersectionObserver' in window && highlightCards.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) activate(entry.target.getAttribute('data-highlight'));
      });
    }, { root: null, rootMargin: '-45% 0px -45% 0px', threshold: 0 });
    highlightCards.forEach((card) => io.observe(card));
  }

  /* Carousels inside the highlight player */
  $$('[data-carousel]').forEach((carousel) => {
    const frames = $$('[data-frame]', carousel);
    if (frames.length < 2) return;
    let index = 0;
    const show = (n) => {
      index = (n + frames.length) % frames.length;
      frames.forEach((f, i) => f.hidden = i !== index);
      const counter = carousel.getAttribute('data-counter');
      if (counter) {
        const out = document.getElementById(counter);
        if (out) out.textContent = 'PHOTO ' + (index + 1) + ' OF ' + frames.length;
      }
    };
    carousel.querySelector('[data-prev]')?.addEventListener('click', (e) => { e.stopPropagation(); show(index - 1); });
    carousel.querySelector('[data-next]')?.addEventListener('click', (e) => { e.stopPropagation(); show(index + 1); });
    show(0);
    let timer = null;
    const start = () => { timer = setInterval(() => show(index + 1), 3500); };
    const stop = () => { if (timer) clearInterval(timer); timer = null; };
    const panel = carousel.closest('[data-panel]');
    if (panel) {
      const id = panel.getAttribute('data-panel');
      document.addEventListener('highlight:change', (e) => {
        if (e.detail === id) start(); else stop();
      });
    }
  });

  /* Tahajjud gender switcher */
  $$('[data-gender-switch]').forEach((group) => {
    const frames = $$('[data-gender-frame]', group);
    $$('[data-gender]', group).forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const gender = btn.getAttribute('data-gender');
        $$('[data-gender]', group).forEach((b) => b.classList.toggle('active', b === btn));
        frames.forEach((f) => { f.hidden = f.getAttribute('data-gender-frame') !== gender; });
      });
    });
  });

  /* Lecturers scroll */
  $$('[data-lecturer-scroll]').forEach((scroller) => {
    const left = $('[data-lecturer-left]');
    const right = $('[data-lecturer-right]');
    const progress = $('[data-lecturer-progress]');
    const update = () => {
      const max = scroller.scrollWidth - scroller.clientWidth;
      const pct = max > 0 ? (scroller.scrollLeft / max) * 95 + 5 : 5;
      if (progress) progress.style.width = Math.max(5, pct) + '%';
      if (left) left.disabled = scroller.scrollLeft < 6;
      if (right) right.disabled = scroller.scrollLeft > max - 6;
    };
    left?.addEventListener('click', () => scroller.scrollBy({ left: -320, behavior: 'smooth' }));
    right?.addEventListener('click', () => scroller.scrollBy({ left: 320, behavior: 'smooth' }));
    scroller.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
  });

  /* Memory Lane audio */
  const memoryBtn = $('#memoryBtn');
  const audio = $('#memoryAudio');
  if (memoryBtn && audio) {
    audio.volume = 0.4;
    memoryBtn.addEventListener('click', () => {
      if (audio.paused) {
        audio.play().then(() => {
          memoryBtn.classList.add('playing');
          memoryBtn.querySelector('[data-memory-label]').textContent = 'Pause Memory Lane';
        }).catch(() => {});
      } else {
        audio.pause();
        memoryBtn.classList.remove('playing');
        memoryBtn.querySelector('[data-memory-label]').textContent = 'Play Memory Lane';
      }
    });
    let lastY = window.scrollY;
    let lastT = Date.now();
    let audioTimeout;
    window.addEventListener('scroll', () => {
      const y = window.scrollY;
      const t = Date.now();
      const speed = Math.abs(y - lastY) / Math.max(1, t - lastT);
      if (!audio.paused) audio.volume = Math.min(0.85, 0.4 + speed * 0.18);
      lastY = y; lastT = t;
      clearTimeout(audioTimeout);
      audioTimeout = setTimeout(() => { if (!audio.paused) audio.volume = 0.4; }, 160);
    }, { passive: true });
  }

  /* Copy buttons */
  $$('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const value = btn.getAttribute('data-copy');
      try {
        await navigator.clipboard.writeText(value);
        const old = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(() => { btn.textContent = old; }, 1500);
      } catch (_) {}
    });
  });

  /* Confirmation */
  $$('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  /* Countdown */
  $$('[data-countdown]').forEach((el) => {
    const target = new Date(el.getAttribute('data-countdown') + 'T00:00:00');
    const days = Math.max(0, Math.ceil((target.getTime() - Date.now()) / 86400000));
    el.textContent = days + (days === 1 ? ' day to camp' : ' days to camp');
  });

  /* Registration: minor + payment plan calculator */
  const minor = $('#isMinor');
  const guardian = $('#guardianFields');
  if (minor && guardian) {
    const sync = () => { guardian.hidden = !minor.checked; };
    minor.addEventListener('change', sync);
    sync();
  }
  $$('[data-plan]').forEach((plan) => {
    plan.addEventListener('change', () => {
      const isInstallment = plan.value === 'INSTALLMENT';
      $$('[data-installment-note]').forEach((el) => { el.hidden = !isInstallment; });
      $$('[data-full-note]').forEach((el) => { el.hidden = isInstallment; });
    });
  });

  /* Newsletter AJAX */
  $$('[data-newsletter-form]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const button = form.querySelector('button');
      const message = form.parentElement.querySelector('[data-newsletter-message]');
      const old = button.textContent;
      button.textContent = 'Subscribing…';
      button.disabled = true;
      try {
        const res = await fetch(form.action, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form),
        });
        const data = await res.json();
        if (message) {
          message.textContent = data.message || data.error || '';
          message.style.color = data.ok ? '#15803d' : '#b91c1c';
        }
        if (data.ok) form.reset();
      } catch (_) {
        if (message) { message.textContent = 'Could not subscribe right now.'; message.style.color = '#b91c1c'; }
      } finally {
        button.textContent = old;
        button.disabled = false;
      }
    });
  });

  /* Table row link (admin) */
  $$('[data-row-href]').forEach((row) => {
    row.style.cursor = 'pointer';
    row.addEventListener('click', (e) => {
      if (e.target.closest('a,button,form,input,select')) return;
      window.location.href = row.getAttribute('data-row-href');
    });
  });
})();
