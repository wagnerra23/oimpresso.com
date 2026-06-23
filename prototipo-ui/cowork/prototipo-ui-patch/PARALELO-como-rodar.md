# PARALELO — como rodar vários PR-C ao mesmo tempo sem embolar

> **Origem:** Cowork [CC] · 2026-05-29 · **motivo:** sequencial tá lento; queremos várias ondas em paralelo.

## A regra de ouro

> **Buildar em paralelo: SIM. Mergear: em série.**
> A migração (achar+trocar controles, rodar lint/build) é o que demora **minutos** — isso roda em paralelo.
> O merge em si leva **segundos** — faz um de cada vez. Você pega ~todo o ganho de paralelismo.

## Por quê

Cada PR-C toca **só** o `Pages/<Módulo>` dele (folders diferentes → nunca colidem entre si) **+ um arquivo compartilhado:** `config/eslint-baseline.json`. Esse JSON é o único ponto de conflito. Dois merges simultâneos nele = conflito chato.

## O fluxo (zero-touch)

1. **Dispara N terminais Claude Code em paralelo**, cada um com **sua própria worktree** (não reusa a mesma — foi o que quebrou antes). Um por módulo:
   - `FIX verde-ação` (Financeiro CSS) — não toca o baseline, **mergeia a qualquer hora**.
   - `PR-C3 RecurringBilling`, `PR-C4 OficinaAuto`, `PR-C5 Sells` — cada um na sua branch/worktree.
2. **Deixa todos buildarem em paralelo** (cada um migra, roda `lint`/`build`, abre o PR). Isso é o grosso do tempo, e roda junto.
3. **Mergeia um de cada vez.** O 1º entra normal. Pro 2º+ em diante, antes de mergear, o Code roda:
   ```bash
   git fetch origin main && git rebase origin/main
   npm run lint:baseline:write          # recalcula o baseline já com o módulo anterior dentro
   git add config/eslint-baseline.json && git commit --amend --no-edit && git push -f
   ```
   (Já deixei essa etapa embutida em cada prompt PR-C.)

## Limites honestos

- **Não rode 2 PR-C no MESMO módulo** — aí sim colidem nos arquivos de tela.
- **Cada paralelo = uma worktree** → mais worktree, mais risco da quebra que o Code já viu. Eu não rodaria mais que **3–4 trilhas** ao mesmo tempo.
- **Eu não comito** — cada bloco é você colando num terminal Code. Os PRs são abertos pelo Code, mergeados por você (Approve, sem `--admin`).

## Lote pronto pra disparar (não colidem ao buildar — folders distintos)
| # | Prompt | Peso | Obs |
|---|---|---|---|
| 0 | `FIX-fin-verde-acao.md` | leve | CSS Financeiro · **não toca baseline, mergeia a qualquer hora** |
| 1 | `PR-C3-recurringbilling.md` | médio | forms Planos/Configurações |
| 2 | `PR-C4-oficinaauto.md` | médio | ServiceOrders/Vehicles · cuidado DnD Kanban |
| 3 | `PR-C5-sells.md` | ⚠️ pesado | Create 68KB, Index 77KB · módulo crítico do balcão |
| 4 | `PR-C6-repair.md` | médio | mobile-first · touch ≥44px |
| 5 | `PR-C7-purchase.md` | leve | 4 telas · terminal rápido |

**Dispara em ondas de 3–4 trilhas** (não as 6 de uma vez — worktree demais). Sugestão:
- **Onda 1 (agora):** FIX + C3 + C4 + C7 (leves/médios, rápidos)
- **Onda 2 (quando 1 merge):** C5 (pesado, sozinho ou com C6) + C6

Cada PR-C que **não** for o 1º a mergear roda o refresh de baseline embutido no prompt. Depois de Purchase, faltam **Admin → Whatsapp → Settings** (me peça quando chegar lá).
