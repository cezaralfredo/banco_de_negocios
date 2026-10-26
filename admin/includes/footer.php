  </div>
</div>

<!-- Confirmação de ações destrutivas -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5" id="confirmTitle">Confirmar ação</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
      <div class="modal-body">
        <p data-confirm-text></p>
        <div data-confirm-type-wrap hidden>
          <label class="form-label small">Para confirmar, digite <strong data-confirm-word></strong>:</label>
          <input class="form-control" data-confirm-input autocomplete="off">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" data-confirm-ok>Confirmar</button>
      </div>
    </div>
  </div>
</div>

<!-- Seletor da biblioteca de mídia -->
<div class="modal fade" id="mediaPicker" tabindex="-1" aria-labelledby="mediaPickerTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="mediaPickerTitle">Biblioteca de mídia</h2>
        <input type="search" class="form-control form-control-sm ms-3" style="max-width:260px" placeholder="Buscar arquivo…" data-mp-search aria-label="Buscar arquivo">
        <select class="form-select form-select-sm ms-2" style="max-width:200px" data-mp-folder aria-label="Pasta"><option value="">Todas as pastas</option></select>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body"><div class="mp-grid" data-mp-grid><p class="muted">Carregando…</p></div></div>
      <div class="modal-footer justify-content-between">
        <label class="btn btn-sm btn-outline-secondary mb-0">⬆ Enviar arquivo<input type="file" accept="image/*" hidden data-mp-upload></label>
        <span class="muted small">Clique em uma imagem para selecioná-la.</span>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('assets/js/admin.js', '../')) ?>"></script>
<?php do_action('admin_footer'); ?>
</body>
</html>
