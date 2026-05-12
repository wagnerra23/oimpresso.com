---
module: ComunicacaoVisual
doc_type: roadmap
status: draft
last_review: 2026-05-12
owner: [W]
metodologia: "Fases sequenciais com gate de sinal qualificado (ADR 0105). Estimates ADR 0106 fator 10x IA-pair recalibrados. Cada fase só ativa se sinal anterior validar (cliente pagante, métrica detectada, ou ROI > threshold)."
related_adrs: [0121, 0143, 0093, 0094, 0105, 0106, 0117, 0119, 0136]
piloto_confirmado: "Gold (CNAE 1813-0/01, R$ [redacted Tier 0]M GMV/ano) — perfil 04-gold-comvis"
piloto_seguinte_candidatos: "Extreme (gráfica industrial PCP), Zoom, Fixar, Mhundo, Produart (a confirmar vertical via snapshot)"
---

# Roadmap fases — Modules/ComunicacaoVisual

> 5 fases sequenciais com gate de sinal qualificado ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)). Cada fase só ativa se sinal anterior validar — sem cliente pagante = não escala features.
>
> **Estimates ADR 0106 recalibradas:** tarefas codáveis IA-pair (fator 10x); tarefas humano-limitadas (canary 7d, monitor 30d, smoke real, treinamento) mantém wallclock real.

---

## Fase 1 — V0 scaffold (sem sinal qualificado mas obrigatório pra cliente fechar)

> **Gate de saída:** `php artisan module:install ComunicacaoVisual` funciona em dev + Sidebar mostra módulo + Pest GUARD Tier 0 verde.
>
> **Justificativa "sem sinal":** scaffold é pré-requisito técnico inevitável; sem ele Wagner não pode mostrar o produto em demo de pré-vendas. ADR 0105 permite "fundação" — trava expansão (features), não scaffold.

**Duração estimada:** 2 semanas wallclock (24h IA-pair concentradas + buffer review Wagner)

### Entregas

