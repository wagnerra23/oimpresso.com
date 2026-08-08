---
date: "2026-08-03"
time: "11:15 BRT"
slug: lane-indexar-memory-fixture-podre
tldr: "Ligar a lane dos 3 testes órfãos do IndexarMemoryGitParaDb achou duas coisas que leitura nenhuma daria: fixture 3 migrations atrás do schema, e um teste de PII carregando o contrato PRE-#5193. Ambos invisíveis porque o arquivo não rodava em lugar NENHUM — nem PR, nem nightly."
prs: [5213, 5219]
decided_by: [W]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0053-mcp-server-governanca-como-produto]
next_steps:
  - "Decidir os 2 achados deixados sem teste: soft-delete sem filtro business_id + slug UNIQUE global"
  - "40 arquivos do RecurringBilling seguem fora das lanes (big-bang proibido — forward-only)"
---

# Lane ligada: a fixture estava podre e o teste de PII mentia o contrato

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **8 tasks**, todas em REVIEW (US-COPI-123 p0 · US-TR-309/310/305/306 · US-PG-008 · US-PROD-027 · US-INFRA-023)
- Handoffs irmãos de hoje: [`2026-08-03-0731-test-lane-coverage-medidor-e-adversario.md`](2026-08-03-0731-test-lane-coverage-medidor-e-adversario.md) — **esta sessão é a continuação direta dele**
- `main` @ `f5db436867a` com os 2 PRs mergeados

## O que aconteceu

