---
slug: garantia-cross-vertical-workflow
number: TBD
title: "Workflow Garantia cross-vertical canônico — Modules/Garantia em cima de FSM canon (proposed)"
type: adr
status: proposed
authority: proposal
lifecycle: draft
decided_by: []
decided_at: null
module: Garantia
tags: [garantia, warranty, cross-vertical, fsm, ressarcimento, rma, nfe-substituicao, oficina, autopecas, comvis, repair]
supersedes: []
supersedes_partially: []
amends: [0143]
superseded_by: []
related: [0143, 0129, 0093, 0094, 0104, 0105, 0106, 0121]
pii: false
review_triggers:
  - "Primeiro claim em prod biz=1 — revisar gateway FSM + side-effects + multi-tenant"
  - "Primeiro ressarcimento fornecedor Bosch real recebido — calibrar SLA + workflow B2B API"
  - "Cliente abusivo flag triggered 5+ vezes — revisar abuse_score threshold + LGPD retenção"
  - "Reforma Tributária 2027 CFOP/CST mudar substituição garantia — revisar §6 fiscal"
---

# ADR (proposed) — Workflow Garantia cross-vertical canônico (Modules/Garantia)

## Status

**`proposed` 2026-05-12.** Aguarda Wagner aprovar 6 decisões pendentes (D1-D6 abaixo) antes de virar `accepted` numerada.

## Contexto

3 SPECs verticais (`OficinaAuto/SPEC.md` §15.3, `Autopecas/SPEC.md` US-AP-006, `Repair/SPEC-FSM-WIREUP.md` §2.1) antecipam workflow garantia, cada um com:

- Tabela própria (`oa_garantias`, `autopecas_garantias`, `parent_job_sheet_id`)
- Fluxo customizado (sem padrão FSM compartilhado)
- Stage `garantia_acionada` terminal em pipeline próprio
- Reinvenção de OS-filha (FK pra OS-pai)

Cenários reais clientes:

- **OficinaAuto Vargas** — recapagem com defeito retorna em 60d, precisa OS-filha sem cobrar
- **Autopecas Vargas/Extreme** — peça Bosch defeituosa, troca imediata loja + ressarcimento fabricante 45d depois
- **ComVis Gold** — banner descolando 15d pós-instalação, reinstalação loja
- **Repair Officeimpresso** — eletrônico volta 7d pós-entrega defeito de execução

Stack canônica disponível (LIVE em prod desde 2026-05-12):

- FSM canon ADR 0143 (`sale_processes` × 11 stages × 21 actions × roles per-business)
- ExecuteStageActionService + GuardsFsmTransitions trait
- Side-effects pattern `App\Domain\Fsm\SideEffects\*` (Reservar/Consumir/Liberar + Cancelar cascade)
- audit-trail `sale_stage_history` append-only
- LGPD consent embutido em Whatsapp/email notifications

**Problema observado**: cada vertical SPEC reinventa garantia → BI cross-vertical impossível, FSM duplicada (viola ADR 0143 "FSM canônico ÚNICO"), padrão `os_pai_id` divergente.

## Decisão proposta

**Criar `Modules/Garantia` cross-vertical** com:

1. **Schema único 5 tabelas** (`warranty_policies`, `warranty_claims_eligibility`, `warranty_claims`, `warranty_resolutions`, `warranty_reimbursements`) — substituindo `oa_garantias` + `autopecas_garantias` (consolidação obrigatória US-WARR-020)
2. **Pipeline FSM canon** `warranty_standard` (11 stages × 10 actions × 6 roles) seed `FsmProcessoGarantiaPadraoSeeder` — em cima de `sale_processes` (ADR 0143 schema)
3. **Side-effects 5** (`CriarOsFilhaGarantia`, `CriarTrocaBalcao`, `MarcarTransactionHadClaim`, `LancarPrejuizoLoja`, `RegistrarEntradaCaixa`) — pattern Domain/Fsm/SideEffects
4. **Listener `WarrantyEligibilitySnapshotter`** — escuta `concluir_producao` Sells + `entregue_completo` Repair + `boleto_pago` Autopecas → cria snapshot eligibility imutável
5. **Roles Spatie per-business** (`garantia.solicitar|analisar|aprovar|executar|financeiro_ressarcimento|relatorio.view#{biz}`)
6. **OS-filha pattern unificado** — `warranty_claims.os_filha_id` aponta nova `service_orders` (OficinaAuto/ComVis) OU `warranty_claims.repair_job_sheet_filha_id` (Repair). Autopecas troca-balcão NÃO cria OS-filha (movimento estoque direto)

## Decisões pendentes Wagner (D1-D6)

### D1 — Garantia cross-vertical único módulo vs per-vertical

**Recomendação:** ✅ **cross-vertical único (`Modules/Garantia`)**

**Pró cross-único:**
- BI cross-vertical (Wagner vê "% custo-garantia" total empresa)
- 1 FSM canon, 1 schema, 1 UI (DRY brutal)
- Verticais novas (futuras) herdam grátis
- Alinha com tese "modular especializado por vertical" + núcleo (ADR 0121) — Modules/Garantia é **núcleo comum** como Modules/Jana/Financeiro/NfeBrasil

