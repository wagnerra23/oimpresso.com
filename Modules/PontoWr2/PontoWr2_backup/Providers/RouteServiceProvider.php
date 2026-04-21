<?php

/**
 * ============================================================================
 * ARQUIVO DESCONTINUADO — NÃO USAR
 * ============================================================================
 *
 * Este RouteServiceProvider foi o responsável pelo crash em produção
 * (2026-04-18, 17:12) porque usava `module_path($mod, '/Routes/api.php')`,
 * assinatura não suportada pela versão do nWidart/laravel-modules que o
 * UltimatePOS utiliza. O Laravel acabava fazendo `require` do diretório do
 * módulo, o que falhava com "failed to open stream".
 *
 * Refatorado para o padrão Jana (ver ADR 0011):
 *   - Rotas agora em Http/routes.php
 *   - Carregamento via start.php (module.json "files": ["start.php"])
 *   - Sem RouteServiceProvider separado
 *
 * Este arquivo permanece vazio até ser removido fisicamente do repositório e
 * do servidor. NÃO É REFERENCIADO por module.json nem por PontoWr2ServiceProvider.
 *
 * Ação pendente: rm -f Modules/PontoWr2/Providers/RouteServiceProvider.php
 * ============================================================================
 */

// intencionalmente vazio
