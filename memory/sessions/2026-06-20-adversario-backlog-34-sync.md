---
title: "Adversário — batch backlog 34 (PR #3090) + sync git→DB das 22 US plano-perdido"
date: 2026-06-20
topic: "Auditoria adversarial do batch backlog 34 + sync git→DB (PR #3090)"
owner: claude
created: "2026-06-20"
type: session
---

# Adversário — batch backlog 34 / PR #3090 / sync git→DB

> Auditoria adversarial das 7 claims da sessão de admissão das 22 US "plano-perdido".
> Método: verificar estado REAL (git, gh CLI, MCP `tasks-*`, código `TaskParserService`) — não o plano.
> TL;DR: o ataque-mãe (claim 1 "a sync vai funcionar") **falhou** — a sync **já rodou e funcionou**: as 22 US estão vivas no DB com `source sha=ad95f06a16`. PR mergeado. Veredito **YELLOW** (mergeia, já mergeou — mas há 1 risco real a verificar + 2 melhorias).

---

## Veredito: 🟡 YELLOW

**A sync FUNCIONA e JÁ MATERIALIZOU as 22 US.** Não há defeito que invalide o merge. Mas há **1 P1** (possível sobrescrita silenciosa de um `US-RB-052` pré-existente criado por wagner 2026-05-16) que precisa de verificação humana, e **2 P2** (gate SPEC advisory vermelho por bug pré-existente de frontmatter no Sells; mapeamentos de módulo que o próprio autor marcou "confirmar").

Não é GREEN porque o P1 (RB-052) não pôde ser 100% descartado nesta sessão (busca de transcript bloqueada em modo não-supervisionado). Não é RED porque nada quebra a sync nem o merge — o estado-DB real prova que as 22 estão lá.

---

## Achados rankeados por severidade

### P0 — quebra (nenhum confirmado)

Nenhum P0. O modo de falha hipotético "EOF-append quebra o parser" foi **refutado empiricamente** (ver claim 1).

---

### P1 — risco real

#### P1-1 · `US-RB-052` pode ter sobrescrito uma task pré-existente de wagner — `tasks-detail` mostra DOIS eventos de criação · **WEAK→suspeito**

- **Claim atacada:** "No duplicates were created" (claim 2) + "client-side PR foi a decisão certa" (claim 7).
- **Evidência verificada:**
  - `mcp tasks-detail US-RB-052` → timeline tem **duas** entradas: `2026-05-16 13:41 — task criada por wagner` E `2026-06-20 23:32 — task criada por claude`. Conteúdo atual = plano-perdido (`recurring-billing-gateway-ativacao`, 109 assinaturas).
  - `git show ad95f06a1^:.../RecurringBilling/SPEC.md` → o SPEC pré-merge tinha **US-RB-050/051** (Inter PJ PIX — Martinho) mas **NÃO US-RB-052**. O PR **adicionou** `US-RB-052` (+`US-RB-055`), pulando 053/054 (consumidos por sessões concorrentes).
  - Logo: existia uma row `task_id=US-RB-052` no DB **desde 2026-05-16** (ad-hoc, criada por wagner, nunca em SPEC). Quando o webhook rodou no merge, `TaskParserService::syncAll()` achou a row por `task_id` e foi pro caminho **UPDATE** (ADR 0144: sobrescreve `title`/`description`/`labels`, preserva `status/owner/sprint/priority`). Resultado: **o título/descrição original de wagner foi substituído pelo conteúdo plano-perdido.**
- **Por que não consegui fechar 100%:** `search_session_transcripts` e leitura de `mcp_task_events` estão bloqueados em modo não-supervisionado. Não recuperei o título original da US-RB-052 de wagner. É possível (menos provável) que a entrada "wagner 2026-05-16" seja só um artefato de timeline do servidor.
- **Ação:** rodar `mcp tasks-detail US-RB-052` com acesso a events, ou `SELECT * FROM mcp_task_events WHERE task_id='US-RB-052' ORDER BY created_at` no DB, pra ver se houve um título distinto sobrescrito. Se sim → restaurar como US-RB-053+ e re-apontar.
- **Veredito:** WEAK (não confirmado como break, mas é o único candidato concreto a dano). O design ADR 0144 garante que o **estado vivo** (status/owner) foi preservado — então no pior caso é descrição perdida, não estado.

