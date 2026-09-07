---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Manufacturing · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 7 |
| CU no SDD | 0 |
| Telas (.tsx) | 5 |
| Telas com `casos.md` | 5 |
| UC declarados | 28 |
| UC com teste que os cita | 27 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

_Nenhuma lacuna: toda tela tem caso **com UC**, todo CU é citado, e toda US **entregue** tem contrato._

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-MANU-001 | `desconhecido` | Quanto custa produzir, com o preço de insumo de hoje |
| US-MANU-002 | `desconhecido` | Relatório de produção do período |
| US-MANU-003 | `desconhecido` | Configurações do módulo |
| US-MANU-004 | `desconhecido` | Ordens de produção — as 8 colunas e as duas marcas do §4.5 |
| US-MANU-005 | `desconhecido` | Insumos — impacto reverso e simulador de preço |
| US-MANU-006 | `desconhecido` | Editor de ingredientes |
| US-MANU-007 | `desconhecido` | Formulário de ordem de produção |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-CFG-01 | Settings | 🧪 aguarda veredito da lane |
| UC-CFG-02 | Settings | 🧪 aguarda veredito da lane |
| UC-CFG-03 | Settings | 🧪 aguarda veredito da lane |
| UC-CFG-04 | Settings | 🧪 aguarda veredito da lane |
| UC-INS-01 | Insumos | 🧪 aguarda veredito da lane |
| UC-INS-02 | Insumos | 🧪 aguarda veredito da lane |
| UC-INS-03 | Insumos | 🧪 aguarda veredito da lane |
| UC-INS-04 | Insumos | 🧪 aguarda veredito da lane |
| UC-INS-05 | Insumos | 🧪 aguarda veredito da lane |
| UC-OP-01 | Index | 🧪 aguarda veredito da lane |
| UC-OP-02 | Index | 🧪 aguarda veredito da lane |
| UC-OP-03 | Index | 🧪 aguarda veredito da lane |
| UC-OP-04 | Index | 🧪 aguarda veredito da lane |
| UC-OP-05 | Index | 🧪 aguarda veredito da lane |
| UC-RECIPE-00 | Recipes | 🧪 aguarda veredito da lane |
| UC-RECIPE-01 | Recipes | 🧪 aguarda veredito da lane |
| UC-RECIPE-02 | Recipes | 🧪 aguarda veredito da lane |
| UC-RECIPE-03 | Recipes | 🧪 aguarda veredito da lane |
| UC-RECIPE-04 | Recipes | 🧪 aguarda veredito da lane |
| UC-RECIPE-05 | Recipes | 🧪 aguarda veredito da lane |
| UC-RECIPE-06 | Recipes | 🧪 aguarda veredito da lane |
| UC-RECIPE-07 | Recipes | 🧪 aguarda veredito da lane |
| UC-RECIPE-08 | Recipes | 📝 sem_teste |
| UC-REPORT-00 | Report | 🧪 aguarda veredito da lane |
| UC-REPORT-01 | Report | 🧪 aguarda veredito da lane |
| UC-REPORT-02 | Report | 🧪 aguarda veredito da lane |
| UC-REPORT-03 | Report | 🧪 aguarda veredito da lane |
| UC-REPORT-04 | Report | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
