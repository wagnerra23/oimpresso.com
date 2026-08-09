---
date: "2026-08-09"
time: "23:00"
slug: jana-o-retrato-ja-existia-e-zero-metas
tldr: "Pedido [CC] de 8 ondas pra migrar telas da Jana virou 5 PRs e três achados que mudam a conclusão: NENHUMA meta existe (0 em biz=1, 0 cross-business), o retrato que a Onda 0 queria construir JÁ RODA (migracao:report — Crm 47 endpoints Blade contra 8 da Jana, 9º lugar), e o brief do negócio não tem cron nem histórico. Onda 5 (verdade nos botões) e A-1 em produção com smoke. Ondas 6-12 sem justificativa por ora."
prs: [5495, 5496, 5498, 5502, 5503]
us: [US-COPI-031, US-COPI-061, US-COPI-148]
next_steps:
  - "[W] abrir o chat em biz=4 e digitar brief — única amostra com dado real (biz=1 tem 0 venda e 0 meta)"
  - "[W] decidir o PLAN-MWART-metas (draft-aguardando-aprovacao desde 2026-05-09); arquivar é resposta legítima com 0 metas cadastradas"
  - "[W] decidir a âncora contaminada: chat-jana.jsx tem 8 ocorrências de Frota/caçambas; conserto é na fonte, escrita gated (ADR 0315)"
  - "Consertar os 3 defeitos de curadoria do brief (linha fabricada, promessa 'próximo brief 8h' sem cron, entusiasmo sobre zeros) — não dependem de amostra nova"
  - "#5501 (outra sessão) segue bloqueado no ledger-check GT-G5 — NÃO tocar, eles estão ativos e já reconciliaram com o #5502"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0093-multi-tenant-isolation-tier-0
---

# Handoff — Jana: o retrato já existia, e nenhuma meta foi cadastrada

> **Por que existe:** [W] colou o pedido [CC] `JANA-ONDAS-PR-2026-08-09` (rev1 e depois rev2), propondo 8 ondas de PR pra fechar o módulo. A sessão terminou com conclusão oposta à premissa do pedido — e com o que sustenta isso em git.

## O que entrou em produção

