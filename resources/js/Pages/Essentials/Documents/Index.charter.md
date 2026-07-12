---
page: /essentials/document
component: resources/js/Pages/Essentials/Documents/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Essentials
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /essentials/document (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Essentials/Http/Controllers/DocumentController@index` (rota `document.index`, resource `document` only index/destroy/show). Central de arquivos e memos internos do business, com compartilhamento por usuário/papel.

---

## Mission
Dar à equipe um repositório simples de arquivos compartilhados (tipo `document`) e de avisos em texto (tipo `memos`), tudo escopado ao business. O usuário envia arquivos, escreve memos, baixa, compartilha com usuários/papéis e remove os próprios. A tela mostra duas listas em tabs (Arquivos / Memos), cada linha indicando se o item é do usuário ou foi compartilhado com ele.

---

## Goals — Features (faz)
- Lista arquivos e memos em duas tabs, contadores por tab, carregadas via `Inertia::defer` (skeleton no first render).
- Upload de arquivo com descrição opcional e barra de progresso (`POST /essentials/document`, throttle 30/min).
- Criação de memo (título + corpo em texto) via mesmo endpoint store.
- Download de arquivo por linha (`/essentials/document/download/{id}`, throttle 60/min) — restrito a criador ou destinatário do share.
- Visualização de memo em diálogo (texto puro, `whitespace-pre-wrap`).
- Compartilhamento por usuário e por papel: carrega estado via `GET /essentials/document-share/{id}/edit` e salva via `PUT /essentials/document-share`.
- Remoção do próprio arquivo/memo com confirmação (`DELETE /essentials/document/{id}`), apagando junto os compartilhamentos.
- Distinção visual "Compartilhado por X" para itens que não são do usuário atual.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO expõe documentos de outro business — leitura/escrita escopadas por `business_id` (multi-tenant Tier 0) no controller.
- ❌ NÃO permite editar arquivo/memo já criado (o controller `update` é no-op / `edit` só redireciona ao index) — inferência pendente de Wagner.
- ❌ NÃO versiona arquivos nem mantém histórico de revisões.
- ❌ NÃO faz preview inline de PDF/imagem — só download.
- ❌ NÃO permite remover item de terceiro (só `is_mine`).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- Carregamento lazy das listas `documents` e `memos` via `Inertia::defer` (tab inicial decide a primeira query; a outra roda sob demanda).
- Sincronização da URL (`?type=memos`) via `history.replaceState` ao trocar de tab, sem re-render de servidor.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO faz polling nem refresh automático das listas.
- ❌ NÃO notifica destinatários ao compartilhar (share é silencioso).
- ❌ NÃO muta dados em requisição GET.
- ❌ NÃO recompartilha automaticamente ao reenviar um arquivo homônimo.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar com Wagner se edição de memo/arquivo deve existir (hoje é no-op)
