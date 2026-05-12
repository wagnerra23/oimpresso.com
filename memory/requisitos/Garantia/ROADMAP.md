---
slug: garantia-roadmap
module: Garantia
type: roadmap
status: discovery
created_at: 2026-05-12
updated_at: 2026-05-12
related: [SPEC.md, MATRIZ-ROI.md, garantia-cross-vertical-workflow]
---

# ROADMAP — Modules/Garantia (cross-vertical workflow)

> **Estimates ADR 0106** (10x IA-pair + 2x margem absoluta).
> Wallclock humano-limitado (canary, smoke, cliente test) preserva relógio real.
> Dependência ADR 0105: cada fase exige **sinal qualificado paying** pra ativar — Vargas autopecas como piloto natural.

## Visão executiva

5 fases sequenciais, cada uma deployable independente em prod (canary biz=1 → biz=cliente piloto → multi-business).

| Fase | Escopo | US | Estimate IA-pair | Wallclock realista | Sinal pra ativar |
|---|---|---|---|---|---|
| **F1** | Schema fundação + FSM stages + listeners | US-WARR-001..004, 020 | ~15h | 1-2 sem | ADR `accepted` (D1-D6 Wagner) |
| **F2** | UI claim creation + foto upload + permissions | US-WARR-005..008, 017 | ~13h | 1-2 sem | F1 verde + 1 vertical alvo |
| **F3** | Workflow ressarcimento semi-manual + termo PDF | US-WARR-009..011, 020 | ~9h | 1-2 sem | F2 verde + cliente piloto autopecas ou recapagem |
| **F4** | NFe substituição + Financeiro integração + analytics base | US-WARR-012..014, 019 | ~13h | 2-3 sem | F3 verde + cliente piloto com volume mensal ≥ 5 claims |
| **F5** | Analytics premium + WhatsApp + cliente abusivo + API B2B + Jana visão | US-WARR-015..018 | ~21h | 3-6 sem | F4 verde + cliente pagante R$ [redacted Tier 0]k+/ano ressarcimento OU lucro estratégico |

**Total:** 20 US (75h IA-pair / 150h margem) · wallclock 8-15 semanas dependente sinal.

---

## Fase 1 — Schema fundação + FSM stages (P0)

**Objetivo:** schema canônico + pipeline FSM `warranty_standard` rodando em prod biz=1 (sem UI ainda). Migração SPECs antecipados.

### Entregáveis

