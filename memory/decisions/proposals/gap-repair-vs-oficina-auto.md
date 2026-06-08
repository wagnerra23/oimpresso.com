# Gap analysis Modules/Repair vs ERP oficina auto — 2026-05-09

> **Status:** pesquisa de viabilidade técnica. NÃO é compromisso de construir.
> ADR 0105 vale: backlog só recebe item se cliente paga + reporta OU métrica detecta drift.

## Estado atual Modules/Repair

`Modules/Repair` é um módulo Laravel modular nWidart herdado do UltimatePOS,
originalmente desenhado pra **assistência técnica genérica de aparelhos**
(celular, eletrônico, equipamento). Ele já roda multi-tenant via `business_id`
em todas as 3 tabelas próprias (`repair_statuses`, `repair_device_models`,
`repair_job_sheets`) + colunas anexadas a `transactions` (12 campos
`repair_*`) e `business` (`repair_settings`, `repair_jobsheet_settings`),
seguindo o padrão Tier 0 ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)).

A entidade central é a **JobSheet** (OS de reparo): tem `contact_id` (cliente),
`brand_id` + `device_id` (Category) + `device_model_id`, `serial_no`,
`service_type` (carry_in/pick_up/on_site), `defects` (texto livre), `checklist`
(JSON), `parts` (JSON com `variation_id` → produtos UltimatePOS = peças com
estoque), `service_staff` (técnico = User), `status_id`
(`repair_statuses` configurável por biz), `estimated_cost`, `delivery_date`,
`security_pwd`/`security_pattern` (irrelevante pra auto, mas customizável),
5 `custom_field_*`, e morphMany `media` (fotos/anexos) via `Media` core.

**Ponta a ponta hoje**: criação OS → atribui técnico → status pipeline →
add peças (decremente estoque do produto Variation) → finaliza com
`Transaction` linkada (`repair_job_sheet_id`) → emite NFC-e via integração
NfeBrasil ([US-NFE-002](../../auto-mem/project_nfebrasil_estado_2026_05_07.md)) →
notifica cliente via SMS/email (templates por status). Tem ainda
`CustomerRepairStatusController` público (cliente consulta status pelo nº OS),
print label/PDF, dashboard, e desde **PR #363 (2026-05-09)** Kanban
**Producao Oficina** com vocabulário automotivo (placa, vehicle, brand, km,
box, orçamento, mecânico) e drag-drop entre 5 colunas.

A camada UI Inertia/React já tem 6 Pages: Dashboard, JobSheet/Index,
Status/Index, DeviceModels/Index, ProducaoOficina/Index, Index. Vocabulário
"oficina auto" já vazou pro frontend (campo `plate`, `vehicle`, `mecanico`,
`box`, `elevador`) — mas mapeado heuristicamente pra
`serial_no`/`device_model.name`/`Brand.name`/`User`.

## Tabela de cobertura (20 capacidades)

