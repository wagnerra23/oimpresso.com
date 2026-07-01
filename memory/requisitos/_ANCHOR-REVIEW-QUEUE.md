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

**Universo ≥5 módulos reais · taxa agregada <1% ≪ gatilho de 20-25% → batches podem continuar** (kill-criteria §103 NÃO disparou). A fila de US ambíguas está **vazia** — o DoD 3 do P10 é satisfeito por esta prova, não por itens.

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

> O gate required `charter status:live precisa de sinal de prod` (no-new-lie) barra tocar charter `status: live` sem sinal (component em `governance/prod-flags.json` live OU campo `smoke:` datado). Nenhuma das 13 telas abaixo tem sinal hoje — os joins foram **verificados pelo refutador Fable** e ficam aqui prontos; aterrissam num PR trivial assim que a tela ganhar smoke datado (skill `tela-smoke`) ou entry no prod-flags. NÃO copiar sem o sinal.

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

Aterrissaram direto (sem gate — status draft): Financeiro/Advisor/Login (#3540) · Jana/Cockpit (#3544) · OficinaAuto/Vehicles/Show (#3542).

## §4 — Pendências do batch 1 (Sells, #3483 — sessão anterior)

| # | Pendência | Proposta | Decisão Wagner |
|---|---|---|---|
| 1 | #3483 mergeou **sem entry no ledger** ("entry gate pendente trio" no título) e com refutador na MESMA sessão do consolidador — forma fraca vs protocolo §2.2/§6 | re-refutar Sells com tier superior (Fable) em sessão fresca + entry retroativa honesta (nova entry, append-only) | |
| 2 | Sells segue com **17 US `sem_campo`** (lint vivo) apesar do claim "47→0" do commit — parte do trabalho ficou em stashes da branch `claude/p10-batch1-sells` (2 stashes WIP: status-truth 8 US + trio 12 US) | completar Sells no próximo lote (L-seq), aproveitando/descartando os stashes conscientemente | |

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
- 2026-07-01 — criado (P10 wave 1: Financeiro/Whatsapp/Jana/OficinaAuto — 226 US ancoradas, 0 ambíguas, refutador Fable tier superior, PRs #3539-#3547). [CC]
