---
date: "2026-08-17"
time: "16:15 BRT"
slug: financeiro-prototipo-ja-aplicado-boletos-aposentado
tldr: "[W] pediu aplicar o protótipo do Financeiro. Medido: já está aplicado e o vivo está à frente nas 7 telas ancoradas. As 3 frentes que ofereci evaporaram sob medição — âncoras estavam certas, Boletos foi aposentada por [W] em maio com ADR, régua de cobrança é mockup de automação inexistente. Entregue: o dead code que sobrou daquela deleção (259→63 ln) + lápide §5 da classe. 2 PRs mergeados."
prs: [5871, 5872]
decided_by: [W]
related_adrs: [0144-paymentgateway-extracao-camada-cobranca, 0344-two-strikes-cobre-processo]
next_steps:
  - "Decidir o POST /boletos/{id}/cancelar: carência de 60d venceu em 2026-07-18 (há 30 dias)"
  - "Escolher direção pra igualar design×produção (escrita no DesignSync é gated, ADR 0315)"
  - "Opcional barato: fechar o espelho local — 121 arquivos sem veredito, inclui financeiro-relatorios.jsx que nunca desceu"
---

# Handoff — o protótipo do Financeiro já estava aplicado, e o trabalho era limpar o resto

## Estado MCP no momento do fechamento

**MCP inalcançável a sessão inteira** — `ToolSearch` por `cycles-active`/`my-work`/`whats-active`
devolveu `No matching deferred tools found`: **não havia canal**, não é passo pulado. É a **4ª
sessão seguida** nessa condição (os handoffs de 08-15 20:35, 08-16 05:40 e 08-16 10:54 registram o
mesmo). Operei em fallback filesystem ([`how-trabalhar.md`](../how-trabalhar.md) §Fallback).

Consequência honesta: **não sei** se existe task na fila cobrindo o que fiz — isso é
**não-auditável nesta sessão**, nunca "não existe".

Sessão irmã hoje: [`2026-08-17-1330-espelho-remendo-payload-e-flip`](2026-08-17-1330-espelho-remendo-payload-e-flip.md)
tocou o **mesmo espelho Cowork** — provável causa do `--sla` parcial que medi (rodada de hoje
12:51Z mediu 0 de 121).

## O que aconteceu

[W] pediu *"aplicar o protótipo do módulo financeiro"* + inventário de telas/seções/componentes.

**A medição inverteu o pedido.** O protótipo **já está aplicado** e o vivo está à frente nas 7
telas ancoradas — Unificado 3090 ln × 2002 do protótipo, Fluxo 562×156, DRE 535×128, Conciliação
351×121, Impostos 270×159, PlanoContas 215×76. Dos 34 componentes do protótipo, **32 estão no
vivo**, vários renomeados com a referência documentada no próprio código (`DrawerLens` diz
*"Referência F1: LensSection em financeiro-page.jsx"*; `FinNovoLancamento`→`TituloCreateSheet`,
`CrossLinkChips`→`FinCrossLinkify`, `ContasFilter`→`FinMultiSelectContas`).

**Ofereci 3 frentes a [W]. As 3 caíram ao serem medidas** — cada uma por um dono que eu não tinha
consultado antes de falar:

1. *"consertar as 7 âncoras cegas"* — o `anchor-content-check` (**required**) resolve as 7 e sai
   `EXIT 0` (`podre: 0 · ✓ ok: 15`). Cego estava o `ancora.mjs`, **o instrumento que eu usei pra
   diagnosticar**, e cego **de propósito**: 2 BITE tests do selftest dele afirmam que *"path com
   sufixo entre parênteses NÃO é lido"*. Li `⚠️ NÃO MEDIDO` como defeito do artefato — com a linha
   seguinte dizendo *"Zero fantasma aqui é AUSÊNCIA DE MEDIÇÃO, não saúde"*.
2. *"criar a tela de Boletos"* — **deletada por decisão de [W] em 2026-05-19**, substituída pela
   Cobrança (Cowork F1.5 **96/100**; ADR 0144 + 0170), `GET /boletos` é 301. E o
   `OrphanRenderGateTest` **já** listava o alvo com a ação escrita: *"Limpar dead code: task separada"*.
