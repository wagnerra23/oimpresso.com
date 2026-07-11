# RUNBOOK: Design-Sync PUSH (git tokens → espelho vivo)

> **Quando usar:** um token do Design System **já foi aceito e buildado no git** (via [`design-sync-pull.md`](design-sync-pull.md) ou edição direta em `semantic.tokens.json` + `npm run tokens:build`) e você quer **re-espelhar** o valor pro projeto vivo no Claude Design (claude.ai/design, "Office Impresso — Design System") pra ele parar de driftar.
>
> **Direção:** git = **SSOT** (nasce aqui, o CI valida, o deploy usa) → espelho = **vitrine derivada** (onde se desenha/apresenta). O git→espelho é PUSH **read-mostly a partir do git aprovado** — a direção legítima da [ADR 0315](../../memory/decisions/0315-design-sync-claude-design-vs-cowork-charter.md)/[ADR 0239](../../memory/decisions/0239-governanca-design-system-git-ssot-regressao-ia.md). É o **par** do [`design-sync-pull.md`](design-sync-pull.md): pull traz o que o Wagner desenhou; push devolve o que o git canonizou.

---

## ⚠️ A regra que motiva este runbook (não pule)

O espelho **nunca** é fonte concorrente do git. Quando o git muda um token (canvas, primary, status, o que for) e o espelho fica no valor velho, o certo é **empurrar o git pro espelho** — nunca "deixar o espelho como está porque alguém pode ter editado lá". Se você suspeita que o espelho tem edição intencional não-espelhada, **isso é um PULL** (rode o [`design-sync-pull.md`](design-sync-pull.md) primeiro, triа́gem inclusa), não um push cego. Push assume que o git já é a verdade.

**Incremental, nunca replace atacado.** A própria tool exige `finalize_plan` (trava o conjunto exato de paths). Empurre **só os arquivos que mudaram** (`colors_and_type.css` e os `preview/colors-*.html` afetados), não a árvore inteira.

---

## Pré-requisitos

- Opt-in `/design-sync` da sessão (Wagner explícito) — os hooks `block-design-sync-without-optin` / `block-skill-design-sync-without-optin` bloqueiam sem isso.
- `_generated-*.css` do git **atualizados** (`npm run tokens:build` rodado após a última mudança de token). Se `git status` mostrar `_generated-*` sujo, faça o build e commite antes.
- projeto espelho: `019dd02f-d2d0-7ba6-a57f-24b3ddd073ac`.

---

## Passos

### 1. Confirmar que o git está buildado e limpo
```bash
npm run tokens:build
git status --short resources/css/tokens/   # deve estar limpo (ou commite o build)
```
O espelho vai ser derivado **destes** `_generated-*.css` — se estiverem stale, você espelha valor errado.

### 2. Montar o `colors_and_type.css` do espelho a partir do git (transform determinístico)

O `colors_and_type.css` do espelho **não** é um dump cru: é um **scaffold estável** (cabeçalho DS v6 + `@font-face` self-hosted + estilos de elemento semânticos + focus ring universal) **envolvendo os blocos de token** que são cópia verbatim dos `_generated-*.css`. O push regenera **só os blocos de token**, preservando o scaffold.

Mapeamento escopo → arquivo git (o mesmo do `ds-token-diff.mjs`):

| Bloco no espelho | Arquivos git (`resources/css/tokens/`) |
|---|---|
| LAYER 1 `@theme` (`:root` light) | `_generated-inertia-theme.css` |
| LAYER 2 foundations `:root` (light) | `_generated-foundations-light.css` |
| LAYER 1+2 DARK (`.dark, [data-theme="dark"]`) | `_generated-inertia-dark.css` + `_generated-foundations-dark.css` |
| LAYER 3 `.cockpit` (light) | `_generated-cockpit-light.css` |
| LAYER 3 `.cockpit[data-theme="dark"]` | `_generated-cockpit-dark.css` |

