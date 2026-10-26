<?php
declare(strict_types=1);

/* ==========================================================================
   Backup do banco de dados e da mídia do cliente
   - Gerado em PHP puro (não depende de mysqldump nem de terminal).
   - Arquivo .zip em storage/<cliente>/backups (sem acesso pela web).
   - Automático: diário/semanal, disparado pelo acesso ao painel ou por
     cron.php?chave=... (para agendar no cPanel ou no cron do servidor).
   ========================================================================== */

function backup_dir(): string
{
    $d = storage_path('backups');
    if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "Require all denied\n");
    return $d;
}

/** Gera o dump SQL do banco atual em $file. */
function backup_dump_sql(string $file): void
{
    global $pdo;
    $fh = fopen($file, 'wb');
    if (!$fh) throw new RuntimeException('Não foi possível criar o arquivo de backup.');
    fwrite($fh, "-- Backup do CMS — cliente " . BN_TENANT . " — " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as [$t]) {
        if ($t === 'login_tentativas') continue;
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        fwrite($fh, "DROP TABLE IF EXISTS `$t`;\n$create;\n");
        $st = $pdo->query("SELECT * FROM `$t`", PDO::FETCH_NUM);
        $batch = [];
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row)) . ')';
            if (count($batch) >= 200) { fwrite($fh, "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n"); $batch = []; }
        }
        if ($batch) fwrite($fh, "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n");
        fwrite($fh, "\n");
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
}

/** Assinatura HMAC do conteúdo SQL: só backups gerados por ESTE cliente nesta instalação podem ser restaurados. */
function backup_signature(string $sql): string
{
    return hash_hmac('sha256', $sql, app_key() . '|' . BN_TENANT);
}

/** Cria um backup .zip (banco + mídia opcional). Retorna o nome do arquivo. */
function backup_create(bool $withMedia = true, string $origem = 'manual'): string
{
    @set_time_limit(300);
    $suffix = ['automatico' => '-auto', 'pre-restauracao' => '-pre'][$origem] ?? '';
    $name = 'backup-' . BN_TENANT . '-' . date('Ymd-His') . $suffix . '.zip';
    $zipFile = backup_dir() . '/' . $name;
    $sql = backup_dir() . '/tmp-' . bin2hex(random_bytes(4)) . '.sql';
    backup_dump_sql($sql);
    if (!class_exists('ZipArchive')) {
        // sem ZipArchive: guarda apenas o .sql
        $name = str_replace('.zip', '.sql', $name);
        rename($sql, backup_dir() . '/' . $name);
        file_put_contents(backup_dir() . '/' . $name . '.sig', backup_signature((string)file_get_contents(backup_dir() . '/' . $name)));
    } else {
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Falha ao criar o .zip.');
        $zip->addFile($sql, 'banco.sql');
        $zip->addFromString('assinatura.txt', backup_signature((string)file_get_contents($sql)));
        $zip->addFromString('LEIA-ME.txt', "Backup do cliente " . BN_TENANT . " gerado em " . date('d/m/Y H:i') . ".\nbanco.sql: dados do banco. media/: arquivos enviados.\nRestaure em Admin › Backup.");
        if ($withMedia) {
            foreach ([media_base(), 'assets/img/uploads'] as $dir) {
                $abs = BN_ROOT . '/' . $dir;
                if (!is_dir($abs)) continue;
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) if ($f->isFile()) $zip->addFile($f->getPathname(), 'arquivos/' . $dir . '/' . substr($f->getPathname(), strlen($abs) + 1));
            }
        }
        $zip->close();
        @unlink($sql);
    }
    global $pdo;
    save_setting($pdo, 'backup_ultimo', date('Y-m-d H:i:s'));
    audit('backup_criado', 'backup', null, ['arquivo' => $name, 'origem' => $origem, 'midia' => $withMedia]);
    if ($origem !== 'pre-restauracao') backup_rotate();
    return $name;
}

function backup_list(): array
{
    $out = [];
    foreach (glob(backup_dir() . '/backup-*.{zip,sql}', GLOB_BRACE) ?: [] as $f) {
        $out[] = ['nome' => basename($f), 'tamanho' => filesize($f), 'data' => filemtime($f), 'auto' => str_contains($f, '-auto.'), 'pre' => str_contains($f, '-pre.')];
    }
    usort($out, fn($a, $b) => $b['data'] <=> $a['data']);
    return $out;
}

function backup_path(string $name): ?string
{
    if (!preg_match('/^backup-[a-z0-9-]+-\d{8}-\d{6}(-auto|-pre|-env)?\.(zip|sql)$/', $name)) return null;
    $p = backup_dir() . '/' . $name;
    return is_file($p) ? $p : null;
}

