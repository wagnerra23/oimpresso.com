---
slug: 0236-extrato-conciliacao-modelo-unificado
number: 236
title: "Extrato bancário + Conciliação: modelo unificado (origem como atributo, conciliação como camada)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-31"
proposed_at: "2026-05-31"
module: financeiro
quarter: 2026-Q2
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
tags: [financeiro, extrato, conciliacao, open-finance, ofx, modelo-de-dados, lgpd, multi-tenant]
pii: false
review_triggers:
  - segundo_cliente_pede_conciliar_extrato_api
  - duplicata_cross_origem_detectada_em_prod
  - reranker_match_conciliacao_evolui_alem_mvp
---

# ADR 236 — Extrato bancário + Conciliação: modelo unificado

> **Status: ACEITO** (Wagner, 2026-05-31). Desenho-alvo + plano faseado aprovados.
> A **Fase 1 já foi implementada e testada** (5+4 testes Pest verdes, net-zero no
> dev DB) — aguarda só decisão de commit/deploy. **Fases 2-3 (migração de dado +
> deprecação) seguem com gate Wagner em cada uma** — migração atrás de feature
> flag + canary biz=1→biz=4, conforme o plano abaixo. Aceitar esta ADR autoriza o
> desenho e a Fase 1; NÃO autoriza migração destrutiva sem novo aval por fase.

## Contexto

Hoje existem **dois mundos paralelos** pra "linha de extrato bancário", separados
pela **origem do dado** em vez de pela **responsabilidade**:

| | Extrato (API) | Conciliação (OFX) |
|---|---|---|
| Rota | `/financeiro/extrato/{contaId}` | `/financeiro/conciliacao` |
| Tabela | `fin_extrato_lancamentos` | `fin_bank_statement_lines` |
| Entrada | `SyncBankStatementsJob` (Inter, cron 07:00) + `PluggyBankSyncService` | upload manual OFX (`ConciliacaoController::upload`) |
| Natureza | espelho read-only do banco | ferramenta de trabalho (bater com `fin_titulos`) |
| Status/workflow | ❌ nenhum | ✅ pendente→sugerido→conciliado→ignorado |
| Anti-duplicata | UNIQUE `(conta_bancaria_id, idempotency_key)` (id da transação do banco) | UNIQUE `(business_id, fitid)` (`unique_fitid_per_biz`) |
| Audit trail + LGPD | ✅ `LogsActivity` + retenção 5 anos ([retention.php](../../Modules/Financeiro/Config/retention.php) bloco `extratos`) | ❌ ausente |
| Schema `tipo` | `char(1)` C/D | `enum` credit/debit/fee/transfer/unknown |
| Schema `valor` | `decimal(15,2)` | `decimal(15,4)` |
| Dono (módulo) | escrita em `RecurringBilling`, leitura em `Financeiro` | `Financeiro` |

**A separação está no eixo errado.** "De onde veio" (API vs arquivo) é um *atributo*
de uma linha de extrato — não justifica duas tabelas, dois models, duas telas, duas
regras de dedupe. O que **de fato** difere é o *dado bruto do extrato* (uma coisa) vs
o *processo de conciliação* (outra coisa, que vive em cima do primeiro).

Consequências concretas dessa divisão hoje:

1. **🔴 Funcionalidade pela metade pro cliente.** Quem usa o banco conectado (Inter,
   via API) vê o extrato lindo mas **não consegue conciliar** — a conciliação só
   enxerga `fin_bank_statement_lines` (upload OFX). As duas metades não se falam.
2. **🔴 Risco de transação em dobro.** Cliente com conta Inter conectada *e* que sobe
   o OFX do mesmo banco grava a mesma transação nas duas tabelas — chaves de dedupe
   distintas não se enxergam. Pode parecer recebimento duplicado.
3. **🟡 Duas regras de "não duplicar" pra manter.** O bug de race condition no upload
   OFX (check-then-insert → 500) existia **só de um lado**; o sync API já era
   idempotente. Quem entrar no time conserta um e esquece o outro.