---

### P2 — melhoria

#### P2-1 · Gate "SPEC (memory/requisitos/*/SPEC.md)" vermelho — mas é **advisory** e a falha é **pré-existente**, não das 22 US · **claim "schema-valid" = FALSE; impacto = baixo**

- **Claim atacada:** "PR #3090 is clean: ... schema-valid" (claim 5).
- **Evidência verificada:**
  - `gh pr checks 3090` → "SPEC (memory/requisitos/*/SPEC.md)" = **fail**. Todos os 18 checks **required** = pass.
  - Annotation da run: `memory/requisitos/Sells/SPEC.md` → **`/last_updated must be string`**.
  - `git show ad95f06a1:.../Sells/SPEC.md` → frontmatter tem `last_updated: 2026-05-31` (sem aspas) e `version: 1.0.0` (sem aspas). `git show ad95f06a1^:.../Sells/SPEC.md` → **já estava assim antes do PR**. O PR **não tocou o frontmatter** — só anexou US no fim do arquivo, e o gate revalida o arquivo inteiro, surfando um bug latente.
  - `gh api .../branches/main/protection` → o gate SPEC schema **NÃO está na lista required** (18 contexts; SPEC não é um deles). Por isso o merge foi legítimo apesar do vermelho.
- **Veredito:** "schema-valid" é **FALSE** literalmente (o gate falhou), mas a causa é dívida pré-existente do Sells, não os blocos. Merge correto (gate advisory).
- **Ação (P2):** quotar `last_updated: "2026-05-31"` e `version: "1.0.0"` no `Sells/SPEC.md` (e varrer outros SPEC com o mesmo bug) — limpa o ruído vermelho recorrente. Candidato a chip separado.

#### P2-2 · Mapeamentos de módulo: 2 placements que o próprio autor marcou "confirmar" · **WEAK**

- **Claim atacada:** "Module mappings are correct" (claim 4).
- **Evidência verificada:**
  - `US-RB-055` (pricing) — o DB description já contém o disclaimer: "⚠️ Módulo: colocado em RecurringBilling... Se for a página pública de pricing, re-homear pra Grow/Infra." Auto-flagado.
  - `US-COPI-126` (renames Copiloto→Jana) — batch doc diz "⚠ confirmar 1º se módulo ainda é Copiloto no código". A US ficou em Jana (módulo já renomeado), descrição fala em propagar rename nos ~112 PHP. Consistente, mas o disclaimer fica.
  - SDD items `US-INFRA-038/039/040` em Infra (vs Governance): defensável — são CI/gates/SQLite, domínio Infra. Não conflita com `US-INFRA-013` (contract-test-gate, ADR 0207) nem com os SDD-gov existentes. Sem duplicata.
- **Veredito:** WEAK — placements são defensáveis; os 2 com disclaimer já carregam a nota de re-homing. Não bloqueia.

#### P2-3 · Caveat do autor "resetar working-copy do servidor pós-merge senão re-aplica" — **especulação, baixo risco real** · **WEAK**

- **Claim atacada:** caveat (a) (claim 6) + double-materialization (claim 7).
- **Evidência verificada:**
  - `SyncMemoryWebhookController::sincronizarComOrigin()` faz `git fetch origin main` + `git reset --hard origin/main` no working-copy do servidor **a cada push em main** (a menos que o push exija deploy manual — composer/migrations/build). Este PR tocou só `*.md` → reset roda.
  - Logo o working-copy do servidor é **forçado a origin/main** pelo próprio webhook — não "fica com os 22 pendentes". O `tasks-create` que rodou no working-copy do servidor escreveu markdown não-commitado que **o `git reset --hard` descarta**. E mesmo que alguém commitasse server-side depois, o `TaskParserService` é **idempotente por task_id** (acha a row, vai pra UPDATE, não duplica).
  - `IndexarMemoryGitParaDb` (o outro sync) também é idempotente por sha.
- **Veredito:** WEAK — o risco de "re-aplica → duplica" é mitigado por (1) reset --hard automático e (2) idempotência por task_id/sha. O caveat do autor é conservador-correto mas o sistema já se protege. Não há segundo caminho de materialização perigoso.

---

## Claims × veredito (resumo)

