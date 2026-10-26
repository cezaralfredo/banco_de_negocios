-- ==========================================================================
-- Correção: use SOMENTE se você importou sql/db_banco_negocios.sql em um banco
-- que já existia (erro "Duplicate entry ... for key 'noticias.slug'").
--
-- Remove as linhas que ficaram repetidas (mantém a mais antiga de cada uma).
-- Pode ser executado mais de uma vez.
-- ==========================================================================
SET NAMES utf8mb4;
USE banco_negocios;

-- Textos do banner repetidos (mesmo título)
DELETE b1 FROM banner_beneficios b1
JOIN banner_beneficios b2 ON b1.titulo = b2.titulo AND b1.id > b2.id;

-- Redes sociais repetidas (mesma rede e mesmo endereço)
DELETE r1 FROM redes_sociais r1
JOIN redes_sociais r2 ON r1.rede = r2.rede AND r1.url = r2.url AND r1.id > r2.id;

-- Termos "mais buscados" repetidos
DELETE p1 FROM buscas_populares p1
JOIN buscas_populares p2 ON p1.termo = p2.termo AND p1.id > p2.id;

-- Força a revisão do schema no próximo acesso (recria papéis e configurações que faltarem)
UPDATE configuracoes SET valor = '6' WHERE chave = 'schema_version';
DELETE FROM configuracoes WHERE chave = 'schema_verificado';