4. **🟡 Audit/LGPD assimétrico.** O lado API tem trilha + retenção declarada; o lado
   OFX não — apesar de OFX ser igualmente "base de conciliação tributária".
5. **🟡 Schema quase-igual diverge** (`C/D` vs enum; `15,2` vs `15,4`) — sincronizar
   é trabalho manual e fonte de erro silencioso.

**Restrições duras que qualquer solução precisa respeitar:**

- **Produção com cliente real** (biz=4 ROTA LIVRE / Larissa). Migração de tabela
  financeira = risco máximo (dinheiro real).
- **Append-only + LGPD** ([retention.php](../../Modules/Financeiro/Config/retention.php)):
  extrato tem retenção 5 anos (CTN Art. 195) + audit trail. Migração **preserva** —
  não apaga-e-recria.
- **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)):
  backfill respeita `business_id`, jamais cross-tenant.
- **Cruza fronteira de módulo** (RecurringBilling ↔ Financeiro): decidir dono é
  arquitetura.
- **Conciliação ainda é MVP**: match fuzzy é `valor + data ±3 dias`. Unificar agora
  não pode petrificar um desenho que vai evoluir.

## Decisão

**Adotar um modelo onde a ORIGEM do dado é um ATRIBUTO de uma única entidade de
extrato, e a CONCILIAÇÃO é uma CAMADA por cima — não uma tabela paralela.**

### Modelo-alvo (canônico)

1. **Uma entidade de linha de extrato** — `fin_extrato_lancamentos` é a sobrevivente
   (já tem audit + LGPD + é a mais antiga/robusta). Ganha:
   - `origem` enum `api` | `ofx` | `manual` (NOT NULL, default `api`) — substitui a
     separação física por tabela.
   - `source_file` nullable (preenchido quando `origem = ofx`).
   - `external_id` (renomeia/abriga o `fitid` do OFX e o `idempotency_key` da API sob
     um nome único) — a chave anti-duplicata passa a ser
     **UNIQUE `(business_id, conta_bancaria_id, external_id)`** servindo as duas origens.
     `conta_bancaria_id` vira NOT NULL no modelo-alvo (OFX hoje aceita null — ver Fase 2).

2. **Conciliação vira camada fina** sobre a linha de extrato:
   - Campos de workflow (`status`, `titulo_id`, `match_score`, `conciliado_by`,
     `conciliado_at`) passam a viver **na própria linha** OU numa tabela-satélite
     `fin_extrato_conciliacao (extrato_lancamento_id, ...)` (1:1). **Decisão de
     implementação deferida pra Fase 2** — recomendação: campos na própria linha
     (menos JOIN, workflow é 1:1 com a linha), satélite só se o histórico de
     re-conciliação virar requisito.

3. **`fin_bank_statement_lines` é deprecada** após o backfill (mantida em modo
   leitura/arquivo durante a transição; drop só em ADR de deprecação dedicada, pós-canary).

4. **Dono canônico: `Modules/Financeiro`.** Extrato + conciliação são domínio
   financeiro. `RecurringBilling` continua *produzindo* linhas (sync Inter) mas
   escreve no model do Financeiro (já é assim hoje — `SyncBankStatementsJob` usa
   `Modules\Financeiro\Models\ExtratoLancamento`).

5. **Uma tela** `/financeiro/extrato/{contaId}` que mostra o extrato (de qualquer
   origem) **com a coluna/ação de conciliação embutida**. `/conciliacao` vira a
   visão "fila de pendências de conciliação" (cross-conta) sobre a mesma tabela, ou
   é absorvida — decisão de UX deferida pra Fase 3.

### Plano faseado (ordem obrigatória — cada fase tem gate Wagner)

- **Fase 0 — esta ADR.** Aprovação do desenho. Zero código de produção.

- **Fase 1 — Conciliação enxerga a API (sem migrar dado).** Fazer
  `ConciliacaoController` (e `sugerirMatches`) lerem **também** `fin_extrato_lancamentos`,
  unificando a leitura numa view/query. Resolve a dor #1 (cliente Inter passa a
  conciliar). **Nenhuma migração destrutiva.** Risco baixo, valor alto.

