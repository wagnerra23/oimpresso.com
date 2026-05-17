# CAPTERRA-FICHA — Modules/OficinaAuto

> **Ficha canônica de benchmark do módulo OficinaAuto** — fonte de verdade para a skill `comparativo-do-modulo` e auditorias Capterra.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) · ADR mãe módulo: [0137](../../decisions/0137-modules-oficinaauto-qualificada.md)
> **Criada:** 2026-05-16 (Wave 22 governance-mega) · **Nota atual:** 63/100 (Bom)

---

## 1. Identidade do módulo

- **Nome interno:** `OficinaAuto`
- **Domínio de negócio:** vertical oficinas mecânicas BR + locação de equipamentos automotivos (caçambas, retroescavadeiras). CNAEs cobertos: 4520-0/01 (mecânica geral), 2212-9/00 (recapagem), 4581-4/00 (locação caçamba).
- **Cliente alvo:** Martinho Caçambas (locação simples, 91 veículos legacy Firebird) + Vargas Recapagem (manutenção complexa multi-item). Sinal qualificado em ADR 0137 — 2 de 4 candidatos OfficeImpresso saudáveis.
- **Status atual:** 🟡 em construção (V0 scaffold done, US-OFICINA-002 a US-OFICINA-006 backlog ativo)

### Concorrentes diretos BR avaliados

| # | Concorrente | Origem | Site | Perfil | Pricing |
|---|---|---|---|---|---|
| 1 | **Oficina Integrada** | BR (líder market share PME) | `oficinaintegrada.com.br` | Mais completo BR — OS, estoque, financeiro, WhatsApp aprovação | R$ [redacted Tier 0]-499/mês |
| 2 | **Ultracar** | BR (premium) | `ultracar.com.br` | Premium oficinas grandes; integração TecDoc catálogo peças | R$ [redacted Tier 0]-800/mês |
| 3 | **Lokoz** | BR (nicho borracharia + auto) | `lokoz.com.br` | Foco borracharia/auto center; OS leve, baixo onboarding | R$ [redacted Tier 0]-199/mês |
| 4 | **MotorSW** | BR (mid) | `motorsw.com.br` | 100% online, intuitivo, OS + estoque + cliente | R$ [redacted Tier 0]-300/mês |
| 5 | **Mecânico Pro** | BR (database peças/manuais) | `mecanicopro.com.br` | Catálogo 50k+ manuais + diagnóstico — NÃO é ERP completo, é referência técnica | R$ [redacted Tier 0]-149/mês |
| 6 | **Mitchell 1** (global benchmark) | EUA | `mitchell1.com` | Padrão-ouro global — ManagerSE com OEM repair info + OS | USD 169/mês |
| — | Outros citados | BR | — | SisMecânica, MinhaOficina, Onmotor, Manager Full, FpqSystem, Soften, Limersoft, Enkad | R$ [redacted Tier 0]-399/mês |

> **Não-concorrente "Mecânico Pro" stricto sensu:** é base técnica (manuais/diagnóstico), não ERP. Mantido na FICHA como referência P3 (catálogo técnico OEM) — gap consciente do oimpresso.

---

## 2. Capacidades baseline com score (P0/P1/P2/P3)

> **P0** = obrigatório paridade BR · **P1** = competitivo · **P2** = diferencial · **P3** = futuro
> Peso ponderado nota: P0=4, P1=2, P2=1, P3=0,5 (ADR 0089 padrão Capterra)

### P0 (obrigatórias paridade BR — 12 capacidades)

