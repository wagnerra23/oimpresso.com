---
slug: 0317-maquina-revisao-adr-quando-rever-gatilhos
number: 317
title: "Máquina de revisão de ADR — quando rever, via 3 classes de gatilho (evento/inconsistência/tempo) + auto-canário"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-07-01"
accepted_via: "Wagner 2026-07-01: 'isso tem que se tornar uma máquina séria e confiável, quando rever adr' → apresentado o design endurecido pela crítica adversarial (que matou 80% do design ingênuo) → 'sim'. Ordem de build aprovada: auto-canário + supersedes_partially primeiro."
module: governance
tags: [governanca, adr, revisao, ciclo-de-vida, sentinela, catraca, fitness-function, decaimento]
supersedes: []
superseded_by: []
related:
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0257-adr-status-lifecycle-kind-modelo-canonico
  - 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0316-esquecimento-real-adr-morta-tombstone-git-auditoria
pii: false
---

# ADR 0317 — Máquina de revisão de ADR: "quando rever"

> Estende [0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md) (meia-vida/catraca/sentinela), [0257](0257-adr-status-lifecycle-kind-modelo-canonico.md) (status×lifecycle×kind), [0258](0258-processo-adr-estado-arte-indice-gerado-supersede-atomico.md) (índice gerado), [0219](0219-governance-drift-framework-driftchecker-plugavel.md) (DriftCheckers). NÃO cria sistema paralelo — cada peça vive numa que já roda.

## Contexto

A máquina de lifecycle de ADR já é rica (`adr-index-generate.mjs --check` gate-duro, `memory-health.mjs` Checks A–N cron diário + PR, 11 DriftCheckers) — **mas toda ela confia no frontmatter como verdade e não sabe QUANDO uma ADR precisa ser revista.** Dois furos reais, dos dois lados:

1. **Rótulo consistente mas semanticamente errado (0035):** `superseded`/`substituido`/`superseded_by:[0048]` — e a 0048 tem `supersedes:[0035]` de volta, então a integridade de máquina PASSA. Mas 0035 é citada em 124 arquivos, listada como canon da stack de IA em `what-oimpresso.md`, e o **corpo** da 0048 diz *"confirma e fortalece 0035"* (rejeita só o Vizra — é emenda parcial, não substituição). Nenhum gate percebe: a máquina concorda com um rótulo que o humano sabe estar errado.
2. **Declarado no texto, invisível ao campo (0097):** título "supersede parcial ADR 0091" com `supersedes:[]`. Os gates leem só o campo → cegos.

Verificação adversarial de 35 ADRs "supersede no título" (2026-07-01): **0 mortas de verdade** — todas emenda/errata mantendo a base viva. A máquina não distingue **emenda** de **substituição**, e o eixo `kind` (0257) existe mas nenhum gate o consome pra lifecycle.

> **Este ADR passou pela própria crítica adversarial antes de nascer.** O design ingênuo (4 checks + fila JSON + `next_review` manual + cadência nova) foi **cortado**: ref-count bruto dá 11 falsos-positivos no dia 1 (fundacionais têm 45 refs e estão "superseded"); fila JSON duplica o baseline-ratchet + viola tasks-no-MCP (0070); `next_review` de git-mtime é auto-refutado (o Check K já abandonou git-date porque touch-em-massa rejuvenesce). O que sobrou é o núcleo que **morde de verdade**.

## Decisão

Uma máquina de **3 classes de gatilho**, priorizadas por confiabilidade (**evento > inconsistência > tempo**), cada uma numa peça existente:

| Classe | Onde | Quando | Severidade |
|---|---|---|---|
| **EVENTO** (PR) | `adr-index-generate.mjs --check` | supersede/órfã/double-supersede no **campo** | 🔴 bloqueia (já existe) |
| **EVENTO-prosa** (PR) | idem | supersede no **título/corpo** sem o campo (0097) | 🟡 **warn** (não bloqueia) |
| **INCONSISTÊNCIA** | `memory-health.mjs` (Check O) | morta **E listada como ADR central/canônica** numa fonte-de-verdade viva | 🟡 sentinela |
| **TEMPO** (rede de segurança) | `memory-health.mjs` (Check R) | TTL por classe vencido, a partir de `decided_at` | 🟡 sentinela |

### 1. Detecção — o que morde (e o que foi cortado)

- **Check O — morta-mas-canon.** ADR `superseded`/`substituido`/`arquivado` **E** citada como "ADR central/canônica" numa **fonte-de-verdade vigente** (`what-oimpresso.md`, `CLAUDE.md`, `BRIEFING.md` ativo, `SPEC.md` vigente). **NÃO ref-count bruto** (o cético provou: 11 falsos no dia 1). Pega o 0035 (~2 flags reais). Baseline-ratchet só-encolhe (padrão dos Checks C/L/M/N).
- **EVENTO-prosa (🟡).** No gerador, regex `supersede|substitui …(\d{4})` em título+corpo sem o número no campo `supersedes` → **warn, nunca 🔴** (regex em prosa PT-BR não pode bloquear merge: "esta ADR **não** substitui 0091" casaria). O 🔴 continua só no **campo estruturado** (órfã/inexistente/double-supersede — já existe).
- **Check R — stale por meia-vida.** `next_review_efetivo = decided_at + TTL(kind)`. **De `decided_at` (imutável), nunca git-mtime.** `kind:meta` e `lifecycle:historical` **isentos**. TTL: proposto/rascunho 30d · decisão-toca-dependência-externa 90d · errata/feature-wish 180d · decisão-arquitetura-interna 270d · meta/historical ∞.
- **`kind:errata` consumido:** errata que rebaixa uma base ainda viva → o conserto canônico é `supersedes_partially` (novo campo, schema), não `supersedes`. É o que reconcilia 0048→0035.

