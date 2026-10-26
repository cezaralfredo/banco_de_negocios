<?php
/* Página de administração registrada por um plugin (plugin_admin_page()). */
require __DIR__ . '/includes/init.php';
$pp = plugin_admin_pages()[$_GET['p'] ?? ''] ?? null;
if (!$pp || !is_file($pp['file'])) { flash('error', 'Página de plugin não encontrada (o plugin está ativo?).'); redirect('plugins.php'); }
$admin_title = $pp['title'];
include $pp['file'];