| # | Claim | Veredito | Nota |
|---|---|---|---|
| 1 | "A sync vai funcionar" | **CONFIRMED** (refuta o ataque) | Parser `TaskParserService::US_HEADING_REGEX` é section-agnostic (`preg_match_all` no body inteiro). EOF-append OK. **Prova empírica:** as 22 já estão no DB com `source sha=ad95f06a16`. Webhook dispara `syncAll()` quando `SPEC.md` muda (SyncMemoryWebhookController:120). Parser+webhook vivem NESTE repo. |
| 2 | "Nenhum duplicado criado" | **WEAK** | 22 net-new confirmados no DB (COPI 123-127, GOV 028-030, INFRA 036-040, etc, todos limpos). Gaps de ID (RB 053/054, GOV 013-027, COPI 118-122) = sessões concorrentes consumiram IDs — não provam dup de slug. **Exceção:** US-RB-052 tem evento "wagner 2026-05-16" → possível overwrite (P1-1). |
| 3 | "Dedup completo — só 4 eram US" | **CONFIRMED (com ressalva)** | Os 4 dedups declarados (NFE-040, VEST, SELL-041, INFRA-035) existem no DB. Não achei 5º duplicado claro entre os net-new. US-SELL-052 ≠ US-SELL-041; US-WA-316/317 têm overlap temático com US-WA-063/064 mas escopo distinto. |
| 4 | "Mapeamentos corretos" | **WEAK** | RB-055 e COPI-126 já carregam disclaimer de re-homing (auto-flagado). SDD→Infra defensável. (P2-2) |
| 5 | "PR clean: 8 SPEC, +380/-0, schema-valid, no PII" | **PARCIAL** | 8 SPEC ✓, exatamente 22 headings ✓, +380/-0 ✓, sem PII (PII scan pass + grep limpo) ✓, sem arquivo estranho ✓. MAS "schema-valid" = **FALSE** (gate SPEC fail por `last_updated` do Sells, pré-existente, advisory). (P2-1) |
| 6 | "Os 2 caveats são válidos" | **WEAK** | Caveat (a) reset/re-aplica: mitigado por reset --hard auto + idempotência. (P2-3) |
| 7 | "Client-side PR foi a decisão certa" | **CONFIRMED na prática** | Funcionou: materializou via o caminho canônico (ADR 0053/0070). Sem segundo caminho perigoso. |

---

## O que o autor pode não ter considerado (hunt)

- **Edits do batch doc na feature branch:** a versão em `origin/main` do `BATCH-BACKLOG-34` é a original (sem reconciliação TASK_CREATED). As edições de reconciliação do autor estão **stranded** na branch de trabalho (`feat/governance-ds-rollout-ledger` / worktree), não no PR #3090. Se importam, precisam de PR próprio.
- **Branch base stale:** PR #3090 mergeou em `ad95f06a1`, com `7e713b15c` (#3089) logo antes — base estava fresca. OK.
- **Ratchets:** "No-mock-in-prod" e "No hardcode business_id" passaram (verde no CI) apesar de US-COPI-123/124 mencionarem `startMockStream`/`business_id` no texto — os ratchets varrem código, não prosa de SPEC.md. Sem trip.
- **Auto-merge:** já disparou — PR está **MERGED** (`mergedAt 2026-06-21T00:07:19Z`, mergeCommit `ad95f06a16`). Não ficou preso em review nem em check vermelho (SPEC não é required).

---

## Comandos-chave usados (rastreabilidade)

- `gh pr view 3090 --json state,mergeCommit,autoMergeRequest` → MERGED, squash, mergeCommit ad95f06a16
- `gh pr checks 3090` → SPEC=fail, 18 required=pass
- `gh api .../branches/main/protection` → SPEC schema NÃO é required
- `gh api .../check-runs/82526214694/annotations` → `Sells/SPEC.md: /last_updated must be string`
- `git show origin/main:Modules/Jana/Services/TaskRegistry/TaskParserService.php` → regex section-agnostic, idempotente por task_id
- `Modules/TeamMcp/Http/Controllers/Mcp/SyncMemoryWebhookController.php:120` → `syncAll()` on SPEC.md change
- `mcp tasks-detail US-RB-052 / US-COPI-123 / US-RB-055` → todas vivas, source sha=ad95f06a16
- `git show ad95f06a1^:.../RecurringBilling/SPEC.md` → RB-052 NÃO existia pré-PR (RB-050/051 sim)
