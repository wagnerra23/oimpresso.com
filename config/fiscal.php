<?php

declare(strict_types=1);

/**
 * Config canon Modules/NfeBrasil + Modules/Fiscal (ADR 0186).
 *
 * Centraliza:
 *   - fallback institucional pra chain de certificado SEFAZ
 *   - matriz UFs suportadas pelo SEFAZ ConsultaCadastro
 *   - feature flags por consumidor (drawer 760, NFe emissão, etc)
 *
 * Aplica a:
 *   - Modules/NfeBrasil/Services/CertificadoService::carregarParaSefazComFallback
 *   - Modules/NfeBrasil/Services/SefazConsultaCadastroService
 *   - Modules/Crm/Http/Controllers/ClienteLookupController::cnpjSefaz
 *
 * @see memory/decisions/0186-chain-certificado-sefaz-consulta-cadastro.md
 */

return [
    /*
    |---------------------------------------------------------------------------
    | Fallback institucional pra chain de certificado SEFAZ (ADR 0186 §Decisão)
    |---------------------------------------------------------------------------
    |
    | business_id do oimpresso operacional cujo certificado A1 é usado quando
    | o business consumidor não tem cert próprio nem legado. Padrão biz=1
    | (oimpresso operacional). Cada uso desse fallback gera audit log em
    | `mcp_audit_log` com `event = 'sefaz.cert.fallback_institutional_used'`.
    |
    | Multi-tenant Tier 0 (ADR 0093): `withoutGlobalScope(ScopeByBusiness::class)`
    | autorizado APENAS na query desse fallback (`CertificadoService::carregarParaSefazComFallback`).
    | Pest test garante invariante.
    |
    */
    'fallback_business_id' => env('FISCAL_FALLBACK_BUSINESS_ID', 1),

    'sefaz_consulta_cadastro_enabled' => env('FISCAL_SEFAZ_CONSULTA_CADASTRO_ENABLED', true),

    /*
    |---------------------------------------------------------------------------
    | Matriz UFs suportadas pelo SEFAZ ConsultaCadastro (ADR 0186 §Decisão)
    |---------------------------------------------------------------------------
    |
    | Apenas UFs com web service NFe ConsultaCadastro2 (cConsCad) funcionando
    | em produção. Fonte: comunidade nfephp-org + SAP Community + auditoria
    | 2026-05-23 (sessions/2026-05-23-arte-busca-cliente-cnpj-ie.md).
    |
    | UFs NÃO listadas → frontend mostra badge "SEFAZ-XX indisponível, preencha
    | IE manualmente". Sem hardcoded — config-driven permite ligar UFs novas
    | sem deploy quando SEFAZ publicar.
    |
    | `endpoint`: identifier interno (svrs = Servidor Virtual RS atende várias UFs,
    | ou nome próprio quando UF tem WS independente).
    |
    | `status`:
    |   - production: testado e estável, default ON
    |   - beta: testado parcialmente, considerar disable se problemas reportados
    |   - deprecated: SEFAZ avisou descontinuação, fallback obrigatório
    |
    */
    'sefaz_consulta_cadastro_ufs_supported' => [
        'RS' => ['endpoint' => 'svrs', 'status' => 'production'],
        'SP' => ['endpoint' => 'sp',   'status' => 'production'],
        'PR' => ['endpoint' => 'pr',   'status' => 'production'],
        'MG' => ['endpoint' => 'mg',   'status' => 'production'],
        'BA' => ['endpoint' => 'ba',   'status' => 'production'],
        'SC' => ['endpoint' => 'svrs', 'status' => 'production'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Cache TTL pra SEFAZ ConsultaCadastro (Redis)
    |---------------------------------------------------------------------------
    |
    | Resposta SEFAZ é mais estável que BrasilAPI mas pode mudar quando empresa
    | troca regime/IE. 30 dias é o sweet spot pra Larissa biz=4 ~30 cadastros/dia
    | (rate-limit SEFAZ típico 1-3 req/s por UF).
    |
    | Key: `sefaz_cadastro:{uf}:{cnpj_digits}`.
    | Compartilhado entre businesses (dado público — mesma justificativa BrasilAPI).
    |
    */
    'sefaz_consulta_cadastro_cache_ttl_seconds' => 60 * 60 * 24 * 30, // 30 dias

    /*
    |---------------------------------------------------------------------------
    | Timeout SEFAZ ConsultaCadastro (ADR 0186 §Invariante #11 — hardening 2026-05-23)
    |---------------------------------------------------------------------------
    |
    | Connect timeout em segundos. sped-common adiciona +20s ao soaptimeout pro
    | CURLOPT_TIMEOUT total. Valor 4 = conecta em 4s OU falha + total request 24s.
    |
    | Default sped-common era 20 (conecta 20s + total 40s) — drawer 760 travava
    | em SEFAZ-RS lerda. Frontend complementa com AbortController 8s
    | (IdentificacaoTab.handleCnpjLookup) — cancela ANTES do backend terminar
    | se SEFAZ conectou mas está demorando demais.
    |
    */
    'sefaz_consulta_cadastro_timeout_seconds' => env('FISCAL_SEFAZ_TIMEOUT_S', 4),

    /*
    |---------------------------------------------------------------------------
    | Timeout frontend AbortController (ms) — IdentificacaoTab.handleCnpjLookup
    |---------------------------------------------------------------------------
    |
    | Drawer 760 abort do fetch SEFAZ após N ms. Limite UX — se SEFAZ não
    | responder, drawer mostra badge "demorou demais" + usuário preenche manual.
    |
    | Exposto via Inertia share() pro front consumir.
    |
    */
    'sefaz_consulta_cadastro_frontend_timeout_ms' => env('FISCAL_SEFAZ_FRONTEND_TIMEOUT_MS', 8000),

    /*
    |---------------------------------------------------------------------------
    | Health-check cert (ADR 0186 fase 6 — não obrigatório no PR inicial)
    |---------------------------------------------------------------------------
    |
    | Cron `php artisan fiscal:cert-health-check` alerta business quando cert
    | próximo de vencer. Threshold em dias.
    |
    */
    'cert_health_check_alert_days' => env('FISCAL_CERT_HEALTH_ALERT_DAYS', 30),
];
