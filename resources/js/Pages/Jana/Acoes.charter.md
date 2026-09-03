---
id: resources-js-pages-jana-acoes-charter
page: /ia/acoes
component: resources/js/Pages/Jana/Acoes.tsx
owner: wagner
status: draft
last_validated: "2026-09-02"
parent_module: Jana
parent_adr: memory/decisions/0052-memoria-jana-3-angulos-faturamento.md
related_prototype: prototipo-ui/cowork/jana-telas-novas.jsx
related_adrs: [52, 93, 94, 104, 180, 182]
related_charters:
  - resources/js/Pages/Jana/Index.charter.md
related_us: [US-COPI-148]
runbook: memory/requisitos/Jana/RUNBOOK-acoes.md
related_casos:
  - resources/js/Pages/Jana/Acoes.casos.md
alcance:
  rota: /ia/acoes
  rota_nome: jana.acoes.index
  permission: jana.access
  menu_hook: Modules/Jana/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: jana_module
tier: B
charter_version: 1
permissao: jana.access
---

# Page Charter — `/ia/acoes` (aba Ações da área Jana — a fila HITL)

> **Status:** `draft` — nasceu em 2026-09-02 como a 4ª aba da paridade com a âncora
> (`jana-merge.jsx` §`JmTabs`). Vira `live` com o screenshot pós-merge aprovado por [W] (R1).

## Mission

A **fila** das ações que a Jana sugere: as 5 de `AcaoHitlService::ACOES`, cada uma com a prévia
que o servidor preparou, o alcance (envio × leitura) e — depois de aprovada — o recibo com quem e
quando. Era o "PR próprio" que o `AcaoHitlController` prometia desde 2026-08-18 (charter do Painel
v10). O Painel mostra a ação quando o número pede; a fila mostra **as cinco**, sempre, com o estado.

## Goals

- **Aba na barra ÚNICA da área** — ghost `acoes` (label `Ações`, `/ia/acoes`), 4ª posição, entre
  Alertas e Memória; item no dropdown legado e no `topnav.php` (lang `menu.acoes`).
- **Tudo que afirma número é do SERVIDOR** — `AcaoHitlService::fila(businessId)`: título (`TITULOS`,
  copy literal da âncora), CTA (`ACOES`, byte a byte), prévia/contexto/alcance (`previa()`, o mesmo
  agregado que pinta a linha do Painel) e o último recibo por chave (`jana_acao_aprovacoes`).
- **Aprovar reusa o `JanaAcaoModal` do Painel** — mesma prévia (buscada de novo), mesma rota
  `POST /ia/acoes/{acao}/aprovar`, mesmo `back()` + toast global. A fila recarrega pelas props.
- **Chips sugeridas · aprovadas** (com contagem), card por ação com chip `envio`/`leitura`
  (`alcance` `null` = leitura), "Ver o recibo" abre a prévia **gravada** + contexto + alcance.
- Aviso de topo, empty states e nota de rodapé com **copy literal** da âncora, pinados em
  `prototipo-ui/contrato/jana-acoes.contract.json`.

## Non-Goals

- ⛔ **Disparar** a ação (WhatsApp/e-mail) — segue PR próprio; por isso o CTA é "Revisar …".
- ⛔ Recusar/desfazer aprovação — `status` da tabela prevê `recusada`, nenhuma rota escreve isso.
- ⛔ Agregação cross-business — `business_id` da sessão (ADR 0093).

## UX targets

- 1280px sem scroll horizontal; cards em coluna (`Card` shared), sem tabela.
- Dark mode por token; a pill `envio`/`leitura` é `Badge` `info`/`secondary` (AP7), não o
  `oklch` cru da âncora.

## Anti-hooks

- ⛔ **Prévia, contexto ou recibo montados no cliente.** A âncora traz 5 prévias FIXAS com números
  do Martinho e um recibo fabricado com `setTimeout` — aqui tudo vem de `fila()`/`previa()`, e o
  recibo mostra o texto **gravado** (`AcaoAprovacao.previa`), nunca a prévia de agora.
- ⛔ **Segundo caminho de aprovação.** Aprovar é o `JanaAcaoModal` (uma rota, um registro, um
  toast). Um `fetch`/`router.post` próprio nesta Page daria dois donos do mesmo ato.
- ⛔ **Toast próprio nesta Page** — `app.tsx` já trata `flash.success`.
- ⛔ **Copy que promete disparo** ("Disparar", "Enviar") — o recibo do charter do Painel v10 vale
  aqui inteiro: botão que abre modal que não dispara mente.

## Skills relevantes

`brief-first` · `multi-tenant-patterns` · `mwart-process` · `comparar-design-prod`

## Charter version log

- v1 (2026-09-02) — Tela nasce da paridade das abas (handoff 2026-08-31 §Paridade Painel).
  Backend: `AcaoHitlService::TITULOS` + `fila()`; `AcaoHitlController@index`; rota
  `jana.acoes.index`. Contrato: UC-ACAO-00..03 em `Acoes.casos.md`.
