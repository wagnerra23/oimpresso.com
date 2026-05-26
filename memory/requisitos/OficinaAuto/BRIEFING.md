---
module: OficinaAuto
status: em-uso
cnae_principal: "4520-0/01"
piloto: Martinho Caçambas LTDA biz=164 Capivari de Baixo SC (sub-vertical 4 mecânica pesada) + Vargas Recapagem (sub-vertical 2 V1)
ultima_atualizacao: 2026-05-26
nota_capterra: 63 (a recalibrar via US-OFICINA-034 vs concorrentes corretos pós-ADR 0194)
nota_fsm_screen: 80 (estado-da-arte gaps #1+#2+#3 LIVE 2026-05-20)
related_adrs: [0093, 0094, 0101, 0105, 0121, 0129, 0137, 0143, 0171, 0192, 0194]
owner: [W]
---

# BRIEFING — Modules/OficinaAuto

> Estado consolidado da capacidade. 1 página executiva. Atualizado por PR mergeado (skill `brief-update` Tier B).
>
> **Última correção (2026-05-26):** domínio Martinho reclassificado de "locação caçamba container CNAE 4581" pra **"mecânica pesada / autorizada caminhão basculante CNAE 4520"** ([ADR 0194](../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)). Vocabulário atualizado pra peça hidráulica · PTO · hora-trabalho. Schema `daily_rate`/`expected_return_date` preservado nullable (sub-vertical 3 hipotético sem cliente real).

## Missão

Vertical especializado pra **oficinas de manutenção/reparação mecânica de veículos automotores pesados** — CNAE 4520-0/01 principal. Cobre 4 sub-verticais ([dominios-verticais-oimpresso.md](../../reference/dominios-verticais-oimpresso.md)):

| Sub-vertical | CNAE | Cliente piloto | Status |
|---|---|---|---|
| 1. Mecânica geral | 4520-0/01 | a aguardar sinal | proposed |
| 2. Recapagem pneus | 2212-9/00 | Vargas (Cliente_874398, V1) | qualified ADR 0137 |
| 3. Locação caçamba container | 4581-4/00 | hipótese sem cliente real | hypothesis (schema preservado) |
| **4. Mecânica pesada + autorizada caminhão basculante** | **4520-0/01** | **Martinho Caçambas LTDA biz=164 Capivari/SC** | **🟢 LIVE prod** ADR 0171 |

Cliente piloto **Martinho biz=164** opera em prod desde 2026-05-13: 91 caminhões de clientes importados + 44.709 vendas Firebird + 103k títulos. Faturamento estimado R$ [redacted Tier 0]M+/mês. Pacote oimpresso R$ [redacted Tier 0]/mês inclui núcleo + OficinaAuto + NFe + NFSe (grandfathered ADR 0171). Add-on WhatsApp R$ [redacted Tier 0]/instância beta 30d.

Posicionamento comercial: **autorizada concessionária-like** pra caminhão pesado de transporte (basculante, Polli-guindaste, plataforma, munck). Caminhão de cliente entra pra peça/serviço programado — NÃO lataria/reparação de batida.

## Capacidades atuais (V0 — LIVE prod)

### Infraestrutura modular (8 peças nWidart canon)

`module.json`, `ServiceProvider`, `RouteServiceProvider`, `InstallController`, `DataController`, `Routes`, `Config`, `composer` — módulo independente do core UltimatePOS.

### Schema multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

- `vehicles` — multi-placa nullable (PLACA + PLACA2 cavalo+reboque sub-vertical 2 Vargas; só PLACA sub-vertical 4 Martinho), `legacy_id` pra mapping Firebird
- `service_orders` — `vehicle_id` FK + `transaction_id` nullable UltimatePOS + `order_type` (`manutencao` Martinho · `locacao` schema órfão sub-vertical 3 hipotético preservado nullable)
- **Schema órfão pós-ADR-0194** (preservado sem drop, review_trigger M6+): `daily_rate` · `expected_return_date` · `delivery_address` · accessor `valor_receber`/`is_overdue` — sub-vertical 3 sem cliente real ancorado

### FSM canônica LIVE ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))

**Fluxo Mecânica pesada Martinho (sub-vertical 4):** `aberta → em_servico → concluida` — OS de caminhão de cliente que entra pra peça/serviço programado.

**Fluxo Recapagem Vargas (sub-vertical 2, V1):** `aberta → orcamento → aprovada → em_producao → concluida → entregue` — multi-item pneu cavalo+reboque.

**FSM screen tríade estado-da-arte 80/100** (LIVE 2026-05-20):
- Timeline auditável drawer ServiceOrderSheet (gap #1 — PR #1195) — quem/quando/motivo/side-effects via `GET /history`
- Mini-grafo horizontal stages (gap #2 — PR #1205) — bullets conectados estilo Linear
- Chips por stage Index (gap #3 — PR #1203) — filtro `?stage=X` com contador bulk `GROUP BY`
- 12 Pest specs novos (history, stages, pipeline) com cobertura multi-tenant Tier 0

### Wave 18 saturação D4/D9

- 2 Services stateless: `VehicleQueryService`, `ServiceOrderSummaryService` com OtelHelper canon (9 spans `oficinaauto.*` mensurados em prod)
- Spans D9.a em `AprovacaoOsService` (gerar_token/validar_token/validar_pin)
- 2 Pest novos: `ServicesObservabilityTest` (8 cenários) + `AprovacaoOsTokenTest` (8 cenários edge)
- `README.md` público + `CHANGELOG.md` canônico
- `module.json` com bloco `governance.fsm_canonico=true` apontando ADR 0143

### UI Inertia/React + UX win destacado

- **8 Pages Inertia** (Vehicles + ServiceOrders × Index/Create/Show/Edit) seguindo MWART canon ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))
- **Kanban ProducaoOficina** (drag-drop @dnd-kit, **placa Mercosul visual stylized**) — feedback positivo Martinho 2026-05-26 *"adorou a placa e design da Oficina ficou top"* (Wagner)
- 3 Pest tests Feature (`ServiceOrderCrudTest`, `VehicleCrudTest`, `VehicleMultiTenantTest`) — biz=1 vs biz=99 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- 9 permissions registradas + sidebar via DataController

### Importer Firebird Delphi WR Comercial (US-OFICINA-002 ✅ done 2026-05-13)

`scripts/legacy-migration/import-vehicles.py` (Python + firebird-driver + pymysql) — 91 vehicles + 91 service_orders importados pra biz=164 às 13:31 BRT. Idempotente via `vehicles.legacy_id`. Pattern reusável pros próximos 6 clientes OfficeImpresso saudáveis (Vargas / Extreme / Gold / Zoom / Fixar / Produart) — [ADR 0171 §"Migration Factory"](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md).

### Auto-faturar OS→Venda derivada ([ADR 0192](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md) extensão · Wave Z-2 · 2026-05-25 LIVE prod biz=164)

- `Modules/OficinaAuto/Observers/ServiceOrderObserver.php` hook `updated` quando `status='concluida'`
- Cria Transaction `source='oficina'` + `os_ref='SO-{id}'` (prefix SO distingue OficinaAuto vs Repair `OS-{id}`)
- Completa 1-1 `service_orders.transaction_id` (FK já existia ADR 0137)
- Idempotência defesa-em-profundidade (transaction_id check + os_ref exists + saveQuietly anti-loop)
- Cross-link bidirecional `/sells` ↔ `/oficina-auto/producao-oficina` (PR #1531 routing por prefix)
- **Cálculo `final_total` GAP pós-ADR-0194** (review_trigger US-OFICINA-027 P0 8h):
  - Hoje: `manutencao = 0` (Wagner edita manual cada OS) · `locacao = daily_rate × dias` (acessor `valor_receber` — schema órfão sem cliente real)
  - V1 sub-vertical 4 correto: `peça × qty + hora-trabalho × horas` quando catálogo peça hidráulica chegar

### Card "Esta OS gerou venda #V-NNNN" no drawer ServiceOrderSheet (Onda 7 · PR #1534)

- Componente shared cross-módulo `@/Components/shared/VendaDerivadaCard.tsx` (extraído verbatim Repair PR #1504 + FASE B PR #1516 — gradiente verde emerald + 3 CTAs Abrir/Imprimir/Compartilhar Web Share API)
- Backend `ServiceOrderController::show()` eager-loads `transaction` + entrega payload `venda_derivada` (V0 core: id/invoice_no/final_total/transaction_date) no JSON `wantsJson()`
- V0 sem breakdown items + fiscal NF-e (FASE B exige extrair `App\Services\VendaDerivadaPayloadService` shared — wave futura)
- 3 Pest GUARDs estruturais + 3 feature tests MySQL (null / populated / multi-tenant biz=1 vs biz=2)
- Repair `ProducaoOficina/Index.tsx` refatorado pra importar shared (24 tests guards preservados)

## Gaps conhecidos (P0-P3 ativos pós-levantamento 2026-05-26)

> Lista cirúrgica do que falta pra Martinho operar plenamente. Fonte: [memory/sessions/2026-05-26-levantamento-martinho-ready.md](../../sessions/2026-05-26-levantamento-martinho-ready.md).

### P0 — bloqueadores reconquista cliente (Sem 22-23)

| US | Esforço | Owner | Descrição |
|---|---|---|---|
| **US-OFICINA-027** | 8h | wagner | Catálogo peça hidráulica V0 + recalc `final_total` OS mecânica (`peça×qty + hora×horas`) |
| **US-OFICINA-028** | 5h | claude | Reescrita BRIEFING + ROADMAP + RUNBOOK + 5 charters pós-ADR-0194 (**este PR é a parte 1: BRIEFING**) |
| **US-OFICINA-029** | 6h | felipe | Cleanup §A — tela batch UI "Revisão pendências legadas" (76.7% inadimplência Martinho) |
| **US-OFICINA-030** | 4h | felipe | Cleanup §B — conciliação VENDA↔FINANCEIRO drift detector (374 vendas R$ [redacted Tier 0]M biz=164) |
| **US-OFICINA-031** | 2h | felipe | Cleanup §C — PESSOAS deduplicador fuzzy match (~920 razões sociais órfãs) |
| US-OFICINA-005 | epic | — | mãe das 3 sub-tasks cleanup acima (epic, não bloqueia individualmente) |

### P1 — UX friction / paridade

| US | Esforço | Owner | Descrição |
|---|---|---|---|
| US-OFICINA-032 | 2h | wagner | Drawer auto-open via `?os=SO-N` query param (Sells→OficinaAuto + Repair) |
| US-OFICINA-033 | 1h | wagner | `ServiceOrder.$fillable` += `contact_id` (mass-assignment fix Wave Z-2 #1) |
| US-OFICINA-006 | (estimado) | — | WhatsApp aprovação OS via link público + PIN (paridade Repair, charter `draft`) |
| US-OFICINA-004 | (estimado) | — | UI Kanban OS multi-item Vargas (V1, sub-vertical 2 recapagem) |

### P2-P3 — débito técnico / canon

| US | Esforço | Owner | Descrição |
|---|---|---|---|
| US-OFICINA-034 | 4h | claude | CAPTERRA-FICHA recalibrada vs concorrentes corretos (Auto Manager / Mecânico Tecnomotor / Plumelp / Sysmecânica) — review_trigger 2026-06-30 |

### Já entregues (✅ done — referência)

- ✅ **US-OFICINA-001** Scaffold módulo nWidart (PR #556)
- ✅ **US-OFICINA-002** Importer Firebird (2026-05-13 — 91 vehicles biz=164)
- ✅ **US-OFICINA-003** FSM canônica via ExecuteStageActionService (PR #1195/#1203/#1205 · gap tríade FSM screen)
- ✅ **Auto-faturar OS→Venda** extensão ADR 0192 (Wave Z-2 PR #1534)
- ✅ **ADR 0194** correção domínio (PR #1593 mergeado 2026-05-26)

## Diferenciais vs mercado (Capterra · a recalibrar US-OFICINA-034)

> **Atenção:** score Capterra atual 63 calculado contra concorrentes errados (locadoras caçamba container Lokoz-like). Recalibração pendente vs sub-vertical 4 correta (Auto Manager · Mecânico Tecnomotor · Plumelp · Sysmecânica · DMS Volvo/Scania/MB).

### Forte hoje

- **Núcleo oimpresso compartilhado:** multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)), Jana IA com memória persistente, NFe-de-boleto-pago automática, FSM auditável LIVE prod
- **UX placa Mercosul + Kanban Producao Oficina visual** — feedback cliente piloto 2026-05-26 *"ficou top"* — diferencial perceptível vs concorrentes oficina pesada que ainda usam tela de lista CRUD genérica
- **Importer Firebird Delphi WR Comercial** — zero-friction migração legacy 26 anos (concorrentes pedem reimportação manual)
- **FSM canon transversal** — mesmo padrão Pipeline em Sells / Repair / OficinaAuto (ADR 0143)
- **Add-on faturável separado** ([ADR 0171](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)) — WhatsApp R$ [redacted Tier 0]/instância paridade ZapBoss + diferencial multi-tenant + auto-link CRM + Jana IA sem cobrança per-seat

### Gap conhecido (a fechar US-OFICINA-027)

- **Catálogo peça hidráulica cross-ref por modelo Scania/Volvo/MB/Ford** — Auto Manager e softwares concessionária já têm. Atualmente ausente em OficinaAuto V0 → bloqueia cobrança automática real (final_total=0 em OS mecânica)

### Não-diferencial (correção honesta pós-ADR-0194)

- **"Locação first-class"** (antes anunciado) — schema `daily_rate`/`expected_return_date` existe mas sem cliente real ancorado. Sub-vertical 3 hipotético — NÃO usar como diferencial até cliente real surgir.

## Arquitetura referência (módulos vizinhos)

- **Modules/Repair** (shared infrastructure — Kanban OS, JobSheet pattern, VendaDerivadaCard)
- **Modules/Vestuario** (produção — ✅ piloto ROTA LIVRE biz=4)
- **FSM canon** [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) (`app/Domain/Fsm/ExecuteStageActionService`)
- **Catálogo peça hidráulica** (gap) — futuro Modules/Compras com cross-ref por modelo + integração potencial com [Tork Tomadas de Força](../../research/clientes-prospect/tork-tomadas-forca/01-perfil.md) (prospect indústria PTO Capivari)

## Próximos PRs (ordem sugerida pós-levantamento 2026-05-26)

> Roadmap 2 semanas pra Martinho operar plenamente — meta 2026-06-09. Fonte: levantamento Martinho-ready §6.

### Sem 22 (2026-05-26 → 2026-06-01) — DESTRAVAR

1. **US-OFICINA-028 BRIEFING** ← este PR (parte 1)
2. **US-OFICINA-028 ROADMAP + RUNBOOK + charters** (sessão seguinte, partes 2-5)
3. **US-OFICINA-033** `$fillable` += contact_id (quick win 1h)
4. **B1 NFSe 500 fix** ([PR #1597](https://github.com/wagnerra23/oimpresso.com/pull/1597)) — paralelo módulo Fiscal

### Sem 23 (2026-06-02 → 2026-06-08) — CLEANUP + COBRANÇA HONESTA

5. **US-OFICINA-029** cleanup §A tela batch UI (felipe 6h)
6. **US-OFICINA-030** cleanup §B drift detector (felipe 4h)
7. **US-OFICINA-031** cleanup §C dedup fuzzy (felipe 2h)
8. **US-OFICINA-027** catálogo peça hidráulica V0 + recalc `final_total` (8h) — destrava cobrança automática real
9. **US-OFICINA-032** drawer auto-open `?os=` query (2h)

### Sem 24+ — DIFERENCIAÇÃO + V1

10. **US-OFICINA-034** CAPTERRA recalibrada vs concorrentes corretos (4h · deadline 2026-06-30)
11. **US-OFICINA-006** WhatsApp aprovação OS PIN (paridade Repair, charter `draft`)
12. **US-OFICINA-004** UI Kanban OS multi-item Vargas (V1 sub-vertical 2 recapagem)

## Refs

- [SPEC.md](SPEC.md) — US completas (US-OFICINA-001..034 + futuras)
- [ROADMAP.md](ROADMAP.md) — Fases 0-5 (pendente reescrita US-OFICINA-028 parte 2)
- [RUNBOOK-migracao-cliente-legacy.md](RUNBOOK-migracao-cliente-legacy.md) — pattern reusável próximos clientes
- [RUNBOOK-fsm-pipeline.md](RUNBOOK-fsm-pipeline.md) — FSM canon LIVE
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — Modular especializado por vertical
- [ADR 0137](../../decisions/0137-modules-oficinaauto-qualificada.md) — Qualificação OficinaAuto (amendado por 0194)
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM canon LIVE prod
- [ADR 0171](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) — Ativação piloto Martinho faseada (amendado por 0194)
- [ADR 0192](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md) — Auto-faturar OS→Venda (`final_total` recalc pendente US-OFICINA-027)
- [ADR 0194](../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — **Correção domínio mecânica pesada (2026-05-26)**
- Perfil cliente: [memory/research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- Prospect cadeia comercial: [memory/research/clientes-prospect/tork-tomadas-forca/01-perfil.md](../../research/clientes-prospect/tork-tomadas-forca/01-perfil.md)
- Dicionário vocabulário: [memory/reference/dominios-verticais-oimpresso.md](../../reference/dominios-verticais-oimpresso.md) §"Sub-vertical 4"
- Levantamento estado atual: [memory/sessions/2026-05-26-levantamento-martinho-ready.md](../../sessions/2026-05-26-levantamento-martinho-ready.md)
