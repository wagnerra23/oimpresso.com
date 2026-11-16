---
id: resources-js-pages-nfse-index-charter
page: /nfse
component: resources/js/Pages/Nfse/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: NFSe
related_us: [US-NFSE-008]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /nfse (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters (telas sem contrato). Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> **Errata 2026-11-16 (lote 3 da auditoria NFSe, PRs #11041 · #11044 · este):** os itens marcados *(errata 2026-11-16)* foram medidos contra o código e corrigidos. O texto anterior de cada um fica citado no próprio item.
>
> Backend: `Modules/NFSe/Http/Controllers/NfseController@index` (middleware UltimatePOS + gate `nfse.view`). Lista `NfseEmissao` paginada (`buildNotasPayload` — `paginate(25)->withQueryString()`), entregue como prop deferida. US-NFSE-008.

---

## Mission

Listagem operacional das NFS-e (Notas Fiscais de Serviço) emitidas pela empresa — status de cada emissão (rascunho / processando / emitida / cancelada / erro — os 5 da coluna; *(errata 2026-11-16: dizia "processando / autorizada / erro / cancelada"; "autorizada" nunca é gravado)*), tomador, valor e competência — com filtros persistentes e atalhos de teclado, pra o operador acompanhar o ciclo de emissão sem abrir o portal da prefeitura. É a porta de entrada do módulo NFSe; emitir vive em `/nfse/emitir` e o detalhe em `/nfse/{id}`.

---

## Goals — Features (faz)

- Tabela (`DataTable` shared) das emissões: competência (`MM/AAAA`), número, tomador, valor dos serviços com o ISS abaixo, `StatusBadge` *(errata 2026-11-16: listava também "data", que a tabela não mostra)*
- Filtros por status (os 5 de `IndexNfseRequest::STATUS`), período (de/ate por competência, `AAAA-MM`) e busca livre (`q` → tomador ou número), persistidos em `localStorage` (`oimpresso.nfse.filters`)
- Ações por linha: ver detalhe (`/nfse/{id}`), baixar PDF quando disponível (`pdf_url`), cancelar — abre o detalhe com o diálogo de cancelamento (`?cancelar=`) *(errata 2026-11-16: o ícone só abria o detalhe)*
- Atalhos de teclado: `N` emitir · `J`/`K` navegar linhas · `/` foco na busca · `Enter` abre a linha focada
- `EmptyState` (shared) quando não há notas
- Paginação server-side (25/página, `withQueryString`) via `PaginatorShape`
- AppShellV2 + PageHeader shared (canon UI v2), sentinela `STATUS_ALL` no Select (evita crash Radix de `value=""`)

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO emite NFSe aqui — emissão é a tela `/nfse/emitir` (PT-02)
- ❌ NÃO edita nota emitida — NFS-e autorizada é imutável (só cancelamento via detalhe)
- ❌ NÃO cancela inline sem passar pelo fluxo de detalhe com motivo (`/nfse/{id}` → cancelar)
- ❌ NÃO configura certificado / provider / alíquota (isso é config do módulo, `NfseProviderConfig`)
- ❌ NÃO mostra XML/RPS bruto na listagem (só metadados; detalhe fica no Show)
- ❌ NÃO pagina além do escopo do tenant — `NfseEmissao` é scopeado por `business_id` (Tier 0)

---

## UX targets

- p95 < 1500ms (tela admin) com `paginate(25)`
- Cabe em 1280px (ROTA LIVRE) sem scroll horizontal
- Filtros sobrevivem a reload (localStorage) — operador não reconfigura toda visita
- Status legível por cor via `StatusBadge` (tokens DS), não texto solto

---

## Automation hooks (faz)

- Filtros aplicados disparam navegação Inertia (`router`) preservando querystring
- Persistência de filtros em `localStorage` no `useEffect`

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO faz polling/refetch automático de status (operador recarrega ou reabre)
- ❌ NÃO dispara emissão/cancelamento a partir desta tela sem ação explícita do usuário
- ❌ NÃO grava nada no backend em GET (só leitura)

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot, não tabela)
- [x] `notas` deferida com `<Deferred>` + esqueleto (#11041, 2026-11-16)
