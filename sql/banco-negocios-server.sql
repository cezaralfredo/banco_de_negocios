-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           8.4.3 - MySQL Community Server - GPL
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Copiando dados para a tabela banco_negocios.anuncios: ~0 rows (aproximadamente)
INSERT INTO `anuncios` (`id`, `usuario_id`, `categoria_id`, `titulo`, `descricao`, `tipo`, `imagem_destaque`, `logo`, `link`, `whatsapp`, `localizacao`, `avaliacao`, `status`, `data_criacao`) VALUES
	(1, NULL, 6, 'Unifor', 'hhkhklhlkh', 'servico', 'assets/img/uploads/capa_aa48283a49e3a433.jpg', 'assets/img/uploads/logo_4e75b0dd9a05ffd2.png', '', '', 'Fortaleza - CE', 4.9, 'ativo', '2026-09-24 19:04:44');

-- Copiando dados para a tabela banco_negocios.anuncio_destaques: ~0 rows (aproximadamente)
INSERT INTO `anuncio_destaques` (`anuncio_id`, `ordem`, `ativo`) VALUES
	(1, 0, 1);

-- Copiando dados para a tabela banco_negocios.banners: ~0 rows (aproximadamente)

-- Copiando dados para a tabela banco_negocios.banner_beneficios: ~3 rows (aproximadamente)
INSERT INTO `banner_beneficios` (`id`, `icone`, `titulo`, `subtitulo`, `ordem`, `ativo`) VALUES
	(1, 'megafone', 'Visibilidade ao seu negócio', 'e até novos clientes', 1, 1),
	(2, 'escudo', 'Ambiente seguro', 'e confiável', 2, 1),
	(3, 'selo', 'Negócios verificados', 'e avaliados', 3, 1);

-- Copiando dados para a tabela banco_negocios.blog_posts: ~0 rows (aproximadamente)

-- Copiando dados para a tabela banco_negocios.buscas_populares: ~5 rows (aproximadamente)
INSERT INTO `buscas_populares` (`id`, `termo`, `contagem`) VALUES
	(1, 'direito', 100),
	(2, 'design', 92),
	(3, 'serviços', 85),
	(4, 'advocacia', 70),
	(5, 'informática', 63);

-- Copiando dados para a tabela banco_negocios.categorias: ~9 rows (aproximadamente)
INSERT INTO `categorias` (`id`, `nome`, `icone`, `slug`, `destaque`) VALUES
	(1, 'Design', '✎', 'design', 1),
	(2, 'Direito', '⚖', 'direito', 1),
	(3, 'Serviços', '⚙', 'servicos', 1),
	(4, 'Tecnologia', '▣', 'tecnologia', 1),
	(5, 'Marketing', '⚑', 'marketing', 1),
	(6, 'Consultoria', '♙', 'consultoria', 1),
	(7, 'Finanças', '◉', 'financas', 1),
	(8, 'Educação', '▣', 'educacao', 1),
	(9, 'Saúde', '♡', 'saude', 1);

