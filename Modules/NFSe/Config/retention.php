<?php

declare(strict_types=1);

/**
 * Política de retenção de dados pessoais — Módulo NFSe (D7 LGPD compliance).
 *
 * Declara explícitamente o tempo de retenção de cada entidade que armazena PII
 * (CPF/CNPJ tomador, email, descrição com possível nome cliente) ou dados fiscais
 * obrigatórios (XML envio/retorno SEFAZ municipal, código verificação, protocolo).
 * LGPD Art. 16: dados pessoais eliminados após término do tratamento; CONFAZ SINIEF
 * 07/2005 Art. 14: nota fiscal eletrônica preservada 5 anos (1825d) — prevalece sobre LGPD.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **Append-only contrato (Tier 0):**
 * `activity_log` é AUDITORIA (LogsActivity em NfseEmissao) — NUNCA purgada.
 * `nfse_emissoes` segue regra fiscal: status `emitida`/`cancelada` retém 5 anos
 * (CONFAZ); `erro`/`rejeitada` pode purgar após 1 ano (sem efeito fiscal).
 *
 * Valores em DIAS. Defaults: 5 anos fiscal CONFAZ; 1 ano webhook/log municipal.
 *
 * **Status atual (Wave 14 — 2026-05-16):** declaração canônica. Jobs
 * `nfse:retention-purge` ficam em backlog pra próxima onda Governance.
 * Esta config É a fonte da verdade pra auditoria LGPD (sub-item D7.c).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules\NfeBrasil\Config\config.php  (espelho NFe — mesma regra CONFAZ)
 * @see Modules\Crm\Config\retention.php     (espelho Crm — mesma estrutura)
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, jobs de purge consultam estas configs antes de deletar.
    | Default false até job `nfse:retention-purge` estar implementado +
    | aprovado por Wagner em canary (ADR 0105 — sinal qualificado).
    */
    'enabled' => env('NFSE_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | nfse_emissao_fiscal: 1825 (5 anos CONFAZ SINIEF 07/2005 Art. 14 —
    |     prevalece sobre LGPD; status emitida/cancelada)
    | nfse_emissao_erro:   365  (1 ano — status erro/rejeitada sem efeito fiscal,
    |     LGPD Art. 16 — minimização)
    | webhook_municipal:   365  (1 ano — log retorno SEFAZ municipal com possível
    |     CPF/CNPJ tomador, LGPD Art. 6º IX)
    | provider_config:     null (indefinido — credencial ativa enquanto contrato
    |     ativo; revogação manual via comando artisan)
    | certificado_a1:      null (indefinido — vida útil do cert A1 = 1 ano,
    |     gerenciada pelo próprio expires_at; purge manual após expiração)
    */
    'entities' => [
        'nfse_emissao_fiscal'   => 1825, // 5 anos CONFAZ
        'nfse_emissao_erro'     => 365,  // 1 ano sem valor fiscal
        'webhook_municipal'     => 365,  // 1 ano (PII tomador em payload SOAP)
        'provider_config'       => null, // indefinido (lifecycle contrato)
        'certificado_a1'        => null, // indefinido (lifecycle cert A1)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at` (recuperável via timestamps — DEFAULT
    |     pra emissões fiscais pelo period CONFAZ; auditoria preserva-se em
    |     activity_log mesmo após soft-delete)
    | 'anonymize'   = substitui PII por placeholder via PiiRedactor — preserva
    |     métricas agregadas (count NFSe/mês, valor total) sem reter PII
    | 'hard_delete' = DELETE definitivo (LGPD Art. 18 §VI — direito eliminação;
    |     APENAS para webhook_municipal e nfse_emissao_erro)
    |
    | Default 'soft_delete' alinha contrato fiscal + LGPD direito eliminação.
    */
    'strategy' => env('NFSE_RETENTION_STRATEGY', 'soft_delete'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | LGPD Art. 18 §VI sugere aviso prévio antes de eliminação. Job de purge
    | dispara notificação ao tomador (via email cadastrado) N dias antes do
    | delete real — somente quando estratégia=anonymize ou hard_delete.
    */
    'notice_period_days' => 30,
];