> **COMPANION `cockpit_domains.css` (2026-07-10, PR #4097 + push design-sync):** o `colors_and_type.css` **cura de propósito** a camada `.cockpit` — traz fundações legíveis + os 4 `--kind` base, mas **omite** o set de domínio (`--origin-*`/`--stage-*`/`--sla-*-dot/line`/`--canal-*-bg/fg/tint`/`--kind-*-soft`/`--kpi-feature-*`). Esse set vive num arquivo **companion** `cockpit_domains.css` no espelho (gerado por `scripts/design-sync/ds-domains-companion.mjs`, verbatim dos `_generated-cockpit-*.css`), que o shell ERP faz `<link>` **ao lado** do `colors_and_type.css`. Refrescar o companion = `ds-domains-companion --write` + empurrar `cockpit_domains.css` no MESMO `finalize_plan`. **Camada UI-0013 respeitada:** `colors_and_type` = fundações; companion = domínio shell. Retirar a redeclaração de domínio dos bundles de módulo é PROIBIDO (defesa de portal — §5 proibicoes 2026-07-10).

Regra de montagem: para cada bloco, substituir o corpo `{ … }` no scaffold pelas linhas `--token: valor;` extraídas do(s) arquivo(s) git correspondente(s). Header, `@font-face`, `html/body/h1…`, `.tabular`, `::selection`, `:where(...)` focus ring = **inalterados** (não são tokens; são a identidade do espelho).

> Automação (futuro): um `scripts/design-sync/ds-mirror-build.mjs` pode assemblar o `colors_and_type.css` a partir do scaffold + `_generated-*`. Enquanto não existe, faça a substituição por bloco (é o inverso exato do parse do `ds-token-diff.mjs`).

Salvar o resultado num arquivo de staging **fora do repo**, ex.: `<scratchpad>/mirror-colors_and_type.css`.

### 3. Validar ANTES de empurrar (diff = 0)
```bash
# Espelho cura → passe o companion pra fechar o falso git-only dos domínios (2026-07-10):
node scripts/design-sync/ds-token-diff.mjs <scratchpad>/mirror-colors_and_type.css resources/css/tokens \
  --companion <scratchpad>/mirror-cockpit_domains.css
```
`divergências de VALOR: 0` → o arquivo montado bate com o git. Só então empurre. (Se der > 0, o passo 2 errou a montagem.) **Sem `--companion`**, `cockpit-light/dark` reporta ~58 domínios como `git-only` — é FALSO drift (o espelho os tem no companion). O residual git-only legítimo pós-companion são tokens de shell não-domínio (`--bubble-*`/`--thread-*`/`--plate-*`/`--sb-scroll`) que o espelho também omite — decisão de curadoria à parte.

### 4. Empurrar incremental via DesignSync
```
DesignSync finalize_plan  project=019dd02f-…  writes=[colors_and_type.css]  localDir=<scratchpad>
DesignSync write_files    planId=<…>  files=[{ path: colors_and_type.css, localPath: mirror-colors_and_type.css }]
```
Se também houver `preview/colors-*.html` refletindo valores de token velhos, inclua-os no MESMO plano (`writes=[colors_and_type.css, preview/colors-primary.html, …]`) e empurre juntos. **Nunca** um `finalize_plan` com a árvore inteira.

### 5. Atualizar o commit-fonte no `README.md` do espelho
O README diz *"derived from wagnerra23/oimpresso.com @ commit `<sha>`"*. Atualizar pro `git rev-parse --short HEAD` do commit que acabou de canonizar o token. É o carimbo de proveniência que prova o espelho estar em dia (e o alvo do sentinela P3).

### 6. Confirmar no espelho
```
DesignSync get_file  project=019dd02f-…  path=colors_and_type.css   # salva
DesignSync get_file  project=019dd02f-…  path=cockpit_domains.css   # salva (companion)
node scripts/design-sync/ds-token-diff.mjs <colors_and_type salvo> resources/css/tokens \
  --companion <cockpit_domains salvo>    # VALOR: 0
```
Loop fechado: `ds-token-diff.mjs --companion` contra o espelho **vivo** agora dá VALOR 0. É o mesmo motor que o sentinela P3 roda periodicamente.

---

## O loop completo (pull ↔ push)

```
Wagner desenha no Claude Design ──PULL (design-sync-pull.md)──▶ semantic.tokens.json ──build──▶ _generated-*.css ──(git = SSOT, CI, deploy)
                                                                                                        │
       espelho vivo ◀──PUSH (este runbook)── colors_and_type.css montado ◀───────────────────────────┘
                                                                                                        │
                                              ds-token-diff.mjs (sentinela P3) ── advisory ── alerta se separar
```

## Anti-padrões
- ❌ Empurrar sem `npm run tokens:build` — espelha `_generated-*` stale.
- ❌ `finalize_plan` com a árvore toda / replace atacado (viola o incremental exigido pela tool + [ADR 0239](../../memory/decisions/0239-governanca-design-system-git-ssot-regressao-ia.md)).
- ❌ Push quando o certo era pull (espelho tem edição intencional não-espelhada) — rode o [`design-sync-pull.md`](design-sync-pull.md) + triagem primeiro.
- ❌ Reescrever o scaffold do `colors_and_type.css` (header/`@font-face`/element styles) — só os blocos de token mudam.
- ❌ Esquecer o carimbo de commit-fonte no README (passo 5) — sem ele o sentinela não tem âncora de proveniência.

**Criado:** 2026-07-08 — par PUSH do `design-sync-pull.md`. Fecha o loop git↔espelho (P2). Origem: proposta [`2026-07-08-profissionalizar-ds-sync-git-espelho.md`](../../memory/decisions/proposals/2026-07-08-profissionalizar-ds-sync-git-espelho.md) P1/P3.
