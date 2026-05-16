<?php

declare(strict_types=1);

/**
 * Politica de retencao de dados pessoais — Modulo ConsultaOs (D7 LGPD compliance).
 *
 * Declaracao canonica do tempo de retencao dos artefatos que armazenam PII ou
 * metadados de identificacao do PORTAL PUBLICO de consulta de OS:
 *
 *  - consulta_os_logs   : audit log de cada busca publica (IP, numero consultado redacted,
 *                          User-Agent truncado, timestamp). 365 dias = janela ANPD Resolucao
 *                          02/2022 minima + cobertura conferencia fiscal anual.
 *  - consulta_os_tokens : tokens publicos efêmeros usados para identificar OS sem auth
 *                          (quando US-CONSULTA-001 sair de mock-only). 90 dias = janela
 *                          razoavel para cliente acompanhar OS (entrega + 60d garantia).
 *
 * LGPD Art. 16: dados pessoais devem ser eliminados apos termino do tratamento.
 * LGPD Art. 7º §VII: tratamento para protecao do credito (manutencao OS em aberto).
 *
 * **Multi-tenant Tier 0 IRREVOGAVEL** ([ADR 0093]):
 * Quando US-CONSULTA-001 ativar busca real, jobs de purge respeitam business_id
 * global scope — NUNCA cross-tenant cleanup. Hoje (mock-only) sem persistencia.
 *
 * **Append-only contrato:**
 * `activity_log` (Spatie LogsActivity) é AUDITORIA — NUNCA purgada, mesmo que
 * dado-fonte seja. Retencao abaixo aplica ao dado vivo (logs estruturados em
 * `storage/logs/consultaos-*.log`), nao ao audit trail formal.
 *
 * Valores em DIAS. Defaults conservadores alinhados pratica BR (Bling, Tiny,
 * Conta Azul — portais publicos rastreio mantem ~1 ano de log).
 *
 * **Status atual (2026-05-16):** declaracao canonica D7.c. Jobs `consultaos:retention-purge`
 * que aplicam efetivamente a politica ficam em backlog ate US-CONSULTA-001 sair de
 * mock-only (sem dado real persistido nao ha o que purgar — esta config É a fonte
 * da verdade pra auditoria LGPD do portal publico).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/ConsultaOs/SPEC.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 * @see Modules\Crm\Config\retention.php (pattern canonico Wave 9)
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar politica de retencao
    |--------------------------------------------------------------------------
    | Quando true, jobs de purge consultam estas configs antes de deletar.
    | Default false ate job `consultaos:retention-purge` estar implementado +
    | aprovado por Wagner em canary (ADR 0105 — sinal qualificado).
    | Hoje (mock-only) sem persistencia — flag fica false na vida real.
    */
    'enabled' => env('CONSULTAOS_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retencao por artefato (em DIAS)
    |--------------------------------------------------------------------------
    | consulta_os_logs   : audit busca publica (IP + numero redacted) — 1 ano
    | consulta_os_tokens : tokens efêmeros de identificacao OS (US-CONSULTA-001) — 90d
    */
    'entities' => [
        'consulta_os_logs'   => 365,   // 1 ano (ANPD Res. 02/2022 + janela fiscal)
        'consulta_os_tokens' => 90,    // 90 dias (janela acompanhamento entrega + garantia)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estrategia de purge
    |--------------------------------------------------------------------------
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminacao)
    | 'anonymize'   = mantem registro mas redaciona PII via PiiRedactor
    |
    | Default 'anonymize' preserva metricas agregadas (volume buscas, taxa hit/miss
    | por dia) sem reter dado pessoal — alinha LGPD + observabilidade do portal.
    */
    'strategy' => env('CONSULTAOS_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso previo ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | Portal publico NAO armazena email/telefone do consultante (sem identificacao).
    | Aviso previo nao se aplica ao log de IP — registro tecnico de seguranca, nao
    | dado pessoal direto (LGPD Art. 5º §II — necessidade legitima seguranca rede).
    | Mantido aqui pra consistencia de schema com outros modulos (Crm pattern).
    */
    'notice_period_days' => 0,
];