| PR | O quê |
|---|---|
| [#5495](https://github.com/wagnerra23/oimpresso.com/pull/5495) | Reconciliação da rev1 — ondas 8/10/11 apontadas pros donos existentes |
| [#5496](https://github.com/wagnerra23/oimpresso.com/pull/5496) | **Onda 5 — verdade nos botões** + guarda + âncoras reconciliadas |
| [#5498](https://github.com/wagnerra23/oimpresso.com/pull/5498) | Plano de teste por uso + resultado do 1º teste |
| [#5502](https://github.com/wagnerra23/oimpresso.com/pull/5502) | `A-1` — o Blade para de se esconder no `SUPERFICIE.md` |
| [#5503](https://github.com/wagnerra23/oimpresso.com/pull/5503) | Triagem da rev2 |

**Onda 5** (smoke real em prod, biz=1): `reapurar` despacha de verdade — com a `Meta` carregada pelo global scope **antes**, porque `MetaApuracao` não tem `business_id` e despachar a partir do `$id` cru vazaria entre tenants; `alertas/config` parou de responder *"Configuração salva."* sem salvar; "STUB spec-ready" saiu de 3 telas. Guarda em `JanaViewsSemAndaimeTest`, ligado **nos dois pontos** da lane (§5 2026-08-02), FP medido em 0 antes.

⚠️ **A metade "apaga MetaApuracao do range" do DoD da US-COPI-031 ficou ABERTA de propósito:** a rota não recebe range, e apagar tudo pra reexecutar só `now()` destruiria as 12 janelas que a US-COPI-011 exige. Declarado no código e na âncora. Fechar exige rota nova — decisão [W].

## Os três achados que mudam a conclusão

**1. Nenhuma meta existe.** 0 em `biz=1`, **0 cross-business** (tela superadmin: *"Nenhum cliente configurou metas ainda"*), 0 hits em 30d. `ApurarMetaJob`, drivers, 12 janelas, farol e projeção nunca tiveram um dado pra processar. As ondas 8/10/11 migrariam telas de uma feature que ninguém jamais cadastrou.

**2. O retrato que a Onda 0 queria construir em 4 PRs já roda.** `npm run migracao:report` (a rev2 o declarava morto — **refutado**, `rc=0`, 50 linhas):

| Módulo | Endpoints servindo Blade |
|---|---|
| **Crm** | **47** |
| Essentials · Repair · Superadmin | 38 · 28 · 25 |
| **Jana** | **8** (9º lugar) |

A decisão §6 #0 do pedido (*"a Jana continua sendo a prioridade?"*) tem resposta: **não**.

**3. O brief do negócio não tem cron nem histórico.** É sob demanda — nasce ao digitar `brief` no chat. A proposal [`brief-se-divide-em-dois`](../decisions/proposals/2026-07-30-brief-se-divide-em-dois.md) já era dona da distinção vs o `brief-fetch` de governança.

## O teste da categoria 1 (rodado)

Acertou o que importa: cliente real com **412 dias de ausência** e mensagem de WhatsApp pronta — verificada em `/cliente` (existe, selo *"distante · há 1a"* bate). Errou em três: linha fabricada (`PRODUTO BEST-SELLER · saídas 0` com conselho por cima), **promessa falsa** no rodapé (*"próximo brief: amanhã, 8h"* — não há cron; classe LC-15 na cara do cliente) e entusiasmo sobre zeros.

**Limite:** rodou numa empresa vazia. Quem tem dado é `biz=4`, e a R6 o proíbe em teste — só [W] pode olhar.

## Bloqueadores da Jana (medidos, não lidos)

`gpt-4o-mini` (o config registra `gpt-4o` → **403 sem acesso**) · `context_recall_avg` **0,3839** (real, n=51) · `JANA_CLARIFY_ENABLED` **OFF**. ⚠️ O selo RAGAS verde dos PRs é **mock** (0,850); o real é **0,69**. E a Jana **lê e fala, não age** — a ADR 0145 tem 0 commits.

## Estado MCP no momento do fechamento

- `my-work`: **6 tasks em REVIEW** (US-TR-309/310/305/306, US-PROD-027, US-INFRA-023) — nenhuma tocada nesta sessão.
- `cycles-active`: **não respondeu** (MCP error -32001, timeout). Declarado em vez de inventado.

## O que NÃO fazer

- Não reabrir as ondas 6-12 sem novo sinal — o retrato põe a Jana em 9º e não há meta cadastrada.
- Não reconstruir a Onda 0: `migracao:report` já faz o que ela pede (`C-10` do próprio pedido manda estender, não recriar).
- Não editar `prototipo-ui/cowork/` à mão pra limpar Frota/caçambas — é **espelho**, e o comparador diz *"ninguém o edita à toa"*.
- Não tocar o [#5501](https://github.com/wagnerra23/oimpresso.com/pull/5501): outra sessão, **ativa**, já reconciliada com o #5502.

## Erros meus nesta sessão (registrados porque a lição é o método)

Quatro achados vieram de conferir minhas próprias afirmações contra a fonte, não de reler o que escrevi: o `4 hits` que media **rota web** e não o módulo (armadilha do `Modules/Auditoria`); confundir o brief de governança com o do negócio; chamar de incidente uma tela branca que era **endpoint AJAX**; e escrever duas frases falsas no plano de teste que o próprio teste derrubou. Mais dois de instrumento: contar falhas de CI por **substring** (casou o nome de um check que passava) e `per_page=100` num commit com **107** check-runs. E o `Dedup-ack` que escrevi dentro de um heading — a regex exige início de linha, então o escape ficou inerte.
