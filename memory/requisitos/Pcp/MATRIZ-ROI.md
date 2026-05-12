---
module: Pcp
type: matriz-roi
status: discovery 2026-05-12
audience: Wagner (decisão priorização)
---

# MATRIZ-ROI — Modules/Pcp (cross-vertical apontamento)

> 20 US-PCP × concorrentes BR/USA. Score 0-3 (0=ausente, 1=básico, 2=competitivo, 3=estado-da-arte).
> Concorrência BR pesquisada via WebSearch + research clientes-legacy.
> ROI score = (dor cliente 0-3) × (frequência uso 0-3) × (custo construção inv 1-5 onde 1=trivial, 5=hard).

## Concorrentes mapeados

| Sigla | Concorrente | Foco | Mercado |
|---|---|---|---|
| **TOTVS-PCP** | TOTVS Protheus SIGAPCP (MATA680/681 + APP Minha Produção) | ERP grande BR, PCP completo | BR-grande |
| **TOTVS-MES** | TOTVS Mes (integração shop floor) | Manufatura grande | BR-grande |
| **MUBI** | Mubisys (sistema gráfico BR) | Com.Visual + terminal apontamento | BR-vertical com.vis |
| **ZEN** | Zênite (gráfico BR) | Com.Visual rotineiro | BR-vertical com.vis |
| **CALC** | Calcgraf (2M orçamentos/mês) | Pricing m² + PCP gráfico | BR-vertical com.vis |
| **SAP** | SAP S/4HANA Manufacturing (Shop Floor Control + Capacity Scheduling Board) | Enterprise USA/global | global-grande |
| **ODOO** | Odoo Manufacturing 18+ Shop Floor + barcode + operator timesheet | OSS global médio-pequeno | global-PME |
| **FREPPLE** | Frepple (OSS PCP/MRP) | Manufatura PME OSS | global-OSS |
| **SANK** | Sankhya PCP | ERP médio BR | BR-médio |
| **SENIOR** | Senior PCP | ERP médio BR | BR-médio |
| **BLING** | Bling | Horizontal BR PME | BR-PME |
| **OIMP** | oimpresso (estado atual via §0 SPEC) | Vertical modular BR | BR-PME-vertical |

## Tabela MATRIZ (cada US × concorrente)

> Legenda: 3=estado-da-arte · 2=competitivo · 1=básico · 0=ausente · ⚙️=parcial-via-config

| US | Feature | TOTVS-PCP | MUBI | ZEN | CALC | SAP | ODOO | FREPPLE | SANK | BLING | OIMP-hoje | Gap |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| US-PCP-001 | Schema base (postos+operações+appointments+schedules) | 3 | 2 | 2 | 2 | 3 | 3 | 3 | 2 | 0 | 0 | **3** |
| US-PCP-004 | CRUD postos trabalho (centro_trabalho) | 3 | 2 | 2 | 2 | 3 | 3 | 3 | 2 | 0 | ⚙️ slots JSON | **2** |
| US-PCP-005 | CRUD operações + tempo padrão | 3 | 2 | 2 | 1 | 3 | 3 | 3 | 2 | 0 | 0 | **3** |
| US-PCP-006 | FSM actions intermediárias (start/pause/stop) | 3 | 2 | 2 | 1 | 3 | 3 | 2 | 2 | 0 | ⚙️ stage-level only | **2** |
| US-PCP-007 | RegisterAppointment idempotent service | 3 | 2 | 1 | 1 | 3 | 3 | 2 | 2 | 0 | 0 | **3** |
| US-PCP-008 | Endpoint API JWT `/api/pcp/scan` | 2 | 2 | 1 | 1 | 3 | 3 | 2 | 1 | 0 | 0 | **3** |
| US-PCP-009 | **PWA mobile QR scanner** | 2 (APP nativo TOTVS) | 2 | 1 | 1 | 2 | 3 (barcode mobile) | 1 | 1 | 0 | 0 | **3 (DIFERENCIAL)** |
| US-PCP-010 | QR token na label OS | 2 | 1 | 0 | 0 | 2 | 3 | 1 | 1 | 0 | ⚙️ label sem QR | **3** |
| US-PCP-011 | Kanban shared agrupador configurável (8 agrupadores) | 1 | 2 | 1 | 1 | 2 | 2 | 1 | 1 | 0 | 2 (1 agrupador) | **1 extend** |
| US-PCP-012 | Detect bottleneck cron + alerta UI | 2 | 1 | 1 | 0 | 3 | 1 | 2 | 2 | 0 | 0 | **3** |
| US-PCP-013 | Capacidade dia/semana view + dashboard | 3 | 2 | 1 | 1 | 3 | 2 | 3 | 2 | 0 | 0 | **3** |
| US-PCP-014 | Performance operador (LGPD-guarded) | 2 | 1 | 1 | 0 | 3 | 2 | 1 | 2 | 0 | 0 | **3** |
| US-PCP-015 | Agendamento drag-drop calendário | 2 | 1 | 1 | 0 | 3 | 2 | 3 | 2 | 0 | 0 | **3** |
| US-PCP-016 | Integração BoM `MfgRecipe` consumo automático | 3 | 2 | 1 | 0 | 3 | 3 | 3 | 2 | 0 | ⚙️ Manufacturing isolado | **2** |
| US-PCP-017 | Dashboard PCP Inertia (4 cards KPI + Kanban + heatmap) | 2 | 2 | 1 | 1 | 3 | 2 | 2 | 2 | 0 | 0 | **3** |
| US-PCP-018 | Broadcast Centrifugo realtime | 1 | 1 | 0 | 0 | 2 | 2 | 1 | 1 | 0 | 0 | **2** |
| US-PCP-019 | Smoke biz=4 ROTA LIVRE | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | **n/a** |
| US-PCP-020 | RUNBOOK + Charter docs | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | **n/a** |

