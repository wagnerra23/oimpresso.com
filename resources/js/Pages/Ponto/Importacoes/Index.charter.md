---
page: /ponto/importacoes
component: resources/js/Pages/Ponto/Importacoes/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-009, US-PONT-010, US-PONT-011]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/importacoes (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/ImportacaoController@index` (rota `ponto.importacoes.index`, middleware `ponto.access`). Lista o histórico de arquivos AFD/AFDT importados.

---

## Mission
Histórico das importações de arquivos AFD/AFDT lidos dos REPs. Mostra cada arquivo com tipo, tamanho, estado de processamento, linhas processadas/criadas, quem enviou e quando — pra o RH acompanhar o pipeline de ingestão de marcações e abrir o detalhe de cada importação.

---

## Goals — Features (faz)
- Lista paginada (20/página) de importações do business, ordenada por mais recente.
- Colunas: arquivo, tipo (badge), tamanho, estado (`StatusBadge kind="importacao"`), linhas criadas/processadas, usuário, quando (humanizado).
- Botão primário "Nova importação" → `/ponto/importacoes/novo`.
- Empty state com CTA pra primeira importação.
- Link "Ver" por linha → `/ponto/importacoes/{id}`.
- Paginação via partial reload (`only: ['importacoes']`).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita nem reprocessa importações a partir da lista (o detalhe é no `Show`).
- ❌ Não deleta arquivos importados — histórico é preservado (auditoria/append-only).
- ❌ Não mostra importações de outro tenant — scope por `business_id` da sessão.
- ❌ Não faz upload aqui — só lista (upload é o `Create`).

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 + PageHeader canon (ADR 0182).

---

## Automation hooks (faz)
- Normaliza o estado do backend (`ESTADO_*`) para os values do `StatusBadge` no frontend.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling do estado nesta tela (o auto-refresh vive no `Show`).
- ❌ Nenhuma mutação em GET — read-only.
- ❌ Não re-dispara jobs de processamento a partir da lista.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) da lista + empty state
- [ ] Confirmar se falta filtro por estado/tipo nesta lista (hoje não tem)
