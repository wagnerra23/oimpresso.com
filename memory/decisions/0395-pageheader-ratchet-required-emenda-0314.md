---
slug: 0395-pageheader-ratchet-required-emenda-0314
number: 395
title: "Emenda à 0314 — `PageHeader · ratchet` vira required: 2 telas novas adotaram o header antigo e mergearam verdes em 5 dias"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-09"
module: governance
quarter: 2026-Q3
tags: [governance, gates, ci, required, advisory, design, pageheader, ratchet, promocao, mordida-provada]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0327-anchor-content-required-emenda-0314
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
pii: false
---

# ADR 0395 — emenda à 0314: `PageHeader · ratchet` vira required

> **Append-only.** Não edito a [0314](0314-poda-gates-onda-2-lei-fusoes.md) nem a
> [0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) — esta ADR aplica a
> política da 0336 a **um** gate concreto, que é exatamente como a 0336 manda
> (*"a promoção de cada gate concreto é um PR próprio, por item"*).
>
> **Ratificação = merge [W]** (R10 · ADR 0275 §5). O flip da branch protection é passo
> pós-merge e está descrito abaixo — nunca antes, e o motivo é técnico, não cerimonial.

## Contexto — o gate detectou certo e foi ignorado, duas vezes

O `pageheader-gate.yml` congela a migração F4 do header (`@/Components/shared/PageHeader` →
canon `@/Components/PageHeader` v3.8,
[0189](0189-pageheader-canon-v3-1-cadastro-roxo.md)/[0190](0190-primary-button-roxo-universal-295.md)):
o contador de adotantes do header antigo **só pode cair**. Ele nasceu advisory.

**Medido em 2026-09-09** (`gh api --paginate .../pageheader-gate.yml/runs?branch=main`,
104 runs, contadas — não amostradas):

| | |
|---|---|
| runs no `main` | 104 |
| `failure` | **23** |
| `success` | 54 |
| `cancelled` | 23 |

As 23 falhas se agrupam em **duas janelas**, e cada janela é **um PR que introduziu um adotante
novo e mergeou verde**:

