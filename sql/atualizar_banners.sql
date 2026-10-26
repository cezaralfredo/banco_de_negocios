USE banco_negocios;

ALTER TABLE banners ADD COLUMN IF NOT EXISTS duracao_segundos INT NOT NULL DEFAULT 5;
ALTER TABLE banners ADD COLUMN IF NOT EXISTS efeito VARCHAR(30) NOT NULL DEFAULT 'fade';
ALTER TABLE banners ADD COLUMN IF NOT EXISTS ordem INT NOT NULL DEFAULT 0;

-- Exemplos de efeitos aceitos pelo frontend: fade, slide e zoom.
-- A duração é informada em segundos, entre 2 e 60.
