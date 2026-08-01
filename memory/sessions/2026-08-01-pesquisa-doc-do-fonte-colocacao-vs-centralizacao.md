---
date: "2026-08-01"
hour: "10:00 BRT"
duration: "~5h"
topic: "Pesquisas da doc-do-fonte: estado-da-arte colocação×centralização (Grab/Diátaxis/Spec Kit/Kiro/Swimm) + estado empírico medido de memory/ + método (LC-08). Levou à reversão da 0364 pra Opção B."
authors: [F, C]
outcomes:
  - "Estado-da-arte: colocação (Spec Kit/Kiro/Swimm) é a maré do mercado; Grab centralizou e a lição é 'consistência'; Diátaxis manda espelhar a estrutura do fonte"
  - "Empírico: 72 módulos reais · 14 sem SPEC · 31 soltos na raiz requisitos/ · ~44% frontmatter divergente · 109 scripts-máquina · 210 charters/74 casos (recibos ao lado)"
  - "Decisão [F]: reverter a 0364 (Opção A move) → Opção B (colocação) — proposal #5156 mergeada; ADR 0365 (reversão) pendente [W]"
  - "Método: 3 near-misses de duplicação/medição pegos por checar antes (LC-08) — o mais reusável desta sessão"
prs: [5152, 5156]
us: []
related_adrs: ["0364-trio-de-tela-mora-em-memory-emenda-0264", "0264-governanca-executavel-trio-dominio-e2e", "0256-knowledge-survival-meia-vida-catraca-sentinela"]
---

# Session log 2026-08-01 — pesquisas da doc-do-fonte: colocação × centralização

## TL;DR

