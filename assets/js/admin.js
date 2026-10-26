/* Painel administrativo — interações comuns */
(() => {
  'use strict';
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const csrf = document.body.dataset.csrf;

  /* Menu lateral no celular */
  const side = $('#adminSide'), toggle = $('[data-admin-toggle]');
  const setMenu = open => { document.body.classList.toggle('admin-menu-open', open); toggle?.setAttribute('aria-expanded', String(open)); };
  toggle?.addEventListener('click', () => setMenu(!document.body.classList.contains('admin-menu-open')));
  $('[data-admin-close]')?.addEventListener('click', () => setMenu(false));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') setMenu(false); });

  /* Confirmação de ações destrutivas:
     <a href="..." data-confirm="Mensagem">  ou  <button form=... data-confirm="..." data-confirm-word="EXCLUIR"> */
  const cm = $('#confirmModal');
  const modal = cm && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(cm) : null;
  let pending = null;
  document.addEventListener('click', e => {
    const el = e.target.closest('[data-confirm]');
    if (!el || !modal) return;
    if (el.dataset.confirmed === '1') { el.dataset.confirmed = ''; return; }
    e.preventDefault();
    pending = el;
    $('[data-confirm-text]', cm).textContent = el.dataset.confirm;
    const word = el.dataset.confirmWord || '';
    const wrap = $('[data-confirm-type-wrap]', cm), input = $('[data-confirm-input]', cm), ok = $('[data-confirm-ok]', cm);
    wrap.hidden = !word; input.value = '';
    $('[data-confirm-word]', cm).textContent = word;
    ok.textContent = el.dataset.confirmButton || 'Confirmar';
    ok.className = 'btn ' + (el.dataset.confirmStyle === 'primary' ? 'btn-primary' : 'btn-danger');
    ok.disabled = !!word;
    const norm = v => v.normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toUpperCase();
    input.oninput = () => { ok.disabled = norm(input.value) !== norm(word); };
    modal.show();
    setTimeout(() => (word ? input : ok).focus(), 300);
  });
  $('[data-confirm-ok]', cm || document)?.addEventListener('click', () => {
    if (!pending) return;
    const el = pending; pending = null; modal.hide();
    el.dataset.confirmed = '1';
    if (el.tagName === 'A') { location.href = el.href; return; }
    if (el.form || el.getAttribute('form')) { const f = el.form || document.getElementById(el.getAttribute('form')); if (el.name) { const h = document.createElement('input'); h.type = 'hidden'; h.name = el.name; h.value = el.value; f.appendChild(h); } f.requestSubmit ? f.requestSubmit() : f.submit(); return; }
    el.click();
  });

  /* Mensagens somem sozinhas (exceto erros e as que têm "Desfazer", que ficam 12s) */
  $$('.flash').forEach(f => setTimeout(() => f.classList.add('flash-hide'), f.classList.contains('error') ? 20000 : (f.querySelector('.undo-link') ? 12000 : 6000)));

  /* Editor HTML simples (páginas e notícias) */
  $$('[data-editor-tag]').forEach(btn => btn.addEventListener('click', () => {
    const field = $(btn.closest('[data-editor]')?.dataset.editor || '#page-content-editor');
    const [open, close] = btn.dataset.editorTag.split('|');
    const s = field.selectionStart, en = field.selectionEnd, sel = field.value.slice(s, en) || 'texto';
    field.setRangeText(open + sel + close, s, en, 'end'); field.focus();
  }));
  $$('[data-editor-image]').forEach(btn => btn.addEventListener('click', () => {
    const field = $(btn.closest('[data-editor]')?.dataset.editor || '#page-content-editor');
    openPicker(item => {
      const alt = (item.alt || item.nome || '').replace(/"/g, '');
      field.setRangeText('<img src="' + item.arquivo + '" alt="' + alt + '">', field.selectionStart, field.selectionEnd, 'end'); field.focus();
    });
  }));

  /* Contadores de caracteres e prévia do Google (SEO) */
  $$('[data-count]').forEach(f => {
    const max = +f.dataset.count, out = document.createElement('small');
    out.className = 'char-count'; f.after(out);
    const upd = () => { out.textContent = f.value.length + '/' + max; out.classList.toggle('over', f.value.length > max); };
    f.addEventListener('input', upd); upd();
  });
  $$('.seo-box').forEach(box => {
    const t = $('[data-seo-title]', box), d = $('[data-seo-desc]', box), pv = $('[data-seo-preview]', box);
    const form = box.closest('form'), mainTitle = form?.querySelector('[name=titulo]');
    const upd = () => { $('.sp-title', pv).textContent = t.value || mainTitle?.value || 'Título da página'; $('.sp-desc', pv).textContent = d.value || 'A descrição aparece aqui nos resultados de busca.'; };
    [t, d, mainTitle].forEach(x => x?.addEventListener('input', upd));
  });

  /* Slug automático a partir do título (enquanto o slug não for editado manualmente) */
  const slugify = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  $$('form').forEach(f => {
    const title = f.querySelector('[name=titulo]'), slug = f.querySelector('[name=slug][data-auto-slug]');
    if (!title || !slug) return;
    let touched = slug.value !== '';
    slug.addEventListener('input', () => { touched = true; });
    title.addEventListener('input', () => { if (!touched) slug.value = slugify(title.value); });
  });

  /* Aviso ao sair com alterações não salvas */
  $$('form[data-dirty-check]').forEach(f => {
    let dirty = false;
    f.addEventListener('input', () => { dirty = true; });
    f.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  });

  /* ---------------- Biblioteca de mídia: seletor ---------------- */
  const mp = $('#mediaPicker');
  const mpModal = mp && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(mp) : null;
  let onPick = null, folders = false;
  async function loadMedia() {
    const grid = $('[data-mp-grid]', mp);
    const q = $('[data-mp-search]', mp).value, folder = $('[data-mp-folder]', mp).value;
    grid.innerHTML = '<p class="muted">Carregando…</p>';
    const res = await fetch('midia_api.php?acao=listar&q=' + encodeURIComponent(q) + '&pasta=' + encodeURIComponent(folder), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (!folders && data.pastas) {
      folders = true;
      data.pastas.forEach(p => { const o = document.createElement('option'); o.value = p.id; o.textContent = p.nome; $('[data-mp-folder]', mp).appendChild(o); });
    }
    grid.innerHTML = '';
    if (!data.itens?.length) { grid.innerHTML = '<p class="muted">Nenhum arquivo encontrado.</p>'; return; }
    data.itens.forEach(item => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'mp-item';
      b.innerHTML = (item.mime.startsWith('image/') ? '<img loading="lazy" alt="">' : '<span class="mp-doc">PDF</span>') + '<span class="mp-name"></span>';
      if (b.querySelector('img')) b.querySelector('img').src = '../' + item.arquivo;
      b.querySelector('.mp-name').textContent = item.nome;
      b.title = item.nome + (item.largura ? ` (${item.largura}×${item.altura})` : '');
      b.addEventListener('click', () => { onPick?.(item); mpModal.hide(); });
      grid.appendChild(b);
    });
  }
  function openPicker(cb) { if (!mpModal) return; onPick = cb; mpModal.show(); loadMedia(); }
  window.CMSMediaPicker = openPicker;
  if (mp) {
    let t; $('[data-mp-search]', mp).addEventListener('input', () => { clearTimeout(t); t = setTimeout(loadMedia, 300); });
    $('[data-mp-folder]', mp).addEventListener('change', loadMedia);
    $('[data-mp-upload]', mp).addEventListener('change', async e => {
      const file = e.target.files[0]; if (!file) return;
      const fd = new FormData(); fd.append('arquivo', file); fd.append('csrf_token', csrf); fd.append('pasta', $('[data-mp-folder]', mp).value);
      const res = await fetch('midia_api.php?acao=enviar', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await res.json();
      if (!data.ok) alert(data.error || 'Falha no envio'); else loadMedia();
      e.target.value = '';
    });
  }
  /* Campos de imagem com "Escolher da biblioteca" */
  $$('[data-media-field]').forEach(w => {
    const input = $('[data-media-input]', w), preview = $('[data-media-preview]', w), clear = $('[data-media-clear]', w), up = $('[data-media-upload]', w);
    const show = src => { preview.innerHTML = src ? '<img alt="">' : '<span>Sem imagem</span>'; if (src) preview.querySelector('img').src = src; clear.hidden = !src; };
    $('[data-media-pick]', w)?.addEventListener('click', () => openPicker(item => { input.value = item.arquivo; if (up) up.value = ''; show('../' + item.arquivo); }));
    clear?.addEventListener('click', () => { input.value = ''; if (up) up.value = ''; show(''); });
    up?.addEventListener('change', () => { const f = up.files[0]; if (f) show(URL.createObjectURL(f)); });
  });
})();

/* Menu lateral em accordion (sanfona): abre uma seção e fecha as demais */
(() => {
  const acc = document.querySelector('[data-side-accordion]');
  if (!acc) return;
  const groups = [...acc.querySelectorAll('.side-group')];
  const setOpen = (g, open) => {
    g.classList.toggle('is-open', open);
    const btn = g.querySelector('[data-side-toggle]');
    btn.setAttribute('aria-expanded', String(open));
    g.querySelectorAll('.side-group-inner a').forEach(a => open ? a.removeAttribute('tabindex') : a.setAttribute('tabindex', '-1'));
  };
  groups.forEach(g => g.querySelector('[data-side-toggle]').addEventListener('click', () => {
    const willOpen = !g.classList.contains('is-open');
    groups.forEach(o => { if (o !== g) setOpen(o, false); });
    setOpen(g, willOpen);
  }));
  // Navegação por teclado entre os títulos das seções (setas, Home, End)
  acc.addEventListener('keydown', e => {
    const btns = groups.map(g => g.querySelector('[data-side-toggle]'));
    const i = btns.indexOf(document.activeElement);
    if (i < 0) return;
    const go = j => { e.preventDefault(); btns[(j + btns.length) % btns.length].focus(); };
    if (e.key === 'ArrowDown') go(i + 1);
    if (e.key === 'ArrowUp') go(i - 1);
    if (e.key === 'Home') go(0);
    if (e.key === 'End') go(btns.length - 1);
  });
})();
