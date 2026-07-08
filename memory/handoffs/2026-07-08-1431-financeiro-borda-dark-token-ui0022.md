---
date: "2026-07-08"
time: "14:31 BRT"
slug: financeiro-borda-dark-token-ui0022
tldr: "Fechei o borderColor 56/57 SISTEMÁTICO do fingerprint no dark do Financeiro. Diagnóstico do handoff anterior estava INVERTIDO: NÃO é hardcode sweep, É token. Provei ao vivo (browser MCP + sentinela por-var): as 575 bordas vêm de --color-border + cockpit --border; a var só parecia morta porque .cockpit tem data-theme=dark → a regra do token re-aplica e sombreia override só-na-raiz. Fix = DTCG semantic.tokens.json dark 0.30→0.335 + tokens:build (app-wide, ADR UI-0022, igual UI-0021). PR #3958 MERGEADO (Wagner aprovou). Restam --fin-line (49) + superfície (0.238 atrás do filtro) como follow-ups."
prs: [3958]
decided_by: [W]
related_adrs: [0022-border-dark-clareado-fidelidade, 0021-primary-dark-clareado-0190]
next_steps:
  - "SUPERFÍCIE (bgEfetivo 56/57): o topo (header+KPIs+filtro) achata em oklch(0.165 0.008 282); proto quer painel ~oklch(0.238 0.009 282) atrás do filtro (a tabela já tem painel 0.205). PRECISA do proto Cowork RODANDO pra cravar qual container recebe o painel + valor exato — o proto do repo (prototipo-ui/cowork/) é JSX que exige build Vite; o bundle rodável (oimpresso.com.html) está em /c/Users/wagne/Downloads/_cowork-handoff-staging/.../project/ e pode estar velho. PR próprio."
  - "--fin-line: 49 bordas oklch(0.92 0.005 240) (clara+fria, SEM override dark) — Financeiro-scoped (.fin-cowork .fin-curadoria), cascade-sensível (specificity 0,2,0 > .dark 0,1,0, então override tem que ser scopeado igual ou maior). Companion natural da superfície."
  - "Residuais frios oklch(0.28 0.008 240) ~11 lados hardcoded no shell."
  - "PÓS-DEPLOY: VRT baselines dark em modo UPDATE (resources/css/** é skip-as-pass, não dispara VRT sozinho, mas os pixels mudaram) + smoke real R1 (re-rodar fingerprint --compare pra confirmar borderColor caindo do 56/57 — pendente proto servível)."
---

# Handoff — Financeiro borda dark: era TOKEN, não hardcode (ADR UI-0022)

## Estado MCP no momento do fechamento
- **cycles-active:** nenhum cycle ATIVO em COPI (off-cycle).
- **my-work (@wagner):** 8 tasks em REVIEW (US-TR-305..311 triage/inbox, US-PG-008, US-FIN-023) — nenhuma é este trabalho (foi ad-hoc, dirigido turn-a-turn pelo Wagner, não tracked como task MCP).
- **decisions:** ADR UI-0022 criada+aceita nesta sessão (emenda à UI-0020, companion neutra da UI-0021).

## O que aconteceu
Retomei do handoff [2026-07-08 10:44](2026-07-08-1044-financeiro-fidelidade-fingerprint-furos.md), que apontava o próximo alvo: `bgEfetivo + borderColor 56/57 SISTEMÁTICO`. O handoff instruía "sweep hardcoded `oklch(0.3 0.012 282)` → 0.335, NÃO é fix de token (testei 2 hipóteses ao vivo e reprovaram)".

**Investiguei ao vivo (browser MCP, prod `oimpresso.com/financeiro/unificado` dark) e o diagnóstico estava INVERTIDO — é token, e descobri o motivo mecânico exato do "reprovou" anterior:**

1. As 575 bordas neutras dominantes vêm de **dois tokens de fundação** (medido por sentinela: pintar cada var de cor distinta e recontar): `--color-border` (utility Tailwind `border-border`, 151 lados) + `--border` cockpit (`var(--border)` no CSS de componente, 424 lados). Não há literal `0.3…282` em `resources/css` fora dos `_generated-*.css` (grep vazio).
2. **Por que a var "parecia morta":** `.cockpit` carrega `data-theme="dark"` (confirmado `cockpit.matches('[data-theme="dark"]')===true`). Então a regra única `.dark, [data-theme="dark"] { --color-border }` **re-aplica o valor no nível do `.cockpit`** e **sombreia** qualquer override feito só na raiz `<html>` (que foi como testaram no DevTools). Setei sentinela na raiz → propagou html→body→div, e **FLIPOU de volta a 0.30 no `.cockpit`** (prova cristalina do shadowing). O handoff anterior escreveu "data-theme está no <html>, não no .cockpit" — **falso, medido ao vivo**.
3. **Prova de que editar o VALOR do token funciona:** setei `--color-border`+`--border` a `0.335` nos níveis certos (html + [data-theme=dark]) → `575 → 0` no valor velho, `575` no alvo. +49 do `--fin-line` = 624 total.

## Fix (PR #3958, MERGEADO)
DTCG `semantic.tokens.json` → `border`/`input` (inertia) + `cockpit.border` `.dark` `oklch(0.30 0.012 282)` → `oklch(0.335 0.012 282)` + `npm run tokens:build`. Regenera 3 vars nos `_generated-*.css`. **App-wide/fundação** (afeta borda dark de todos os módulos) — precedente ADR UI-0021 (primary "app inteiro"). `dtcg-equivalence.mjs`: 296/296 fiéis, 0 divergências. CI: 74 pass / 0 fail. Wagner: "merge e salve tudo".

## Decisão de escopo (registro)
Tomei a decisão app-wide (não Financeiro-scoped) e segui sem perguntar — o hook `block-askq-execution-menu` barrou a pergunta ("recomende e siga; Wagner valida no merge"). Justificativa: proto Cowork é a fonte de design do dark inteiro (ADR 0299), `0.335` é o neutro do proto em todo lugar, precedente UI-0021. Wagner confirmou implicitamente ao mandar mergear.

## Lições catalogadas
- **A borda do Financeiro É token-driven** (`--color-border` + cockpit `--border`), NÃO hardcode — corrige a lição do handoff 10:44. O `.cockpit[data-theme=dark]` NÃO é bloco morto: `.cockpit` TEM `data-theme=dark`.
- **Override de custom-prop na raiz `<html>` é sombreado por qualquer ancestral com `[data-theme=dark]`** (aqui `.cockpit`). Testar a var no DevTools na raiz dá falso-negativo. Editar o VALOR do token (fonte DTCG) atinge todos os níveis que a regra `.dark,[data-theme=dark]` casa.
- **Sentinela por-var** (pintar cada candidato de cor distinta e recontar bordas) é o método rápido pra saber QUAL var pinta QUAL borda — resolveu a ambiguidade que a análise estática não fechava.

## Pointers
- ADR: [`memory/requisitos/_DesignSystem/adr/ui/0022-border-dark-clareado-fidelidade.md`](../requisitos/_DesignSystem/adr/ui/0022-border-dark-clareado-fidelidade.md).
- Doc de fidelidade (round 2026-07-08 apendado): [`financeiro-unificado-visual-comparison.md`](../requisitos/Financeiro/financeiro-unificado-visual-comparison.md).
- Session log: [`memory/sessions/2026-07-08-financeiro-borda-dark-token.md`](../sessions/2026-07-08-financeiro-borda-dark-token.md).
