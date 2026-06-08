---
slug: compras-briefing
title: "BRIEFING — Modules/Compras"
type: briefing
module: Compras
status: scaffold
updated_at: 2026-05-21
version: 0.1
owner: wagner
---

# BRIEFING — Modules/Compras

## TL;DR (5 linhas)

- **O que é:** cockpit de compras (lista + 4 KPIs + drawer), substitui Blade legacy `/purchases/*`. FSM 6 estágios (rascunho→pago), import XML DF-e, entrada matricial tam×cor pra vestuário.
- **Estado:** Wave 1 scaffold (2026-05-21) — módulo nWidart criado, sem rota live ainda.
- **Caminho:** B híbrido — greenfield Controllers/Pages, REUSA `transactions` polimórfica + TransactionUtil + Observer Financeiro.
- **Maior risco:** Wave 6 bridge `NfeDfeRecebido → Transaction(type=purchase)` — único componente verdadeiramente novo.
- **Cliente piloto:** Larissa @ ROTA LIVRE biz=4 vestuário; Wave 4.5 GradeMatrixInput é a unlock dela.

## Capacidades atuais (Wave 1)

| Capacidade | Status | Onde |
|---|---|---|
| Scaffold módulo nWidart | ✅ | `Modules/Compras/` |
| Sidebar entry "Compras" | ✅ | `DataController::modifyAdminMenu` (gated por `compras_module` package) |
| Permissions Spatie | ✅ catálogo (5 perms) | `DataController::user_permissions` |
| Rota `/compras` | ✅ stub Inertia | `Routes/web.php` → `ComprasController::index` |
| Page React `Compras/Index` | ❌ Wave 4 | a criar |
| CRUD compra manual | ❌ Wave 3 | wrapper TransactionUtil |
| Import XML DF-e | ❌ Wave 6 | `ImportarDfeComoCompraService` (gap novo) |
| GradeMatrixInput tam×cor | ❌ Wave 4.5 | componente custom TanStack |
| Deprecação `/purchases` | ❌ Wave 8 | 301 redirect + flag |

## Capabilities mapeadas pra vertical

- **horizontal:** todo business que compra de fornecedor — base de qualquer ERP
- **vestuário (Larissa):** GradeMatrixInput tam×cor + entrada por modelo pai (US-COM-005)
- **automotivo (OficinaAuto):** futuro — compra de peças com lote/garantia
- **gráfica (Officeimpresso):** futuro — compra de matéria-prima (PVC, papel) com unidade fracionada

## Score Capterra (atual)

- D1 Funcional base: 25/100 (scaffold; sem CRUD operacional)
- D2 UX (cockpit): 0/100 (Page Inertia não existe)
- D3 Vertical vestuário: 0/100 (GradeMatrixInput pendente Wave 4.5)
- D4 Integrações (DFe): 0/100 (bridge pendente Wave 6)
- **Total: ~5/100** — esperado pós Wave 5 (sem 4.5/6): ~40/100
- **Total target pós Wave 9 canary biz=4:** ~75/100

## Próximos passos (ordem)

1. Wave 2: docs canon (este arquivo + SPEC + RUNBOOK + ADR proposta) ✅
2. Wave 3: backend wrapper ComprasController + ComprasService + Pest baseline
3. Wave 4: bundle CSS Cowork + Page React F1 pin literal
4. Wave 4.5: GradeMatrixInput.tsx (vestuário unlock — Larissa biz=4)
5. Wave 5: build + smoke local + PR
6. Wave 6: bridge XML DF-e (gap novo, maior risco)
7. Wave 7: Pest multi-tenant + idempotência
8. Wave 8: deprecação `/purchases` legacy
9. Wave 9: canary 7d biz=1 → biz=4 ROTA LIVRE

## Pegadinhas conhecidas (tier 0)

- Multi-tenant ADR 0093 — `session('user.business_id')` em todo query. Job recebe `$businessId` no constructor
- Cowork bundle aplicar INTEIRO 1ª vez (proibicoes.md Tier 0) — copiar `compras-page.css` direto
- F3 anti-patterns ([LICOES_F3_FINANCEIRO_REJEITADO](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)) — pin literal protótipo, sem mock data, sem service inventado
- Pest biz=1 NUNCA biz=4 (ADR 0101) — biz=4 só canary 7d pós-merge

## Owner & ADRs

- Owner: Wagner
- ADR proposta: [compras-modulo-greenfield-hibrido](../../decisions/proposals/compras-modulo-greenfield-hibrido.md)
- ADRs base: 0093, 0094, 0101, 0104, 0105, 0106, 0107, 0114, 0143

## Refs

- [SPEC.md](SPEC.md)
- [RUNBOOK-compras-index.md](RUNBOOK-compras-index.md)
- [DISCOVERY-LARISSA-COMPRAS.md](DISCOVERY-LARISSA-COMPRAS.md)
- [memory/sessions/2026-05-21-como-integrar-compras.md](../../sessions/2026-05-21-como-integrar-compras.md)
- [memory/sessions/2026-05-21-arte-grade-matrix-input-vestuario.md](../../sessions/2026-05-21-arte-grade-matrix-input-vestuario.md)
