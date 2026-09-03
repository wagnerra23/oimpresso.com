---
id: resources-js-pages-jana-alertas-charter
page: /ia/alertas
component: resources/js/Pages/Jana/Alertas.tsx
owner: wagner
status: draft
last_validated: "2026-09-02"
parent_module: Jana
parent_adr: memory/decisions/0052-memoria-jana-3-angulos-faturamento.md
related_prototype: prototipo-ui/cowork/jana-telas-novas.jsx
related_adrs: [52, 93, 94, 104, 180, 182]
related_charters:
  - resources/js/Pages/Jana/Index.charter.md
related_us: [US-COPI-060, US-COPI-061, US-COPI-148]
runbook: memory/requisitos/Jana/RUNBOOK-alertas.md
related_casos:
  - resources/js/Pages/Jana/Alertas.casos.md
alcance:
  rota: /ia/alertas
  rota_nome: jana.alertas.index
  permission: jana.access
  menu_hook: Modules/Jana/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: jana_module
tier: B
charter_version: 1
permissao: jana.access
---

# Page Charter — `/ia/alertas` (aba Alertas da área Jana)

> **Status:** `draft` — nasceu em 2026-09-02 como a 3ª aba da paridade com a âncora
> (`jana-merge.jsx` §`JmTabs`: Painel · Conversa · **Alertas** · Ações · Memória · Plataforma).
> Vira `live` com o screenshot pós-merge aprovado por [W] (R1).

## Mission

A lista consolidada dos **desvios de meta** que a Jana já calcula. Até aqui, `/ia/alertas` era um
Blade que dizia *"a lista de alertas ainda não existe"*; a `MetaDesvioNotification` disparava pro
sino e ninguém tinha onde ver o conjunto. Dono/gestor abre a aba, vê **o que está fora da
projeção**, por quanto e desde quando, e abre a meta.

## Goals

- **Aba na barra ÚNICA da área** — ghost `alertas` no `DataController` (label `Alertas`,
  `/ia/alertas`), 3ª posição, lido pelo `JanaSubNav` compartilhado; `JanaAreaHeader
  active="alertas"`. O item voltou também ao dropdown legado e ao `topnav.php` (tinham saído em
  2026-05-25 porque a tela era stub).
- **A conta é do SERVIDOR.** `AlertaService::calcular(Meta)` — extraído de `avaliar()` em
  2026-09-02 pra ser **a mesma fórmula** da notificação — devolve projetado · realizado ·
  desvio · severidade (1× baixa · 1,5× média · 3× alta sobre o corte) · `dispara`. A tela filtra
  e formata; não recalcula nada.
- **Lista = só o que dispara** (`|desvio| > corte`, corte de
  `config('copiloto.alertas.desvio_threshold_default')`); o rodapé conta as metas que ficaram
  abaixo do corte. Filtro local por severidade (todas · alta · média · baixa) + contagem
  `N disparando · corte em X%`.
- **Colunas da âncora**: Meta (nome + slug) · Desvio · Severidade (dot + texto, AP7) ·
  Projetado × realizado · Data ref. · Chegou por · ações. Linha `urgent` quando severidade alta.
- **Kebab → "Abrir a meta"** (`/ia/metas/{id}`, `<a href>` nativo — `MetasController@show` é Blade).
- `status` novo|lido lê a `MetaDesvioNotification` do usuário logado (`read_at`) — hoje só
  chega ao payload; a coluna "Chegou por" diz **in-app**, o único canal que `via()` declara.
- Empty state e nota de rodapé com **copy literal** da âncora (`EmptyState variant="done"` ·
  `jtn-nota`), pinadas em `prototipo-ui/contrato/jana-alertas.contract.json`.

## Non-Goals

- ⛔ Persistir configuração de alertas (corte, canais, silêncio noturno) — é a **US-COPI-061**;
  `AlertasController@updateConfig` valida e **descarta**, e `/ia/alertas/config` segue Blade.
- ⛔ Disparar/repetir notificação a partir desta tela — quem notifica é o `avaliar()` (cron).
- ⛔ Agregação cross-business — a tela é `business_id` da sessão (+ metas da plataforma, `NULL`),
  o mesmo recorte do `IndexController::buildMetasPayload`.

## UX targets

- 1280px sem scroll horizontal (ROTA LIVRE); tabela via `DataTable` shared (PT-01), paginador de
  uma página (a lista inteira vem na prop — poucas metas por business).
- Dark mode por token (`bg-destructive`/`bg-warning`/`bg-info` nos dots; nunca o `oklch` cru da âncora).

## Anti-hooks

- ⛔ **Recalcular desvio/severidade no frontend.** Fonte autoritativa `AlertaService::calcular`
  (mesma família do farol no Painel). O filtro de severidade é recorte de exibição, não veredito.
- ⛔ **Oferecer o que o servidor não honra.** Medido em 2026-09-02 na âncora `JmAlertas`, três
  affordances ficaram de fora, com o motivo de cada uma:
  - *Silenciar esta meta / Voltar a alertar* — no protótipo é `localStorage`; a promessa da copy
    (*"só não notifica"*) é **falsa em prod**: o `avaliar()` não lê navegador nenhum e seguiria
    notificando. Junto saíram os chips de STATUS (abertos · silenciados · todos) — sem silêncio,
    "silenciados" seria filtro sempre vazio.
  - *Perguntar por que caiu* — `ChatController@novaConversa` não aceita pergunta inicial (medido
    2026-08-07, `Index.charter.md` §Anti-hooks). Botão que abre a Conversa sem a pergunta mente.
  - *Alerta de topo + "Ver a configuração" / "Configurar alertas"* — é o drawer de config, que
    depende da mesma US-COPI-061; entra em PR próprio (ou nasce com o aviso que a âncora traz).
  É o mesmo critério que encolheu o `JanaConfigDrawer` (charter do Painel v8): entra o que é
  verdade **e** de fato local.
- ⛔ **`<Link>` do Inertia pra rota Blade** (`/ia/metas/{id}`) — clique viraria no-op silencioso.
- ⛔ **Montar toast próprio nesta Page** — `app.tsx` já trata `flash.success` globalmente.

## Skills relevantes

`brief-first` (Tier A) · `multi-tenant-patterns` (Tier A) · `mwart-process` (Tier A) · `comparar-design-prod` (Tier B)

## Charter version log

- v1 (2026-09-02) — Tela nasce da paridade das abas (handoff 2026-08-31 §Paridade Painel:
  *"abas: protótipo 6 × prod 3"*). Âncora `jana-telas-novas.jsx` §`JmAlertas` (desce em
  2026-08-27; SYNC com o vivo re-medido hoje via `DesignSync.get_file`, path do manifesto).
  Backend: `AlertaService::calcular()` (refactor sem mudança de fórmula) + `listar()`;
  `AlertasController@index` → Inertia. Blade `alertas/index.blade.php` apagado. Contrato:
  UC-ALERTA-00..04 em `Alertas.casos.md`.
