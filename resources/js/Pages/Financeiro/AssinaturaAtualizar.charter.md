---
id: resources-js-pages-financeiro-assinatura-atualizar-charter
page: /financeiro/assinatura/atualizar
route: financeiro.assinaturas.atualizar.show
controller: Modules\Financeiro\Http\Controllers\AssinaturaController@showAtualizar
status: draft
owner: eliana
created: 2026-05-31
refs:
  - FIN-004
  - ADR 0093 (multi-tenant Tier 0)
  - ADR UI-0013 (Constituição UI v2)
component: resources/js/Pages/Financeiro/AssinaturaAtualizar.tsx
related_us: [US-FIN-063]
last_validated: "2026-05-31"
parent_module: Financeiro
related_prototype: n/a (sem protótipo Cowork — form de atualização de cobrança, FIN-004; segue DS)  # 2026-09-09 [C]: ⚠️ ERRATA do recibo herdado: buscar `FIN-004`/`atualizar cobran` dá 0, mas `prototipo-ui/cowork/Wagner/AssinaturaAtualizar.tsx` EXISTE no espelho. Medido: é porte REVERSO do código vivo (310 vs 309 linhas; difere só na API do PageHeader — `@/Components/shared/PageHeader` + `description`/`action` contra `@/Components/PageHeader` + `subtitle`/`actions`). Ancorar nele seria ancorar a tela nela mesma (§5 2026-06-05). Conclusão "sem fonte" MANTIDA, recibo corrigido. medido 2026-09-09 pelas 3 pernas (repo inteiro com --hidden, projeto Cowork por ID via DesignSync.list_files, espelho). Ver memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md §5.3 + §5.7.
tier: B
charter_version: 1
---

# Charter — Atualizar Cobrança (Assinatura)

> Status `draft` — Wagner revisa Non-Goals + Anti-hooks antes de virar `live`.

## Mission

Permitir que admin/operador altere com segurança o **valor**, **ciclo** ou
**forma de pagamento** de uma assinatura recorrente ativa, com **preview do
impacto** (diff antigo → novo) antes de confirmar — evitando alteração cega em
cobrança de cliente real (biz=4 ROTA LIVRE, prod).

## Goals

- Listar assinaturas ativas (scoped por `business_id`) numa tabela legível:
  status, valor atual, ciclo, forma, próximo vencimento.
- Selecionar uma assinatura e editar apenas os campos que mudam (payload parcial).
- Mostrar **preview de impacto** com diff campo a campo (de → para) antes de salvar.
- Bloquear o `PATCH` quando não há nenhuma mudança real (sem patch cego).

## Non-Goals

- Criar/cancelar assinatura (só atualização de cobrança).
- Cobrar/emitir fatura agora (gateway é responsabilidade do Service).
- Editar dados do contato/plano.
- Histórico de alterações / audit trail visível (futuro).

## UX targets

- Header canon (`@/Components/shared/PageHeader`) — sem CSS legacy os-page-h/fin-page-h.
- Form só aparece após seleção (foco/contexto).
- Botão salvar só habilita com diff real.
- Cores 100% tokens DS (Badge default|secondary|destructive; Alert default).

## Automation hooks / Anti-hooks

- **Anti-hook:** nunca disparar `PATCH` automático por mudança de campo — sempre
  exige clique explícito após ver o preview.
- **Anti-hook:** Controller/log NUNCA imprime valor real ou PII (biz=4 prod).
- HITL pending: alteração de cobrança requer aprovação humana (publication-policy).

## Backend contract

- `PATCH /financeiro/assinaturas/{assinatura}` → `AssinaturaController@atualizar`
- Request: `UpdateAssinaturaRequest` — `valor` (numeric 0.01..999999.99),
  `ciclo` (mensal|trimestral|semestral|anual), `forma_pagamento`
  (boleto|pix|cartao) — todos `sometimes`, ≥1 obrigatório.
- Permissão: `recurringbilling.assinatura.update`.
