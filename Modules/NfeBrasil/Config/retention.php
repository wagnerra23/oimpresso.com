<?php

declare(strict_types=1);

/**
 * Política de retenção de dados — Módulo NfeBrasil (D7 LGPD compliance).
 *
 * NfeBrasil = emissão/recepção NFe/NFC-e/NFSe + DF-e (Documento Fiscal eletrônico
 * recebido). Tabelas contêm PII fiscal estruturada (CNPJ/CPF emitente +
 * destinatário, endereço, valor operação) com vínculo direto a obrigações
 * tributárias federais/estaduais/municipais.
 *
 * LGPD Art. 16: dados pessoais devem ser eliminados após o término do tratamento.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093]):
 * Jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant cleanup.
 *
 * **CRÍTICO — Obrigação fiscal SOBREPÕE LGPD Art. 16:**
 * Documentos fiscais eletrônicos são REGULADOS por legislação tributária com
 * janelas de guarda OBRIGATÓRIAS que sobrepõem o direito de eliminação:
 * - **CTN Art. 173 §I** (decadência tributária): **5 anos** mínimo
 * - **CONFAZ Convênio SINIEF 07/2005 Art. 4º §1º** (NFe): **mínimo 5 anos**
 * - **LC 116/2003 + leis municipais ISSQN** (NFSe): tipicamente **5 anos**
 * - **Lei 8.137/90** (crimes contra ordem tributária): até **12 anos** prescrição
 *
 * Por isso defaults aqui são **3650 dias (10 anos)** — garante margem segura
 * pra qualquer auditoria fiscal Receita Federal/SEFAZ/Município sem expor
 * empresa a multa por falta de guarda.
 *
 * **Append-only contrato:**
 * `nfe_emissoes` e `nfe_eventos` são APPEND-ONLY por força de lei (CONFAZ SINIEF
 * 07/2005 Art. 14) — NUNCA hard-delete, mesmo após cancelamento via SEFAZ.
 * Anonymize aqui é NO-OP pra colunas fiscais (CNPJ/CPF emitente são imutáveis
 * por contrato fiscal); apenas dados acessórios (notas internas, log debug)
 * podem ser anonimizados.
 *
 * Valores em DIAS. **NÃO REDUZIR sem ADR + parecer contábil/jurídico.**
 *
 * **Status atual (2026-05-16):** declaração canônica. Jobs
 * `nfebrasil:retention-purge` em backlog (cenário raro — fiscal geralmente
 * archive, não purge). Esta config É a fonte da verdade pra auditoria LGPD
 * (sub-item D7.c rubrica governance v3).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Default false — em fiscal, archive (mover pra storage cold S3/Glacier)
    | é estratégia mais comum que purge real. Ativar APENAS após parecer
    | contábil + jurídico per-business.
    */
    'enabled' => env('NFEBRASIL_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por tabela (em DIAS)
    |--------------------------------------------------------------------------
    | nfe_emissoes/eventos/inutilizacoes: 3650d (10y) — CONFAZ + CTN + margem
    |                                     prescrição Lei 8.137/90
    | nfe_certificados: 3650d (10y) — A1/A3 certificado fiscal, evidência
    |                   assinatura de documentos emitidos
    | nfe_fiscal_rules/business_configs/tax_rate_links: null (indefinido) —
    |                  config tributária per-business; vive enquanto business
    |                  vive (regra de negócio fiscal, não PII)
    | nfe_dfe_recebidos/itens/eventos: 3650d (10y) — DF-e capturado da SEFAZ
    |                                  contém CNPJ fornecedor + nossa empresa;
    |                                  evidência fiscal de entrada
    | nfe_dfe_nsu_state: null (indefinido) — checkpoint sync NSU SEFAZ
    |                    (sem PII, controle operacional)
    | nfse_emissoes/eventos_cancelamento: 3650d (10y) — espelho NFe para ISSQN
    */
    'tabelas' => [
        'nfe_emissoes'                      => 3650,   // 10 anos (CONFAZ + CTN)
        'nfe_eventos'                       => 3650,   // 10 anos (audit fiscal)
        'nfe_inutilizacoes'                 => 3650,   // 10 anos (numeração SEFAZ)
        'nfe_certificados'                  => 3650,   // 10 anos (evidência assinatura)
        'nfe_fiscal_rules'                  => null,   // indefinido (regra)
        'nfe_business_configs'              => null,   // indefinido (config)
        'nfe_fiscal_rule_tax_rate_links'    => null,   // indefinido (link)
        'nfe_dfe_recebidos'                 => 3650,   // 10 anos (entrada fiscal)
        'nfe_dfe_itens'                     => 3650,   // 10 anos (entrada fiscal)
        'nfe_dfe_eventos'                   => 3650,   // 10 anos (audit fiscal)
        'nfe_dfe_nsu_state'                 => null,   // indefinido (checkpoint)
        'nfse_emissoes'                     => 3650,   // 10 anos (ISSQN municipal)
        'nfse_eventos_cancelamento'         => 3650,   // 10 anos (audit fiscal)
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | Default 'anonymize' é NO-OP pra colunas fiscais imutáveis (CNPJ/CPF
    | emitente/destinatário, valor, chave acesso 44 dígitos). PiiRedactor
    | atua APENAS em campos acessórios (notas internas, log debug, observações
    | livres). Documento fiscal É IMUTÁVEL por contrato CONFAZ.
    */
    'strategy' => env('NFEBRASIL_RETENTION_STRATEGY', 'anonymize'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio ao titular (em DIAS)
    |--------------------------------------------------------------------------
    | Pra documento fiscal, aviso é mais um placeholder simbólico — eliminação
    | real exige parecer contábil prévio (não basta opt-out cliente).
    */
    'notice_period_days' => 30,
];
