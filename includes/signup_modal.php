<?php
/* Modal único de cadastro — usado pela barra superior, pelo banner, pela seção
   "Amplie suas oportunidades!" e por qualquer link para cadastro.php. */
$termsLink = '<a target="_blank" rel="noopener" href="pagina.php?slug=termos-de-uso">' . e(t('signup.terms_link')) . '</a>';
?>
<div class="modal fade" id="signupModal" tabindex="-1" aria-labelledby="signupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content auth-card">
      <div class="modal-header">
        <div>
          <p class="eyebrow dark mb-1"><?= e(t('signup.eyebrow')) ?></p>
          <h2 class="modal-title fs-4" id="signupModalLabel"><?= e(t('signup.title')) ?></h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('signup.close')) ?>"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-success d-none" data-signup-success role="status">
          <strong><?= e(t('signup.success_title')) ?></strong><br><?= e(t('signup.success_text')) ?>
        </div>
        <form class="auth-form" method="post" action="cadastro.php" data-signup-form novalidate>
          <div class="alert alert-danger d-none" data-signup-error role="alert"></div>
          <p class="muted mb-0"><?= e(t('signup.intro')) ?></p>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <label><?= e(t('signup.name')) ?><input name="nome" required minlength="3" autocomplete="name"></label>
          <label><?= e(t('signup.email')) ?><input type="email" name="email" required autocomplete="email"></label>
          <label><?= e(t('signup.password')) ?><input type="password" name="senha" required minlength="8" autocomplete="new-password"><small class="muted fw-normal"><?= e(t('signup.password_hint')) ?></small></label>
          <label><?= e(t('signup.confirm')) ?><input type="password" name="confirmacao" required minlength="8" autocomplete="new-password"></label>
          <label class="check-row"><input type="checkbox" name="termos" value="1" required> <span><?= str_replace('{link}', $termsLink, e(t('signup.terms'))) ?></span></label>
          <button class="btn btn-primary w-100" type="submit" data-label="<?= e(t('signup.submit')) ?>" data-sending="<?= e(t('signup.sending')) ?>"><?= e(t('signup.submit')) ?></button>
        </form>
      </div>
      <div class="modal-footer">
        <small><?= e(t('signup.have_account')) ?> <a href="admin/index.php"><?= e(t('signup.login')) ?></a></small>
      </div>
    </div>
  </div>
</div>
