---
id: requisitos-financeiro-plano-fase1-conciliacao-le-extrato-api
---

# Fase 1 — Conciliação passa a enxergar o extrato da API

> **Origem:** [ADR 0236](../../decisions/0236-extrato-conciliacao-modelo-unificado.md) (proposto 2026-05-31).
> **Escopo:** fazer a Conciliação ler **também** `fin_extrato_lancamentos` (extrato
> sincronizado via API do banco), além de `fin_bank_statement_lines` (upload OFX).
> **Promessa central:** cliente com banco conectado (Inter) passa a conciliar.
> **Invariante:** ZERO migração destrutiva. Nenhuma linha existente muda de tabela.

---

## Por que esta fase primeiro

Resolve a dor #1 da ADR 0236 (funcionalidade pela metade pra quem usa Open Finance)
com o **menor risco possível**: é só leitura unificada + escrita de status numa coluna
nova. Nada de backfill, nada de drop, nada de reapontar o fluxo automático. Se der
errado, reverte com 1 revert de PR.

---

## O problema técnico concreto

A conciliação hoje é cega pro extrato da API. Três pontos no
[`ConciliacaoController`](../../../Modules/Financeiro/Http/Controllers/ConciliacaoController.php):

1. **`index()`** lê `linhas` e `stats` **só** de `fin_bank_statement_lines`.
2. **`sugerirMatches()`** varre **só** `fin_bank_statement_lines` pendentes pra casar
   com `fin_titulos`.
3. **`match()` / `ignorar()`** dão `UPDATE` em `fin_bank_statement_lines` por `id`.

Já `fin_extrato_lancamentos` **não tem** colunas de workflow (`status`, `titulo_id`,
`match_score`, `conciliado_by`, `conciliado_at`) — é read-only puro. Esse é o único
gap de schema que a Fase 1 precisa fechar.

---

## Decisões de desenho da Fase 1

- **DD-1 — Identidade de linha na tela = `origem:id`.** Como agora vêm linhas de duas
  tabelas, o front precisa de um identificador que diga *de qual tabela* e *qual id*.
  Padrão: `"ofx:123"` e `"api:456"`. As rotas `match`/`ignorar` passam a receber esse
  par (ou ganham um sufixo `?origem=api`) em vez de só `{lineId}` numérico.
- **DD-2 — Workflow de conciliação para linhas API mora na PRÓPRIA `fin_extrato_lancamentos`.**
  Adiciona-se colunas `status`/`titulo_id`/`match_score`/`conciliado_by`/`conciliado_at`
  (todas **nullable**, default coerente). É **aditivo** — não quebra o `ExtratoController`
  read-only nem o `SyncBankStatementsJob` (que não menciona essas colunas no upsert).
  Isso já é o começo do modelo-alvo da ADR 0236 (workflow na linha), sem migrar dado.
- **DD-3 — Leitura unificada via normalização em PHP, não SQL UNION.** `index()` busca
  os dois conjuntos, normaliza pra um shape comum (`{uid, origem, data, descricao,
  valor, tipo, status, titulo_id, match_score, source_file}`) e concatena. Evita
  acoplar schemas divergentes (`C/D` vs enum; `15,2` vs `15,4`) num UNION frágil.
  Volume é baixo (LIMIT 200 hoje) — custo desprezível.
- **DD-4 — `sugerirMatches()` passa a varrer as duas origens.** Mesmo algoritmo
  (valor + data ±3 dias), agora também sobre `fin_extrato_lancamentos` com
  `status IS NULL` (ainda não tocado). Continua MVP — não evolui o match nesta fase.
- **DD-5 — Normalização de sinais.** `fin_bank_statement_lines.tipo` é
  enum credit/debit; `fin_extrato_lancamentos.tipo` é `C/D`. A normalização mapeia
  ambos pra um vocabulário só na camada de leitura. `valor`: OFX guarda sinal
  (negativo=débito); API guarda valor + `tipo` C/D — normalizar pra convenção única
  (recomendado: valor com sinal, como o OFX, derivando de `C/D`).

---

## Mudanças por arquivo (escopo fechado)

### 1. Migration aditiva (nova)
`Modules/Financeiro/Database/Migrations/2026_06_*_add_conciliacao_cols_to_fin_extrato_lancamentos.php`
- `ADD COLUMN status` nullable (`pendente|sugerido|conciliado|ignorado`, default NULL =
  "não entrou no fluxo"). **Idempotente** (`if (! Schema::hasColumn(...))`).