-- Copiando dados para a tabela banco_negocios.configuracoes: ~30 rows (aproximadamente)
INSERT INTO `configuracoes` (`id`, `chave`, `valor`) VALUES
	(1, 'schema_version', '6'),
	(2, 'site_name', 'Banco de Negócios'),
	(3, 'site_tagline', 'Conectando oportunidades'),
	(4, 'site_logo', ''),
	(5, 'site_logo_compacto', ''),
	(6, 'site_favicon', ''),
	(7, 'theme_primary', '#9f1549'),
	(8, 'theme_dark', '#74152f'),
	(9, 'theme_accent', '#ef5d98'),
	(10, 'theme_font', 'DM Sans'),
	(11, 'theme_layout', 'wide'),
	(12, 'nav_theme', 'light'),
	(13, 'topbar_text', 'Bem-vindo ao Banco de Negócios!'),
	(14, 'slider_arrows', '1'),
	(15, 'slider_dots', '1'),
	(16, 'slider_autoplay', '1'),
	(17, 'slider_effect', 'fade'),
	(18, 'footer_show_logo', '1'),
	(19, 'footer_slogan', 'Conectando pessoas, negócios e oportunidades.'),
	(20, 'footer_email', 'contato@bancodenegocios.com.br'),
	(21, 'footer_phone', ''),
	(22, 'footer_address', 'Fortaleza - CE'),
	(23, 'footer_cta_text', 'Cadastre seu negócio →'),
	(24, 'footer_cta_link', 'cadastro.php'),
	(25, 'footer_links', ''),
	(26, 'footer_bottom_text', 'Conteúdo institucional gerenciado pelo administrador'),
	(27, 'footer_copyright', ''),
	(28, 'cookie_text', ''),
	(29, 'cookie_policy_url', 'pagina.php?slug=politica-de-privacidade'),
	(30, 'cookie_version', '1');

-- Copiando dados para a tabela banco_negocios.consentimentos_cookies: ~0 rows (aproximadamente)

-- Copiando dados para a tabela banco_negocios.home_promocao: ~0 rows (aproximadamente)
INSERT INTO `home_promocao` (`id`, `titulo`, `texto`, `beneficios`, `texto_botao`, `imagem`, `link`, `ativo`, `atualizado_em`) VALUES
	(1, 'Amplie suas oportunidades!', 'Cadastre seu negócio e seja encontrado por milhares de pessoas.', 'Cadastre seu negócio em poucos minutos\nApareça para clientes da sua região\nGerencie seus anúncios facilmente\nConecte-se e faça mais negócios', 'Criar Conta Agora', 'assets/img/uploads/home_50410b7a215cf208.jpg', 'cadastro.php', 1, '2026-09-24 19:17:17');

-- Copiando dados para a tabela banco_negocios.idiomas: ~3 rows (aproximadamente)
INSERT INTO `idiomas` (`id`, `codigo`, `nome`, `bandeira`, `padrao`, `ativo`, `ordem`) VALUES
	(1, 'pt', 'Português', '🇧🇷', 1, 1, 1),
	(2, 'en', 'English', '🇺🇸', 0, 1, 2),
	(3, 'es', 'Español', '🇪🇸', 0, 1, 3);

-- Copiando dados para a tabela banco_negocios.menu_itens: ~4 rows (aproximadamente)
INSERT INTO `menu_itens` (`id`, `titulo`, `url`, `parent_id`, `ordem`, `status`, `criado_em`) VALUES
	(1, 'Sobre Nós', 'pagina.php?slug=sobre-nos', NULL, 10, 1, '2026-09-24 18:55:51'),
	(2, 'Serviços', 'index.php#servicos', NULL, 20, 1, '2026-09-24 18:55:51'),
	(3, 'Notícias', 'noticias.php', NULL, 30, 1, '2026-09-24 18:55:51'),
	(4, 'Direitos Autorais', 'pagina.php?slug=direitos-autorais', NULL, 40, 1, '2026-09-24 18:55:51');

-- Copiando dados para a tabela banco_negocios.noticias: ~0 rows (aproximadamente)
INSERT INTO `noticias` (`id`, `titulo`, `slug`, `resumo`, `conteudo`, `imagem`, `autor`, `destaque`, `status`, `publicado_em`, `criado_em`) VALUES
	(1, 'Bem-vindo ao novo Banco de Negócios', 'bem-vindo-ao-novo-banco-de-negocios', 'Conheça as novidades da plataforma: busca aprimorada, acessibilidade e suporte a vários idiomas.', '<p>A plataforma recebeu melhorias na busca, novos recursos de acessibilidade e suporte a vários idiomas.</p><p>Cadastre seu negócio e seja encontrado por milhares de pessoas.</p>', NULL, 'Equipe Banco de Negócios', 1, 1, '2026-09-24 15:55:51', '2026-09-24 18:55:51');

