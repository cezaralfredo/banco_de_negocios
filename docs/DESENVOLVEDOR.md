# Guia do desenvolvedor — CMS v7

## Inicialização
Toda página inclui `config/database.php`, que carrega `core/bootstrap.php`:
1. `bn_resolve_tenant()` escolhe o cliente pelo domínio (`config/tenants.json`) — em `localhost` aceita `?cliente=slug`.
2. Conecta ao banco do cliente (`$pdo`), carrega os módulos de `core/`.
3. Sessão `cms_<cliente>` (isolada), cabeçalhos de segurança, `ensure_schema()` (migrações idempotentes).
4. Plugins ativos do cliente e `do_action('init')`.
5. Páginas públicas passam por `maintenance_gate()`.

## Multi-cliente
- Cadastro: `config/tenants.json` (gerenciado em Admin › Clientes). O cliente `principal` usa `DB_*`/`config/local.php`.
- Mídia: `media/<slug>/AAAA/MM`. Privado: `storage/<slug>/` (backups, logs, lixeira de arquivos).
- `BN_TENANT`, `BN_TENANT_DATA`, `is_primary_tenant()`, `media_base()`, `storage_path()`.

## Permissões (RBAC)
- Catálogo em `permission_catalog()` (filtro `permissions`). Formato `ação:recurso`.
- `can('publicar:noticias')`, `deny_unless($perm)` (redireciona com aviso), `require_perm($perm)` (endpoints).
- Páginas do painel: a permissão para abrir vem do menu em `admin/includes/init.php` ou da variável `$admin_perm` definida antes do `require`.
- Padrão PRG: `require 'includes/init.php'` → processar POST → `flash()` + `redirect()` → `include 'includes/header.php'`.

## Conteúdo
- Versões: `revision_save($tipo, $id)` após salvar; tipos em `content_types()` (filtro `content_types`).
- Lixeira: `$tid = trash_delete($tabela, $id, $rotulo, $recurso, [[tabela_rel, coluna]])` e `flash('success', 'Excluído', undo_link($tid, 'pagina.php'))`.
- Workflow: `workflow_actions()`, `workflow_can_edit()`, `workflow_transition()`.
- Auditoria: `audit($acao, $recurso, $id, $detalhes)`.
- Mídia: `media_store_upload()`, `media_field()` / `media_field_value()` nos formulários, `media_usage()`.
- SEO: `seo_fields()` / `seo_post()` nos formulários; `$seo = [...]` antes de incluir `includes/header.php`.

## Hooks
Ações: `init`, `head`, `body_start`, `footer`, `admin_head`, `admin_footer`, `dashboard_widgets`, `settings_tab`, `settings_saved`, `content_saved`, `content_deleted`, `content_restored`, `workflow_changed`, `user_login`, `user_logout`, `maintenance_changed`, `cron`.
Filtros: `admin_menu`, `permissions`, `content_types`, `seo_meta`, `mail_before_send`.

## Plugins
```
plugins/meu-plugin/plugin.json  {"nome": "...", "descricao": "...", "versao": "1.0.0", "configuracao": "plugin.php?p=meu-plugin"}
plugins/meu-plugin/plugin.php   add_action(...) / add_filter(...) / plugin_admin_page(...)
```
Configurações do plugin: `setting($pdo, 'plugin_meu_x')` / `save_setting(...)`. Segredos: `api_key('identificador')`.

## Temas
`themes/<slug>/theme.json` define `nome`, `descricao`, `preview` (cores) e `variaveis` (ver `theme_var_schema()`); `theme.css` é opcional e deve usar as variáveis CSS (`--wine`, `--navy`, `--page-bg`, `--surface`, `--footer-bg`, `--hero-from`, `--hero-to`, `--heading-font`, `--site-font`, `--radius`, `--btn-radius`, `--container`).
