USE banco_negocios;

ALTER TABLE anuncios ADD COLUMN IF NOT EXISTS logo VARCHAR(255) NULL;

INSERT INTO configuracoes (chave,valor) VALUES
('site_favicon',''),('site_logo_compacto',''),('nav_theme','light')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

CREATE TABLE IF NOT EXISTS home_promocao (
 id TINYINT PRIMARY KEY,
 titulo VARCHAR(180) NOT NULL DEFAULT 'Amplie suas oportunidades!',
 texto TEXT,
 imagem VARCHAR(255) NULL,
 link VARCHAR(255) DEFAULT 'cadastro.php',
 ativo TINYINT(1) NOT NULL DEFAULT 1,
 atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT IGNORE INTO home_promocao (id,titulo,texto,imagem,link,ativo) VALUES (1,'Amplie suas oportunidades!','Cadastre seu negócio e seja encontrado por milhares de pessoas.','', 'cadastro.php',1);
