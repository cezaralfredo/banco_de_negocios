/* Banco de Negócios — scripts do site público */
(() => {
  'use strict';
  const I18N = window.BN_I18N || {};
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];

  /* ------------------------------------------------------------------
     LGPD — consentimento de cookies
     Cookie "bn_consent" = JSON {v, id, date, necessary, preferences, statistics, marketing}
  ------------------------------------------------------------------ */
  const Consent = {
    name: 'bn_consent',
    read() {
      const raw = document.cookie.split('; ').find(c => c.startsWith(this.name + '='));
      if (!raw) return null;
      try { return JSON.parse(decodeURIComponent(raw.split('=').slice(1).join('='))); } catch (e) { return null; }
    },
    write(cats, choice) {
      const banner = $('[data-cookie-banner]');
      const prev = this.read();
      const data = {
        v: banner?.dataset.version || '1',
        id: prev?.id || (Date.now().toString(36) + Math.random().toString(36).slice(2, 10)),
        date: new Date().toISOString(),
        necessary: true,
        preferences: !!cats.preferences, statistics: !!cats.statistics, marketing: !!cats.marketing
      };
      document.cookie = `${this.name}=${encodeURIComponent(JSON.stringify(data))}; max-age=${60 * 60 * 24 * 180}; path=/; SameSite=Lax`;
      // Registro do consentimento (prestação de contas — LGPD art. 8º, §2º)
      try {
        const body = new URLSearchParams({ id: data.id, escolha: choice, v: data.v, categorias: ['necessary', ...['preferences', 'statistics', 'marketing'].filter(k => data[k])].join(',') });
        navigator.sendBeacon ? navigator.sendBeacon('cookie_consent.php', body) : fetch('cookie_consent.php', { method: 'POST', body });
      } catch (e) {}
      document.dispatchEvent(new CustomEvent('bn:consent', { detail: data }));
      return data;
    },
    allows(cat) { const c = this.read(); return !!(c && c[cat]); }
  };
  window.BNConsent = Consent;

  function initCookies() {
    const banner = $('[data-cookie-banner]');
    const modalEl = $('#cookieModal');
    const modal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const current = Consent.read();
    if (banner && (!current || String(current.v) !== String(banner.dataset.version))) {
      banner.hidden = false;
      document.body.classList.add('has-cookie-banner');
    }
    const close = () => { if (banner) banner.hidden = true; document.body.classList.remove('has-cookie-banner'); modal?.hide(); };
    const syncSwitches = () => { const c = Consent.read() || {}; $$('[data-cookie-cat]').forEach(i => { i.checked = !!c[i.dataset.cookieCat]; }); };
    $$('[data-cookie-action]').forEach(btn => btn.addEventListener('click', () => {
      const action = btn.dataset.cookieAction;
      if (action === 'accept') { Consent.write({ preferences: true, statistics: true, marketing: true }, 'aceitar'); close(); }
      else if (action === 'reject') { Consent.write({}, 'recusar'); close(); }
      else if (action === 'customize') { syncSwitches(); modal?.show(); }
      else if (action === 'save') {
        const cats = {}; $$('[data-cookie-cat]').forEach(i => { cats[i.dataset.cookieCat] = i.checked; });
        Consent.write(cats, 'personalizar'); close();
      }
    }));
    $$('[data-cookie-open]').forEach(b => b.addEventListener('click', () => { syncSwitches(); modal?.show(); }));
  }

  /* ------------------------------------------------------------------
     Slider de banners (efeitos: fade, slide, zoom, none)
  ------------------------------------------------------------------ */
  function initSlider() {
    const root = $('[data-banner-slider]');
    if (!root) return;
    const hero = root.closest('.hero');
    const slides = $$('[data-banner-slide]', root);
    const dots = $$('[data-dot]', hero);
    const play = $('[data-slide-play]', hero);
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let current = 0, paused = root.dataset.autoplay !== '1' || reduce, hover = false, timer;

    const show = (index, dir = 1) => {
      if (slides.length < 2) return;
      const next = (index + slides.length) % slides.length;
      if (next === current) return;
      const prev = slides[current];
      prev.classList.remove('is-active', 'from-left');
      prev.classList.add(dir > 0 ? 'is-leaving' : 'is-leaving-right');
      prev.setAttribute('aria-hidden', 'true');
      setTimeout(() => prev.classList.remove('is-leaving', 'is-leaving-right'), 800);
      const el = slides[next];
      el.classList.toggle('from-left', dir < 0);
      void el.offsetWidth; // reinicia a transição
      el.classList.add('is-active');
      el.removeAttribute('aria-hidden');
      current = next;
      dots.forEach((d, i) => { d.classList.toggle('is-active', i === current); d.setAttribute('aria-current', i === current ? 'true' : 'false'); });
    };
    const schedule = () => {
      clearTimeout(timer);
      if (paused || hover || slides.length < 2) return;
      const seconds = Math.max(2, Number(slides[current].dataset.duration || 5));
      timer = setTimeout(() => { show(current + 1, 1); schedule(); }, seconds * 1000);
    };
    $('[data-slide-prev]', hero)?.addEventListener('click', () => { show(current - 1, -1); schedule(); });
    $('[data-slide-next]', hero)?.addEventListener('click', () => { show(current + 1, 1); schedule(); });
    dots.forEach((d, i) => d.addEventListener('click', () => { show(i, i > current ? 1 : -1); schedule(); }));
    play?.addEventListener('click', () => {
      paused = !paused;
      play.textContent = paused ? '▶' : 'Ⅱ';
      play.setAttribute('aria-label', paused ? (I18N.play || 'Play') : (I18N.pause || 'Pause'));
      schedule();
    });
    if (play && paused) { play.textContent = '▶'; play.setAttribute('aria-label', I18N.play || 'Play'); }
    root.addEventListener('mouseenter', () => { hover = true; clearTimeout(timer); });
    root.addEventListener('mouseleave', () => { hover = false; schedule(); });
    hero.addEventListener('keydown', e => {
      if (e.target.closest('input,select,textarea')) return;
      if (e.key === 'ArrowLeft') { show(current - 1, -1); schedule(); }
      if (e.key === 'ArrowRight') { show(current + 1, 1); schedule(); }
    });
    let startX = null;
    root.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
    root.addEventListener('touchend', e => {
      if (startX === null) return;
      const dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 45) { show(current + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1); schedule(); }
      startX = null;
    });
    schedule();
  }

  /* ------------------------------------------------------------------
     Modal único de cadastro
  ------------------------------------------------------------------ */
  function initSignup() {
    const modalEl = $('#signupModal');
    if (!modalEl || !window.bootstrap) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const form = $('[data-signup-form]', modalEl);
    const errorBox = $('[data-signup-error]', modalEl);
    const success = $('[data-signup-success]', modalEl);

    // Qualquer link marcado ou que aponte para cadastro.php abre o MESMO modal
    document.addEventListener('click', e => {
      const a = e.target.closest('[data-signup-open], a[href="cadastro.php"], a[href^="cadastro.php?"]');
      if (!a || e.ctrlKey || e.metaKey || e.shiftKey) return;
      e.preventDefault();
      bootstrap.Modal.getInstance($('.modal.show:not(#signupModal)'))?.hide();
      modal.show();
    });
    if (location.hash === '#cadastro') modal.show();

    modalEl.addEventListener('hidden.bs.modal', () => {
      if (!success.classList.contains('d-none')) { success.classList.add('d-none'); form.hidden = false; form.reset(); }
      errorBox.classList.add('d-none');
    });

    form.addEventListener('submit', async e => {
      e.preventDefault();
      errorBox.classList.add('d-none');
      const pass = form.senha, confirm = form.confirmacao;
      confirm.setCustomValidity(pass.value && pass.value !== confirm.value ? (I18N.err_confirm || 'As senhas não coincidem.') : '');
      if (!form.checkValidity()) { form.reportValidity(); return; }
      const btn = form.querySelector('[type=submit]');
      btn.disabled = true; btn.textContent = btn.dataset.sending;
      try {
        const res = await fetch('cadastro.php?ajax=1', { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (data.ok) {
          form.hidden = true;
          success.classList.remove('d-none');
          setTimeout(() => modal.hide(), 3500);
        } else {
          errorBox.textContent = data.error || 'Erro';
          errorBox.classList.remove('d-none');
        }
      } catch (err) {
        errorBox.textContent = 'Não foi possível enviar agora. Tente novamente.';
        errorBox.classList.remove('d-none');
      } finally {
        btn.disabled = false; btn.textContent = btn.dataset.label;
      }
    });
    form.confirmacao?.addEventListener('input', () => form.confirmacao.setCustomValidity(''));
  }

  /* ------------------------------------------------------------------
     Menu mobile, alfabeto, ordenação e animações
  ------------------------------------------------------------------ */
  function initUi() {
    const menu = $('.mobile-menu'), nav = $('.site-header nav');
    menu?.addEventListener('click', e => { e.stopPropagation(); const open = nav.classList.toggle('mobile-open'); menu.setAttribute('aria-expanded', String(open)); });
    document.addEventListener('click', e => {
      if (nav?.classList.contains('mobile-open') && !nav.contains(e.target) && e.target !== menu) { nav.classList.remove('mobile-open'); menu?.setAttribute('aria-expanded', 'false'); }
    });

    const alphabet = $('.alphabet-scroller .alphabet');
    $('[data-alphabet-prev]')?.addEventListener('click', () => alphabet?.scrollBy({ left: -180, behavior: 'smooth' }));
    $('[data-alphabet-next]')?.addEventListener('click', () => alphabet?.scrollBy({ left: 180, behavior: 'smooth' }));
    const sel = alphabet?.querySelector('.selected');
    if (sel && alphabet.scrollWidth > alphabet.clientWidth) alphabet.scrollLeft = sel.offsetLeft - alphabet.clientWidth / 2;

    $$('[data-autosubmit]').forEach(s => s.addEventListener('change', () => (s.form || document.getElementById(s.getAttribute('form')))?.submit()));

    const items = $$('.reveal');
    if ('IntersectionObserver' in window) {
      const obs = new IntersectionObserver(entries => entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('is-visible'); obs.unobserve(en.target); } }), { threshold: .12 });
      items.forEach(i => obs.observe(i));
    } else items.forEach(i => i.classList.add('is-visible'));
  }

  document.addEventListener('DOMContentLoaded', () => { initCookies(); initSlider(); initSignup(); initUi(); });
})();
