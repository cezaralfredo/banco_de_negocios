  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Editor HTML simples usado em páginas e notícias */
document.querySelectorAll('[data-editor-tag]').forEach(btn => btn.addEventListener('click', () => {
  const field = document.querySelector(btn.closest('[data-editor]')?.dataset.editor || '#page-content-editor');
  const [open, close] = btn.dataset.editorTag.split('|');
  const s = field.selectionStart, en = field.selectionEnd, sel = field.value.slice(s, en) || 'texto';
  field.setRangeText(open + sel + close, s, en, 'end'); field.focus();
}));
document.querySelectorAll('[data-editor-image]').forEach(btn => btn.addEventListener('click', () => {
  const url = prompt('URL da imagem:'); if (!url) return;
  const field = document.querySelector(btn.closest('[data-editor]')?.dataset.editor || '#page-content-editor');
  field.setRangeText('<img src="' + url.replace(/"/g, '') + '" alt="">', field.selectionStart, field.selectionEnd, 'end'); field.focus();
}));
/* Rola até o formulário em edição */
document.querySelector('.is-editing')?.scrollIntoView({ block: 'start', behavior: 'smooth' });
</script>
</body>
</html>
