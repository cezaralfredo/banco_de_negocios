<?php
/* API JSON da biblioteca de mídia (usada pelo seletor de imagens dos formulários). */
require_once __DIR__ . '/../config/database.php';
require_perm('ler:midia');
header('Content-Type: application/json; charset=utf-8');
$acao = (string)($_GET['acao'] ?? 'listar');

if ($acao === 'listar') {
    $q = trim((string)($_GET['q'] ?? ''));
    $pasta = (int)($_GET['pasta'] ?? 0);
    $sql = 'SELECT id,nome,arquivo,mime,largura,altura,alt FROM midias WHERE 1=1';
    $p = [];
    if ($q !== '') { $sql .= ' AND (nome LIKE ? OR alt LIKE ? OR titulo LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
    if ($pasta) { $sql .= ' AND pasta_id=?'; $p[] = $pasta; }
    if (($_GET['tipo'] ?? 'imagem') === 'imagem') $sql .= " AND mime LIKE 'image/%'";
    echo json_encode(['ok' => true, 'itens' => db_rows($pdo, $sql . ' ORDER BY id DESC LIMIT 120', $p), 'pastas' => db_rows($pdo, 'SELECT id,nome FROM midia_pastas ORDER BY nome')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($acao === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_perm('enviar:midia');
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { echo json_encode(['ok' => false, 'error' => 'Sessão expirada.']); exit; }
    try {
        $path = media_store_upload($_FILES['arquivo'] ?? [], 'arquivo', 2400, 2400, (int)($_POST['pasta'] ?? 0) ?: null, false);
        echo json_encode(['ok' => (bool)$path, 'arquivo' => $path]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
echo json_encode(['ok' => false, 'error' => 'Ação inválida.']);