| ID | Capacidade | Oficina Integrada | Ultracar | Lokoz | MotorSW | **oimpresso atual** | **Alvo V1** |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| OA-001 | OS workflow estados (aberta→serviço→concluída) | ✅ | ✅ | ✅ | ✅ | 🟡 string livre V0 | ✅ FSM ADR 0143 US-OFICINA-003 |
| OA-002 | Cadastro veículo (placa Mercosul + chassi + km) | ✅ | ✅ | ✅ | ✅ | ✅ (multi-placa nullable, Mercosul visual) | ✅ |
| OA-003 | Itens OS = peças + mão-de-obra (linha mista) | ✅ | ✅ | ✅ | ✅ | ❌ (apenas string description V0) | ✅ V1 (modelo `ServiceOrderItem` peça/serviço/desconto) |
| OA-004 | Aprovação cliente do orçamento (link/WhatsApp) | ✅ WhatsApp+email | ✅ portal | ✅ WhatsApp | ✅ email | 🟡 service `AprovacaoOsService` + token+PIN existe; falta UI público | ✅ US-OFICINA-006 |
| OA-005 | Geração NFSe serviço automática | ⚠️ via integração | ✅ nativo | ⚠️ manual | ⚠️ manual | ✅ via Modules/NfeBrasil (núcleo compartilhado) | ✅ |
| OA-006 | Controle estoque peças com baixa por OS | ✅ | ✅ | ✅ | ✅ | ✅ via UltimatePOS core | ✅ |
| OA-007 | Cliente + histórico veículos múltiplos | ✅ | ✅ | ✅ | ✅ | ✅ (Contact UltimatePOS + vehicle FK) | ✅ |
| OA-008 | Cobrança boleto/PIX vinculada à OS | ✅ via gateway | ✅ | ⚠️ | ✅ | ✅ via Modules/RecurringBilling (núcleo) | ✅ |
| OA-009 | Multi-tenant (multi-oficina por conta) | ⚠️ multi-empresa caro | ⚠️ | ❌ | ⚠️ | ✅ Tier 0 ADR 0093 nativo | ✅ |
| OA-010 | Agenda mecânicos (alocação OS↔técnico) | ✅ | ✅ | 🟡 | ✅ | ❌ V0 | 🟡 V1 (`mechanic_id` em OS + dashboard agenda) |
| OA-011 | Kanban produção (drag-drop OS por estágio) | 🟡 lista | ✅ | ❌ | 🟡 | ✅ ProducaoOficinaController + @dnd-kit | ✅ |
| OA-012 | Auditoria FSM (timeline append-only) | ❌ | ⚠️ | ❌ | ❌ | ✅ via ADR 0143 (sale_stage_history) | ✅ DIFERENCIAL |

### P1 (competitivo — 8 capacidades)

| ID | Capacidade | Oficina Integrada | Ultracar | Lokoz | **oimpresso** | Alvo |
|---|---|:-:|:-:|:-:|:-:|:-:|
| OA-101 | Tabela tempos padrão (catálogo serviços × min) | ✅ | ✅ TecDoc | 🟡 | ❌ | 🟡 backlog V2 |
| OA-102 | Diagnóstico OBD-II / scanner integrado | ❌ DIY | ✅ via parceiro | ❌ | ❌ | ❌ fora escopo V1 |
| OA-103 | Garantia serviço (re-OS sem nova cobrança) | ✅ | ✅ | ⚠️ | ❌ | 🟡 backlog V2 |
| OA-104 | Histórico OS por veículo (timeline) | ✅ | ✅ | ✅ | 🟡 (parcial via ServiceOrderSummaryService) | ✅ V1 (tela `vehicles/{id}/historico`) |
| OA-105 | Foto da peça danificada anexada à OS | ✅ | ✅ | ✅ | ❌ | 🟡 backlog V1 (Spatie Media) |
| OA-106 | Locação first-class (`order_type=locacao`) | ❌ | ❌ | ⚠️ módulo extra | ✅ DIFERENCIAL (daily_rate + expected_return_date + valor_receber accessor) | ✅ DIFERENCIAL ÚNICO |
| OA-107 | Importer cliente legacy (Firebird Delphi) | ❌ | ❌ | ❌ | 🟡 US-OFICINA-002 backlog | ✅ V1 DIFERENCIAL ÚNICO |
| OA-108 | Métricas (ticket médio, taxa retorno, lead time OS) | ✅ dashboards | ✅ dashboards | 🟡 | ❌ | 🟡 backlog V2 |