- **Fase 2 — Migração de dados (atrás de feature flag).** Adicionar colunas (`origem`,
  `source_file`, `external_id`) em `fin_extrato_lancamentos`; backfill idempotente de
  `fin_bank_statement_lines` → `fin_extrato_lancamentos` respeitando `business_id` e
  preservando `created_at`/audit; reapontar `upsertOfx` pra nova tabela. Canary
  **biz=1 primeiro**, depois biz=4. `fin_bank_statement_lines` vira read-only.

- **Fase 3 — Unificação de UI.** Uma tela só. Deprecação formal da tabela antiga (ADR
  dedicada de deprecação, append-only).

## Justificativa

**Por que origem-como-atributo, não duas tabelas:** a "origem" não muda a natureza do
dado (é uma linha de extrato com data/valor/tipo/contraparte em ambos os casos). O
único motivo de hoje serem duas tabelas é histórico — nasceram em sprints diferentes
(US-RB-046 Open Finance vs Onda 19 Conciliação OFX), não por design.

**Por que `fin_extrato_lancamentos` sobrevive (e não a outra):** já tem audit trail
(`LogsActivity`), retenção LGPD declarada (5 anos, CTN Art. 195), idempotência madura,
e é a que recebe o fluxo automático (mais volume). Migrar OFX → ela é "subir o nível"
do dado OFX; o contrário rebaixaria o dado API.

**Por que faseado e não big-bang:** é produção financeira com cliente pagante. ADR 0105
(cliente como sinal) + a regra de canary do projeto exigem mudança incremental
validada. A Fase 1 entrega o valor central (cliente Inter conciliando) **sem tocar em
um byte de dado existente** — então a parte arriscada (migração) pode esperar sinal
real e ser feita com calma.

**Quando reabrir:** se um 2º cliente pedir conciliar extrato de API (acelera Fase 1);
se duplicata cross-origem aparecer em prod (acelera Fase 2); se o match de conciliação
evoluir além do MVP (pode mudar onde os campos de workflow moram).

## Consequências

**Positivas:**
- Cliente com banco conectado passa a conciliar (a partir da Fase 1).
- Uma regra de dedupe, um model, uma trilha de audit, uma política LGPD.
- Fim do risco de transação-em-dobro entre origens.
- Manutenção: um lugar pra "extrato bancário" em vez de dois.

**Negativas / Trade-offs:**
- Esforço real de migração (Fase 2) com risco em prod — mitigado por flag + canary.
- `external_id` precisa de estratégia que sirva FITID *e* id-de-transação sem colidir
  (provável: prefixo por origem, ex. `ofx:<fitid>` / `api:<idTransacao>`).
- Durante a transição (Fases 1-2) o código convive com as duas tabelas — complexidade
  temporária.
- `conta_bancaria_id` NOT NULL no alvo exige tratar OFX sem conta detectada (hoje
  aceita null) — provável "conta OFX genérica" por business ou exigir seleção no upload.

**Riscos mitigados:**
- Migração destrutiva → backfill idempotente + tabela antiga read-only, não drop.
- Cross-tenant → backfill com `business_id` explícito por linha (Tier 0).
- Perda de audit/LGPD → tabela sobrevivente já é a que tem audit; backfill preserva
  `created_at`.
- Petrificar desenho MVP → onde os campos de conciliação moram fica deferido pra Fase 2.

## Referências

- [ADR 0093 — Multi-tenant isolation Tier 0](0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição v2](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0105 — Cliente como sinal](0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0143 — FSM Pipeline append-only](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [retention.php — política LGPD Financeiro](../../Modules/Financeiro/Config/retention.php)
- Código: `Modules/Financeiro/Http/Controllers/ConciliacaoController.php`,
  `Modules/Financeiro/Http/Controllers/ExtratoController.php`,
  `Modules/RecurringBilling/Jobs/SyncBankStatementsJob.php`,
  `Modules/Financeiro/Services/Integrations/PluggyBankSyncService.php`
