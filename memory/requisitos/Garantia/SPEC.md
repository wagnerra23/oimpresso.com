---
slug: garantia-spec
module: Garantia
type: spec
status: rascunho
lifecycle: draft
owner: [W]
version: "1.0"
last_updated: "2026-06-13"
created_at: 2026-05-12
updated_at: 2026-05-12
authority: proposal
related_adrs: [0143-fsm-pipeline-live-prod-marco-2026-05-12, 0129-state-machine-canonica-fsm-rbac, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0104-processo-mwart-canonico-unico-caminho, 0105-cliente-como-sinal-guiar-sem-mandar, 0106-recalibracao-velocidade-fator-10x-ia-pair, 0121-oimpresso-modular-especializado-por-vertical]
related_modules: [OficinaAuto, Autopecas, Repair, ComunicacaoVisual, Vestuario, NfeBrasil, RecurringBilling, Financeiro]
us_prefix: US-WARR
pii: false
---

# SPEC — Garantia (planejado — não existe) (workflow cross-vertical)

> **Status discovery 2026-05-12.** Esta SPEC é PROPOSTA. Wagner aprova → spawn implementador depois.
> NÃO implementar código produção a partir deste doc — apenas leitura + revisão.

## §1 Visão

**Garantia (planejado — não existe)** é o módulo cross-vertical canônico do oimpresso que padroniza o workflow de garantia / warranty claim / RMA pra qualquer vertical (`OficinaAuto`, `Autopecas`, `Repair`, `ComunicacaoVisual`, futuras), integrando com:

- **FSM canon LIVE** ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) — pipeline `garantia_*` em cima do `ExecuteStageActionService`
- **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — `business_id` global scope em todas as 4 tabelas centrais + roles Spatie per-business
- **NFe canon** (`Modules/NfeBrasil`) — emissão de NFe substituição (CFOP 5.949 ou 1.949) quando peça volta + sai
- **Asaas/Inter** (Modo MoR) — sem ressarcimento financeiro ao cliente (garantia ≠ estorno); custo é do lojista. Ressarcimento fornecedor (B2B) flui em paralelo

### Por que cross-vertical e não per-vertical?

OficinaAuto+Autopecas+Repair já têm SPECs com tabelas `oficina_auto_garantias`, `autopecas_garantias`, `repair.warranty_until` — cada uma reinventando workflow. Custos:

- 3 schemas divergentes → BI cross-vertical impossível
- 3 FSMs paralelas violando ADR 0143 (FSM canônico ÚNICO)
- 3 implementações de "OS-filha sem cobrar cliente" — duplicação
- ComVis, Vestuario, futuras verticais teriam que reinventar

**Decisão recomendada (D1 ADR draft):** **schema único cross-vertical** `warranty_*` no `Garantia (planejado — não existe)`, com `morphTo` pro item original (peça/serviço/banner/peça-eletrônica) e `parent_*_id` em cada vertical apontando OS-filha pra OS-pai.

### Antecedentes nos SPECs existentes

| Módulo | Sinalização existente | Conflito a resolver |
|---|---|---|
| `OficinaAuto/SPEC.md` §15.3 | `oa_garantias` granular per-item (peça vs serviço), `os_pai_id` + `tipo_os` enum, stage FSM `garantia_acionada` | Garantia per-item já antecipada → **manter granularidade**; consolidar tabela em `warranty_claims` |
| `Autopecas/SPEC.md` US-AP-006 | `autopecas_garantias` com `tipo enum [loja/fabricante]`, fluxo RMA pendente→enviado→aprovado/rejeitado, `custo_loja_substituicao_pago` | Workflow RMA mais maduro que OficinaAuto → **virar referência** `warranty_reimbursements` |
| `Repair/SPEC-FSM-WIREUP.md` §2.1 | Stage `garantia_acionada` terminal + action `acionar_garantia` (gerente) cria OS-filha via `parent_job_sheet_id` | Pipeline pioneiro → **manter mecânica** OS-filha (parent FK) |
| `ComunicacaoVisual/SPEC.md` | NÃO menciona garantia (descoberto no discovery) | **GAP** — adicionar quando workflow estiver canônico (banner descolando = reimpressão) |
| `Vestuario` | Não usa garantia (vestuário tem troca/devolução, não warranty) | Garantia (planejado — não existe) é **opt-in per business** — Vestuario simplesmente não ativa |

## §2 Cenários de uso (Given/When/Then detalhados)

### Cenário A — OficinaAuto recapagem com defeito (Vargas-like)

**Given** Vargas Recapagem (`business_id=N`) vende serviço "recapagem cavalo placa ABC-1234" R$ [redacted Tier 0] + 4 bandas R$ [redacted Tier 0] em 2026-03-01
- `transactions.id=10001` com pipeline FSM canon LIVE (ADR 0143)
- `oa_servicos_executados.id=55` (serviço, 180 dias garantia)
- `oa_pecas_utilizadas.id=88` (banda, 90 dias garantia)
- 4 registros em `warranty_claims_eligibility` (1 serviço + 4 bandas)