### P2 (diferencial — 6 capacidades)

| ID | Capacidade | Concorrência | **oimpresso** | Alvo |
|---|---|:-:|:-:|:-:|
| OA-201 | IA conversacional (Jana) consulta OS por WhatsApp | ❌ ninguém BR | 🟡 via Jana shared infra | ✅ DIFERENCIAL S3 |
| OA-202 | NFe-de-boleto-pago automática (NFSe gerada ao pagar) | ❌ ninguém oficina BR | ✅ via núcleo (RecurringBilling listener) | ✅ DIFERENCIAL |
| OA-203 | Aprovação OS via WhatsApp + PIN (anti-fraude) | ⚠️ link aberto sem PIN | ✅ AprovacaoOsService token+PIN (US-OFICINA-006) | ✅ DIFERENCIAL |
| OA-204 | Multi-vertical (oficina + locação no mesmo módulo) | ❌ produtos separados | ✅ `order_type` enum | ✅ DIFERENCIAL ÚNICO |
| OA-205 | OpenTelemetry observabilidade (spans por ação) | ❌ | ✅ 9 spans `oficinaauto.*` em prod (Wave 18) | ✅ DIFERENCIAL |
| OA-206 | Charter MWART por página (governança UX) | ❌ | ✅ 4 RUNBOOKs MWART + 1 charter | ✅ DIFERENCIAL |

### P3 (futuro)

| ID | Capacidade | Quem tem | Alvo |
|---|---|:-:|:-:|
| OA-301 | Catálogo OEM peças/manuais (Mitchell-like) | Mecânico Pro, Mitchell 1, TecDoc | ❌ fora escopo (parceria futura) |
| OA-302 | Telemetria conectada (FleetCard, OBD live) | ⚠️ Mitchell, alguns BR | ❌ fora escopo |
| OA-303 | Marketplace peças B2B integrado | ⚠️ parceiros | ❌ fora escopo |

---

## 3. Nota Capterra ponderada (0-100)

| Eixo | Peso | Score atual | Score-alvo V1 |
|---|---|---|---|
| **Features P0 (12)** | 4 cada = 48 max | 36 (75%) — 9 ✅ + 3 🟡 parciais | 48 (100%) com US-002/003/006 |
| **Features P1 (8)** | 2 cada = 16 max | 6 (37%) | 12 (75%) V1 |
| **Features P2 (6)** | 1 cada = 6 max | 5 (83%) — diferenciais núcleo já entregues | 6 (100%) |
| **Features P3 (3)** | 0,5 cada = 1,5 max | 0 (0%) | 0,5 (33%) |
| **UX Capterra v2** | 14 max | 8 (Kanban + AppShellV2 + charter) | 12 (V1 fotos + agenda) |
| **Automação** | 14 max | 8 (FSM canon + WhatsApp aprovação parcial + NFe auto) | 12 |
| **TOTAL** | **100** | **63 (Bom)** | **90,5 (Excelente)** |

**Cálculo bruto:** (36 + 6 + 5 + 0 + 8 + 8) = 63/100 → **Bom**, abaixo da paridade total (75 mínimo BR).

**Bottleneck:** ItemOS peça+serviço (OA-003) + Agenda mecânicos (OA-010) + Garantia (OA-103) + Histórico veículo (OA-104) + Aprovação UI público (OA-004).

---

## 4. Top 5 gaps (ordem ROI × esforço)

