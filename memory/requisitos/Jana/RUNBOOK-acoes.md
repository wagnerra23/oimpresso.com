---
id: requisitos-jana-runbook-acoes
slug: jana-runbook-acoes
title: "Jana — Runbook da tela Ações (/ia/acoes)"
type: runbook
module: Jana
tela: Jana/Acoes
owner: W
status: ativo
date: "2026-09-02"
last_validated: "2026-09-02"
related_adrs:
  - 0052-memoria-jana-3-angulos-faturamento
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0180-sidebar-v3-hierarquia-canonica
preconditions:
  - "Usuário autenticado num business com `jana.access` (o grupo /ia já garante o gate)"
  - "`SellsCockpitAggregator::buildInsightsAggregates` responde pro business (é o agregado da prévia)"
steps:
  - "Abrir /ia/acoes autenticado: a aba Ações acende no JanaAreaHeader (ghost `acoes`, 4ª posição)"
  - "Conferir as 5 ações de `AcaoHitlService::ACOES` na aba 'sugeridas', cada uma com prévia do servidor e o chip envio/leitura"
  - "Clicar no CTA 'Revisar …' → modal HITL (o mesmo do Painel) → Aprovar → toast global + a ação migra pra 'aprovadas' com quem/quando"
  - "Em 'aprovadas', 'Ver o recibo' mostra a prévia GRAVADA (a do servidor no instante da aprovação) + contexto"
  - "Conferir isolamento Tier 0: recibo de outro business nunca aparece"
---

# RUNBOOK — Ações da Jana (`/ia/acoes`)

> **Tipo:** runbook reproduzível
> **Irmãos:** [`Acoes.charter.md`](../../../resources/js/Pages/Jana/Acoes.charter.md) (lei) · [`Acoes.casos.md`](../../../resources/js/Pages/Jana/Acoes.casos.md) (contrato UC) · [`jana-acoes.contract.json`](../../../prototipo-ui/contrato/jana-acoes.contract.json) (copy pinada)
> **Âncora de design:** `prototipo-ui/cowork/jana-telas-novas.jsx` §`JmAcoesFila` (a aba vive no `JmTabs` de `jana-merge.jsx`). Resolva por `node prototipo-ui/ancora.mjs Jana/Acoes`.
> **Validado:** **estático** contra `origin/main` em 2026-09-02 — rotas, `AcaoHitlController`, `AcaoHitlService`, `jana_acao_aprovacoes` e o protótipo conferidos arquivo a arquivo.
> ⚠️ **Fluxo vivo contra prod NÃO exercitado nesta data.** O smoke real com screenshot é o passo 6 abaixo (R1).

A **fila** das ações que a Jana sugere — a que o `AcaoHitlController` chamava de "PR próprio" desde 2026-08-18. As 5 chaves e os 5 rótulos de CTA são os de `AcaoHitlService::ACOES`, byte a byte; a prévia e o alcance vêm do servidor (`AcaoHitlService::previa`, mesmo agregado que pinta a linha do Painel); aprovar grava o recibo em `jana_acao_aprovacoes` com o texto **do servidor**. **Nada é enviado** — por isso o botão diz "Revisar", não "Disparar".

## 1. Fonte de dado

| dado | dono |
|---|---|
| chaves · CTA · título | `AcaoHitlService::ACOES` + `::TITULOS` (copy literal da âncora `JTN_HITL`) |
| prévia · contexto · alcance | `AcaoHitlService::previa(key, businessId)` |
| recibo (quem · quando · prévia gravada) | `AcaoHitlService::fila()` — último `AcaoAprovacao` por chave, `business_id` da sessão |
| aprovar | `POST /ia/acoes/{acao}/aprovar` (rota que já existia) via `JanaAcaoModal` reusado |

## 2. Smoke (passo 6 — R1)

```bash
curl -sv https://oimpresso.com/ia/acoes 2>&1 | grep '^< HTTP'   # 302 → login sem sessão; 200 logado
```

Logado (biz=1): screenshot 1280 da aba acesa + fila; aprovar UMA ação de leitura (`investigar-ticket`) e conferir o toast + o recibo em "aprovadas" + a linha em `jana_acao_aprovacoes`.
