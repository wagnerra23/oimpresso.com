---
slug: oficina-auto-modulo-canonico-fsm-wireup
title: "Modules/OficinaAuto — wire-up FSM canônico, garantia granular per-item, schema multi-placa pra Vargas+Martinho"
type: adr-proposal
status: proposed
authority: pending
lifecycle: draft
proposed_by: Claude (discovery agent) + Wagner [W] revisor
proposed_at: 2026-05-12
module: OficinaAuto
tags: [oficina-auto, fsm, multi-placa, garantia-granular, recapagem, cacambas, vargas, martinho, modulo-vertical, schema-evolution, pwa-mecanico]
supersedes: []
supersedes_partially: []
amends: [0137]
related: [0143, 0137, 0121, 0094, 0093, 0105, 0106, 0011, 0129]
pii: false
review_triggers:
  - "Wagner aprovar ou rejeitar status `proposed` → `accepted`"
  - "1 piloto pagante (Vargas OU Martinho) → ativar fase 2 implementação"
  - "Modules/NFSe driver real entregue → destravar US-OFICINA-018"
  - "App PWA mecânico campo bata <30% conversão → reavaliar fase 4"
---

# ADR (proposta) — Modules/OficinaAuto wire-up FSM canônico

## Status

**Proposta** — discovery + design feito 2026-05-12 em worktree `focused-bohr-b5963f`. Aguarda revisão Wagner [W]. NÃO implementar até virar `accepted`.

## Contexto

[ADR 0137](../../0137-modules-oficinaauto-qualificada.md) qualificou `Modules/OficinaAuto` como `em construção` (Vargas + Martinho sinal qualificado 2 de 4 OfficeImpresso saudáveis). V0 scaffold mergeado PR #556 — Vehicle + ServiceOrder CRUD multi-tenant Tier 0 + 8 Pages Inertia + 16 Pest tests.

Pós-V0, três questões arquiteturais bloqueiam progressão:

1. **State machine** — ADR 0137 v0 cita "FSM canônica OS (3 Simples + 5 Complexa)" mas ADR 0143 (LIVE 2026-05-12) tornou `App\Domain\Fsm` canon ÚNICO. OficinaAuto deve wire-up (não inventar paralela).
2. **Schema multi-placa** — Vargas tem cavalo+reboque (PLACA + PLACA2 20% + CHASSI2 8%). V0 já tem campos nullable, mas FSM + UI ainda assumem 1 placa.
3. **Granularidade garantia** — caminhão recapagem Vargas tem pneu (peça 6m) + serviço aplicação (180d) com prazos diferentes; granularidade per-item vs OS-todo.