- US-WARR-001 — Schema 5 tabelas (`warranty_policies`, `warranty_claims_eligibility`, `warranty_claims`, `warranty_resolutions`, `warranty_reimbursements`) + Models + global scope `business_id` + factories Pest + seeds testing.
- US-WARR-002 — `FsmProcessoGarantiaPadraoSeeder` (11 stages × 10 actions × 6 roles per-business) — espelha pattern `FsmProcessoVendaComProducaoSeeder` (ADR 0143 PR #621).
- US-WARR-003 — Side-effects core: `CriarOsFilhaGarantia`, `CriarTrocaBalcao`, `MarcarTransactionHadClaim`, `LancarPrejuizoLoja`, `RegistrarEntradaCaixa`. Pattern `App\Domain\Fsm\SideEffects\*`. Pest fixtures + multi-tenant guard.
- US-WARR-004 — Listener `WarrantyEligibilitySnapshotter` — escuta `concluir_producao` Sells + `entregue_completo` Repair + `boleto_pago` Autopecas → cria N rows eligibility imutáveis.
- US-WARR-020 — Migração legacy: SPECs antecipados (`oa_garantias` + `autopecas_garantias`) viram tabelas legadas; dados migrados via job.

### Pré-requisitos

- ✅ ADR `accepted` (Wagner decide D1-D6 → numerar próximo ADR após 0143)
- ✅ ADR 0143 LIVE (já em prod 2026-05-12)
- ✅ ADR 0093 multi-tenant Tier 0 cumprido
- ⚠️ Wagner aprova schema final (D1 + D2 cross-vertical + OS-filha pattern)

### Critérios de aceite (DoD)

- [ ] Migrations aplicadas em `dev` + `homolog`
- [ ] Models testados Pest 5+ fixtures cobertura ≥80%
- [ ] Cross-tenant test biz=99 confirma isolamento (convenção `feedback_test_biz_99_cross_tenant_convention.md`)
- [ ] FSM seed roda + idempotente
- [ ] `php artisan fsm:scan-drift` daily detecta zero drift no warranty
- [ ] Smoke prod biz=1: criar 1 eligibility manual via tinker → confirma snapshot

### Risco / Mitigação

- ⚠️ Migração `oa_garantias` ou `autopecas_garantias` se já estiverem em prod → mitigar: SPECs ainda não em prod (status SPECs = sinalização antecipada).
- ⚠️ FSM `garantia_acionada` no `OficinaAuto` precisa virar deprecado → smoke teste em biz=1 antes biz=4.

### Wallclock realista

**1-2 semanas IA-pair + 1 review humano (Felipe ou Wagner).**

---

## Fase 2 — UI básica claim creation (P0)

**Objetivo:** drawer "Solicitar garantia" funcional no SaleSheet + OficinaAuto + Repair. Atendente cria claim, gerente aprova/rejeita, técnico executa. Visível em /garantia index.

### Entregáveis

- US-WARR-005 — Drawer "Solicitar garantia" botão visível em SaleSheet / OficinaAuto drawer / Repair JobSheet drawer quando `eligibility_id` ativo. Form: item, motivo, foto upload. Submit → claim em `garantia_solicitada`.
- US-WARR-006 — UI `/garantia` index + drawer detalhe + timeline FSM canon. Reusa `FsmActionPanel.tsx` (ADR 0143 PR #638) + `SaleTimeline.tsx` (PR #623). Filtros: status, vertical, cliente, fornecedor, período.
- US-WARR-007 — Upload foto mobile com compressão client-side + storage S3-compatível (PNG/JPG ≤5MB, webp fallback).
- US-WARR-008 — Permissions UI registrar 6 roles per-business + middleware ACL.
- US-WARR-017 (parcial) — Lookup garantia ativa por NF+SKU (atendente balcão Autopecas crítico — Vargas).

### Pré-requisitos

- ✅ F1 verde + smoke prod biz=1
- ✅ Cliente piloto identificado (Vargas Autopecas natural — ADR 0105 sinal qualificado)
- ⚠️ Wagner aprova mockup UI antes Edit/Write (`mwart-comparative` Tier A — ADR 0107 visual comparison gate)

### Critérios de aceite

- [ ] Drawer abre em <300ms
- [ ] Foto upload <5s típico (rede 4G)
- [ ] Gerente recebe Whatsapp +email em <30s do submit
- [ ] Pest browser smoke (Pest v4 browser) cobre fluxo end-to-end biz=1 + biz=99 cross-tenant
- [ ] Charter `Index.charter.md` + `Drawer.charter.md` aprovado Wagner (S4+ skill `charter-first` — quando ativa)

### Risco / Mitigação

- ⚠️ Foto upload em rede precária BR (3G/2G) → testar com chrome devtools throttling antes prod
- ⚠️ Mobile-first design exige `mwart-comparative` 15 dimensões — gate Wagner SCREENSHOT (ADR 0114)

### Wallclock realista

**1-2 semanas IA-pair (UI + Charter) + 1 review humano + 1 cliente piloto smoke.**

---

## Fase 3 — Workflow ressarcimento + termo PDF (P1)

**Objetivo:** workflow ressarcimento fornecedor semi-manual funcional. Termo rejeição PDF assinado digital.

### Entregáveis

- US-WARR-009 — UI fluxo ressarcimento fornecedor: drawer ressarcimento + status semáforo + anexar protocolo RMA + comprovante crédito.
- US-WARR-010 — Job `MarcarRessarcimentoPrescrito` cron daily (90d sem resposta fornecedor → auto-prescrito + flag fornecedor unreliable).
- US-WARR-011 — Termo Rejeição PDF — template + assinatura digital opcional (cliente assina via link curto + LGPD opt-in).
- US-WARR-020 (finalização) — Confirma migração legacy + drop tabelas `oa_garantias` + `autopecas_garantias` se houver.

### Pré-requisitos

- ✅ F2 verde em pelo menos 1 cliente piloto (1+ claim resolvido end-to-end)
- ✅ Cliente piloto Autopecas tem ≥1 fornecedor real (Bosch/Nakata/Fras-le) já cadastrado em `contacts`
- ⚠️ Wagner valida template Termo Rejeição PDF (jurídico — Eliana[E] advogada)

### Critérios de aceite

- [ ] 1 ressarcimento real Bosch tracked end-to-end (pendente → enviado → recebido)
- [ ] Termo Rejeição PDF gera + cliente assina via link curto (testar com 1 cliente abusivo simulado)
- [ ] Pest: side-effect `LancarPrejuizoLoja` cria movimento Financeiro correto

### Risco / Mitigação

- ⚠️ Template Termo Rejeição precisa revisão jurídica (Eliana[E]) — defesa LGPD/processo
- ⚠️ PDF generation lib pode ser lenta — usar pool worker Centrifugo? Ou job background simples?

### Wallclock realista

**1-2 semanas IA-pair + 1 review Eliana(jurídico) + canary 30d cliente piloto.**

---

## Fase 4 — NFe substituição + Financeiro + analytics base (P1)

**Objetivo:** fiscal BR canônico funcional. Movimentações contábeis automáticas. Dashboard básico operacional.

### Entregáveis

- US-WARR-012 — Emissão NFe substituição CFOP 5.949 + entrada CFOP 1.949 (vínculo `nfeRef`). Hook em `MarcarTransactionHadClaim`. Toggle policy `cfop_devolucao` / `cfop_substituicao`.
- US-WARR-013 — Movimentação Financeiro automática: lançar prejuízo (movimento DESPESA categoria "garantia honrada") + creditar ressarcimento recebido (movimento RECEITA categoria "ressarcimento fornecedor").
- US-WARR-014 — Dashboard básico: "Custo garantia % faturamento" mensal + "Top 10 produtos problemáticos" + "Fornecedor unreliable score".
- US-WARR-019 — UI admin CRUD `warranty_policies` per-business (Wagner pode editar via tenant admin).

### Pré-requisitos

- ✅ F3 verde + 5+ claims resolvidos em prod
- ✅ Cliente piloto com NFe-55 ativo (PJ) e/ou NFC-e ativo (PF)
- ⚠️ Wagner aprova fiscal CFOP escolhido (D4) — possivelmente revisão contábil externa

### Critérios de aceite

- [ ] NFe emitida + autorizada SEFAZ (cstat 100) em ambiente homolog primeiro, depois prod canary
- [ ] Movimento Financeiro casa com NFe ↔ ressarcimento recebido (reconciliação)
- [ ] Dashboard mostra dados reais 30d+ histórico
- [ ] Wagner valida dashboard via screenshot (ADR 0114 — gate visual)

### Risco / Mitigação

- ⚠️ SEFAZ pode rejeitar CFOP 5.949 c/ ICMS isento se faltar campo `nfeRef` — testar em homolog primeiro
- ⚠️ Reforma Tributária 2027 (IBS/CBS) pode mudar CFOP — review_triggers em ADR
- ⚠️ Contabilidade externa (Eliana[E]) valida lançamento DESPESA/RECEITA contábil

### Wallclock realista

**2-3 semanas IA-pair + 1 canary 30d + reconciliação financeira mês fechado.**

---

## Fase 5 — Analytics premium + WhatsApp + cliente abusivo + API B2B + Jana visão (P2-P3)

**Objetivo:** diferenciais competitivos (BI premium, automação IA, integração B2B). Só ativar com sinal qualificado paying (ADR 0105).

### Entregáveis

- US-WARR-015 — Notificação WhatsApp cliente per-stage (template configurável + LGPD consent opt-in ADR 0143).
- US-WARR-016 — Cliente abusivo flag — `abuse_score` calculado per N claims/6m + flag manual gerente + LGPD retention 5 anos (defesa).
- US-WARR-017 — API B2B fornecedor: primeira integração Bosch (sandbox primeiro) — POST RMA automático + polling status.
- US-WARR-018 — Jana vision `AnalisarFotoIa` — detecta mau uso/martelada/falta lubrificação via foto → anexa laudo gerente.
- US-WARR-014 (extensões) — Dashboard premium: "Custo-garantia per-vertical", "Tempo médio resolução por fornecedor", "Cliente abusivo top 10".

### Pré-requisitos

- ✅ F4 verde + 50+ claims tracked em prod (volume razoável pra analytics)
- ✅ Cliente paying R$ [redacted Tier 0]k+/ano ressarcimento (ADR 0105 sinal qualificado)
- ✅ ADS Universal Sonnet/Opus disponível (S5 ~jul/2026) pra Jana visão
- ⚠️ Bosch (ou Nakata/Fras-le) tem API B2B documentada + sandbox acessível

### Critérios de aceite

- [ ] WhatsApp template per-stage com opt-in LGPD honrado (testar com 5 clientes consent ON + 5 OFF)
- [ ] API Bosch sandbox: abrir RMA → polling status → marcar `recebido` automaticamente
- [ ] Jana visão accuracy ≥75% em 100 claims teste (manual labeling)
- [ ] Dashboard premium aprovado Wagner via screenshot

### Risco / Mitigação

- ⚠️ Bosch API B2B pode não existir ou ser inacessível PME → spike US-WARR-017 timebox 16h max
- ⚠️ Jana visão custo Opus alto — só processar fotos onde abuse_score sinaliza incerteza
- ⚠️ Cliente abusivo flag tem viés possível — gerente sempre tem override manual + auditoria

### Wallclock realista

**3-6 semanas IA-pair + spike API B2B (timebox) + canary IA visão 60d.**

---

## Cronograma agregado (cenário "Vargas Autopecas piloto qualificado, ativação imediata")

| Mês | Fase | Marco |
|---|---|---|
| **M0** (mai/26) | discovery (este SPEC) | Wagner aprova ADR D1-D6 |
| **M0.5** (jun/26) | F1 | Schema LIVE prod biz=1 |
| **M1** (jun/26) | F2 | UI claim creation LIVE biz=Vargas |
| **M2** (jul/26) | F3 | Ressarcimento Bosch tracked |
| **M3-M4** (ago-set/26) | F4 | NFe substituição LIVE + Financeiro |
| **M5+** (out/26+) | F5 | API B2B + Jana visão (condicional ROI) |

**Cenário pessimista** (sinal demora, blocked Wagner): toda Fase 1+2 fica em backlog ADR feature-wish. Só ativar quando 1+ cliente paying explicitamente pedir.

## Métricas de saúde (per fase)

- **F1:** zero drift FSM warranty no `fsm:scan-drift` daily
- **F2:** % de claims criados pelo atendente (vs % criados pelo gerente direto) — proxy de "ferramenta usável"
- **F3:** % de ressarcimentos recebidos / solicitados (target ≥50% após 90d)
- **F4:** % de NFe substituição autorizada SEFAZ (target ≥98% — proxy de fiscal correto)
- **F5:** Whatsapp open rate cliente + Jana visão accuracy ≥75%

## Refs

- SPEC: [SPEC.md](SPEC.md)
- MATRIZ-ROI: [MATRIZ-ROI.md](MATRIZ-ROI.md)
- ADR draft: [garantia-cross-vertical-workflow](../../decisions/proposals/drafts/garantia-cross-vertical-workflow.md)
- ADR mãe FSM canon: [0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- ADR estimates IA-pair: [0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- ADR cliente sinal qualificado: [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- ADR multi-tenant Tier 0: [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