**When** cliente retorna 2026-04-30 (60 dias depois — DENTRO garantia) com 1 pneu descolando
- Atendente abre drawer OS-10001, clica "Solicitar garantia" → drawer claim
- Seleciona item: peça `oa_pecas_utilizadas.id=88` (1 banda específica)
- Preenche motivo: "Descolando lateral direita, fotos anexas"
- Anexa 3 fotos via upload mobile
- Submit → `warranty_claims.id=701` em stage `garantia_solicitada`

**Then**:
- FSM dispara side-effect `NotificarGerenteGarantia` (WhatsApp + email)
- Gerente abre drawer claim → action `analisar` → stage `garantia_em_analise`
- Gerente avalia → action `aprovar_garantia` (role `garantia.aprovar#{biz}`) → stage `garantia_aprovada`
- Side-effect `CriarOsFilhaGarantia`:
  - Nova `service_orders.id=10501` criada com `os_pai_id=10001`, `tipo_os=retorno_garantia`
  - `current_stage_id` pipeline OS reparo padrão começa em `recebido_para_diagnostico`
  - Veículo pré-preenchido, atendimento NÃO cobra cliente
  - Itens pré-importados: 1 banda nova
- Side-effect `ReservarEstoque` (banda nova)
- Quando OS-10501 conclui execução → action `concluir_garantia` no claim → stage `garantia_concluida` (T)
- `warranty_resolutions.id=701` com `decisao=aprovada`, `valor_envolvido=R$ [redacted Tier 0]`
- Transaction-10001 NÃO sofre estorno; lojista absorve R$ [redacted Tier 0] custo
- KPI mensal: "% custo-garantia sobre faturamento" sobe

### Cenário B — Autopecas peça Bosch defeituosa (Vargas balcão)

**Given** Vargas Autopecas vende peça Bosch BB-318 cubo Gol em 2026-01-15 por R$ [redacted Tier 0]
- `warranty_claims_eligibility` registrado com `fornecedor_id=Bosch`, `tipo=fabricante`, `prazo_dias=365`

**When** cliente retorna 2026-06-15 (5 meses depois, DENTRO 1 ano fabricante)
- Balconista abre `warranty_claims.id=702` em `garantia_solicitada`
- Item: produto cubo BB-318
- Motivo: "Travou em 800km de uso, fotos do mancal"

**Then**:
- Gerente aprova → `garantia_aprovada`
- Side-effect `CriarTrocaImediataLoja` (NÃO cria OS-filha, é venda balcão):
  - Sai 1 unidade BB-318 nova do estoque (reserva)
  - Cliente leva peça nova hoje
  - `warranty_resolutions.decisao=aprovada_troca_imediata`, `valor_envolvido=R$ [redacted Tier 0]`
- Pipeline ramifica → `ressarcimento_solicitado`:
  - `warranty_reimbursements.id=702` criado com `fornecedor_id=Bosch`, `valor_solicitado=R$ [redacted Tier 0]`
  - Status `pendente`
  - Side-effect `AbrirRmaBosch` (V3 = API B2B; V1 = humano-action — exporta PDF/PNG anexos pra portal Bosch)
- Loja absorve custo no momento (custo_loja_substituicao_pago=200)
- 45 dias depois Bosch deposita R$ [redacted Tier 0] → financeiro marca `ressarcimento_recebido` (T) → ajuste contábil

### Cenário C — ComVis banner descolou (Gold-like)

**Given** Gold Comvis vende e instala banner 3x2m em 2026-04-01 por R$ [redacted Tier 0]
- Política da loja: "30 dias garantia instalação, 12 meses cor (mild solvent), excluído rasgo por tensão inadequada"
- `warranty_claims_eligibility` criado: `tipo=loja`, `prazo_dias=30` (instalação), `escopo=instalacao`

**When** cliente reclama 2026-04-15 (15 dias, DENTRO 30d instalação): banner descolou lateral
- Atendente abre claim — `motivo_relato="lateral descolou, fotos anexas"`
- Anexa foto + texto: "Vento forte mas suporte deveria aguentar"

**Then**:
- Gerente analisa → checa fotos → action `aprovar_garantia` (reinstalação loja)
- Side-effect `CriarOsFilhaGarantia`:
  - Nova service_orders (ou job ComVis equivalente) com `os_pai_id` apontando venda original
  - Tipo: reinstalação sem cobrar
  - Material extra (cola, fita) sai do estoque sem faturar cliente
- Sem ressarcimento fornecedor (defeito instalação interna, não fornecedor de lona)
- Termo de Conformidade (cliente assina foto/digital): "loja honrou garantia 100%"

### Cenário D — Garantia fabricante vencida mas loja honra (política comercial)

**Given** Autopecas Vargas vende peça Bosch em 2025-01-01 (16 meses atrás) — garantia fabricante 12m **vencida há 4m**

**When** cliente retorna alegando defeito, mas mostra histórico (cliente fiel há 8 anos, R$ [redacted Tier 0]k+ comprou)
- Atendente abre claim → seleciona item → motivo
- Gerente avalia: **fora prazo formal**, mas decide honrar por relacionamento