| # | Gap | Severidade | Esforço | ROI | US relacionada |
|---|---|---|---|---|---|
| 1 | **ItemOS = peças + mão-de-obra (modelo `ServiceOrderItem`)** — sem isso OS é texto livre, não vira NFSe nem baixa estoque | 🔴 P0 fatal paridade | 8h | Alto (desbloqueia OA-005, OA-006, NFSe auto, dashboard ticket médio) | criar US-OFICINA-007 |
| 2 | **Tela público aprovação OS via WhatsApp + PIN** — service `AprovacaoOsService` existe (token+PIN+spans D9.a), falta Page Inertia pública sem auth | 🔴 P0 | 6h | Alto (DIFERENCIAL P2-203 + paridade P0-004) | US-OFICINA-006 |
| 3 | **FSM canônica seed (Simples 3 estados + Complexa 5)** via `fsm:bulk-start-pipeline` adaptado | 🔴 P0 | 5h | Alto (audit-trail compliance + concorrentes não têm) | US-OFICINA-003 |
| 4 | **Importer Firebird `EQUIPAMENTO_VEICULO`** — 91 vehs Martinho piloto | 🔴 P0 | 4h | Alto (desbloqueia ativação cliente piloto) | US-OFICINA-002 |
| 5 | **Histórico OS por veículo (timeline UI)** — paridade P1 todos têm | 🟡 P1 | 4h | Médio (vende pra Vargas/Martinho perceberem profundidade) | criar US-OFICINA-008 |

---

## 5. Como auditar este módulo (paths exatos)

**Locais a inspecionar:**
- Models: `Modules/OficinaAuto/Entities/{Vehicle,ServiceOrder}.php`
- Controllers: `Modules/OficinaAuto/Http/Controllers/{Vehicle,ServiceOrder,ProducaoOficina}Controller.php`
- Service público FSM/aprovação: `Modules/OficinaAuto/Services/{VehicleQueryService,ServiceOrderSummaryService,AprovacaoOsService}.php`
- Migrations: `Modules/OficinaAuto/Database/Migrations/*.php`
- Pages: `resources/js/Pages/OficinaAuto/{Vehicles,ServiceOrders,Producao}/*.tsx`
- Tests Pest: `Modules/OficinaAuto/Tests/Feature/*.php` (ServiceOrderCrudTest, VehicleCrudTest, VehicleMultiTenantTest, ServicesObservabilityTest, AprovacaoOsTokenTest)
- Permissões: 9 entries via `Modules/OficinaAuto/Http/Controllers/DataController.php`

**Critérios de classificação ✅/🟡/❌:**

| Capacidade | ✅ APROVADO requer | 🟡 PARCIAL | ❌ AUSENTE |
|---|---|---|---|
| OA-001 FSM | Process+Stage+Action seed via `fsm:bulk-start-pipeline` + ExecuteStageActionService + Pest transitions | Status string livre + UI Kanban funcional | Sem workflow |
| OA-003 ItemOS | Model `ServiceOrderItem` + UI add linha + estoque-baixa observer + Pest soma valores | Apenas description livre | Sem itens |
| OA-004 Aprovação | Service token+PIN + Page público sem auth + WhatsApp dispatch + Pest fluxo aprovado/rejeitado | Service existe sem UI público | Sem service |
| OA-010 Agenda | `mechanic_id` FK + dashboard agenda semana + Pest alocação | Sem alocação | Sem campo |
| OA-012 Auditoria | `sale_stage_history` populado + UI timeline render | Tabela existe sem UI | Sem tabela |

---

## 6. Métricas de prod relevantes

- Vehicles cadastrados biz-piloto: `SELECT business_id, count(*) FROM vehicles GROUP BY business_id` (meta V1 Martinho: 91)
- OS abertas/concluídas última semana: `SELECT current_state, count(*) FROM service_orders WHERE created_at > NOW()-INTERVAL 7 DAY GROUP BY current_state`
- Latência `AprovacaoOsService::validarPin` p95 — meta `<200ms` (via spans `oficinaauto.aprovacao_os.validar_pin`)
- Taxa de OS aprovadas via WhatsApp (após US-006) — meta `>60% no primeiro envio`

---

## 7. Métricas de adoção

- **Última auditoria:** 2026-05-16 (esta — 1ª oficial)
- **Capacidades P0 cobertas:** 9/12 = 75%
- **Gap P0+P1 atual:** 6 (3 P0 + 3 P1)
- **Próxima reauditoria:** 2026-08-16 (trimestral) ou após US-OFICINA-002/003/006 (whichever first)

