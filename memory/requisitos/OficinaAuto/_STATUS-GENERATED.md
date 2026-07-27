<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — OficinaAuto · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 48 |
| CU no SDD | 19 |
| Telas (.tsx) | 9 |
| Telas com `casos.md` | 10 |
| UC declarados | 51 |
| UC com teste que os cita | 51 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| `CU-OFI-03` sem UC | caso de uso que o exercite — Consulta de placa auto-preenche dados técnicos |
| `CU-OFI-14` sem UC | caso de uso que o exercite — Ver o pátio num relance |
| `CU-OFI-17` sem UC | caso de uso que o exercite — Painel fiscal da OS |
| `US-OFICINA-012` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Consulta CRLV/placa (cache 30d + adapter pluggable) — **P1** |
| `US-OFICINA-042` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Painel fiscal NF-e/NFS-e na OS |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-OFICINA-005 | `desconhecido` | Cleanup tools pra cliente legacy migrado — **P0 (emergente PR #555)** |
| US-OFICINA-006 | `desconhecido` | FSM wire-up canônico `ServiceOrder` (espelha Sells/Repair ADR 0143) — **P0** |
| US-OFICINA-007 | `todo` | Importer Vargas (1.064 veículos multi-placa) — **P0** |
| US-OFICINA-008 | `todo` | Schema garantia granular per-item (`oa_pecas_utilizadas`+`oa_servicos_executados |
| US-OFICINA-009 | `todo` | Defeitos múltiplos por OS (JSON array) — **P1** |
| US-OFICINA-010 | `todo` | Stages oficina-específicos `teste_estrada` + `ajuste_final` + loop — **P1** |
| US-OFICINA-011 | `todo` | Re-orçamento (action `escalar_supervisor` + flag `aprovado_apos_aumento`) — **P1 |
| US-OFICINA-013 | `todo` | Tabela tempária seed (100 serviços comuns BR) — **P1** |
| US-OFICINA-015 | `todo` | App PWA mecânico campo (V0 — minhas OS + foto + clock-in) — **P2** |
| US-OFICINA-016 | `todo` | Garantia lembrete cron (pré-vencimento WhatsApp) — **P2** |
| US-OFICINA-018 | `todo` | NFSe modelo 56 split documentos fiscais — **P1** |
| US-OFICINA-019 | `todo` | Comissão por OS (mecânico + atendente, % escalonado) — **P2** |
| US-OFICINA-020 | `todo` | Importer Firebird `WR_KANBAN` → `oa_kanban_state` (pré-arte Vargas/Martinho) — * |
| US-OFICINA-021 | `todo` | Integração FIPE veículo (valor mercado + filtro garantia) — **P2** |
| US-OFICINA-022 | `desconhecido` | Cleanup tools cliente legacy migrado (continua US-OFICINA-005) — **P0 já existe* |
| US-AUTO-002 | `desconhecido` | Consulta CRLV/Renavam por placa (DETRAN/SerPro) — **P0** |
| US-AUTO-004 | `desconhecido` | Tabela tempária (preço hora-homem por tipo serviço) — **P0** |
| US-AUTO-006 | `desconhecido` | OS multi-mecânico (1 OS, N mecânicos com peças/horas distintas) — **P0** |
| US-AUTO-007 | `desconhecido` | Diagnóstico assistido por Jana IA (sintoma → hipóteses + tempário sugerido) — ** |
| US-AUTO-008 | `desconhecido` | Catálogo peças OEM + similares (cód fabricante + equivalentes) — **P1** |
| US-AUTO-010 | `desconhecido` | NFC-e (peça) + NFS-e (serviço) automática a partir de boleto pago — **P0** |
| US-AUTO-011 | `desconhecido` | Comissão por OS (vendedor + mecânico, % escalonado) — **P1** |
| US-AUTO-012 | `desconhecido` | App mobile mecânico (PWA — vê OS, marca status, sobe foto) — **P0** |
| US-AUTO-013 | `desconhecido` | Garantia serviço (registro + lembrete pós-X dias) — **P1** |
| US-AUTO-014 | `desconhecido` | Lembrete revisão (km/tempo) — **P1** |
| US-AUTO-015 | `desconhecido` | Pré-cadastro fornecedores + cotação (RFQ) — **P2** |
| US-AUTO-016 | `desconhecido` | Apontamento horas mecânico (clock-in/out por OS) — **P2** |
| US-AUTO-017 | `desconhecido` | Painel cliente público (status OS online) — **P1** |
| US-AUTO-018 | `desconhecido` | CT-e/MDF-e quando entrega de veículo — **P3** |
| US-OFICINA-026 | `todo` | Outreach Martinho Caçambas + cutover discovery — fechar contrato pioneer |
| US-OFICINA-046 | `todo` | Dívida F3: repontuar kanban Caçambas overdue→expected_completion + remover UI "L |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-01 | ServiceOrders/Board | 🧪 aguarda veredito da lane |
| UC-02 | ServiceOrders/Board | 🧪 aguarda veredito da lane |
| UC-03 | ServiceOrders/Board | 🧪 aguarda veredito da lane |
| UC-04 | ServiceOrders/Board | 🧪 aguarda veredito da lane |
| UC-05 | ServiceOrders/Board | 🧪 aguarda veredito da lane |
| UC-06 | ServiceOrders/Board | 🧪 aguarda veredito da lane |
| UC-07 | ServiceOrders/Board | 🧪 aguarda veredito da lane |
| UC-09 | ServiceOrders/Board | 🧪 aguarda veredito da lane |
| UC-11 | ServiceOrders/Board | 🧪 aguarda veredito da lane |
| UC-OAP-01 | AprovacaoPublica | 🧪 aguarda veredito da lane |
| UC-OAP-02 | AprovacaoPublica | 🧪 aguarda veredito da lane |
| UC-OAP-03 | AprovacaoPublica | 🧪 aguarda veredito da lane |
| UC-OAP-04 | AprovacaoPublica | 🧪 aguarda veredito da lane |
| UC-OAP-05 | AprovacaoPublica | 🧪 aguarda veredito da lane |
| UC-OAP-06 | AprovacaoPublica | 🧪 aguarda veredito da lane |
| UC-OAP-07 | AprovacaoPublica | 🧪 aguarda veredito da lane |
| UC-OCR-01 | ServiceOrders/Create | 🧪 aguarda veredito da lane |
| UC-OCR-02 | ServiceOrders/Create | 🧪 aguarda veredito da lane |
| UC-OCR-03 | ServiceOrders/Create | 🧪 aguarda veredito da lane |
| UC-OCR-04 | ServiceOrders/Create | 🧪 aguarda veredito da lane |
| UC-OED-01 | ServiceOrders/Edit | 🧪 aguarda veredito da lane |
| UC-OED-02 | ServiceOrders/Edit | 🧪 aguarda veredito da lane |
| UC-OED-03 | ServiceOrders/Edit | 🧪 aguarda veredito da lane |
| UC-OED-05 | ServiceOrders/Edit | 🧪 aguarda veredito da lane |
| UC-OED-06 | ServiceOrders/Edit | 🧪 aguarda veredito da lane |
| UC-OED-07 | ServiceOrders/Edit | 🧪 aguarda veredito da lane |
| UC-OED-08 | ServiceOrders/Edit | 🧪 aguarda veredito da lane |
| UC-OIM-01 | importer-frota-legada (blade) | 🧪 aguarda veredito da lane |
| UC-OIM-02 | importer-frota-legada (blade) | 🧪 aguarda veredito da lane |
| UC-OIM-03 | importer-frota-legada (blade) | 🧪 aguarda veredito da lane |
| UC-OSH-01 | ServiceOrders/Show | 🧪 aguarda veredito da lane |
| UC-OSH-02 | ServiceOrders/Show | 🧪 aguarda veredito da lane |
| UC-OSH-03 | ServiceOrders/Show | 🧪 aguarda veredito da lane |
| UC-OSH-04 | ServiceOrders/Show | 🧪 aguarda veredito da lane |
| UC-OSH-05 | ServiceOrders/Show | 🧪 aguarda veredito da lane |
| UC-OSH-06 | ServiceOrders/Show | 🧪 aguarda veredito da lane |
| UC-OSH-07 | ServiceOrders/Show | 🧪 aguarda veredito da lane |
| UC-OSH-08 | ServiceOrders/Show | 🧪 aguarda veredito da lane |
| UC-OSH-09 | ServiceOrders/Show | 🧪 aguarda veredito da lane |
| UC-OSH-10 | ServiceOrders/Show | 🧪 aguarda veredito da lane |
| UC-OSH-11 | ServiceOrders/Show | 🧪 aguarda veredito da lane |
| UC-OVC-01 | Vehicles/Create | 🧪 aguarda veredito da lane |
| UC-OVC-02 | Vehicles/Create | 🧪 aguarda veredito da lane |
| UC-OVC-03 | Vehicles/Create | 🧪 aguarda veredito da lane |
| UC-OVE-01 | Vehicles/Edit | 🧪 aguarda veredito da lane |
| UC-OVE-02 | Vehicles/Edit | 🧪 aguarda veredito da lane |
| UC-OVI-01 | Vehicles/Index | 🧪 aguarda veredito da lane |
| UC-OVI-02 | Vehicles/Index | 🧪 aguarda veredito da lane |
| UC-OVI-03 | Vehicles/Index | 🧪 aguarda veredito da lane |
| UC-OVS-01 | Vehicles/Show | 🧪 aguarda veredito da lane |
| UC-OVS-02 | Vehicles/Show | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
