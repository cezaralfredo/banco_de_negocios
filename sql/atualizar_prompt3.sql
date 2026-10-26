USE banco_negocios;

INSERT INTO configuracoes (chave,valor) VALUES
('site_name','Banco de Negócios'),('site_tagline','Conectando oportunidades'),('site_logo',''),
('theme_primary','#9f1549'),('theme_dark','#74152f'),('theme_accent','#ef5d98'),('theme_font','DM Sans'),('theme_layout','wide')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

ALTER TABLE paginas ADD COLUMN IF NOT EXISTS parent_id INT NULL;
ALTER TABLE paginas ADD COLUMN IF NOT EXISTS imagem VARCHAR(255) NULL;
ALTER TABLE paginas ADD COLUMN IF NOT EXISTS formato VARCHAR(20) NOT NULL DEFAULT 'html';

CREATE TABLE IF NOT EXISTS menu_itens (
 id INT AUTO_INCREMENT PRIMARY KEY,
 titulo VARCHAR(120) NOT NULL,
 url VARCHAR(255) NOT NULL,
 parent_id INT NULL,
 ordem INT NOT NULL DEFAULT 0,
 status TINYINT(1) NOT NULL DEFAULT 1,
 criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(parent_id) REFERENCES menu_itens(id) ON DELETE CASCADE
);
INSERT IGNORE INTO menu_itens (id,titulo,url,parent_id,ordem,status) VALUES
(1,'Sobre Nós','#sobre',NULL,10,1),(2,'Serviços','#servicos',NULL,20,1),(3,'Notícias','pagina.php?slug=noticias',NULL,30,1),(4,'Direitos Autorais','pagina.php?slug=direitos-autorais',NULL,40,1);
