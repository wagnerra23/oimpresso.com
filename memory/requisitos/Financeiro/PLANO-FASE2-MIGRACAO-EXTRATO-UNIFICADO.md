---
id: requisitos-financeiro-plano-fase2-migracao-extrato-unificado
---

# Fase 2 — Migração de dados: unificar extrato OFX → tabela canônica

> **Origem:** [ADR 0236](../../decisions/0236-extrato-conciliacao-modelo-unificado.md) (aceita 2026-05-31).
> **Pré-requisito:** Fase 1 em produção ✅ (conciliação já lê as 2 origens — 2026-06-01).
> **Escopo:** migrar as linhas de `fin_bank_statement_lines` (OFX) PARA
> `fin_extrato_lancamentos` (canônica), unificando a chave de dedupe, e reapontar
> o `upload()` OFX pra gravar na tabela canônica. A tabela antiga vira read-only.
> **Status:** CODIGO IMPLEMENTADO (2026-06-01) — Wagner deu o sinal (ADR 0105). Migrations + command financeiro:backfill-extrato-ofx + Pest (8 passed) prontos. O backfill em PRODUCAO ainda e gate humano (canary biz=1 -> biz=4). Fundacao codavel entregue.

---

## ⚠️ Por que esta fase é a arriscada

Diferente da Fase 1 (aditiva, zero migração de dado), a Fase 2 **move dado em
produção financeira com cliente real** (biz=4 Larissa). Os princípios:

- **Append-only + LGPD** ([retention.php](../../../Modules/Financeiro/Config/retention.php)):
  extrato tem retenção 5 anos (CTN Art. 195) + audit trail. Backfill **preserva**
  `created_at` e a trilha — nunca apaga-e-recria.
- **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)):
  backfill por `business_id`, jamais cross-tenant.
- **Reversível**: tabela antiga NÃO é dropada nesta fase (só read-only). Drop fica
  pra Fase 3 (ADR de deprecação dedicada).
- **Sinal qualificado** ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)):
  só executar se houver cliente com banco conectado **e** que sobe OFX do mesmo banco
  (risco de duplicata cross-origem real), OU métrica detectando o drift.

---

## O problema que a Fase 2 resolve

Hoje (pós-Fase 1) as 2 origens **convivem** mas em tabelas separadas:
- Mesma transação pode entrar 2× (OFX + API) porque as chaves de dedupe não se
  enxergam — `(business_id, fitid)` vs `(conta_bancaria_id, idempotency_key)`.
- 2 tabelas, 2 models, 2 regras de manutenção.

A Fase 2 colapsa tudo numa tabela só com **uma** chave anti-duplicata.

---

## Decisões de desenho

### DD-1 — `external_id` unificado (a parte mais delicada)
A ADR define UNIQUE `(business_id, conta_bancaria_id, external_id)`. O `external_id`
precisa servir as 2 origens **sem colidir**. Estratégia: **prefixo por origem**.
- OFX: `external_id = "ofx:" . fitid`
- API: `external_id = "api:" . idempotency_key`

Assim, mesmo que um FITID e um idempotency_key sejam numericamente iguais, os
prefixos os separam. **Caveat de produto** (decisão Wagner): se a intenção é que
a MESMA transação vinda de OFX e de API seja deduplicada entre si, o prefixo
**impede** isso (elas teriam external_id diferente). Se for esse o objetivo,
precisamos de uma chave derivada do conteúdo (ex. hash de `valor+data+contraparte`)
— mais complexo e sujeito a falso-positivo. **Recomendação:** começar com prefixo
(conservador, nunca funde linhas distintas), e tratar dedup cross-origem como
feature separada se virar sinal real.

### DD-2 — `conta_bancaria_id` NOT NULL no alvo
OFX hoje aceita `conta_bancaria_id` NULL (quando o upload não detecta a conta).
A tabela canônica tem FK NOT NULL. Tratamento: criar **1 conta "OFX genérica" por
business** (tipo_conta `ofx_avulso`) pra abrigar linhas OFX sem conta detectada,
OU exigir seleção de conta no upload (mudança de UX). **Recomendação:** conta
genérica por business (não quebra o fluxo atual de upload).

### DD-3 — Backfill idempotente, em lote, por business
- `insertOrIgnore` na canônica (respeitando o novo UNIQUE) — re-rodar é no-op.
- Preserva `created_at` original (não usa `now()`).
- Mapeia campos: `data_movimento`→`data`, enum `tipo` OFX (credit/debit/fee/transfer)
  → `C`/`D` (fee/transfer viram D/C conforme sinal), `valor` 15,4 → 15,2 (cuidado
  com truncamento — validar que não há centavo perdido).