- `ADD COLUMN titulo_id` unsignedInteger nullable + FK `fin_titulos` `onDelete set null`.
- `ADD COLUMN match_score` decimal(5,2) nullable.
- `ADD COLUMN conciliado_by` unsignedInteger nullable.
- `ADD COLUMN conciliado_at` timestamp nullable.
- Index `(business_id, status)` pra os stats.
- `down()` dropa as 5 colunas (reversível).

### 2. `ExtratoLancamento` model
- Adiciona os 5 campos ao `$fillable` + casts (`conciliado_at` → datetime).
- Estende `getActivitylogOptions()->logOnly([...])` pra incluir `status`/`titulo_id`
  (auditar conciliação — coerente com o bloco que já audita valor/tipo/data).

### 3. `ConciliacaoController`
- `index()` → busca + normaliza as duas origens; `stats` somam as duas.
- `sugerirMatches()` → varre as duas; ao casar, grava status na tabela certa.
- `match()` / `ignorar()` → resolvem `origem` (DD-1) e dão UPDATE na tabela certa,
  **mantendo o filtro `business_id`** (Tier 0) em ambos os caminhos.
- `upload()` permanece **idêntico** (o fix de dedupe da sessão anterior fica intacto).

### 4. `Conciliacao/Index.tsx`
- Interface `Linha` ganha `uid: string` + `origem: 'ofx'|'api'`.
- Coluna nova "Origem" (chip: `OFX` / `Banco`) pra Eliana saber a procedência.
- `confirmarMatch`/`ignorar` passam `uid`/`origem` nas rotas.
- Resto do layout intacto (KPIs, upload, busca, footer canon).

### 5. Rotas
- `conciliacao/{lineId}/match` e `.../ignorar` aceitam o identificador composto
  (ou ganham `?origem=`). Manter retrocompat com o `{lineId}` numérico puro (OFX).

---

## Testes (Pest) — estende o que já existe

Reaproveitar o padrão de `ConciliacaoUploadDedupeTest` (MySQL-guard + DatabaseTransactions
+ prefixo único). Novos casos:
- `index` lista linhas das DUAS origens com `stats` somados corretamente.
- `sugerirMatches` casa um `fin_extrato_lancamentos` (origem API) com um `fin_titulo`
  aberto (valor+data) e marca `status=sugerido` **na tabela do extrato**.
- `match` numa linha origem=API atualiza `fin_extrato_lancamentos` (não a do OFX) +
  respeita `business_id` (Tier 0 — não concilia linha de outro tenant).
- `ignorar` idem.
- Regressão: upload OFX + conciliação OFX continuam funcionando igual (não quebrou).

---

## Riscos & mitigação (Fase 1)

| Risco | Mitigação |
|---|---|
| Identidade composta `origem:id` bagunçar rotas | Validação estrita no controller; teste de rota por origem |
| Audit/LGPD na linha API | Colunas entram no `LogsActivity`; retenção já cobre a tabela |
| Tier 0 no novo caminho `match`/`ignorar` API | Filtro `business_id` obrigatório + teste cross-tenant |
| Divergência de sinal/tipo entre origens | Normalização central única (DD-5) + teste de valor +/- |
| Migration em prod | Aditiva + idempotente + `down()`; canary biz=1 antes de biz=4 |

## O que a Fase 1 NÃO faz (fica pra Fase 2/3 da ADR 0236)
- ❌ Não migra `fin_bank_statement_lines` → `fin_extrato_lancamentos`.
- ❌ Não unifica a chave de dedupe nem cria `external_id`.
- ❌ Não deprecia tabela nem unifica as telas.
- ❌ Não evolui o algoritmo de match (segue MVP valor+data±3d).

---

## Estimativa
Codável com IA-pair (recalibração [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)):
migration + model + controller + front + Pest ≈ 1 sessão. Gate humano: canary biz=1 →
observar 1 ciclo de sync (07:00) → biz=4.

## Pré-requisitos pra começar
1. ~~ADR 0236 sair de `proposto` → `aceito` (Wagner).~~ ✅ **Aceita 2026-05-31.**
2. ~~Confirmar a decisão DD-1.~~ ✅ **Resolvido:** rotas mantêm `{lineId}` numérico +
   `origem` no corpo do POST (retrocompat 100%, `whereNumber` preservado).

## Status de implementação (2026-05-31)
**Implementada e testada — não commitada.** Verificação net-zero contra MySQL dev:
- `ConciliacaoLeExtratoApiTest` — 5 passed (14 assertions)
- `ConciliacaoUploadDedupeTest` (regressão do fix anterior) — 4 passed
- Migration aplicada → testes → rollback; dev DB confirmado limpo (`col_status=0 bank=0`).

Pendências antes de merge: `npm run build` (front não foi buildado) + decisão de
commit/branch + canary biz=1→biz=4 (Fase 1 toca leitura/escrita-status, não migra dado).