3. *"aplicar a régua D+0/D+3/D+8"* — o próprio protótipo escreve embaixo dela *"Automação proposta
   — o disparo real fica no módulo Cobrança"*. Mockup de automação inexistente; o vivo já trocou
   por estado real de boleto.

O trabalho real apareceu do item 2: **o resíduo daquela deleção**. `BoletoController::index()`
inalcançável (nenhuma rota GET; a única é o `POST cancelar`) renderizando uma page deletada, mais
5 privados que só ele chamava.

Depois do merge, [W] perguntou **se o design já copiou as funções da produção**, pra igualar os
dois. Medido via `DesignSync`: **sim, copiou — e a foto é de 31/05**. Detalhe na seção própria.

## Artefatos gerados

| PR | O quê | Δ |
|---|---|---|
| [#5871](https://github.com/wagnerra23/oimpresso.com/pull/5871) `45c823f0e` | `BoletoController` **259 → 63 ln** (`index` + `shapeRemessa`/`kpis`/`funil`/`listarContas`/`bancoShort`) + entrada fora da `ORPHAN_RENDER_ALLOWLIST` | +28 −218 |
| [#5872](https://github.com/wagnerra23/oimpresso.com/pull/5872) `d37bc2c4e` | Lápide §5 + LC-08 98→99 | +13 −1 |

Preservado o `cancelar()` — a rota 301 não o cobre e `BoletoRemessa` segue vivo
(`CnabDirectStrategy`, `TituloService`, `ContaBancaria`, `Config/retention`).

## Persistência

- **git:** 2 PRs mergeados em `origin/main`, branches remotas e locais removidas
- **§5:** fonte `licoes-rejeitadas.md` (append-only) → `proibicoes.md` regravado por
  `sec5-derive.mjs --write` (`--check` **1 → 0**; 125 limites, **0 perdidos**)
- **ledger:** LC-08 → 99; `licoes-code-two-strikes.mjs` lê `99x` e o selftest dele passa
  (**LC-22 cumprido** — rodei o consumidor *com* a mudança, não reli o texto)
- **MCP:** não propagado — sem canal (ver Estado MCP)

## Design × produção — a resposta medida (pergunta de [W] pós-merge)

`_arquivo/repo-mirror/` no projeto Cowork tem **77 arquivos** = a árvore de `Pages/Financeiro/`
(Unificado + **22 componentes** + `_lib/forma-pagamento.ts`, Dre, Fluxo, Conciliação, PlanoContas,
Cobrança). **Todos os 114 paths estão dentro de `_arquivo/`** (0 fora) — arquivado, não é fonte viva.

E é **datado**: o charter copiado tem `charter_version: 13` / `last_validated: 2026-05-31`; o vivo
tem `charter_version: 22` / `2026-07-13`. **9 versões de diferença** e 3 US que produção ganhou
depois — **US-FIN-029** (as 3 lentes; no mirror aparece no *Backlog* como "aprovada, não aplicada",
no vivo está implementada), **US-FIN-031** (bulk ≤500 títulos, PR #3905), **US-FIN-038** (pill
"Conta indefinida", 14/07). No nível de componente: vivo tem `FinPeriodBar` e
`FinPillContaIndefinida` que o mirror não tem; o `FinSubNav` do mirror virou
`_shared/FinanceiroSubNav.tsx`.

**Não escolhi direção** — igualar Design←Produção é **escrita** no DesignSync, gated por opt-in
([ADR 0315](../decisions/0315-design-sync-claude-design-vs-cowork-charter.md)).

## Próximos passos pra retomar

```
node scripts/governance/cowork-mirror-freshness.mjs --sla
```

Decisões abertas, em ordem de barateza:
1. **Espelho local** (leitura, sem opt-in): `--sla` diz `0 sync · 0 stale · 121 unchecked · mediu
   0/121`. Nenhum arquivo tem veredito ⇒ toda comparação hoje roda às cegas. Traria junto o
   `financeiro-relatorios.jsx`, **vivo no Cowork e que nunca desceu** (LIVE-ONLY).
2. **`POST /boletos/{id}/cancelar`** — `Routes/web.php` declarou preservá-lo por 60d desde
   2026-05-19; venceu **2026-07-18**, há 30 dias. Decisão [W].
3. **Direção do igualar** design×produção (item acima).

## Lições catalogadas

- **§5 nova** (2026-08-17, mergeada em #5872): *tratar PRESENÇA de protótipo no espelho como
  DEMANDA de tela*. Espelho é **retrato do Cowork, não backlog** — contém tela aposentada, mockup
  não-construído e módulo vizinho, todos legitimamente. Antes de propor tela a partir de protótipo:
  *o alvo ainda é desejado?* + *o vivo já não está à frente?*, com recibo.
- **NÃO virou gate** ([ADR 0344](../decisions/0344-two-strikes-cobre-processo.md) two-strikes): a
  defesa certa já existe e **já tinha mordido** — o `OrphanRenderGateTest` tinha o veredito escrito
  na allowlist. Faltou eu consultá-la: falha de execução, não ausência de ferramenta. Gate novo
  duplicaria régua consolidada (§5 2026-07-09) e o predicado é semântico (ADR 0224 — hooks
  block-vs-advisory; o slug real dela contém ponto e não casa o pattern do `related_adrs` deste
  schema, por isso está citada aqui e não no frontmatter).
- **Erros de sonda meus, pegos por controle positivo:** pathspec `Modules/*/Http/Controllers/`
  devolveu **0** (o `BoletoController` existia) — `:(glob)` devolve 18; `$?` depois de pipe é do
  `tail`, não do `node` (li "EXIT=0" num selftest que saía **1**); `TZ=America/Sao_Paulo date` não
  converte no Git Bash (BRT saiu = UTC), conferido com `Intl`.
- **Achado sem dono — `module-grades-gate` colapsa "não entreguei o relatório" em "reprovei":** o
  cálculo dava `✅ all clear` e o job morria com `HTTP 503` no `POST` do comentário. **3 reruns**
  até passar, num gate **required**. Família da §5 2026-07-29 (instrumento afirma estado do objeto
  quando o que falhou foi a medição), no eixo da **entrega**. Não toquei — workflow required, fora
  do pedido.
- **O `block-memory-drift` me barrou 2× no PRÓPRIO rascunho** (Edit e Write) e o `block-destructive`
  barrou o `rm` dele — FP por construção (arquivo untracked desta sessão, não história de ninguém),
  o mesmo já registrado no handoff de 08-16 05:40. Segui o caminho que o hook sanciona (**arquivo
  novo, nome novo**: `1611` → `1615`), **nunca o override Tier 0**, que é do [W]. O `1611` fica
  untracked e não entra no commit.
- **Aberto e não meu:** selftest do `ancora.mjs` está **vermelho no `main`** (`EXIT 1`, `[FAIL]
  BITE real … âncora da Jana`), no `design-memory-gate.yml:250`, que é **advisory** — por isso
  ninguém viu. E `ProvaViva.charter.md` declara `design-handoff "Financeiro - Prova Viva
  (primitivos).html"` sem path, com espaços/parênteses no nome: fica fora dos 18 resolvíveis até do
  gate required.

## Pointers detalhados

- Session log: [`2026-08-17-financeiro-prototipo-medido-e-o-boletos-aposentado.md`](../sessions/2026-08-17-financeiro-prototipo-medido-e-o-boletos-aposentado.md)
- Lápide completa: `memory/licoes-rejeitadas.md` §"2026-08-17 — Tratar PRESENÇA de protótipo…"
- Gate que já sabia: `tests/Feature/Architecture/OrphanRenderGateTest.php`
- Sessão irmã (mesmo espelho, hoje): [`2026-08-17-1330-espelho-remendo-payload-e-flip.md`](2026-08-17-1330-espelho-remendo-payload-e-flip.md)