## Score ROI ponderado (dor × frequência × inv_custo)

> Score: dor cliente (0-3) × frequência uso (0-3) ÷ custo construção (1-5)

| Rank | US | Dor cliente | Freq uso | Custo inv (1=fácil,5=hard) | **ROI** | Justificativa |
|---|---|---|---|---|---|---|
| **1** | US-PCP-009 PWA QR scanner | 3 | 3 | 3 (PWA câmera quirks) | **3.0** | Diferencial vs Bling/Conta Azul; paridade Delphi APP Minha Produção (TOTVS); cobre Vargas+Extreme+Martinho |
| **2** | US-PCP-007 RegisterAppointment service (core) | 3 | 3 | 2 | **4.5** | Sem isso nada funciona; idempotency é table-stakes |
| **3** | US-PCP-001 Schema base + triggers append-only | 3 | 3 | 2 | **4.5** | Fundação Tier 0; paridade Portaria 671 pattern proven |
| **4** | US-PCP-012 Bottleneck detection (regras simples) | 3 | 2 | 2 | **3.0** | Dor relatada cliente: "5 OS pararam em corte e eu só descobri sexta" — gerente quer alerta proativo |
| **5** | US-PCP-013 Capacidade dia/semana | 3 | 3 | 2 | **4.5** | Plotter ComVis: "amanhã 95% capacidade — vou recusar pedido? estender turno?" — decisão diária real |

### Top 5 features ROI (resumo)

1. **US-PCP-009 PWA QR scanner mobile** — diferencial competitivo BR + paridade TOTVS APP Minha Produção
2. **US-PCP-007 RegisterAppointment service idempotent** — fundação core
3. **US-PCP-001 Schema base + triggers append-only** — fundação Tier 0
4. **US-PCP-013 Capacidade dia/semana** — decisão diária real cliente
5. **US-PCP-012 Bottleneck detection (regras simples)** — alerta proativo gerente

## Concorrentes vs oimpresso — vantagens chave

| Vantagem | Onde oimpresso ganha |
|---|---|
| **PWA real-time + offline** | Mubisys/Zênite/Calcgraf têm terminal desktop fixo; oimpresso PWA mobile funciona no celular do mecânico (US-PCP-009) |
| **FSM canon + audit trail LGPD** | Bling/TOTVS pequeno NÃO têm append-only LGPD-pronto; oimpresso já tem ADR 0143 deployed |
| **Multi-tenant Tier 0** | TOTVS/Sankhya são single-tenant ou multi-tenant fraco; oimpresso ADR 0093 IRREVOGÁVEL |
| **Custo** | TOTVS R$5-15k/mês; SAP R$50k+/mês; oimpresso R$ 297-997/mês target (ROTA LIVRE precedent) |
| **Jana IA conversacional** | Nenhum concorrente BR pequeno-médio tem; ComVis Extreme/Gold pediria via WhatsApp "qual gargalo da semana?" e Jana responde com dados reais |
| **Cross-vertical (1 PCP serve oficina+gráfica+repair)** | Mubisys só gráfica; ProMoz só oficina; oimpresso 1 fundação 4+ verticais |

## Onde concorrentes ganham (gap pra fechar futuro P3)

| Gap | Concorrente | US futuro |
|---|---|---|
| **MRP completo + sugestão compras automática** | SAP, Frepple, TOTVS | P3 backlog |
| **Capacity Scheduling Board visual (Gantt-like)** | SAP `Capacity Scheduling Board` | US-PCP-015 já cobre básico; visual avançado P2 |
| **Manutenção preventiva máquinas** | SAP PM, Odoo Maintenance | P3 backlog |
| **Quality control (qty_lost detalhado + motivo)** | TOTVS QC, SAP Quality | US-PCP-007 cobre qty_lost; motivo/refugo backlog P2 |
| **Roteiro/Process flow visual** | TOTVS Roteiro + Operação visual; SAP routing | P2 |

---
**Versão inicial 2026-05-12** — fundamentação ROI cruzada com 11 concorrentes + estado atual oimpresso (§0 SPEC).