**Then**:
- Stage especial: `garantia_aprovada` com `tipo_aprovacao=cortesia_loja` no payload
- `warranty_resolutions.observacao_gerente="Cliente fiel R$ [redacted Tier 0]k+ histórico, decidi honrar fora prazo, ver SPI fidelização"`
- Loja absorve 100% (não tenta fornecedor — fora prazo)
- KPI separado: "cortesia_loja vs garantia_legal" pra Wagner dimensionar política

### Cenário E — Cliente abusivo (rejeição com laudo)

**Given** Cliente João tem 3 claims em 6 meses (já flagged `abuse_score>0.7`)

**When** abre 4º claim alegando "ferramenta nova defeituosa em 7 dias"
- Atendente abre claim → upload fotos
- Fotos mostram CLARO mau uso (martelada visível, falta de óleo)

**Then**:
- Job `AnalisarFotoIa` (V4 — Jana vision) detecta sinais mau uso, anexa laudo
- Gerente analisa → action `rejeitar_garantia` (role `garantia.aprovar#{biz}`)
- Stage `garantia_rejeitada` (T)
- `warranty_resolutions.decisao=rejeitada`, `justificativa="Sinais visuais de mau uso (martelada lateral) + ausência de manutenção; histórico abuse_score=0.85"`
- Termo de Rejeição (PDF assinado) — cliente recebe explicação formal + foto-laudo
- LGPD: dados claim preservados 5 anos (defesa processo) — opt-out parcial

## §3 Schema cross-vertical proposto

### Tabelas centrais (4 + 1 ponte)

```
warranty_policies          1─────────N  warranty_claims_eligibility
warranty_claims_eligibility 1─────────N  warranty_claims (quando defeito ocorre)
warranty_claims             1─────────1  warranty_resolutions (decisão final)
warranty_claims             1─────────0..1 warranty_reimbursements (se fabricante)
```

#### `warranty_policies` (regra)

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | unsignedInteger FK | **Tier 0 ADR 0093** |
| `nome` | string(120) | "Bosch peças motor 12m", "Banner instalação 30d" |
| `escopo` | enum [`produto_id`, `categoria_id`, `servico_id`, `fornecedor_id`, `business_default`] | |
| `escopo_ref_id` | bigint nullable | FK polimórfico conforme escopo |
| `tipo` | enum [`loja`, `fabricante`, `loja_e_fabricante`] | |
| `prazo_dias_peca` | int nullable | 0 = sem garantia peça |
| `prazo_dias_servico` | int nullable | 0 = sem garantia serviço |
| `prazo_dias_instalacao` | int nullable | comvis específico |
| `fornecedor_id` | bigint FK contacts nullable | quando `tipo` envolve fabricante |
| `cobertura_loja_pct` | decimal(5,2) default 100 | "loja absorve X% se fornecedor recusa" |
| `requer_foto` | bool default true | obriga upload na abertura |
| `requer_laudo_tecnico` | bool default false | claim só aprova c/ laudo formal |
| `cortesia_pos_prazo_dias` | int default 0 | tolerância política comercial (D4 abaixo) |
| `cfop_devolucao` | string(4) nullable | default `1949` ou `5949` |
| `cfop_substituicao` | string(4) nullable | default `5949` (loja saída) |
| `ativo` | bool default true | |
| `created_at`, `updated_at` | timestamps | |

Index único: `(business_id, escopo, escopo_ref_id, tipo, ativo)` parcial onde `ativo=true`.

#### `warranty_claims_eligibility` (snapshot per-item vendido)

Snapshot criado AUTOMATICAMENTE no `boletoPago` / `concluir_execucao` de qualquer pipeline FSM canon. Imutável.

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | unsignedInteger FK | Tier 0 |
| `transaction_id` | bigint FK transactions | venda original |
| `item_morph_type` | string | `App\Product`, `Modules\OficinaAuto\Models\Peca`, `Modules\Repair\Models\JobSheetItem`, etc |
| `item_morph_id` | bigint | |
| `quantidade` | decimal(15,4) default 1 | suporta divisão (1 banner = 1 unid; 4 bandas = 4 unid) |
| `policy_id` | bigint FK warranty_policies | |
| `inicio_em` | date | data NFe / entrega |
| `expira_em_peca` | date nullable | calculado: `inicio + prazo_dias_peca` |
| `expira_em_servico` | date nullable | calculado: `inicio + prazo_dias_servico` |
| `fornecedor_id` | bigint FK contacts nullable | copia da policy |
| `valor_unitario_original` | decimal(15,2) | snapshot |
| `total_acionamentos` | int default 0 | contador (incrementa quando claim aprovado) |
| `bloqueado_em` | timestamp nullable | flag se item virou "100% usado" (4 bandas todas trocadas) |
| `created_at`, `updated_at` | timestamps | |

Index: `(business_id, transaction_id)`, `(business_id, item_morph_type, item_morph_id)`, `(business_id, expira_em_peca)`.

