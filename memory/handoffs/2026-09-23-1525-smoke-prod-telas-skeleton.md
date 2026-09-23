---
date: "2026-09-23"
time: "15:25 BRT"
slug: smoke-prod-telas-skeleton
tldr: "Smoke em produção (biz=1, commit ef330df535) das 4 telas que saíam do skeleton eterno: Metas, Tipos e Modelos de dispositivo passam — o partial reload do navegador volta Inertia com a prop adiada; Logs do Officeimpresso serve Blade (flag desligada em prod) e não exercita o conserto."
decided_by: [W]
cycle: null
prs: [7769, 7780]
next_steps:
  - "Logs do Officeimpresso: o conserto do #7780 só age quando a flag useV2OfficeimpressoLogs for ligada no GrowthBook — fazer o smoke nesse dia"
  - "US-FIN-069 segue aguardando decisão [W] (ADR 0411)"
---

# Handoff — smoke em produção das telas do skeleton eterno

Fecha o primeiro próximo passo do [handoff de 15:02](2026-09-23-1502-skeleton-inertia-e-hooks-de-commit.md) (append-only: aquele arquivo não se edita).

## Estado MCP no momento
- Nenhum cycle ativo em COPI; nenhuma task ativa para @wr23 (consultado no handoff de 15:02, sem mudança nesta janela).

## O que aconteceu
1. **Deploy conferido antes do navegador.** Por SSH na Hostinger: produção em `ef330df535` (o `main`), e os 4 controllers com a guarda `ajax() && ! inertia()`.
2. **Smoke pelo navegador, biz=1.** Em cada tela: duas leituras do DOM com intervalo (números estáveis), a resposta do **partial reload** que o próprio navegador fez (com `X-Requested-With`), e screenshot.

| Tela | Resultado | Prova |
|---|---|---|
| Metas `/hrm/sales-target` | ✅ 8 colaboradores, 0 skeleton | partial voltou Inertia `Essentials/Metas` com `paginator` de 8 registros |
| Tipos de licença `/hrm/leave-type` | ✅ estado vazio | partial voltou Inertia `Essentials/Tipos` com `tipos: []` (biz=1 não tem tipos) |
| Modelos de dispositivo `/repair/device-models` | ✅ KPIs zerados + estado vazio | partial voltou Inertia `Repair/DeviceModels/Index` com `models` e `kpis` — **era a que estava quebrada em prod** |
| Logs `/officeimpresso/licenca_log` | ✅ carrega, **não exercita o conserto** | flag `useV2OfficeimpressoLogs` desligada em prod: serve Blade, lista montada no servidor (sem o ramo `ajax()`) |

Nas três primeiras a prova é o **formato** da resposta, não a quantidade de linhas: antes do conserto, esse mesmo pedido devolvia o JSON do DataTables e a tela ficava no skeleton.

## Artefatos gerados
- Só este handoff e a linha no índice. Nenhum código mudou.

## Persistência
- git: este arquivo + `memory/08-handoff.md`.

## Próximos passos pra retomar
- Ligar a flag do Logs só com smoke da versão Inertia no mesmo dia.

## Lições catalogadas
- Tela com pouco dado não prova nada pela contagem de linhas: a evidência que discrimina é a resposta do partial reload.

## Pointers detalhados
- Conserto e prova de mordida: [#7769](https://github.com/wagnerra23/oimpresso.com/pull/7769) e [#7780](https://github.com/wagnerra23/oimpresso.com/pull/7780).
