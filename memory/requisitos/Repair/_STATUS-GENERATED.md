---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Repair · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 2 |
| CU no SDD | 0 |
| Telas (.tsx) | 13 |
| Telas com `casos.md` | 0 |
| UC declarados | 0 |
| UC com teste que os cita | 0 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| Tela `Dashboard/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `DeviceModels/Create` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `DeviceModels/Edit` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `DeviceModels/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `JobSheet/AddParts` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `JobSheet/Create` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `JobSheet/Edit` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `JobSheet/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `JobSheet/Show` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `ProducaoOficina/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Show` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Status/Index` sem `casos.md` | o contrato da tela (trio incompleto) |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-REPA-001 | `desconhecido` | [TODO — título] |
| US-REPA-002 | `todo` | 3 testes do Wave18 quebram com `base_path()` fora do bootstrap do app |

## UC por status

| UC | Tela | Status |
|---|---|---|

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
