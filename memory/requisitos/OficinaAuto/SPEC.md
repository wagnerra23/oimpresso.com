---
module: OficinaAuto
version: 0.1.0
last_updated: 2026-05-26
status: ativo
lifecycle: ativo
piloto: Vargas + Martinho (sinal qualificado em ADR 0137 — 2 de 4 candidatos OfficeImpresso saudáveis são oficina/recapagem)
piloto_previsao: V0 scaffold 2026-05-11 (PR US-OFICINA-001); V1 importer Martinho Sprint 2+
cnae_principal: "4520-0/01" (serviços de manutenção e reparação mecânica de veículos automotores)
cnaes_cobertos: ["4520-0/01", "2212-9/00", "4581-4/00"]
related_adrs: [0137, 0121, 0094, 0093, 0105, 0106, 0035, 0011, 0089, 0119, 0129]
related_proposals: [proposals/gap-repair-vs-oficina-auto.md]
last_review: 2026-05-26
owners: [W]
---

# Especificação funcional — Modules/OficinaAuto

> ⚠️ **STATUS ATUALIZADO 2026-05-11** — [ADR 0137](../../decisions/0137-modules-oficinaauto-qualificada.md) (`amends` 0121) qualifica `Modules/OficinaAuto` como **em construção** baseado em sinal qualificado (Vargas + Martinho — 50% sample OfficeImpresso saudável). Seções §1-§13 abaixo são do SPEC antecipatório original (2026-05-10) — preservadas como contexto estratégico (concorrentes, posicionamento, pricing). A tabela de US **ativas** é a §V0 logo abaixo. Esquema técnico canônico está em ADR 0137 §"Escopo arquitetural V0".

## US ativas

Ver §V0 logo abaixo — convenção `US-OFICINA-NNN` ([ADR 0134](../../decisions/0134-tasks-create-respeita-spec-placeholders.md)).

## V0 — Scaffold ativo (US-OFICINA-NNN)

> Convenção do ID: `US-OFICINA-NNN` para user stories desta vertical (ADR 0137). O antigo `US-AUTO-NNN` (preservado abaixo §3) era do SPEC antecipatório — não criar novas tasks com esse prefixo, usar `US-OFICINA-NNN`.

> **Formato:** US em headers `### US-OFICINA-NNN · ...` (não tabela) pra parser MCP indexar via regex [ADR 0134](../../decisions/0134-tasks-create-respeita-spec-placeholders.md).

### US-OFICINA-001 · Scaffold módulo V0 (8 peças + Vehicle + ServiceOrder + Pest + Inertia Pages) — **DONE PR #556**

> owner: — · priority: p0 · estimate: 6h · status: done · type: story · origin: ADR-0137
> done: 2026-05-11 · PR: #556 (squash `b72981eb`) · Pest: pendente Wagner validar local

Scaffold completo conforme [ADR 0137 §"Escopo arquitetural V0"](../../decisions/0137-modules-oficinaauto-qualificada.md):
- 8 peças nWidart canônicas (module.json + composer + Config + ServiceProvider + RouteServiceProvider + InstallController + DataController + Routes)
- Migrations `vehicles` (multi-placa nullable) + `service_orders` (vehicle_id FK + transaction_id nullable) — multi-tenant Tier 0 ADR 0093
- Models `Vehicle` + `ServiceOrder` com global scope `business_id` + soft delete + relations
- 9 permissions registradas + sidebar entry "Oficina Auto" via DataController
- 8 Pages Inertia (Vehicles + ServiceOrders × Index/Create/Show/Edit)
- 16 Pest tests (CRUD + multi-tenant biz=1 vs biz=99)
- 4 RUNBOOKs MWART hook satisfaction

**Pendente Wagner:** `php artisan test --filter=OficinaAuto` local + decisão naming `vehicles` vs `oficina_auto_vehicles` antes de US-OFICINA-002.

### US-OFICINA-002 · Importer Firebird `EQUIPAMENTO_VEICULO` → `vehicles` Laravel — **P0**

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story · origin: ADR-0137
> blocked_by: US-OFICINA-001 (done)

