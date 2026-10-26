# CMS — Banco de Negócios (white label)

CMS de diretório comercial e conteúdo em **PHP 8.1+ (sem frameworks), MySQL/MariaDB, HTML5, CSS3 e JavaScript**. “Banco de Negócios” é o primeiro cliente: nome, logo, cores, tema, textos e configurações são definidos por cliente no painel, sem alterar código.

## Instalação local

1. Copie a pasta `banco-de-negocios` para `htdocs` (XAMPP), `www` (WAMP) ou a pasta pública do Laragon.
2. Inicie Apache e MySQL.
3. No phpMyAdmin, importe `sql/db_banco_negocios.sql`.
4. Se necessário, ajuste `config/database.php` ou defina `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS`.
5. Acesse `http://localhost/banco-de-negocios/`.

## Painel administrativo

Acesse `/admin/`. Credenciais de demonstração: `admin@bancodenegocios.com.br` / `password`. Troque essa senha em produção. O login usa `password_verify()` e as páginas do painel são protegidas por `$_SESSION`.

## Funcionalidades

A página inicial possui barra superior vinho, navegação, hero responsivo, slider de banners gerenciável, busca avançada, filtro alfabético, categorias, cards de anúncios, CTA, cookies, footer e painel de acessibilidade. O conteúdo utiliza consultas PDO quando o banco está disponível e dados de demonstração como fallback para facilitar a visualização inicial.

O CMS contém login, dashboard, CRUD de anúncios, CRUD de categorias, CRUD de usuários, configurações gerais, páginas institucionais e gerenciamento completo de banners. O cadastro público está disponível em `cadastro.php`.

## Slider de banners

A página inicial consulta os banners ativos da tabela `banners`. Para uma base já existente, execute `sql/atualizar_banners.sql` no banco `banco_negocios` antes de cadastrar ou editar banners. No painel, cada banner permite configurar **tempo na tela** (2 a 60 segundos), **efeito** (`fade`, `slide` ou `zoom`), **ordem**, imagem, título, subtítulo, botão e status ativo/inativo. Apenas banners ativos aparecem no site.

Após importar uma base existente, execute também o arquivo `sql/db_banco_negocios.sql` caso ainda não tenha aplicado as atualizações de usuários, páginas e configurações.

Para produção, recomenda-se revisar as credenciais, ativar HTTPS, configurar limites de upload no PHP e manter o armazenamento seguro das imagens em `assets/img/`.

## Funcionalidades do prompt3

Para uma instalação existente, execute `sql/atualizar_prompt3.sql`. A migração adiciona a identidade editável do site, campos de hierarquia e imagem nas páginas e a tabela de itens do menu.

O painel agora possui módulos separados de **Identidade do site**, **Aparência** e **Menu principal**. A identidade permite alterar nome, slogan e logo; a aparência permite alterar cores, tipografia e largura do layout; o menu permite criar, editar, remover, ordenar e aninhar itens.

O CMS de páginas aceita conteúdo HTML básico, títulos, listas, links e imagens por URL, além de página pai, ordem e status de publicação. No frontend, o menu mobile foi reorganizado, o alfabeto A–Z tornou-se uma faixa horizontal com setas, a grade de categorias mostra três itens no mobile e o footer usa colunas empilháveis.

## Funcionalidades do prompt4

Execute `sql/atualizar_prompt4.sql` em uma instalação existente. A migração adiciona logo aos anúncios, favicon, logo compacto, tema da navegação e a tabela `home_promocao`.

O painel usa Bootstrap 5.3.8 e agora inclui upload seguro de capa e logotipo de anúncios, com validação MIME, limite de tamanho, criação automática da pasta de uploads e redimensionamento quando a extensão GD está disponível. A home e a listagem de categorias exibem a capa como fundo do card e o logo no avatar circular.

Em **Identidade do site**, é possível enviar logo principal, logo compacto para telas menores e favicon. Em **Aparência**, o administrador escolhe o esquema de contraste da barra de navegação. A sidebar administrativa destaca automaticamente a tela atual. O menu principal renderiza submenus usando dropdown do Bootstrap.