**Pró per-vertical (rejeitado):**
- Cada vertical com workflow específico próprio (overhead)
- SPECs antecipados (OficinaAuto/Autopecas) já viram código (sunk cost)

**Decisão Wagner:** [ ] cross-único [ ] per-vertical [ ] híbrido

### D2 — OS-filha pattern (parent_id) vs evento garantia em OS-original

**Recomendação:** ✅ **OS-filha com `parent_*_id`**

**Pró OS-filha:**
- Histórico veículo/cliente mostra "OS-1234 (venda) + OS-1456 (garantia)" separadas — KPI claro
- Permite cobrar custos materiais nova execução sem afetar venda original
- Repair SPEC-FSM-WIREUP §2.2 já adotou pattern (`parent_job_sheet_id`) — consistente

**Pró evento OS-original (rejeitado):**
- Menos tabelas, claim vira apenas log na OS
- Perde rastreio se múltiplos defeitos diferentes na mesma OS

**Decisão Wagner:** [ ] OS-filha [ ] evento OS-original [ ] híbrido per-vertical

### D3 — Ressarcimento fornecedor automático (API B2B) vs manual (notificação humano)

**Recomendação:** ✅ **manual V1, evoluir pra API V4 conforme sinal (ADR 0105)**

**V1 manual:**
- Side-effect `AbrirRmaFornecedor` gera **task humano** ("Abrir RMA Bosch protocolo via portal manualmente")
- UI permite anexar protocolo RMA + comprovante crédito recebido depois

**V4 API B2B (futuro):**
- Adapter por fornecedor (Bosch primeiro como spike US-WARR-017)
- POST RMA → polling status → auto-update `warranty_reimbursements`
- Spike só quando 1+ cliente paying tiver R$ [redacted Tier 0]k+/ano ressarcimento

**Pró API auto direto V1 (rejeitado):**
- Cada fornecedor tem API própria (~10+ portais distintos) — complexity insane
- Sem sinal qualificado contraria ADR 0105

**Decisão Wagner:** [ ] manual V1 [ ] API auto V1 [ ] híbrido por fornecedor

### D4 — NFe garantia (CFOP devolução + nova) vs nota crédito interna

**Recomendação:** ✅ **CFOP devolução (1.949) + saída substituição (5.949) — fiscal canônico**

Base legal: CFOP 1.949 ("outra entrada não especificada") aceito pacificamente em substituição garantia (refs: Resposta Consulta SP 18512/2019, Portal Tributário). Saída substituição loja → cliente: CFOP 5.949 sem ICMS (isenção fundamentada em substituição garantia, RICMS-SP Anexo I art. 132 fora prazo recobra).

**Trade-off:**
- ✅ Compliance fiscal forte (Reforma Tributária 2027 não vai mexer em garantia substituição)
- ⚠️ Complexity extra em `Modules/NfeBrasil` — 2 NFes por claim (entrada + saída)
- ⚠️ Quando cliente é PF (NFC-e), simplificado: NFC-e original cliente leva nota nova com mesmo número? Não — emite NFC-e nova sem ICMS marcando substituição. Detalhes na US-WARR-012.

**Alternativa rejeitada (nota crédito interna):**
- Não é fiscal-válida — Receita exige NFe substituição se peça volta ao estoque/fornecedor

**Decisão Wagner:** [ ] CFOP 1.949+5.949 [ ] crédito interno [ ] decidir per-vertical

### D5 — Política garantia per-business vs global oimpresso

**Recomendação:** ✅ **per-business com fallback global**

**Per-business:**
- Cada loja (Vargas, Gold, futura) define **suas próprias** policies via UI admin (`/garantia/policies`)
- Ex: Vargas serviço recapagem 180d, banda 90d, mão-de-obra 30d
- Ex: Gold ComVis instalação 30d, cor 12m mild-solvent
- Vai pra `warranty_policies.business_id` (Tier 0 ADR 0093)

**Fallback global:**
- oimpresso fornece **template recomendado** por vertical (importável via wizard onboarding)
- Loja pode editar/desabilitar

**Pró global puro (rejeitado):**
- Toda loja BR é diferente — política comercial é diferencial competitivo
- Não respeita autonomia tenant

**Decisão Wagner:** [ ] per-business [ ] global [ ] híbrido c/ fallback

### D6 — Foto/laudo defeito: obrigatório vs opcional (per-business rule)

**Recomendação:** ✅ **opcional default + flag `warranty_policies.requer_foto` por policy**

**Razão:**
- Autopecas balcão Vargas: peça pequena Bosch — foto óbvia (5min), obrigar
- ComVis: banner 3x2m descolando — foto crítica, **obrigar**
- Recapagem caminhão: foto difícil (peça já no veículo no pátio) — **opcional + texto detalhado bastam**
- Cliente abusivo flag exige foto pra rebater alegação — **obrigar via policy override gerente**

Per-policy granularidade evita "uma regra vale tudo" rígida.