/** Mantém somente os N backups mais recentes. */
function backup_rotate(): void
{
    global $pdo;
    $keep = max(1, (int)setting($pdo, 'backup_manter', '7'));
    foreach (array_slice(backup_list(), $keep) as $b) { @unlink(backup_dir() . '/' . $b['nome']); @unlink(backup_dir() . '/' . $b['nome'] . '.sig'); }
}

/** Restaura um backup (substitui o banco atual; arquivos de mídia são repostos). */
function backup_restore(string $name, bool $restoreMedia = true): void
{
    global $pdo;
    @set_time_limit(600);
    $path = backup_path($name);
    if (!$path) throw new RuntimeException('Backup não encontrado.');
    // 1) Lê e valida o backup ANTES de qualquer alteração
    $files = [];
    if (str_ends_with($path, '.sql')) {
        $sql = (string)file_get_contents($path);
        $sig = is_file($path . '.sig') ? trim((string)file_get_contents($path . '.sig')) : '';
    } else {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('Arquivo .zip inválido.');
        $sql = (string)$zip->getFromName('banco.sql');
        $sig = trim((string)$zip->getFromName('assinatura.txt'));
        if ($restoreMedia) {
            $allowedDirs = [media_base() . '/'];
            if (is_primary_tenant()) $allowedDirs[] = 'assets/img/uploads/';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = (string)$zip->getNameIndex($i);
                if (!str_starts_with($n, 'arquivos/') || str_contains($n, '..') || str_contains($n, "\0")) continue;
                $rel = substr($n, 9);
                $okDir = false;
                foreach ($allowedDirs as $d) if (str_starts_with($rel, $d)) $okDir = true;
                // só arquivos de mídia do próprio cliente, com extensões de imagem/PDF
                if (!$okDir || !preg_match('#^[a-z0-9/_.-]+\.(jpe?g|png|webp|gif|pdf)$#i', $rel)) continue;
                $files[$rel] = $i;
            }
        }
    }
    if (trim($sql) === '') throw new RuntimeException('O backup não contém o arquivo banco.sql.');
    if ($sig === '' || !hash_equals(backup_signature($sql), $sig)) {
        throw new RuntimeException('Assinatura inválida: este backup não foi gerado por este cliente nesta instalação (ou foi alterado). Por segurança, ele não pode ser restaurado.');
    }
    // 2) Backup de segurança do estado atual (não descarta backups antigos)
    backup_create(false, 'pre-restauracao');
    // 3) Restaura banco e mídia
    foreach (sql_split($sql) as $stmt) $pdo->exec($stmt);
    if ($files && isset($zip)) {
        foreach ($files as $rel => $i) {
            $dst = BN_ROOT . '/' . $rel;
            if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0755, true);
            file_put_contents($dst, $zip->getFromIndex($i));
        }
    }
    if (isset($zip)) $zip->close();
    setting_cache_clear();
    audit('backup_restaurado', 'backup', null, ['arquivo' => $name, 'arquivos_midia' => count($files)]);
}

/** Divide um script SQL em comandos (respeitando aspas). */
function sql_split(string $sql): array
{
    $out = []; $buf = ''; $q = null; $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($q) {
            $buf .= $c;
            if ($c === '\\') { $buf .= $sql[++$i] ?? ''; continue; }
            if ($c === $q) $q = null;
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') { $q = $c; $buf .= $c; continue; }
        if ($c === '-' && ($sql[$i + 1] ?? '') === '-') { while ($i < $len && $sql[$i] !== "\n") $i++; continue; }
        if ($c === ';') { if (trim($buf) !== '') $out[] = trim($buf); $buf = ''; continue; }
        $buf .= $c;
    }
    if (trim($buf) !== '') $out[] = trim($buf);
    return $out;
}

/** Backup automático: executado no máximo uma vez por período. */
function backup_maybe_auto(): void
{
    global $pdo;
    $freq = setting($pdo, 'backup_auto', 'semanal'); // desligado, diario, semanal
    if ($freq === 'desligado') return;
    $last = strtotime(setting($pdo, 'backup_ultimo_auto', '2000-01-01'));
    if (time() - $last < ($freq === 'diario' ? 86400 : 604800)) return;
    save_setting($pdo, 'backup_ultimo_auto', date('Y-m-d H:i:s')); // evita execuções em paralelo
    try {
        backup_create(setting($pdo, 'backup_auto_midia', '1') === '1', 'automatico');
    } catch (Throwable $e) {
        error_log('[CMS] Backup automático falhou: ' . $e->getMessage());
    }
}
