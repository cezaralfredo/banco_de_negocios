-- ==========================================================================
-- Banco de Negócios — schema completo (instalação nova)
-- Inclui todas as atualizações até o prompt6.
-- Para uma instalação existente, use sql/atualizar_prompt6.sql.
-- ==========================================================================
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS banco_negocios CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE banco_negocios;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(180) UNIQUE NOT NULL,
  senha VARCHAR(255) NOT NULL,
  nivel_acesso ENUM('admin','user') DEFAULT 'user',
  status ENUM('ativo','inativo') DEFAULT 'ativo',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  icone VARCHAR(120) DEFAULT '✦',
  slug VARCHAR(140) UNIQUE NOT NULL,
  destaque TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS anuncios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  categoria_id INT NULL,
  titulo VARCHAR(180) NOT NULL,
  descricao TEXT NOT NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT 'servico',
  imagem_destaque VARCHAR(255),
  logo VARCHAR(255) NULL,
  link VARCHAR(255) NULL,
  whatsapp VARCHAR(30),
  localizacao VARCHAR(120),
  avaliacao DECIMAL(2,1) DEFAULT 0,
  status ENUM('ativo','inativo') DEFAULT 'ativo',
  data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY(categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS anuncio_destaques (
  anuncio_id INT PRIMARY KEY,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  anuncio_id INT NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  conteudo TEXT NOT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(180) NOT NULL,
  subtitulo TEXT,
  imagem_fundo VARCHAR(255),
  texto_botao VARCHAR(80),
  link_botao VARCHAR(255),
  status TINYINT(1) DEFAULT 1,
  duracao_segundos INT NOT NULL DEFAULT 5,
  efeito VARCHAR(30) NOT NULL DEFAULT 'padrao',
  ordem INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banner_beneficios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  icone VARCHAR(40) NOT NULL DEFAULT 'megafone',
  titulo VARCHAR(120) NOT NULL,
  subtitulo VARCHAR(160) NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS buscas_populares (
  id INT AUTO_INCREMENT PRIMARY KEY,
  termo VARCHAR(100) NOT NULL,
  contagem INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(100) UNIQUE NOT NULL,
  valor TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS paginas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(180) NOT NULL,
  slug VARCHAR(180) UNIQUE NOT NULL,
  conteudo LONGTEXT NOT NULL,
  imagem VARCHAR(255) NULL,
  parent_id INT NULL,
  status TINYINT(1) DEFAULT 1,
  ordem INT DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(120) NOT NULL,
  url VARCHAR(255) NOT NULL,
  parent_id INT NULL,
  ordem INT NOT NULL DEFAULT 0,
  status TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(parent_id) REFERENCES menu_itens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS home_promocao (
  id TINYINT PRIMARY KEY,
  titulo VARCHAR(180) NOT NULL DEFAULT 'Amplie suas oportunidades!',
  texto TEXT,
  beneficios TEXT NULL,
  texto_botao VARCHAR(80) NOT NULL DEFAULT 'Criar Conta Agora',
  imagem VARCHAR(255) NULL,
  link VARCHAR(255) DEFAULT 'cadastro.php',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

-- --------------------------------------------------------------------------
-- Dados iniciais
-- --------------------------------------------------------------------------
-- Administrador de demonstração: admin@bancodenegocios.com.br / password  (TROQUE EM PRODUÇÃO)
INSERT IGNORE INTO usuarios (nome,email,senha,nivel_acesso,status) VALUES
('Administrador','admin@bancodenegocios.com.br','$2y$12$ht/AiquN9Nbfdajt7adnkurTydlWlk4I9ujc4rbUnNv98otXTWt2G','admin','ativo');

INSERT IGNORE INTO categorias (nome,icone,slug,destaque) VALUES
('Design','✎','design',1),('Direito','⚖','direito',1),('Serviços','⚙','servicos',1),('Tecnologia','▣','tecnologia',1),
('Marketing','⚑','marketing',1),('Consultoria','♙','consultoria',1),('Finanças','◉','financas',1),('Educação','▣','educacao',1),('Saúde','♡','saude',1);

INSERT INTO buscas_populares (termo,contagem) VALUES ('direito',100),('design',92),('serviços',85),('advocacia',70),('informática',63);

INSERT INTO configuracoes (chave,valor) VALUES
('schema_version','6'),
('site_name','Banco de Negócios'),('site_tagline','Conectando oportunidades'),('site_logo',''),('site_logo_compacto',''),('site_favicon',''),
('theme_primary','#9f1549'),('theme_dark','#74152f'),('theme_accent','#ef5d98'),('theme_font','DM Sans'),('theme_layout','wide'),('nav_theme','light'),
('topbar_text','Bem-vindo ao Banco de Negócios!'),
('slider_arrows','1'),('slider_dots','1'),('slider_autoplay','1'),('slider_effect','fade'),
('footer_show_logo','1'),('footer_slogan','Conectando pessoas, negócios e oportunidades.'),
('footer_email','contato@bancodenegocios.com.br'),('footer_phone',''),('footer_address','Fortaleza - CE'),
('footer_cta_text','Cadastre seu negócio →'),('footer_cta_link','cadastro.php'),('footer_links',''),
('footer_bottom_text','Conteúdo institucional gerenciado pelo administrador'),('footer_copyright',''),
('cookie_text',''),('cookie_policy_url','pagina.php?slug=politica-de-privacidade'),('cookie_version','1')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

INSERT IGNORE INTO paginas (titulo,slug,conteudo,ordem,status) VALUES
('Sobre Nós','sobre-nos','<p>O Banco de Negócios conecta empreendedores, vendedores e prestadores de serviços a potenciais clientes em um só lugar.</p>',0,1),
('Termos de Uso','termos-de-uso','<p>Ao criar sua conta, você concorda em utilizar a plataforma de forma responsável e respeitosa.</p>',1,1),
('Política de Privacidade','politica-de-privacidade','<p>Respeitamos sua privacidade e tratamos seus dados conforme a Lei Geral de Proteção de Dados (Lei nº 13.709/2018).</p><h2>Cookies</h2><p>Utilizamos cookies necessários ao funcionamento do site e, somente com o seu consentimento, cookies de preferências, estatística e marketing. Você pode alterar sua escolha a qualquer momento pelo link “Preferências de cookies” no rodapé.</p>',2,1),
('Direitos Autorais','direitos-autorais','<p>Todo conteúdo publicado deve respeitar os direitos autorais de seus respectivos titulares.</p>',3,1);

INSERT IGNORE INTO menu_itens (id,titulo,url,parent_id,ordem,status) VALUES
(1,'Sobre Nós','pagina.php?slug=sobre-nos',NULL,10,1),(2,'Serviços','index.php#servicos',NULL,20,1),
(3,'Notícias','noticias.php',NULL,30,1),(4,'Direitos Autorais','pagina.php?slug=direitos-autorais',NULL,40,1);

INSERT IGNORE INTO home_promocao (id,titulo,texto,beneficios,texto_botao,imagem,link,ativo) VALUES
(1,'Amplie suas oportunidades!','Cadastre seu negócio e seja encontrado por milhares de pessoas.',
 'Cadastre seu negócio em poucos minutos\nApareça para clientes da sua região\nGerencie seus anúncios facilmente\nConecte-se e faça mais negócios',
 'Criar Conta Agora','','cadastro.php',1);

INSERT IGNORE INTO idiomas (codigo,nome,bandeira,padrao,ativo,ordem) VALUES
('pt','Português','🇧🇷',1,1,1),('en','English','🇺🇸',0,1,2),('es','Español','🇪🇸',0,1,3);

INSERT INTO banner_beneficios (icone,titulo,subtitulo,ordem,ativo) VALUES
('megafone','Visibilidade ao seu negócio','e até novos clientes',1,1),
('escudo','Ambiente seguro','e confiável',2,1),
('selo','Negócios verificados','e avaliados',3,1);

INSERT INTO redes_sociais (rede,nome,url,ordem,ativo) VALUES
('facebook','Facebook','https://facebook.com',1,1),('instagram','Instagram','https://instagram.com',2,1),
('linkedin','LinkedIn','https://linkedin.com',3,1),('youtube','YouTube','https://youtube.com',4,1);

INSERT INTO noticias (titulo,slug,resumo,conteudo,autor,destaque,status) VALUES
('Bem-vindo ao novo Banco de Negócios','bem-vindo-ao-novo-banco-de-negocios',
 'Conheça as novidades da plataforma: busca aprimorada, acessibilidade e suporte a vários idiomas.',
 '<p>A plataforma recebeu melhorias na busca, novos recursos de acessibilidade e suporte a vários idiomas.</p><p>Cadastre seu negócio e seja encontrado por milhares de pessoas.</p>',
 'Equipe Banco de Negócios',1,1);

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