**Decisão Wagner:** [ ] per-policy flag [ ] obrigatório global [ ] opcional global

## Multi-tenant Tier 0 amarração ADR 0093

- ✅ 5 tabelas com `business_id` indexado + FK
- ✅ Models com `HasBusinessScope`
- ✅ Roles Spatie suffix `#{biz}`
- ✅ Jobs assíncronos com `$businessId` no constructor
- ✅ UI scoped por `session('user.business_id')` — 404 silencioso anti-info-leak
- ✅ Pest cross-tenant biz=99

## Consequências (se aceito)

### Positivas

1. **Workflow padronizado** — qualquer vertical herda canônico
2. **BI cross-vertical possível** — Wagner mede custo-garantia total
3. **Diferencial competitivo** vs Tiny/Bling/Conta Azul/Omie (zero workflow warranty)
4. **Compliance fiscal forte** — CFOP correto + audit trail completo
5. **LGPD respeitado** — fotos preservadas conforme retention (5 anos defesa)
6. **Reusa FSM canon ADR 0143** — zero duplicação stack

### Negativas / Trade-offs

1. **Migração SPECs antecipados** — `oa_garantias` (OficinaAuto) e `autopecas_garantias` (Autopecas) viram tabelas legadas; US-WARR-020 consolida. Se já tiverem código gerado, custo migração extra
2. **5 tabelas novas** — overhead schema pra equipes pequenas (mitigação: per-business opt-in via flag)
3. **NFe substituição complexity** — `Modules/NfeBrasil` precisa suportar `nfeRef` em CFOP 5.949 — pode descobrir bugs SEFAZ em produção (mitigar com canary)
4. **Ressarcimento fornecedor manual V1** — custo operacional financeiro (humano abre RMA portal Bosch). Mitigação: dashboard "pending RMA" + cron 90d auto-prescrito
5. **Cliente abusivo flag (US-WARR-016)** — falso positivo possível; gerente sempre tem override manual

## Alternativas avaliadas

| Alternativa | Pró | Con | Veredito |
|---|---|---|---|
| **Per-vertical** (cada SPEC implementa próprio) | menos abstração | duplicação 3x, BI quebrado, FSM divergente | ❌ rejeitado |
| **Spatie state-machine ou Workflow component** | maturidade comunidade | não suporta multi-tenant per-business + RBAC granular | ❌ rejeitado (ADR 0129 já avaliou) |
| **Garantia como evento em Sells (sem módulo próprio)** | menos código | mistura concerns; SoC brutal violado (ADR 0094 §5) | ❌ rejeitado |
| **Adiar tudo até cliente paying explícito (ADR 0105)** | zero sinal => zero gasto | 4 SPECs já antecipam; sunk cost de discovery | 🟡 considerado — escolha intermediária: SPEC pronto, código só com sinal |

## Cronograma proposto (condicional sinal qualificado ADR 0105)

Ver `ROADMAP.md` em `memory/requisitos/Garantia/ROADMAP.md` para 5 fases sequenciais.

## Refs

- SPEC: [memory/requisitos/Garantia/SPEC.md](../../../requisitos/Garantia/SPEC.md)
- MATRIZ-ROI: [memory/requisitos/Garantia/MATRIZ-ROI.md](../../../requisitos/Garantia/MATRIZ-ROI.md)
- ROADMAP: [memory/requisitos/Garantia/ROADMAP.md](../../../requisitos/Garantia/ROADMAP.md)
- ADR mãe FSM: [0143](../../0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- ADR multi-tenant: [0093](../../0093-multi-tenant-isolation-tier-0.md)
- ADR cliente sinal: [0105](../../0105-cliente-como-sinal-guiar-sem-mandar.md)
- SPECs antecipados: OficinaAuto §15.3 · Autopecas US-AP-006 · Repair SPEC-FSM-WIREUP §2.1
- Refs externos discovery 2026-05-12:
  - [Mitchell1 Manager SE warranty tab](https://www.mymitchell.com/tchs/helpfiles/RepairCenter/1033/Content/18158.htm)
  - [Tekmetric repair workflow](https://support.tekmetric.com/hc/en-us/articles/360047413853)
  - [SAP S/4HANA warranty claim management](https://community.sap.com/t5/enterprise-resource-planning-blog-posts-by-sap/warranty-claim-management-customer-claim-processing-in-s-4hana-cloud-erp/ba-p/14154628)
  - [Portal Tributário — devolução substituição em garantia](https://www.portaltributario.com.br/artigos/devolucaogarantia.htm)
  - [Resposta Consulta SP 18512/2019 CFOP 1949](https://www.legisweb.com.br/legislacao/?id=380313)
  - [Neoband termos garantia comvis](https://www.neoband.com.br/termos-de-garantia/)
  - [Ultracar ERP oficina BR](https://ultracar.com.br/)

## Aprovação

**Pendente Wagner [W].** Wagner decide D1-D6 → ADR vira `accepted` numerada (próximo número disponível após 0143) → spawn implementador Fase 1.

NÃO implementar código produção até aceite formal.