O cadastro público passou a usar modal Bootstrap. Após o envio, a conta é criada como `inativo`, aguarda ativação pelo administrador e, quando bem-sucedido, exibe confirmação e redireciona após três segundos. O módulo **Promoção da home** controla a imagem e o conteúdo da área abaixo dos anúncios, inclusive sua exibição.

## Correções do prompt5

A versão atual corrige o modal de cadastro da seção “Amplie suas oportunidades” usando um formulário embutido, sem abrir a página pública por trás. O cadastro preserva os dados na sessão e a página de Termos de Uso oferece retorno ao fluxo.

A home separa categorias em destaque das demais categorias. Novas categorias aparecem em “Todas as categorias” e podem ser marcadas individualmente no painel para participar dos destaques. O filtro A–Z e o filtro por categoria funcionam na listagem pública.

Os cards sobre o banner agora são alinhados, clicáveis e usam o link editável do anúncio. A imagem decorativa lateral do banner foi removida. O painel de anúncios permite editar link e selecionar o anúncio para “Negócios em destaque”, definindo sua ordem.

A página de perfil foi reorganizada com capa, logotipo, descrição, contato, publicações/blog e uma coluna lateral de outros anúncios. As tabelas administrativas receberam tratamento responsivo. O schema também inclui `sql/atualizar_prompt5.sql`, com as colunas e tabelas necessárias para anúncios, banners, categorias, destaques e publicações.

## Novidades do prompt6

**Atualização de uma instalação existente:** importe `sql/atualizar_prompt6.sql` no phpMyAdmin. Se preferir, apenas abra o site: a migração automática (`ensure_schema()` em `includes/functions.php`) cria as tabelas e colunas novas na primeira visita. Para uma instalação nova, use somente `sql/db_banco_negocios.sql` (schema completo, agora com `SET NAMES utf8mb4`).

> A senha do administrador de demonstração não funcionava porque o hash do SQL original era inválido. A migração corrige o hash: `admin@bancodenegocios.com.br` / `password` (troque em produção).

### Correções
- **Anúncios sobre o banner (4):** cards com fundo branco, título em negrito, categoria, avaliação e localização visíveis e imagem (logo ou capa) à esquerda, alinhados verticalmente. Todos apontam para o mesmo link do “Ver perfil” (`perfil.php?id=…`).
- **Busca A–Z + campos (6):** reescrita em `search_filters()` / `search_ads()`. Letra, texto, categoria, localização, tipo (novo campo *tipo* no anúncio) e ordenação são aplicados juntos. A página de resultados mostra os filtros ativos (removíveis), a contagem e mantém o formulário preenchido.
- **Modal “Criar conta” (7):** existe um único modal (`includes/signup_modal.php`), aberto pela barra superior, pelo banner, pela seção “Amplie suas oportunidades!”, pelo rodapé e por qualquer link para `cadastro.php`. O envio é via AJAX, sem iframe.
- **Categorias (9.1):** checkbox “Exibir em Categorias em destaque” não se repete mais; a categoria em edição fica destacada (linha, botão e formulário com visual “Editando”). O mesmo padrão foi aplicado a anúncios, banners, notícias, textos do banner, redes sociais e idiomas. O formulário de anúncios também tinha campos triplicados e um erro que gravava o destaque com ID 0 — corrigidos.
- **Promoção da home (9.3):** a seção “Amplie suas oportunidades!” agora lê a tabela `home_promocao` (título, texto, benefícios, botão, link, imagem e exibir/ocultar).

