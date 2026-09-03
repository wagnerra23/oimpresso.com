---
id: requisitos-jana-runbook-alertas
slug: jana-runbook-alertas
title: "Jana — Runbook da tela Alertas (/ia/alertas)"
type: runbook
module: Jana
tela: Jana/Alertas
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
  - "Pelo menos 1 meta ativa com período vigente E apuração — sem os dois o AlertaService volta calado"
  - "config('copiloto.alertas.desvio_threshold_default') resolvido (10 em prod)"
steps:
  - "Abrir /ia/alertas autenticado: a aba Alertas acende no JanaAreaHeader (ghost `alertas` do DataController)"
  - "Conferir a lista: só metas com |desvio| > corte aparecem; o rodapé conta as que ficaram abaixo"
  - "Filtrar por severidade (todas · alta · média · baixa) e conferir a contagem 'N disparando · corte em X%'"
  - "Abrir a meta pelo kebab (⋯ → Abrir a meta) e cair em /ia/metas/{id}"
  - "Conferir isolamento Tier 0: meta de outro business nunca aparece"
---

# RUNBOOK — Alertas da Jana (`/ia/alertas`)

> **Tipo:** runbook reproduzível
> **Irmãos:** [`Alertas.charter.md`](../../../resources/js/Pages/Jana/Alertas.charter.md) (lei) · [`Alertas.casos.md`](../../../resources/js/Pages/Jana/Alertas.casos.md) (contrato UC) · [`jana-alertas.contract.json`](../../../prototipo-ui/contrato/jana-alertas.contract.json) (copy pinada)
> **Âncora de design:** `prototipo-ui/cowork/jana-telas-novas.jsx` §`JmAlertas` (a aba vive no `JmTabs` de `jana-merge.jsx`). Resolva sempre por `node prototipo-ui/ancora.mjs Jana/Alertas`, nunca no olho.
> **Validado:** **estático** contra `origin/main` em 2026-09-02 — rotas, controller, `AlertaService`, `MetaDesvioNotification` e o protótipo (`DesignSync.get_file`, path do manifesto) conferidos arquivo a arquivo.
> ⚠️ **Fluxo vivo contra prod NÃO exercitado nesta data.** O smoke real com screenshot é o passo 6 abaixo e é a evidência que fecha a R1.

A lista consolidada dos **desvios de meta** que o `AlertaService` já calcula (projeção linear entre `data_ini` e `data_fim` do período vigente; severidade por múltiplo do corte — 1× baixa · 1,5× média · 3× alta). Até esta tela, o Blade de `/ia/alertas` dizia, com todas as letras, que *"a lista de alertas ainda não existe"*.

Vive dentro do `AppShellV2`, sob o header de área `JanaAreaHeader` (aba **Alertas** — 3ª do vocabulário `Painel · Conversa · Alertas · Ações · Memória · Plataforma` da âncora `JmTabs`).

## 1. Fonte de dado — o que é do servidor e o que não é

| dado | dono | como chega |
|---|---|---|
| projetado · realizado · desvio · severidade | `AlertaService::calcular(Meta)` (a MESMA conta do `avaliar()` que dispara a notificação) | prop `alertas` do `AlertasController@index` |
| corte (threshold) | `config('copiloto.alertas.desvio_threshold_default')` | prop `corte` |
| status novo/lido | `notifications` do usuário logado (`MetaDesvioNotification`, `read_at`) | campo `status` de cada linha |
| canal | `MetaDesvioNotification::via()` = `database` + `broadcast` → **in-app** | fixo na coluna "Chegou por" |

**O que a tela NÃO faz (medido, não suposto):** silenciar por meta e "Perguntar por que caiu" — o servidor não honra nenhum dos dois (não há persistência de config: `AlertasController@updateConfig` valida e descarta, US-COPI-061; `ChatController@novaConversa` não aceita pergunta inicial). Ver §Anti-hooks do charter.

## 2. Smoke (passo 6 — R1)

```bash
curl -sv https://oimpresso.com/ia/alertas 2>&1 | grep '^< HTTP'   # 302 → login sem sessão; 200 logado
```

Logado (biz=1): screenshot 1280 da aba acesa + lista/empty state; `read_network_requests` no clique de um chip de severidade (filtro é local — zero request).
