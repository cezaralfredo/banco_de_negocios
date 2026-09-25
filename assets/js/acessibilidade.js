/* Banco de Negócios — recursos de acessibilidade
   Fonte maior/menor · Contraste escuro · Destacar links · Leitor de texto · Fonte para dislexia
   As escolhas ficam salvas no navegador (localStorage) quando o visitante aceita cookies de
   "Preferências"; caso contrário, valem apenas durante a sessão (sessionStorage). */
(() => {
  'use strict';
  const html = document.documentElement;
  const I18N = window.BN_I18N || {};
  const KEY = 'bn_a11y';
  const MIN = 0.85, MAX = 1.6, STEP = 0.15;
  const store = () => (window.BNConsent?.allows('preferences') ? localStorage : sessionStorage);
  const load = () => { try { return JSON.parse(localStorage.getItem(KEY) || sessionStorage.getItem(KEY) || '{}'); } catch (e) { return {}; } };
  let state = Object.assign({ scale: 1, contrast: false, links: false, reader: false, dyslexia: false }, load());

  const save = () => {
    try {
      const data = JSON.stringify({ ...state, reader: false });
      localStorage.removeItem(KEY); sessionStorage.removeItem(KEY);
      store().setItem(KEY, data);
    } catch (e) {}
  };
  const announce = msg => { const live = document.querySelector('[data-a11y-live]'); if (live) { live.textContent = ''; setTimeout(() => { live.textContent = msg; }, 50); } };

  /* ---------- Tamanho da fonte: escala o font-size real de cada elemento ---------- */
  const SKIP = '.a11y-column, .a11y-floating, script, style, svg, svg *, img, br, iframe';
  function applyScale() {
    document.querySelectorAll('body *').forEach(el => {
      if (el.matches(SKIP) || el.closest('.a11y-column, .a11y-floating')) return;
      if (!el.dataset.bnFs) {
        if (state.scale === 1) return;
        el.dataset.bnFs = parseFloat(getComputedStyle(el).fontSize);
      }
      const base = parseFloat(el.dataset.bnFs);
      el.style.fontSize = state.scale === 1 ? '' : (base * state.scale).toFixed(2) + 'px';
    });
    html.classList.toggle('a11y-scaled', state.scale !== 1);
  }

  /* ---------- Leitor de texto (Web Speech API) ---------- */
  const synth = window.speechSynthesis;
  let hoverTimer = null, lastEl = null;
  const lang = () => document.body.dataset.lang || html.lang || 'pt-BR';
  function speak(text) {
    if (!synth || !text) return;
    synth.cancel();
    const u = new SpeechSynthesisUtterance(text.replace(/\s+/g, ' ').trim().slice(0, 600));
    u.lang = lang();
    const voice = synth.getVoices().find(v => v.lang && v.lang.toLowerCase().startsWith(u.lang.slice(0, 2).toLowerCase()));
    if (voice) u.voice = voice;
    u.rate = 1;
    synth.speak(u);
  }
  const READABLE = 'h1,h2,h3,h4,h5,p,li,a,button,label,small,b,strong,span.rating,time,figcaption,td,th,blockquote';
  function textOf(el) {
    if (el.matches('input,select,textarea')) return el.getAttribute('aria-label') || el.placeholder || el.closest('label')?.innerText || '';
    return el.getAttribute('aria-label') || el.innerText || el.alt || '';
  }
  function onOver(e) {
    const el = e.target.closest(READABLE);
    if (!el || el === lastEl || el.closest('.a11y-column, .a11y-floating')) return;
    clearTimeout(hoverTimer);
    hoverTimer = setTimeout(() => {
      document.querySelectorAll('.a11y-reading').forEach(x => x.classList.remove('a11y-reading'));
      lastEl = el; el.classList.add('a11y-reading'); speak(textOf(el));
    }, 350);
  }
  function onFocus(e) { if (e.target.closest('.a11y-column, .a11y-floating')) return; speak(textOf(e.target)); }
  function onSelect() { const s = String(window.getSelection() || '').trim(); if (s.length > 2) speak(s); }
  function setReader(on) {
    state.reader = on;
    html.classList.toggle('a11y-reader', on);
    const fn = on ? 'addEventListener' : 'removeEventListener';
    document[fn]('mouseover', onOver);
    document[fn]('focusin', onFocus);
    document[fn]('mouseup', onSelect);
    if (!on) { synth?.cancel(); document.querySelectorAll('.a11y-reading').forEach(x => x.classList.remove('a11y-reading')); lastEl = null; }
    if (on && !synth) { announce('Seu navegador não oferece síntese de voz.'); return; }
    on ? speak(I18N.readerOn || 'Leitor de texto ativado.') : announce(I18N.readerOff || 'Leitor de texto desativado.');
  }

  /* ---------- Estado visual dos botões ---------- */
  function render() {
    html.classList.toggle('a11y-contrast', !!state.contrast);
    html.classList.toggle('a11y-links', !!state.links);
    html.classList.toggle('a11y-dyslexia', !!state.dyslexia);
    document.querySelectorAll('[data-a11y]').forEach(b => {
      const k = b.dataset.a11y;
      if (b.hasAttribute('aria-pressed')) b.setAttribute('aria-pressed', String(!!state[k]));
      if (k === 'font-up') b.disabled = state.scale >= MAX;
      if (k === 'font-down') b.disabled = state.scale <= MIN;
    });
  }

  function act(action) {
    switch (action) {
      case 'font-up': state.scale = Math.min(MAX, +(state.scale + STEP).toFixed(2)); applyScale(); break;
      case 'font-down': state.scale = Math.max(MIN, +(state.scale - STEP).toFixed(2)); applyScale(); break;
      case 'contrast': state.contrast = !state.contrast; break;
      case 'links': state.links = !state.links; break;
      case 'dyslexia': state.dyslexia = !state.dyslexia; requestAnimationFrame(() => { if (state.scale !== 1) { document.querySelectorAll('[data-bn-fs]').forEach(el => { delete el.dataset.bnFs; el.style.fontSize = ''; }); applyScale(); } }); break;
      case 'reader': setReader(!state.reader); break;
      case 'reset':
        state = { scale: 1, contrast: false, links: false, reader: false, dyslexia: false };
        setReader(false); applyScale(); break;
    }
    render(); save();
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-a11y]').forEach(b => b.addEventListener('click', () => act(b.dataset.a11y)));

    // Painel flutuante aberto pelo ícone da barra superior
    const panel = document.querySelector('[data-a11y-panel]');
    const toggle = document.querySelector('[data-a11y-toggle]');
    const setOpen = open => { if (!panel) return; panel.hidden = !open; toggle?.setAttribute('aria-expanded', String(open)); if (open) panel.querySelector('[data-a11y]')?.focus(); };
    toggle?.addEventListener('click', e => { e.stopPropagation(); setOpen(panel.hidden); });
    document.querySelector('[data-a11y-close]')?.addEventListener('click', () => { setOpen(false); toggle?.focus(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && panel && !panel.hidden) { setOpen(false); toggle?.focus(); } });
    document.addEventListener('click', e => { if (panel && !panel.hidden && !panel.contains(e.target) && !toggle.contains(e.target)) setOpen(false); });

    // Se o consentimento de preferências mudar, move as escolhas para o armazenamento correto
    document.addEventListener('bn:consent', save);

    if (state.scale !== 1) applyScale();
    state.reader = false;
    render();
  });
})();
