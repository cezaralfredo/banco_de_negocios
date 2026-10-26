<?php
/* Registro do consentimento de cookies (LGPD — prestação de contas).
   Não guarda o IP em texto puro: apenas um hash com "sal" da instalação. */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$pdo) { http_response_code(204); exit; }
ensure_schema($pdo);
$id = preg_replace('/[^a-z0-9]/i', '', (string)($_POST['id'] ?? ''));
$escolha = in_array($_POST['escolha'] ?? '', ['aceitar', 'recusar', 'personalizar'], true) ? $_POST['escolha'] : 'personalizar';
$allowed = ['necessary', 'preferences', 'statistics', 'marketing'];
$cats = implode(',', array_intersect($allowed, explode(',', (string)($_POST['categorias'] ?? ''))));
$versao = substr(preg_replace('/[^0-9.]/', '', (string)($_POST['v'] ?? '1')), 0, 10) ?: '1';
if ($id === '' || strlen($id) > 40) { http_response_code(400); exit; }
$salt = setting($pdo, 'consent_salt', '');
if ($salt === '') { $salt = bin2hex(random_bytes(16)); save_setting($pdo, 'consent_salt', $salt); }
$ipHash = hash('sha256', $salt . ($_SERVER['REMOTE_ADDR'] ?? ''));
try {
    $pdo->prepare('INSERT INTO consentimentos_cookies (consent_id,escolha,categorias,versao,ip_hash,user_agent) VALUES (?,?,?,?,?,?)')
        ->execute([$id, $escolha, $cats, $versao, $ipHash, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
} catch (Throwable $e) { }
echo '{"ok":true}';
