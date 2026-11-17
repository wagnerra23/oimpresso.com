---
authority: generated
---

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
| US no SPEC | 34 |
| CU no SDD | 17 |
| Telas (.tsx) | 1 |
| Telas com `casos.md` | 1 |
| UC declarados | 11 |
| UC com teste que os cita | 11 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| `CU-COM-06` sem UC | caso de uso que o exercite — Agir sobre a compra pelo menu Ações |
| `CU-COM-09` sem UC | caso de uso que o exercite — Importar DF-e recebida como compra |
| `CU-CFX-01` sem UC | caso de uso que o exercite — Pedir material pela requisição |
| `CU-CFX-02` sem UC | caso de uso que o exercite — Cotar entre fornecedores |
| `CU-CFX-03` sem UC | caso de uso que o exercite — Fazer o pedido, com alçada |
| `CU-CFX-04` sem UC | caso de uso que o exercite — Receber o pedido, em partes |
| `CU-CFX-05` sem UC | caso de uso que o exercite — Atualizar o custo no recebimento |
| `CU-CFX-06` sem UC | caso de uso que o exercite — Gerar o título a pagar |
| `CU-CFX-07` sem UC | caso de uso que o exercite — Devolver ao fornecedor |
| `CU-CFX-08` sem UC | caso de uso que o exercite — Lançar a compra pela nota recebida |
| `US-COM-006` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Pest cross-tenant biz=1 vs biz=99 (4 testes) |
| `US-COM-007` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Fix business_id source: auth() em vez de session() + abort_i |
| `US-COM-009` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Validar JOIN scope contacts.business_id em TransactionUtil:: |
| `US-COM-011` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Teste E2E de cálculo custo/total/estoque da compra (Tier 0 v |
| `US-COM-013` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Recebimento parcial (qty recebida por linha ≠ pedida + trâns |
| `US-COM-024` **entregue sem contrato** (`status: doing`) | UC que prove o que foi entregue — Custo do insumo numa data, pelo método da empresa (UC-FLX-38 |
| `US-COM-026` **entregue sem contrato** (`status: doing`) | UC que prove o que foi entregue — Compra recebida aplica a regra de custo e preço (UC-FLX-17) |
| `US-COM-027` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Estoque mínimo vira requisição de compra (UC-FLX-19) — **P2* |
| `US-COM-030` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — MRP: necessidade de compra somando todas as OS, com prazo do |
| `US-COM-031` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Cotação de compra entre fornecedores — **P1** |
| `US-COM-032` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Aprovação do pedido de compra por alçada de valor — **P2** |
| `US-COM-034` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Inspeção de qualidade no recebimento da compra — **P2** |
| `US-COM-028` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Aprovar ou recusar o preço proposto no detalhe da compra (UC |
| `US-COM-029` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Compra a partir de pedido não aceita pedido nem linha de out |

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
| US-COM-014 | `todo` | FSM de estágios PERSISTIDA + auditável |
| US-COM-015 | `todo` | Teste de invariante de estoque no fluxo de entrada |
| US-COM-016 | `todo` | Cobrir fluxo `/compras`→contas a pagar (Observer Financeiro) com teste |
| US-COM-017 | `cancelled` | ~~PiiRedactor no Drawer de compra~~ → RETIRADA (2026-07-03) |
| US-COM-018 | `todo` | Autosave rascunho de compra (`localStorage` `{biz}.{user}` debounced) |
| US-COM-019 | `wontfix` | ~~Eager-load `->with(['contact','location'])` em `listarCompras().paginate()`~~  |
| US-COM-020 | `todo` | A11y do drawer (`role=dialog` + focus-trap + `aria-label` + `Esc`) |
| US-COM-021 | `cancelled` | ~~Investigar flakiness das baselines dark/empty do VRT e reabilitar no gate L2~~ |
| US-COM-022 | `todo` | Lane required de Compras vira árvore-menos-quarentena (vermelha no main há 5 run |
| US-COM-023 | `todo` | Teste de 7 dias do cockpit Compras na ROTA LIVRE (biz=4) |
| US-COM-025 | `desconhecido` | Rastreio do rolo: em que OS cada fração da mídia foi usada (UC-FLX-18) — **P2** |
| US-COM-033 | `todo` | Avaliação automática do fornecedor por entrega — **P3** |

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
| UC-CMP-10 | Index | 🧪 aguarda veredito da lane |
| UC-CMP-11 | Index | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