Artisan command `officeimpresso:import-vehicles {business_id} {firebird_dsn}` que:
- Conecta Firebird cliente OfficeImpresso (ex: Martinho Caçambas — 91 veículos piloto V0)
- Lê `EQUIPAMENTO_VEICULO` (mapping em [_MAPPING/TELA-LISTA-VENDAS.md §9.4](../../research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md))
- Popula `vehicles` Laravel respeitando `business_id` global scope
- Preserva `EQUIPAMENTO_VEICULO.CODIGO` em `vehicles.legacy_id`
- Pest: smoke biz=1 (Wagner WR2 SC) + integration cruzando 91 vehicles esperados Martinho
- **NOVO insight (Agent F PR #555):** importer deve ter **cleanup rules** — `FINANCEIRO.DT_VENCTO > 365d + sem BOLETO + sem movimentação` → flag `write-off candidate`. ROI maior que dunning pra cliente legacy típico.

### US-OFICINA-003 · FSM canônica OS (3 estados Simples + 5 Complexa) — **P0**

> owner: — · priority: p0 · estimate: 5h · status: todo · type: story · origin: ADR-0137
> blocked_by: US-OFICINA-001 (done), ADR-0129 (FSM canônica)

2 processos seed por business pra `service_orders` ([ADR 0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md) FSM tabular):
- **OS Simples** (Martinho · sub-vertical 4 mecânica pesada caminhão basculante · ADR 0194 — pré-correção dizia "caçamba avulsa"): `aberta` → `em_servico` → `concluida`
- **OS Complexa** (Vargas recapagem multi-item · sub-vertical 2 V1): `aberta` → `orcamento` → `aprovada` → `em_producao` → `concluida` → `entregue`

Importer legacy mapeia `VENDA_ESTAGIO` Firebird → estado FSM correspondente. Pest: state transitions + side-effects.

### US-OFICINA-004 · UI Kanban OS (V1 — multi-item + multi-mecânico) — **P1**

> owner: — · priority: p1 · estimate: 8h · status: todo · type: story · origin: ADR-0137
> blocked_by: US-OFICINA-003

Aproveitar **pré-arte Delphi** ([Controller.Producao.Kanban.pas](../../research/clientes-legacy-officeimpresso/_MAPPING/TELA-PRODUCAO-KANBAN.md)) — Wagner descobriu Kanban industrial built-in com 8 agrupadores + drag-drop. Replicar via @dnd-kit React + persistir estado UI em tabela equivalente a `WR_KANBAN(CHAVE, COLUNA, ORDEM, COLUNA_FECHADA)`. Caso piloto V1: **Vargas** (multi-item média 3 itens/OS).

### US-OFICINA-005 · Cleanup tools pra cliente legacy migrado — **P0 (emergente PR #555)**

> owner: — · priority: p0 · estimate: 12h · status: todo · type: story · origin: Agent-F-investigacao-Martinho-2026-05-11
> blocked_by: US-OFICINA-002 (importer)
> evidence: Martinho 76,7% inadimplência = lixo histórico 2015-19 (não cliente que não paga). Veredito adversarial [04-inadimplencia-investigacao.md](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/04-inadimplencia-investigacao.md)

3 sub-features ROI principal pra cliente OfficeImpresso migrado:
- (a) **Tela "Revisão de pendências legadas"** — batch UI (200/dia × 23 dias) com ações: Baixar / Cancelar / Renegociar / Write-off
- (b) **Conciliação VENDA↔FINANCEIRO** — detectar 374 vendas 12m sem lançamento (R$ 1,64M drift Martinho)
- (c) **PESSOAS deduplicador** — fuzzy match das ~920 razões sociais órfãs (cliente vencido mas não cadastrado)

**Pricing piloto:** R$ 15k one-time + R$ 400/mês. Pitch: *"R$ 4,82M no relatório, só R$ 940k cobrável; R$ 3,88M é fóssil 2015-19. Limpamos em 3 semanas e o relatório passa a ser honesto."*

### Schema V0 (canônico ADR 0137)

#### Tabela `vehicles`

Cadastro de veículos. Multi-placa nullable (cavalo+reboque Vargas case) + ENUM vehicle_type cobrindo 3 sub-verticais (caminhão/cavalo/semi_reboque/caçamba/auto/moto/outro). `legacy_id` preserva CODIGO Firebird EQUIPAMENTO_VEICULO pra importer US-OFICINA-002.

Multi-tenant Tier 0 (ADR 0093): `business_id` indexado + FK CASCADE + global scope no Model.

#### Tabela `service_orders`

OS vinculada a 1 vehicle + opcionalmente 1 transaction UltimatePOS (1 OS = 1 venda). Status livre na V0 (FSM em US-OFICINA-003). Soft delete.

### Permissões V0

- `oficinaauto.access` — acessar módulo
- `oficinaauto.vehicle.{view,create,update,delete}`
- `oficinaauto.service_order.{view,create,update,delete}`

### Inertia Pages V0

- `resources/js/Pages/OficinaAuto/Vehicles/{Index,Create,Show,Edit}.tsx`
- `resources/js/Pages/OficinaAuto/ServiceOrders/{Index,Create,Show,Edit}.tsx`

Todos usam `AppShellV2` Persistent Layout + components shared (`PageHeader`, `EmptyState`).

### Critério de validação V0 → V1 (ADR 0137 §"Critério de validação")

- [x] Scaffold 8 peças (RUNBOOK criar-modulo) — este PR
- [x] Pest tests escritos (Vehicle CRUD + Multi-tenant + ServiceOrder CRUD) — este PR
- [ ] Martinho importado (91 veículos legacy → MySQL) — US-OFICINA-002
- [ ] 1 OS criada manualmente via UI nova — smoke pós-deploy
- [ ] Smoke biz=1 sem regressão (ADR 0101)
- [ ] Canary 7 dias com Martinho sem incidente

---

## §14 — Pipeline FSM canônico OficinaAuto (proposto pós-ADR 0143)

> **Contexto:** [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) tornou o `App\Domain\Fsm` o caminho ÚNICO de pipeline em prod (Sells + Repair LIVE em biz=1). OficinaAuto **DEVE** wirar-se ao FSM canônico em vez de inventar state machine própria. Espelha o pattern Sells/Repair com semântica oficina-específica.
>
> Discovery realizado contra perfis Vargas (recapagem caminhão multi-placa, 181 OS/mês, sem PCP · sub-vertical 2) + Martinho (mecânica pesada caminhão basculante · 91 caminhões de clientes, 8 status distinct + 2 estágios FSM já no legacy · sub-vertical 4 ADR 0194 — pré-correção dizia "caçambas avulsas"). Stages calibrados pra suportar ambos sub-tipos.

### §14.1 Stages canônicos OficinaAuto (15 stages)

| # | Key | Label PT-BR | Cor | Initial? | Terminal? | Notas / motivação cliente |
|---|---|---|---|---|---|---|
| 0 | `recebido_para_diagnostico` | Recebido pra diagnóstico | `slate` | ✅ | — | Veículo na recepção; checklist entrada |
| 1 | `em_diagnostico` | Em diagnóstico | `blue` | — | — | Mecânico avaliando + fotos |
| 2 | `diagnosticado_aguardando_aprovacao` | Diagnosticado — aguardando aprovação | `amber` | — | — | Orçamento gerado, link WhatsApp enviado |
| 3 | `cliente_aprovou_orcamento` | Cliente aprovou orçamento | `cyan` | — | — | 🔒 ReservarEstoque peças + criar OS-filhas comissão |
| 4 | `cliente_rejeitou_orcamento` | Cliente rejeitou orçamento | `red` | — | ✅ T | Vai pra entrega-sem-conserto + KPI taxa rejeição |
| 5 | `aguardando_aprovacao_supervisor` | Aguardando aprovação supervisor (escalada) | `yellow` | — | — | **NOVO oficina** — peças caras (> R$ X config per business) ou re-orçamento. Lateral entrada de #3 |
| 6 | `aguardando_pecas` | Aguardando peças | `purple` | — | — | Reserva confirmada; espera fornecedor |
| 7 | `pecas_chegadas` | Peças chegaram | `indigo` | — | — | Estoque libera mecânico |
| 8 | `em_execucao` | Em execução | `orange` | — | — | Conserto em andamento; clock-in mecânico |
| 9 | `pausado` | Pausado | `gray` | — | — | Espera externa (cliente, fornecedor, peça extra detectada) |
| 10 | `teste_estrada` | Teste de estrada / rodagem | `lime` | — | — | **NOVO oficina** — pós-execução, validação real (recapagem Vargas: ⚠️ obrigatório) |
| 11 | `ajuste_final` | Ajuste final | `teal` | — | — | **NOVO oficina** — re-entrada loop teste→ajuste→teste (típico Vargas pneu) |
| 12 | `concluido_aguardando_retirada` | Concluído — aguardando retirada | `green` | — | — | Pronto + WhatsApp cliente |
| 13 | `entregue_completo` | Entregue ao cliente | `emerald` | — | ✅ T | Caso de sucesso |
| 14 | `cancelado` | Cancelado | `zinc` | — | ✅ T | Override gerente (🔒 LiberarReserva) |
| 15 | `garantia_acionada` | Garantia acionada (re-entrada) | `rose` | — | ✅ T | **NOVO oficina** — cria OS-filha vinculada `os_pai_id`; OS-filha NÃO fatura cliente, alimenta KPI custo-garantia % faturamento |

**Stages novos vs Repair canon (ADR 0143):**
- `aguardando_aprovacao_supervisor` — escalada hierarquica (peças > R$ X / re-orçamento pós-execução)
- `teste_estrada` — diferencial caminhão/oficina pesada (Vargas exige pós-recapagem)
- `ajuste_final` — loop iterativo teste↔ajuste antes de concluir
- `garantia_acionada` — cria OS-filha; comum em oficina (Martinho 8 status distinct → provavelmente já tem)

**Diferenças vs Repair (eletrônico/genérico):**
- Sem `orcamento_aprovado` / `orcamento_rejeitado` separados — usa `cliente_aprovou_orcamento` / `cliente_rejeitou_orcamento` (mais natural PT-BR oficina)
- Adiciona test-loop (10→11→10) que Repair não tem
- Garantia é stage **terminal** (não in-progress) — OS-filha vira nova OS

### §14.2 Actions × roles (proposto — calibrar com Wagner)

| Action | Stages from | Stage to | Role | Side-effect 🔒 |
|---|---|---|---|---|
| `iniciar_diagnostico` | 0 | 1 | `oficina.atendente#{biz}` | — |
| `gerar_orcamento` | 1 | 2 | `oficina.mecanico#{biz}` | `EnviarLinkAprovacaoWhatsapp` |
| `cliente_aprovou` (via link público) | 2 | 3 | `public.token` | 🔒 `ReservarEstoquePecas` |
| `cliente_rejeitou` (via link público) | 2 | 4 | `public.token` | — |
| `escalar_supervisor` | 3, 8, 11 | 5 | `oficina.mecanico#{biz}` | `NotificarSupervisor` |
| `supervisor_aprovou` | 5 | 3 ou 8 | `oficina.supervisor#{biz}` | — |
| `confirmar_pecas_pedidas` | 3 | 6 | `oficina.estoque#{biz}` | — |
| `marcar_pecas_chegadas` | 6 | 7 | `oficina.estoque#{biz}` | `NotificarMecanico` |
| `iniciar_execucao` | 7 | 8 | `oficina.mecanico#{biz}` | 🔒 `ClockInApontamento` |
| `pausar` | 8 | 9 | `oficina.mecanico#{biz}` | `ClockOutApontamento` |
| `retomar` | 9 | 8 | `oficina.mecanico#{biz}` | `ClockInApontamento` |
| `concluir_execucao` | 8 | 10 | `oficina.mecanico#{biz}` | 🔒 `ConsumirEstoque` + `ClockOutApontamento` |
| `iniciar_teste` | 10 | 10 | `oficina.mecanico#{biz}` | — (mantém stage, log) |
| `precisa_ajuste` | 10 | 11 | `oficina.mecanico#{biz}` | — |
| `ajuste_concluido` | 11 | 10 | `oficina.mecanico#{biz}` | — |
| `aprovar_entrega` | 10 | 12 | `oficina.supervisor#{biz}` | `NotificarClientePronto` |
| `entregar_veiculo` | 12 | 13 | `oficina.atendente#{biz}` | 🔒 `EmitirDocumentosFiscais` (split NFe55+NFSe56) |
| `cancelar_os` | 0..12 | 14 | `oficina.gerente#{biz}` | 🔒 `LiberarReservaEstoque` + `CancelarVendaCascade` |
| `acionar_garantia` | 13 (após X dias) | 15 | `oficina.atendente#{biz}` | 🔒 `CriarOsFilhaGarantia` |

Convenção espelha Sells/Repair: roles per-business com suffix `#{biz}` (Spatie), actions `is_critical` exigem role obrigatória (fail-secure).

### §14.3 Transitions inválidas explícitas (anti-bypass governance)

Wagner pain-point original ADR 0143: *"orçamento foi para estágio voltou sem ninguém ter autorizado"*. Aqui idem:

- ❌ `recebido → entregue` direto (pular diagnóstico) — só `cancelar_os` permite atalho
- ❌ `aguardando_pecas → entregue` (pular execução) — só via cancelar
- ❌ `cliente_rejeitou → qualquer não-terminal` — terminal acabou
- ❌ `garantia_acionada → entregue_completo` — OS-filha é fluxo novo

---

## §15 — Schema proposto evolução `Modules/OficinaAuto/Entities`

> Schema V0 (PR #556) preservado. Adições propostas pra V1 (importer Vargas/Martinho + FSM wire-up + diferenciais ROI).

### §15.1 `vehicles` — campos novos

| Campo | Tipo | Default | Motivação |
|---|---|---|---|
| `km_atual` | unsignedInteger nullable | NULL | já presente V0 — usar |
| `combustivel` | enum [gasolina/etanol/flex/diesel/gnv/eletrico/hibrido] | NULL | fiscal NFSe + lembrete revisão |
| `fipe_codigo` | string(20) nullable | NULL | **NOVO** — código FIPE pra valor de mercado (seguro/garantia) |
| `crlv_consultado_em` | timestamp nullable | NULL | cache 30d consulta DETRAN |
| `crlv_dados_json` | json nullable | NULL | snapshot resposta API |
| `observacao_tecnica` | text nullable | NULL | "carro antigo, motor turbinado, atenção freios" — texto livre mecânico |

### §15.2 `service_orders` — campos novos

| Campo | Tipo | Default | Motivação |
|---|---|---|---|
| `current_stage_id` | bigint FK `sale_process_stages` nullable | NULL | **FSM canon** ADR 0143 (opt-in per OS) |
| `km_entrada` | unsignedInteger nullable | NULL | controle quilometragem na recepção |
| `km_saida` | unsignedInteger nullable | NULL | km ao entregar (diff = uso teste estrada) |
| `defeitos_json` | json nullable | NULL | **multi-defeitos** (Vargas: pneu+freio+óleo em 1 OS) — array `[{descricao, gravidade, prioridade}]` |
| `diagnostico_tecnico` | text nullable | NULL | texto livre mecânico pós-diagnóstico (parente de `observacao_tecnica` veículo mas por OS) |
| `aprovado_em_orcamento` | timestamp nullable | NULL | quando cliente clicou aprovar |
| `aprovado_apos_aumento` | bool default false | false | flag re-orçamento (peça extra detectada → cliente re-aprovou) |
| `valor_orcamento_inicial` | decimal(15,2) nullable | NULL | snapshot orçamento original |
| `valor_orcamento_final` | decimal(15,2) nullable | NULL | snapshot pós-execução |
| `os_pai_id` | bigint FK `service_orders.id` nullable | NULL | **garantia** — OS-filha referencia OS-pai |
| `tipo_os` | enum [normal, retorno_garantia, re_entrada, reentrega_defeito] | normal | classificação pra KPI |
| `mecanico_principal_user_id` | bigint FK `users.id` nullable | NULL | atribuição V1 (multi-mecânico = `oa_apontamentos` V2) |

### §15.3 Tabelas novas

**`oa_pecas_utilizadas`** — peças aplicadas em 1 OS (granular per-item)
| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | int FK + scope | Tier 0 |
| `service_order_id` | bigint FK | CASCADE |
| `product_id` | bigint FK products | UltimatePOS produto base |
| `quantidade` | decimal(10,3) | |
| `valor_custo` | decimal(15,2) | snapshot custo (não muda se preço mudar depois) |
| `valor_venda` | decimal(15,2) | |
| `fornecedor_id` | bigint FK contacts nullable | rastreabilidade |
| `garantia_dias` | int default 90 | |
| `oem_code` | string(50) nullable | original/similar |
| `qualidade` | enum [original, oem, genuina, similar] | |

**`oa_servicos_executados`** — serviços (mão-de-obra) por OS
| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | int FK + scope | |
| `service_order_id` | bigint FK | |
| `descricao` | string(255) | |
| `mecanico_user_id` | bigint FK users | |
| `horas_apontadas` | decimal(6,2) | |
| `valor_hora_aplicado` | decimal(10,2) | |
| `valor_servico` | decimal(15,2) computed | `horas × valor_hora` |
| `garantia_dias` | int default 180 | serviço > peça |
| `categoria` | enum [mecanica, eletrica, lanternagem, pintura, diagnostico] | |
| `codigo_tempario` | string(20) nullable | FK futura Sindirepa |

**`oa_garantias`** — garantia GRANULAR per-item (peça OU serviço)
| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | int FK + scope | |
| `service_order_id` | bigint FK | OS origem |
| `item_type` | enum [peca, servico] | morphTo manual |
| `item_id` | bigint | `oa_pecas_utilizadas.id` OU `oa_servicos_executados.id` |
| `garantia_dias` | int | |
| `expira_em` | date | calculado na criação |
| `acionada_em` | timestamp nullable | NULL = não acionada |
| `acionada_os_filha_id` | bigint FK service_orders nullable | OS criada via `acionar_garantia` action |
| `observacoes` | text nullable | "trocada por defeito de fábrica" |
| `lembrete_enviado_em` | timestamp nullable | WhatsApp pré-vencimento |

**Decisão pendente**: granularidade garantia per-item (rastreável, complexo) vs OS-todo (simples, perde rastreio peça-específica). Recomendação: **per-item** — Vargas pode ter peça com 1 ano + serviço com 3 meses na MESMA OS, OS-todo perde isso. Custo extra: 1 join + UI mostra timeline garantia por item.

---

## §16 — Vinculação NFSe56 + NFe55 (split documentos fiscais)

> Reusa pipeline ADR 0143 `EmitirDocumentosFiscais` (já LIVE pra Sells single-doc). OficinaAuto requer **split** porque OS típica oficina = NFC-e/NFe (peça) + NFSe (serviço) em paralelo.

### Cenário canônico Vargas (recapagem)

| Item | Tipo fiscal | Modelo | Valor |
|---|---|---|---|
| Pneu recauchutado 295/80R22.5 | Mercadoria | **NFe modelo 55** ou **NFC-e modelo 65** se PF | R$ 800 |
| Banda de rodagem aplicação | Serviço | **NFSe modelo 56** (LC 116/03 item 14.05 — recapagem) | R$ 500 |
| **Total OS** | — | 2 documentos coexistindo | R$ 1.300 |

### Action FSM `emitir_documentos` (entregar_veiculo side-effect 🔒)

Pseudo-código (não implementar agora):

```
foreach (oa_pecas_utilizadas as peca):
    if cliente.tipo == 'PF' and peca.valor < threshold_nfe:
        dispatch EmitirNfceJob(peca)
    else:
        dispatch EmitirNfeJob(peca)

foreach (oa_servicos_executados as servico):
    dispatch EmitirNfseJob(servico, codigo_lc_116=servico.codigo_lc)
```

Cria 1+N `transaction_documents` linkados ao `transaction_id` da OS. Pipeline US-RB-044 (boleto pago→NFe) já entregue cobre 1 documento — adapter `OficinaAutoNfsService` faz o split.

**Pré-requisito não atendido:** Modules/NFSe driver real (ADR 0143 §"Plano restante" — 0 drivers implementados, 10 US backlog). OficinaAuto V1 só pode emitir NFC-e/NFe55 (estamos cobertos pela Modules/NfeBrasil US-NFE-002). NFSe56 vira P1 quando 1º driver municipal estiver verde.

### Falha graceful — anti-rollback

Se NFSe falhar (SEFAZ municipal down): OS **continua entregue**, lança Event `NfseFalhouAdiar` + cron retry exponencial 24h. Documentação parcial gera transaction_documents.status = `pending_retry`. Wagner pain-point ADR 0143 #1 preservado: nunca pular sequencial fiscal por defeito de OS.

---

## §17 — App campo mecânico (Fase 4 futura)

> Mecânico Vargas trabalha em galpão grande, longe de PC. Mecânico Martinho roda nas caçambas em campo (estacionária = ele sobe e desce do caminhão). UI desktop atual NÃO atende. PWA mobile-first é diferencial alto vs concorrência (só Oficina Integrada Android + Manager Full web mobile entregam isso hoje).

### Capacidades V0 da app

1. **"Minhas OS hoje"** — lista filtrada por `mecanico_principal_user_id = current_user` + `current_stage_id IN (em_execucao, pausado, ajuste_final)` ordenada por SLA
2. **Push notifications** transição — `pecas_chegadas` notifica mecânico, `concluido_aguardando_retirada` notifica recepção
3. **Diagnóstico voz → texto** — Web Speech API (offline graceful) preenche `diagnostico_tecnico`
4. **Foto antes/depois (audit)** — `morphMany Media` já reusável de Modules/Repair; auto-tagging EXIF timestamp+geo
5. **Carimbo digital tempo trabalhado** — clock-in/out POR OS (vira `oa_apontamentos` futuro); botão "Pausei" / "Voltei" 1-tap
6. **Lista peças aplicar** — visualiza `oa_pecas_utilizadas` da OS atribuída pra mecânico não esquecer
7. **Confirmação `concluir_execucao`** — FSM action requer assinatura digital (canvas) ou foto carro montado

### Não-V0 (Fase 5+)

- OBD-II read (Bluetooth) → tirar leitura ECU automática
- Reconhecimento placa via OCR câmera (boas-vindas zero-typing)
- Anotação por voz longa-duração (diagnóstico extenso vira transcript)
- Sync offline-first robusta (galpão Vargas pode ter 4G ruim)

Estimate Fase 4: ~80h IA-pair fator 10x = ~2 semanas Felipe (PWA scaffold + 5 screens + push Centrifugo + clock-in side-effect). **Bloqueado** até FSM canon Repair LIVE + 1 piloto pagante validar.

### Listado como US-OFICINA-FASE-4-001..007 (BACKLOG futuro)

---

## §18 — User stories US-OFICINA-NNN (continuação 003+)

> Continuação das US ativas (001 DONE, 002 P0 backlog). Estimates ADR 0106 (fator 10x IA-pair, margem 2x).

### US-OFICINA-006 · FSM wire-up canônico `ServiceOrder` (espelha Sells/Repair ADR 0143) — **P0**

> owner: — · priority: p0 · estimate: 6h · status: todo · type: story · origin: ADR-0143
> blocked_by: US-OFICINA-001 (done)

- Adicionar `current_stage_id` em `service_orders` migration
- Criar seeder `FsmProcessoOficinaAutoPadraoSeeder` (15 stages × 19 actions × roles)
- `ServiceOrder` Model adota trait `GuardsFsmTransitions`
- `OficinaAutoFsmActionController` espelhando `RepairFsmActionController`
- UI drawer `FsmActionPanel` reuso de Modules/Repair (R5)
- Pest: 15 transition tests biz=1 + cross-tenant biz=99 guard

### US-OFICINA-007 · Importer Vargas (1.064 veículos multi-placa) — **P0**

> owner: — · priority: p0 · estimate: 8h · status: todo · type: story · origin: ADR-0137 + Vargas perfil
> blocked_by: US-OFICINA-002 (Martinho importer paving)

Espelha US-OFICINA-002 (Martinho) mas adiciona:
- Mapping PLACA2/CHASSI2 → `placa_secundaria` / `chassi_secundario`
- Suporte `tipo_veiculo` = `cavalo`/`semi_reboque` distintos
- Importer detecta cliente PESSOA vinculado a múltiplos EQUIPAMENTO_VEICULO (1 transportadora → N caminhões)
- Pest fixture: cliente PF dono de 1 cavalo + 2 reboques distintos

### US-OFICINA-008 · Schema garantia granular per-item (`oa_pecas_utilizadas`+`oa_servicos_executados`+`oa_garantias`) — **P1**

> owner: — · priority: p1 · estimate: 5h · status: todo · type: story · origin: §15
> blocked_by: US-OFICINA-006 (FSM ServiceOrder pra side-effect ConsumirEstoque dispatcher criar registro)

Cria as 3 tabelas + Models + global scope. UI lista garantias ativas + status (válida/vencendo/expirada/acionada).

### US-OFICINA-009 · Defeitos múltiplos por OS (JSON array) — **P1**

> owner: — · priority: p1 · estimate: 3h · status: todo · type: story · origin: Vargas (3.08 itens/OS = média 3 defeitos/peças)
> blocked_by: US-OFICINA-006

Campo `defeitos_json` em `service_orders` + UI form repeater + render pretty no drawer. Form schema `{descricao, gravidade enum[baixa/media/alta/critica], prioridade int}`.

### US-OFICINA-010 · Stages oficina-específicos `teste_estrada` + `ajuste_final` + loop — **P1**

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story · origin: §14.1
> blocked_by: US-OFICINA-006

Inclui no seeder + UI mostra contagem de iterações loop (KPI "média ajustes por OS"). Útil pra Vargas (recapagem requer N passadas teste).

### US-OFICINA-011 · Re-orçamento (action `escalar_supervisor` + flag `aprovado_apos_aumento`) — **P1**

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story · origin: §15
> blocked_by: US-OFICINA-006

Cenário Vargas: mecânico abre pneu, descobre roda interna danificada não prevista, orçamento sobe R$ 200. Action `escalar_supervisor` muda stage temporário → supervisor aprova → volta com `aprovado_apos_aumento=true`. KPI "% OSs com re-orçamento".

### US-OFICINA-012 · Consulta CRLV/placa (cache 30d + adapter pluggable) — **P1**

> owner: — · priority: p1 · estimate: 6h · status: todo · type: story · origin: SPEC antecipatório §US-AUTO-002
> blocked_by: US-OFICINA-001

Adapter `ConsultaPlacaService` (SerPro homologação OU Infosimples R$ 0,15/consulta). Cache `vehicles.crlv_dados_json` 30 dias. Add-on cobrável (não tier-1 free).

### US-OFICINA-013 · Tabela tempária seed (100 serviços comuns BR) — **P1**

> owner: — · priority: p1 · estimate: 5h · status: todo · type: story · origin: SPEC antecipatório §US-AUTO-004
> blocked_by: US-OFICINA-008

Tabela `oa_temparios` + seed manual 100 serviços frequentes (troca óleo, alinhamento, recapagem banda padrão, troca pastilha freio etc) com tempo_horas calibrado. Categoria enum [mecanica, eletrica, lanternagem, pintura, diagnostico].

### US-OFICINA-014 · Aprovação OS via WhatsApp (link público + PIN) — **P0**

> owner: — · priority: p0 · estimate: 7h · status: todo · type: story · origin: SPEC antecipatório §US-AUTO-009
> blocked_by: US-OFICINA-006 (FSM action `cliente_aprovou` precisa estar no seeder)

Endpoint público `/oficina/aprovar/{token}` mostra orçamento mobile-first + PIN 4 dígitos via SMS/WhatsApp. Webhook dispara FSM action `cliente_aprovou` ou `cliente_rejeitou` com role `public.token`. Rate-limit + LGPD consentimento.

### US-OFICINA-015 · App PWA mecânico campo (V0 — minhas OS + foto + clock-in) — **P2**

> owner: — · priority: p2 · estimate: 16h · status: todo · type: story · origin: §17
> blocked_by: US-OFICINA-006, US-OFICINA-008

Scope V0: lista minhas OS + foto antes/depois + clock-in/out botão grande. Sem voz/OBD-II ainda.

### US-OFICINA-016 · Garantia lembrete cron (pré-vencimento WhatsApp) — **P2**

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story · origin: SPEC antecipatório §US-AUTO-013
> blocked_by: US-OFICINA-008

Job daily compara `oa_garantias.expira_em - 7d` → dispara WhatsApp template "Sr. João, garantia do pneu OS-1234 vence em 7 dias — algum sintoma?". Opt-in LGPD obrigatório.

### US-OFICINA-017 · Histórico veículo (timeline OS + KPIs km/manutenção) — **P1**

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story · origin: SPEC antecipatório §US-AUTO-003
> blocked_by: US-OFICINA-006

Page `Vehicles/Show.tsx` aba "Histórico" lista todas OS daquele veículo + foto antes/depois + soma km percorrido entre revisões. Útil Vargas (mesmo caminhão volta a cada 6m recapagem).

### US-OFICINA-018 · NFSe modelo 56 split documentos fiscais — **P1**

> owner: — · priority: p1 · estimate: 10h · status: todo · type: story · origin: §16
> blocked_by: Modules/NFSe driver real (10 US backlog SPEC-NFSE-CANCEL.md ADR 0143)

Adapter `OficinaAutoNfsService.emitirSplit($serviceOrder)` que dispatches N jobs NFe55/NFC-e + M jobs NFSe56 paralelos. Falha graceful (1 documento OK, outro pending_retry). Pré-requisito: 1 driver municipal NFSe verde (Joinville/SC ou cidade piloto).

### US-OFICINA-019 · Comissão por OS (mecânico + atendente, % escalonado) — **P2**

> owner: — · priority: p2 · estimate: 8h · status: todo · type: story · origin: SPEC antecipatório §US-AUTO-011
> blocked_by: US-OFICINA-008 (oa_servicos_executados pra calcular base)

Regras config per-user. Trigger: FSM action `entregar_veiculo` side-effect `CalcularComissaoJob`. Relatório mensal.

### US-OFICINA-020 · Importer Firebird `WR_KANBAN` → `oa_kanban_state` (pré-arte Vargas/Martinho) — **P2**

> owner: — · priority: p2 · estimate: 4h · status: todo · type: story · origin: _LICOES-CRITICAS §8
> blocked_by: US-OFICINA-007 (Vargas importer)

Aproveita Kanban industrial Delphi (descobeto sessão 2026-05-11). Importer lê `WR_KANBAN(CHAVE, COLUNA, ORDEM, COLUNA_FECHADA)` → popula tabela equivalente preservando estado UI do cliente legacy. Bonus migration UX.

### US-OFICINA-021 · Integração FIPE veículo (valor mercado + filtro garantia) — **P2**

> owner: — · priority: p2 · estimate: 4h · status: todo · type: story · origin: §15.1 `fipe_codigo`
> blocked_by: US-OFICINA-001

Adapter consulta FIPE (API pública gratuita) auto-popula `vehicles.fipe_codigo` + valor de referência. Útil pra cap garantia em peças caras (% sobre valor FIPE) ou seguro frota.

### US-OFICINA-022 · Cleanup tools cliente legacy migrado (continua US-OFICINA-005) — **P0 já existe**

(US-005 já cobre — pular ID 022)

**Total estimate US 003-021 (excluindo 022 que já existe):** ~109h codáveis × 2x margem (ADR 0106) = ~6 semanas Felipe IA-pair (assumindo ~20h/semana focal).

---

## Anexo (SPEC antecipatório 2026-05-10)

> Convenção do ID antiga: `US-AUTO-NNN` para user stories, `R-AUTO-NNN` para regras Gherkin.
> **Modulo NÃO existe em código.** Este SPEC é **antecipatório** — formaliza o contrato de construção SE/QUANDO houver cliente piloto pagante (gatilho ADR 0105).
> Antes de scaffoldear (caso ativado), ler [Modules/Repair](../../../Modules/Repair) (shared infra — ADR 0121 §P8) + [Modules/Jana](../../../Modules/Jana) + imitar (ADR 0011).

## 1. Visão

ERP vertical brasileiro pra **oficina mecânica auto SMB** (1–20 mecânicos, 50–500 OS/mês) que substitui Ultracar/Oficina Integrada/Onmotor entregando: cadastro veículo+placa+chassi+km+CRLV, tabela tempária Sindirepa, OS multi-mecânico com Kanban, diagnóstico assistido por IA, catálogo peças OEM/similar, comissão por OS, NFC-e/NFS-e automática a partir de boleto pago — combinação que **nenhum concorrente vertical entrega hoje**.

**Tese de entrada:** quadrante "vertical auto + tech moderno + IA" está vazio (research 2026-05-09). Mubisys/Ultracar/Oficina Integrada têm PCP, mas zero IA conversacional, NFS-e travada (Reclame Aqui Ultracar), stack legacy, sem mobile real.

**Status atual:** **NÃO em construção.** Sem cliente piloto pagante, **viola ADR 0105** ativar trabalho. Modules/Repair já cobre ~55-60% das capacidades (gap-repair-vs-oficina-auto.md) e Kanban Producao Oficina (PR #363) já está em produção com vocabulário automotivo — débito técnico controlado até gatilho.

## 2. Audiência alvo

### Perfil-alvo: oficina mecânica BR de pequeno-médio porte

| Dimensão | Faixa |
|---|---|
| Funcionários | 3–20 (1 dono + 2-15 mecânicos + 1-2 atendimento/financeiro) |
| GMV anual | R$ 600k – R$ 5M |
| OS/mês | 50 – 500 |
| Boxes/elevadores | 2 – 8 |
| Estado fiscal | Simples Nacional (maioria) ou Lucro Presumido |
| CNAE principal | **4520-0/01** (manutenção mecânica) — secundários 4520-0/05 (elétrica), 4520-0/02 (lanternagem/pintura), 4530-7/03 (autopeças) |
| Sistema atual | Ultracar / Oficina Integrada / Onmotor / IS2 Desktop / Excel+WhatsApp |
| Cliente final | PF (90%) + frota PJ pequena (taxi, transporte leve, prestadora serviço) |
| Geografia | 32% concentração SP ([CINAU](https://oficinabrasil.com.br/noticia/mercado-cinau/dimensoes-do-mercado-de-reposicao-quem-somos-onde-estamos-e-quanto-representamos)) |
| TAM | R$ 128bi/ano BR (Sindirepa-SP 2022); 121k oficinas ativas |

### Mecânicas operacionais típicas

1. Cliente chega na oficina (recepção informal) ou liga/WhatsApp marcando hora
2. Atendente/dono recebe veículo: anota placa, km, sintoma, autoriza diagnóstico
3. Mecânico examina; gera orçamento (peças + mão-de-obra via tabela tempária)
4. Cliente aprova (presencial, telefone ou WhatsApp com link/PIN)
5. Mecânico executa serviço; pode dividir entre múltiplos mecânicos (ex: revisão = mecânica + elétrica)
6. Compra peças (estoque interno OU fornecedor — espera chegar)
7. Teste; cliente busca veículo; pagamento (PIX/cartão/boleto)
8. NFC-e (peça) + NFS-e (serviço) emitidas; lembrete revisão futura agendado

### Candidato piloto (FRACO — não satisfaz gatilho)

- **Martinho Caçambas** — CNAE vestuário+caçamba, NÃO oficina mecânica. Mesmo dono pode ter contato com oficinas via fornecedor caçamba, mas sinal indireto (ADR 0105 exige sinal direto: cliente paga + reporta).

**Conclusão:** sem piloto válido. Roadmap deste SPEC é **CONDICIONAL** ao gatilho descrito em §9.

## 3. Capacidades core (User Stories)

Priorização: **P0** = bloqueia 1ª piloto (mínimo viável reconhecível pelo mercado vertical) · **P1** = competitivo vs Ultracar/Oficina Integrada · **P2** = diferencial de longo prazo · **P3** = backlog/feature-wish.

### US-AUTO-001 · Cadastro veículo persistente (placa + chassi + km + ano + modelo + cor) — **P0**

> **Área:** Cadastro
> **Rota:** `GET/POST /oficina-auto/veiculos`
> **Controller:** `VeiculoController`
> **Permissão Spatie:** `auto.veiculo.{view,create,update}`

**Como** atendimento da oficina
**Quero** cadastrar veículo do cliente uma única vez (placa unique por business, chassi 17 chars, ano, marca/modelo, cor, km_atual)
**Para** não recadastrar a cada OS + ter histórico completo do veículo

**Definition of Done:**
- [ ] Tabela `oficina_auto_veiculos` (id, business_id, contact_id FK, placa unique-by-biz, chassi 17, marca, modelo, ano_fabricacao, ano_modelo, cor, combustivel enum [gasolina/etanol/flex/diesel/gnv/eletrico/hibrido], km_atual, observacao)
- [ ] FK em `oficina_auto_os.veiculo_id` (substitui `JobSheet.serial_no` heurístico atual)
- [ ] Validação placa Mercosul (3 letras + 1 dígito + 1 letra + 2 dígitos) OU antiga (3 letras + 4 dígitos)
- [ ] Multi-tenant `business_id` global scope (skill `multi-tenant-patterns` Tier A — ADR 0093)
- [ ] Migração idempotente: vocabulário "placa/vehicle/box/mecânico" já em produção via Producao Oficina (PR #363) sem entidade — script seed converte heurístico→entidade real
- [ ] Pest Feature: cadastro válido + duplicado mesma biz reprovado + isolation cross-biz

**Concorrência:** todos verticais auto têm. **oimpresso 🟡** — JobSheet.serial_no carrega placa em frontend mas sem entidade Veículo persistente.

---

### US-AUTO-002 · Consulta CRLV/Renavam por placa (DETRAN/SerPro) — **P0**

> **Área:** Cadastro
> **Rota:** `POST /oficina-auto/veiculos/consultar-placa`
> **Reusa:** API SerPro oficial OU agregador (Infosimples / API Placas / ConsultarPlaca)

**Como** atendimento
**Quero** digitar placa e o sistema preencher chassi, marca, modelo, ano, situação (regular/débito) automaticamente
**Para** não digitar 12 campos por veículo + detectar débito antes de aceitar serviço

**Definition of Done:**
- [ ] Adapter pluggable (SerPro homologação OU Infosimples R$ 0,15/consulta)
- [ ] Cache 30 dias por placa (TTL config)
- [ ] Add-on cobrável: 200 consultas inclusas tier Pro / 500 tier Premium / sobra R$ 0,49/consulta
- [ ] Fallback gracioso: API down → form manual + flag "dados não validados"
- [ ] LGPD: registro consentimento cliente pra consulta DETRAN (Art. 7º)
- [ ] Audit log toda consulta (CPF/CNPJ requisitante + placa + timestamp)

**Concorrência:** **NENHUM concorrente vertical entrega como tier-1** (research 2026-05-09 — usam APIs paralelas mas não integram nativamente). **Diferencial alto** se nativo.

---

### US-AUTO-003 · Histórico do veículo (todas OS passadas) — **P0**

> **Área:** Cadastro
> **Rota:** `GET /oficina-auto/veiculos/{id}/historico`
> **blocked_by:** US-AUTO-001

**Como** dono/mecânico
**Quero** ver linha do tempo de todas OS daquele veículo (data, mecânico, defeito, peças trocadas, custo)
**Para** decidir manutenção próxima sem adivinhar (ex: "última troca correia 50.000 km, agora está 95.000 — vence")

**DoD:**
- [ ] `oficina_auto_veiculos hasMany os`
- [ ] Page `Veiculos/Show.tsx` com aba Histórico
- [ ] Filtro por período + tipo serviço
- [ ] Export PDF "passaporte do veículo"
- [ ] Bonus: integração com Jana — "última revisão deste Civic foi quando?" responde direto

**Concorrência:** Ultracar ✅, Oficina Integrada ✅, Manager Full ✅. Padrão esperado.

---

### US-AUTO-004 · Tabela tempária (preço hora-homem por tipo serviço) — **P0**

> **Área:** Pricing
> **Rota:** `GET/POST /oficina-auto/temparios`
> **Controller:** `TemparioController`

**Como** dono/atendente
**Quero** cadastrar/importar tabela tempária (ex: alinhamento dianteiro = 0.5h, troca embreagem Gol = 4h, revisão completa = 6h) com valor hora-homem por categoria mecânico
**Para** orçamento sair em 30s sem cálculo manual + padronizar preço entre mecânicos

**DoD:**
- [ ] Tabela `oficina_auto_temparios` (id, business_id, codigo_servico, descricao, tempo_horas, categoria enum [mecanica/eletrica/lanternagem/pintura/diagnostico], aplicavel_a JSON [marcas/modelos], valor_hora_padrao)
- [ ] Importer Sindirepa/Cilia (CSV oficial — licenciamento sob demanda) OU manual
- [ ] Cálculo orçamento: `mao_obra = tempo_horas × valor_hora_categoria`
- [ ] Override por OS (mecânico justifica desvio)
- [ ] Multi-tenant scope

**Concorrência:** Tempario.com.br (R$ 79/m standalone — concorrente integração), Catálogo Tempário, Sindirepa-Cilia. Quase todos verticais oficina **integram** ou esperam que dono **digite**. **oimpresso ❌** hoje.

---

### US-AUTO-005 · OS com pipeline (recepção → diagnóstico → orçamento → aprovação → peças → execução → teste → entrega) — **P0**

> **Área:** Producao
> **Rota:** `GET /oficina-auto/os` + Kanban
> **Reusa:** [Modules/Repair](../../../Modules/Repair) `JobSheet` + Kanban PR #363 + `repair_statuses` configurável

**Como** atendente/PCP
**Quero** Kanban com 5+ colunas configuráveis mostrando OS em cada etapa, drag-drop pra mover
**Para** dono saber em 5s qual OS está atrasada + quem é responsável

**DoD:**
- [x] Kanban drag-drop (Inertia + dnd-kit) — entregue PR #363
- [x] Status pipeline configurável por business (`repair_statuses`) — herdado UltimatePOS
- [ ] Override labels: "JobSheet" → "OS"; "Device" → "Veículo"; "Box" novo conceito
- [ ] Notificação Centrifugo (CT 100) ao mudar coluna — vendedor sabe sem olhar
- [ ] Foto/anexo na etapa (mecânico sobe foto antes/depois) — `morphMany Media` já existe ✅
- [ ] SLA por etapa: alerta se passar X horas sem mover

**Concorrência:** Ultracar ✅, Oficina Integrada 🟡 (lista, não Kanban), Manager Full ✅, Calcgraf ❌. **oimpresso ✅ entregue PR #363** mas vocabulário e fluxo precisam consolidar.

---

### US-AUTO-006 · OS multi-mecânico (1 OS, N mecânicos com peças/horas distintas) — **P0**

> **Área:** Producao
> **blocked_by:** US-AUTO-005

**Como** dono
**Quero** que 1 OS de revisão completa possa ter mecânico_A (parte mecânica, 4h) + mecânico_B (parte elétrica, 1h) registrados separadamente
**Para** calcular comissão correta + saber produtividade individual + custo real por etapa

**DoD:**
- [ ] Tabela `oficina_auto_os_atribuicoes` (id, os_id, mecanico_user_id, etapa, horas_apontadas, valor_hora_aplicado, peças_atribuidas JSON)
- [ ] UI: "atribuir mecânico" multi-select + apontamento horas
- [ ] Custo real OS = sum(atribuicoes.horas × valor_hora) + peças
- [ ] Audit log mudanças (quem atribuiu, quem mudou)
- [ ] Pest: 1 OS com 2 mecânicos diferentes calcula comissão correta cada um

**Concorrência:** Ultracar ✅, Manager Full 🟡, Oficina Integrada 🟡 (1 mecânico só). **oimpresso ❌** — `service_staff` é singular hoje.

---

### US-AUTO-007 · Diagnóstico assistido por Jana IA (sintoma → hipóteses + tempário sugerido) — **P1**

> **Área:** IA
> **Reusa:** [Modules/Jana](../../../Modules/Jana) tools + ContextSnapshotService

**Como** mecânico iniciante / atendente
**Quero** descrever sintoma ("Civic 2015 fazendo barulho na frente quando faz curva") e receber 3-5 hipóteses ranqueadas + tempário estimado + peças prováveis
**Para** acelerar diagnóstico + reduzir dependência de mecânico sênior (dor #5 setor: 1 em 4 oficinas não acha mecânico — Doutor-IE)

**DoD:**
- [ ] Jana tool `auto.diagnostico.sugerir` com input (marca, modelo, ano, km, sintoma_texto)
- [ ] Output: hipóteses[] (descricao, probabilidade %, tempario_sugerido_horas, peças_prováveis[])
- [ ] PolicyEngine: `REQUIRE_HUMAN_REVIEW` (mecânico aprova antes de virar orçamento)
- [ ] Aprendizado: cada OS fechada vira fact `auto.diagnostico_realizado` em `MemoriaContrato` (ADR 0035)
- [ ] Disclaimer obrigatório UI: "sugestão IA — sempre confirmar com mecânico habilitado"
- [ ] LGPD: sem PII real do cliente em prompt (placa OK, CPF não)

**Concorrência:** **NENHUM concorrente vertical entrega.** Manager Full tem modelo 3D avarias (UI), não diagnóstico semântico. **Diferencial #1 oimpresso.**

---

### US-AUTO-008 · Catálogo peças OEM + similares (cód fabricante + equivalentes) — **P1**

> **Área:** Catalog
> **Rota:** `GET/POST /oficina-auto/pecas`

**Como** mecânico
**Quero** buscar peça pelo código OEM (ex: "VW 1H6 803 199 A" — cubo de roda Gol G6) e ver: original, similares (Bosch, Nakata, Fras-le), preço, fornecedor disponível
**Para** decidir entre original (caro) vs similar (margem) sem abrir 3 catálogos paralelos

**DoD:**
- [ ] Extender `products` UltimatePOS: campos `oem_code`, `aplicavel_a JSON`
- [ ] Tabela pivô `oficina_auto_peca_similares` (peca_origem_id, peca_similar_id, qualidade enum [original/oem/genuina/similar], compatibilidade_pct)
- [ ] Busca por OEM + ranking similares
- [ ] Catálogo seed parcial (Bosch + Nakata + Fras-le — open data ou parceria)
- [ ] Multi-tenant + escopo público (catálogo é shared) vs privado (preço e estoque do business)

**Concorrência:** Limersoft 🟡 (kits), Ultracar 🟡, restantes ❌. **Diferencial real** se entregue + parceria fornecedor.

---

### US-AUTO-009 · Aprovação OS via WhatsApp (link + PIN) — **P0**

> **Área:** Comercial
> **Rota:** pública `GET /a/{token}` (sem auth)
> **Reusa:** WhatsApp Cloud API (token Meta já no projeto)

**Como** atendimento
**Quero** enviar link "Olá Sr João, sua OS-1234 está orçada em R$ 850 — clique pra ver detalhes e aprovar com PIN" pelo WhatsApp do cliente
**Para** acelerar aprovação (cliente não precisa voltar à oficina) + evitar disse-me-disse

**DoD:**
- [ ] Endpoint público `/a/{token}` mostra orçamento (peças + mão-de-obra + total) em tela mobile-first
- [ ] PIN 4 dígitos enviado SMS/WhatsApp em paralelo (anti-fraude)
- [ ] Estado machine: pendente → aprovado / reprovado_com_motivo
- [ ] Webhook → atualiza OS + notifica oficina via Centrifugo
- [ ] Rate-limit IP/token + auditoria
- [ ] LGPD: aviso processamento + revogação consentimento

**Concorrência:** Ultracar ✅ (envio email/WhatsApp link), Soften ✅, Manager Full ✅. **Padrão de mercado** — não opcional pra MVP.

---

### US-AUTO-010 · NFC-e (peça) + NFS-e (serviço) automática a partir de boleto pago — **P0**

> **Área:** Fiscal
> **Reusa:** [Modules/NfeBrasil](../NfeBrasil/SPEC.md) US-NFE-002 (NFC-e ✅ pronta) + Modules/NFSe (a criar)
> **Reusa:** [Modules/RecurringBilling](../RecurringBilling/SPEC.md) US-RB-044 (boleto pago→NFe ✅ entregue)

**Como** financeiro
**Quero** boleto/Pix recebido → NFC-e (item peças, modelo 65) + NFS-e (item serviço, código LC 116/03 14.05) automáticas
**Para** eliminar 2 cliques humanos do fluxo + atacar reclamação pública Ultracar (cliente RA disse "1 ano e NFS-e prometida não foi implantada")

**DoD:**
- [x] Pipeline US-RB-044 (boleto pago→NFC-e) — **entregue ✅**
- [ ] Adapter OficinaAuto: split automático OS em itens peça (NFC-e modelo 65) vs serviço (NFS-e)
- [ ] CFOP 5102 (peça) + 5933 (serviço); CSOSN 102 (Simples sem permissão crédito)
- [ ] Modules/NFSe novo (homologação SEFAZ municipal — começar 1 município do piloto)
- [ ] Fallback: SEFAZ down → retry exponencial 24h
- [ ] PDF DANFE/NFSe enviado WhatsApp cliente

**Concorrência:** **Ultracar reclamação pública NFS-e travada** ([RA](https://www.reclameaqui.com.br/ultracar/nao-consigo-implantar-nota-fiscal-de-servico-no-sistema-ultracar_qc7uVBvCKVxeUrHH/)) — **wedge #1 ataque oimpresso**. NFC-e auto pronta no núcleo.

---

### US-AUTO-011 · Comissão por OS (vendedor + mecânico, % escalonado) — **P1**

> **Área:** Financeiro
> **Reusa:** [Modules/Financeiro](../Financeiro/) HR + UltimatePOS `essentials_commission_agents`

**Como** dono
**Quero** que ao fechar OS, comissão de cada mecânico (% sobre mão-de-obra apontada) e do atendente vendedor (% sobre venda peça) seja calculada
**Para** pagar correto na folha sem planilha paralela

**DoD:**
- [ ] Regra config por funcionário: tipo enum [linear, escalonada_meta, por_categoria_servico]
- [ ] Tabela `oficina_auto_comissao_regras` (user_id, tipo, pct_base, meta_valor, pct_bonus, escopo enum [mao_obra, peca, ambos])
- [ ] Trigger: pagamento OS confirmado (US-AUTO-010) → cria lançamento `comissao_pendente`
- [ ] Multi-mecânico: usa atribuições (US-AUTO-006) pra split correto
- [ ] Relatório mensal por mecânico/atendente
- [ ] Reapuração permitida com motivo + audit log

**Concorrência:** Ultracar ✅, Soften ✅, Mubisys ✅. **Padrão esperado.**

---

### US-AUTO-012 · App mobile mecânico (PWA — vê OS, marca status, sobe foto) — **P0**

> **Área:** UX
> **Reusa:** Inertia/React responsive + PWA manifest

**Como** mecânico no chão da oficina
**Quero** abrir minha lista de OS no celular, ver detalhes, marcar status, subir foto antes/depois sem ir até o computador
**Para** não atrasar fluxo + atender dor #6 setor (mecânico-no-chão precisa mobile)

**DoD:**
- [ ] PWA manifest + service worker offline-first
- [ ] Page `/oficina-auto/minhas-os` mobile-first (Tailwind 4 responsive)
- [ ] Upload foto chunked (Uppy ou nativo) com compressão client-side
- [ ] Push notification (Centrifugo) ao receber OS
- [ ] Funciona em 4G + offline graceful (queue sync)

**Concorrência:** Oficina Integrada ✅ (Android), Manager Full ✅ (web mobile), oficina.app ✅ (mobile-first), Ultracar 🟡 (só pós-vendas). **Crítico** — sem isso conversão sofre.

---

### US-AUTO-013 · Garantia serviço (registro + lembrete pós-X dias) — **P1**

> **Área:** Pos-venda
> **Reusa:** Job scheduled (Hostinger cron OK) + WhatsApp template

**Como** dono
**Quero** que ao fechar OS, garantia (3m peça / 6m serviço configurável) seja registrada e cliente receba lembrete antes de vencer
**Para** pos-venda diferenciada + reduzir disputa "tinha garantia ou não?"

**DoD:**
- [ ] Tabela `oficina_auto_garantias` (os_id, tipo, prazo_dias, vence_em, status, lembrete_enviado_em)
- [ ] Job daily compara `vence_em - 7dias` → dispara WhatsApp template
- [ ] Acionamento garantia: nova OS marcada `garantia_de_os_id` (não fatura cliente)
- [ ] Relatório custo garantia % faturamento (margem real)

**Concorrência:** Manager Full ✅, Soften 🟡, Ultracar 🟡. Diferenciador médio.

---

### US-AUTO-014 · Lembrete revisão (km/tempo) — **P1**

> **Área:** Pos-venda
> **blocked_by:** US-AUTO-001 (km_atual)

**Como** dono
**Quero** que cliente receba lembrete WhatsApp "seu Civic está há 5.000km da última revisão — agendar?" baseado em km estimado (média mensal × tempo decorrido)
**Para** recompra recorrente (LTV +30% segundo benchmark setor)

**DoD:**
- [ ] Tabela `oficina_auto_revisoes_planejadas` (veiculo_id, tipo enum [km, tempo, hibrido], proxima_em_km, proxima_em_data, template_msg)
- [ ] Job daily compara `veiculo.km_estimado_atual` (km_ultima_os + média_mensal × meses) com `proxima_em_km`
- [ ] WhatsApp template + agendamento integrado
- [ ] Opt-in LGPD obrigatório (Art. 7º)

**Concorrência:** Manager Full ✅ (lembretes WhatsApp por km/tempo), Soften ✅, Ultracar 🟡. Esperado mid-tier.

---

### US-AUTO-015 · Pré-cadastro fornecedores + cotação (RFQ) — **P2**

> **Área:** Compras
> **Reusa:** UltimatePOS `contacts.type=supplier`

**Como** comprador/dono
**Quero** disparar cotação pra 3 fornecedores (peça X, qty Y) e comparar respostas + escolher
**Para** garantir melhor preço peça + audit trail

**DoD:**
- [ ] Tabela `oficina_auto_cotacoes` + `oficina_auto_cotacao_respostas`
- [ ] Envio email/WhatsApp pra fornecedor com link público resposta
- [ ] UI compare 3+ respostas lado-a-lado
- [ ] Trigger compra direta (gera Purchase Transaction)

**Concorrência:** Ultracar 🟡, Mubisys 🟡, Limersoft 🟡. Diferenciador mid-tier.

---

### US-AUTO-016 · Apontamento horas mecânico (clock-in/out por OS) — **P2**

> **Área:** Producao
> **blocked_by:** US-AUTO-006

**Como** mecânico
**Quero** marcar "começo agora" / "pausei" / "terminei" no celular pra cada OS
**Para** apontamento real bate com tempário + dono mede produtividade real

**DoD:**
- [ ] Tabela `oficina_auto_apontamentos` (mecanico_id, os_id, inicio, fim, motivo_pausa)
- [ ] UI mobile clock-in 1-tap
- [ ] Cálculo `horas_realizadas = sum(fim - inicio)` × `valor_hora_categoria`
- [ ] Dashboard produtividade: % tempo apontado vs jornada, OS/dia

**Concorrência:** Ultracar Master ✅, Manager Full ✅. Mid-tier.

---

### US-AUTO-017 · Painel cliente público (status OS online) — **P1**

> **Área:** Comercial
> **Rota:** pública `GET /repair-status?token=X`
> **Reusa:** [Modules/Repair](../../../Modules/Repair) `CustomerRepairStatusController` ✅ entregue

**Como** cliente
**Quero** entrar no link enviado pelo WhatsApp e ver "OS-1234 — etapa: Aguardando peça" sem ligar pra oficina
**Para** reduzir telefonemas + transparência

**DoD:**
- [x] Rota pública `/repair-status` Modules/Repair ✅
- [ ] Override labels OficinaAuto (placa, mecânico, etapa)
- [ ] Foto antes/depois disponível ao cliente quando mecânico libera

**Concorrência:** Oficina Integrada ✅, Manager Full ✅. Padrão.

---

### US-AUTO-018 · CT-e/MDF-e quando entrega de veículo — **P3**

> **Área:** Fiscal
> **Reusa:** [Modules/NfeBrasil](../NfeBrasil/SPEC.md) (CT-e/MDF-e a adicionar)

**Como** dono frota / oficina especializada caminhão
**Quero** emitir CT-e (transporte) e MDF-e (manifesto) quando reboco de veículo é necessário
**Para** estar legal — Ajustes SINIEF abr/2026 tornaram obrigatório alguns casos

**Status:** **proposta P3** — só ativa se piloto for oficina com frota/reboco. Maioria oficinas SMB não precisa.

---

## 4. Concorrentes verticais

### 4.1 Ultracar (BH/MG, 31 anos)
- **Pricing:** R$ 189-494/m (3 tiers públicos)
- **Forte:** 430+ funcionalidades, base instalada, blog SEO
- **Calcanhar documentado:** [NFS-e travada 1 ano cliente RA](https://www.reclameaqui.com.br/ultracar/nao-consigo-implantar-nota-fiscal-de-servico-no-sistema-ultracar_qc7uVBvCKVxeUrHH/), [suporte despreparado](https://www.reclameaqui.com.br/ultracar/sistema-cheio-de-falhas-e-sem-suporte_OauYLk_oxCsFoDzC/)
- **Stack:** PHP tradicional, sem stack pública

### 4.2 Oficina Integrada / Mundomidia (Viçosa/MG, 23 anos)
- **Pricing:** R$ 99-339/m (4 tiers — anual −15%)
- **Forte:** "1º 100% online", app Android, NFC-e+NFSe ilim.
- **Calcanhar:** [boleto pago e não liberam acesso](https://www.reclameaqui.com.br/mundo-midia/sistema-oficina-integrada-o-boleto-pago-e-nao-liberam-acesso_VUIgPcnVI7SFxe3O/), UI desktop-tradicional, zero IA

### 4.3 Onmotor
- **Pricing:** R$ 0-479/m (V1 free → V12 NFe)
- **Forte:** múltiplos tiers granular, 5d trial
- **Calcanhar:** stack desconhecida, sem mobile destacado, free só 50 OS/m

### 4.4 Oficina Inteligente (CNPJ <2 anos)
- **Pricing:** R$ 399-599/m premium
- **Forte:** marketing "120+ recursos", multi-segmento (oficina/auto-center/borracharia/troca-óleo/caminhões)
- **Calcanhar:** sem track record, RA não verificada

### 4.5 IS2 Automotive WD
- **Pricing:** R$ 112 PC + R$ 172 NFe (one-time, vitalícia)
- **Forte:** SEO hyper-local (cidade-por-cidade), pagamento único
- **Calcanhar:** desktop puro, sem suporte/updates inclusos, base envelhece com mudança SEFAZ

### 4.6 Manager Full
- **Pricing:** R$ 155-300/m
- **Forte:** **modelo 3D interativo de avarias** (único no mapeamento), NFe+NFSe, busca XML SEFAZ, lembretes WhatsApp
- **Calcanhar:** stack desconhecida, sem IA conversacional (3D é UI)

### 4.7 oficina.app (App Garage)
- **Pricing:** Free + Premium fechado
- **Forte:** mobile-first, laudo técnico fotos
- **Calcanhar:** multi-segmento (não pure-play oficina), pricing opaco

### 4.8 NeXT Software, Soften, GestãoClick, Limersoft, MinhaOficina, WSoft
- Range pricing R$ 29,90-379/m, mix desktop+cloud
- **Calcanhar comum:** zero IA, multi-segmento sem profundidade vertical real, stack legacy ou desconhecida

> **NOTA — calibração brief:** dos 6 concorrentes do brief (Mecânico/Tecnosistemas, ManagerOS, Auto Manager, Lokoz, OficinaMaster, Workshop), **nenhum apareceu como líder real do mercado BR** em busca direta 2026-05-09. Mapeamento usa concorrentes que efetivamente disputam clientes (research [02-concorrentes-erp-auto-br.md](../../research/2026-05-prospeccao-auto/02-concorrentes-erp-auto-br.md)).

## 5. Diferenciais oimpresso

| Diferencial | oimpresso | Ultracar | Of.Integrada | Onmotor | Of.Inteligente | Manager Full |
|---|---|---|---|---|---|---|
| **Jana IA conversacional + memória persistente** ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)) | ✅ planejado | ❌ | ❌ | ❌ | ❌ | ❌ (3D não é IA) |
| **NFC-e/NFS-e auto a partir de boleto pago** (US-RB-044 ✅) | ✅ | ❌ travada | ❌ | ❌ | 🟡 | 🟡 |
| **Multi-tenant Tier 0** (ADR 0093) | ✅ | 🟡 | 🟡 | ❌ | ❌ | ❌ |
| **Stack moderna** (Laravel 13.6 + Inertia v3 + React 19) | ✅ | ❌ PHP trad | ❌ desktop em browser | ❌ | ❌ | ❌ |
| **MCP server governado** (ADR 0053) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Constituição v2 ADRs públicas** (ADR 0094) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Diagnóstico assistido por IA** (US-AUTO-007) | ✅ planejado | ❌ | ❌ | ❌ | ❌ | ❌ |
| **API CRLV/DETRAN nativa** (US-AUTO-002) | ✅ planejado | ❌ via 3rd party | ❌ | ❌ | ❌ | ❌ |
| **PWA mecânico mobile-first** (US-AUTO-012) | ✅ planejado | 🟡 só pós-vendas | ✅ Android | ❌ | ❌ | ✅ web mobile |

**Wedge primário (3 frases):**
> *"O ERP de oficina auto que dispara NFC-e + NFS-e automaticamente quando o boleto cai. Que responde 'qual cliente está atrasado' direto no chat, com memória persistente. Que consulta CRLV nativo pela placa — enquanto Ultracar deixa cliente 1 ano sem NFS-e e Oficina Integrada bloqueia acesso depois do boleto pago."*

## 6. Arquitetura técnica

### 6.1 Estrutura de diretórios (a criar SE/QUANDO ativado)

```
Modules/OficinaAuto/         ← a criar (status: feature-wish até gatilho)
├── Config/
│   ├── config.php
│   └── permissions.php       ← Spatie: auto.veiculo.*, auto.os.*, auto.tempario.*, auto.peca.*, auto.comissao.*
├── Database/
│   ├── Migrations/
│   │   ├── create_oficina_auto_veiculos_table.php
│   │   ├── create_oficina_auto_temparios_table.php
│   │   ├── create_oficina_auto_os_atribuicoes_table.php
│   │   ├── create_oficina_auto_peca_similares_table.php
│   │   ├── create_oficina_auto_garantias_table.php
│   │   ├── create_oficina_auto_revisoes_planejadas_table.php
│   │   ├── create_oficina_auto_cotacoes_table.php
│   │   ├── create_oficina_auto_apontamentos_table.php
│   │   └── create_oficina_auto_comissao_regras_table.php
│   └── Seeders/
│       ├── TemparioSindirepaSeeder.php (parcial — sob licença)
│       └── PecasOemBaseSeeder.php (Bosch/Nakata/Fras-le open data)
├── Entities/                ← Eloquent Models (BusinessIdScope global)
│   ├── Veiculo.php
│   ├── Tempario.php
│   ├── OsAtribuicao.php
│   ├── PecaSimilar.php
│   ├── Garantia.php
│   ├── RevisaoPlanejada.php
│   ├── Cotacao.php
│   ├── Apontamento.php
│   └── ComissaoRegra.php
├── Http/
│   ├── Controllers/
│   │   ├── DataController.php       ← UltimatePOS hooks
│   │   ├── InstallController.php    ← 3 rotas (status, install, uninstall) — RUNBOOK-criar-modulo
│   │   ├── VeiculoController.php
│   │   ├── TemparioController.php
│   │   ├── OsAtribuicaoController.php (extends Repair JobSheet)
│   │   ├── DiagnosticoController.php (Jana wrapper)
│   │   ├── PecaController.php
│   │   ├── ComissaoController.php
│   │   ├── GarantiaController.php
│   │   ├── RevisaoController.php
│   │   ├── CotacaoController.php
│   │   ├── ApontamentoController.php
│   │   ├── AprovacaoPublicaController.php  ← rota pública /a/{token}
│   │   └── PainelClienteController.php (extends Repair CustomerRepairStatusController)
│   └── Requests/
├── Jobs/
│   ├── ConsultarCrlvJob.php
│   ├── EmitirNfsServicoJob.php (após Modules/NFSe)
│   ├── LembreteRevisaoJob.php (cron daily)
│   └── LembreteGarantiaJob.php (cron daily)
├── Listeners/
│   ├── BoletoPagoEmiteNotasFiscais.php (split NFC-e peça + NFS-e serviço)
│   └── OsConcluidaCalculaComissao.php
├── Services/
│   ├── DiagnosticoService.php (wrapper Jana tool)
│   ├── TemparioCalculator.php
│   ├── ComissaoCalculator.php
│   └── ConsultaPlacaService.php (adapter SerPro/Infosimples)
├── Resources/
│   ├── views/  (mínimo Blade — 99% Inertia)
│   └── lang/
├── Routes/
│   ├── web.php
│   └── api.php
├── Tests/
│   ├── Feature/
│   └── Unit/
├── module.json
└── composer.json
```

Frontend Inertia em `resources/js/Pages/OficinaAuto/` seguindo Cockpit Pattern V2 (ADR 0110) com `.charter.md` ao lado de cada Page (S4+).

### 6.2 Reusa Modules/Repair (shared infra — ADR 0121 §P8)

- **JobSheet** → renomeado/aliased "OS" via override label
- **repair_statuses** configurável por business → pipeline OficinaAuto
- **Kanban drag-drop** PR #363 → Producao Oficina já em produção
- **CustomerRepairStatusController** → painel cliente público (US-AUTO-017)
- **Media morphMany** → fotos antes/depois (US-AUTO-005, US-AUTO-012)
- **ContextSnapshotService hook** `repair_job_sheet` → Jana já contextualiza OS

### 6.3 Reusa outros Modules

- **NfeBrasil** US-NFE-002 (NFC-e ✅) + futuro Modules/NFSe (US-AUTO-010)
- **RecurringBilling** US-RB-044 (boleto pago→NFe ✅ entregue)
- **Financeiro** AR/AP/extrato/comissão base
- **Jana** ContextSnapshotService + tools (US-AUTO-007, US-AUTO-014 lembretes inteligentes)

### 6.4 Schema essencial (resumo)

```sql
-- oficina_auto_veiculos
id, business_id (FK + scope), contact_id (FK), placa unique-by-biz, chassi 17,
marca, modelo, ano_fabricacao, ano_modelo, cor, combustivel, km_atual,
crlv_consultado_em, crlv_dados_json, observacao, created_at, updated_at

-- oficina_auto_temparios
id, business_id, codigo_servico, descricao, tempo_horas, categoria,
aplicavel_a_json, valor_hora_padrao, ativo

-- oficina_auto_os_atribuicoes (1 OS → N mecânicos)
id, os_id (FK repair_job_sheets), mecanico_user_id, etapa,
horas_apontadas, valor_hora_aplicado, peças_atribuidas_json

-- oficina_auto_peca_similares (catálogo OEM)
id, peca_origem_id (FK products), peca_similar_id (FK products),
qualidade enum [original, oem, genuina, similar], compatibilidade_pct

-- oficina_auto_garantias
id, business_id, os_id, tipo (peca|servico), prazo_dias, vence_em,
status, lembrete_enviado_em

-- oficina_auto_revisoes_planejadas
id, business_id, veiculo_id, tipo (km|tempo|hibrido),
proxima_em_km, proxima_em_data, template_msg, opt_in_lgpd_at

-- oficina_auto_apontamentos
id, business_id, mecanico_user_id, os_id, inicio, fim, motivo_pausa

-- oficina_auto_comissao_regras
id, business_id, user_id, tipo (linear|escalonada_meta|por_categoria),
escopo (mao_obra|peca|ambos), pct_base, meta_valor, pct_bonus
```

Todos com `business_id` indexado + FK + global scope (Tier 0 IRREVOGÁVEL — ADR 0093).

## 7. Roadmap CONDICIONAL (só ativa se 1 piloto pagar)

> ⚠️ **NÃO IMPLEMENTAR.** Roadmap abaixo é antecipatório — só vira backlog ativo quando gatilho §9 for satisfeito. Sem cliente piloto pagante, **viola ADR 0105** (cliente como sinal qualificado).

### Fase 0 — Scaffold (1 semana IA-pair)
Module skeleton + DataController + InstallController + 3 migrations core (veiculos, temparios, os_atribuicoes) + Charter inicial. **0 features visíveis ao cliente.**

### Fase 1 — MVP-6 capacidades core (3 semanas IA-pair, fator 10x ADR 0106)
- US-AUTO-001 (veículo persistente)
- US-AUTO-002 (CRLV/placa)
- US-AUTO-004 (tempário)
- US-AUTO-005 (OS Kanban — já entregue PR #363, só labels)
- US-AUTO-006 (multi-mecânico)
- US-AUTO-009 (aprovação WhatsApp)
- US-AUTO-010 (NFC-e auto — adapter sobre US-RB-044)
- US-AUTO-012 (PWA mecânico)
- US-AUTO-017 (painel cliente — já entregue, só labels)

**Esforço estimado IA-pair (ADR 0106):** ~76h codáveis × 2x margem = ~10 dias úteis Felipe (vs ~50 dias humano sem IA-pair). Conferir gap-repair-vs-oficina-auto.md.

### Fase 2 — Diferenciais (4 semanas + wallclock SEFAZ)
- US-AUTO-007 (Jana diagnóstico — diferencial #1)
- US-AUTO-008 (catálogo OEM)
- US-AUTO-011 (comissão por OS)
- US-AUTO-013 (garantia)
- US-AUTO-014 (lembrete revisão)
- Modules/NFSe homologação 1 município (humano-limitado: ~30 dias wallclock SEFAZ)

### Fase 3 — Escala (6+ meses)
- US-AUTO-015 (cotação RFQ), US-AUTO-016 (apontamento clock), US-AUTO-018 (CT-e/MDF-e se piloto frota)
- 2ª-5ª piloto via Migration Factory (ADR 0119)
- Endorsement Sindirepa/CINAU (gap competitivo vs Ultracar 31 anos)

**Total MVP→produção piloto: ~8 semanas IA-pair + 30 dias wallclock SEFAZ NFS-e = ~3 meses corridos.** Sem IA-pair seria ~10 semanas Felipe + 30 dias = ~5 meses.

## 8. Pricing tier sugerido (calibrado mercado vertical auto BR)

> Pricing baseado em [research/2026-05-prospeccao-auto/03-pricing-erps-auto-br.md](../../research/2026-05-prospeccao-auto/03-pricing-erps-auto-br.md). Range mediana mercado: **R$ 70-599/m** (Onmotor V2 R$ 47,60 → Oficina Inteligente Fantástico R$ 599).

| Tier | Preço/m | Inclui | Posição vs mercado |
|---|---|---|---|
| **Auto Starter** | **R$ 199/m** | 1 oficina, 1-3 mecânicos, 100 OS/m, NFC-e+NFS-e ilim, WhatsApp básico, app mecânico read-only, **sem CRLV nativo** (add-on R$ 49/m), Jana IA básica (Q&A faturamento) | Acima entry tiers (Onmotor V2 R$ 47, MinhaOficina Bronze R$ 70, NeXT Pro R$ 69) — **diferenciar por Jana + stack** ou descer pra R$ 149 |
| **Auto Pro** | **R$ 399/m** | 1 oficina, 4-10 mecânicos, 500 OS/m, 5 users, app mecânico full, NFe completo, **CRLV incluso 200 consultas/m**, Jana IA completa (diagnóstico assistido US-AUTO-007), tempário pré-cadastrado, multi-mecânico | Mediana mercado mid (Of.Integrada R$ 339, Of.Inteligente R$ 399, Ultracar Plus R$ 324, Onmotor V10 R$ 397) — **competitivo se Jana+CRLV viram diferencial percebido** |
| **Auto Premium** | **R$ 799/m** | Multi-loja (até 5), 11-30 mecânicos, OS ilim, users ilim, CT-e/MDF-e, **CRLV ilim**, Jana IA full + memória dedicada, SLA telefônico, customer success dedicado, treino presencial | Acima top tier mercado (Of.Inteligente Fantástico R$ 599, Ultracar Master R$ 494) — **só funciona se entregar 2x valor** vs alternativas. Risco caro |
| **Setup** | **R$ 0 default** | — | Norma do nicho (10/12 ERPs cobram zero). Cobrar **R$ 999 opcional** se migração documentada de Ultracar/Of.Integrada/Delphi |
| **Trial** | **14 dias** sem cartão | — | Mercado padrão 7d, 14d competitivo sem ser exagero |
| **Anual** | **12 paga 10** | — | Padrão mercado |

**Calibração brief:** brief sugeriu R$ 199/399/799. **Validado contra research:** R$ 199 starter está **acima** entry tiers (risco conversão); R$ 399 pro está **na mediana** (OK); R$ 799 premium está **acima** top tier (precisa diferencial percebido grande).

**Recomendação:** **manter R$ 199/399/799 como ancorado no brief**, com nota: validar com 1ª piloto se R$ 199 está convertendo. Se conversão <10%, pivotar pra R$ 149 starter.

## 9. Pré-requisitos pra ATIVAR (mudar status pra `em_construcao`)

> **Esta seção é a fronteira ADR 0105.** Sem TODOS os pré-requisitos abaixo, módulo permanece `feature-wish`. Não scaffoldear, não criar tasks ativas, não codar.

### 9.1 Sinal qualificado de mercado (gatilho cliente — ADR 0105)

**Pelo menos 1 dos 3 cenários:**

1. **1 oficina pagante upfront** (Cenário A — preferido):
   - Assina contrato Auto Pro R$ 399/m × 3 meses upfront (R$ 1.197 antecipado)
   - Compromisso reportar bugs/features semanal por 6 meses
   - Geografia SP/MG (32% concentração mercado, suporte presencial possível)
   - Já usa Ultracar/Oficina Integrada/Onmotor com dor concreta de NFS-e ou aprovação WhatsApp

2. **Concorrente direto sai do mercado** (Cenário B):
   - Mubisys/Ultracar/Oficina Integrada anuncia descontinuação OU é adquirido com migração forçada
   - Wagner identifica 5+ oficinas órfãs procurando substituto

3. **Cross-sell vertical orgânico** (Cenário C):
   - Cliente Modules/ComunicacaoVisual ou Vestuario indica oficina parceira/familiar
   - 2+ leads inbound qualificados (call de 30min cada) com decisor presente
   - 1 dos 2 fecha (Cenário A reduzido)

### 9.2 6 features mínimas validadas (paridade competitiva)

Antes de cobrar 1º cliente, **TODAS** essas 6 capacidades core funcionam end-to-end em homologação:

1. **US-AUTO-001** — cadastro veículo persistente (placa+chassi+km)
2. **US-AUTO-004** — tabela tempária funcional (mínimo 100 serviços seed)
3. **US-AUTO-005/006** — OS Kanban + multi-mecânico (já 80% via Repair PR #363)
4. **US-AUTO-007** — diagnóstico Jana IA (mínimo 3 marcas BR — VW/Fiat/Chevrolet)
5. **US-AUTO-009** — aprovação WhatsApp link+PIN
6. **US-AUTO-010** — NFC-e auto a partir de boleto pago (já entregue núcleo, só adapter)

**Não inclui** US-AUTO-002 (CRLV — pode ser fase 2), US-AUTO-008 (OEM — fase 2), US-AUTO-012 (PWA mobile — fase 2), US-AUTO-013/014 (garantia/lembrete — fase 3).

### 9.3 Capacidade time

- **WIP atual:** 5 pessoas (Wagner/Maiara/Felipe/Luiz/Eliana) com Modules/Vestuario live, Modules/ComunicacaoVisual em planejamento (1ª piloto Q3-2026), Jana memória em sprint, MWART Financeiro em batch. **2 verticais paralelos = capacidade limitada.**
- **Recomendação:** ativar OficinaAuto **só após** Modules/ComunicacaoVisual validar 2ª piloto (M6 — dez/2026 estimado). Antes, oportunidade-custo é negativa (gap-repair-vs-oficina-auto.md §recomendação).

### 9.4 ADR de ativação

Quando os pré-requisitos forem satisfeitos, **abrir ADR canon** "OficinaAuto-ativacao-vertical" com:
- evidência sinal qualificado (contrato assinado, lead qualificado, cliente cross-sell)
- evidência 6 features mínimas verde (Pest + smoke real)
- aprovação Wagner [W] + revisão Felipe [F]
- mudança SPEC `status: feature-wish` → `status: em_construcao`
- criação batch tasks no MCP via `tasks-create` (não markdown — ADR 0070)

## 10. Métricas de sucesso (12m após ativação, NÃO antes)

| Métrica | Baseline (M0 ativação) | M6 | M12 | Crítica |
|---|---|---|---|---|
| Clientes pagantes Modules/OficinaAuto | 1 (piloto) | 5 | **15-30** | <5 = re-avaliar tese |
| ARR módulo (R$/ano) | R$ 4,8k | R$ 24k | **R$ 60-120k** | <R$ 40k = pivotar |
| US entregues (de 18 totais) | 6 (mínimo) | 12 (P0+P1) | **15** | <12 = stack mal calibrado |
| Cases públicos clicáveis | 0 | 1 | **2** | (transparência radical) |
| Bug crítico produção | n/a | <1/mês | <1/trimestre | (Pest gate ADR 0094) |
| Churn módulo | n/a | <5%/m | <8%/ano | (review trigger ADR 0121) |
| NFS-e auto funcionando ≥ 1 município | sim (piloto) | 3 municípios | 10 municípios | (wedge #1 vs Ultracar) |

**Meta convergente com [ADR 0022](../../decisions/0022-meta-5mi-ano-financeira.md):** Modules/OficinaAuto contribui R$ 60-120k ARR de R$ 5M total (1-2,5% no M12 pós-ativação). Multi-vertical é a tese — oficina é diversificação, não substituição da gráfica.

## 11. Anti-padrões — o que NÃO fazer

1. ❌ **Construir SEM cliente piloto pagante** — viola ADR 0105 explicitamente. Status `feature-wish` é proteção contra ansiedade.
2. ❌ **Esperar Martinho Caçambas virar piloto** — vestuário/caçamba ≠ oficina mecânica. Sinal indireto não satisfaz gatilho.
3. ❌ **Copiar feature-set Ultracar e cobrar 30% menos** — mesmo erro Caminho A do comparativo Capterra Modules/ComunicacaoVisual. Sem diferencial, perde por base instalada (31 anos).
4. ❌ **Hard-code vocabulário automotivo no núcleo UltimatePOS** — quebra ADR 0121 §P1. Tudo "veículo/placa/CRLV/tempário" vai em `Modules/OficinaAuto/`.
5. ❌ **Reutilizar Modules/Repair sem override de labels** — débito técnico atual (PR #363 vazou "placa/box/mecânico" no frontend sem entidade Veículo). Quando ativar, formalizar override.
6. ❌ **Esquecer `business_id` global scope em qualquer Model nova** — Tier 0 IRREVOGÁVEL (ADR 0093). Skill `multi-tenant-patterns` enforce.
7. ❌ **Daemon SEFAZ NFS-e no Hostinger** — ADR 0062. Homologação SEFAZ + retry exponencial → CT 100. App web Hostinger.
8. ❌ **PII real (CPF/CNPJ cliente, placa real) em PR/commit/log** — skill `commit-discipline` Tier A. `[REDACTED]` ou `PiiRedactor`.
9. ❌ **Cobrar setup R$ 999+ default** — anomalia no nicho (10/12 concorrentes setup zero). Setup só com migração documentada explícita.
10. ❌ **Embutir API DETRAN ilimitada no tier Starter** — sangra margem (R$ 0,15/consulta × volume). Cobrar add-on R$ 49/m / 500 consultas.
11. ❌ **Implementar US-AUTO-018 (CT-e/MDF-e) sem piloto frota** — esperado SINIEF mas piloto SMB típico não usa. Backlog P3.
12. ❌ **Esquecer disclaimer Jana diagnóstico** — "sugestão IA, sempre confirmar com mecânico habilitado" é obrigatório (LGPD + responsabilidade civil — sem disclaimer, oimpresso vira corresponsável por dano de diagnóstico errado).
13. ❌ **Smoke test com `business_id=1`** (Wagner WR2, prod) — ADR 0101 manda biz piloto.
14. ❌ **Migrar 5 oficinas em paralelo no 1º trimestre pós-ativação** — capacidade humana 5 pessoas. Migration Factory rolling: 1 piloto/mês até M3, 2/mês após M6.
15. ❌ **Ativar OficinaAuto antes de Modules/ComunicacaoVisual ter 2ª piloto** — viola WIP (ADR 0094 §5 SoC brutal). 1 vertical comprovado > 2 mornos.

## 12. Decisões pendentes (resolver SE/QUANDO ativar)

- [ ] Adapter CRLV: SerPro oficial (homologação ~30d wallclock) vs Infosimples (R$ 0,15/consulta sem homologação) — depende volume piloto
- [ ] Tempário: licenciar Sindirepa/Cilia (sob demanda — pricing CONFIDENTIAL pelo Sindirepa-RJ) vs construir base própria com 100 serviços comuns + crowdsource
- [ ] Modelo 3D avarias (Manager Full diferencial): replicar via three.js (~80h) ou parceria (iframe whitelabel) ou ignorar (não é IA)
- [ ] Catálogo OEM Bosch/Nakata/Fras-le: parceria oficial (cobrável) vs scraping com fair-use vs zero (US-AUTO-008 vira P3)
- [ ] NFS-e: Joinville/SC primeiro (perto Wagner) ou cidade do piloto (segue cliente) — provavelmente segue piloto
- [ ] PWA offline-first vs online-only: depende qualidade 4G na oficina piloto

## 13. Referências

- ADR 0121 — Modular especializado por vertical (mãe deste módulo)
- ADR 0094 — Constituição v2 (princípios duros)
- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0105 — Cliente como sinal qualificado (gatilho de ativação)
- ADR 0106 — Recalibração velocidade fator 10x IA-pair
- ADR 0089 — Capterra-driven evolution
- ADR 0119 — Migration Factory
- [Proposal gap-repair-vs-oficina-auto.md](../../decisions/proposals/gap-repair-vs-oficina-auto.md) — audit F32 anterior, 55-60% reuso Repair, ~10 dias IA-pair MVP
- [Research mercado oficinas auto BR 2026-05-09](../../research/2026-05-prospeccao-auto/01-mercado-oficinas-auto-br.md)
- [Research concorrentes ERP auto BR](../../research/2026-05-prospeccao-auto/02-concorrentes-erp-auto-br.md)
- [Research pricing ERPs auto BR](../../research/2026-05-prospeccao-auto/03-pricing-erps-auto-br.md)
- [SPEC Modules/Vestuario](../Vestuario/SPEC.md) — modelo SPEC live em produção
- [SPEC Modules/ComunicacaoVisual](../ComunicacaoVisual/SPEC.md) — modelo SPEC em construção planejada
- [SPEC Modules/NfeBrasil](../NfeBrasil/SPEC.md) — reuso US-NFE-002 NFC-e
- [SPEC Modules/RecurringBilling](../RecurringBilling/SPEC.md) — reuso US-RB-044 boleto-pago→NFe
- [Modules/Repair](../../../Modules/Repair) — shared infra (ADR 0121 §P8), ~55-60% reuso
- [Modules/Jana](../../../Modules/Jana) — reuso IA US-AUTO-007 diagnóstico
- [RUNBOOK criar módulo](../Infra/RUNBOOK-criar-modulo.md)
- Sindirepa-SP TAM R$ 128bi/2022 — https://rafamarrafon.com.br/oicinas-mecanicas-faturam-128-bilhoes-em-2022/
- CINAU 121k oficinas BR — https://oficinabrasil.com.br/noticia/mercado-cinau/dimensoes-do-mercado-de-reposicao-quem-somos-onde-estamos-e-quanto-representamos

### US-OFICINA-035 · DVI (Vistoria Digital · Digital Vehicle Inspection) schema + API — **P1**

> owner: — · priority: p1 · estimate: 4h (IA-pair fator 10x ADR 0106) · status: in-progress (backend done) · type: story · origin: Wave 3 OficinaAuto · 2026-05-26
> blocked_by: —
> blocks: Wave 3b (UI integration drawer ServiceOrderRichSheet — depende PR #1624) · WhatsApp "Enviar p/ cliente" (depende US-OFICINA-014 PR #1627)

Wedge competitivo vs RepairShopr/mHelpDesk catalogado em [CAPTERRA-FICHA Repair gap #3](../Repair/CAPTERRA-FICHA.md). Mecânico registra itens vistoriados na OS com semáforo verde/amarelo/vermelho (ok/atencao/critico) + recomendação + valor + foto opcional. Card UI proposto (screenshot Wagner 2026-05-26): "VISTORIA DIGITAL · DVI" com badges contadores ("8 ok · 2 atenção · 1 crítico"), lista de 5 items com semáforo + valor, e bloco "TOTAL RECOMENDADO · CLIENTE" agregando atencao+critico.

**DoD (entregue Wave 3):**
- [x] Migration `oa_inspection_items` (10 categorias enum + 3 severity enum + metadata json + photo_url + sort_order + multi-tenant business_id Tier 0 ADR 0093)
- [x] Model `OaInspectionItem` com global scope business_id + LogsActivity D7.b + SoftDeletes + scopes (oks/atencoes/criticas/recomendaveis) + constantes CATEGORIAS + SEVERITIES_VALIDAS
- [x] Service `DviInspectionService` com 4 métodos (addItem cross-tenant defense, breakdownPorSeverity, totalRecomendado, listarOrdenado)
- [x] Controller `DviInspectionController` HTTP JSON (store 201 / update / destroy) com Policy `update(ServiceOrder)` + cross-OS guard (item.service_order_id === order.id)
- [x] FormRequests `StoreDviRequest` + `UpdateDviRequest` (Rule::in CATEGORIAS + SEVERITIES_VALIDAS, descricao max:150, recomendacao max:255, valor_recomendado numeric min:0)
- [x] 3 rotas Routes/web.php (POST/PUT/DELETE em `/ordens-servico/{order}/dvi[/{item}]`) com throttle:60,1
- [x] ServiceOrder.dviInspectionItems() HasMany + getDviBreakdownAttribute() accessor
- [x] Pest 10 specs (CRUD + global scope cross-tenant + Service validações + cross-OS HTTP 404)

**Pendente Wave 3b:**
- [ ] UI Pages/OficinaAuto integração no drawer ServiceOrderRichSheet (depende PR #1624 mergear)
- [ ] Botão "Enviar p/ cliente" via WhatsApp (depende US-OFICINA-014 / PR #1627 — link público + PIN)
- [ ] Upload de foto S3 (V0 só aceita photo_url string — UI vai precisar de file picker)

### US-OFICINA-026 · Outreach Martinho Caçambas + cutover discovery — fechar contrato pioneer

> owner: wagner · priority: p0 · estimate: 8h · status: todo · type: story
> blocked_by: —

Fechar 1º cliente pagante Modules/OficinaAuto (goal #1 CYCLE-06 — sinal qualificado ADR 0105).

**DoD:**
- [ ] Outreach direto Martinho (call/presencial — não cold email)
- [ ] Pacote pioneer apresentado (pricing + setup + grandfathering)
- [ ] Contrato assinado OU explicitamente recusado (decidir Plano B)
- [ ] Se aceito: discovery cutover Delphi WR Comercial → oimpresso (skill `officeimpresso-source-analysis` + `officeimpresso-financial-snapshot`)
- [ ] Se aceito: cycle dedicado ativar OficinaAuto (Sprint 0 com US-OFICINA-001..005 P0)
- **Estimate:** 8h humano-limitado (call + análise + decisão) — relógio mundo real, não fator 10x

---

**Última atualização:** 2026-05-26 — US-OFICINA-035 DVI Vistoria Digital backend (schema + Model + Service + HTTP API + Pest) — wedge CAPTERRA Repair gap #3. UI Wave 3b. 2026-05-15 — US-OFICINA-026 adicionada (goal #1 CYCLE-06 Martinho prod). 2026-05-10 — SPEC criada **antecipatória** sem cliente piloto. Status `feature-wish` lifecycle `aguarda-sinal-qualificado`. Não codar até gatilho §9 satisfeito. Revisar trimestralmente — se 12 meses sem sinal, considerar arquivar como `historical` (ADR 0095 lifecycle).