| # | Entrega | US | Esforço | Owner | Status |
|---|---|---|--:|---|---|
| 1.1 | Module scaffold via skill `criar-modulo` (8 peças nWidart obrigatórias) | — | 4h | Claude | done (legacy 2026-05-10) |
| 1.2 | DataController hooks UltimatePOS (`user_permissions`, `modifyAdminMenu`, `superadmin_package`) | — | 2h | Claude | done (legacy 2026-05-10) |
| 1.3 | InstallController + 3 rotas Install (status, install, uninstall — RUNBOOK-criar-modulo) | — | 2h | Claude | done (legacy 2026-05-10) |
| 1.4 | Migrations 5 tabelas canon `cv_*` (substratos, acabamentos, instalacoes_catalogo, ordens_producao, instalacoes) — `cv_orcamentos` mapeia pra legacy `comvis_orcamentos` (não duplica) | — | 6h | Claude | done 2026-05-12 |
| 1.5 | Models Entities/* (Substrato, Acabamento, InstalacaoCatalogo, OrdemProducao, Instalacao) com BusinessIdScope + GuardsFsmTransitions trait | — | 4h | Claude | done 2026-05-12 |
| 1.6 | Seed processo FSM "OS Comunicação Visual" (16 stages + 30+ actions críticas + 10 roles per-business) — `FsmProcessoComunicacaoVisualSeeder` | — | 4h | Claude | done 2026-05-12 |
| 1.7 | Pest GUARD Tier 0 (`Tier0GuardTest.php`): multi-tenant scope biz=1 vs biz=99 + anti-hook charter (NÃO recálculo m² pós-NFe + NÃO disparar plotter auto + NÃO emitir fiscal auto + FSM UPDATE direto bloqueado + idempotência seeder) | — | 2h | Claude | done 2026-05-12 |
| 1.8 | Charter `.charter.md` ao lado de cada Page Inertia inicial (Index + OS detail) | — | 0h (já existe ComunicacaoVisual.charter.md módulo) | — | done |

**Sinal qualificado pra ativar Fase 2:**
- ✅ Wagner aprovou ADR `comunicacao-visual-modulo-canonico` (proposed → accepted)
- ✅ Snapshot financeiro Gold confirma vertical comvis (já feito 2026-05-11) ou Extreme/outro qualificou
- ✅ 1 dos 6 saudáveis sinaliza interesse formal pra piloto (Wagner cold-call confirma vontade pagar)

**Riscos críticos Fase 1:**
- 🟡 Pest worktree não roda (vendor não compartilhado — anti-pattern `_LICOES-CRITICAS.md` §heurística). Mitigação: smoke local após PR merge.
- 🟡 Conflito de migration naming `cv_*` vs convenção núcleo (Vestuario usa `vest_*`). Decisão: padronizar prefixo `cv_` (já alinhado).
- 🟢 Scaffold é mecânico — risco baixo se skill `criar-modulo` rodar limpo.

---

## Fase 2 — Piloto Gold (signal qualificado — 1ª cliente pagante)

> **Gate de saída:** 1 cliente real (Gold) rodando em prod cv_* + pagando módulo + canary 7d verde + Wagner aprova case interno.
>
> **Gate de entrada:** Fase 1 completa + Wagner cold-call Gold (CNPJ 03.348.254/0001-93 — confirmar identidade vs Mubisys post-mortem) ou Extreme se Gold já churned, recebe sinal formal "topamos piloto".

**Duração estimada:** 8 semanas wallclock (Q3/26 — set/26 conforme SPEC §7)

### Entregas

| # | Entrega | US | Esforço | Owner | Status |
|---|---|---|--:|---|---|
| 2.1 | **Service `OrcamentoCalculator`** (m² × substrato × acabamento × instalação) + Pest 6+ casos | US-COMVIS-001 | 8h | — | todo |
| 2.2 | UI Inertia `/comvis/orcamento/calcular` (form mobile-first + preview tempo real + WhatsApp 1-clique) | US-COMVIS-001 | 6h | — | todo |
| 2.3 | CRUD substratos + acabamentos + instalacoes_catalogo | US-COMVIS-002 | 6h | — | todo |
| 2.4 | Seed tabela tributária CNAE 1813 (NCMs 4911.10, 4911.99, 3919; CFOPs 5101/5102/5933; CSOSN 102/500) | US-COMVIS-006 | 3h | — | todo |
| 2.5 | **FSM CV ativada** em prod biz=gold (cadastra processo + roles per-business + smoke 1 OS end-to-end) | US-COMVIS-NEW-001 | 4h | — | todo |
| 2.6 | UI Kanban PCP reusa componente Repair `<KanbanBoard>` extraído pra `Components/shared/` | US-COMVIS-003 | 6h | — | todo |
| 2.7 | **Adapter NFe-de-boleto-pago** ComVis (US-RB-044 já entregue; adapter decide NFC-e vs NFe vs NFSe) | US-COMVIS-009 | 4h | — | todo |
| 2.8 | **Importer Firebird Gold** (clientes + produtos legacy → cv_substratos + cv_ordens abertas) | US-COMVIS-017 | 16h primeira + dry-run obrigatório | — | todo |
| 2.9 | Treinamento Gold (vídeo curto + WhatsApp suporte + 2 calls follow-up) | — | 16h wallclock (Maiara M) | — | todo |
| 2.10 | **Canary 7d** Gold rodando paralelo OfficeImpresso Delphi legacy | — | 7d wallclock | — | todo |
| 2.11 | **Cutover final** + monitor 30d ([Pattern 04 canary 30-7-1](../../dominios/_patterns/04-smoke-canary-30-7-1.md)) | — | 4h + 30d wallclock | — | todo |
| 2.12 | Case interno escrito `memory/sales/<data>/cases/gold.md` (D+90) | — | 4h | — | todo |

**Total Fase 2 IA-pair:** ~55h codável + ~37 dias wallclock humano-limitado (treinamento+canary+monitor)

**Sinal qualificado pra ativar Fase 3:**
- ✅ Gold pagante 90d em prod (não voltou pro Delphi — review_trigger ADR 0119 #4)
- ✅ NPS Gold ≥40 (Maiara survey D+30/60/90)
- ✅ ARR Gold ≥R$ [redacted Tier 0]k/ano (Pro R$ [redacted Tier 0] × 12 + setup, mínimo viável)
- ✅ 0 bug crítico aberto >7d
- ✅ Wagner aprova abrir outreach 2ª piloto

**Riscos críticos Fase 2:**
- 🔴 **Gold é o Gold post-mortem Mubisys?** Wagner valida identidade (registry "Gold" versão 1466 vs CNPJ Três Lagoas/MS) antes de qualquer outreach. Se sim → SKIP pra Extreme (R$ [redacted Tier 0]M GMV "EXTREMA LED").
- 🔴 **Drift schema Firebird Gold** — banco específico pode ter triggers/procedures customizadas. Mitigação: dry-run obrigatório + count/totals match ([Pattern 07](../../dominios/_patterns/07-three-mode-dry-run-local-prod.md)) antes cutover prod.
- 🟡 **PCP Kanban componente Repair extraível?** Verificar acoplamento atual `Modules/Repair/resources/js/_components/KanbanBoard.tsx` — pode exigir refactor pra `Components/shared/`. Spawn-off task se acoplamento alto.
- 🟡 **Gold tem 29k vendas EM PRODUÇÃO** = funil textual maduro. Mapping `VENDA.SITUACAO` → FSM stages exige decisão case-a-case (cada um dos 7 estados Gold → qual stage CV?).

---

## Fase 3 — Migração 6 saudáveis (rollout)

> **Gate de saída:** ≥3 clientes pagantes total (Gold + 2 a 4 dos demais — Extreme primeiro, depois Zoom/Fixar/Mhundo/Produart conforme snapshot + sinal).
>
> **Gate de entrada:** Fase 2 verde + Gold case público ou interno aprovado + Wagner valida lista priorizada.

**Duração estimada:** 16 semanas wallclock (Q4/26 — Q1/27 — set 1 piloto a cada 2 meses)

### Entregas

| # | Entrega | US | Esforço | Owner | Status |
|---|---|---|--:|---|---|
| 3.1 | **PLANO-MIGRACAO-6-SAUDAVEIS** executa rolling (1 piloto/mês após Gold) | — | (16h codável + 26h time/cliente conforme PLANO) | — | parcial — Vargas removido (autopeças) |
| 3.2 | **Extreme piloto** (gráfica industrial PCP — R$ [redacted Tier 0]M GMV) | — | 6h codável + 26h time | — | todo |
| 3.3 | **Sub-feature PCP gráfico industrial** (`aguardando_maquina` stage opcional via business flag) — Extreme-specific | US-COMVIS-NEW-002 | 8h | — | todo |
| 3.4 | **Zoom piloto** (R$ [redacted Tier 0]M GMV — versão Delphi 1474 mais nova) | — | 6h codável + 26h time | — | todo |
| 3.5 | **Fixar piloto** (R$ [redacted Tier 0]M GMV — mid-tier) | — | 6h codável + 26h time | — | todo |
| 3.6 | **Mhundo piloto** (R$ [redacted Tier 0]k GMV — pequeno) | — | 6h codável + 26h time | — | todo |
| 3.7 | **Produart piloto** (R$ [redacted Tier 0]k — "banco antigo" cuidado) — opcional se Wagner aprovar pacote agressivo | — | 6h codável + 26h time | — | todo (condicional) |

**Total Fase 3 IA-pair:** ~38h codável + ~5 clientes × 26h time = ~130h wallclock

**Sinal qualificado pra ativar Fase 4:**
- ✅ 3+ clientes pagantes Modules/ComunicacaoVisual em prod
- ✅ ARR módulo ≥ R$ [redacted Tier 0]k/ano (baseline ADR 0022)
- ✅ Pest cobertura ≥70% módulo + 100% anti-hooks
- ✅ 0 churn em <90d
- ✅ Métrica concreta detectou demanda P1 (ex: 1 cliente abriu ticket "preciso pós-cálculo orçado vs real" = sinal ativa #8)

**Riscos críticos Fase 3:**
- 🔴 **Vargas removido** (Wagner confirmou autopeças 2026-05-10 → Modules/OficinaAuto qualificada ADR 0137). PLANO-MIGRACAO original (Q3/26 Vargas piloto) DESATUALIZADO — Extreme passa a ser piloto 2.
- 🟡 **Capacidade Wagner [W]** — 3,7h/m × 4 pilotos = ~15h/mês discovery+decisão. Mitigação: delegar Felipe migração técnica + Maiara suporte L1.
- 🟡 **Concorrência Mubisys** — pós Gold post-mortem (Mubisys ganhou R$ [redacted Tier 0]k/m), pode retaliar com preço R$ [redacted Tier 0]/m. Mitigação: defender pelo diferencial NFe-automática + Jana, não preço.

---

## Fase 4 — Estado da arte (diferencial competitivo P1)

> **Gate de saída:** Features P1 da MATRIZ-ROI entregues + ARR módulo ≥R$ [redacted Tier 0]k/ano + 1 case público publicado.
>
> **Gate de entrada:** Fase 3 verde (≥3 clientes pagantes 90d sem churn) + sinal explícito de pelo menos 1 cliente pedindo feature P1 específica.

**Duração estimada:** 12 semanas wallclock (Q2/27 — Q3/27)

### Entregas (priorizadas por ROI score MATRIZ-ROI)

| # | Entrega | US | ROI score | Esforço | Owner | Status |
|---|---|---|--:|--:|---|---|
| 4.1 | **Pós-cálculo orçado vs realizado** (Calcgraf único entrega — wedge real) | US-COMVIS-005 | 1500 | 10h | — | todo |
| 4.2 | **Dual-doc fiscal NFe55 + NFSe56 simultâneo** (1 OS = 2 docs — reuso US-SELL-014) | US-COMVIS-NEW-003 | 1083 | 6h | — | todo |
| 4.3 | **Workflow arte WhatsApp aprovação cliente** (reuso ADR 0117 multi-números) | US-COMVIS-NEW-004 | 1062 | 8h | — | todo |
| 4.4 | **Apontamento plotter mobile + CMYK tracking** | US-COMVIS-004 | 875 | 8h | — | todo |
| 4.5 | **Comissão multi-papel JSON** (vendedor + designer + instalador) | US-COMVIS-011 | 700 | 5h | — | todo |
| 4.6 | **Wizard onboarding Jana detecta CNAE 1813** | US-COMVIS-NEW-005 | 500 | 3h | — | todo |
| 4.7 | **Gestão fachada/instalação NR-35 + agenda + GPS comprovação** | US-COMVIS-007 | 416 | 12h | — | todo |
| 4.8 | **NFSe automática driver 3 prefeituras** (Floripa/Gravatal/Goiânia conforme geografia clientes) | US-COMVIS-008 + 3× US-NFSE-CANCEL-XXX | 321 | 14h | — | todo |

**Total Fase 4 IA-pair:** ~66h codável + ~30d wallclock validação clientes

**Sinal qualificado pra ativar Fase 5:**
- ✅ 5+ clientes pagantes
- ✅ ARR módulo ≥R$ [redacted Tier 0]k/ano
- ✅ 1 case público publicado (vídeo 90s + autoriza menção em battle card)
- ✅ NPS módulo ≥45
- ✅ Pest cobertura ≥80% + 100% anti-hooks

**Riscos críticos Fase 4:**
- 🔴 **Driver NFSe 3 prefeituras simultaneo** = 14h cada × 3 = 42h se mal-coordenado. Mitigação: prioritizar 1 de cada vez baseado em sinal cliente.
- 🟡 **WhatsApp arte-aprovação LGPD** — `contact.whatsapp_consent` exige opt-in explícito. Fluxo onboarding cliente final deve coletar consent (não cliente piloto, mas tomador final da OS).
- 🟡 **Pós-cálculo precisa apontamento plotter** (US-COMVIS-004) preencher — sem 80% das OS apontadas, métrica vira ruído. Mitigação: enforcement gentle via Jana ("8 OS sem apontamento esta semana — quer relembrar operadores?").

---

## Fase 5 — Expansão (escala — TAM completo CNAE 1813-0/01)

> **Gate de saída:** 10+ clientes pagantes + ARR módulo ≥R$ [redacted Tier 0]k/ano + benchmark setor habilitado via Jana ("média margem comvis SC = X%") + status `maduro` ADR 0121.
>
> **Gate de entrada:** Fase 4 verde + Wagner valida tese vertical comvis vs investir noutro vertical (OficinaAuto, etc).

**Duração estimada:** 24+ semanas wallclock (Q4/27 — 2028)

### Entregas (P2/P3 conforme sinal)

| # | Entrega | US | Prioridade | Esforço | Condicional ativar |
|---|---|---|---|--:|---|
| 5.1 | **Bulk update material via Jana** | US-COMVIS-013 | P2 | 4h | 1 cliente pediu |
| 5.2 | **Dashboard "22h" Jana 3 ângulos faturamento** | US-COMVIS-014 | P2 | 6h | dor cliente noturno |
| 5.3 | **Cadastro máquina + CMYK alerta reposição** | US-COMVIS-015 | P2 | 8h | gráfica industrial parou ≥2x/mês |
| 5.4 | **Provador orçamento online público** | US-COMVIS-010 | P2 | 10h | gráfica reportou >5 lead/m via web |
| 5.5 | **DAM básico Wasabi/Minio S3 + Uppy chunked** | US-COMVIS-012 | P2 | 12h | 80MB WhatsApp incomoda |
| 5.6 | **Mobile-first PWA operador plotter** | — | P2 | 8h | operador celular reclama |
| 5.7 | **Loja whitelabel pública catálogo** | US-COMVIS-018 | P3 | 12h | 2+ gráficas pediram SEO |
| 5.8 | **CT-e/MDF-e entrega gráfica** | US-COMVIS-016 | P3 | 10h | SINIEF 2026 ativa obrigação |
| 5.9 | **Reforma Tributária IBS/CBS** | — | P3 | 4h | LC 214/2025 fase teste 2027 ativa |
| 5.10 | **Captura plotter via SNMP/SDK** (Roland/Mimaki/HP Latex) | — | P3 | 24h | cliente industrial valida ROI |
| 5.11 | **Benchmark setor (média margem comvis BR)** via Jana cross-tenant (anonimizado) | — | P3 | 16h | 10+ clientes ativos |
| 5.12 | **Plugin Corel/Illustrator** (preflight + auto-orçamento) | — | P3 | 40h | 2+ gráficas pediram |

**Total Fase 5 IA-pair:** ~75h codável condicional (só ativa o que sinal qualificou)

**Sinal qualificado pra promoção `ativo` → `maduro`:**
- ✅ 10+ clientes pagantes
- ✅ ARR módulo ≥R$ [redacted Tier 0]k/ano
- ✅ Benchmark setor habilitado (anonimizado cross-tenant)
- ✅ 2+ cases públicos publicados
- ✅ Churn <10%/ano

**Sinal pra `historical` (degradar):**
- ❌ <2 clientes ativos por 12m após Fase 4 → review trigger ADR 0121 ativa amend "aposentar módulo"
- ❌ Mubisys/Zênite reduzem preço 50% + ganham 3 dos nossos clientes em 6m → reavaliar tese

---

## Estimate total agregado

| Fase | Esforço IA-pair (h) | Wallclock humano-limitado | Risco | ARR esperado |
|---|--:|---:|---|--:|
| Fase 1 V0 | 24h | 2 sem | 🟢 baixo | R$ [redacted Tier 0] |
| Fase 2 Gold piloto | 55h | 8 sem | 🔴 alto (1ª piloto = make-or-break) | R$ [redacted Tier 0]k/ano |
| Fase 3 Rollout 6 saudáveis | 38h + 130h time | 16 sem | 🟡 médio (capacidade time) | +R$ [redacted Tier 0]-90k/ano |
| Fase 4 Diferencial P1 | 66h | 12 sem | 🟡 médio (driver NFSe per-município) | +R$ [redacted Tier 0]k/ano |
| Fase 5 Expansão P2/P3 | 75h condicional | 24+ sem | 🟢 baixo (gated por sinal) | +R$ [redacted Tier 0]-200k/ano |
| **Total M0-M24** | **~258h codável + ~62 sem wallclock** | | | **~R$ [redacted Tier 0]-410k ARR M24** |

## Gate de revisão (review_triggers ADR 0121)

- **M3 (set/26):** 1ª piloto não fechou em prod → atrasar Fase 3, reavaliar Fase 2 scope
- **M9 (mar/27):** <2 pagantes → revisar tese vertical comvis vs investir noutro (OficinaAuto, NfeBrasil B2C)
- **M12 (jun/27):** ARR <R$ [redacted Tier 0]k → considerar `historical` ADR 0121
- **M18 (dez/27):** Mubisys retaliando preço 50%+ + churn ≥30% → battle card revisada + pricing reposicionado
- **M24 (jun/28):** 10+ pagantes ≥80% retention → promover `maduro` + ativar benchmark setor

## Decisões pendentes pra Wagner

1. **Identidade Gold** — registry "Gold" v1466 vs Gold Comunicação Visual MS Mubisys post-mortem? Wagner valida CNPJ antes Fase 2.
2. **Extreme se Gold churned** — Fase 2 piloto vira Extreme? Wagner valida vertical "EXTREMA LED" via snapshot financeiro (a rodar).
3. **Capacidade Felipe [F]** — 80h migração 5 pilotos + scaffold 24h + Fase 4 66h = 170h. Sustentável vs Modules/Vestuario manutenção?
4. **Driver NFSe geografia** — Floripa first ou Gravatal (ROTA LIVRE biz=4 prox-pseudo-cliente CV)?

## Refs

- [SPEC.md](SPEC.md) — backlog US-COMVIS-001..018 + concorrentes
- [MATRIZ-ROI.md](MATRIZ-ROI.md) — features × ROI score (24 avaliadas)
- [ComunicacaoVisual.charter.md](ComunicacaoVisual.charter.md) — charter módulo
- [PLANO-MIGRACAO-6-SAUDAVEIS.md](PLANO-MIGRACAO-6-SAUDAVEIS.md) — plano migração detalhado (Vargas removido)
- [proposal ADR ComunicacaoVisual canônico](../../decisions/proposals/drafts/comunicacao-visual-modulo-canonico.md)
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — sinal qualificado (gating fases)
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — estimates 10x recalibrado
- [ADR 0119](../../decisions/0119-migration-factory-capacidade-institucional.md) — Migration Factory
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — modular especializado (review_triggers)
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM canon LIVE
