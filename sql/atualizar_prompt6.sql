-- ==========================================================================
-- Banco de Negócios — atualização do prompt6 (instalação existente)
-- Compatível com MySQL 5.7/8 e MariaDB (XAMPP/WAMP/Laragon). Pode ser executado
-- mais de uma vez. Se preferir, basta abrir o site: a migração automática
-- (includes/functions.php) cria as mesmas tabelas/colunas na primeira visita.
-- ==========================================================================
SET NAMES utf8mb4;
USE banco_negocios;

-- Procedimento auxiliar: adiciona a coluna somente se ela ainda não existir
DROP PROCEDURE IF EXISTS bn_add_col;
DELIMITER $$
CREATE PROCEDURE bn_add_col(IN t VARCHAR(64), IN c VARCHAR(64), IN def VARCHAR(255))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = t AND COLUMN_NAME = c) THEN
    SET @bn_sql = CONCAT('ALTER TABLE `', t, '` ADD COLUMN `', c, '` ', def);
    PREPARE bn_stmt FROM @bn_sql; EXECUTE bn_stmt; DEALLOCATE PREPARE bn_stmt;
  END IF;
END$$
DELIMITER ;

-- Colunas que faltavam em instalações antigas
CALL bn_add_col('paginas','parent_id','INT NULL');
CALL bn_add_col('paginas','imagem','VARCHAR(255) NULL');
CALL bn_add_col('categorias','destaque','TINYINT(1) NOT NULL DEFAULT 0');
CALL bn_add_col('anuncios','link','VARCHAR(255) NULL');
CALL bn_add_col('anuncios','logo','VARCHAR(255) NULL');
CALL bn_add_col('banners','duracao_segundos','INT NOT NULL DEFAULT 5');
CALL bn_add_col('banners','efeito','VARCHAR(30) NOT NULL DEFAULT ''fade''');
CALL bn_add_col('banners','ordem','INT NOT NULL DEFAULT 0');
CREATE TABLE IF NOT EXISTS anuncio_destaques (anuncio_id INT PRIMARY KEY, ordem INT NOT NULL DEFAULT 0, ativo TINYINT(1) NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS blog_posts (id INT AUTO_INCREMENT PRIMARY KEY, anuncio_id INT NOT NULL, titulo VARCHAR(180) NOT NULL, conteudo TEXT NOT NULL, status TINYINT(1) NOT NULL DEFAULT 1, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

-- 6) Busca: tipo do anúncio (produto/serviço)
CALL bn_add_col('anuncios','tipo','VARCHAR(20) NOT NULL DEFAULT ''servico''');

CREATE TABLE IF NOT EXISTS home_promocao (id TINYINT PRIMARY KEY, titulo VARCHAR(180) NOT NULL DEFAULT 'Amplie suas oportunidades!', texto TEXT, imagem VARCHAR(255) NULL, link VARCHAR(255) DEFAULT 'cadastro.php', ativo TINYINT(1) NOT NULL DEFAULT 1, atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS menu_itens (id INT AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(120) NOT NULL, url VARCHAR(255) NOT NULL, parent_id INT NULL, ordem INT NOT NULL DEFAULT 0, status TINYINT(1) NOT NULL DEFAULT 1, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

-- 9.3) Área promocional "Amplie suas oportunidades!"
CALL bn_add_col('home_promocao','beneficios','TEXT NULL');
CALL bn_add_col('home_promocao','texto_botao','VARCHAR(80) NOT NULL DEFAULT ''Criar Conta Agora''');
INSERT IGNORE INTO home_promocao (id,titulo,texto,imagem,link,ativo) VALUES (1,'Amplie suas oportunidades!','Cadastre seu negócio e seja encontrado por milhares de pessoas.','','cadastro.php',1);
-- Copia os textos antigos do CTA (que ficavam em configuracoes) para a promoção
UPDATE home_promocao p JOIN configuracoes c ON c.chave='cta_benefits' SET p.beneficios=c.valor WHERE p.id=1 AND (p.beneficios IS NULL OR p.beneficios='');
UPDATE home_promocao SET beneficios='Cadastre seu negócio em poucos minutos\nApareça para clientes da sua região\nGerencie seus anúncios facilmente\nConecte-se e faça mais negócios' WHERE id=1 AND (beneficios IS NULL OR beneficios='');

CREATE TABLE IF NOT EXISTS banner_beneficios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  icone VARCHAR(40) NOT NULL DEFAULT 'megafone',
  titulo VARCHAR(120) NOT NULL,
  subtitulo VARCHAR(160) NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS idiomas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(10) NOT NULL UNIQUE,
  nome VARCHAR(60) NOT NULL,
  bandeira VARCHAR(16) NOT NULL DEFAULT '',
  padrao TINYINT(1) NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  ordem INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS traducoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(190) NOT NULL,
  idioma VARCHAR(10) NOT NULL,
  valor TEXT NOT NULL,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_traducao (chave, idioma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS noticias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  resumo VARCHAR(400) NULL,
  conteudo LONGTEXT NOT NULL,
  imagem VARCHAR(255) NULL,
  autor VARCHAR(120) NULL,
  destaque TINYINT(1) NOT NULL DEFAULT 0,
  status TINYINT(1) NOT NULL DEFAULT 1,
  publicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS redes_sociais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rede VARCHAR(30) NOT NULL,
  nome VARCHAR(60) NOT NULL,
  url VARCHAR(255) NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS consentimentos_cookies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  consent_id VARCHAR(40) NOT NULL,
  escolha VARCHAR(20) NOT NULL,
  categorias VARCHAR(120) NOT NULL,
  versao VARCHAR(10) NOT NULL,
  ip_hash CHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO idiomas (codigo,nome,bandeira,padrao,ativo,ordem) VALUES
('pt','Português','🇧🇷',1,1,1),('en','English','🇺🇸',0,1,2),('es','Español','🇪🇸',0,1,3);

INSERT INTO banner_beneficios (icone,titulo,subtitulo,ordem,ativo)
SELECT * FROM (SELECT 'megafone' a,'Visibilidade ao seu negócio' b,'e até novos clientes' c,1 d,1 e
  UNION ALL SELECT 'escudo','Ambiente seguro','e confiável',2,1
  UNION ALL SELECT 'selo','Negócios verificados','e avaliados',3,1) x
WHERE NOT EXISTS (SELECT 1 FROM banner_beneficios);

INSERT INTO redes_sociais (rede,nome,url,ordem,ativo)
SELECT * FROM (SELECT 'facebook' a,'Facebook' b,'https://facebook.com' c,1 d,1 e
  UNION ALL SELECT 'instagram','Instagram','https://instagram.com',2,1
  UNION ALL SELECT 'linkedin','LinkedIn','https://linkedin.com',3,1
  UNION ALL SELECT 'youtube','YouTube','https://youtube.com',4,1) x
WHERE NOT EXISTS (SELECT 1 FROM redes_sociais);

-- 3) Menu "Notícias" e página "Sobre Nós" no padrão das páginas internas
INSERT IGNORE INTO paginas (titulo,slug,conteudo,ordem,status) VALUES ('Sobre Nós','sobre-nos','<p>O Banco de Negócios conecta empreendedores, vendedores e prestadores de serviços a potenciais clientes em um só lugar.</p>',0,1);
UPDATE menu_itens SET url='noticias.php' WHERE url IN ('pagina.php?slug=noticias','#noticias');
UPDATE menu_itens SET url='pagina.php?slug=sobre-nos' WHERE url='#sobre';
UPDATE menu_itens SET url='index.php#servicos' WHERE url='#servicos';
INSERT INTO menu_itens (titulo,url,parent_id,ordem,status) SELECT 'Notícias','noticias.php',NULL,30,1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM menu_itens WHERE url='noticias.php');

INSERT INTO noticias (titulo,slug,resumo,conteudo,autor,destaque,status)
SELECT 'Bem-vindo ao novo Banco de Negócios','bem-vindo-ao-novo-banco-de-negocios','Conheça as novidades da plataforma: busca aprimorada, acessibilidade e suporte a vários idiomas.','<p>A plataforma recebeu melhorias na busca, novos recursos de acessibilidade e suporte a vários idiomas.</p><p>Cadastre seu negócio e seja encontrado por milhares de pessoas.</p>','Equipe Banco de Negócios',1,1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM noticias);