Adicionalmente:
- 6 decisões pendentes do SPEC antecipatório 2026-05-10 (CRLV adapter, tempário, modelo 3D, OEM, NFS-e cidade, PWA offline).
- Renaming `vehicles` vs `oa_vehicles` levantado em `_LICOES-CRITICAS.md` (drift PR #556).

## Decisão proposta

Estabelecer **5 decisões arquiteturais** pra OficinaAuto V1, espelhando o pattern Sells/Repair (ADR 0143).

### D1 — Wire-up FSM canônico OficinaAuto via caminho 2 (módulo próprio)

3 caminhos avaliados:

| Caminho | Descrição | Pros | Cons | Veredito |
|---|---|---|---|---|
| 1 — Reusar Repair FSM direto | `ServiceOrder` é alias de `JobSheet` | Zero código novo | Vocabulário não-automotivo + stages erradas (sem teste_estrada) + perde isolamento vertical | ❌ rejeitado — viola ADR 0121 §P1 (especialização vertical) |
| 2 — **Módulo próprio FSM ÓRBITA Repair** | Seeder próprio `FsmProcessoOficinaAutoPadraoSeeder` + reuso 100% engine (Domain/Fsm) + UI components compartilhados | Especialização vertical preservada; reuso engine + UI; baixo custo | Mais 1 processo no banco per business (15 stages × ~19 actions) | ✅ **RECOMENDADO** |
| 3 — Engine paralela isolada | `App\Domain\OficinaAutoFsm` clone | Total isolamento | Duplica código LIVE prod testado; viola ADR 0094 §5 SoC | ❌ rejeitado — re-inventa roda |

**Caminho 2 detalhe:** OficinaAuto cria 1 processo seed por business (`FsmProcessoOficinaAutoPadraoSeeder`) reutilizando todas tabelas FSM canon (`sale_processes`, `sale_process_stages`, `sale_stage_actions`, `sale_stage_action_roles`, `sale_stage_history`). `ServiceOrder` adota trait `GuardsFsmTransitions` + adiciona coluna `current_stage_id` nullable (opt-in per OS, espelha Sells/Repair).

Roles per-business com suffix `#{biz}` Spatie (consistente ADR 0143): `oficina.atendente#{biz}`, `oficina.mecanico#{biz}`, `oficina.supervisor#{biz}`, `oficina.gerente#{biz}`, `oficina.estoque#{biz}`.

15 stages canônicos OficinaAuto detalhados em SPEC §14.1 (preserva nomenclatura Repair onde overlap, adiciona oficina-específicos: `teste_estrada`, `ajuste_final`, `aguardando_aprovacao_supervisor`, `garantia_acionada` terminal cria OS-filha).

### D2 — Renaming `vehicles` → `oa_vehicles` (e `service_orders` → `oa_service_orders`) — RENOMEAR

Argumento favorável (renomear):
- Convenção `comvis_*` em Modules/ComunicacaoVisual estabelecida
- Isolamento namespace verbal (oimpresso pode ter `vehicles` futuro pra outro contexto: Modules/RecurringBilling agendamento veicular?)
- `service_orders` colide semanticamente com `repair_job_sheets` (que é "OS de reparo"); prefixar evita confusão dev

Argumento contrário (manter):
- PR #556 já mergeado em prod (drift custa migration rename)
- ADR 0137 §"Escopo arquitetural V0" especificou literal `vehicles`/`service_orders`

**Decisão proposta:** **RENOMEAR via migration nova ANTES de US-OFICINA-002 (importer)** — Felipe disse no `_LICOES-CRITICAS.md` "decidir antes de US-OFICINA-002, rename via migration depois é caro". Custo migration agora: ~30min IA-pair (tabela vazia em biz=4 piloto). Custo depois: rename + 1.064 rows Vargas + cleanup FK.

Migration: `rename_vehicles_to_oa_vehicles_and_service_orders_to_oa_service_orders` + atualizar Models + Controllers + 8 Inertia Pages + 16 Pest tests. Zero data migration (V0 sem dados real ainda — só biz=1 smoke).

### D3 — Granularidade garantia: **PER-ITEM** (peça e serviço separados)

3 opções:

| Opção | Pros | Cons | Veredito |
|---|---|---|---|
| OS-todo (1 garantia 1 prazo) | Simples; 1 linha por OS | Vargas mistura pneu 6m + serviço 180d numa OS → perde info | ❌ |
| **Per-item peça+serviço** | Rastreio fino; suporta "trocar só essa peça, manter resto" | 1 join + UI mostra timeline por item | ✅ **RECOMENDADO** |
| Híbrido (per-item E OS-todo redundante) | Flexível | Caos sync entre granularidades | ❌ |

**Decisão proposta:** **per-item**. Tabela `oa_garantias` com `item_type` enum [peca, servico] + `item_id` referenciando `oa_pecas_utilizadas` OU `oa_servicos_executados`. Default `garantia_dias`:
- Peças: 90 dias (CDC mínimo + prática mercado)
- Serviços: 180 dias (3-6m padrão setor)

Configurável per business (`oficinaauto.garantia.peca_dias_padrao` config), override per produto/serviço, override per OS (mecânico justifica).

KPI emergente: "custo garantia % faturamento" — útil pra dono (acima 5% = qualidade mecânico ruim ou peça similar barata; abaixo 1% = sub-aplicação).

### D4 — Pricing mão-de-obra: **HORA × HORA-TEMPÁRIO** (não preço fechado)

2 opções:

| Opção | Pros | Cons |
|---|---|---|
| Hora-tempário (US-OFICINA-013) | Padrão mercado BR (Sindirepa); dono define valor_hora por mecânico/categoria; flexível desvio justificado | Requer tabela seed mantida; dono precisa entender hora-tempário (curva learning) |
| Preço fechado por serviço | Simples atendente cotar (R$ [redacted Tier 0] alinhamento independente de quanto demora) | Não captura serviço complexo realista; mecânico bom rola prejuízo |

**Decisão proposta:** **hora-tempário como default, com override "preço fechado" por OS** (atendente pode digitar valor manual ignorando cálculo). Suporta os 2 modelos (Vargas usa hora-tempário; oficina pequena pode preferir preço fechado).

Tabela `oa_temparios` armazena tempo padrão (decimal horas). Cálculo OS: `mao_obra = tempo_horas × valor_hora_categoria_mecanico`. Override `oa_servicos_executados.valor_servico` ignora cálculo se preenchido manual.

### D5 — App campo mecânico: **PWA, NÃO nativo iOS/Android**

3 opções:

| Opção | Pros | Cons |
|---|---|---|
| Nativo (Flutter/React Native) | UX premium; push reliability; offline robusto; câmera nativa | 2 codebases iOS+Android; release store friction; ~80h dev cada plataforma |
| **PWA (manifest + service worker)** | 1 codebase (Inertia/React reusa); install promotível; offline gracioso; push via Centrifugo | UX 90% nativo (não 100%); iOS Safari quirks históricos |
| Web responsive sem install | Zero friction usar | Sem push real; offline ruim; UX feel não-app |

**Decisão proposta:** **PWA**. Wagner já tem Centrifugo CT100 + Tailwind 4 responsive + Inertia React 19 — adicionar manifest + service worker offline-first ~16h (US-OFICINA-015 V0). Reusa 100% componentes Page Inertia desktop. Push notifications via Web Push API (Centrifugo broker).

iOS Safari quirks aceitáveis pra V0 (Wagner pode testar Vargas Android primeiro; iOS depois). Se conversão Vargas <50% mecânicos instalam PWA, reavaliar Fase 5+ Flutter.

### D6 — Integração peças (catálogo OEM Bosch/Nakata/Fras-le): **DEFER P2**

Modules/Autopecas (catálogo OEM) é US-OFICINA-008 = P1 no SPEC original mas reavaliando: **deferir pra fase 5** (pós-piloto pagante). Razão:
- Vargas usa banda de rodagem (não peça OEM convencional) — diferente catálogo
- Martinho usa caçambas estacionárias (peças do caminhão de transporte, não da caçamba)
- Catálogo OEM exige parceria ou scraping (custo legal + manutenção)
- ROI baixo pré-piloto

Quando ativar: parceria Bosch/Nakata oficial (cobrável Pro tier) OU scraping com fair-use limitado.

## Consequências previstas

### Positivas

1. **OficinaAuto fica governance-aligned** — wire-up FSM canon reusa LIVE prod testado (40+ PRs ADR 0143 em ~10h); zero re-invenção
2. **Vargas + Martinho cobertos com mesma fundação** — schema multi-placa + multi-defeitos + garantia granular suporta ambos sub-tipos
3. **Reuso UI cross-módulo** — `FsmActionPanel.tsx` (Sells + Repair) ganha 3º cliente (OficinaAuto); custo nano
4. **PWA mecânico desbloqueia diferencial competitivo real** — 2 de N concorrentes BR têm mobile real (Oficina Integrada Android, Manager Full web mobile)
5. **Garantia per-item ataca pain-point setor** — disputa "tinha garantia ou não?" é dor #3 oficinas (CINAU pesquisa)

### Negativas / Trade-offs

1. **Migration rename V0 → V1** (`vehicles` → `oa_vehicles`) custa ~30min agora vs ~3h depois — mas é mudança breaking pra qualquer Pest/Controller no namespace dev pessoal de Felipe/Maiara que possa estar editando paralelo
2. **15 stages FSM (vs 13 Repair, 11 Sells)** — mais 1 processo seed por business; bundle FSM crescendo. Mitigação: scaffold seeder via cópia Repair + edição estrutural ~3h
3. **PWA iOS Safari pode dar problema** — sem garantia push reliability iOS; mitigação: testar Vargas Android primeiro, iOS opcional V1
4. **NFSe split (US-OFICINA-018) depende driver NFSe que NÃO existe** — bloqueia OS recapagem fiscal-completa até 1 município driver verde
5. **Cleanup tools US-OFICINA-005 (já no SPEC)** — prioridade P0 emergente que pode comer capacidade pra US-OFICINA-006..010 FSM. Trade-off: cleanup gera receita imediata (R$ [redacted Tier 0]k+R$ [redacted Tier 0]/m piloto) mas atrasa fundação canônica
6. **App PWA fase 4 pode nunca acontecer** se piloto Vargas/Martinho fechar contrato R$ [redacted Tier 0]/m mas não exigir mobile (mecânicos chefão usa laptop)

## Alternativas avaliadas e rejeitadas

- **Reusar Repair FSM direto (caminho 1)** — perde isolamento vertical, vocabulário errado
- **State machine ad-hoc OficinaAuto-only** — ignorar ADR 0143 → viola §5 SoC brutal + Tier 0 governance
- **Manter `vehicles` (não renomear)** — drift custo futuro maior
- **Garantia OS-todo** — perde rastreio peça-específica (Vargas mix prazos)
- **Mobile nativo Flutter primeiro** — over-engineering V0 sem piloto validado
- **Catálogo OEM Bosch parceria primeiro** — ROI baixo pré-piloto pagante

## Multi-tenant Tier 0 amarração (ADR 0093)

- ✅ Todas tabelas novas (`oa_pecas_utilizadas`, `oa_servicos_executados`, `oa_garantias`, `oa_temparios`, futuras `oa_apontamentos`, `oa_cotacoes`) com `business_id` indexado + FK CASCADE
- ✅ Models adotam `HasBusinessScope` global scope
- ✅ Roles Spatie per-business suffix `#{biz}` (sempre)
- ✅ Jobs assíncronos (`EmitirNfceJob`, `EmitirNfeJob`, `EmitirNfseJob`, `CalcularComissaoJob`, `LembreteGarantiaJob`) recebem `$businessId` constructor
- ✅ Endpoints `OficinaAutoFsmActionController` scoped via `session('user.business_id')` — 404 silencioso anti-info-leak
- ⛔ `withoutGlobalScopes` apenas em superadmin OU importer command com comentário `// SUPERADMIN: importer Vargas legacy`

## Plano de adoção (se aprovado)

### Fase 1 — Fundação canônica (3 semanas IA-pair, ADR 0106 fator 10x)

- US-OFICINA-006 (FSM wire-up, 6h)
- D2 rename migration (`vehicles` → `oa_vehicles`) (1h)
- US-OFICINA-008 (schema garantia granular, 5h)
- US-OFICINA-009 (defeitos múltiplos JSON, 3h)
- US-OFICINA-010 (stages teste_estrada + ajuste_final + loop, 4h)
- US-OFICINA-011 (re-orçamento escalada, 4h)
- US-OFICINA-002 (importer Martinho 91 veículos, 4h)
- US-OFICINA-007 (importer Vargas multi-placa, 8h)

Subtotal: ~35h codáveis × 2 margem = **70h Felipe = ~3 semanas**.

### Fase 2 — Diferenciais competitivos (4 semanas)

- US-OFICINA-014 (aprovação WhatsApp link público, 7h)
- US-OFICINA-012 (CRLV consulta, 6h)
- US-OFICINA-013 (tempário seed 100 serviços, 5h)
- US-OFICINA-017 (histórico veículo timeline, 4h)
- US-OFICINA-005 (cleanup tools cliente legacy, 12h — já listado)

Subtotal: ~34h × 2 = **68h = ~3 semanas**.

### Fase 3 — App campo + comissão (3 semanas + wallclock SEFAZ)

- US-OFICINA-015 (PWA mecânico V0, 16h)
- US-OFICINA-019 (comissão, 8h)
- US-OFICINA-016 (lembrete garantia, 3h)
- US-OFICINA-021 (FIPE integração, 4h)
- US-OFICINA-018 (NFSe split — wallclock driver NFSe externo)

Subtotal: ~31h × 2 = **62h = ~3 semanas** + waitwall NFSe driver.

### Critério de validação Fase 1 → Fase 2

- [ ] Pest 100% verde stages canônicos OficinaAuto biz=1
- [ ] Smoke biz=4 (ROTA LIVRE) sem regressão — usar veículos fake já que Larissa não opera oficina
- [ ] Martinho importado: 91 veículos migrados, 100% placas válidas
- [ ] Vargas importado: 1.064 veículos, 216 com PLACA2 (cavalo+reboque) corretos
- [ ] 1 OS criada UI nova ponta-a-ponta (recebido → entregue) com FSM transitions audit log completo

## Refs

- **ADR mãe**: [0137](../../0137-modules-oficinaauto-qualificada.md) — OficinaAuto qualificada
- **ADR FSM canon**: [0143](../../0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — pipeline LIVE prod
- **ADR modular vertical**: [0121](../../0121-oimpresso-modular-especializado-por-vertical.md)
- **ADR cliente sinal**: [0105](../../0105-cliente-como-sinal-guiar-sem-mandar.md)
- **ADR estimates**: [0106](../../0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- **SPEC OficinaAuto**: [memory/requisitos/OficinaAuto/SPEC.md](../../../requisitos/OficinaAuto/SPEC.md) — §14-§18 novas seções
- **SPEC Repair FSM wireup**: [memory/requisitos/Repair/SPEC-FSM-WIREUP.md](../../../requisitos/Repair/SPEC-FSM-WIREUP.md) — pattern paralelo
- **Lições críticas legacy**: [memory/research/clientes-legacy-officeimpresso/_LICOES-CRITICAS.md](../../../research/clientes-legacy-officeimpresso/_LICOES-CRITICAS.md) — Vargas + Martinho perfis corretos

## Aprovação

**Status atual:** `proposed`. Aguarda revisão Wagner [W]:

1. ⏳ Sign-off D1 (caminho 2 FSM wire-up via módulo próprio + reuso engine)
2. ⏳ Sign-off D2 (rename `vehicles` → `oa_vehicles` agora vs depois)
3. ⏳ Sign-off D3 (garantia per-item vs OS-todo)
4. ⏳ Sign-off D4 (hora-tempário default com override preço fechado)
5. ⏳ Sign-off D5 (PWA vs nativo)
6. ⏳ Sign-off D6 (defer catálogo OEM peças P2 → fase 5)
7. ⏳ Plano fase 1-3 priorização US

**SE aprovado:** mover este arquivo de `proposals/drafts/` pra `memory/decisions/NNNN-oficina-auto-modulo-canonico-fsm-wireup.md` (próximo número canônico), status `accepted`, mergear em PR único + criar batch de tasks-create no MCP.

**SE rejeitado/parcial:** Wagner anota emendas inline (`> COMENTÁRIO W: alternar X`) e fica `proposed` até converger.

---

**Última atualização:** 2026-05-12 — proposta inicial draft.