### 2. Auto-canário (o que separa sério de teatro)

Hoje **não existe** monitor de liveness do próprio cron `memory-health.yml`. O GitHub desabilita workflow agendado após 60d sem atividade — **em silêncio**. Sem canário, a fila para de crescer e o brief fica verde = **regressão disfarçada de saúde**. Decisão: carimbo `governance/.memory-health-last-cron` (escrito pelo run de cron); o run **de PR** compara — carimbo velho (> 8 dias) enquanto PRs rodam ⇒ 🔴 "o cron morreu". Assim a classe TEMPO não é ilusão.

### 3. Fila, ação e catraca — reusando o que existe (zero paralelo)

- **A "fila" é derivada**, não um arquivo novo: `memory-health.mjs --json` emite os `open[]` a cada run (como o `_INDEX-GENERATED`). **Sem `adr-review-queue.json`** (duplicaria o baseline-ratchet + viraria task-tracker JSON, violando 0070).
- **Surfacing:** `AdrReviewBriefLineService` (molde: `PlanHealthBriefLineService`) — 1 linha determinística na seção FLAGS do brief, **só quando muda / tem 🔴**, **teto de vazão** (top-3 por severidade; resto silencioso). Evita o warn crônico que matou o `next_review` do `_INDEX-LIFECYCLE`.
- **Verificação adversarial:** o item enfileirado passa pelo workflow (1 cético por item, pergunta única "o rótulo mente?").
- **Ação (humana, append-only):** `confirma_rótulo` (grandfather no baseline) · `corrige_rótulo` (relabel via 0257 — nova emenda muda status×lifecycle×kind + `supersedes_partially`; ex 0035→ativo, 0048→errata) · `esquece` (git rm + tombstone via [0316](0316-esquecimento-real-adr-morta-tombstone-git-auditoria.md), **só p/ morto-real E baixa-referência** — o inverso do 0035).
- **Catraca:** par `<adr>:<check>` resolvido/grandfatherado no **baseline existente** não reabre salvo mudança de sinal.

### 4. Invariantes (Tier 0)

- **A máquina só detecta e enfileira — NUNCA reescreve frontmatter sozinha.** Relabel/esquecimento passam por verificação adversarial + Wagner (append-only, 0257).
- **Detector é determinístico** (regex + léxico fixo + fonte-de-verdade), sem LLM — reproduzível no CI. LLM só no julgamento adversarial humano-assistido.
- **Zero sistema paralelo** (proibições §5): tudo em `memory-health.mjs` / `adr-index-generate.mjs` / `adr.schema.json` / `Kernel.php` / 1 `BriefLineService`.

## Consequências

- ✅ "Quando rever" vira mecânico: evento (PR) + inconsistência (canon-listing) + tempo (TTL de `decided_at`), com auto-canário garantindo que o relógio não morre calado.
- ✅ Pega os dois furos reais (0035 morta-mas-canon; 0097 texto-invisível) em classes distintas, sem inundar de falso-positivo.
- ✅ `supersedes_partially` dá o vocabulário pra distinguir emenda de morte (o erro-raiz do 0035).
- ⚠️ **Custo real = calibrar o baseline inicial do Check O** (grandfather das ~35 emenda-legítimas + citações históricas) senão morde errado no dia 1 — mitigado pelo ratchet só-encolhe.
- ⚠️ **Risco residual honesto:** a confiança termina no que o autor escreve no corpo; não há oráculo formal acima. A máquina reduz o custo de detecção; o julgamento final é humano por desenho.

## Implementação (ondas, dentro das peças existentes)

1. **Onda 1 (aprovada):** `supersedes_partially` no `adr.schema.json` (feito neste PR) + **auto-canário** do cron.
2. **Onda 2:** Check O (morta-mas-canon) + EVENTO-prosa (🟡) no gerador — com baseline calibrado.
3. **Onda 3:** Check R (TTL de `decided_at`) + `AdrReviewBriefLineService` (teto de vazão) + `quarterlyOn` no Kernel.
4. **Aplicação:** rodar a máquina → fila → relabel do 0035/0048 (`supersedes_partially`) → e só então `esquece` (0316) o que for morto-real-baixa-ref.

## Refs
- [0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md) · [0257](0257-adr-status-lifecycle-kind-modelo-canonico.md) · [0258](0258-processo-adr-estado-arte-indice-gerado-supersede-atomico.md) · [0270](0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento.md) · [0316](0316-esquecimento-real-adr-morta-tombstone-git-auditoria.md)
- `scripts/governance/memory-health.mjs` · `scripts/governance/adr-index-generate.mjs` · `Modules/Governance/Services/Checkers/{AdrLinksChecker,DesignDocsFreshnessChecker}.php` · workflow `maquina-revisao-adr-design` (2026-07-01, design + crítica adversarial)
