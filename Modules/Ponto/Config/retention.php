<?php

declare(strict_types=1);

/**
 * Política de retenção de dados — Modules/Ponto.
 *
 * Wave 11 governance v3 booster D7.c (LGPD Art. 16 — eliminação ao fim do tratamento).
 *
 * Cada entrada documenta:
 *   - retention_years: período mínimo de guarda (alinhado às bases legais BR)
 *   - base_legal: artigo/lei que JUSTIFICA a retenção
 *   - hard_delete: se TRUE, registros após o prazo podem ser HARD DELETE; se FALSE,
 *     append-only (Portaria 671/2021 Art. 85) ou anonimização (LGPD Art. 12)
 *   - notes: observações operacionais (FK cascades, dependências)
 *
 * Bases legais aplicadas:
 *   - **CLT Art. 11** (prescrição quinquenal): direitos trabalhistas extintos em 5 anos
 *   - **Portaria MTP 671/2021 Art. 85**: marcações IMUTÁVEIS (append-only)
 *   - **Lei 8.213/91 Art. 11** + **DL 99.684/90**: PIS e FGTS — guarda 30 anos
 *     (não aplicado a marcações já cobertas por Portaria 671; aplica a cadastros)
 *   - **LGPD Art. 16 III** (cumprimento de obrigação legal — fiscal/trabalhista)
 *   - **LGPD Art. 18 III** (anonimização ao término do tratamento)
 *
 * ⚠️ NÃO USE este config pra justificar DELETE em `ponto_marcacoes`. Marcações são
 *    append-only por LEI (Portaria 671 Art. 85) — triggers MySQL + override Eloquent
 *    em `Modules\Ponto\Entities\Marcacao::update()` / `::delete()` bloqueiam.
 *    Período aqui (5 anos) refere-se ao requisito MÍNIMO de exibição/exportação na UI
 *    e EVENTUAL anonimização de FKs externas (colaborador_config_id → tombstone) após
 *    desligamento — JAMAIS hard delete.
 *
 * @see memory/proibicoes.md §"Multi-tenant Tier 0" e §"Append-only Portaria 671"
 * @see https://www.planalto.gov.br/ccivil_03/decreto-lei/del5452.htm (CLT)
 * @see https://www.in.gov.br/en/web/dou/-/portaria-mtp-n-671-de-8-de-novembro-de-2021 (Portaria 671)
 * @see https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm (LGPD)
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Marcações de ponto — APPEND-ONLY POR LEI
    |--------------------------------------------------------------------------
    | Portaria MTP 671/2021 Art. 85: imutáveis. Cadeia hash SHA-256 + NSR + trigger MySQL.
    | Período aqui é o REQUISITO MÍNIMO DE EXIBIÇÃO e EXPORTAÇÃO AFD/AFDT pra fiscalização
    | trabalhista (CLT Art. 11 — 5 anos de prescrição quinquenal).
    | hard_delete=false IRREVOGÁVEL.
    */
    'marcacoes' => [
        'retention_years' => 5,
        'base_legal'      => 'CLT Art. 11 + Portaria MTP 671/2021 Art. 85 (append-only)',
        'hard_delete'     => false,
        'notes'           => 'Imutáveis. Trigger MySQL + Eloquent override em Marcacao::update/delete. '
                           . 'Pós-prazo: continua disponível em DB; UI pode arquivar visualização.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Banco de Horas — movimentos (append-only ledger)
    |--------------------------------------------------------------------------
    | Reforma Trabalhista (Lei 13.467/2017): acordo individual de banco de horas tem
    | prazo de compensação de 6 meses. Histórico de movimentos precisa sobreviver
    | pra defesa trabalhista (CLT Art. 11 — 5 anos prescrição quinquenal).
    */
    'banco_horas_movimentos' => [
        'retention_years' => 5,
        'base_legal'      => 'CLT Art. 11 + Lei 13.467/2017 (Reforma — banco de horas individual)',
        'hard_delete'     => false,
        'notes'           => 'Append-only via Eloquent override. Saldo derivado preservado em '
                           . 'ponto_banco_horas_saldos (replicável). Pós-desligamento: 5 anos.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Intercorrências (workflow RASCUNHO→PENDENTE→APROVADA→APLICADA)
    |--------------------------------------------------------------------------
    | Justificativas trabalhistas com base legal CLT Art. 473 + atestados Art. 6º
    | Lei 605/49. Período espelha prescrição CLT (5 anos).
    */
    'intercorrencias' => [
        'retention_years' => 5,
        'base_legal'      => 'CLT Art. 11 (prescrição quinquenal) + Art. 473 (faltas justificadas)',
        'hard_delete'     => false,
        'notes'           => 'Anexos (atestados PDF) seguem mesma política. Anonimização recomendada '
                           . 'em vez de hard delete (preservar audit trail + minimizar PII).',
    ],

    /*
    |--------------------------------------------------------------------------
    | Apuração diária (cálculo CLT — derivado)
    |--------------------------------------------------------------------------
    | Re-derivável a partir de marcacoes + escala + intercorrencias. Período menor:
    | 2 anos (suficiente pra contestação imediata; passado isso re-apura sob demanda).
    */
    'apuracoes_dia' => [
        'retention_years' => 2,
        'base_legal'      => 'Re-derivável (marcacoes append-only sobrevivem 5 anos)',
        'hard_delete'     => true,
        'notes'           => 'Pode ser apagada e reapurada via ReapurarDiaJob. Cache de leitura.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Escalas de trabalho
    |--------------------------------------------------------------------------
    | Mudança de escala afeta jornada CLT — histórico via spatie/activitylog
    | (D7.b booster) preservado. Após fim do uso (escala desativada): 2 anos.
    */
    'escalas' => [
        'retention_years_pos_desativacao' => 2,
        'base_legal'                      => 'CLT Art. 11 (prescrição) + LGPD Art. 16 (fim do tratamento)',
        'hard_delete'                     => false,
        'notes'                           => 'Soft delete (SoftDeletes trait — não presente hoje, '
                                           . 'considerar adicionar). Histórico em activity_log preservado.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cadastro de colaborador (Modules\Ponto\Entities\Colaborador)
    |--------------------------------------------------------------------------
    | Bridge entre users (UltimatePOS) e ponto. Pós-desligamento: 5 anos pra atender
    | prescrição CLT + eventual ação trabalhista. PIS/CPF anonimizáveis após o prazo
    | (LGPD Art. 18 III) — substituir por hash; manter FKs históricas vivas.
    */
    'colaboradores' => [
        'retention_years_pos_desligamento' => 5,
        'base_legal'                       => 'CLT Art. 11 + Lei 8.213/91 (eSocial) + LGPD Art. 7º V',
        'hard_delete'                      => false,
        'notes'                            => 'SoftDeletes ativo. Anonimização recomendada após 5 anos: '
                                            . 'CPF/PIS → hash determinístico; matrícula preservada como tombstone. '
                                            . 'NUNCA hard delete (quebra FK em marcacoes append-only).',
    ],

    /*
    |--------------------------------------------------------------------------
    | REP (Registro Eletrônico de Ponto) cadastro
    |--------------------------------------------------------------------------
    | REPs ficam ativos enquanto a empresa opera. Inativação: marcar `ativo=false`.
    | NUNCA delete: marcacoes referenciam rep_id (hash chain — quebra integridade).
    */
    'reps' => [
        'retention_years_pos_desativacao' => 30,
        'base_legal'                      => 'Portaria MTP 671/2021 Art. 85 (rastreabilidade do REP) + '
                                          .  'Lei 8.213/91 (FGTS/INSS 30 anos)',
        'hard_delete'                     => false,
        'notes'                           => 'Inativação via `ativo=false`. Hard delete BLOQUEADO — '
                                           . 'marcacoes têm FK e cadeia hash SHA-256 depende do REP.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logs CLT audit — activity_log + storage/logs/laravel.log estruturado
    |--------------------------------------------------------------------------
    | Audit trail de Colaborador/Escala/Intercorrencia via spatie/activitylog
    | (D7.b booster). Necessário pra fiscalização MTE + defesa em ação trabalhista.
    | Período mais longo: 7 anos cobre 5 de prescrição + 2 de margem.
    */
    'audit_log' => [
        'retention_years' => 7,
        'base_legal'      => 'CLT Art. 11 (5y prescrição) + margem operacional 2y + LGPD Art. 37 (DPO)',
        'hard_delete'     => true,
        'notes'           => 'Tabela activity_log (Spatie). Logs PII em laravel.log já passam por '
                           . 'PiiRedactor (D7.a). Rotação via filesystem mensal recomendada.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Importações AFD (Modules\Ponto\Entities\Importacao + arquivos)
    |--------------------------------------------------------------------------
    | Arquivos AFD originais ficam em storage. Marcações derivadas vão pra
    | ponto_marcacoes (append-only). O arquivo bruto pode ser arquivado/deletado
    | após processamento — origem disponível na própria marcação (`dispositivo_id=afd:N`).
    */
    'importacoes_afd' => [
        'retention_years' => 2,
        'base_legal'      => 'Arquivo bruto re-importável; marcações derivadas em append-only 5 anos',
        'hard_delete'     => true,
        'notes'           => 'Arquivos físicos em storage/app/private/afd/* podem ser movidos pra cold storage. '
                           . 'Entry Importacao (metadata) fica 2 anos pra auditoria de re-imports.',
    ],

];
