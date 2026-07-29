---
date: "2026-07-29"
time: "13:33 UTC"
slug: srs-e2-phpdoc-deprecated
tldr: "E2 do DEPRECATION-PLAN do SRS entregue e mergeada (#5019, 9f31b69411): @deprecated em 21 classes — não 15, como o plano estimava. Diff 100% aditivo, provado; lint no CT 100 com controle negativo. E3 segue gated por [W], e o destino de DocRequirement/DocLink continua EM ABERTO por decisão da própria 0357."
prs: [5019]
decided_by: [W]
next_steps:
  - "E3 (migração de dados: T1..T7) — gated por [W]; exige PiiRedactor re-rodado em docs_chat_messages e Pest cross-tenant biz=1 vs biz=99 ANTES e DEPOIS"
  - "[W] decide o destino de docs_requirements + docs_links — a 0357 deixou explicitamente em aberto até E3"
  - "E4: corrigir charter_adr: 0080 no SCOPE.md do SRS (a 0357 já registrou como achado)"
  - "E6: atualizar memory/requisitos/SRS/BRIEFING.md — ficou stale quando a E1 mergeou"
  - "Opcional (fora do escopo declarado da E2): @deprecated nos 9 Console Commands e 4 FormRequests do SRS"
related_adrs:
  - 0357-deprecar-srs-sucessor-kb-jana-governance
  - 0093-multi-tenant-isolation-tier-0
---

# E2 do SRS entregue — e a contagem do plano estava 6 classes curta

Continuação direta da **E1**, que a [ADR 0357](../decisions/0357-deprecar-srs-sucessor-kb-jana-governance.md) executou hoje mais cedo ([#5011](https://github.com/wagnerra23/oimpresso.com/pull/5011)). O [DEPRECATION-PLAN](../requisitos/SRS/DEPRECATION-PLAN.md) define E2 como *"docs/comments only, ~50 LOC, não muda comportamento"*.

## Estado MCP no momento do fechamento

Consultado agora (não herdado de handoff anterior):

- **`cycles-active`** → *"Nenhum cycle ATIVO em COPI"*.
- **`my-work`** → **10 tasks**, todas em `REVIEW`: `US-COPI-123` (p0, remover `startMockStream` da rota live), `US-TR-309/310/305/306/311`, `US-PG-008`, `US-PROD-027`, `US-INFRA-023`, `US-KB-002`.
- **`decisions-search query:"deprecar SRS MemCofre"`** → 4 ADRs, **nenhuma delas a 0357**; vieram ADRs históricas do MemCofre + a 0059. Registro como **observação medida**, não como diagnóstico: **não investiguei** se é lag do webhook git→MCP (a 0357 foi mergeada hoje) ou ranking do full-text.
- **`sessions-recent`** não está exposta neste token — usei `ls -t memory/sessions/` como substituto, ciente de que **não é equivalente**.
- **Nenhuma task de MCP foi criada** para a E2. Sessões irmãs de 28/07 registraram que `tasks-create` nega por falta do scope `jana.mcp.tasks.write`; **não testei** nesta sessão.

## O que entrou

[#5019](https://github.com/wagnerra23/oimpresso.com/pull/5019) **MERGED** — squash `9f31b69411`, branch deletada. CI: **93 SUCCESS + 2 SKIPPED, 0 falhas** (95 checks). `module-grades-gate`: 0 regressões, 36 módulos estáveis.

`@deprecated since 2026-07-29 (ADR 0357)` em **21 classes**, cada uma apontando o sucessor + o item do plano: **KB** (acervo/busca — `IngestController`, `InboxController`, `MemoryReader`, `DocSource`, `DocEvidence`) · **Jana** (chat — `ChatController`, `ChatAssistant`, `DocChatMessage`) · **Governance + `mcp_audit_log`** (validação — `DocValidator`, `ModuleAuditor`, `ModuloController`, `DocValidationRun`, `DocRetentionCleaner`) · **descontinuar** (`DashboardController`, `DataController`, `InstallController`, `DocPage`) · **em aberto** (`DocRequirement`, `DocLink`).

**O módulo SEGUE SERVINDO em produção.** `deprecating` ≠ removido: nada foi desativado, renomeado, movido ou removido.

## As três coisas que valem para quem pegar a E3

1. **A contagem do plano estava errada — 21, não 15.** Se a E3/E4 dimensionarem esforço pelo número do plano, vão subestimar. O denominador da E2 é **8 Controllers + 6 Services + 7 Entities**; os **9 Console Commands** e **4 FormRequests** ficaram fora do escopo declarado e **seguem sem marcação**.
2. **`DocRequirement` + `DocLink` não têm sucessor, e isso é deliberado.** A 0357 diz que o destino fica em aberto **até E3** — o docblock registra a hipótese do plano (ARCHIVE) *como hipótese*. Quem for fazer a E3 precisa de **decisão [W]**, não de leitura do docblock.
3. **R9 do plano já não existe.** O `Modules/SRS/CHANGELOG.md` tem **0 marcadores de conflito** — o plano ainda o lista como bloqueador de E1/E0. O plano é de maio e não foi reconciliado com a árvore.

## Dois achados NÃO consertados (de propósito)

- `memory/requisitos/SRS/BRIEFING.md` ficou **stale no momento em que a E1 mergeou** — ainda afirma *"deprecação PLANEJADA … mas NÃO executada"*. Update final está previsto para **E6**.
- `charter_adr: 0080` errado no `SCOPE.md` — correção prevista para **E4**, já registrada pela própria 0357.

Nos dois casos, consertar por conveniência significaria **tocar arquivo legado sob glob de gate diff-aware** sem pagar a dívida que o toque acorda (§5 2026-07-12 + emenda 2026-07-27). Ficam como próximos passos, não como dívida silenciosa.

## Evidência

Lint real no CT 100 (`oimpresso-staging`, PHP 8.4.22), com os arquivos enviados por stdin — **sem `git pull` no container**, cujo checkout está em 07-23 com alterações de outra sessão:

```
php -l nos 21 arquivos          → 21× "No syntax errors detected"
controle NEGATIVO ('{' aberto)  → exit=255   (o lint morde)
controle POSITIVO (DocSource)   → exit=0
```

Diff medido: `118 insertions(+)`, **0 deletions**, **0 linhas fora de docblock**, **0 tokens de risco** (`withoutGlobalScopes` / `business_id` / `mock`) nas linhas novas.

## Nota sobre quem mergeou

O `gh pr merge` respondeu **"already merged"**: o [W] habilitou **auto-merge às 13:22:07Z** e o GitHub mergeou às **13:31:56Z**, quando o último check ficou verde. A atribuição foi verificada em `mergedBy`/`autoMergeRequest` antes de virar texto — o merge **não** foi ato do meu comando.

## Ambiente

Worktree estava **38 commits atrás** de `origin/main` (e 7 à frente, em branch de sessão anterior não publicada). Trabalhei a partir de `origin/main` fresco em branch nova; os 7 commits seguem no ref local `claude/great-shaw-b24f35`, nada perdido.