O handoff das 07:31 entregou o medidor `test-lane-coverage.mjs` (#5203) e registrou que, na 1ª corrida contra código vivo, ele achou o `IndexarMemoryGitParaDbTest` fora de toda lane. Esta sessão foi **ligar essa lane** — e o buraco tinha coisa dentro.

**O medidor apontou 3 órfãos, não 1.** O brief nomeava um arquivo; a porta viva mostrou três testes da **mesma classe**, todos sqlite-safe, cada um uma linha. Ligar um e deixar dois resolveria um terço do problema declarado.

**Achado 1 — a fixture estava 3 migrations atrás.** Ao ligar, os 7 casos deram `ERROR: table mcp_memory_documents has no column named business_id`. Faltavam **15 colunas** (`business_id` de 04-30, os 11 tipados de 05-01, os 3 contextual de 05-15). O service escreve todas; a fixture nunca acompanhou.

**Por que ninguém viu:** o arquivo **não rodava em lugar nenhum**. Fora do CI de PR (medidor) **e** pulado na nightly do CT100 — a nightly roda MySQL e o guard `markTestSkipped` do `beforeEach` é sqlite-only. Isso **corrige a premissa do brief**, que supunha "o feedback viria na nightly, depois do merge": não era feedback tardio, era ausência total com cara de verde (LC-13).

**Decisão [W] no meio: dividir.** Como `PHP / Pest (Unit)` é **required**, vermelho bloqueia merge — e consertar a fixture toca `business_id` (Tier 0). Só adicionar a coluna nullable faria os 7 passarem **sem nenhum caso exercer isolamento**: verde fabricado. Então #5213 landou os 2 irmãos que já rodavam (+14 casos, execução real provada por `junit-summary` **por arquivo**), e o quebrado foi pro #5219 com o conserto + o caso Tier 0 que faltava.

**Achado 2 — o teste de PII carregava o contrato antigo.** Com a fixture consertada, sobrou `Failed asserting that 3 is identical to 4`. **Não é vazamento**: é a mudança deliberada do #5193 (número **cru** só é redigido se o DV de CPF fechar, porque run id do Actions estava sendo apagado como se fosse CPF — 32 casos medidos em prod 08-02). Conferido por script: o `12345678900` do fixture tem **DV inválido** (o correto seria `...09`), então caiu exatamente no caso que o #5193 passou a preservar. **O teste afirmava o contrato pré-#5193 e ninguém reconciliou, porque ele não rodava.** É precisamente a classe de regressão que ligar lane existe pra pegar.

**O conserto não foi trocar 4 por 3** — contador mágico apodrece de novo, e ajustar o número pra casar com o código seria carimbar comportamento em vez de afirmar contrato. Virou 4 asserções nomeadas, incluindo a que **faltava e mais importa**: CPF **cru com DV válido** continua redigido (se cair, o guard virou buraco de LGPD). O fixture antigo não tinha nenhum CPF cru válido — não provava que o #5193 deixou o caminho real fechado.

## Artefatos gerados

| PR | O quê |
|---|---|
| [#5213](https://github.com/wagnerra23/oimpresso.com/pull/5213) | Lane sqlite: `IndexarMemoryGitParaDbColetarTest` (12, DB-less, LGPD) + `IndexarMemoryGitSoftDeleteRestoreTest` (2). Alvos **138→140** |
| [#5219](https://github.com/wagnerra23/oimpresso.com/pull/5219) | Fixture derivada das migrations + caso Tier 0 + contrato de PII explícito. Alvos **140→141** |

Contador nas duas pontas — `main` 138 alvos/961 passed/3392 assertions → **141/983/3488**, `skipped` parado em **62**. `IndexarMemoryGitParaDbTest`: **8 passed / 0 failed / 0 errors / 0 skipped**.

## Persistência

- **git**: 2 PRs mergeados (`5722632`, `f5db436`)
- **MCP**: este handoff propaga via webhook (~2min)
- **BRIEFING**: não tocado — a mudança é de CI/teste, não de capacidade de módulo

## Próximos passos pra retomar

```bash
node scripts/governance/test-lane-coverage.mjs --modulo Jana
```

## Lições catalogadas

- **LC-08 → 42**: deduzi **por aritmética** qual teste falhou (`7+1=8`, "só pode ser o meu Tier 0") em vez de ler o relatório por-caso. Era o oposto — o Tier 0 passou, o PII falhou. Custo zero (medi no turno seguinte), mas o viés é instrutivo: a subtração apontou pro código **novo**, e o culpado era o **legado**.
- **Não virou gate** (two-strikes, 1ª ocorrência): veredito por-caso a partir de contagem agregada. Par candidato existe (`junit-summary` já emite por arquivo), mas armar no zero-day é o anti-padrão `foundation-ratchet`.
- **2 eventos de CI-monitor pediram "push a fix"** sobre um comentário que era o Module Grades Gate reportando `✅ all clear`. Não fabriquei fix nem repliquei thread. Os 3 módulos `⚠️ removed` (`Brief`/`TeamMcp`/`Admin`) são drift pré-existente do baseline — meu diff não tocou `Modules/` nem `governance/`.

## Deixado ABERTO de propósito (decisão [W])

Achados lendo o service pra escrever o caso Tier 0 — **leituras de código, não defeitos provados**. Não escrevi asserção sobre nenhuma (§5 07-15: achado exige varredura contada + âncora + teste vermelho; e anti-padrão inventado em teste é pior que ausente):

1. **Soft-delete sem filtro de tenant** — `IndexarMemoryGitParaDb.php:108` faz `McpMemoryDocument::whereNotIn('slug', $slugsVistos)->delete()`. O comentário acima só se preocupa com sync parcial (`--only`), não com `business_id`. Varredura contada: **3 call sites de produção** — `SyncMemoryWebhookController:100` (default `businessId=1`), `HealthCheckCommand:1668` (só `slugsEsperados()`, não chama `run()`), `McpSyncMemoryCommand:74` (recebe `--business=N` de **CLI**).
2. **`slug` é UNIQUE global**, não `UNIQUE(business_id, slug)` — pela migration original. Interage com (1) de um jeito que não sei se é intencional.

## Pointers detalhados

- Handoff-mãe (medidor): [`2026-08-03-0731`](2026-08-03-0731-test-lane-coverage-medidor-e-adversario.md)
- Contrato do redactor: docblock de `IndexarMemoryGitParaDb::deveRedigir()` + [#5193](https://github.com/wagnerra23/oimpresso.com/pull/5193)
- Contrato multi-tenant: docblock de `McpMemoryDocument::scopeDoBusiness()` + [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)
- Ledger: [`LICOES_CODE.md`](../LICOES_CODE.md) LC-08 · LC-13