-- Copiando dados para a tabela banco_negocios.paginas: ~4 rows (aproximadamente)
INSERT INTO `paginas` (`id`, `titulo`, `slug`, `conteudo`, `imagem`, `parent_id`, `status`, `ordem`, `criado_em`) VALUES
	(1, 'Sobre Nós', 'sobre-nos', '<p>O Banco de Negócios conecta empreendedores, vendedores e prestadores de serviços a potenciais clientes em um só lugar.</p>', NULL, NULL, 1, 0, '2026-09-24 18:55:51'),
	(2, 'Termos de Uso', 'termos-de-uso', '<p>Ao criar sua conta, você concorda em utilizar a plataforma de forma responsável e respeitosa.</p>', NULL, NULL, 1, 1, '2026-09-24 18:55:51'),
	(3, 'Política de Privacidade', 'politica-de-privacidade', '<p>Respeitamos sua privacidade e tratamos seus dados conforme a Lei Geral de Proteção de Dados (Lei nº 13.709/2018).</p><h2>Cookies</h2><p>Utilizamos cookies necessários ao funcionamento do site e, somente com o seu consentimento, cookies de preferências, estatística e marketing. Você pode alterar sua escolha a qualquer momento pelo link “Preferências de cookies” no rodapé.</p>', NULL, NULL, 1, 2, '2026-09-24 18:55:51'),
	(4, 'Direitos Autorais', 'direitos-autorais', '<p>Todo conteúdo publicado deve respeitar os direitos autorais de seus respectivos titulares.</p>', NULL, NULL, 1, 3, '2026-09-24 18:55:51');

-- Copiando dados para a tabela banco_negocios.redes_sociais: ~4 rows (aproximadamente)
INSERT INTO `redes_sociais` (`id`, `rede`, `nome`, `url`, `ordem`, `ativo`) VALUES
	(1, 'facebook', 'Facebook', 'https://facebook.com', 1, 1),
	(2, 'instagram', 'Instagram', 'https://instagram.com', 2, 1),
	(3, 'linkedin', 'LinkedIn', 'https://linkedin.com', 3, 1),
	(4, 'youtube', 'YouTube', 'https://youtube.com', 4, 1);