| # | Capacidade | Status oimpresso | Esforço gap (IA-pair, fator 10x) |
|---|------------|------------------|-----------------------------------|
| 1 | Cadastro veículo c/ placa+chassi+ano+modelo+cor+km+cliente | 🟡 parcial — `JobSheet.serial_no` carrega placa no frontend, mas **não há entidade Veículo persistente**. Cada OS cadastra do zero | ~6h: criar `repair_vehicles` (placa unique por biz, chassi, ano, modelo, km_atual) + FK em JobSheet + form Inertia |
| 2 | Consulta CRLV/CRV (placa → Renavam) | ❌ ausente | ~12h: integração API SerPro/Detran (oficial) OU APIs paralelas (Placa API, ApiBrasil); cert + custo R$/consulta + cache + UI lookup; humano-limitado parcialmente (homologação SerPro) |
| 3 | Histórico veículo (todas OS passadas) | 🟡 trivial após #1 — JobSheet.vehicle_id `hasMany` | ~2h: relacionamento + Page Histórico/Show |
| 4 | OS com etapas (recebimento → diagnóstico → orçamento → aprovação → peças → mecânico → teste → entrega) | ✅ **completo** — `repair_statuses` configurável por biz + sort_order + sms/email_template + is_completed_status; Kanban PR #363 já materializa 5 colunas | 0h |
| 5 | Tabela tempária (preço hora-homem por tipo serviço) | 🟡 parcial — UltimatePOS `products` tipo "service" cobre, mas não há catálogo "tempário" (tabela ABRA-OS estilo) | ~8h: seed catálogo SINDIPEÇAS/ABRA + UI listagem + FK em JobSheet items |
| 6 | Catálogo peças (cód fabricante, OEM, similar) | 🟡 parcial — `products` UltimatePOS tem `sku`, brand, custom_fields; falta campo OEM e relação "similar/equivalente" | ~6h: 2 colunas + tabela pivô `product_similars` |
| 7 | Estoque peças (movimento, ponto de pedido) | ✅ **completo** — UltimatePOS Variation/PurchaseLine/SellLine + `alert_quantity` core | 0h |
| 8 | Pré-cadastro fornecedores + cotação | 🟡 parcial — `contacts.type=supplier` existe; falta fluxo cotação RFQ | ~10h: `purchase_quotes` + UI compare 3 fornecedores |
| 9 | NFS-e (serviço) + NFC-e (peça) | 🟡 NFC-e ✅ pronta (US-NFE-002 biz=1 smoke ready); **NFS-e ausente** — humano-limitado: certificação SEFAZ municipal varia 5570 municípios | ~40h codáveis + ~30 dias wallclock pra homologar 1 município (Joinville/SC) |
| 10 | Aprovação OS via WhatsApp (link + PIN) | ❌ ausente | ~16h: WhatsApp Cloud API (já tem token Meta no projeto) + endpoint público `/aprovar/{token}` + state machine pendente→aprovado→reprovado |
| 11 | Foto antes/depois (mecânico anexa OS) | ✅ **completo** — `morphMany Media` já existe + upload_doc + multi-image | 0h |
| 12 | Garantia serviço (registro + lembrete pós-X dias) | ❌ ausente — campo `repair_warranty_id` em transactions existe mas sem fluxo | ~10h: `warranty_periods` + Job scheduled lembrete |
| 13 | Lembrete revisão (6m/10mil km) | ❌ ausente | ~12h: Job daily comparando `vehicle.km_atual + tx_date` + WhatsApp template |
| 14 | Contas a pagar/receber | ✅ **completo** — Modules/Financeiro ([reference_financeiro_integracao.md](../../auto-mem/reference_financeiro_integracao.md)) | 0h |
| 15 | Caixa diário (fechamento) | ✅ **completo** — UltimatePOS `cash_registers` core | 0h |
| 16 | Comissão mecânico (% por OS) | 🟡 parcial — UltimatePOS `essentials_commission_agents` cobre vendas, não OS | ~8h: extender pra `repair_job_sheet.service_staff` |
| 17 | App mobile mecânico (vê OS, marca status) | 🟡 parcial — PWA via Inertia `producao-oficina` JÁ funciona em mobile (Tailwind responsive). App nativo seria custo pesado | ~4h: tailwind tweaks + PWA manifest + offline-first opcional |
| 18 | Painel cliente (status online) | ✅ **completo** — `CustomerRepairStatusController` rota pública `/repair-status` | 0h |
| 19 | Integração ARLA/RNS / regulamentos setoriais | ❌ ausente, low priority pra MVP | ~20h por integração (pesquisar quais clientes pedem) |
| 20 | Relatórios DRE / margem por mecânico / margem por cliente | 🟡 parcial — Modules/Financeiro tem DRE base; falta cortes "por mecânico" e "por cliente recorrente" | ~10h: queries + Inertia Page Dashboard |

**Legenda:** ✅ tem completo · 🟡 parcial · ❌ ausente

## Esforço total pra "MVP oficina auto" (cobrir 12 capacidades essenciais — 60%)

Essenciais escolhidas (=os 12 que cliente piloto reconheceria como mínimo viável):
**#1, #3, #4, #5, #6, #7, #9-NFC-e-only, #10, #11, #14, #15, #18**

- #1 Veículo persistente: **6h**
- #3 Histórico (após #1): **2h**
- #4 OS etapas: **0h** ✅
- #5 Tempário básico: **8h**
- #6 Catálogo peças OEM: **6h**
- #7 Estoque: **0h** ✅
- #9 NFC-e (peça) only: **0h** ✅ (NFS-e fica pra v2)
- #10 WhatsApp aprovação: **16h**
- #11 Foto: **0h** ✅
- #14, #15 Financeiro/Caixa: **0h** ✅
- #18 Painel cliente: **0h** ✅

**Total codável MVP: ~38h IA-pair × 2x margem = ~76h ≈ 10 dias úteis Felipe**
(equivalente humano sem IA-pair: ~380h ≈ 50 dias = 10 semanas)

## Esforço total pra "ERP oficina auto completo" (todas 20)