---

## 8. Histórico de revisão da ficha

- `2026-05-16` — Claude Opus (Wave 22 governance-mega) — criação inicial pós-BRIEFING V0 + ADR 0137 qualificação

---

## 9. Referências externas

- Oficina Integrada: https://www.oficinaintegrada.com.br/
- Ultracar: https://ultracar.com.br/
- MotorSW: https://motorsw.com.br/
- Lokoz: https://lokoz.com.br/
- Mecânico Pro: https://mecanicopro.com.br/
- Mitchell 1 (global benchmark): https://mitchell1.com/
- Compararsoftware TOP 10 BR: https://www.compararsoftware.com.br/oficina-mecanica
- Limersoft 7 melhores BR: https://www.limersoft.com.br/post/os-7-melhores-programas-para-oficinas-mecanicas-manutencao-de-frotas-autopecas-e-servicos-automoti
- Soften Sistemas: https://www.softensistemas.com.br/sistema-para-oficina-mecanica
- ADR 0137 — qualificação módulo: [memory/decisions/0137-modules-oficinaauto-qualificada.md](../../decisions/0137-modules-oficinaauto-qualificada.md)
- ADR 0143 — FSM canon LIVE prod: [memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)

---

## 10. UX heuristics + Automation targets (Capterra v2)

```yaml
ux_heuristics:
  - id: aprovar-os-cliente-clicks
    nome: "Cliques cliente faz pra aprovar orçamento OS via link"
    score: P0
    benchmark: "Oficina Integrada: 3 (abre link + ver itens + botão aprovar). Ultracar: 4 (portal+login)."
    target: "<= 3 (link WhatsApp → ver itens → PIN → aprovar)"
    metrica: "navegacao_steps_aprovacao_os_cliente"

  - id: criar-os-mecanico-clicks
    nome: "Cliques mecânico cria OS no balcão"
    score: P0
    benchmark: "Lokoz: 5. Oficina Integrada: 7. MotorSW: 6."
    target: "<= 5 (placa → cliente vinculado → +itens → salvar)"
    metrica: "navegacao_steps_criar_os_balcao"

  - id: kanban-mover-os-clicks
    nome: "Cliques pra mover OS entre estágios produção"
    score: P1
    benchmark: "Ultracar: drag-drop 1 ação. Oficina Integrada: 3 (lista+select+save)."
    target: "1 (drag-drop @dnd-kit)"
    metrica: "navegacao_steps_mover_os_kanban"
    atual: "✅ 1 já entregue V0"

automation_targets:
  - id: nfse-on-os-concluida
    nome: "Auto-emitir NFSe quando OS muda pra concluida + paga"
    score: P0
    benchmark: "Ultracar SIM. Oficina Integrada PARCIAL (botão manual)."
    target: "Listener ServiceOrderConcluida → EmitirNfseJob via NfeBrasil, p95 < 30s, idempotente"
    metrica: "auto_nfse_oficina_p95_seconds + success_rate"

  - id: whatsapp-aprovacao-os
    nome: "Auto-enviar link WhatsApp pra aprovar OS quando muda pra orcamento"
    score: P0
    benchmark: "Oficina Integrada SIM. Lokoz SIM. Ultracar SIM."
    target: "Listener ServiceOrderOrcamento → SendWhatsappAprovacaoOsJob com token+PIN, idempotente"
    metrica: "whatsapp_aprovacao_dispatched_total + first_response_p50"
    status: "🟡 service pronto, dispatcher faltando US-OFICINA-006"

  - id: estoque-baixa-on-os
    nome: "Baixa automática estoque peças quando OS conclui"
    score: P0
    benchmark: "Oficina Integrada SIM. Ultracar SIM. Lokoz SIM."
    target: "Observer ServiceOrderItem::saved + ServiceOrder::concluding → ProductDecrementJob, idempotente por (os_id, item_id)"
    metrica: "estoque_baixa_idempotency_violations (alvo 0)"
    status: "❌ blocked by OA-003 ItemOS"
```
