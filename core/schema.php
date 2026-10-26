<?php
declare(strict_types=1);

/* ==========================================================================
   Migração do schema — versão 7 (CMS profissional)
   Idempotente: pode rodar várias vezes. Chamada por ensure_schema().
   ========================================================================== */

function schema_v7_statements(): array
{
    return [
        // RBAC
        "CREATE TABLE IF NOT EXISTS papeis (id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(60) NOT NULL UNIQUE, nome VARCHAR(80) NOT NULL, descricao VARCHAR(255) NULL, sistema TINYINT(1) NOT NULL DEFAULT 0, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS papel_permissoes (papel_id INT NOT NULL, permissao VARCHAR(80) NOT NULL, PRIMARY KEY (papel_id, permissao)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE usuarios ADD COLUMN papel_id INT NULL",
        "ALTER TABLE usuarios ADD COLUMN ultimo_login DATETIME NULL",
        "CREATE TABLE IF NOT EXISTS login_tentativas (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(180) NOT NULL, ip VARCHAR(45) NOT NULL, sucesso TINYINT(1) NOT NULL DEFAULT 0, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_login (email, ip, criado_em)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS senha_resets (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, token_hash CHAR(64) NOT NULL, expira_em DATETIME NOT NULL, usado TINYINT(1) NOT NULL DEFAULT 0, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (token_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Auditoria
        "CREATE TABLE IF NOT EXISTS auditoria (id BIGINT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NULL, usuario_nome VARCHAR(120) NULL, acao VARCHAR(40) NOT NULL, recurso VARCHAR(60) NULL, recurso_id VARCHAR(40) NULL, detalhes TEXT NULL, ip VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_aud_data (criado_em), INDEX idx_aud_acao (acao), INDEX idx_aud_user (usuario_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Versões e lixeira
        "CREATE TABLE IF NOT EXISTS revisoes (id BIGINT AUTO_INCREMENT PRIMARY KEY, recurso VARCHAR(40) NOT NULL, recurso_id INT NOT NULL, numero INT NOT NULL, dados LONGTEXT NOT NULL, resumo VARCHAR(200) NULL, usuario_id INT NULL, usuario_nome VARCHAR(120) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_rev (recurso, recurso_id, numero)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS lixeira (id INT AUTO_INCREMENT PRIMARY KEY, tabela VARCHAR(60) NOT NULL, registro_id INT NOT NULL, recurso VARCHAR(60) NOT NULL, rotulo VARCHAR(200) NOT NULL, dados LONGTEXT NOT NULL, relacionados LONGTEXT NULL, arquivo VARCHAR(500) NULL, usuario_id INT NULL, usuario_nome VARCHAR(120) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // Workflow
        "ALTER TABLE noticias ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'publicado'",
        "ALTER TABLE paginas ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'publicado'",
        "CREATE TABLE IF NOT EXISTS workflow_eventos (id INT AUTO_INCREMENT PRIMARY KEY, recurso VARCHAR(40) NOT NULL, recurso_id INT NOT NULL, de VARCHAR(20) NULL, para VARCHAR(20) NOT NULL, comentario TEXT NULL, usuario_id INT NULL, usuario_nome VARCHAR(120) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (recurso, recurso_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        // SEO
        "ALTER TABLE noticias ADD COLUMN seo_titulo VARCHAR(70) NULL", "ALTER TABLE noticias ADD COLUMN seo_descricao VARCHAR(200) NULL", "ALTER TABLE noticias ADD COLUMN og_imagem VARCHAR(255) NULL", "ALTER TABLE noticias ADD COLUMN indexar TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE paginas ADD COLUMN seo_titulo VARCHAR(70) NULL", "ALTER TABLE paginas ADD COLUMN seo_descricao VARCHAR(200) NULL", "ALTER TABLE paginas ADD COLUMN og_imagem VARCHAR(255) NULL", "ALTER TABLE paginas ADD COLUMN indexar TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE anuncios ADD COLUMN slug VARCHAR(200) NULL", "ALTER TABLE anuncios ADD COLUMN seo_titulo VARCHAR(70) NULL", "ALTER TABLE anuncios ADD COLUMN seo_descricao VARCHAR(200) NULL", "ALTER TABLE anuncios ADD COLUMN og_imagem VARCHAR(255) NULL", "ALTER TABLE anuncios ADD COLUMN indexar TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE anuncios ADD UNIQUE INDEX uk_anuncio_slug (slug)",
        // Mídia
        "CREATE TABLE IF NOT EXISTS midia_pastas (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, parent_id INT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS midias (id INT AUTO_INCREMENT PRIMARY KEY, pasta_id INT NULL, nome VARCHAR(190) NOT NULL, arquivo VARCHAR(255) NOT NULL, mime VARCHAR(80) NOT NULL, tamanho INT NOT NULL DEFAULT 0, largura INT NULL, altura INT NULL, alt VARCHAR(255) NULL, titulo VARCHAR(190) NULL, usuario_id INT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (pasta_id), INDEX (arquivo)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}

function schema_upgrade_v7(PDO $pdo): void
{
    foreach (schema_v7_statements() as $q) {
        try { $pdo->exec($q); } catch (Throwable $e) { /* já existe */ }
    }
    // Estado editorial a partir do status antigo
    try {
        $pdo->exec("UPDATE noticias SET estado = IF(status=1,'publicado','rascunho') WHERE estado='publicado' AND status=0");
        $pdo->exec("UPDATE paginas SET estado = IF(status=1,'publicado','rascunho') WHERE estado='publicado' AND status=0");
    } catch (Throwable $e) { }
    // Slugs dos anúncios
    foreach (db_rows($pdo, "SELECT id,titulo FROM anuncios WHERE slug IS NULL OR slug=''") as $a) {
        $base = slugify($a['titulo']); $slug = $base; $n = 2;
        while (db_one($pdo, 'SELECT id FROM anuncios WHERE slug=? AND id<>?', [$slug, $a['id']])) $slug = $base . '-' . $n++;
        try { $pdo->prepare('UPDATE anuncios SET slug=? WHERE id=?')->execute([$slug, $a['id']]); } catch (Throwable $e) { }
    }
    rbac_seed($pdo);
    theme_migrate_legacy($pdo);
    // Registra na biblioteca as imagens que já existiam
    foreach (glob(BN_ROOT . '/assets/img/uploads/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) ?: [] as $f) {
        $rel = 'assets/img/uploads/' . basename($f);
        if (db_one($pdo, 'SELECT id FROM midias WHERE arquivo=?', [$rel])) continue;
        $size = @getimagesize($f);
        try {
            $pdo->prepare('INSERT INTO midias (nome,arquivo,mime,tamanho,largura,altura) VALUES (?,?,?,?,?,?)')->execute([basename($f), $rel, $size['mime'] ?? 'image/jpeg', (int)filesize($f), $size[0] ?? null, $size[1] ?? null]);
        } catch (Throwable $e) { }
    }
    $defaults = [
        'tema' => 'classico', 'seg_tentativas_login' => '5', 'seg_bloqueio_minutos' => '15', 'seg_sessao_minutos' => '120',
        'seg_senha_min' => '8', 'seg_senha_forte' => '1', 'lixeira_dias' => '30', 'versoes_max' => '50',
        'backup_auto' => 'semanal', 'backup_manter' => '7', 'backup_auto_midia' => '1', 'seo_titulo_padrao' => '%titulo% | %site%',
        'seo_indexar_site' => '1', 'urls_amigaveis' => '0', 'midia_max_mb' => '8', 'manutencao_ativa' => '0',
        'manutencao_titulo' => 'Estamos em manutenção', 'manutencao_mensagem' => 'Estamos realizando melhorias no site. Voltamos em breve!',
        'plugins_ativos' => '[]', 'backup_ultimo_auto' => date('Y-m-d H:i:s'), 'cron_chave' => bin2hex(random_bytes(12)), 'manutencao_token' => bin2hex(random_bytes(10)),
    ];
    foreach ($defaults as $k => $v) {
        try { $pdo->prepare('INSERT IGNORE INTO configuracoes (chave,valor) VALUES (?,?)')->execute([$k, $v]); } catch (Throwable $e) { }
    }
    setting_cache_clear();
}

function schema_v7_required(): array
{
    return [
        'papeis' => ['slug'], 'papel_permissoes' => ['permissao'], 'usuarios' => ['papel_id'], 'auditoria' => ['acao'],
        'revisoes' => ['numero'], 'lixeira' => ['dados'], 'workflow_eventos' => ['para'], 'midias' => ['arquivo'], 'midia_pastas' => ['nome'],
        'login_tentativas' => ['ip'], 'senha_resets' => ['token_hash'], 'noticias' => ['estado', 'seo_titulo'], 'paginas' => ['estado', 'seo_titulo'], 'anuncios' => ['slug', 'seo_titulo'],
    ];
}

/** v8: nova tipografia (Hanken Grotesk + Poppins). Remove a fonte antiga herdada
    automaticamente da tela "Aparência" (DM Sans), para que o tema Clássico use o novo padrão.
    Escolhas feitas pelo cliente em Temas e layout (outras fontes) são mantidas. */
function schema_upgrade_v8(PDO $pdo): void
{
    if (setting($pdo, 'tipografia_v8', '') === '1') return;
    $vars = json_decode(setting($pdo, 'tema_vars_classico', '{}'), true) ?: [];
    if (($vars['fonte_texto'] ?? '') === 'DM Sans') unset($vars['fonte_texto']);
    if (($vars['fonte_titulos'] ?? '') === 'Plus Jakarta Sans') unset($vars['fonte_titulos']);
    save_setting($pdo, 'tema_vars_classico', json_encode($vars));
    save_setting($pdo, 'tipografia_v8', '1');
}
