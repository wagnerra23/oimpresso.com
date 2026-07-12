---
page: /ponto/relatorios
component: resources/js/Pages/Ponto/Relatorios/Index.tsx
related_prototype: n/a (herda PT-01 Lista; grade de cards de relatórios — segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-012]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/relatorios (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/RelatorioController@index` (rota `ponto.relatorios.index`) + `@gerar` (rota `ponto.relatorios.gerar`). Middleware `ponto.access`. Catálogo de relatórios do ponto.

---

## Mission
Catálogo dos relatórios do módulo de ponto (AFD, AFDT, AEJ, Espelho, Horas Extras, Banco de Horas, Atrasos/Faltas, Eventos eSocial). O usuário define período e (opcionalmente) colaborador, depois clica em "Gerar" no card do relatório desejado. Hoje só o Espelho está disponível; os demais aparecem como "Em breve".

---

## Goals — Features (faz)
- Grade de cards de relatórios agrupados por categoria (default "Geral").
- Filtros globais: período (`<input type="month">`) e colaborador (Select, só aparece se o backend enviar a lista).
- Cada card mostra ícone/cor (mapeados a tokens do DS), badge "Disponível"/"Em breve".
- Botão "Gerar" monta a URL `/ponto/relatorios/{chave}?periodo=...&colaborador=...` (só habilitado se `disponivel`).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não gera o relatório na própria tela — "Gerar" navega pra rota de geração (a maioria hoje `abort(501)` — não implementada).
- ❌ Não permite gerar relatórios marcados "Em breve" (botão desabilitado).
- ❌ Não persiste nem agenda relatórios — é geração sob demanda.
- ❌ Não expõe dados de outro tenant — a geração é escopada por `business_id` no backend. *(inferência pendente de Wagner)*

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 + PageHeader canon (ADR 0182).

---

## Automation hooks (faz)
- Nenhuma automação server-side nesta tela — só monta URLs de geração (a geração real vive nas rotas `@gerar`).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não dispara geração ao abrir nem ao trocar filtros — só no clique em "Gerar".
- ❌ Nenhuma mutação em GET — a tela é read-only.
- ❌ Não envia relatório por e-mail/eSocial automaticamente.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Definir quais relatórios "Em breve" entram no escopo (hoje só Espelho gera)
- [ ] Smoke visual 1280/1440 (screenshot) da grade + filtros
