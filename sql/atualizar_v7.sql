-- ==========================================================================
-- CMS v7 — atualização para instalação existente (MySQL 5.7/8 e MariaDB)
-- Pode ser executado mais de uma vez. Se preferir, apenas acesse o site/painel:
-- a migração automática aplica as mesmas mudanças (e cria os papéis padrão).
-- ==========================================================================
SET NAMES utf8mb4;
USE banco_negocios;

DROP PROCEDURE IF EXISTS bn_add_col;
DELIMITER $$
CREATE PROCEDURE bn_add_col(IN t VARCHAR(64), IN c VARCHAR(64), IN def VARCHAR(255))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND COLUMN_NAME = c) THEN
    SET @bn_sql = CONCAT("ALTER TABLE `", t, "` ADD COLUMN `", c, "` ", def);
    PREPARE bn_stmt FROM @bn_sql; EXECUTE bn_stmt; DEALLOCATE PREPARE bn_stmt;
  END IF;
END$$
DELIMITER ;

CREATE TABLE IF NOT EXISTS papeis (id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(60) NOT NULL UNIQUE, nome VARCHAR(80) NOT NULL, descricao VARCHAR(255) NULL, sistema TINYINT(1) NOT NULL DEFAULT 0, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS papel_permissoes (papel_id INT NOT NULL, permissao VARCHAR(80) NOT NULL, PRIMARY KEY (papel_id, permissao)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CALL bn_add_col('usuarios','papel_id','INT NULL');
CALL bn_add_col('usuarios','ultimo_login','DATETIME NULL');
CREATE TABLE IF NOT EXISTS login_tentativas (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(180) NOT NULL, ip VARCHAR(45) NOT NULL, sucesso TINYINT(1) NOT NULL DEFAULT 0, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_login (email, ip, criado_em)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS senha_resets (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, token_hash CHAR(64) NOT NULL, expira_em DATETIME NOT NULL, usado TINYINT(1) NOT NULL DEFAULT 0, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (token_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS auditoria (id BIGINT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NULL, usuario_nome VARCHAR(120) NULL, acao VARCHAR(40) NOT NULL, recurso VARCHAR(60) NULL, recurso_id VARCHAR(40) NULL, detalhes TEXT NULL, ip VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_aud_data (criado_em), INDEX idx_aud_acao (acao), INDEX idx_aud_user (usuario_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS revisoes (id BIGINT AUTO_INCREMENT PRIMARY KEY, recurso VARCHAR(40) NOT NULL, recurso_id INT NOT NULL, numero INT NOT NULL, dados LONGTEXT NOT NULL, resumo VARCHAR(200) NULL, usuario_id INT NULL, usuario_nome VARCHAR(120) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_rev (recurso, recurso_id, numero)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS lixeira (id INT AUTO_INCREMENT PRIMARY KEY, tabela VARCHAR(60) NOT NULL, registro_id INT NOT NULL, recurso VARCHAR(60) NOT NULL, rotulo VARCHAR(200) NOT NULL, dados LONGTEXT NOT NULL, relacionados LONGTEXT NULL, arquivo VARCHAR(500) NULL, usuario_id INT NULL, usuario_nome VARCHAR(120) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CALL bn_add_col('noticias','estado','VARCHAR(20) NOT NULL DEFAULT ''publicado''');
CALL bn_add_col('paginas','estado','VARCHAR(20) NOT NULL DEFAULT ''publicado''');
CREATE TABLE IF NOT EXISTS workflow_eventos (id INT AUTO_INCREMENT PRIMARY KEY, recurso VARCHAR(40) NOT NULL, recurso_id INT NOT NULL, de VARCHAR(20) NULL, para VARCHAR(20) NOT NULL, comentario TEXT NULL, usuario_id INT NULL, usuario_nome VARCHAR(120) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (recurso, recurso_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CALL bn_add_col('noticias','seo_titulo','VARCHAR(70) NULL');
CALL bn_add_col('noticias','seo_descricao','VARCHAR(200) NULL');
CALL bn_add_col('noticias','og_imagem','VARCHAR(255) NULL');
CALL bn_add_col('noticias','indexar','TINYINT(1) NOT NULL DEFAULT 1');
CALL bn_add_col('paginas','seo_titulo','VARCHAR(70) NULL');
CALL bn_add_col('paginas','seo_descricao','VARCHAR(200) NULL');
CALL bn_add_col('paginas','og_imagem','VARCHAR(255) NULL');
CALL bn_add_col('paginas','indexar','TINYINT(1) NOT NULL DEFAULT 1');
CALL bn_add_col('anuncios','slug','VARCHAR(200) NULL');
CALL bn_add_col('anuncios','seo_titulo','VARCHAR(70) NULL');
CALL bn_add_col('anuncios','seo_descricao','VARCHAR(200) NULL');
CALL bn_add_col('anuncios','og_imagem','VARCHAR(255) NULL');
CALL bn_add_col('anuncios','indexar','TINYINT(1) NOT NULL DEFAULT 1');
-- índice único do slug dos anúncios é criado pela migração automática (após gerar os slugs)
CREATE TABLE IF NOT EXISTS midia_pastas (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, parent_id INT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS midias (id INT AUTO_INCREMENT PRIMARY KEY, pasta_id INT NULL, nome VARCHAR(190) NOT NULL, arquivo VARCHAR(255) NOT NULL, mime VARCHAR(80) NOT NULL, tamanho INT NOT NULL DEFAULT 0, largura INT NULL, altura INT NULL, alt VARCHAR(255) NULL, titulo VARCHAR(190) NULL, usuario_id INT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (pasta_id), INDEX (arquivo)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE noticias SET estado = IF(status=1,'publicado','rascunho') WHERE estado='publicado' AND status=0;
UPDATE paginas SET estado = IF(status=1,'publicado','rascunho') WHERE estado='publicado' AND status=0;
-- Mantém schema_version em 6 para que o primeiro acesso execute a parte em PHP da v7
-- (papéis padrão, slugs de anúncios, biblioteca de mídia, configurações iniciais).
DROP PROCEDURE IF EXISTS bn_add_col;
