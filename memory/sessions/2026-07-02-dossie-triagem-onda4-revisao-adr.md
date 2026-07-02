---
date: "2026-07-02"
hour: "10:49 BRT"
topic: "Onda 4 da máquina de revisão de ADR — DOSSIÊ de triagem 1×1 das filas Check R (24 revisões vencidas) + Check O (5 morta-mas-canon)"
authors: [C]
prs: []
related_adrs:
  - 0317-maquina-revisao-adr-quando-rever-gatilhos
  - 0257-adr-status-lifecycle-kind-modelo-canonico
  - 0297-excecao-append-only-migracao-legacy-frontmatter-adr
  - 0316-esquecimento-real-adr-morta-tombstone-git-auditoria
  - 0105-cliente-como-sinal-guiar-sem-mandar
---

# Dossiê Onda 4 — triagem humana 1×1 das filas da máquina de revisão

> **Invariante Tier 0 (ADR 0317 §4):** a máquina só detecta — **nenhum veredito deste dossiê é aplicado sem Wagner decidir item a item.** Vereditos aplicáveis mexem SÓ em frontmatter, sob labels de exceção (`adr-status-relabel` conforme 0257 / `adr-legacy-schema-migration` conforme 0297); **corpo NUNCA** (append-only). Evidência coletada por 5 agentes read-only em 2026-07-02 (pós-migração 0297 / PR #3595, que fez a fila R crescer de 16 → 24 ao normalizar `proposed`→`proposto`).

## Como ler os vereditos

| Veredito | O que significa | Como aplica (se Wagner aprovar) |
|---|---|---|
| **RATIFICAR-ACEITAR** | A proposta virou realidade implementada; o rótulo `proposto` mente | Relabel frontmatter `status: proposto → aceito` (PR label `adr-status-relabel`, corpo intacto) |
| **APOSENTAR** | Mundo mudou / nunca vai acontecer / coberta por posterior | Relabel `status → recusado` ou `lifecycle → arquivado` + `superseded_by` se houver herdeira |
| **EMENDAR** | Tema vivo mas a ADR precisa reescrita | **Nova ADR** com `supersedes[_partially]: [N]` (append-only — nunca editar a antiga) |
| **MANTER-PROPOSTA** | Genuinamente ainda em avaliação (ex.: feature-wish aguardando sinal ADR 0105) | `--update-baseline` no memory-health (grandfather = registro "revisei, segue de pé") |

---

## FILA R — revisão vencida por TTL (24 itens, todas `proposto`/`rascunho` >30d)

### Tabela executiva

| # | ADR | Recomendação | Conf. | Decisão Wagner |
|---|---|---|---|---|
| 1 | 0020 grupo-economico | **APOSENTAR** | alta | [ ] |
| 2 | 0068 retrieval-reranker | **RATIFICAR-ACEITAR** | alta | [ ] |
| 3 | 0072 maturacao-memoria | **EMENDAR** (ou grandfather como roadmap-mãe) | alta | [ ] |
| 4 | 0074 temporal-validity | **RATIFICAR-ACEITAR** | média | [ ] |
| 5 | 0076 skills-db-primary | **RATIFICAR-ACEITAR** | alta | [ ] |
| 6 | 0118 segregacao-dominios | **RATIFICAR-ACEITAR** | alta | [ ] |
| 7 | 0119 migration-factory | **RATIFICAR-ACEITAR** | alta | [ ] |
| 8 | 0122 admin-center-ct100 | **RATIFICAR-ACEITAR** | alta | [ ] |
| 9 | 0125 autopecas feature-wish | **MANTER-PROPOSTA** | alta | [ ] |
| 10 | 0126 mcp-jira-projects | **RATIFICAR-ACEITAR** | alta | [ ] |
| 11 | 0146 contact-lid feature-wish | **MANTER-PROPOSTA** | alta | [ ] |
| 12 | 0151 comissao feature-wish | **MANTER-PROPOSTA** | alta | [ ] |
| 13 | 0152 pcp feature-wish | **MANTER-PROPOSTA** | alta | [ ] |
| 14 | 0153 module-grade-v1 | **RATIFICAR-ACEITAR** | alta | [ ] |
| 15 | 0159 grade-v3-errata-realismo | **RATIFICAR-ACEITAR** | alta | [ ] |
| 16 | 0165 breakpoints mobile-first | **EMENDAR** | média | [ ] |
| 17 | 0168 protocolo-wagner-sempre | **RATIFICAR-ACEITAR** | alta | [ ] |
| 18 | 0169 errata-0168 runbook-onda | **RATIFICAR-ACEITAR** | alta | [ ] |
| 19 | 0170 paymentgateway | **RATIFICAR-ACEITAR** | alta | [ ] |
| 20 | 0193 nfe-sem-forcedelete | **RATIFICAR-ACEITAR** | alta | [ ] |
| 21 | 0230 governance-scorecard | **RATIFICAR-ACEITAR** | alta | [ ] |
| 22 | 0231 especialista-por-area | **MANTER-PROPOSTA** | média | [ ] |
| 23 | 0232 modelo-peso-real | **EMENDAR** | média | [ ] |
| 24 | 0233 ativacao-memoria | **RATIFICAR-ACEITAR** | alta | [ ] |

**Padrão macro:** 14 de 24 são "implementado mas o rótulo ficou `proposto`" (lapso administrativo, não drift de decisão) — o Check L (vivo-mas-proposto) tem interseção conceitual aqui. 5 são feature-wish dormentes CORRETAS sob ADR 0105 (gatilho de cliente não disparou). 3 pedem emenda. 1 aposenta. 1 mantém como roadmap.

### Item a item

**1 · 0020-officeimpresso-grupo-economico** (proposto 2026-04-24, 69d)
- **Decide:** grupos econômicos (matriz+filiais) via self-FK `matriz_id` em `business` + fallback de config.
- **Mundo hoje:** ZERO implementação — grep `matriz_id`/`effectiveMatriz()`/`filiais()` = 0 resultados em `.php`; nenhuma migration; nenhuma ADR posterior cobre o tema.
- **Recomendação:** APOSENTAR (`recusado` ou `arquivado`) — 10 semanas sem qualquer movimento; se a demanda voltar, reescreve com contexto atual.
- **Pergunta:** matriz/filial ainda é prioridade pro legado OfficeImpresso em 2026-H2?

**2 · 0068-sprint9-retrieval-ollama-reranker-strategy** (rascunho 2026-05-04, 59d)
- **Decide:** Sprint 9 retrieval — Ollama embedders (nomic/qwen3), reranker BGE v2-m3, fix documentTemplate.
- **Mundo hoje:** implementado e em prod — `Modules/KB/Services/KbBgeRerankerService.php` (wrapper de `Modules/Jana/Services/Retrieval/BgeReranker`), config qwen3_local, scores RAGAS tabelados na própria ADR.
- **Recomendação:** RATIFICAR-ACEITAR — rascunho por lapso; o código foi escrito.
- **Pergunta:** P2 (cross-encoder) segue roadmap ou o BGE slice-1 encerrou?

**3 · 0072-maturacao-memoria-team-mcp-openclaw-soa-2026** (proposto 2026-05-05, 58d)
- **Decide:** roadmap-mãe 4 movimentos (P0 skills-as-entities → P1 temporal validity → P2 score per-memory → P3 action-aware retrieval).
- **Mundo hoje:** P0 desdobrou em 0073→0075→0076 (implementada); P1 desdobrou em 0074, ratificada pela **0295 (aceita 2026-06-20)**; P2/P3 sem ADR filha. A mãe virou documento de contexto — as decisões vivem nas filhas.
- **Recomendação:** EMENDAR (nova ADR curta consolidando estado P0-P3 + veredito de P2/P3) OU, mais barato, MANTER-PROPOSTA como roadmap-mãe com grandfather.
- **Pergunta:** P2 (score per-memory) e P3 (action-aware) ainda estão vivos ou descartados? (Nota: 0232 modelo-peso-real tangencia P2.)

**4 · 0074-temporal-validity-bi-temporal-time-travel** (proposto 2026-05-05, 58d)
- **Decide:** bi-temporal em `jana_memoria_facts` (event-time + system-time) + detecção de supersedence + tool `memoria-historica`.
- **Mundo hoje:** slice 1 (schema) IMPLEMENTADO — migration `2026_06_20_000002_add_event_time_to_jana_memoria_facts.php` (`event_valid_from/until`, `supersedes_id`); ratificada pela ADR 0295 (aceita). Slices 2-3 (tool MCP, detecção Haiku) não confirmados.
- **Recomendação:** RATIFICAR-ACEITAR com nota "slices 2-3 em aberto — governados pela 0295".
- **Confiança média:** confirmar se a 0295 já formaliza a aceitação da 0074 (se sim, o relabel é só espelhar).

**5 · 0076-skills-db-primary-git-destino-drift-alert** (proposto 2026-05-05, 58d)
- **Decide:** Skills V2 — DB primary, git destino auditável, drift per-skill. Supersede 0073+0075 no próprio frontmatter.
- **Mundo hoje:** backend implementado — 5 migrations `create_mcp_skill*` de 2026-05-05 (skills, versions, labels, test_runs, approvals). UI: completude não verificada.
- **Recomendação:** RATIFICAR-ACEITAR (uma ADR `proposto` que SUPERSEDE duas outras é contradição de máquina — quem mata precisa estar viva).

**6 · 0118-segregacao-dominios-externos-clientes-legacy** (proposto 2026-05-09, 54d)
- **Decide:** `memory/dominios/<sistema>/` + `memory/clientes-legacy/<alias>.md`.
- **Mundo hoje:** implementado e VIVO — `memory/dominios/wr-comercial/` (~429 arquivos), `_patterns/` (7), `_template/`, `clientes-legacy/rota-livre.md`; ADR 0203 (aceita 2026-05-26) usa a estrutura no pipeline Firebird→Martinho.
- **Recomendação:** RATIFICAR-ACEITAR.

**7 · 0119-migration-factory-capacidade-institucional** (proposto 2026-05-09, 54d)
- **Decide:** Migration Factory Tier B em 4 componentes; §explícito "NÃO criar `Modules/MigrationFactory/` agora, só no 2º sistema externo".
- **Mundo hoje:** sendo executada conforme escrito — componente 1 feito (=0118), engine segue em `scripts/legacy-migration/` (respeitando o "não criar agora"), catálogo WR Comercial ativo (Martinho biz=164 importado), ADR 0203 valida end-to-end.
- **Recomendação:** RATIFICAR-ACEITAR — a decisão (inclusive o "ainda não") está em vigor; `proposto` não descreve isso.

**8 · 0122-admin-center-ct100** (proposto 2026-05-09, 54d)
- **Decide:** `Modules/Admin` Centro de Operações Wagner-only Tailscale-only no CT 100.
- **Mundo hoje:** implementado — 70+ arquivos, middlewares `IsWagner.php` + `TailscaleOnly.php`, migration `mcp_admin_audit_log`, 13 Pest (AuthGate/CrossTenant/MultiTenantPermission), dashboards v4 em expansão (Waves 25-27). Já citado como decisão vigente no `Modules/Governance/SCOPE.md` (`not_contains` referencia "ADR 0122 — separação intencional").
- **Recomendação:** RATIFICAR-ACEITAR.

**9 · 0125-modules-autopecas-feature-wish** (proposto/feature-wish 2026-05-10, 53d)
- **Decide:** vertical Autopecas dormente até Vargas assinar pioneer (gate ADR 0105).
- **Mundo hoje:** honrada à risca — SPEC/charter/plano antecipatórios existem, ZERO código, project AUTO no MCP marcado backlog. Vargas não assinou. Review-trigger de arquivar é "12 meses sem sinal" (só mai/2027).
- **Recomendação:** MANTER-PROPOSTA (grandfather). *Meta-nota:* o TTL 30d pra `proposto` atropela feature-wish cujo `kind` correto lhes daria 180d — ver "Ajustes na máquina" no fim.

**10 · 0126-mcp-jira-projects-modulos-verticais** (proposto 2026-05-10, 53d)
- **Decide:** 3 projects canônicos (COMVIS/VEST/AUTO) em `mcp_jira_projects` via migration idempotente + seeder.
- **Mundo hoje:** implementado — migration `2026_05_10_120000_seed_modulos_verticais_mcp_jira_projects.php` + `McpDefaultsSeeder` com os 3, rollback protegido. (Typo no header do arquivo: título diz "0125" — corrigível SÓ sob label de exceção se Wagner quiser, é frontmatter/título.)
- **Recomendação:** RATIFICAR-ACEITAR.

**11 · 0146-contact-lid-canonico-pk-refactor** (proposto/feature-wish 2026-05-15, 48d)
- **Decide:** promover `contact_lid` a chave canônica WhatsApp em 4 fases, aguardando sinal (2º incidente cross-contact / canary Cloud API / volume 5x / vertical novo).
- **Mundo hoje:** honrada — schema 3-identifiers mergeado (PRs #854-856: colunas `lid`/`phone_e164`/`bsuid`, `LidPhoneMap` + resolver testado), mas threading segue `customer_external_id` (refactor de chave NÃO começou = correto, o gatilho não disparou).
- **Recomendação:** MANTER-PROPOSTA (grandfather).

**12 · 0151-modules-comissao-feature-wish** (proposto/feature-wish 2026-05-15, 48d)
- **Decide:** Comissão dormente até cliente reportar dor real (5 gatilhos enumerados).
- **Mundo hoje:** honrada — SPEC 14 US pré-pago, zero código, Larissa segue com `commission_agent` legacy + planilha da Eliana.
- **Recomendação:** MANTER-PROPOSTA (grandfather).

**13 · 0152-modules-pcp-feature-wish** (proposto/feature-wish 2026-05-15, 48d)
- **Decide:** PCP (apontamento OPERATION-level) dormente até cliente com produção física demandar.
- **Mundo hoje:** honrada — SPEC 20 US, zero código, Kanban atual segue STAGE-level (`Modules/Repair/ProducaoOficinaController`).
- **Recomendação:** MANTER-PROPOSTA (grandfather).

**14 · 0153-module-grade-rubrica-v1** (proposto 2026-05-16, 47d)
- **Decide:** rubrica module-grade v1 (5 dimensões, 0-100).
- **Mundo hoje:** implementada E evoluída — `ModuleGradeService` + `module:grade`/`module:grade-v4`, migration histórico, UI `/governance/module-grades`, testes. Evolução v2/v3 (0154/0155 + erratas 0156-0158) TODAS aceitas — só a v1 fundadora ficou `proposto` (inversão absurda: as filhas aceitas, a mãe proposta).
- **Recomendação:** RATIFICAR-ACEITAR.

**15 · 0159-module-grade-v3-errata-meta-97-realismo** (proposto 2026-05-16, 47d)
- **Decide:** 4 relaxações na rubrica v3 (D5 internal_governance / D9.b ready-mode / D4.b N/A declarativo / D3.b CHANGELOG ≤7d).
- **Mundo hoje:** as 4 codificadas e testadas (`ModuleGradeServiceV3Adr0159RelaxationsTest.php`, `module_clients.yaml`, flags `module.json`), coleta daily.
- **Recomendação:** RATIFICAR-ACEITAR.

**16 · 0165-design-system-breakpoints-mobile-first-responsive** (proposto 2026-05-17, 46d)
- **Decide:** breakpoints Tailwind 4 nativos (sem custom) + mobile-first em todas as Pages + touch ≥44px + CI Playwright 3 viewports (375/1024/1440).
- **Mundo hoje:** PARCIAL — breakpoints nativos ok (zero custom drift), MAS 3 enforcements nunca nasceram: seção "Viewports validados" no charter (sem linter), skill `responsive-mobile-first` (não existe), CI 3-viewports (não confirmado). Pages seguem desktop-first na prática (monitor 1280px da Larissa é o alvo real).
- **Recomendação:** EMENDAR — nova ADR curta ou decidir aposentar a ambição mobile-first se o alvo real é 1280/1440.
- **Pergunta:** mobile-first ainda é meta (clientes usam celular?) ou o alvo é desktop 1280+ e a 0165 superdimensionou?

**17 · 0168-protocolo-wagner-sempre-tier-A-irrevogavel** (proposto 2026-05-17, 46d)
- **Decide:** PROTOCOLO-WAGNER-SEMPRE.md canon Tier 0 + skill enforce + agent wagner-understand + RUNBOOK.
- **Mundo hoje:** ESPINHA DORSAL VIVA do projeto — PROTOCOLO com R1-R11 + LEI-DE-UMA-TELA, citado como REGRA ZERO em `proibicoes.md` (Tier 0 IRREVOGÁVEL). ADR 0225 (aceita) recalibrou a skill de Tier A→B mantendo R1/R10 Tier 0. Uma regra chamada "IRREVOGÁVEL" com status `proposto` é a maior incoerência da fila.
- **Recomendação:** RATIFICAR-ACEITAR (com nota "skill recalibrada pela 0225").

**18 · 0169-errata-0168-runbook-onda-cowork-canon** (proposto 2026-05-17, 46d)
- **Decide:** RUNBOOK-onda-cowork.md como 4º artefato canônico (12 fases por Onda).
- **Mundo hoje:** operacional — Ondas 1-6 Sells + Compras/Financeiro/Oficina rodaram sob ele; feedback canon de transparência de gaps existe.
- **Recomendação:** RATIFICAR-ACEITAR.

**19 · 0170-paymentgateway-extracao-camada-cobranca** (proposto 2026-05-19, 44d)
- **Decide:** extrair camada de cobrança de RecurringBilling pra `Modules/PaymentGateway` (Onda 0 docs-only, "later phase=2").
- **Mundo hoje:** ULTRAPASSOU a proposta — módulo real com 6 drivers (Inter/C6/Asaas/BcbPix/Pagarme/SicoobApi), 5 models, controllers/jobs/events, `module.json` habilitado. **`what-oimpresso.md` está STALE** ("🟡 Onda 0 docs only") — corrigir a fonte viva junto.
- **Recomendação:** RATIFICAR-ACEITAR + fix na citação de `what-oimpresso.md`.

**20 · 0193-nfeservice-retransmitir-sem-forcedelete** (proposto 2026-05-25, 38d)
- **Decide:** eliminar `forceDelete()` do `NfeService::retransmitirInterno()` (soft-delete Caminho A).
- **Mundo hoje:** implementado 100% — zero `forceDelete` no service, `NfeEmissao` com SoftDeletes, `withTrashed()` no `proximoNumeroLocked`, guard Pest D6 verde (`Wave27NfeSaturationTest`); proibições já tratam como lei.
- **Recomendação:** RATIFICAR-ACEITAR.
- **Pergunta:** a UNIQUE composta `(business_id, transaction_id, deleted_at)` foi deliberadamente deferida? (hoje resolve via query-side)

**21 · 0230-metodo-governance-scorecard** (proposto 2026-05-28, 35d)
- **Decide:** método 4 etapas + score-as-code `memory/scorecards/governance.yaml` + invariantes A (ratchet) e B (origin).
- **Mundo hoje:** em-prod — yaml com `status: em-prod`, grade executável `.claude/governance-eval/grade.mjs`, invariantes nas regras.
- **Recomendação:** RATIFICAR-ACEITAR.

**22 · 0231-processo-trabalho-canonico-especialista-por-area** (proposto 2026-05-28, 35d)
- **Decide:** modus operandi DIVIDIR → ESPECIALISTA POR ÁREA → CONSOLIDAR pra tarefa complexa.
- **Mundo hoje:** agentes existem (`audit-research/implement/senior-expert`, `coordenador-paralelo`) e o padrão é usado (inclusive NESTE dossiê) — mas é meta-guidance opt-in, sem enforcement nem critério de "quando obriga".
- **Recomendação:** MANTER-PROPOSTA (grandfather) — aspiração honesta; promover a aceito sem definir "quando aplica" criaria lei vaga.
- **Pergunta:** quer formalizar critério de disparo (ex.: auditoria ≥3 dimensões)? Se sim, vira RATIFICAR com emenda.

**23 · 0232-modelo-peso-real-classificacao-por-meta** (proposto 2026-05-28, 35d)
- **Decide:** fórmula-mãe de peso por contribuição à meta (ADR não decai por tempo; memória decai half-life 60d; iniciativa = ROI weighted) com campo cross-tipo `relevancia_meta`.
- **Mundo hoje:** só texto — `relevancia_meta` NÃO existe em nenhum yaml/índice; fórmula não-executável; `meta_contribution` deferido como P1.
- **Recomendação:** EMENDAR (nova ADR com ondas P0/P1/P2 executáveis) OU APOSENTAR se a meta-classificação perdeu prioridade.
- **Pergunta:** ainda quer a fórmula de peso-por-meta? Quem implementa o P0 e em qual cycle?

**24 · 0233-ativacao-memoria-momento-decisao** (proposto 2026-05-29, 34d)
- **Decide:** memória comportamental em 3 camadas (Tier A eager / Tier B lazy / hook momento-decisão) + convenção `gatilho:/evento:/hook:` em feedback-*.md + R13.
- **Mundo hoje:** hooks core implementados (`nudge-recommend-not-menu.ps1`, `nudge-diagnosis-without-evidence.ps1`, `block-serving-branch-switch.ps1`) e testados no grade.mjs; convenção `gatilho:` segue aspiracional (sem verificação em feedbacks novos).
- **Recomendação:** RATIFICAR-ACEITAR (núcleo real); enforcement da convenção pode ser follow-up separado.

---

## FILA O — morta-mas-canon (5 itens)

> Vereditos possíveis: **RELABEL-PARCIAL** (a "morte" era emenda — sucessora ganha `supersedes_partially`, antiga revive, modelo 0257 como no caso 0035/0048 e 0078/0094) · **FIX-CITAÇÃO** (a morta está morta; a fonte VIVA deve citar a sucessora — fonte viva não é append-only, edita normal) · **CITAÇÃO-HISTÓRICA** (referência legítima ao passado → `--update-baseline`).

| # | ADR morta | Morta por | Recomendação | Decisão Wagner |
|---|---|---|---|---|
| O1 | 0008 sidebar-unica-tabs | 0039 (Chat Cockpit) | **CITAÇÃO-HISTÓRICA** (provável colisão de número com UI-0008) | [ ] |
| O2 | 0010 sistema-memoria | 0027 + 0053 | **RELABEL-PARCIAL** (bifurcação, não morte) | [ ] |
| O3 | 0079 constituicao-v1 | 0094 (Constituição v2) | **FIX-CITAÇÃO** (fontes vivas → 0094) | [ ] |
| O4 | 0136 sells-grade-toggle | 0178 (tabs de Visão) | **FIX-CITAÇÃO** (SPEC Sells → 0178) | [ ] |
| O5 | 0190 primary-roxo-295 | 0235 (DS v4) | **FIX-CITAÇÃO** (forward-link "0190 → 0235") | [ ] |

**O1 · 0008-sidebar-unica-tabs-horizontais** — decidia sidebar única + tabs horizontais do Ponto WR2 (era AdminLTE); morta pela 0039 (Cockpit 3 colunas). As citações vivas encontradas apontam pra **UI-0008** (`_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md`, ATIVA) — é colisão de numeração entre as duas séries, não uso da decision/0008. Nada no mundo manda usar tabs horizontais. → CITAÇÃO-HISTÓRICA (`--update-baseline`); avaliar se o detector do Check O deve distinguir série `decisions/NNNN` de série `UI-NNNN` (falso-positivo estrutural).

**O2 · 0010-sistema-memoria-projeto** — decidia as 3 camadas de memória (CLAUDE.md + `memory/` + `sessions/`); "morta" por 0027 (papéis) + 0053 (MCP server). MAS a estrutura procedimental da 0010 está VIVA e operante (é literalmente como este repo funciona) — as sucessoras especializaram papéis e infra, não revogaram a estrutura. É o padrão 0035/0048: emenda, não morte. → RELABEL-PARCIAL: 0027 e 0053 ganham `supersedes_partially: [0010]`; 0010 volta `aceito/ativo` (PR label `adr-status-relabel`).

**O3 · 0079-constituicao-oimpresso-7-camadas-governanca** — Constituição v1; supersedida INTEIRA pela 0094 (frontmatter correto dos dois lados). O problema é só citação stale em fonte viva: `memory/governance/CONSTITUTION.md` ("ADR de origem: 0079"), `ENFORCEMENT.md` ("distribuídos em ADR 0079 Fases 3-5"), `governance-gate.yml:407`, templates SCOPE.md ("gerado via Fase 3.4 do ADR 0079"). → FIX-CITAÇÃO: apontar autoridade pra 0094 mantendo 0079 como nota histórica ("evolução de"). Citações em `audit-2026-05-05*.md` são histórico puro (não tocar).

**O4 · 0136-sells-grade-avancada-modo-toggle** — toggle `lista|grade-avancada` em localStorage; morta pela 0178 (tabs Operacional/Financeira/Produção, unificação -777 LOC). Código atual não tem toggle (`visao:` apenas). Citações stale: `memory/requisitos/Sells/SPEC.md:512/537/559` (US-SELL-015/016/017 citam 0136) + 2 comentários mortos em `HandleInertiaRequests.php:152,519`. → FIX-CITAÇÃO: SPEC aponta 0178 (com "0136 histórico"); comentários mortos no middleware podem sair em PR de higiene.

**O5 · 0190-primary-button-roxo-universal-295** — primary roxo `oklch(0.55 0.15 295)` universal; "morta" pela 0235 (DS v4), que ASSUME e expande a mesma regra — o roxo 295 está vivo em 11 arquivos CSS e na skill `pageheader-canon`. Duas opções: (A) RELABEL-PARCIAL (0235 ganha `supersedes_partially: [0190]`, 0190 revive) — mais fiel ao modelo 0257, já que a regra da 0190 segue valendo literalmente; (B) FIX-CITAÇÃO conservadora — 0190 fica morta e as fontes vivas citam "0190 → 0235" (`_DesignSystem/SPEC.md:238/240/265`, `DESIGN.md:113/151-153`, `semantic.tokens.json`; `.claude/rules/css.md` JÁ cita 0235 corretamente). Recomendo **B** (a 0235 é o regime completo; manter 1 dona da regra evita duas ADRs vivas dizendo a mesma coisa).

---

## Ajustes na própria máquina (achados colaterais da triagem)

1. **TTL de feature-wish por `kind`, não por `status`:** 0125/0151/0152 têm `kind: feature-wish` (TTL 180d) mas `status: proposto` (TTL 30d) — o lookup `TTL_DAYS[st] || TTL_DAYS[kind]` deixa o status atropelar o kind. Se Wagner concordar que feature-wish dormente sob 0105 merece 180d, inverter a precedência (`TTL_DAYS[kind] || TTL_DAYS[st]`) ou isentar `kind: feature-wish` com status honrado. Tira 5 itens de re-flag futuro.
2. **Check O × colisão de séries:** O1 sugere que o detector pode casar "0008" da série UI (`memory/requisitos/_DesignSystem/adr/ui/`). Vale 1 iteração de calibração (o histórico do check é 11→5; seria 5→4).
3. **Check L vs Check R:** 14 "implementado-mas-proposto" são conceitualmente Check L (vivo-mas-proposto); o R os pegou pela idade. Sem ação — só registro de que o relabel em lote resolve as duas filas.

## Como aplicar (quando Wagner devolver o 1×1)

- **RATIFICAR/APOSENTAR/RELABEL:** PRs pequenos (lotes de ~5 por PR, ≤300 linhas) tocando SÓ frontmatter, label `adr-status-relabel` (0257) — corpo byte-idêntico (mesmo mecanismo do #3514/0078 e da migração 0297/#3595).
- **MANTER-PROPOSTA:** um único `node scripts/governance/memory-health.mjs --update-baseline` após os relabels (grandfather do que sobrar na fila).
- **EMENDAR:** ADRs novas (0072-consolidação, 0165-responsive-v2, 0232-ondas) — cada uma com aprovação própria.
- **FIX-CITAÇÃO (fila O):** 1 PR de docs editando as fontes vivas (não são append-only).