-- Copiando dados para a tabela banco_negocios.traducoes: ~84 rows (aproximadamente)
INSERT INTO `traducoes` (`id`, `chave`, `idioma`, `valor`, `atualizado_em`) VALUES
	(1, 'nav.home', 'en', 'Home', '2026-09-24 18:55:51'),
	(2, 'nav.home', 'es', 'Inicio', '2026-09-24 18:55:51'),
	(3, 'top.create_account', 'en', 'Sign up', '2026-09-24 18:55:51'),
	(4, 'top.create_account', 'es', 'Crear cuenta', '2026-09-24 18:55:51'),
	(5, 'top.login', 'en', 'Log in', '2026-09-24 18:55:51'),
	(6, 'top.login', 'es', 'Entrar', '2026-09-24 18:55:51'),
	(7, 'top.panel', 'en', 'Dashboard', '2026-09-24 18:55:51'),
	(8, 'top.panel', 'es', 'Acceder al panel', '2026-09-24 18:55:51'),
	(9, 'top.search_placeholder', 'en', 'Search', '2026-09-24 18:55:51'),
	(10, 'top.search_placeholder', 'es', 'Buscar', '2026-09-24 18:55:51'),
	(11, 'hero.eyebrow', 'en', 'A new way of doing business', '2026-09-24 18:55:51'),
	(12, 'hero.eyebrow', 'es', 'Una nueva forma de hacer negocios', '2026-09-24 18:55:51'),
	(13, 'hero.explore', 'en', 'Explore listings', '2026-09-24 18:55:51'),
	(14, 'hero.explore', 'es', 'Explorar anuncios', '2026-09-24 18:55:51'),
	(15, 'search.title', 'en', 'Find what you need', '2026-09-24 18:55:51'),
	(16, 'search.title', 'es', 'Encuentra lo que necesitas', '2026-09-24 18:55:51'),
	(17, 'search.subtitle', 'en', 'Search by category, city, type of service or product. Use the A-Z filter to browse easily.', '2026-09-24 18:55:51'),
	(18, 'search.subtitle', 'es', 'Busca por categoría, ciudad, tipo de servicio o producto. Usa el filtro A-Z para navegar fácilmente.', '2026-09-24 18:55:51'),
	(19, 'search.placeholder', 'en', 'Type what you are looking for...', '2026-09-24 18:55:51'),
	(20, 'search.placeholder', 'es', 'Escribe lo que buscas...', '2026-09-24 18:55:51'),
	(21, 'search.category', 'en', 'Category', '2026-09-24 18:55:51'),
	(22, 'search.category', 'es', 'Categoría', '2026-09-24 18:55:51'),
	(23, 'search.location', 'en', 'Location', '2026-09-24 18:55:51'),
	(24, 'search.location', 'es', 'Ubicación', '2026-09-24 18:55:51'),
	(25, 'search.type', 'en', 'Type', '2026-09-24 18:55:51'),
	(26, 'search.type', 'es', 'Tipo', '2026-09-24 18:55:51'),
	(27, 'search.all', 'en', 'All', '2026-09-24 18:55:51'),
	(28, 'search.all', 'es', 'Todas', '2026-09-24 18:55:51'),
	(29, 'search.all_types', 'en', 'All', '2026-09-24 18:55:51'),
	(30, 'search.all_types', 'es', 'Todos', '2026-09-24 18:55:51'),
	(31, 'search.type_product', 'en', 'Product', '2026-09-24 18:55:51'),
	(32, 'search.type_product', 'es', 'Producto', '2026-09-24 18:55:51'),
	(33, 'search.type_service', 'en', 'Service', '2026-09-24 18:55:51'),
	(34, 'search.type_service', 'es', 'Servicio', '2026-09-24 18:55:51'),
	(35, 'search.button', 'en', 'Search', '2026-09-24 18:55:51'),
	(36, 'search.button', 'es', 'Buscar', '2026-09-24 18:55:51'),
	(37, 'search.popular', 'en', 'Most searched:', '2026-09-24 18:55:51'),
	(38, 'search.popular', 'es', 'Más buscados:', '2026-09-24 18:55:51'),
	(39, 'search.sort', 'en', 'Sort by:', '2026-09-24 18:55:51'),
	(40, 'search.sort', 'es', 'Ordenar por:', '2026-09-24 18:55:51'),
	(41, 'search.all_letters', 'en', 'All', '2026-09-24 18:55:51'),
	(42, 'search.all_letters', 'es', 'Todas', '2026-09-24 18:55:51'),
	(43, 'home.categories_title', 'en', 'Featured categories', '2026-09-24 18:55:51'),
	(44, 'home.categories_title', 'es', 'Categorías destacadas', '2026-09-24 18:55:51'),
	(45, 'home.ads_title', 'en', 'Featured businesses', '2026-09-24 18:55:51'),
	(46, 'home.ads_title', 'es', 'Negocios destacados', '2026-09-24 18:55:51'),
	(47, 'card.profile', 'en', 'View profile', '2026-09-24 18:55:51'),
	(48, 'card.profile', 'es', 'Ver perfil', '2026-09-24 18:55:51'),
	(49, 'card.contact', 'en', 'Contact', '2026-09-24 18:55:51'),
	(50, 'card.contact', 'es', 'Contactar', '2026-09-24 18:55:51'),
	(51, 'news.title', 'en', 'News', '2026-09-24 18:55:51'),
	(52, 'news.title', 'es', 'Noticias', '2026-09-24 18:55:51'),
	(53, 'a11y.title', 'en', 'Accessibility', '2026-09-24 18:55:51'),
	(54, 'a11y.title', 'es', 'Accesibilidad', '2026-09-24 18:55:51'),
	(55, 'a11y.font_up', 'en', 'Larger text', '2026-09-24 18:55:51'),
	(56, 'a11y.font_up', 'es', 'Letra más grande', '2026-09-24 18:55:51'),
	(57, 'a11y.font_down', 'en', 'Smaller text', '2026-09-24 18:55:51'),
	(58, 'a11y.font_down', 'es', 'Letra más pequeña', '2026-09-24 18:55:51'),
	(59, 'a11y.contrast', 'en', 'Dark contrast', '2026-09-24 18:55:51'),
	(60, 'a11y.contrast', 'es', 'Contraste oscuro', '2026-09-24 18:55:51'),
	(61, 'a11y.links', 'en', 'Highlight links', '2026-09-24 18:55:51'),
	(62, 'a11y.links', 'es', 'Resaltar enlaces', '2026-09-24 18:55:51'),
	(63, 'a11y.reader', 'en', 'Text reader', '2026-09-24 18:55:51'),
	(64, 'a11y.reader', 'es', 'Lector de texto', '2026-09-24 18:55:51'),
	(65, 'a11y.dyslexia', 'en', 'Dyslexia-friendly font', '2026-09-24 18:55:51'),
	(66, 'a11y.dyslexia', 'es', 'Fuente para dislexia', '2026-09-24 18:55:51'),
	(67, 'a11y.reset', 'en', 'Reset', '2026-09-24 18:55:51'),
	(68, 'a11y.reset', 'es', 'Restablecer', '2026-09-24 18:55:51'),
	(69, 'cookie.title', 'en', 'Your privacy matters', '2026-09-24 18:55:51'),
	(70, 'cookie.title', 'es', 'Tu privacidad es importante', '2026-09-24 18:55:51'),
	(71, 'cookie.accept', 'en', 'Accept all', '2026-09-24 18:55:51'),
	(72, 'cookie.accept', 'es', 'Aceptar todas', '2026-09-24 18:55:51'),
	(73, 'cookie.reject', 'en', 'Reject non-essential', '2026-09-24 18:55:51'),
	(74, 'cookie.reject', 'es', 'Rechazar no esenciales', '2026-09-24 18:55:51'),
	(75, 'cookie.customize', 'en', 'Customize', '2026-09-24 18:55:51'),
	(76, 'cookie.customize', 'es', 'Personalizar', '2026-09-24 18:55:51'),
	(77, 'footer.links', 'en', 'Useful links', '2026-09-24 18:55:51'),
	(78, 'footer.links', 'es', 'Enlaces institucionales', '2026-09-24 18:55:51'),
	(79, 'footer.social', 'en', 'Social media', '2026-09-24 18:55:51'),
	(80, 'footer.social', 'es', 'Redes sociales', '2026-09-24 18:55:51'),
	(81, 'footer.contact', 'en', 'Contact us', '2026-09-24 18:55:51'),
	(82, 'footer.contact', 'es', 'Contáctanos', '2026-09-24 18:55:51'),
	(83, 'footer.rights', 'en', 'All rights reserved.', '2026-09-24 18:55:51'),
	(84, 'footer.rights', 'es', 'Todos los derechos reservados.', '2026-09-24 18:55:51');

-- Copiando dados para a tabela banco_negocios.usuarios: ~0 rows (aproximadamente)
INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `nivel_acesso`, `status`, `criado_em`) VALUES
	(1, 'Administrador', 'admin@bancodenegocios.com.br', '$2y$12$ht/AiquN9Nbfdajt7adnkurTydlWlk4I9ujc4rbUnNv98otXTWt2G', 'admin', 'ativo', '2026-09-24 18:55:51');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
