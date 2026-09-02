---
date: "2026-09-02"
time: "08:04 BRT"
slug: fiscal-onda0-e-consertos-de-gates
tldr: "Onda 0 do Fiscal fechada (10 de 11 PRs mergeados). O PR-F0 corrigiu 3 premissas do plano antes de qualquer codigo. Dos 7 buracos de gate achados, 2 consertados (eram base desatualizada), 1 aguarda ok visual do [W], 4 abertos — incluindo CU-FISC-16, que faz a producao mostrar dado de demonstracao como se fosse real."
prs: [6511, 6513, 6514, 6515, 6516, 6517, 6518, 6519, 6520, 6522, 6523]
us: ["US-FISCAL-022"]
next_steps:
  - "Ok visual [W] do gate F1.5 (Ponto/Dashboard) -> workflow_dispatch modo update"
  - "Decidir CU-FISC-16: marcar procedencia, flag, ou Non-Goal"
  - "CT 100 em 502 — nenhuma sessao roda teste fora do CI"
---

# 2026-09-02 08:04 BRT — Fiscal Onda 0 fechada, e o que os gates mostraram no caminho

> Sessão iniciada em 2026-09-01 com um plano de 7 ondas / 22 PRs colado pelo [W] (produzido
> pelo Claude Design). A Onda 0 fechou. O resto virou chip ou decisão [W].

## ⚠️ Estado MCP no momento do fechamento

**Não consultado — servidor indisponível a sessão inteira** (o hook `brief-fetch` caiu em
fallback por timeout no SessionStart e não voltou). Logo: `cycles-active`, `my-work`,
`sessions-recent` e `decisions-search` **não** têm snapshot aqui. Isto é declarado, não
omitido — o checklist MCP-first do [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md)
ficou por cumprir, pela mesma razão do handoff das 12:00 do dia anterior.

## O que entrou (10 de 11 PRs; base `203c49ba3c`)

| PR | O quê |
|---|---|
| #6511 | **PR-F0** — mede `screen-coverage` do Fiscal e corrige 3 premissas do plano |
| #6513 | **PR-F2** — contrato de tela das 7 telas (âncora + copy + ordem), bite-test provado |
| #6515 | errata do F0 — "1/7 Pest Browser" era crédito, não cobertura |
| #6519 | **PR-F1 residual** — smoke autenticado nas 4 telas sem cobertura Browser |
| #6514 | destrava a lane do Fiscal (allowlist MySQL +10 arquivos) |
| #6516 · #6517 | Onda 1 — token roxo + `Nfe` troca `fx-*` pelas primitivas do DS |
| #6518 · #6523 | watchdog/RAGAS — runner distingue "não medi" de "regrediu" |
| #6520 · #6522 | Onda 2 — 6 dos 8 CU sem UC + `US-FISCAL-022` |

`#6517` ficou OPEN aguardando CI (0 required falhos) quando esta sessão fechou.

## Os 3 achados do PR-F0 que mudaram o plano

1. **`PR-F1` encolheu.** O plano tratava "baseline VRT das 7 telas" como pré-requisito da
   Onda 1. Ela **já estava commitada** (7/7 no `visreg-screens.json` + os 7 `.snap`).
2. **`PR-B3` (a lane) era o item de maior alavancagem**, não o 4º da Onda 2: **15 de 21**
   arquivos de teste do Fiscal chamavam `markTestSkipped`. A lane dava verde medindo uma
   fração do módulo.
3. **O gate `fiscal.nfe.view` já tinha teste** (`UC-FNFE-08`, com controle negativo) — o
   plano dizia que não. Faltava a lane rodá-lo.

## Os buracos de gate — 2 consertados, 4 abertos