[F] levantou *"memory bagunçada, arquivos perdidos"* e pediu pra organizar doc + máquinas + fluxos. Rodei duas pesquisas — **estado-da-arte de mercado** (onde a doc deve morar) + **inventário empírico exaustivo** (20 agentes) do que memory/ tem hoje. Resultado: [F] **reverteu a decisão da manhã** (ADR 0364, Opção A = mover o trio pra `memory/_telas/`) para **Opção B = o trio FICA colocado ao lado do `.tsx`, a doc espelha o fonte**. O plano está documentado ([proposal #5156](../decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md), mergeada); a reversão canônica (ADR 0365) é ato pendente do [W].

## Contexto

De manhã [F] tinha decidido a **Opção A** ([ADR 0364](../decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md), ratificada por [W]): mover o trio (`.charter.md`+`.casos.md`) de `resources/js/Pages/` pra `memory/requisitos/<Mod>/_telas/`. À tarde, pedindo pra "organizar", [F] reconsiderou: *"eu quero como no fonte"*, *"não sei se o `_telas` vai conseguir tudo"* → **colocação (Opção B)**. Isso acionou o **gate-de-reversão cláusula (c)** que a própria 0364 previu.

## Pesquisa 1 — Estado-da-arte: onde a doc-do-fonte deve morar (2026)

| Quem | O que faz | Lição pro oimpresso |
|---|---|---|
| **[Grab](https://noise.getoto.net/2026/05/29/from-decentralized-docs-as-code-to-a-centralized-repository-evolving-grabs-documentation-strategy/)** | docs-as-code **descentralizado → centralizado** (mai/2026) | Centralizar só compensa se entregar **consistência** (taxonomia/naming/nav únicos). Sem isso, paga o custo e não colhe o ganho. |
| **[Diátaxis](https://diataxis.fr/)** (Canonical/Ubuntu) | *"a arquitetura da referência deve espelhar a estrutura do que descreve — como um mapa"* | Valida **colocação/espelho**: doc que mora junto/espelhando o fonte é canônico. |
| **[Spec Kit](https://github.com/github/spec-kit)** (GitHub) · **[Kiro](https://kiro.dev/docs/specs/)** (AWS) | **COLOCAM** a spec ao lado da feature (`.specify/`, `.kiro/specs/`) | A maré spec-driven **coloca**, não centraliza — premissa: "o dev atualiza o doc quando ele está perto do código". |
| **Swimm** | doc↔código acoplado com sync/drift | O valor **não é onde o doc mora** — é o **enforcement que trava merge** quando drifta. |

**Convergência do mercado:** instruções do agente versionadas (`AGENTS.md`), princípios não-negociáveis separados da spec (`constitution.md`), critério de aceite executável. O oimpresso já tem o equivalente (CLAUDE.md/rules/skills, Constituição/proibições, casos-gate).

**Tradução (LC-09 — traduzir premissa, não copiar):** a Opção B (colocação) **alinha à maré de mercado** + à lição do Grab (o benefício da centralização é a consistência, que dá pra ter colocado via correlação por frontmatter + gates). Prior art do próprio repo: [2026-07-17-arte-artefatos-por-tela](2026-07-17-arte-artefatos-por-tela.md) + [2026-07-23-grade-swimm-vs-kb-doc-codigo](2026-07-23-grade-swimm-vs-kb-doc-codigo.md).

## Pesquisa 2 — Estado empírico de memory/ (workflow 20 agentes, medido em origin/main)

Inventário exaustivo (inventário + gaps + design + 4 críticos adversariais). Recibos com comando ao lado — números datados, re-rodáveis:

- **72 módulos reais** (7 pseudo `_*`); **14 sem SPEC** (Atendimento, BI, Chat, Copiloto, Grow, Modules, Orcamento, Purchase, Site, Stock{Adjustment,Transfer}, Tarefas, User, VozDoCliente — vários são tombstones); **1 sem BRIEFING** (User).
- **31 `.md` soltos** na raiz de `memory/requisitos/` (módulos-nomeados fora de dir + fósseis + templates).
- **~44%** do corpus (~683/1537) com frontmatter ausente/divergente (recibo do proposal `estrutura-canon-memoria`).
- **109 scripts-máquina** (101 `governance/` + 8 `tests/`, não-teste) — **NÃO 188** (o "188" inflava contando 81 `.test.mjs`, que são bite-tests).
- **210 charters / 74 casos** em Pages (`git ls-tree -r origin/main resources/js/Pages/ | grep -c '.charter.md$'`).

**Achado-chave:** o trio **já é colocado** hoje — `scripts/casos-coverage-guard.mjs` (gate required) resolve por **path-irmão** (`dirname+basename+.charter/.casos`, L126-129); o dual-resolver da 0364 **nunca landou** em origin/main. Logo **Opção B ≈ status quo** — reverter agora, antes de qualquer move, é custo-zero. E o gap "máquinas espalhadas" **já foi fechado** por [`governance/MAQUINAS-INVENTARIO.md`](../../governance/MAQUINAS-INVENTARIO.md) (#5155, enforçado por `--check` advisory).

## Entregas

- **PR [#5152](https://github.com/wagnerra23/oimpresso.com/pull/5152)** — desambiguação dos 5 índices concorrentes de memory/ (fase 1) → **merged**.
- **PR [#5156](https://github.com/wagnerra23/oimpresso.com/pull/5156)** — [proposal Opção B](../decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md): programa de 9 partes (B0–B8), append-only, reverte só o eixo de localização da 0364 → **merged**.
- **ADR 0365** (reversão) — texto pronto na proposal; **cunhagem/flip = [W]** (pendente).

## Aprendizados / pegadinhas (o mais reusável — LC-08 em ação)

- **Quase dupliquei um workflow de 20 agentes** pra "catalogar os 188 scripts sem registry" — checar antes achou `maquinas-inventario.mjs` já existente (§5 não-duplicar). *"Usar o máximo de tokens" ≠ queimar em trabalho duplicado.*
- **Quase "liguei o órfão" (B5)** — mas #5155 já tinha ligado; meu "órfão, zero invocador" foi medição em **checkout stale** (o arquivo nem estava no meu tree). Guard de base: medir sempre contra `origin/main`.
- **O crítico adversário ELE MESMO errou** um número ("214/75") — medi eu (3 métodos) e achei **210/74**. Nem o adversário escapa do LC-08; a defesa é medir, não confiar.
- **Pegadinhas de git-glob:** `git ls-files 'Pages/**/*.charter.md'` e `git ls-tree | grep '\tblob\t'` erraram silenciosamente (contagem falsa). Método confiável = `git ls-tree -r | grep '\.ext$'` ou `awk '$2=="blob"'`. Nunca reportar output de comando que falhou como dado (§5 2026-07-28/31).
- **Workflow travou silencioso ~36min** (2 agentes pendurados no barrier); o harness **não notifica hang**, só conclusão. Recuperado com stop+resume (`resumeFromRunId` → 8 agentes do cache, resto ao vivo). Auto-check via `send_later` pega o que a notificação não pega.

## Próximos passos (não-bloqueante)

- [ ] **[W]** — cunhar/flip do **ADR 0365** (reverter canon 0364 → Opção B oficial).
- [ ] **[F]** — B6: limpar os 31 soltos (fóssil→lápide+relink; preservar `Officeimpresso1.md`, ref histórica ADR 0017).
- [ ] **[F]** — B7: censo dos 14 sem SPEC (excluir tombstones) + cobertura forward-only via ratchet.
- [ ] **[F]/[W]** — B3 (RAG in-place, glob de Pages no `IndexarMemoryGitParaDb`) — o único ganho de A que B precisa preservar.

## Referências

- Proposal: [2026-08-01-reverter-0364-trio-colocado-opcao-b.md](../decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md)
- ADR revertida: [0364](../decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md) · base: [0264](../decisions/0264-governanca-executavel-trio-dominio-e2e.md)
- Prior art: [2026-07-17-arte-artefatos-por-tela](2026-07-17-arte-artefatos-por-tela.md) · [2026-07-23-grade-swimm-vs-kb-doc-codigo](2026-07-23-grade-swimm-vs-kb-doc-codigo.md)
