# _ANCHOR-REVIEW-QUEUE — fila A6 de anchoring (decisões humanas pendentes)

> **O que é:** fila humana do backfill de anchors SPEC↔código (P10/SA-A5/A6 do roadmap SDD — [P10](_Governanca/roadmap/P10-sa-a5-a6-batches-ia-fila-wagner.md) · [ADR 0273](../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) · [protocolo G5](Governance/PROTOCOLO-REFUTADOR-BACKFILL.md)). US ambígua (evidência conflitante) fica **SEM campo** até decisão aqui — nunca inventar anchor.
> **Formato de item ambíguo** (motor `sdd-fase-2.js`): módulo · US · candidatos · evidência conflitante · decisão pendente.
> **Como decidir:** preencher a coluna "Decisão Wagner" (`anchor <path>` / `_pendente_` / outra) — batches de ~20, ~30min.

## §1 — Taxa de ambiguidade publicada (gatilho §103 do P10)

| Lote | Módulo(s) | US processadas | Ambíguas | Taxa |
|---|---|---:|---:|---:|
| batch 1 (#3483, sessão anterior) | Sells | 30 de 47 | ~2 | ~6% |
| SA-A5-P10-financeiro (#3539) | Financeiro | 38 | 0 | 0% |
| SA-A5-P10-whatsapp (#3546) | Whatsapp | 72 | 0 | 0% |
| SA-A5-P10-jana (#3543) | Jana | 68 | 0 | 0% |
| SA-A5-P10-oficinaauto (#3541) | OficinaAuto | 48 | 0 | 0% |
| **wave 2** (#3571-3577, #3580) | Pcp · PG · Fiscal · Compras · Sells-completion · Crm · NfeBrasil · RecurringBilling | 147 | 0 | 0% |
| **wave 3 lote B** (#3627, #3628, #3638) | NFSe · Autopecas · ComunicacaoVisual | 48 | 0 | 0% |
| **wave 3 — charters** (#3633-3636) | Fiscal · Cliente · KB · ProjectMgmt · Compras · RecurringBilling · Auditoria · Admin · Infra · TeamMcp · Superadmin | 66 joins (25 charters editados + 15 deferidos §3-bis) | 1 | 1,5% |

**Universo 16 módulos reais · taxa agregada <1% ≪ gatilho de 20-25%** (kill-criteria §103 NÃO disparou em nenhuma wave). A fila de US ambíguas está **vazia** — o DoD 3 do P10 é satisfeito por esta prova, não por itens. _Nota de qualidade (≠ ambiguidade): a refutação Fable reprovou 6 de 15 lotes na rodada 1 (Financeiro 7,5% · OficinaAuto 3,7% · Compras 8,3% · Crm 4,5% · NfeBrasil 4,8% · RecurringBilling 30,9% · ComunicacaoVisual 11,1%) — todos corrigidos e re-aprovados a 0%; reprovados registrados no ledger (§6 do protocolo)._

> **Inventory NÃO ancorado (lote B):** o SPEC de Inventory (25 US, 0% coverage) é **FUNDIR** na [`_TRIAGEM-IDENTIDADE-2026-06.md`](_TRIAGEM-IDENTIDADE-2026-06.md) (repartir os 29 docs → Produto/Compras/Estoque, cluster P6/P7 ADIADO). Backfillar âncora num SPEC que vai ser repartido = retrabalho — o motor `sdd-fase-2.js` proíbe tocar pastas FUNDIR/MATAR (aguardam trilha E gated no Wagner). Fica de fora do P10 até a decisão de identidade aterrissar.

## §2 — US ambíguas aguardando decisão

_(vazio — nenhum lote da wave 1 produziu ambíguas; itens futuros entram aqui no formato do motor)_

| Módulo | US | Candidatos | Evidência conflitante | Decisão Wagner |
|---|---|---|---|---|
| — | — | — | — | — |

## §3 — Telas órfãs de spec (charter sem US pra joinar — NÃO inventar slug)

Detectadas nos lotes de `related_us`; cada uma precisa de decisão: criar US no SPEC dono, joinar em US existente de outro módulo, ou aceitar órfã.

| Charter | Situação | Decisão Wagner |
|---|---|---|
| `Pages/Financeiro/ContasBancarias/Index.charter.md` | tela serve config de boleto (ConfigurarBoletoSheet) mais que o create-form da US-FIN-008 (que está `_pendente_`) | |
| `Pages/Financeiro/Extrato/Index.charter.md` | story dona é US-RB-046 (SPEC do RecurringBilling) — join cross-module permitido? | |
| `Pages/Financeiro/Impostos/Index.charter.md` | tela F2 sem US-FIN numerada no SPEC (só rota `/impostos`) | |
| `Pages/Financeiro/AssinaturaAtualizar.charter.md` | rota FIN-004 legacy, sem US-FIN-NNN correspondente | |
| `Pages/Jana/Admin/Governanca/Index.charter.md` | só referencia MEM-MCP-1.e — sem US-COPI confiável | |
| `Pages/Jana/Memoria.charter.md` | drift pré-existente: `related_specs` cita MEM-* que vivem em comparativo/ENTERPRISE, não no SPEC.md | |

## §3-bis — related_us DEFERIDOS pelo gate charter-live-signal (prontos pra aterrissar)

> O gate required `charter status:live precisa de sinal de prod` (no-new-lie) barra tocar charter `status: live` sem sinal (component em `governance/prod-flags.json` live OU campo `smoke:` datado). Nenhuma das 28 telas abaixo tem sinal hoje — os joins foram **verificados pelo refutador Fable** e ficam aqui prontos; aterrissam num PR trivial assim que a tela ganhar smoke datado (skill `tela-smoke`) ou entry no prod-flags. NÃO copiar sem o sinal.

| Charter (status: live) | related_us verificado | Sinal necessário |
|---|---|---|
| `Pages/Financeiro/Conciliacao/Index.charter.md` | `[US-FIN-009]` | smoke ou prod-flags `Financeiro/Conciliacao/Index` |
| `Pages/Jana/Chat.charter.md` | `[US-COPI-001, US-COPI-002, US-COPI-003, US-COPI-004, US-COPI-005, US-COPI-105]` (106 REMOVIDA — refutador: pertence ao Painel) | smoke ou prod-flags `Jana/Chat` |
| `Pages/Jana/Dashboard.charter.md` | `[US-COPI-050, US-COPI-010, US-COPI-011, US-COPI-012]` | smoke ou prod-flags `Jana/Dashboard` |
| `Pages/Jana/Pro.charter.md` | `[US-COPI-118, US-COPI-119]` | smoke ou prod-flags `Jana/Pro` |
| `Pages/Jana/Memoria.charter.md` | `[US-COPI-MEM-005, US-COPI-MEM-008, US-COPI-MEM-012]` (⚠️ MEM-* vivem fora do SPEC — ver §3) | smoke ou prod-flags `Jana/Memoria` |
| `Pages/Jana/Admin/Roadmap.charter.md` | `[US-COPI-111]` | smoke ou prod-flags `Jana/Admin/Roadmap` |
| `Pages/Jana/Painel.charter.md` | `[US-JANA-PAINEL-001, US-COPI-106]` (106 MOVIDA do Chat — correção do refutador) | smoke ou prod-flags `Jana/Painel` |
| `Pages/Whatsapp/Settings.charter.md` | `[US-WA-001, US-WA-014]` (PR #3547 fechado por isso) | smoke ou prod-flags `Whatsapp/Settings` |
| `Pages/OficinaAuto/ServiceOrders/Create.charter.md` | `[US-OFICINA-001, US-OFICINA-038, US-OFICINA-039]` | smoke ou prod-flags `OficinaAuto/ServiceOrders/Create` |
| `Pages/OficinaAuto/ServiceOrders/Edit.charter.md` | `[US-OFICINA-001]` (005 REMOVIDA — refutador: '005-bis'≠005) | smoke ou prod-flags `OficinaAuto/ServiceOrders/Edit` |
| `Pages/OficinaAuto/Vehicles/Create.charter.md` | `[US-OFICINA-001, US-OFICINA-012]` | smoke ou prod-flags `OficinaAuto/Vehicles/Create` |
| `Pages/OficinaAuto/Vehicles/Edit.charter.md` | `[US-OFICINA-001]` | smoke ou prod-flags `OficinaAuto/Vehicles/Edit` |
| `Pages/OficinaAuto/Vehicles/Index.charter.md` | `[US-OFICINA-001, US-OFICINA-002]` | smoke ou prod-flags `OficinaAuto/Vehicles/Index` |
| `Pages/Atendimento/CaixaUnificada/Index.charter.md` | `[US-WA-012, US-WA-055, US-WA-066, US-WA-095]` | smoke ou prod-flags `Atendimento/CaixaUnificada/Index` |
| `Pages/Atendimento/JanaTemplates.charter.md` | `[US-WA-070]` | smoke ou prod-flags `Atendimento/JanaTemplates` |
| `Pages/Atendimento/Macros/Index.charter.md` | `[US-WA-048]` | smoke ou prod-flags `Atendimento/Macros/Index` |
| `Pages/Atendimento/Metricas/Index.charter.md` | `[US-WA-041]` | smoke ou prod-flags `Atendimento/Metricas/Index` |
| `Pages/ProjectMgmt/Board/Index.charter.md` | `[US-TR-307]` (TR-308 é topicamente da tela mas está `todo`/gap — só listar quando entregue) | smoke ou prod-flags `ProjectMgmt/Board/Index` |
| `Pages/RecurringBilling/Faturas/Index.charter.md` | `[US-RB-042]` (RECURRINGBILLING-007 é duplicata declarada da 042 — refutador: não listar as duas) | smoke ou prod-flags `RecurringBilling/Faturas/Index` |
| `Pages/RecurringBilling/Planos/Index.charter.md` | `[US-RB-001]` | smoke ou prod-flags `RecurringBilling/Planos/Index` |
| `Pages/RecurringBilling/Configuracoes/Index.charter.md` | `[US-RB-010]` (anchor cita ConfiguracoesController → `Inertia::render('RecurringBilling/Configuracoes/Index')`) | smoke ou prod-flags `RecurringBilling/Configuracoes/Index` |
| `Pages/Sells/Create.charter.md` | `[US-SELL-001, US-SELL-003, US-SELL-004, US-SELL-005, US-SELL-006, US-SELL-007, US-SELL-053]` | smoke ou prod-flags `Sells/Create` |
| `Pages/governance/Dashboard.charter.md` | `[US-GOV-001, US-COPI-095, US-COPI-098]` (GOV-001 via render; COPI-095/098 via anchor Jana) | smoke ou prod-flags `governance/Dashboard` |
| `Pages/governance/Policies.charter.md` | `[US-GOV-002]` (via `Inertia::render('governance/Policies')`) | smoke ou prod-flags `governance/Policies` |
| `Pages/governance/Audit.charter.md` | `[US-GOV-003]` (via `Inertia::render('governance/Audit')`) | smoke ou prod-flags `governance/Audit` |
| `Pages/governance/DriftAlerts.charter.md` | `[US-GOV-004]` (via `Inertia::render('governance/DriftAlerts')`) | smoke ou prod-flags `governance/DriftAlerts` |
| `Pages/governance/ModuleGrades/Index.charter.md` | `[US-GOV-006]` | smoke ou prod-flags `governance/ModuleGrades/Index` |
| `Pages/governance/ModuleGrades/Show.charter.md` | `[US-GOV-007]` | smoke ou prod-flags `governance/ModuleGrades/Show` |

Aterrissaram direto (sem gate — status draft/deprecated/live-ok): Financeiro/Advisor/Login (#3540) · Jana/Cockpit (#3544) · OficinaAuto/Vehicles/Show (#3542) · **wave 3 (#3633-3636):** Fiscal ×7 · Cliente ×3 · KB ×4 · ProjectMgmt Inbox+Triage · Purchase/Create · RecurringBilling/Index · Auditoria/Index · Admin Index+FeatureFlags×2 · team-mcp/Team/Index · superadmin/Usuario360 ×2 (25 charters).

## §4 — Pendências do batch 1 (Sells, #3483 — sessão anterior)

| # | Pendência | Proposta | Decisão Wagner |
|---|---|---|---|
| 1 | #3483 mergeou **sem entry no ledger** ("entry gate pendente trio" no título) e com refutador na MESMA sessão do consolidador — forma fraca vs protocolo §2.2/§6 | re-refutar Sells com tier superior (Fable) em sessão fresca + entry retroativa honesta (nova entry, append-only) | |
| 2 | ~~Sells segue com 17 US `sem_campo`~~ **✅ FECHADO 2026-07-01** pelo lote wave 2 [#3575](https://github.com/wagnerra23/oimpresso.com/pull/3575) (refutador Fable 28/28 aprovado; stashes da `claude/p10-batch1-sells` IGNORADOS — trabalho refeito da evidência; stashes podem ser dropados) | — | ✅ |

## §4-bis — Pendências de produto/higiene surgidas na wave 2 (advisories dos refutadores — não-bloqueantes)

| # | Item | Origem | Decisão Wagner |
|---|---|---|---|
| 1 | Sells US-018/021: deep-link `?date_field=` vale só pro endpoint JSON, não pra URL do browser (zero pushState); tooltip '6 datas' do sketch não existe — itens de "Escopo (a especificar)", done defensável | refutação #3575 | |
| 2 | Sells US-008: nota "11 invariantes" stale (arquivo tem 15 `it()`) — higiene | refutação #3575 | |
| 3 | RecurringBilling US-RB-012: candidata a `_parcial_` em manutenção futura (DoD pede HMAC literal, implementação é shared-secret equivalente; eventos refunded/subscription.canceled não mapeados no ProcessAsaasWebhookJob) | refutação #3580 | |
| 4 | Crm §0 do SPEC: discovery stale desde 2026-05-07 ("conversa fica órfã, sem contact_id" — o linker US-WA-078 já popula) — emendar §0 | refutação #3576 | |
| 5 | PaymentGateway PG-001/002/005: 3 conflitos doneness LEGADOS grandfathered (status aberto × âncora viva) — triagem junto com §5 | consolidação #3572 | |
| 6 | Fiscal US-015: DoD '[x] 2-50 chars' stale vs min:3 do código; US-020: checkbox órfão de migration que existe | refutação #3573 | |

## §5 — Dívida entry-gate: US implementada sem aceite/teste (triagem em 3 baldes — PROPOSTA)

> **Regra dura (proibições §5):** teste sem âncora de contrato = tautológico = REJEITADO. Nenhum teste é fabricado pelo P10 — cada US abaixo recebe UMA decisão: **(A)** task MCP de teste real ancorado em contrato (SPEC/ADR/charter/casos) · **(B)** status-truth: rebaixar o anchor pra `_parcial_`/`_pendente_` · **(C)** `_lacuna_` na linha `**Testado em:**` + task backlog (convenção Financeiro/NfeBrasil 2026-06-23). Aceites (`req_sem_aceite`): IA rascunha do contrato, Wagner aprova.
> **Fonte única da lista viva:** `node scripts/governance/anchor-lint.mjs` (linhas 📋/🚪). Snapshot pré-wave-1: 27 sem-aceite + 45 sem-teste. **Pós-wave-1 o universo cresceu para 121/187** — ancorar não criou a dívida, tornou-a visível; a triagem abaixo cobre o snapshot original, o incremento novo segue a mesma régua por módulo.

| Módulo | US (snapshot pré-wave-1) | Balde proposto | Racional | Decisão Wagner |
|---|---|---|---|---|
| RecurringBilling | US-RB-044 (sem teste) | **A** — teste real | Tier-0 dinheiro (NFe-de-boleto-pago) | |
| NfeBrasil | US-NFE-049/050/051/052/060/061 (sem teste) | **A** — teste real | Tier-0 fiscal | |
| Financeiro | US-FIN-001/004/009 (sem teste) | **A** — teste real | Tier-0 dinheiro | |
| Vestuario | US-VEST-001..009 (sem aceite E sem teste) | **A** — aceite rascunhado por IA + teste real | cliente piloto ROTA LIVRE em produção | |
| KB | US-KB-001..006 (sem aceite E sem teste) | **B ou C** | módulo interno, risco baixo — rebaixar ou `_lacuna_` | |
| ProjectMgmt | US-TR-301..308 + 304/305/306/307/310/311 (sem aceite E sem teste) | **B ou C** | idem | |
| PaymentGateway | US-PG-001/002 (sem aceite E sem teste) + US-PG-005 (sem teste) | **A** — teste real | Tier-0 dinheiro (Onda 0 docs-only: validar se anchor não está otimista) | |
| Compras | US-COM-001/005 (sem aceite E sem teste) | **C** — `_lacuna_` + backlog | módulo em convergência C1 | |
| Governance | US-GOV-021 (sem aceite E sem teste) | **C** — `_lacuna_` + backlog | meta-módulo | |
| Fiscal | US-FISCAL-001 (sem teste) | **A** — teste real | Tier-0 fiscal | |
| Jana | US-COPI-107/108/109/111/112/113 (sem teste) | **C** — `_lacuna_` + backlog | features IA com eval próprio (RAGAS) no trilho 0318 | |
| Cliente (lane) | US-CRM-063..078: teste existe mas FORA das lanes de JUnit (verde impossível) | **infra** — incluir `tests/Feature/Cliente` + `tests/Feature/Contact` na `ci-sqlite-pest.list` (ou lane própria) | não é falta de teste; é lane | |
| PontoWr2 (lane) | US-PONT-001..012: `Modules/Ponto/Tests` fora das lanes | **infra** — lane `ponto-pest` ou entrada na lista | idem | |
| Sells (lane) | US-SELL-011..014/029..036: `tests/Feature/Domain/Fsm` fora das lanes | **infra** — incluir na `ci-sqlite-pest.list` | idem | |

## §6 — Pendências de arming (trilho floor/nightly — OUTRA sessão)

- `anchor_coverage` no scorecard: baseline `armed:false, valid_measurements:1/3` — deixar o cron diário produzir 3 medições consecutivas (~3 dias) e abrir PR no `governance/sdd-scorecard-baseline.json` (modelo = entry `full_suite_pass_rate`; `direction:up` ⇒ cada lote sobe o piso). NÃO tocar neste trilho a partir da campanha de conteúdo.
- Promoção `ledger-check` a `--enforce` + required: P10 passo 8, calendário ADR 0275 §5 (1/semana, ≥2 semanas advisory limpo) — pré-requisito P09 fechado.

---

**Evolução**
- 2026-07-02 — **wave 3 (charters `related_us`)** fechada: backfill do join US→tela nos charters que ainda não tinham o campo (cobertura de charter 30/158 → 55/158). 25 charters editados (PRs #3633 Fiscal · #3634 Cliente+KB · #3635 Gestão · #3636 Admin+MCP) + 15 joins deferidos pra §3-bis (charters `status:live` sem sinal). Refutador Fable 5 (G5, sessão fresca): 66 joins verificados, **1 refutado** (`Purchase/Edit → US-COM-004` — US de infra de deprecação, não descreve a tela; removido antes do merge), error_rate 1,5% < 2% → aprovado. Órfãs (charter sem US identificável, ficam sem campo): ~62 charters — Produto ×8 (sem SPEC), Repair ×8 (SPEC placeholder TODO), Sells ×6 (Show/Edit/Drafts/Quotations/Subscriptions/Caixa não citadas), team-mcp ×4, Cliente Map/Import/Ledger (só listadas na tabela de Pages, sem US própria), TransactionPayment ×3 (sem SPEC), Stock* ×4, Suporte ×2, ComVis/Manufacturing/Orcamento/Settings/User/governance-DsRollout ×1 cada, Admin GovernanceV4/RagQuality/ScreenReview. [CC]
- 2026-07-01 (noite) — wave 2 fechada (Pcp/PG/Fiscal/Compras/Sells-completion/Crm/NfeBrasil/RecurringBilling — 147 US, 0 ambíguas, 5 lotes reprovados r1 e re-aprovados; coverage global 42,6%→59,8%; PRs #3571-#3580). §1 atualizado, §4.2 fechado, §4-bis criado (6 advisories de produto). [CC]
- 2026-07-01 — criado (P10 wave 1: Financeiro/Whatsapp/Jana/OficinaAuto — 226 US ancoradas, 0 ambíguas, refutador Fable tier superior, PRs #3539-#3547). [CC]