| # | Buraco | Estado |
|---|---|---|
| 3 | `Governance Gate` (backlog drift) | ✅ **era base desatualizada** — `update-branch` |
| 4 | `casos-ratchet` (`stale: Nfe.casos.md`) | ✅ idem |
| 6 | baseline `Ponto/Dashboard` `0.2115%` | 🟡 **diagnosticado, aguarda ok visual [W]** |
| 5 | watchdog de crons | 🟡 #6518/#6523 entraram; vermelho só limpa no próximo agendamento |
| 2 | espelho de design 3 ciclos atrás | 🔴 depende do Cowork (pedido formal enviado) |
| 7 | **CT 100 em `502`** | 🔴 **não voltou** — nenhuma sessão roda teste fora do CI |
| 1 | **`CU-FISC-16`** | 🔴 **intocado** — ver abaixo |

### #6 — o diagnóstico completo (para não refazer)

`Dashboard` foi promovido ao topo da sidebar como **"Visão geral"** e os grupos ganharam
contador (`CADASTRO 2 · COMERCIAL 1 · SISTEMA 2`). O conteúdo da tela é **idêntico**.
Prova cruzada em 3 pontos: `snap-diff` (colunas 0-1, linhas 0-14 = faixa da sidebar) ·
diff-view olhado · **a produção viva tem a sidebar do ATUAL**. A baseline é que está velha.
Registrado no [#6519](https://github.com/wagnerra23/oimpresso.com/pull/6519#issuecomment-5501025354).
Falta: aprovação visual [W] → `workflow_dispatch` modo update.

### #1 — `CU-FISC-16`, o que engana quem opera

Medido na produção viva (`/fiscal`): o header diz **"0 notas"**, a lista mostra **10**, o chip
diz **18**. São três props distintas (`Cockpit.tsx:125,128,129`) e a do meio se chama
`notasMock`. Somado ao alerta real **"Certificado vencido há 26 dias"**, a leitura natural de
quem abre a tela é "está tudo funcionando" — e não está. A âncora de design é coerente
consigo mesma (deriva o header da lista, `fiscal-page.jsx:450`); a produção mistura fonte
real com mock. **Não é divergência de design — é bug de integração**, e tem nome no backlog:
`CU-FISC-16` (um dos 8 CU sem UC). Decisão [W] pendente.

## Erros meus nesta sessão (registrados, não escondidos)

- **Li crédito como cobertura, 3×** (E2E como união `Browser ∪ VRT`; "1 creditado" como
  "1 exercitado"; supus que #6520 destravaria a Onda 1). Todos pegos por medição, não por
  revisão. Classe LC-08. A errata do 2º virou o #6515.
- **Quase fabriquei crédito**: no #6519, meu comentário citava paths de tela, e o mapa credita
  por menção — media `+6` em vez de `+4`. Corrigido, com advertência no próprio comentário.
- **Dois jobs meus disputaram o mesmo arquivo temporário**: apaguei um `.json` que o outro
  ainda lia, e o `|| echo "99:99"` virou "99 required FALHANDO" — sentinela de erro lida como
  veredito. Corrigido com arquivo por PR + falha de leitura tratada como *pular*.
- **Ia refazer trabalho de outra sessão** (o `last_run` do #6517 já estava consertado com nota
  de revalidação). Abrir o arquivo antes evitou o conflito.
- **Tratei `CONFLICTING` do GitHub como fato**: era stale (assíncrono). Localmente não havia
  conflito nenhum. Teria "resolvido" um conflito inexistente.

## Chips abertos

`lane` · `Onda 1` · `CU→UC` (os três terminaram e viraram PR) · `watchdog de crons`.

## Próximo passo sugerido

Três decisões [W] destravam o resto: **(a)** ok visual do #6 (5 segundos); **(b)** `CU-FISC-16`
— marcar procedência, esconder atrás de flag, ou declarar Non-Goal; **(c)** as 7 decisões da
Onda 3+ do plano de 7 ondas, das quais #3 e #4 encolheram para *reancorar* (a capacidade
existe, a âncora é que não cobre).
