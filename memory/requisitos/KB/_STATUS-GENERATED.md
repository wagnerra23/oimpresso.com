<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — KB · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 7 |
| CU no SDD | 10 |
| Telas (.tsx) | 3 |
| Telas com `casos.md` | 3 |
| UC declarados | 29 |
| UC com teste que os cita | 24 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| `CU-KB-06` sem UC | caso de uso que o exercite — Histórico de revisões |
| `CU-KB-07` sem UC | caso de uso que o exercite — KPIs do acervo |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-KB-001 | `desconhecido` | — Bridge canon dos 143 ADRs (ONDA 1, ✅ LIVE) |
| US-KB-003 | `desconhecido` | — Pergunta IA RAG sobre grafo (ONDA 4, ✅ LIVE) |
| US-KB-004 | `desconhecido` | — Trilha de aprendizado Larissa (ONDA 3+5) |
| US-KB-005 | `desconhecido` | — Troubleshooter Q→Sim/Não→Fix (ONDA 3) |
| US-KB-007 | `desconhecido` | — Imprimir SOP balcão físico (ONDA 5) — ⬜ **não começou** |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-01 | Index.v2 | 🧪 aguarda veredito da lane |
| UC-05 | Index.v2 | 🧪 aguarda veredito da lane |
| UC-06 | Index.v2 | 🧪 aguarda veredito da lane |
| UC-07 | Index.v2 | 🧪 aguarda veredito da lane |
| UC-09 | Index.v2 | 🧪 aguarda veredito da lane |
| UC-10 | Index.v2 | 📝 sem_teste |
| UC-13 | Index.v2 | 📝 sem_teste |
| UC-14 | Index.v2 | 📝 sem_teste |
| UC-KB-01 | Index | 🧪 aguarda veredito da lane |
| UC-KB-02 | Index | 🧪 aguarda veredito da lane |
| UC-KB-03 | Index | 🧪 aguarda veredito da lane |
| UC-KB-04 | Index | 🧪 aguarda veredito da lane |
| UC-KB-05 | Index | 🧪 aguarda veredito da lane |
| UC-KB-06 | Index | 🧪 aguarda veredito da lane |
| UC-KBG-01 | Graph | 🧪 aguarda veredito da lane |
| UC-KBG-02 | Graph | 🧪 aguarda veredito da lane |
| UC-KBG-03 | Graph | 🧪 aguarda veredito da lane |
| UC-KBV2-01 | Index | 🧪 aguarda veredito da lane |
| UC-KBV2-02 | Index.v2 | 🧪 aguarda veredito da lane |
| UC-KBV2-03 | Graph | 🧪 aguarda veredito da lane |
| UC-KBV2-04 | Index.v2 | 🧪 aguarda veredito da lane |
| UC-KBV2-05 | Graph | 🧪 aguarda veredito da lane |
| UC-KBV2-06 | Index.v2 | 🧪 aguarda veredito da lane |
| UC-KBV2-07 | Index.v2 | 🧪 aguarda veredito da lane |
| UC-KBV2-08 | Index.v2 | 🧪 aguarda veredito da lane |
| UC-KBV2-09 | Index.v2 | 📝 sem_teste |
| UC-KBV2-10 | Index.v2 | 📝 sem_teste |
| UC-KBV2-13 | Index.v2 | 🧪 aguarda veredito da lane |
| UC-KBV2-14 | Index.v2 | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
