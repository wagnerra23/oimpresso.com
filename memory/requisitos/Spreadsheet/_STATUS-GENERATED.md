---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Spreadsheet · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 5 |
| CU no SDD | 0 |
| Telas (.tsx) | 1 |
| Telas com `casos.md` | 1 |
| UC declarados | 5 |
| UC com teste que os cita | 5 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

_Nenhuma lacuna: toda tela tem caso **com UC**, todo CU é citado, e toda US **entregue** tem contrato._

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-SHEET-001 | `desconhecido` | — Criar planilha nova |
| US-SHEET-002 | `desconhecido` | — Listar e editar planilhas do business |
| US-SHEET-003 | `desconhecido` | — Share via link (público read-only) |
| US-SHEET-004 | `desconhecido` | — Post-share (revogar / listar shares ativos) |
| US-SHEET-005 | `desconhecido` | — Embed view (iframe-friendly) |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-PLAN-01 | Index | 🧪 aguarda veredito da lane |
| UC-PLAN-02 | Index | 🧪 aguarda veredito da lane |
| UC-PLAN-03 | Index | 🧪 aguarda veredito da lane |
| UC-PLAN-04 | Index | 🧪 aguarda veredito da lane |
| UC-PLAN-05 | Index | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
