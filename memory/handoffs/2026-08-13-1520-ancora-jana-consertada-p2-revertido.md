---
date: "2026-08-13"
time: "15:20 BRT"
slug: ancora-jana-consertada-p2-revertido
tldr: "Os 3 defeitos que o #5719 sinalizou na âncora do /ia foram atacados: P-1 (6 serviços fantasma) e P-3 (contraste AA no escuro, 12 sites e não 1) consertados; P-2 (Frota) RETIRADO — era diagnóstico errado, [W] corrigiu com a captura do Cowork vivo. PR #5738 mergeado com 116 verdes. Os 3 vermelhos de CI que apareceram no caminho eram todos de outros PRs, cada um falsificado antes de ser descartado."
prs: [5738]
decided_by: [W]
next_steps:
  - "[W]: P-1 e P-3 vivem no ESPELHO (prototipo-ui/cowork/) — pela ADR 0374 o próximo --export-from os SOBRESCREVE. Pra durarem, o conserto precisa nascer no Cowork vivo e descer."
  - "[W]: COWORK_BOT_PAT não empurra (403 denied to github-actions[bot]) — o modo update do visreg gera as 26 baselines e PERDE. O guard do step não pega porque lê .permissions.push com o token errado."
  - "Conferir amanhã ~10:35Z: mv-metabolismo verde (a causa foi corrigida na main hoje 11:50/12:41 UTC; o watchdog G6 só limpa na próxima run AGENDADA)."
  - "Observação, NÃO proposta: PixelBaselineTest é o único dos 5 visreg sem visreg-flake-retry.sh — e é o enforcing. Envolvê-lo em retry é afrouxar gate; decisão [W]."
---

# A âncora da Jana consertada — e o P-2 que não era defeito

## Estado MCP no momento do fechamento

⚠️ **O servidor MCP esteve INALCANÇÁVEL a sessão inteira.** O hook de `SessionStart` caiu em
fallback com `SyntaxError` ("servidor MCP inalcançável") e nenhuma tool `mcp__oimpresso__*` esteve
disponível. **Operei sem `brief-fetch`** — violação de Tier A que registro em vez de omitir.

Usei o **fallback filesystem** que o [`how-trabalhar.md`](../how-trabalhar.md) §Fallback autoriza:

