---
date: "2026-07-29"
hour: "13:33 UTC"
topic: "Etapa E2 da deprecação do Modules/SRS — PHPDoc @deprecated nas 21 classes, sem tocar comportamento"
authors: [C]
outcomes:
  - "PR #5019 MERGED (squash 9f31b69411) — 93 SUCCESS + 2 SKIPPED, 0 falhas"
  - "21 classes marcadas (o plano de maio estimava 15) — contagem medida, não herdada"
  - "Diff 100% aditivo: 118 inserções, 0 remoções, 0 linhas fora de docblock"
related_adrs:
  - 0357-deprecar-srs-sucessor-kb-jana-governance
---

# E2 do SRS — a etapa que só escreve comentário, e o que ela ainda assim revelou

**TL;DR** — Executada a etapa **E2** do [DEPRECATION-PLAN do SRS](../requisitos/SRS/DEPRECATION-PLAN.md): `@deprecated` em cada Controller, Service e Entity de `Modules/SRS/`, apontando o sucessor canônico de cada uma. [PR #5019](https://github.com/wagnerra23/oimpresso.com/pull/5019) mergeado em `9f31b69411`. Zero mudança de comportamento — e isso foi **provado**, não afirmado. Três divergências em relação ao plano ficaram registradas.

## O que foi feito

`@deprecated since 2026-07-29 (ADR 0357)` em **21 classes**, cada uma citando o sucessor e o item do plano:

| Grupo | Classes | Sucessor apontado |
|---|---|---|
| Acervo / busca | `IngestController` · `InboxController` · `MemoryReader` · `DocSource` · `DocEvidence` | `Modules\KB` |
| Chat sobre corpus | `ChatController` · `ChatAssistant` · `DocChatMessage` | `Modules\Jana` |
| Validação / drift | `DocValidator` · `ModuleAuditor` · `ModuloController` · `DocValidationRun` · `DocRetentionCleaner` | `Modules\Governance` + `mcp_audit_log` |
| Já absorvido | `MemoriaController` | `Modules\KB\Http\Controllers\MemoriaController` |
| Consolidar | `RequirementsFileReader` | `App\Services\ModuleRequirementsGenerator` |
| Descontinuar (sem sucessor) | `DashboardController` · `DataController` · `InstallController` · `DocPage` | — |
| **EM ABERTO até E3** | `DocRequirement` · `DocLink` | a 0357 não decide |

## O ponto de método: "não muda comportamento" é afirmação verificável

Numa etapa que só escreve comentário, a tentação é declarar segurança por inspeção. Foi medido:

- **Diff**: `118 insertions(+)`, **0 deletions**, e **0 linhas adicionadas fora de docblock** (contadas, não olhadas).
- **Tokens de risco** nas linhas novas (`withoutGlobalScopes` / `business_id` / `mock`): **0** — comentário que mencione esses termos pode acordar gate que varre fonte.
- **Lint real** no CT 100 (`oimpresso-staging`, PHP 8.4.22), com os 21 arquivos empacotados e enviados por stdin, **sem tocar o checkout do container** (que está em 07-23 com alterações de outra sessão):

```
php -l nos 21 arquivos          → 21× "No syntax errors detected"
controle NEGATIVO ('{' aberto)  → exit=255   (o lint morde)
controle POSITIVO (DocSource)   → exit=0
```

O `21` é **contagem de sucessos**, não ausência de falhas — e o controle negativo prova que o instrumento reprova quando deve. Sem ele, um `grep -v "No syntax errors"` vazio significaria tanto "tudo passou" quanto "o php nem rodou" (LC-13).

## Divergências entre o plano e a árvore

1. **São 21 classes, não 15.** O plano de maio estimou 15; a contagem real é **8 Controllers + 6 Services + 7 Entities**. **Console Commands (9)** e **FormRequests (4)** ficaram de fora — o escopo declarado da E2 é Controller/Service/Entity. Se forem desejados, é 1 PR curto.
2. **`DocRequirement` e `DocLink` não ganharam sucessor inventado.** A ADR 0357 diz literalmente que o destino deles fica **em aberto até E3**. O docblock registra a hipótese do plano (ARCHIVE) *marcada como hipótese*, e não como decisão — anti-padrão inventado em doc canônico é pior que ausente, porque parece canon.
3. **R9 do plano (merge conflict ativo no `CHANGELOG.md`) já não existe** — 0 marcadores. Foi resolvido entre maio e hoje; o plano segue afirmando que bloqueia E1.

## Achados que NÃO foram consertados aqui (e por quê)

- **`memory/requisitos/SRS/BRIEFING.md` está stale**: ainda diz *"deprecação PLANEJADA … mas NÃO executada"*, o que deixou de ser verdade quando a E1 mergeou em 2026-07-29. O plano põe o update final do BRIEFING em **E6**.
- **`charter_adr: 0080` errado no `SCOPE.md`** — a própria 0357 já registrou como correção de **E4**.

Nos dois casos, consertar agora significaria tocar arquivo legado sob glob de gate diff-aware por conveniência, sem pagar a dívida que o toque acorda (lápide §5 2026-07-12 + emenda 2026-07-27).

## Nota de método sobre o merge

Eu ia relatar *"mergeei"*. O `gh pr merge` respondeu **"already merged"**: o [W] tinha habilitado **auto-merge às 13:22:07Z**, e o GitHub mergeou sozinho às **13:31:56Z** quando o último check ficou verde. `mergedBy` e `autoMergeRequest` desmentiram a atribuição antes de ela virar texto. É a mesma disciplina de sempre — quem executou a ação é fato do sistema, não do comando que eu disparei.

## Estado do ambiente no início

O worktree estava **38 commits atrás de `origin/main`** e 7 à frente numa branch de sessão anterior (`claude/great-shaw-b24f35`, não publicada). Trabalhei a partir de `origin/main` fresco em branch nova; os 7 commits seguem no ref local, nada perdido.