#### `warranty_claims` (solicitação cliente)

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | unsignedInteger FK | Tier 0 |
| `eligibility_id` | bigint FK warranty_claims_eligibility | |
| `transaction_id` | bigint FK transactions | denormalizado pra UI rápida |
| `customer_id` | bigint FK contacts | |
| `current_stage_id` | bigint FK sale_process_stages | **FSM canon ADR 0143** |
| `motivo_relato` | text | descrição cliente |
| `defeito_categoria` | enum [`peca_defeito_fabrica`, `peca_quebra_uso`, `servico_inadequado`, `instalacao_defeito`, `cor_desbote`, `cliente_alega_outros`] | |
| `fotos_json` | json | array de paths (S3/storage local) |
| `laudo_tecnico_path` | string nullable | PDF/imagem |
| `abuse_score` | decimal(3,2) nullable | calculado V4 IA |
| `valor_envolvido` | decimal(15,2) | computed snapshot custo |
| `os_filha_id` | bigint FK service_orders nullable | quando vertical exige OS-filha (oficina/repair/comvis) |
| `repair_job_sheet_filha_id` | bigint FK repair_job_sheets nullable | Repair vertical |
| `solicitado_em` | timestamp | |
| `solicitado_por_user_id` | bigint FK users | atendente |
| `aprovado_em` | timestamp nullable | |
| `aprovado_por_user_id` | bigint FK users nullable | |
| `concluido_em` | timestamp nullable | |
| `created_at`, `updated_at` | timestamps | |

Index: `(business_id, current_stage_id)`, `(business_id, customer_id, solicitado_em)`, `(business_id, eligibility_id)`.

#### `warranty_resolutions` (decisão)

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | unsignedInteger FK | Tier 0 |
| `claim_id` | bigint FK warranty_claims UNIQUE | 1-1 |
| `decisao` | enum [`aprovada`, `aprovada_troca_imediata`, `aprovada_reparo_filha`, `aprovada_cortesia_loja`, `parcial`, `rejeitada`] | |
| `justificativa` | text | obrigatório se `rejeitada` ou `cortesia` |
| `valor_envolvido` | decimal(15,2) | custo final loja |
| `tipo_resolucao` | enum [`os_filha`, `troca_balcao`, `reembolso_credito`, `reimpressao`, `nenhum`] | |
| `cfop_aplicado_devolucao` | string(4) nullable | default 1949 |
| `nfe_devolucao_id` | bigint FK nfe_brasil_emissoes nullable | NFe entrada peça defeituosa |
| `nfe_substituicao_id` | bigint FK nfe_brasil_emissoes nullable | NFe saída peça nova |
| `termo_pdf_path` | string nullable | PDF assinado/digital |
| `created_at`, `updated_at` | timestamps | |

#### `warranty_reimbursements` (ressarcimento B2B fornecedor)

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | unsignedInteger FK | Tier 0 |
| `claim_id` | bigint FK warranty_claims | |
| `fornecedor_id` | bigint FK contacts | Bosch/Nakata/Fras-le etc |
| `valor_solicitado` | decimal(15,2) | |
| `valor_recebido` | decimal(15,2) nullable | |
| `protocolo_rma` | string(60) nullable | número fornecedor |
| `status` | enum [`pendente`, `enviado_fornecedor`, `em_analise_fornecedor`, `aprovado`, `recebido`, `negado`, `prescrito`] | |
| `aberto_em` | timestamp | |
| `enviado_em` | timestamp nullable | |
| `resolvido_em` | timestamp nullable | |
| `forma_recebimento` | enum [`credito_nota`, `boleto_avulso`, `transferencia`, `troca_peca`] nullable | |
| `evidencia_path` | string nullable | comprovante crédito |
| `created_at`, `updated_at` | timestamps | |

## §4 Pipeline FSM Garantia canônico

**Cria processo `warranty_standard` em `sale_processes` (table FSM canon ADR 0143)** com seed `FsmProcessoGarantiaPadraoSeeder`:

```
garantia_solicitada (initial)
  ↓ analisar_garantia (role: garantia.analisar)
garantia_em_analise
  ↓ aprovar_garantia (role: garantia.aprovar — is_critical)     ↓ rejeitar_garantia (role: garantia.aprovar — is_critical)
garantia_aprovada                                                 garantia_rejeitada (T)
  ↓ executar_garantia (role: garantia.executar)
garantia_em_execucao
  ↓ concluir_garantia (role: garantia.executar — is_critical)
garantia_concluida (T)
  └── (se fornecedor) → ressarcimento_solicitado
                          ↓ enviar_para_fornecedor (role: garantia.financeiro_ressarcimento)
                        ressarcimento_enviado
                          ↓ marcar_recebido | marcar_negado | marcar_prescrito
                        ressarcimento_recebido (T) | ressarcimento_negado (T) | ressarcimento_prescrito (T)
```

### Stages (8 stages + 3 terminais ressarcimento = 11 total)

