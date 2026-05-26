# Modules/OficinaAuto

> Vertical oficinas automotivas BR (CNAEs 4520-0/01 Manutenção/Reparação de Veículos, 2212-9/00 Recapagem, 4581-4/00 Locação de Caçambas).
> **Status:** V0 em construção — sinal qualificado **Martinho Caçambas** (ADR 0137).
> **Tier 0:** Multi-tenant `business_id` global scope ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).

## Por que existe

Sistema operacional pra oficina/locadora pequena+média BR que UltimatePOS núcleo + Repair genérico não cobre:

- **Veículo é entidade primária** (placa, chassis, RENAVAM PII-protected) — não produto+serial
- **OS específica automotiva** (locação caçamba × manutenção × recapagem)
- **WhatsApp aprovação por PIN+token** assinado HMAC (US-OFICINA-006)
- **FSM canônica** Simples (3 estados) + Complexa (5 estados) — ADR 0143 LIVE

## Cliente piloto

**Martinho Caçambas LTDA biz=164 Capivari de Baixo SC** (sinal qualificado CYCLE-06 — ADR 0105). Caso de uso primário: **mecânica pesada caminhão basculante + loja peça hidráulica + oficina autorizada** (sub-vertical 4 CNAE 4520 · ADR 0194 — pré-correção dizia "locação de caçambas com manutenção esporádica"). Caminhões DE CLIENTES entram pra peça/serviço; não frota Martinho.

Candidatos secundários: Vargas (recapagem complexa fluxo 5 estados).

## Journey real biz=1 (Wagner dev)

| Passo | Onde | Resultado esperado |
|-------|------|-------------------|
| 1. Login biz=1 + entrar `/oficina-auto/veiculos` | `VehicleController@index` | Lista veículos com KPIs (disponivel/locada/manutencao/atrasada) |
| 2. Criar veículo placa `WAG0001`, tipo `cacamba_basculante` | `POST /oficina-auto/veiculos` | Veículo persistido com `business_id=1` |
| 3. Abrir OS de manutenção pra `WAG0001` | `POST /oficina-auto/ordens-servico` | OS criada status `aberta` |
| 4. Transição FSM → `em_servico` → `concluida` | `ExecuteStageActionService` (US-OFICINA-003) | Audit log em `sale_stage_history` |
| 5. Gerar link WhatsApp aprovação (OS orçamento) | `AprovacaoOsService::gerarTokenAprovacao` | Token HMAC + PIN 4 dígitos no cache 7d |
| 6. Cliente abre link em browser anônimo, digita PIN | `AprovacaoOsService::validarToken/validarPin` | Lockout 30min após 5 tentativas erradas |

## Estrutura

```
Modules/OficinaAuto/
├── Config/                 # retention.php (LGPD Art. 16)
├── Console/                # cleanup commands
├── Database/Migrations/    # 2025_*_create_vehicles + service_orders
├── Entities/
│   ├── Vehicle.php         # multi-tenant Vehicle (PII redactor RENAVAM/chassis)
│   └── ServiceOrder.php    # OS append-only audit
├── Http/
│   ├── Controllers/        # VehicleController, ServiceOrderController, ProducaoOficinaController
│   └── Requests/           # Form Request validation
├── Policies/               # RBAC Spatie (oficinaauto.vehicle.*, oficinaauto.service_order.*)
├── Providers/
├── Routes/web.php          # /oficina-auto/* (middleware UltimatePOS stack)
├── Services/
│   ├── AprovacaoOsService.php      # HMAC + PIN + lockout (US-OFICINA-006)
│   ├── VehicleQueryService.php     # Wave 18 — listar/contagem com spans OTel
│   └── ServiceOrderSummaryService.php # Wave 18 — KPIs dashboard com spans OTel
└── Tests/Feature/          # 10 testes Pest (FSM, multi-tenant, LGPD, security, services)
```

## Permissões Spatie (com sufixo `#{biz}` per ADR 0093)

- `oficinaauto.vehicle.{view,create,update,delete}#1`
- `oficinaauto.service_order.{view,create,update,delete}#1`

## Observabilidade D9.a (ADR 0155)

Spans canon (zero-cost se `otel.enabled=false`):
- `oficinaauto.vehicle.listar`
- `oficinaauto.vehicle.contagem_por_status`
- `oficinaauto.vehicle.buscar`
- `oficinaauto.so.kpis_dashboard`
- `oficinaauto.so.contagem_por_status`
- `oficinaauto.so.proximas_vencer`
- `oficinaauto.aprovacao.gerar_token`
- `oficinaauto.aprovacao.validar_token`
- `oficinaauto.aprovacao.validar_pin`

Atributos sempre `business_id` Tier 0 + `module=OficinaAuto`. Sem PII em attributes.

## LGPD ([ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4)

- `pii_fields_tracked`: plate, secondary_plate, chassis, renavam, contact_id
- `pii_redactor_enabled`: true (defense in depth — `Modules\Jana\Services\Privacy\PiiRedactor`)
- `activity_log_enabled`: true (audit trail via Spatie ActivityLog)
- Retenção: 1825 dias (5 anos) por `Config/retention.php`

## Referências

- SPEC: `memory/requisitos/OficinaAuto/SPEC.md`
- BRIEFING: `memory/requisitos/OficinaAuto/BRIEFING.md`
- ROADMAP: `memory/requisitos/OficinaAuto/ROADMAP.md`
- Charter: `memory/requisitos/OficinaAuto/OficinaAuto.charter.md`
- Mockup demo: `memory/requisitos/OficinaAuto/demo-martinho-2026-05-13/`
- ADR mãe: [0137](../../memory/decisions/0137-modules-oficinaauto-qualificada.md)
- FSM: [0143](../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