### Novas funcionalidades
- **Multilíngue (1):** tabelas `idiomas` e `traducoes`. Em *Admin › Idiomas e traduções* é possível cadastrar idiomas, definir o padrão e traduzir textos da interface (`t()`, textos-base em `includes/i18n.php`) e conteúdos cadastrados (`tc()`: banners, textos do banner, menu, promoção, rodapé, páginas). O visitante troca o idioma pelo seletor da barra superior (`?lang=en`).
- **Notícias (3):** *Admin › Notícias* (capa, resumo, conteúdo HTML, autor, destaque, agendamento). Páginas `noticias.php` e `noticia.php` e também `pagina.php` seguem o layout do perfil (capa, corpo e coluna lateral).
- **Textos do banner (5):** *Admin › Textos do banner* para incluir/alterar/ordenar os itens (com escolha de ícone), exibidos maiores no banner.
- **Rodapé (8):** *Admin › Rodapé e redes sociais* para editar todas as colunas, links, contato, barra inferior e incluir/excluir redes sociais (ícones SVG).
- **LGPD (10):** banner no final da página no primeiro acesso com Aceitar, Recusar e Personalizar (necessários, preferências, estatísticas, marketing). A escolha é salva no cookie `bn_consent`, registrada em `consentimentos_cookies` (com IP anonimizado por hash) e pode ser alterada em “Preferências de cookies” no rodapé. O texto e a versão da política são configuráveis. Scripts de terceiros podem checar `window.BNConsent.allows('statistics')`.

### Acessibilidade e UX
- **Ícone na barra superior (2.1)** maior e em destaque; abre o painel de acessibilidade em qualquer página.
- **Coluna no banner (2.2):** Fonte maior, Fonte menor, Contraste escuro, Destacar links, Leitor de texto (Web Speech API, no idioma da página) e Fonte para dislexia (OpenDyslexic, incluída em `assets/fonts`). As escolhas persistem quando o visitante aceita cookies de preferências.
- **Slider (9.2):** em *Admin › Banners e slider* é possível ligar/desligar setas, indicadores e troca automática e escolher o efeito padrão (fade, deslizar, zoom ou sem efeito); cada banner pode ter um efeito próprio. Também suporta teclado (← →) e gesto de arrastar no celular.


---

## Versão 8 — Layout e tipografia

- **Menu lateral em accordion (sanfona):** Conteúdo, Aparência, Sistema, Plugins e Ajuda abrem os submenus ao clicar e fecham automaticamente quando outro é aberto. A seção da página atual já abre expandida. Acessível por teclado (setas ↑/↓, Home/End) e com `aria-expanded`.
- **Tipografia padronizada (referência: template Velzon):** Hanken Grotesk no texto e Poppins nos títulos.
  - Painel: base 13px, títulos 16–20px com peso 600, textos de apoio 12px, menu 15px e submenu 13px.
  - Site: base 15px, apoio 13px, mínimo 12px, títulos de 17 a 38px com peso 600 e entrelinhas de 1.6 (texto) e 1.25 (títulos).
  - Toda a escala fica em `assets/css/typography.css` (site) e no bloco "v8" de `assets/css/admin.css` (painel). Basta ajustar as variáveis `--fs-*`.
- O tema Clássico passa a usar Hanken Grotesk + Poppins. Personalizações de cor são mantidas. As fontes podem ser trocadas em **Aparência › Temas e layout**.
- **Atualização:** substitua os arquivos e acesse o painel. A migração v8 roda sozinha (não há SQL novo).

## Versão 7 — CMS profissional

> ⚠️ `sql/db_banco_negocios.sql` é só para **instalação nova**. Se você o importou num banco já existente e recebeu `Duplicate entry ... noticias.slug`, execute `sql/corrigir_importacao_repetida.sql` (remove linhas repetidas) e confira logo, favicon e textos do rodapé (a versão antiga do arquivo os restaurava ao padrão).
>
> **Atualizar uma instalação existente:** substitua os arquivos e acesse o painel — a migração automática cria tabelas, colunas, papéis padrão e configurações. Opcionalmente, importe antes `sql/atualizar_v7.sql` (compatível com MySQL 5.7/8 e MariaDB). **Instalação nova:** importe `sql/db_banco_negocios.sql` e acesse o site.
>
> Requisitos: PHP 8.1+ com `pdo_mysql`, `gd`, `fileinfo`, `sodium`, `zip` e `mbstring`; MySQL 5.7+ ou MariaDB 10.3+. As pastas `media/` e `storage/` precisam permitir escrita. Credenciais do banco: variáveis de ambiente `DB_*` ou `config/local.php` (copie `config/local.example.php`).