| ord | slug | label | cor | initial | terminal | side-effects |
|---|---|---|---|---|---|---|
| 1 | `garantia_solicitada` | Garantia solicitada | amber | ✅ | — | `NotificarGerenteGarantia` (Whatsapp+email) |
| 2 | `garantia_em_analise` | Em análise | yellow | — | — | — |
| 3 | `garantia_aprovada` | Aprovada | green | — | — | 🔒 `CriarOsFilhaGarantia` OU `CriarTrocaBalcao` (decide via decisao) + `ReservarEstoque` |
| 4 | `garantia_rejeitada` | Rejeitada | red | — | ✅ T | `GerarTermoRejeicaoPdf` + `NotificarClienteRejeicao` |
| 5 | `garantia_em_execucao` | Em execução | sky | — | — | (OS-filha rolando, polling) |
| 6 | `garantia_concluida` | Concluída cliente | emerald | — | ✅ T | `MarcarTransactionOriginalHadClaim` + `NotificarClienteConcluida` |
| 7 | `ressarcimento_solicitado` | Ressarcimento pendente | violet | — | — | (após `garantia_concluida` se policy.tipo envolve fabricante) |
| 8 | `ressarcimento_enviado` | Enviado fornecedor | blue | — | — | 🔒 `AbrirRmaFornecedor` (V1 humano, V4 API B2B) |
| 9 | `ressarcimento_recebido` | Recebido | green | — | ✅ T | `RegistrarEntradaCaixa` (Modules/Financeiro) |
| 10 | `ressarcimento_negado` | Negado fornecedor | red | — | ✅ T | 🔒 `LancarPrejuizoLoja` + alerta gerente |
| 11 | `ressarcimento_prescrito` | Prescrito (sem resposta) | zinc | — | ✅ T | idem prejuizo + flag fornecedor unreliable |

### Actions × roles (Spatie per-business)

| action | from | to | role(s) | is_critical | side-effects |
|---|---|---|---|---|---|
| `analisar_garantia` | 1 | 2 | `garantia.analisar#{biz}` | — | — |
| `aprovar_garantia` | 2 | 3 | `garantia.aprovar#{biz}` | ✅ | `CriarOsFilhaGarantia` ou `CriarTrocaBalcao` + `ReservarEstoque` |
| `rejeitar_garantia` | 2 | 4 | `garantia.aprovar#{biz}` | ✅ | `GerarTermoRejeicaoPdf` |
| `iniciar_execucao` | 3 | 5 | `garantia.executar#{biz}` | — | — |
| `concluir_garantia` | 5 | 6 | `garantia.executar#{biz}` | ✅ | `ConsumirEstoqueGarantia` + `MarcarTransactionHadClaim` |
| `abrir_ressarcimento` | 6 | 7 | system trigger | — | (auto se policy fabricante) |
| `enviar_para_fornecedor` | 7 | 8 | `garantia.financeiro_ressarcimento#{biz}` | ✅ | `AbrirRmaFornecedor` |
| `marcar_recebido` | 8 | 9 | `garantia.financeiro_ressarcimento#{biz}` | ✅ | `RegistrarEntradaCaixa` |
| `marcar_negado` | 8 | 10 | `garantia.financeiro_ressarcimento#{biz}` | ✅ | `LancarPrejuizoLoja` |
| `marcar_prescrito` | 8 | 11 | system cron (90d) | — | `LancarPrejuizoLoja` |

### Transições proibidas (skip)

- ❌ `garantia_solicitada → garantia_aprovada` (skip análise) — só `is_critical=true` permite override
- ❌ `garantia_rejeitada → qualquer` (terminal)
- ❌ `ressarcimento_recebido → qualquer` (terminal)
- ❌ `garantia_em_execucao → garantia_rejeitada` (já aprovou, executou — não pode rejeitar mid-execution; criar novo claim)

## §5 Vinculação com FSM Sells canon

Quando `concluir_garantia` dispara → side-effect `MarcarTransactionOriginalHadClaim`:

```php
Transaction::find($claim->transaction_id)->update([
    'had_warranty_claim' => true,
    'last_claim_at' => now(),
]);
```

**Sem afetar `payment_status` original.** Garantia é **custo lojista**, não estorno cliente. Diferenças importantes:

| Operação | Efeito Transaction original | Efeito Asaas/Inter |
|---|---|---|
| Cancelamento venda (FSM canon LIVE) | `status=cancelled` + cascade refund | `RefundCobrancaAsaasJob` real |
| **Garantia concluída** | flag `had_warranty_claim` apenas | ❌ NENHUM — não estornar nada |
| Devolução comercial (FUTURO) | `partial_return` + financeiro estorna | TBD |

### NFe substituição

Quando peça é trocada (cenário B) e cliente NÃO leva nota nova (NFC-e cliente final), apenas registra interno. Mas quando é venda PJ com NFe-55:

**Decisão (D4 ADR draft):**
- **Entrada peça defeituosa loja**: NFe `CFOP 1.949` (outra entrada — emitente é a loja-mesma OU pode pedir fornecedor emitir devolução)
- **Saída peça nova ao cliente**: NFe `CFOP 5.949` ("outra saída de mercadoria não especificada", isenta ICMS pois substituição em garantia) — referenciar NFe original via `nfeRef`
- Quando fornecedor envia peça-reposição via RMA: NFe `CFOP 1.949` entrada (vendor → loja) sem ICMS

