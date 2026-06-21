---
date: "2026-06-21"
time: "12:14 BRT"
slug: 2026-06-21-1214-auditoria-maturidade-checkl-adr0297
tldr: "Auditoria multi-agente de maturidade do processo (3 dossiês: knowledge-arch 82%, session-handoff 76%, governança SDD 68%; meta-achado 'mede tudo, governa nada'). Resolvido o deadlock que travava ratificar ADRs legacy: ADR 0297 + exceção append-only no gate (corpo byte-idêntico sob label) #3139, depois migração+ratificação de 0123/0124/0189 #3141 → débito do Check L 'legacy' 3→0."
decided_by: [W]
cycle: CYCLE-08
prs: [3139, 3141]
related_adrs:
  - 0297-excecao-append-only-migracao-legacy-frontmatter-adr
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0257-adr-status-lifecycle-kind-modelo-canonico
  - 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico
next_steps:
  - "Migrar/ratificar os ADRs legacy remanescentes (0122, 0125, 0126 — TARGETS órfãos do fix_adr_legacy_schema.py) usando a nova exceção 'adr-legacy-schema-migration'."
  - "Atacar os P0 cruzados das 3 auditorias (Onda 1): dívida de segredos em claro + gitleaks/history-scan; sentinela transporte CT100→main; refutador-no-baseline."
  - "Decidir destino do fix_adr_legacy_schema.py (aposentar — substituído pela exceção sancionada do gate)."
---

## Estado MCP no momento

- **Cycle:** CYCLE-08 "Receita — Onda A" (2026-05-31→06-28, 75% decorrido, 7d restantes). Esta sessão foi **off-cycle** (governança/processo, não goal de receita).
- **my-work:** 30 tasks (7 review / 8 blocked / 15 todo) — nenhuma diretamente ligada a este trabalho de governança.
- **origin/main:** `08c4a8fa21` (pós-#3141).

## O que aconteceu

Começou com "tem tokens livres?" → escolha de **auditoria multi-agente de maturidade do processo**. Fan-out de 3 auditores (`audit-research-expert`) em paralelo:

| Área | Nota | Decisão |
|---|---|---|
| Knowledge-architecture | 82% | CONSOLIDAR (+1 vetor EVOLUIR: recall semântico/temporal) |
| Session-handoff | 76% | CONSOLIDAR |
| Governança SDD | 68% | CONSOLIDAR |

**Meta-achado consolidado:** o processo **mede em estado-da-arte mas o último elo (enforcement/transporte/honestidade-declarada) é frouxo** — "mede tudo, governa nada". Gaps cruzados (atacar primeiro): segredos em claro no git; transporte CT100→main quebrado (trava SDD floor **e** deixou MCP 19d stale); baselines que engolem regressão; recall sem camada semântica.

Em seguida, fechamos o débito do **Check L** (ADR vivo-mas-proposto) para os 3 ADRs legacy que sobraram. **Descoberta:** havia um **deadlock real** — `block-adr-edits` (append-only) só libera `status/lifecycle/kind/authority`, mas mudar só o status faz o `memory-schema-gate` reprovar por faltarem 6 campos canônicos; adicioná-los é bloqueado. Evidência de que já travou alguém: `scripts/fix_adr_legacy_schema.py` órfão, TARGETS `0122..0126`, nunca landou.

[W] escolheu **emendar o gate** (caminho sistêmico). Entreguei em 2 PRs:
- **#3139** — ADR **0297** (emenda operacional de 0257) + nova exceção `legacymig` no gate, sob label `adr-legacy-schema-migration`, que **verifica corpo byte-idêntico** (a decisão é imutável; só a etiqueta migra) e libera o rename de frontmatter legacy→canônico. MERGED.
- **#3141** — migração de **0123/0124/0189** (legacy→canônico) + ratificação proposto→aceito (os 3 são realizados: trait `HasArquivos` usado por 4+ módulos; skill `curador`; PageHeader v3.1 aplicado+emendado por 0190). Passou pelo gate via a exceção (prova end-to-end). MERGED.

## Artefatos gerados

- `memory/decisions/0297-excecao-append-only-migracao-legacy-frontmatter-adr.md` (99 linhas, canon, status aceito) — via #3139.
- `.github/workflows/governance-gate.yml` — step `legacymig` + condição de bloqueio atualizada (#3139).
- `memory/decisions/{0123,0124,0189}-*.md` — frontmatter migrado, status aceito (#3141).
- `memory/sessions/2026-06-21-arte-{knowledge-architecture,session-handoff,governanca-sdd}.md` — os 3 dossiês de auditoria (~60KB), incluídos NESTE PR de fechamento.
- Label `adr-legacy-schema-migration` criado no repo.

## Persistência

- **git:** #3139 + #3141 já em `main`. Este handoff + dossiês neste PR de fechamento.
- **MCP:** webhook GitHub→MCP propaga ~2min após push (0297 fica buscável via `decisions-search`).
- **BRIEFING:** N/A — sessão de governança, nenhum módulo de produto teve capacidade alterada.

## Próximos passos pra retomar

```
/continuar    # ou: decisions-search "0297 excecao legacy"; gh pr view 3139; gh pr view 3141
```
Retomada natural: aplicar a exceção 0297 aos ADRs legacy restantes (0122/0125/0126) OU atacar a Onda 1 dos gaps cruzados das auditorias.

## Lições catalogadas

- **Deadlock append-only × schema:** migrar frontmatter de ADR legacy NÃO passa pelos gates sem uma exceção dedicada. A solução canônica agora é o label `adr-legacy-schema-migration` (corpo byte-idêntico). Não tentar rename à mão sem o label — `block-adr-edits` bloqueia 13 linhas "bad".
- **`pull_request` roda o workflow da branch base:** PR que depende de mudança de gate só passa **depois** do PR do gate mergear no main (por isso 2 PRs sequenciais, não 1).
- **Validar local replica do CI:** schema via AJV/2020 (deps ausentes no worktree fresh → validei a lógica equivalente em Python); índice de ADR via `adr-index-generate --check`; corpo-idêntico via o mesmo `awk` do gate. Zerou loop de CI.
- **TZ no git-bash Windows não converte:** `TZ=America/Sao_Paulo date` devolveu UTC; usar `date -u` e subtrair 3 pra BRT real.

## Pointers detalhados

- Consolidação das 3 auditorias + scorecard: ver os 3 `memory/sessions/2026-06-21-arte-*.md`.
- Racional completo da exceção + alternativas rejeitadas (supersede / só-no-leitor / ampliar allowlist): ADR 0297, seção "Alternativas consideradas".
- Lógica do gate: `.github/workflows/governance-gate.yml` job `block-adr-edits`, step `legacymig`.