| # | PR | data | tela que adotou o header antigo |
|---|---|---|---|
| 1 | [#6779](https://github.com/wagnerra23/oimpresso.com/pull/6779) | 2026-09-04 | `resources/js/Pages/Repair/Settings/Index.tsx` |
| 2 | [#7040](https://github.com/wagnerra23/oimpresso.com/pull/7040) | 2026-09-08 | `resources/js/Pages/Patrimonio/Index.tsx` |

Nos dois casos o gate **acusou corretamente** (step `PageHeader migration guard`, exit 1, vermelho
visível) e **não segurou nada**, porque advisory não bloqueia merge. Nos dois casos a dívida
precisou de PR corretivo depois — no caso do Patrimônio, dois
([#7109](https://github.com/wagnerra23/oimpresso.com/pull/7109) migrou,
[#7115](https://github.com/wagnerra23/oimpresso.com/pull/7115) consertou o padding duplicado que a
migração expôs).

O efeito colateral é o que torna isto urgente: entre 08-09 e 09-09 o `main` ficou **vermelho neste
check para todo PR aberto**, por dívida de terceiro. Um vermelho permanente que ninguém pode
consertar no próprio PR é como se ensina um time a ignorar a cor do CI.

## Decisão

**`PageHeader · ratchet (header antigo só decresce)` passa a required em `main`.**

### Por que isto cabe na 0336 pela LETRA, e não como desvio soberano

Várias promoções recentes (0339, 0341, 0348, 0373) foram registradas honestamente como
**desvio consciente** da DR-2 — bite-log não coletado. Esta **não é** uma delas:

- **DR-2 cumprida.** A regra pede *"contrafactual coletável (≥2 PRs reais)"* ou *"reincidência
  documentada no padrão 0327"*. Temos **2 PRs distintos**, nomeados acima, ambos com o
  contrafactual já materializado — não é "teria mordido", é **mordeu e passou**. É o mesmo
  padrão que promoveu o `anchor-content` na [0327](0327-anchor-content-required-emenda-0314.md)
  (*"uma âncora podre mergeou verde 2×"*), com o agravante do custo do conserto posterior.
- **DR-3.1 cumprida sem ressalva.** O gate **não** é advisory-wrapped: não há
  `::warning::` + `exit 0`. Ele sai `exit 1` real e mostra vermelho — o que estava neutralizado
  era só o bloqueio de merge, nunca a máquina.
- **FP medido = 0.** As 23 falhas são todas o **mesmo step**, classificadas pelo step que falhou
  e não pelo texto do log (método da
  [0369](0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md)). O guard sai `rc=0` no
  `main` desde que a dívida foi paga.

### Por que isto não fere a política Tier-0 da 0314

A 0314 mantém `required = só Tier-0`. Esta é mais uma **exceção formal por emenda**, no molde que
a 0327 abriu e a 0336 generalizou — e o critério da 0336 é justamente *não* "ser importante", e
sim **provar mordida real**. Está provada.

## Pré-requisitos do deadlock — pagos no MESMO PR, e medidos

O incidente de 2026-08-05→08 (2 dias de merge travado no repo inteiro) nasceu de promover um
check **e** mexer no gatilho dele sem cuidado. Este gate tinha **as duas** condições de
inalcançabilidade:

1. **`paths:` nos DOIS gatilhos** (`push` e `pull_request`). Required assim = context que nunca
   nasce num PR fora de `resources/js/**` ⇒ `BLOCKED` com **0 falhas e 0 pendentes**, a
   assinatura que engana o diagnóstico. **Removido.**
2. **`types:` sem `synchronize`** (`[opened, reopened, ready_for_review]`, herança da poda de fila
   do #6622). Sem ele o check nasce quando o PR abre e **nunca mais** — um PR que abrisse vermelho
   ficaria vermelho para sempre. É a única das 4 formas de "required que não nasce" que **passa**
   num teste manual de um push só. **Acrescentado.**

Recibo, com a mudança aplicada:

```
$ node scripts/governance/required-always-run.mjs
REQUIRED ALWAYS-RUN — 46 contexts required · 46 always-run · 0 FILTRADO(s)
                    · 0 SEM `synchronize` · 0 não-resolvido(s)
✅ todo context required nasce em todo PR — e RE-nasce em todo push.
```

O `name:` do job **não** foi tocado (já não continha `(advisory)`), então não há dança
zero-window do P14 a fazer.

## Ordem de operações — e por que o flip vem DEPOIS do merge

A entrada de 2026-08-08 no baseline recomenda *"flip do vivo PRIMEIRO, baseline DEPOIS"*, para não
deixar o `protection-drift` 🔴 numa janela sem quem consertasse. **Aqui a ordem se inverte, por
uma razão dura:** aquela receita pressupõe um workflow que **já era** always-run. Este não era.
Flipar antes do merge tornaria required, no `main`, um check que o `main` ainda não sabe gerar em
todo PR — deadlock imediato para todo PR que não toque `resources/js/**`.

Portanto:

1. **Merge deste PR** (workflow always-run + baseline + ADR + `_HOOKS-INDEX` regenerado).
2. **Flip do vivo**, `POST` aditivo em `required_status_checks/contexts`, com
   `gh api --input <arquivo UTF-8 sem BOM>`. O context tem `·` (U+00B7) e `ó` (U+00F3):
   payload inline no shell do Windows vira mojibake e cria um required que **nenhum check-run
   satisfaz** (incidente 2026-07-02, 23 contexts corrompidos, merge travado).
3. **Validar** com `node scripts/governance/protection-drift.mjs` — string-exata, não contagem
   (a contagem não muda com mojibake).
4. **`gh pr update-branch` nos PRs abertos**, para que carreguem o workflow always-run e o check
   nasça no head SHA deles.

Entre (1) e (2) o `protection-drift` fica 🔴 `required SUMIU do vivo` — **esperado**, é a janela
de reconciliação, e fecha em minutos. O gate `protection-drift` não é required.

## Consequências

- **Boa:** uma tela nova não consegue mais adotar o header antigo em silêncio. O ratchet F4 passa
  a ser lei, não sugestão.
- **Custo:** ~19s de job por PR (Node puro, sem DB, sem rede).
- **Atrito honesto:** um PR que legitimamente precise adotar o header antigo passa a exigir
  decisão explícita. Hoje esse caso não existe — o canon v3.8 é o destino declarado da F4.
- **Reversão barata:** `gh api -X DELETE .../required_status_checks/contexts` com o mesmo payload
  (46→45) + PR de demoção editando o baseline (ADR 0275 §5). **Primeiro falso-positivo real
  rebaixa a advisory** — a barra de reversão é baixa de propósito.

## Alternativas consideradas

- **Deixar advisory e confiar na disciplina.** Refutada pela medição: 2 adotantes novos em 5 dias,
  ambos mergeados com o vermelho na tela.
- **Promover sem tocar no gatilho.** Seria o deadlock de 2026-08-05→08 reencenado — o
  `required-always-run.mjs` reprovaria, e com razão.
- **Migrar as 47 telas restantes antes de promover.** Inverte a ordem: o ratchet existe
  justamente para que a dívida **pare de crescer** enquanto a migração acontece. Promover agora
  congela o denominador; migrar é trabalho separado e contínuo.