Refs base legal: [Portal Tributário — substituição em garantia](https://www.portaltributario.com.br/artigos/devolucaogarantia.htm) + [Resposta Consulta SP 18512/2019](https://www.legisweb.com.br/legislacao/?id=380313).

## §6 Roles novas Spatie (per-business, suffix `#{biz}` ADR 0143)

| Role | Função | Substitui |
|---|---|---|
| `garantia.solicitar#{biz}` | Atendente cria claim (qualquer atendente da loja) | — |
| `garantia.analisar#{biz}` | Avalia procedência (gerente meio-nível) | — |
| `garantia.aprovar#{biz}` | Autoriza ou rejeita formalmente | `repair.gerente` legacy |
| `garantia.executar#{biz}` | Técnico/instalador refaz | `repair.technician` legacy |
| `garantia.financeiro_ressarcimento#{biz}` | Financeiro acompanha cobrança fornecedor | financeiro padrão |
| `garantia.relatorio.view#{biz}` | Dashboard/KPIs garantia | gerente |

## §7 Integrações cross-vertical

| Vertical | Como conecta | OS-filha pattern |
|---|---|---|
| **OficinaAuto** | Substitui `oa_garantias` por `warranty_claims_eligibility` (gerada no `concluir_producao` Sells FSM). Stage `garantia_acionada` legacy do OficinaAuto FSM **deprecado** — agora claim vive em FSM `warranty_standard` separado. OS-filha continua sendo `service_orders` com `os_pai_id` apontando OS-pai. | `claim.os_filha_id = nova_service_orders.id` |
| **Autopecas** | Snapshot eligibility no `boleto_pago` venda balcão. Workflow troca-balcão (cenário B) NÃO cria OS-filha — apenas movimentação estoque. | `claim.os_filha_id NULL`, registra em `warranty_resolutions.tipo_resolucao=troca_balcao` |
| **Repair** | Snapshot no `entregue_completo`. Action legacy `acionar_garantia` (SPEC-FSM-WIREUP §2.2) **redireciona pra Garantia (planejado — não existe)** — não cria OS-filha direto, cria claim primeiro. | `claim.repair_job_sheet_filha_id = nova_repair_job_sheets.id` |
| **ComVis** | Snapshot no fechamento OS impressão. Reinstalação = OS-filha em Modules/ComunicacaoVisual (a definir) ou Modules/Repair adaptado. | `claim.os_filha_id` apontando JobSheet ComVis |
| **Vestuario** | NÃO ativa (troca/devolução tem fluxo próprio Modules/Vestuario futuro — não warranty) | N/A |
| **NfeBrasil** | Emite NFe substituição CFOP 5.949 + entrada CFOP 1.949 quando aplicável. Hook em `MarcarTransactionHadClaim` listener. | — |
| **Financeiro** | `LancarPrejuizoLoja` lança movimento contábil; `RegistrarEntradaCaixa` quando ressarcimento chega. | — |
| **RecurringBilling/Asaas** | NÃO afeta cobrança original (garantia ≠ estorno). | — |
| **Whatsapp** | Notificações cliente (claim aberto, aprovado, rejeitado, concluído) + opt-in LGPD (ADR 0143 §LGPD). | — |
| **Jana** | V4: visão computacional foto-laudo (`AnalisarFotoIa` job) detecta mau uso. V3: tool `buscar_garantias_ativas` MCP pra atendente perguntar via chat. | — |

## US ativas

> Backlog de user stories deste SPEC (convenção `US-WARR-NNN`). Detalhamento completo, fases e estimates na seção §8 abaixo.

## §8 Lista de US (US-WARR-001..020)

**Convenção:** ID `US-WARR-NNN`; estimates recalibrados ADR 0106 (10x IA-pair + 2x margem). Status: `todo`. Origem: discovery 2026-05-12.

**Implementado em:** _pendente_ — SPEC proposta em discovery (2026-05-12, ADR draft não aceita); Modules/Garantia e schema warranty_* nunca construídos (só a warranties legacy core UPos 2019 existe, escopo diferente)

### Fase 1 — Schema fundação (P0)

**US-WARR-001** · Schema base 4 tabelas + ponte (`warranty_policies`, `warranty_claims_eligibility`, `warranty_claims`, `warranty_resolutions`, `warranty_reimbursements`) — **P0**
> estimate: 4h · type: story · blocked_by: ADR 0143 LIVE
Migrations + Models + global scope `business_id` + factories + seeds testing.

**US-WARR-002** · FSM seed `FsmProcessoGarantiaPadraoSeeder` (11 stages × 10 actions × 6 roles per-business) — **P0**
> estimate: 3h · type: story · blocked_by: US-WARR-001
Espelha pattern `FsmProcessoVendaComProducaoSeeder` (ADR 0143 PR #621). Per-business multiplicador.

**US-WARR-003** · Side-effects core (`CriarOsFilhaGarantia`, `CriarTrocaBalcao`, `MarcarTransactionHadClaim`, `LancarPrejuizoLoja`, `RegistrarEntradaCaixa`) — **P0**
> estimate: 5h · type: story · blocked_by: US-WARR-002
Pattern `App\Domain\Fsm\SideEffects\*` (ADR 0143). Pest fixtures per side-effect + multi-tenant guard.

**US-WARR-004** · `WarrantyEligibilitySnapshotter` listener — auto cria eligibility no `concluir_producao` Sells FSM + `entregue_completo` Repair FSM + `boleto_pago` Autopecas — **P0**
> estimate: 3h · type: story · blocked_by: US-WARR-001
Listener escuta eventos FSM canon → resolve `warranty_policies` aplicável → cria N eligibility rows.

### Fase 2 — UI básica claim creation (P0)

**US-WARR-005** · Drawer "Solicitar garantia" no SaleSheet + OficinaAuto + Repair drawer — **P0**
> estimate: 4h · type: story · blocked_by: US-WARR-003
Botão visível no drawer quando `eligibility` ativo. Form: item, motivo, foto upload. Submit → claim em `garantia_solicitada`.

**US-WARR-006** · UI lista de claims (`/garantia` index) + drawer detalhe + timeline FSM canon — **P0**
> estimate: 4h · type: story · blocked_by: US-WARR-005
Reusa `FsmActionPanel.tsx` (ADR 0143 PR #638) + `SaleTimeline.tsx` (PR #623). Filtros: status, vertical, cliente, fornecedor, período.

**US-WARR-007** · Upload foto mobile com compressão client-side + storage S3-compatível — **P0**
> estimate: 3h · type: story · blocked_by: US-WARR-005
Pattern já tem em outros módulos? Validar. PNG/JPG ≤5MB com webp fallback.

**US-WARR-008** · Permissions UI (registrar 6 roles per-business + ACL middleware) — **P0**
> estimate: 2h · type: chore · blocked_by: US-WARR-002

### Fase 3 — Workflow ressarcimento (P1)

**US-WARR-009** · UI fluxo ressarcimento fornecedor — drawer ressarcimento + status semáforo + anexar protocolo RMA — **P1**
> estimate: 4h · type: story · blocked_by: US-WARR-006

**US-WARR-010** · Job `MarcarRessarcimentoPrescrito` cron daily (90d sem resposta → auto-prescrito + flag fornecedor) — **P1**
> estimate: 2h · type: story · blocked_by: US-WARR-009

**US-WARR-011** · Termo de Rejeição PDF — geração com template + assinatura digital opcional (cliente assina via link curto) — **P1**
> estimate: 3h · type: story · blocked_by: US-WARR-005

### Fase 4 — NFe + integração Financeiro (P1)

**US-WARR-012** · Emissão NFe substituição CFOP 5.949 + entrada CFOP 1.949 (vínculo `nfeRef`) — **P1**
> estimate: 4h · type: story · blocked_by: US-WARR-003, Modules\NfeBrasil canon
Hook em `MarcarTransactionHadClaim`. Toggle policy `cfop_devolucao` / `cfop_substituicao`.

**US-WARR-013** · Movimentação Financeiro automática (lançar prejuízo + creditar ressarcimento recebido) — **P1**
> estimate: 3h · type: story · blocked_by: US-WARR-003, Modules\Financeiro

### Fase 5 — Analytics + diferenciais (P2)

**US-WARR-014** · Dashboard "Custo garantia % faturamento" + "Top 10 produtos problemáticos" + "Fornecedor unreliable score" — **P2**
> estimate: 5h · type: story · blocked_by: Fase 1-2 completas + amostra 30+ claims

**US-WARR-015** · Notificação WhatsApp cliente (template per-stage transition, respeita LGPD consent ADR 0143) — **P2**
> estimate: 3h · type: story · blocked_by: US-WARR-005

**US-WARR-016** · Cliente abusivo flag — `abuse_score` calculado per N claims/6m + flag manual gerente — **P2**
> estimate: 4h · type: story · blocked_by: US-WARR-014

### Fase 6 — Maturação (P3)

**US-WARR-017** · API B2B fornecedor — primeira integração Bosch (sandbox primeiro) abrindo RMA automático via POST → status polling — **P3**
> estimate: 8h · type: spike · blocked_by: contato comercial Bosch + sinal qualificado (ADR 0105)

**US-WARR-018** · Jana vision `AnalisarFotoIa` — detecta mau uso/martelada/falta lubrificação via foto → anexa laudo gerente — **P3**
> estimate: 6h · type: story · blocked_by: ADS Universal Sonnet/Opus disponível + 50+ claims com foto

**US-WARR-019** · Política garantia per-business UI admin (CRUD `warranty_policies`) — **P2**
> estimate: 4h · type: story · blocked_by: US-WARR-001

**US-WARR-020** · Migração SPECs antecipados — descontinuar `oa_garantias` + `autopecas_garantias` migrando dados pra schema canônico — **P1** (após Fase 1 LIVE)
> estimate: 3h · type: chore · blocked_by: US-WARR-001..004 LIVE

### Resumo

- **Total US:** 20
- **P0:** 8 US (~25h) — fundação Fase 1+2
- **P1:** 5 US (~15h) — workflow ressarcimento + NFe + financeiro
- **P2:** 5 US (~21h) — analytics + UX premium
- **P3:** 2 US (~14h) — API B2B + IA visão
- **Estimate total IA-pair:** ~75h (recalibrado ADR 0106 — sem margem 2x)
- **Estimate ABSOLUTO (com margem 2x):** ~150h
- **Estimate humano-limitado (sinal Wagner cliente paying):** wallclock 6-12 semanas

## §9 Multi-tenant Tier 0 — checklist ADR 0093

- [x] Todas 5 tabelas têm `business_id` indexado + FK pra `business.id`
- [x] Models com `HasBusinessScope` global scope
- [x] Roles Spatie cadastradas per-business suffix `#{biz}`
- [x] Jobs assíncronos sempre recebem `$businessId` no constructor
- [x] Endpoints UI scoped por `session('user.business_id')`
- [x] Pest cross-tenant biz=99 (refinamento convenção `feedback_test_biz_99_cross_tenant_convention.md`)
- [x] `withoutGlobalScopes` permitido APENAS com `// SUPERADMIN: <razão>`

## §10 Estado da arte mercado (resumo discovery 2026-05-12)

### Concorrência analisada (Auto/Manufatura)

| Software | País | Pontos fortes warranty | Pontos fracos |
|---|---|---|---|
| **Ultracar** | BR | OS completa, foco oficinas pesadas/leves, 30 anos mercado | Warranty mencionado mas sem workflow estruturado público |
| **Auto Manager** | BR | Mid-tier oficinas, manutenção+revisão | Warranty 🟡 — só registra prazo, sem RMA |
| **Mitchell1 (Manager SE)** | USA | Warranty tab configurável, vendor invoice tracking, shop+insurance warranty | Caro (USD 200/m), sem versão BR fiscal |
| **Tekmetric** | USA | Tech assignment historical, warranty traçável por técnico, multi-shop unified | Sem fiscal BR |
| **Shop-Ware** | USA | Digital workflow + warranty tracking real-time analytics | Sem fiscal BR |
| **SAP S/4HANA WTY** | DE/global | Pipeline completo claim management + customer/vendor claim processing, automated high-volume | Inacessível PME BR (cost) |
| **Epicor Warranty** | USA | Automotive specific (OEM+suppliers), end-to-end automation | Mesmo |
| **TBF Jupiter** | USA | Especialista B2B claim automotive (puro-warranty) | Niche, não ERP completo |

### Concorrência analisada (ERPs horizontais BR)

| Software | Warranty |
|---|---|
| **Tiny ERP** | 🟡 Inventário+devolução padrão, sem workflow warranty estruturado |
| **Bling** | 🟡 RMA mencionado, sem fluxo formal |
| **TOTVS Protheus** | ✅ Warranty Management Module disponível (TMM), customizável, mas custo enterprise |
| **Conta Azul** | ❌ Sem módulo warranty |
| **Omie** | ❌ Sem módulo warranty |

### Concorrência analisada (ComVis BR)

| Empresa | Política garantia |
|---|---|
| **Neoband** | Troca de material defeituoso apenas, não cobre instalação/remoção/frete; 12-15m cor (mild solvent), 7d reclamação |
| **Mr. Print** | Reimpressão se defeito comprovado processo |
| **Wind Banner 24HS** | Reimpressão limitada, 7d prazo |

**Implicação pro oimpresso:** mercado BR PME tem **gap claro** de workflow warranty estruturado. Diferencial oimpresso: schema canônico cross-vertical + ressarcimento fornecedor tracking + integração NFe substituição automática + foto-laudo IA (V4). Mercado USA já tem (Tekmetric/Shop-Ware), mas inacessível BR por idioma+fiscal+preço.

## §11 Decisões pendentes Wagner (ver ADR draft)

Ver `memory/decisions/proposals/drafts/garantia-cross-vertical-workflow.md` para detalhes das decisões D1-D6.

## Refs

- ADR mãe FSM: [0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- ADR mãe FSM design: [0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md)
- ADR multi-tenant: [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- ADR modular vertical: [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- ADR sinal cliente: [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- ADR estimates: [0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- SPECs antecipados: [OficinaAuto §15.3](../OficinaAuto/SPEC.md) · [Autopecas US-AP-006](../Autopecas/SPEC.md) · [Repair SPEC-FSM-WIREUP §2.1](../Repair/SPEC-FSM-WIREUP.md)
- ADR draft proposta: [garantia-cross-vertical-workflow](../../decisions/proposals/drafts/garantia-cross-vertical-workflow.md)
- MATRIZ-ROI: [MATRIZ-ROI.md](MATRIZ-ROI.md)
- ROADMAP: [ROADMAP.md](ROADMAP.md)