- `sessions-recent` → por `ls -t memory/sessions/`: esta sessão + `2026-08-13-jana-dark-ancora-defeituosa` (a irmã que sinalizou)
- `decisions-search since:2026-08-13` → por `git log -- memory/decisions/`: **0374** (ratificada, #5737) · **0378** (criada #5747, ratificada #5750)
- handoffs irmãos de hoje: `0750-smoke-real-pages-no-modulo-dono`, `1230-ct100-atualizado`, `1330-jana-dark-e-a-ancora-que-mentia`
- `cycles-active` / `my-work` → **não consultados** (sem MCP; sem fallback equivalente, e não invento estado)

## O que aconteceu

Continuação direta do handoff [`1330-jana-dark-e-a-ancora-que-mentia`](2026-08-13-1330-jana-dark-e-a-ancora-que-mentia.md).
Aquela sessão **sinalizou** 3 defeitos na âncora (`prototipo-ui/cowork/jana-merge.jsx`) e deixou o
conserto pendente — o PR 0.5 proposto em 09/ago nunca rodou. Esta rodou.

**P-1 consertado.** Os 6 `Analise*Service` não existem (re-medido: `rc=1`). Trocados pelo formato
real derivado do `JANA_DRILL_FONTES` do código, não inventado: `SellsCockpitAggregator::<metodo>`.
`churn` e `frota`, que **não têm** método no back, declaram isso em prosa — e o render passou a
vestir de `<code>` só o que contém `::`.

**P-3 consertado, e era 12× maior que o pedido.** O par medido é misto (`--neg` pegou o override
escuro, `--neg-soft` não). Medindo a família: **12 dos 18** consumidores `*-soft` reprovam AA no
escuro. neg 2,19→4,22 · warn 1,60→5,68 · pos 1,93→5,08, sem regredir o claro. O **accent (6 sites)
ficou intocado de propósito**: passa hoje (4,41) e o mesmo `color-mix` o **reprovaria** (2,35) —
aplicar a família uniformemente teria quebrado o que estava bom.

**P-2 RETIRADO — era meu erro.** [W] mandou a captura do Cowork vivo: *"essa é a âncora correta do
protótipo"*, com `FROTA UTILIZAÇÃO`, meta `Utilização de frota` e "caçamba". O Non-Goal governa o
que se **constrói na tela** `/ia` do núcleo, não o que a fonte de design **retrata** — o protótipo
é o cockpit do Martinho (`biz=164`), onde frota é o negócio. Revertido integralmente. Agravante que
registro: eu não só podei domínio real, **inventei** "Conversão de orçamentos" no lugar.

## O que fica de pé, e o que não

| Entrega | Dura? |
|---|---|
| `ancora.mjs` enxergando `Classe::metodo` (FP medido antes: zero) | ✅ permanente |
| Charter `Jana/Index` **v6** | ✅ permanente |
| Âncoras de símbolo no `JanaCockpit.tsx` (as refs de linha do #5719 apodreceram) | ✅ permanente |
| Baseline de pixel da Jana regenerada | ✅ permanente |
| **P-1 e P-3** (em `prototipo-ui/cowork/`) | ⚠️ **some no próximo `--export-from`** |

A última linha é consequência da [ADR 0374](../decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md),
ratificada por [W] **hoje**: `prototipo-ui/cowork/` é **espelho de leitura**; a rota prevista é
Cowork → git, e a escrita git → Cowork **segue gated**. Eu insisti 3× pela palavra `design-sync`
antes de ler a ADR que respondia isso — o hook estava certo as três vezes.

## Os 3 vermelhos de CI — nenhum era do meu diff

| Check | Causa real | Como provei |
|---|---|---|
| `visual-regression` (required) | Baseline de **10/ago**, anterior ao **#5719** (paridade de tema escuro) | `chat-jana.css` tem 0 import no build · app não usa `.jc-*` como `className` · meu diff no `.tsx` filtrando comentários fica **vazio** |
| `Append-only canon` (required) | Base **18 commits** atrás; leu a ADR 0374 modificada pelo **#5737** | O gate passa em #5734/#5732/#5730/#5727/#5728/#5719 · `gh pr update-branch` resolveu |
| `watchdog G6` (advisory) | `mv-metabolismo` falhou 10:36 UTC; causa corrigida na main 11:50/12:41 | Rodei o watchdog local: **24 medidos, 24 vivos** · não está no `required-checks-baseline.json` |

**Nota de mecanismo, pro próximo que vir:** no `visual-regression` os 16 testes **PASSAM** e o step
sai `exit 2`. Zona cinza não é falha de teste — quem derruba é o `afterAll`
(`VisregThreshold::writeGrayZoneSummary` lança quando há tela na zona cinza e
`VISREG_GRAY_APPROVED != 1`). "16 passed" e vermelho ao mesmo tempo é o comportamento **correto**.

**Duas hipóteses minhas que morreram medidas** (registro porque economizam tempo de quem repetir):
o vermelho **não** era flake (re-rodei: falhou igual, seed diferente) e **não** era o P-3.

## Escolha que não fiz

Não apliquei o label `visreg-gray-approved`. Ele é a assinatura de aprovação visual [W] (gate
F1.5); aplicá-lo seria assinar por ele. Regenerei a baseline (apenas a da Jana, 1 de 16) — o gate
passou por **mérito**, não por dispensa.

Também não registrei supressão em `governance/cron-vermelho-esperado.json` pro watchdog: o arquivo
exige razão, validade e *quem declarou*, com merge de [W] como ato — e o vermelho some sozinho na
próxima run agendada.

## PR

- [#5738](https://github.com/wagnerra23/oimpresso.com/pull/5738) — mergeado `9c8f016` às 18:01 UTC,
  auto-merge squash, **116 pass · 1 fail advisory (watchdog G6) · 2 skipping**.