-- Configurações novas (slider, footer, cookies)
INSERT IGNORE INTO configuracoes (chave,valor) VALUES
('slider_arrows','1'),('slider_dots','1'),('slider_autoplay','1'),('slider_effect','fade'),
('footer_show_logo','1'),('footer_phone',''),('footer_cta_text','Cadastre seu negócio →'),('footer_cta_link','cadastro.php'),('footer_links',''),
('footer_bottom_text','Conteúdo institucional gerenciado pelo administrador'),('footer_copyright',''),
('cookie_text',''),('cookie_policy_url','pagina.php?slug=politica-de-privacidade'),('cookie_version','1');

-- Corrige o hash inválido do administrador de demonstração (senha: password)
UPDATE usuarios SET senha='$2y$12$ht/AiquN9Nbfdajt7adnkurTydlWlk4I9ujc4rbUnNv98otXTWt2G'
WHERE email='admin@bancodenegocios.com.br' AND senha='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCq6qM9e2JfT3J6l9M3K';

-- Traduções iniciais (exemplos). As demais podem ser cadastradas em Admin › Idiomas e traduções.
INSERT IGNORE INTO traducoes (chave,idioma,valor) VALUES
('nav.home','en','Home'),('nav.home','es','Inicio'),
('top.create_account','en','Sign up'),('top.create_account','es','Crear cuenta'),
('top.login','en','Log in'),('top.login','es','Entrar'),
('top.panel','en','Dashboard'),('top.panel','es','Acceder al panel'),
('top.search_placeholder','en','Search'),('top.search_placeholder','es','Buscar'),
('hero.eyebrow','en','A new way of doing business'),('hero.eyebrow','es','Una nueva forma de hacer negocios'),
('hero.explore','en','Explore listings'),('hero.explore','es','Explorar anuncios'),
('search.title','en','Find what you need'),('search.title','es','Encuentra lo que necesitas'),
('search.subtitle','en','Search by category, city, type of service or product. Use the A-Z filter to browse easily.'),('search.subtitle','es','Busca por categoría, ciudad, tipo de servicio o producto. Usa el filtro A-Z para navegar fácilmente.'),
('search.placeholder','en','Type what you are looking for...'),('search.placeholder','es','Escribe lo que buscas...'),
('search.category','en','Category'),('search.category','es','Categoría'),
('search.location','en','Location'),('search.location','es','Ubicación'),
('search.type','en','Type'),('search.type','es','Tipo'),
('search.all','en','All'),('search.all','es','Todas'),
('search.all_types','en','All'),('search.all_types','es','Todos'),
('search.type_product','en','Product'),('search.type_product','es','Producto'),
('search.type_service','en','Service'),('search.type_service','es','Servicio'),
('search.button','en','Search'),('search.button','es','Buscar'),
('search.popular','en','Most searched:'),('search.popular','es','Más buscados:'),
('search.sort','en','Sort by:'),('search.sort','es','Ordenar por:'),
('search.all_letters','en','All'),('search.all_letters','es','Todas'),
('home.categories_title','en','Featured categories'),('home.categories_title','es','Categorías destacadas'),
('home.ads_title','en','Featured businesses'),('home.ads_title','es','Negocios destacados'),
('card.profile','en','View profile'),('card.profile','es','Ver perfil'),
('card.contact','en','Contact'),('card.contact','es','Contactar'),
('news.title','en','News'),('news.title','es','Noticias'),
('a11y.title','en','Accessibility'),('a11y.title','es','Accesibilidad'),
('a11y.font_up','en','Larger text'),('a11y.font_up','es','Letra más grande'),
('a11y.font_down','en','Smaller text'),('a11y.font_down','es','Letra más pequeña'),
('a11y.contrast','en','Dark contrast'),('a11y.contrast','es','Contraste oscuro'),
('a11y.links','en','Highlight links'),('a11y.links','es','Resaltar enlaces'),
('a11y.reader','en','Text reader'),('a11y.reader','es','Lector de texto'),
('a11y.dyslexia','en','Dyslexia-friendly font'),('a11y.dyslexia','es','Fuente para dislexia'),
('a11y.reset','en','Reset'),('a11y.reset','es','Restablecer'),
('cookie.title','en','Your privacy matters'),('cookie.title','es','Tu privacidad es importante'),
('cookie.accept','en','Accept all'),('cookie.accept','es','Aceptar todas'),
('cookie.reject','en','Reject non-essential'),('cookie.reject','es','Rechazar no esenciales'),
('cookie.customize','en','Customize'),('cookie.customize','es','Personalizar'),
('footer.links','en','Useful links'),('footer.links','es','Enlaces institucionales'),
('footer.social','en','Social media'),('footer.social','es','Redes sociales'),
('footer.contact','en','Contact us'),('footer.contact','es','Contáctanos'),
('footer.rights','en','All rights reserved.'),('footer.rights','es','Todos los derechos reservados.');

INSERT INTO configuracoes (chave,valor) VALUES ('schema_version','6') ON DUPLICATE KEY UPDATE valor='6';

DROP PROCEDURE IF EXISTS bn_add_col;
