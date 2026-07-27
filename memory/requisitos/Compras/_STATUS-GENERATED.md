<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Compras · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 21 |
| CU no SDD | 9 |
| Telas (.tsx) | 1 |
| Telas com `casos.md` | 1 |
| UC declarados | 9 |
| UC com teste que os cita | 9 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| `CU-COM-06` sem UC | caso de uso que o exercite — Agir sobre a compra pelo menu Ações |
| `CU-COM-09` sem UC | caso de uso que o exercite — Importar DF-e recebida como compra |
| `US-COM-006` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Pest cross-tenant biz=1 vs biz=99 (4 testes) |
| `US-COM-007` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Fix business_id source: auth() em vez de session() + abort_i |
| `US-COM-009` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Validar JOIN scope contacts.business_id em TransactionUtil:: |
| `US-COM-011` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Teste E2E de cálculo custo/total/estoque da compra (Tier 0 v |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-COM-001 | `in` | — Cockpit `/compras` (lista paginada + 4 KPIs + drawer) |
| US-COM-002 | `desconhecido` | — Criar compra manual |
| US-COM-003 | `pending` | — Importar XML DF-e como compra (GAP NOVO) |
| US-COM-004 | `desconhecido` | — Deprecar `/purchases` legacy |
| US-COM-005 | `in` | — Entrada matricial tam×cor (GradeMatrixInput) |
| US-COM-008 | `desconhecido` | Throttle 60/1 em /compras + FormRequest ListarComprasRequest |
| US-COM-010 | `todo` | Adicionar Compras em config/governance/module_clients.yaml (Larissa biz=4 piloto |
| US-COM-012 | `todo` | Matching automático XML→produto (EAN + xProd; fallback manual) |
| US-COM-013 | `todo` | Recebimento parcial (qty recebida por linha ≠ pedida + trânsito residual + autos |
| US-COM-014 | `todo` | FSM de estágios PERSISTIDA + auditável |
| US-COM-015 | `todo` | Teste de invariante de estoque no fluxo de entrada |
| US-COM-016 | `todo` | Cobrir fluxo `/compras`→contas a pagar (Observer Financeiro) com teste |
| US-COM-017 | `retirada` | ~~PiiRedactor no Drawer de compra~~ → RETIRADA (2026-07-03) |
| US-COM-018 | `todo` | Autosave rascunho de compra (`localStorage` `{biz}.{user}` debounced) |
| US-COM-019 | `todo` | Eager-load `->with(['contact','location'])` em `listarCompras().paginate()` |
| US-COM-020 | `todo` | A11y do drawer (`role=dialog` + focus-trap + `aria-label` + `Esc`) |
| US-COM-021 | `todo` | Investigar flakiness das baselines dark/empty do VRT e reabilitar no gate L2 |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-CMP-01 | Index | 🧪 aguarda veredito da lane |
| UC-CMP-02 | Index | 🧪 aguarda veredito da lane |
| UC-CMP-03 | Index | 🧪 aguarda veredito da lane |
| UC-CMP-04 | Index | 🧪 aguarda veredito da lane |
| UC-CMP-05 | Index | 🧪 aguarda veredito da lane |
| UC-CMP-06 | Index | 🧪 aguarda veredito da lane |
| UC-CMP-07 | Index | 🧪 aguarda veredito da lane |
| UC-CMP-08 | Index | 🧪 aguarda veredito da lane |
| UC-CMP-09 | Index | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
