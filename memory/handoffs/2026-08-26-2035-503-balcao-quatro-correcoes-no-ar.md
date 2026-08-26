---
date: "2026-08-26"
time: "20:35"
slug: "503-balcao-quatro-correcoes-no-ar"
tldr: "O 503 que a ROTA LIVRE fotografou era o nosso deploy (76 janelas em 7 dias, mediana 78s). PR #6309 mergeado e deployado com smoke real capturado na janela de produção. 3 propostas minhas foram refutadas por adversários antes de virar código, e eu mesmo deixei um 1-de-3 que virou o #6330."
prs: [6309, 6330]
next_steps:
  - "Mergear #6330 (o 1-de-3 que faltou nos failsafes do deploy)"
  - "Decidir #6316 (baseline de clientes · dark) — [W] apontou que deve resolver"
  - "Decisão B: encolher a janela de 80s, cuja peça central é remover --classmap-authoritative"
---

# Handoff 2026-08-26 20:35 BRT — O 503 do balcão era o nosso deploy

> Append-only ([ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md)). Não editar depois.

## Onde o trabalho parou

**[PR #6309](https://github.com/wagnerra23/oimpresso.com/pull/6309) mergeado por [W] às 19:27Z e
deployado com sucesso às 20:26Z.** Quatro correções vivas em produção, com prova capturada na
janela real de manutenção — não narração.

Narrativa completa, os 3 laudos adversariais e as 4 pesquisas em
[`sessions/2026-08-26-503-balcao-larissa-janela-de-deploy.md`](../sessions/2026-08-26-503-balcao-larissa-janela-de-deploy.md).

## O achado

A cliente piloto reclamou de `503` no meio da venda e passou a **anotar código de produto no
papel**. Medido: **76 janelas de 503 em 7 dias** (mediana 78s, p90 89s, máx 101s, ~12,3 min/dia),
todas do próprio `deploy.yml` via `php artisan down`. Quatro delas na manhã dela, entre 08:34:13
e 09:45:17 BRT.

## Evidência do smoke (capturada às 20:25:38Z, 3s depois da janela abrir)

```
HTTP/1.1 503 Service Unavailable
Retry-After: 60
Refresh: 15                              <- era 60
'Estamos atualizando o sistema':  1      <- a página nova
'rascunho da venda':              1
'Service Unavailable' (padrão):   0      <- a do framework NÃO foi servida
```

Janela `20:25:35Z → 20:26:55Z` = **80s**, coerente com a mediana levantada. Bundle publicado
contém `oi-deploy-503` (controle positivo: 19 ocorrências da string antiga).

## Decisões [W] nesta sessão

| decisão | efeito |
|---|---|
| **"módulo não muda de pasta"** vale para o caminho absoluto | diretório de release **fora**; alvo passa a ser encolher a janela para ~10–20s, não zerá-la |
| **Não segurar deploy em horário comercial** | velocidade de entrega é valor deliberado; resolve-se pelo lado técnico |
| **`visual-regression` já estava demovido a advisory** (#6278, mesma data) | o vermelho é ruído, não bloqueio |

## Dívida deixada, explicitamente

- **[#6330](https://github.com/wagnerra23/oimpresso.com/pull/6330) aberto** — existem **3**
  invocações executáveis de `artisan down` no `deploy.yml`; o #6309 consertou **1**. As outras
  duas são os failsafes, e o re-`down` delas **sobrescreve** `maintenance.php` com versão sem
  template, desfazendo a página no caminho em que o framework está quebrado.
- **[#6316](https://github.com/wagnerra23/oimpresso.com/pull/6316)** regenera a baseline de
  `clientes · dark`, cuja origem é o #6303 (mapa Google→OSM) ter atualizado só a suíte de pixel.
  ⚠️ O `required-checks-baseline.json` registra que **rebake não fechou o gap** para
  `financeiro-unificado` (render não-determinístico) — se o `0.1791%` voltar idêntico, é a mesma
  doença e baseline não é o remédio.
- **Nenhum smoke de comportamento com sessão logada.** O conserto do rascunho foi provado
  *presente no bundle*, não *exercitado na tela* — isso exige credencial e é de [W].

## Estado MCP no momento do fechamento

Não consultado nesta sessão (trabalho entrou por incidente de cliente via WhatsApp, não por task
do backlog). O que existe de estado vivo está nos 2 PRs abertos acima.
