<?php
declare(strict_types=1);

/*
 * Conexão com o banco de dados.
 *
 * As credenciais do cliente PRINCIPAL vêm das variáveis de ambiente abaixo
 * (ou dos valores padrão para XAMPP/WAMP). Para outros clientes, cadastre-os
 * em Admin › Clientes (gravado em config/tenants.json).
 *
 *   DB_HOST=127.0.0.1  DB_NAME=banco_negocios  DB_USER=root  DB_PASS=  DB_PORT=3306
 *
 * Para definir sem variáveis de ambiente, edite os valores padrão em
 * core/tenant.php › bn_default_tenant().
 */
require_once __DIR__ . '/../core/bootstrap.php';
