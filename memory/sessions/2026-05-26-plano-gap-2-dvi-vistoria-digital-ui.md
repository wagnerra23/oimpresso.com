---
title: "Plano Gap 2 — DVI Vistoria Digital UI (semaforo) no drawer"
date: "2026-05-26"
type: gap-plan
status: draft
gap_id: 2
modulo: OficinaAuto
us_relacionada: US-OFICINA-035 (Wave 3b — UI da Wave 3 backend)
cliente: Martinho biz=164
esforco_estimado: "6-10h IA-pair (fator 10x ADR 0106) + 3h smoke real"
roi: alto-diferencial-competitivo
bloqueia_demo: nao (diferencial vs concorrentes)
---

# Plano Gap 2 — DVI Vistoria Digital UI

## Contexto

Wave 3 OficinaAuto mergeou hoje (PR #1630) **backend completo** US-OFICINA-035:
- Migration `oa_inspection_items` (10 categorias enum + 3 severity ok/atencao/critico)
- Model `OaInspectionItem` (LogsActivity + softDeletes + global scope multi-tenant)
- Service `DviInspectionService` (addItem + breakdownPorSeverity + totalRecomendado)
- Controller CRUD + 2 FormRequests + 3 rotas
- `ServiceOrder::dviInspectionItems()` hasMany + accessor `dvi_breakdown`
- 10 Pest specs

**Falta: a TELA.** Sem semaforo verde/amarelo/vermelho renderizado, mecanico nao vai usar.

## Research estado-da-arte 2026

DVI software 2026 (AutoVitals, Tekmetric, Mitchell1, Torque360, Mighty Auto Pro) converge em **5 elementos UI**:

1. **Semaforo color-coded** verde (ok) / amarelo (atencao) / vermelho (critico) — universal. [Mitchell1 case study](https://mitchell1.com/shopconnection/digital-vehicle-inspections-build-trust-and-healthier-bottom-lines/) cita aumento de 30-50% em approval rate.
2. **Foto inline por item** — 30+ fotos por veiculo eh o benchmark ([AutoVitals best practices](https://blog.autovitals.com/digital-vehicle-inspection-best-practices)).
3. **Categoria iconografica** — motor/freios/suspensao com icone proprio (Lucide ja tem todos).
4. **Total recomendado destacado** — somatorio severity IN (atencao, critico) num bloco "VOCE PODE ECONOMIZAR R$ X PREVENINDO AGORA" — psicologia approval.
5. **Botao "Enviar pro cliente" via WhatsApp** — gera link publico (espelho `AprovacaoPublica.tsx` ja em PR #1627) com resumo DVI + foto + valores — cliente aprova/rejeita item-a-item.

**Wedge competitivo:** RepairShopr/mHelpDesk concorrentes BR (Bling/Tiny/Cobli) NAO tem DVI nativo. Martinho sub-vertical 4 mecanica pesada CNAE 4520 e o primeiro caso de uso. Cliente sinal qualificado ADR 0105.

## Arquivos a tocar

| Arquivo | Operacao | Notas |
|---|---|---|
| `resources/js/Pages/OficinaAuto/ProducaoOficina/_components/ServiceOrderRichSheet.tsx` | EDIT — adicionar Section 3.5 "VISTORIA DIGITAL · DVI" entre PECAS&MO e FOTOS | Reusa pattern Section ja em uso |
| `resources/js/Pages/OficinaAuto/ProducaoOficina/_components/DviSemaforoSection.tsx` | NOVO — render lista items + badges contadores + total recomendado | Composto: header badges + items list + footer total |
| `resources/js/Pages/OficinaAuto/ProducaoOficina/_components/DviSemaforoSection.charter.md` | NOVO — charter Tier B component | Status draft → live apos Wagner validar screenshot |
| `resources/js/Pages/OficinaAuto/ProducaoOficina/_components/DviItemRow.tsx` | NOVO — linha individual item com severity dot + categoria icon + descricao + valor + actions | Hover actions Edit/Delete |
| `resources/js/Pages/OficinaAuto/ProducaoOficina/_components/DviItemFormSheet.tsx` | NOVO — shadcn Sheet lateral 480px (espelha ServiceOrderItemFormSheet pattern Wave 5) | Form: categoria radio + severity radio + descricao + valor_recomendado + recomendacao + metadata (json hidden V0) |
| `resources/js/Pages/OficinaAuto/ProducaoOficina/_components/DviSendToClientButton.tsx` | NOVO — botao "Enviar pro cliente" gera link WhatsApp | V0: dispara `EnviarLinkAprovacaoDviJob` (similar Wave 4 PR #1627 mas para DVI especificamente) |
| `Modules/OficinaAuto/Jobs/EnviarLinkAprovacaoDviJob.php` | NOVO — Job WhatsApp envio link DVI publico | Espelha pattern `EnviarLinkAprovacaoWhatsappJob` (Wave 4) |
| `resources/js/Pages/OficinaAuto/AprovacaoDviPublica.tsx` | NOVO — tela publica cliente revisa DVI item-a-item | Mobile-first 360px, sem auth, token HMAC like AprovacaoPublica |
| `resources/js/Pages/OficinaAuto/AprovacaoDviPublica.charter.md` | NOVO — charter Tier A page | Status draft |
| `Modules/OficinaAuto/Http/Controllers/Public/AprovacaoDviController.php` | NOVO — controller publico GET token + POST aprovar/rejeitar item | Token HMAC + LGPD |
| `Modules/OficinaAuto/Services/AprovacaoDviService.php` | NOVO — token gen + validacao + tracking item-a-item | Espelha AprovacaoOsService |
| `Modules/OficinaAuto/Database/Migrations/2026_05_27_*_add_client_decision_to_oa_inspection_items.php` | NOVO — adicionar `client_decision enum(pending,approved,rejected)` + `client_decided_at` | aditivo nullable |
| `Modules/OficinaAuto/Tests/Feature/DviUiIntegrationTest.php` | NOVO — Inertia assert DVI section payload | 4 Pest |
| `Modules/OficinaAuto/Tests/Feature/AprovacaoDviPublicaTest.php` | NOVO — token publico fluxo aprovar/rejeitar item | 6 Pest |
| `Modules/OficinaAuto/Routes/web.php` | EDIT — adicionar GET /aprovar-dvi/{token} + POST submit item | publico, throttle 30/1 |
| `Modules/OficinaAuto/SCOPE.md` | EDIT — declarar 4 novos files | Bloqueio scope-guard.yml |

## Restricoes Tier 0 deste gap

1. **Multi-tenant ADR 0093** — DviInspectionService JA tem global scope. Job novo precisa receber `businessId` no constructor (pattern Wave 4 EnviarLinkAprovacaoWhatsappJob).
2. **F3 anti-padroes** — `AprovacaoDviPublica.tsx` e Pages/<Mod>/<Tela>.tsx — LER `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` ANTES de escrever. 6 meta + 15 tecnicos pra evitar.
3. **Charter > Spec (UI-0013)** — DviSemaforoSection (Tier B component) + AprovacaoDviPublica (Tier A page) precisam de charter ANTES da Page (skill `charter-write`). Charter inclui `mwart_pattern_reuse` apontando pra protótipo Cowork se houver, ou marca `prototype_score: N/A — derivado screenshot Wagner aprovado`.
4. **PT aplicavel** — `memory/requisitos/_DesignSystem/padroes-tela/PT-XX-*.md` pode ter pattern de Drawer-com-Sections. Verificar antes.
5. **Camera input + privacidade LGPD** — fotos DVI servidas pra cliente NAO podem expor outros clientes do mesmo business (token HMAC isola).
6. **Anti-hook charter** — `EnviarLinkAprovacaoDviJob` precisa de aprovacao humana antes de disparar (botao explicit, sem trigger automatico Observer).

## Mini-comparativo atual → target

| Aspecto | Hoje (Wave 3 backend only) | Target Gap 2 |
|---|---|---|
| Mecanico cadastra item DVI | API JSON crua | Sheet UI 480px |
| Visualizacao no drawer | nenhuma | Section semaforo 3 badges contadores + lista items |
| Severity color | N/A | Verde/amarelo/vermelho com dot circular |
| Categoria icon | N/A | Lucide icons Wrench/Disc/Battery/etc |
| Total recomendado | API breakdown crua | Bloco destacado "Voce pode economizar R$ X" |
| Cliente recebe | Nada | WhatsApp link publico com DVI completo |
| Cliente aprova item-a-item | N/A | Checkbox por item + total dinamico aprovado |
| Foto inline (Gap 1) | N/A | Plugar se Gap 1 mergeado antes |
| Tracking | N/A | `client_decision` enum + decided_at por item |

## Esforco estimado

- Charter Tier B DviSemaforoSection: 1h (Wagner aprovou screenshot 2026-05-26 — referencia)
- Charter Tier A AprovacaoDviPublica: 1h
- DviSemaforoSection + DviItemRow + DviItemFormSheet: 2h
- AprovacaoDviPublica page mobile-first: 2h
- AprovacaoDviController + Service + Job: 1.5h
- Migration aditiva client_decision: 30min
- 10 Pest specs: 1.5h
- Update SCOPE.md + routes: 30min
- **Total: 9-12h IA-pair** (fator 10x ADR 0106) + 3h smoke real Wagner
- **Maior gap dos 6** — sub-decomposicao em 3 PRs recomendada:
  - **PR-2a:** DviSemaforoSection + DviItemFormSheet + Inertia integration (drawer-internal) — 4h
  - **PR-2b:** EnviarLinkAprovacaoDviJob + AprovacaoDviController + Service — 3h
  - **PR-2c:** AprovacaoDviPublica.tsx publica mobile-first + migration client_decision — 4h

## Smoke criteria

- [ ] biz=164 Martinho `/oficina-auto/ordens-servico/{id}`: section DVI renderiza 3 badges (3 ok / 2 atencao / 1 critico) + 6 items listados ordenados por severity (critico primeiro)
- [ ] Clica "+Item DVI", Sheet abre, escolhe categoria "freios" + severity "critico" + descricao "Pastilhas a 10% de vida util" + valor R$ 850, salva, item aparece com dot vermelho
- [ ] Clica "Enviar pro cliente", confirmacao modal, WhatsApp dispara, URL publico salvo
- [ ] Mobile real 360px `/aprovar-dvi/{token}`: cliente Martinho ve lista, marca aprovar 2 items, total dinamico atualiza R$ 1.700 → R$ 1.250
- [ ] Cross-tenant: token de biz=164 nao funciona se logado biz=1
- [ ] Audit-log: ActivityLog grava decision per item

## Dependencias

- **Wave 3 backend JA mergeado** (PR #1630)
- **Gap 1 (upload foto) recomendado ANTES** — pra DVI ja nascer com foto inline
- **3 PRs separados** — cumprir commit-discipline ≤300 linhas
- **PR independente de gaps 3, 4, 5, 6**

## DRAFT tasks pra Wagner copy-paste

```yaml
# Task pai
title: "Gap 2 — DVI Vistoria Digital UI (3 PRs)"
module: OficinaAuto
us: US-OFICINA-035
priority: high
estimated_hours: 12
owner_proposal: claude-paralelo
description: |
  Implementar UI Wave 3b OficinaAuto US-OFICINA-035 — semaforo verde/amarelo/
  vermelho no drawer + tela publica AprovacaoDviPublica + Job envio WhatsApp.

  Wedge competitivo vs Bling/Tiny/Cobli (nenhum tem DVI nativo BR).

  Dividir em 3 sub-tasks:
  - 2a: Drawer section + Sheet form (4h)
  - 2b: Service + Controller publico + Job (3h)
  - 2c: AprovacaoDviPublica mobile-first + migration aditiva (4h)

  Pre-req: Gap 1 (upload foto) merged antes (recomendado).

  Refs: ADR 0093, ADR 0094, ADR 0105 (cliente sinal Martinho), ADR 0106
acceptance_criteria:
  - "Wagner biz=164 valida 3 screenshots (drawer DVI + Sheet form + publica mobile)"
  - "Cross-tenant: token biz=164 falha em biz=1"
  - "Pest 10/10 verde local"
  - "Cliente final Martinho aprova 2 items via mobile real"
```

## Refs

- [ADR 0093 Multi-tenant Tier 0](memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- ADR 0105 Cliente como sinal Martinho
- [AutoVitals DVI Best Practices](https://blog.autovitals.com/digital-vehicle-inspection-best-practices)
- [Mitchell1 DVI healthier bottom lines](https://mitchell1.com/shopconnection/digital-vehicle-inspections-build-trust-and-healthier-bottom-lines/)
- [Tekmetric DVI mobile feature](https://www.tekmetric.com/feature/digital-vehicle-inspection)
- PR #1630 backend Wave 3 (mergeado hoje)
- PR #1627 Wave 4 Job pattern (espelhar)
- `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` (LER ANTES)
- `memory/requisitos/_DesignSystem/padroes-tela/` (Drawer pattern)