- Em chunks de 500, por `business_id`, com log de quantas migraram vs puladas.
- Workflow de conciliação (`status`/`titulo_id`/`match_score`/`conciliado_by`/
  `conciliado_at`) já existe na canônica (Fase 1) — backfill carrega junto.

### DD-4 — Feature flag controla a leitura/escrita
- Flag `financeiro.extrato_unificado` (off por padrão).
- **Off**: comportamento Fase 1 (lê as 2 tabelas).
- **On**: `upload()` grava na canônica; leitura só da canônica; `fin_bank_statement_lines`
  read-only.
- Permite ligar em biz=1 (canary), observar, depois biz=4.

### DD-5 — Tabela antiga read-only (não drop)
- Após backfill + flag on, `fin_bank_statement_lines` não recebe mais escrita.
- Guard no model/Observer: bloqueia INSERT (DomainException) com mensagem clara.
- Drop real = Fase 3 (ADR de deprecação, após período de observação).

---

## Mudanças por arquivo (escopo)

1. **Migration** — adiciona `origem`/`source_file`/`external_id` em
   `fin_extrato_lancamentos` + cria UNIQUE `(business_id, conta_bancaria_id, external_id)`.
   Idempotente. (Nota: `status` etc. já entraram na Fase 1.)
2. **Command** `financeiro:backfill-extrato-ofx` — `--business=` obrigatório (Tier 0),
   `--dry` default, lote 500, log por business. Idempotente.
3. **`ConciliacaoController`** — atrás da flag: `upload()` grava na canônica;
   `index`/`sugerirMatches`/`match`/`ignorar` leem só canônica quando flag on.
4. **`BankStatementLine` model / Observer** — guard read-only quando flag on.
5. **Conta OFX genérica** — seeder/migration cria 1 por business (DD-2).
6. **Flag** `financeiro.extrato_unificado` no sistema de feature flags.

---

## Testes (Pest) — antes de qualquer canary

- Backfill idempotente: rodar 2× não duplica (UNIQUE).
- Backfill preserva `created_at` + workflow status.
- `external_id` com prefixo não colide (FITID == idempotency_key numérico → 2 linhas).
- Tier 0: backfill `--business=A` não toca linhas de B.
- Valor 15,4 → 15,2 sem perda (ou documenta arredondamento).
- Flag off = comportamento Fase 1 intacto (regressão).
- Flag on = upload grava na canônica + antiga read-only (DomainException no insert).
- Conta OFX genérica criada por business (DD-2).

---

## Roteiro de execução (gate Wagner em cada marco)

1. **Gate 0** — Wagner aprova este plano + confirma DD-1 (prefixo vs hash de conteúdo).
2. Migration + command + flag + testes (PR isolado, CI verde).
3. **Canary biz=1**: backfill dry → real → liga flag → observa 1 ciclo (sync 07:00 +
   upload manual). Métrica: zero duplicata, conciliação funcionando.
4. **Gate 1** — Wagner aprova ir pra biz=4 após biz=1 limpo.
5. **biz=4** (Larissa): backup → backfill dry → real → flag on → smoke + observa.
6. **Gate 2** — período de observação (ex. 30d) antes de marcar antiga deprecável (Fase 3).

---

## Riscos & mitigação

| Risco | Mitigação |
|---|---|
| Backfill corrompe/perde dado | dry-run obrigatório + `insertOrIgnore` + preserva created_at + tabela antiga intacta (read-only, não drop) |
| Duplicata cross-origem não resolvida | DD-1 conservador (prefixo) — nunca funde linhas distintas; dedup real é feature separada |
| Truncamento 15,4→15,2 | teste de valor + log de divergência; se houver centavo, escala Wagner |
| Tier 0 | `--business=` obrigatório + teste cross-tenant |
| Flag liga cedo demais | off por padrão + canary biz=1 antes de biz=4 |
| Prod financeira | backup pré + maintenance + smoke (via deploy.yml, igual Fase 1) |

## O que a Fase 2 NÃO faz
- ❌ Não dropa `fin_bank_statement_lines` (Fase 3).
- ❌ Não unifica a UI numa tela só (Fase 3).
- ❌ Não muda o algoritmo de match (segue MVP valor+data±3d).
- ❌ Não resolve dedup cross-origem por conteúdo (feature separada, se virar sinal).

## Estimativa
Codável com IA-pair: migration + command + flag + testes ≈ 1-2 sessões. O relógio
real está nos **gates humanos**: canary biz=1 (observar ≥1 ciclo) + biz=4 (observar
≥30d antes da Fase 3). Sem atalho nesses prazos — é dinheiro real.
