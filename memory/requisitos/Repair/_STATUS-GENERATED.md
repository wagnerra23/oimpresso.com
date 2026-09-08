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
| US no SPEC | 5 |
| CU no SDD | 0 |
| Telas (.tsx) | 14 |
| Telas com `casos.md` | 13 |
| UC declarados | 70 |
| UC com teste que os cita | 70 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| Tela `JobSheet/Index` sem `casos.md` | o contrato da tela (trio incompleto) |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-REPA-001 | `desconhecido` | [TODO — título] |
| US-REPA-002 | `todo` | 3 testes do Wave18 quebram com `base_path()` fora do bootstrap do app |
| US-REPA-003 | `desconhecido` | Configurar os padrões da folha de OS e o que sai impresso |
| US-REPA-004 | `o` | Listar e filtrar as ordens de serviço abertas |
| US-REPA-005 | `desconhecido` | Manter o catálogo de status e de modelos que as OS usam |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-DMCRE-01 | DeviceModels/Create | 🧪 aguarda veredito da lane |
| UC-DMCRE-02 | DeviceModels/Create | 🧪 aguarda veredito da lane |
| UC-DMCRE-03 | DeviceModels/Create | 🧪 aguarda veredito da lane |
| UC-DMCRE-04 | DeviceModels/Create | 🧪 aguarda veredito da lane |
| UC-DMEDT-01 | DeviceModels/Edit | 🧪 aguarda veredito da lane |
| UC-DMEDT-02 | DeviceModels/Edit | 🧪 aguarda veredito da lane |
| UC-DMEDT-03 | DeviceModels/Edit | 🧪 aguarda veredito da lane |
| UC-DMEDT-04 | DeviceModels/Edit | 🧪 aguarda veredito da lane |
| UC-DMIDX-01 | DeviceModels/Index | 🧪 aguarda veredito da lane |
| UC-DMIDX-02 | DeviceModels/Index | 🧪 aguarda veredito da lane |
| UC-DMIDX-03 | DeviceModels/Index | 🧪 aguarda veredito da lane |
| UC-DMIDX-04 | DeviceModels/Create | 🧪 aguarda veredito da lane |
| UC-DMIDX-05 | DeviceModels/Index | 🧪 aguarda veredito da lane |
| UC-DMIDX-06 | DeviceModels/Index | 🧪 aguarda veredito da lane |
| UC-JSC-01 | JobSheet/Create | 🧪 aguarda veredito da lane |
| UC-JSC-02 | JobSheet/Create | 🧪 aguarda veredito da lane |
| UC-JSC-03 | JobSheet/Create | 🧪 aguarda veredito da lane |
| UC-JSC-04 | JobSheet/Create | 🧪 aguarda veredito da lane |
| UC-JSC-05 | JobSheet/Create | 🧪 aguarda veredito da lane |
| UC-JSC-06 | JobSheet/Create | 🧪 aguarda veredito da lane |
| UC-JSE-01 | JobSheet/Edit | 🧪 aguarda veredito da lane |
| UC-JSE-02 | JobSheet/Edit | 🧪 aguarda veredito da lane |
| UC-JSE-03 | JobSheet/Edit | 🧪 aguarda veredito da lane |
| UC-JSE-04 | JobSheet/Edit | 🧪 aguarda veredito da lane |
| UC-JSE-05 | JobSheet/Edit | 🧪 aguarda veredito da lane |
| UC-JSE-06 | JobSheet/Edit | 🧪 aguarda veredito da lane |
| UC-JSP-01 | JobSheet/AddParts | 🧪 aguarda veredito da lane |
| UC-JSP-02 | JobSheet/AddParts | 🧪 aguarda veredito da lane |
| UC-JSP-03 | JobSheet/AddParts | 🧪 aguarda veredito da lane |
| UC-JSP-04 | JobSheet/AddParts | 🧪 aguarda veredito da lane |
| UC-JSP-05 | JobSheet/AddParts | 🧪 aguarda veredito da lane |
| UC-JSP-06 | JobSheet/AddParts | 🧪 aguarda veredito da lane |
| UC-JSS-01 | JobSheet/Show | 🧪 aguarda veredito da lane |
| UC-JSS-02 | JobSheet/Show | 🧪 aguarda veredito da lane |
| UC-JSS-03 | JobSheet/Show | 🧪 aguarda veredito da lane |
| UC-JSS-04 | JobSheet/Show | 🧪 aguarda veredito da lane |
| UC-JSS-05 | JobSheet/Show | 🧪 aguarda veredito da lane |
| UC-PAC-08 | Settings/Index | 🧪 aguarda veredito da lane |
| UC-RDSH-01 | Dashboard/Index | 🧪 aguarda veredito da lane |
| UC-RDSH-02 | Dashboard/Index | 🧪 aguarda veredito da lane |
| UC-RDSH-03 | Dashboard/Index | 🧪 aguarda veredito da lane |
| UC-RDSH-04 | Dashboard/Index | 🧪 aguarda veredito da lane |
| UC-RIDX-01 | Index | 🧪 aguarda veredito da lane |
| UC-RIDX-02 | Index | 🧪 aguarda veredito da lane |
| UC-RIDX-03 | Index | 🧪 aguarda veredito da lane |
| UC-RIDX-04 | Index | 🧪 aguarda veredito da lane |
| UC-RPOE-01 | ProducaoOficina/Index | 🧪 aguarda veredito da lane |
| UC-RPOE-02 | ProducaoOficina/Index | 🧪 aguarda veredito da lane |
| UC-RPOE-03 | ProducaoOficina/Index | 🧪 aguarda veredito da lane |
| UC-RPOE-04 | ProducaoOficina/Index | 🧪 aguarda veredito da lane |
| UC-RPOE-05 | ProducaoOficina/Index | 🧪 aguarda veredito da lane |
| UC-RPOE-06 | ProducaoOficina/Index | 🧪 aguarda veredito da lane |
| UC-RSET-01 | JobSheet/Create | 🧪 aguarda veredito da lane |
| UC-RSET-02 | Settings/Index | 🧪 aguarda veredito da lane |
| UC-RSET-03 | JobSheet/AddParts | 🧪 aguarda veredito da lane |
| UC-RSET-04 | Settings/Index | 🧪 aguarda veredito da lane |
| UC-RSET-05 | Settings/Index | 🧪 aguarda veredito da lane |
| UC-RSET-06 | Settings/Index | 🧪 aguarda veredito da lane |
| UC-RSET-07 | Settings/Index | 🧪 aguarda veredito da lane |
| UC-RSET-08 | Settings/Index | 🧪 aguarda veredito da lane |
| UC-RSHW-01 | Show | 🧪 aguarda veredito da lane |
| UC-RSHW-02 | Show | 🧪 aguarda veredito da lane |
| UC-RSHW-03 | Show | 🧪 aguarda veredito da lane |
| UC-RSHW-04 | Show | 🧪 aguarda veredito da lane |
| UC-RSTIDX-01 | Status/Index | 🧪 aguarda veredito da lane |
| UC-RSTIDX-02 | Status/Index | 🧪 aguarda veredito da lane |
| UC-RSTIDX-03 | Status/Index | 🧪 aguarda veredito da lane |
| UC-RSTIDX-04 | Status/Index | 🧪 aguarda veredito da lane |
| UC-RSTIDX-05 | Status/Index | 🧪 aguarda veredito da lane |
| UC-RSTIDX-06 | Status/Index | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
