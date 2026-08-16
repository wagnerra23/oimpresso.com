---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Estoque · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 0 |
| CU no SDD | 0 |
| Telas (.tsx) | 0 |
| Telas com `casos.md` | 1 |
| UC declarados | 14 |
| UC com teste que os cita | 12 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

_Nenhuma lacuna: toda tela tem caso **com UC**, todo CU é citado, e toda US **entregue** tem contrato._

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

_Nenhuma._

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-EST-01 | Movimentacao | 🧪 aguarda veredito da lane |
| UC-EST-02 | Movimentacao | 🧪 aguarda veredito da lane |
| UC-EST-02B | Movimentacao | 📝 sem_teste |
| UC-EST-03 | Movimentacao | 🧪 aguarda veredito da lane |
| UC-EST-04 | Movimentacao | 🧪 aguarda veredito da lane |
| UC-EST-05 | Movimentacao | 🧪 aguarda veredito da lane |
| UC-EST-06 | Movimentacao | 🧪 aguarda veredito da lane |
| UC-EST-07 | Movimentacao | 🧪 aguarda veredito da lane |
| UC-EST-08 | Movimentacao | 🧪 aguarda veredito da lane |
| UC-EST-08B | Movimentacao | 📝 sem_teste |
| UC-INV-02 | Movimentacao | 🧪 aguarda veredito da lane |
| UC-INV-03 | Movimentacao | 🧪 aguarda veredito da lane |
| UC-INV-05 | Movimentacao | 🧪 aguarda veredito da lane |
| UC-INV-06 | Movimentacao | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
