---
id: governance-scorecards-fonte-unica-qualidade-telas
---

# FONTE ÚNICA — Qualidade de Telas (Screen Quality)

> **Este é o ponto de entrada único sobre "nota de tela / quais telas arrumar".**
> Criado 2026-06-07 pra acabar com a fragmentação (havia 8 fontes + 4 conflitos).
> Validado contra `main` @ 561ff8be3.
> Se algum outro doc contradisser ESTE, este vence — os outros são snapshots históricos.

---

## 1. O método canônico (1 só)

| Item | Verdade canônica | Onde mora |
|---|---|---|
| **Dimensões** | **16** (as 15 do framework UX + dim-16 "Pré-Flight conformance") | [`SCREEN-GRADE-METODO.md`](../../requisitos/_DesignSystem/SCREEN-GRADE-METODO.md) |
| **Níveis** | Beginner 0-49 · Developing 50-69 · Advanced 70-84 · Leader 85-94 · Champion 95-100 | idem |
| **Como a nota sai** | **LLM-as-judge** (agente lê o `.tsx` + charter e pondera por persona). NÃO é computável por command. | ADR **0250** |
| **Como NÃO regride** | catraca **determinística** no CI: `screen-grades-ratchet.mjs` (nota só sobe) + cobertura | ADR **0250** |
| **ADR mãe** | **0250** (`0250-screen-qa-specialist-sustentavel.md`) — NÃO 0249 (ver §4) | `memory/decisions/` |

## 2. A fonte de dados ÚNICA E VIVA

✅ **`memory/governance/scorecards/screens/*.yaml`** (222 arquivos) — **única fonte operacional.** A catraca lê o `nota:` daqui. Toda atualização de nota vai AQUI.

🗄️ **Snapshots HISTÓRICOS (congelados em 30/mai — NÃO são verdade atual, só registro):**
- `screen-grades-baseline-2026-05-30.json` — baseline original (gerou os YAMLs)
- `SCREEN-GRADE-BOARD-2026-05-30.md` + `screen-grade-board.html` — visualizações derivadas
- `screen-grades-pilot.md` — calibração de 6 telas (notas divergem de propósito; histórico)

> ⚠️ **As notas dos 222 YAMLs estão DEFASADAS** (seed de 30/mai). Confirmado: em `main` @ 561ff8be3 (97 commits após o seed) a distribuição dos YAMLs é idêntica = nunca foram re-rodados, apesar de muitas telas terem sido refatoradas. Ver §3.

## 3. Estado real (re-avaliação 2026-06-07, contra `main` fresco)

A pergunta "quais telas precisa arrumar?" tinha resposta-fantasma. Re-lendo o `.tsx` atual das 44 telas com nota seed <70:

| Classe | Qtd | Ação |
|---|---|---|
| ✅ STALE-LOW (nota velha, já boa, real ~72-84) | **37** | só re-gradear (catraca só sobe = seguro) |
| 🔴 REAL-LOW (precisa mesmo) | **5** | consertar (test-first) |
| 🟡 STUB (Jana Brief/Regras — feature não feita) | **2** | decisão de produto |

> `Admin/RagQualityDashboard` era REAL-LOW na triagem do tree antigo mas **já foi consertado** nos 97 commits (0 cor crua em main) → migrou pra STALE.

**As 5 reais** (todas o MESMO problema: cor Tailwind crua em vez de token DS):
1. `Manufacturing/Index` (~62) — `text-emerald/amber-700` cru (4 ocorrências) + skeleton
2. `Financeiro/Extrato/Index` (~64) — `text-emerald-600` cru (3×) + skeleton na tabela
3. `Financeiro/Configuracoes/Contador` (~63) — spans `bg-emerald/amber` (2×) → `<Badge>`
4. `ComunicacaoVisual/Index` (~64) — badge cor crua (1×) + select nativo
5. `Repair/JobSheet/Index` (~63) — `animate-pulse` manual → `<Skeleton>`; status → `<Badge>`

## 4. Conflitos encontrados e como ficam resolvidos

| # | Conflito | Resolução canônica |
|---|---|---|
| C1 | **15 vs 16 dimensões** (`framework-15-dimensoes.md` diz 15) | Canônico = **16**. O framework é a base UX (15); a dim-16 (Pré-Flight) foi somada no MÉTODO. Quem citar dimensões usa o MÉTODO. |
| C2 | **Níveis: 3 faixas (framework) vs 5 níveis (método)** | Canônico = **5 níveis** do MÉTODO. As 3 faixas do framework (<60/60-80/>80) são legado — ignorar pra classificar tela. |
| C3 | **ADR 0249 vs 0250** | Canônico = **0250**. (0249 foi tomado pela ADR ds-v6.) Handoffs que dizem "0249" estão congelados/velhos. |
| C4 | **Baseline stale** (nota 30/mai vs realidade) | Ver §3. Re-grade pendente dos 37 STALE. |
| C5 | **222 graded vs 275 total .tsx** | Não é conflito: 275 = universo de telas; 222 = com nota; 53 sem nota (stubs/layouts). |
| C6 | **Screen-Grade vs Screen-Review** (sistema paralelo em `Admin/SCREEN-REVIEW-RUNBOOK.md`) | ORTOGONAIS: Grade = nota lida do código; Review = aprovação visual por screenshot pós-merge. Não integrados — manter separados conscientemente. |

## 5. O furo de prevenção (a fechar)

Nenhum dos 3 gates de cor pega **classe Tailwind de cor crua no `.tsx`** (`text-emerald-600`):
- `stylelint color-no-hex` → só `#hex` em CSS
- `conformance-gate.mjs` → só `oklch()`/`--accent` em CSS
- `foundation-guard.mjs` → só estrutura CSS

→ **Ação:** gate ratchet novo que varre `.tsx` por `(text|bg|border|ring)-(cor)-NNN` cru. É por isso que as 5 telas escaparam.

## 6. Plano de execução (ordem aprovada por Wagner 2026-06-07: 1→3→2→4)

1. **Limpar 37 fantasmas** — subir `nota:` dos 37 STALE (catraca só sobe; risco zero).
2. **Fechar o furo do lint** (§5) — gate `.tsx` cor-crua.
3. **Consertar as 5 reais** — test-first (TRAVAR→print→MUDAR→PROVAR), 1 PR/tela, cycle primeiro.
4. **Decidir os 2 stubs Jana** — implementar ou tirar do dashboard.

## 7. Pendências antigas nunca feitas (do histórico — revisar relevância)

`ScreenGradeCommand` PHP · dim-16 mecânica · sentinela "TELAS SEM RE-SMOKE" no Daily Brief · self-healing `screen-smoke-after-merge` · consolidação dos 6 motores de score (decisão [W] de 31/mai) · agente-autor rolando por módulos P0.

---
**Refs:** ADR 0250 · [`SCREEN-GRADE-METODO.md`](../../requisitos/_DesignSystem/SCREEN-GRADE-METODO.md) · [`framework-15-dimensoes.md`](../../requisitos/_DesignSystem/framework-15-dimensoes.md) · `.claude/agents/screen-qa-specialist.md` · `.claude/skills/screen-grade/`
**Loop seguro de conserto:** ver `screen-qa-loop-seguro` (memória).