- Soma das colunas "esforço gap": **~158h codáveis × 2x margem = ~316h ≈ 40 dias úteis Felipe**
- **+30 dias wallclock humano-limitado** (homologação SEFAZ NFS-e em 1 município, certificação SerPro/Detran, smoke real com cliente piloto)

**Total ERP completo: ~70 dias úteis Felipe + 30 dias wallclock = ~3 meses corridos**

## Análise: vale a pena?

**Reaproveitamento atual estimado: 55-60%.**

**O que muda menos (já serve quase 1:1):**
- Estrutura OS (JobSheet → fits perfeitamente OS automotiva, basta renomear)
- Status configurável + Kanban + drag-drop ✅ entregue PR #363
- Cliente (Contact UltimatePOS) ✅
- Financeiro/Caixa/NFC-e ✅
- Estoque peças (Variation/SellLine) ✅
- Foto OS (Media morphMany) ✅
- Painel cliente público ✅
- IA Jana (ContextSnapshotService já tem hook `repair_job_sheet`)

**O que muda mais (precisa código novo):**
- Cadastro veículo persistente (entidade nova; hoje cada OS recadastra)
- Tabela tempária (catálogo brasileiro setorial)
- Catálogo peças OEM/similar (extender products)
- WhatsApp aprovação OS
- Garantia + lembrete revisão (Jobs schedule)
- Comissão mecânico (extender essentials_commission_agents)

**Comparativo: construir do zero seria ~3-4x mais caro** (~140 dias Felipe).
Reusar `Modules/Repair` economiza **~70 dias** ≈ **R$ 200k em custo dev**
(@ ~R$ 3k/dia mid-level).

## Recomendação

**Adiar com critério de gatilho** (alinhado ADR 0105 — cliente como sinal qualificado):

1. **Não construir agora.** Sem 1 piloto pago + Larissa/ROTA LIVRE estável,
   abrir 2º vertical com 5 pessoas (W/M/F/L/E) viola WIP e dilui MWART/Jana.

2. **Gatilho pra MVP-12 (10 dias Felipe + canary):**
   - 1 oficina paga R$ 500-800/mês de mensalidade upfront 3 meses
   - OU concorrente direto (Mubisys/Mecânico/Auto Manager) sai do mercado e libera demanda
   - OU Wagner conhece dono de oficina via networking gráfica (cross-sell vertical)

3. **Backlog ADR-feature-wish:** Criar `memory/decisions/wish/oficina-auto-vertical.md`
   com este gap + 12 essenciais priorizadas. Não vira US ativa até gatilho.

4. **Reaproveitar achado pro vertical gráfica:** O Kanban Producao Oficina
   já está em produção e Larissa pode usar pra OS de impressão. Validar
   primeiro lá (sinal real) antes de pivotar pra auto.

## Riscos

- **Multi-vertical pode poluir Modules/Repair** (gráfica vs auto). Mitigação:
  branch separada `Modules/RepairAuto` ou flag `business.repair_settings.vertical=auto|tecnica|grafica`. Custo +5h pra introduzir.
- **Suporte 2 verticais com 5 pessoas = capacidade limitada.** Mitigação:
  focar 1 vertical até R$ 2-3M ARR (1 vertical comprovado > 2 mornos).
- **Mubisys/Mecânico/Auto Manager têm endorsements setoriais** (sindicatos auto,
  consultorias). oimpresso começaria do zero — CAC alto, ciclo venda longo.
- **NFS-e municipal é poço sem fundo** — 5570 municípios, cada um com
  homologação própria. Limitar MVP a 1 município (cliente piloto define).
- **Cadastro veículo recadastra a cada OS hoje** — usuários acostumados.
  Migração de dados existentes precisa script idempotente quando #1 entrar.
- **Vocabulário "automotivo" já em produção** (Producao Oficina) sem entidade
  Veículo real → débito técnico. Se MVP não acontecer em 12m, decidir reverter
  copy pra termos genéricos OU formalizar.

---

**Conclusão executiva:** `Modules/Repair` cobre **~55-60% de um ERP oficina
auto** já hoje. MVP-12-essenciais custa **~10 dias Felipe** (não 10 semanas) —
viável tecnicamente. Mas **sem cliente pagante + sinal qualificado, o ROI
é negativo** (oportunidade-custo de MWART Financeiro + Jana memória).
Adiar com gatilho explícito; reaproveitar Kanban no vertical gráfica
primeiro pra colher sinal real de drag-drop OS.