O guia completo para administradores está no próprio painel em **Ajuda › Guia do administrador** (`admin/ajuda.php`); cada tela tem um botão “? Ajuda” que abre a seção correspondente. Detalhes técnicos em `docs/DESENVOLVEDOR.md`.

| Recurso | Onde | Resumo |
|---|---|---|
| Multi-cliente (white label) | Sistema › Clientes | Um banco de dados e uma pasta de mídia por cliente, escolhido pelo domínio; sessões isoladas; criação automática do banco e do primeiro administrador. Local: `?cliente=slug`. |
| Modo de manutenção | Sistema › Modo de manutenção | Página personalizada (HTTP 503), agendamento com desligamento automático, contagem regressiva, bypass para quem tem `acessar:manutencao`, IPs liberados e link secreto. |
| RBAC | Sistema › Papéis e permissões | Permissões `ação:recurso` (ex.: `publicar:noticias`); papéis Administrador, Editor-chefe, Editor, Leitor e personalizados. |
| Temas | Aparência › Temas e layout | 4 temas (Clássico, Corporativo, Natureza, Elegante) com variáveis centralizadas (cores, fontes, raio, botões, largura), pré-visualização e “desfazer”. |
| Versões | botão “Histórico de versões” | Notícias, páginas, anúncios e banners: quem/quando/o quê, comparação lado a lado e restauração. |
| Fluxo editorial | Notícias e Páginas | Rascunho → Em revisão → Aprovado → Publicado → Arquivado, com comentários e e-mails de aviso. |
| Biblioteca de mídia | Conteúdo › Biblioteca de mídia | Upload múltiplo, pastas, busca, texto alternativo, corte e redimensionamento, “onde é usado” e seletor nos formulários. |
| SEO | campos em cada conteúdo + Configurações › SEO | Título SEO, meta description, slug editável, Open Graph, indexação por página, sitemap, robots e URLs amigáveis. |
| Auditoria | Sistema › Logs de auditoria | Logins, permissões, manutenção, exclusões, backups etc., com filtros e exportação CSV. |
| Configurações globais | Sistema › Configurações globais | Geral, identidade, SEO, SMTP, analytics, chaves de API (criptografadas), segurança, conteúdo. |
| Backup | Sistema › Backup | Banco + mídia em .zip, automático (diário/semanal) com retenção, restauração com backup prévio, `cron.php`. |
| Lixeira e desfazer | Sistema › Lixeira | Toda exclusão pede confirmação, vai para a lixeira e oferece “Desfazer”. |
| Plugins e hooks | Sistema › Plugins | `plugins/<slug>/plugin.json` + `plugin.php`, ações/filtros, páginas e permissões próprias. Exemplos: WhatsApp flutuante e Barra de aviso. |
| Integrações | Configurações globais | E-mail SMTP (recuperação de senha, boas-vindas, fluxo editorial) e Analytics (GA4, GTM, Matomo, scripts) carregados só com consentimento LGPD. |

### Estrutura

```
core/        núcleo (bootstrap, clientes, RBAC, auditoria, lixeira, versões, workflow, mídia, SEO, temas, manutenção, backup, plugins, e-mail)
admin/       painel
includes/    templates do site
themes/      temas (theme.json + theme.css)
plugins/     plugins
media/       uploads (uma pasta por cliente)
storage/     backups, logs e chave de criptografia (protegida)
config/      conexão e cadastro de clientes (protegida)
sql/         schema completo e migrações
```

### Tarefa agendada (recomendada)

Agende `cron.php?chave=...` (a chave aparece em Admin › Backup) uma vez por dia para backup automático, limpeza da lixeira, rotação de logs e agendamento da manutenção:

```
wget -q -O- "https://seusite.com.br/cron.php?chave=SUA_CHAVE"
```

### Segurança

O demo `admin@bancodenegocios.com.br` / `password` deve ter a senha trocada em produção. O arquivo `storage/app.key` criptografa senhas SMTP e chaves de API: inclua-o nos backups do servidor. O utilitário `gerar_hash.php` foi removido.
