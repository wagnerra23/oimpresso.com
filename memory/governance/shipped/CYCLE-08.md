<!-- GERADO por método one-shot validado na sessão 2026-06-22 (fonte completa). NÃO é o shipped-log-generate.mjs v1 (reprovado). -->
---
status: parcial
cycle: CYCLE-08
generated: "2026-06-22"
---

# Shipped log (PARCIAL) · CYCLE-08

> ⚠️ **PARCIAL** — cycle aberto (`2026-05-31 → 2026-06-28`); janela coberta `2026-05-31..2026-06-22`. Regenerar ao fechar.
> **Rótulo honesto:** lista o que foi **mergeado em `main`** na janela. Merge ≠ deploy ≠ funciona em produção. Reverts riscados abaixo.
> Fonte: REST por sub-janela de dia (sem teto de 1000 da Search API) + API `/commits` pra push-direto. **Não** depende de `Refs: US-XXX` (cycle drift conhecido).

## Contagem

- **1073 PRs** mergeados em `main` na janela · 618 de produto (feat/fix/refactor/perf/style) · 455 de manutenção (docs/chore/test/ci/build)
- **33 entregas push-direto** (commits sem objeto-PR — invisíveis a query de PR)
- **1 revert reconciliado** (par riscado — entrega líquida zero)
- **200 tocam Design System** (já no CHANGELOG.md do DS, backfill #3167)

## Reconciliação — merge ≠ entrega

- ⚠️ **#2104 revertido por #2107** — conta como entrega líquida **zero**, não +1 feature.

## Entregas push-direto na main (sem PR)

> Classe inteira que o gerador via-PR nunca vê. Inclui produto real (multi-tenant, cliente, oficina, cockpit).

- fix(main): restaura codebase apagado pelo squash do #2413
- docs(audit): auditoria IA OS 2026-06-06 — 79/100 (era 68 em maio)
- chore(governance): hook PreToolUse bloqueia menu de execução em AskUserQuestion
- ci(financeiro): lane Pest MySQL (baseline+seed) — 5 guards verde [M]
- test(financeiro): lane CI MySQL verde — baseline completo + cleanup + seed mínimo (US-FIN-052) [M]
- chore(repo): remove paths duplicados por case (pt-BR/pt-br · Nfe-/nfe-)
- fix(oficina): tokens da placa em oklch (não hex) — destrava stylelint color-no-hex ratchet
- fix(oficina): tokeniza placa Mercosul (--plate-frame / --plate-ink)
- feat(oficina): re-tokeniza Kanban Oficina → família stage DS (UI-Lint #2228)
- docs(adr): 0244 DS v5 canon + Oficina tela-padrão + Inbox 9.75 régua congelada
- docs(handoff): sessão 2026-06-02 handoff Cowork → main + retorno pro Design
- fix(multi-tenant): recalibra guard business_id + ativa no CI (Tier 0)
- ci(ui): gate de arquitetura roda os testes AppShell/accent/core-screens
- docs(prototipo-ui): mirror charters Vendas/Compras + inventario classes
- refactor(css): tokeniza background #fff -> var(--surface) no bundle financeiro
- test(core-screens): smoke estrutural + scaffold browser das telas-nucleo
- test(arch): gate "toda tela Inertia usa AppShellV2"
- fix(cockpit): accent default = roxo canon 295 (era azul 220 no shell)
- docs(handoff): 2026-06-01 17:45 — US-CRM-078 backend CT-100 + sqlite fora da memoria
- docs(memoria): remove sqlite dos docs de teste (canon = CT-100 MySQL)
- chore(grades): rebaseline Crm 88->87 (drift pos-merge US-CRM-078 backend)
- docs(design): brief tela EnderecoTab lista + seletor na venda (US-CRM-078)
- test(cliente): ContactAddressController test no CT 100 MySQL real, nao sqlite (US-CRM-078 PR2)
- feat(cliente): ContactAddressController CRUD enderecos + rotas + Pest (US-CRM-078 PR2 backend)
- test(cliente): created_by NOT NULL no Contact (MySQL real pegou, sqlite mascarava)
- test(cliente): ContactAddress cross-tenant roda no CT 100 MySQL real, nao sqlite (US-CRM-078)
- docs(canon): testes CT 100 = MySQL real, NAO sqlite (retira conflito que induziu erro)
- fix(spec): last_updated string + US-CRM-078 no us_list (schema gate)
- feat(cliente): contact_addresses + ContactAddress model + Pest cross-tenant (US-CRM-078 PR1)
- fix(charter): page rota valida (schema gate)
- fix(charter): page rota valida (schema gate)
- design: 7 telas overlap — minha versao superset (complementa #2037) [CI-green]
- design: 37 telas <70 -> >=70 + XSS Cms + sidebar dedup (US-TR-309..314) [CI-green]

## Por área (PRs mergeados)

### governance — 94 (+78 manutenção)
- fix: guard automático de base STALE vs origin/main + PROTOCOL §10.4 Passo 0 (#2033)
- feat: G4 retorno automático §10.2 — design_return_skipped + workflow pós-merge (#2064)
- feat: governanca:scorecard — placar [CC]×Jana mecanizado (graduação de lições) (#2151)
- feat: governanca:ciclo-diario — orquestrador diário advisory (#2152)
- feat: UC-guards + protocol_freshness — Casos de Uso → GUARD Pest (#2153) · `DS`
- feat: US-GOV-013 — tornar gate visual ADR 0108 real (sair do stub) (#2217)
- feat: nudge hook contract-anchor — mecaniza Check 9 (anti-regressão Stage 1) (#2278)
- feat: reuse-index + gate anti-duplicação de símbolo (JS+PHP) (#2343)
- feat: gate no-mock-in-prod (stub/mock/rand/NO-OP em controller) (#2344)
- feat: gate design-spec:check — contrato estrutural por-tela (enactment ADR 0255) (#2357)
- feat: a11y ratchet — acessibilidade como categoria protegida (determinístico) (#2359)
- feat: loop de aprendizado two-strikes pra erros de código (#2366)
- feat: mutation gate advisory (infection) — fecha LC-03 (#2368)
- fix: registra colisões ADR 0236/0246 + conserta links-fantasma 0180 + ref screen-qa (#2381)
- feat: sentinela memory-health — batimento cardíaco da memória (ADR 0256 Onda 1) (#2386)
- feat: ADR 0257 — modelo status/lifecycle/kind de ADR + exceção de normalização (#2387)
- feat: comando supersede atômico de ADR (peça #2 — modelo adr-tools) (#2393)
- feat: gerador determinístico de índice de ADR (modelo Log4brains) (#2391)
- fix: gate libera superseded_by/supersedes + conserta parser do índice (#2395)
- fix: resolve conflitos de ADR — supersessão atômica de 12 ADRs (ADR 0258) (#2396)
- feat: revisão de conflitos + GAP 1 (supersede gate duro) + GAP 3 (anti-ressurreição) (#2399)
- feat: GAP 2 — memory-health ENFORCE + baseline ratchet (#2401)
- feat: F1 gates — casos:check (trio+rastreabilidade) + dominio:check (ADR 0264) (#2463) · `DS`
- feat: F1b — harness Playwright + spec Oficina UC-06 (G-3, não-required) (#2466)
- feat: casos:check G-5 — metadata viva (owner + last_run + Status por UC) (#2467) · `DS`
- feat: casos:check G-6 — frescor via git (Salto #1 dos 3) (#2469) · `DS`
- feat: dominio:check Salto #3 — domínio além do enum (cobertura de código) (#2471)
- feat: casos:check G-7 — Status derivado do verde (Salto #2, F1) (#2472) · `DS`
- feat: components-tree-guard — arvore canonica de Components/ enforcada (#2542)
- feat: hook red-first em modo WARN - advisory de nascenca (SDD FV-T0) (#2589)
- feat: sdd-scorecard agregador GT-G2 — scorecard v1 (3 vivas + 7 not_yet_measured) (#2597)
- feat: protocolo refutador de backfill IA + ledger + ledger-check (GT-G5, advisory) (#2588)
- feat: codemod ghost-fix + tabela curada de renames (KL-A1, dry-run only) (#2593)
- feat: gate-selftest — quem vigia os vigias, fixtures versionadas boa/ruim por catraca (GT-G6, advisory) (#2605)
- feat: tela DS Rollout + Ledger de Conformidade DS (censo ds-ledger.mjs) (#2621)
- feat: snapshot diário do scorecard SDD em mcp_sdd_scorecard_history (GT-G7 1/2) (#2617)
- fix: GLOB_BRACE indefinido no musl derrubava ~6 detects do Module Grade v4 (LC-bug-1) (#2668)
- feat: hook bloqueador red-first FV-T0 (motor de block, nasce advisory) (#2662)
- fix: // SUPERADMIN nos bypass de global scope — KB/Financeiro/NfeBrasil (US-GOV-019) (#2679)
- fix: // SUPERADMIN nos bypass de global scope — OficinaAuto (US-GOV-019) (#2694)
- fix: // SUPERADMIN nos bypass de global scope — KB/NFSe/Crm/Arquivos (US-GOV-019) (#2696)
- fix: // SUPERADMIN nos bypass de global scope — PaymentGateway (US-GOV-019) (#2698)
- fix: // SUPERADMIN nos bypass de global scope — NfeBrasil/ComVis (US-GOV-019) (#2699)
- fix: // SUPERADMIN nos bypass de global scope — Superadmin/Financeiro/app/misc (US-GOV-019) (#2707)
- fix: // SUPERADMIN nos bypass de global scope — Whatsapp (US-GOV-019 · fecha a lane) (#2721)
- fix: ghost-fix.mjs pula **/adr/** (append-only) + teste-guard (#2729)
- feat: scorecard mede n_quarantine (FV-Q3) — 27 arquivos (#2731)
- fix: KL-E2 — remove 2 ghosts dos stubs (Modules/Index, Modules/Sells) (#2757)
- fix: KL-E2 Estoque — falso-ghost Modules/Inventory + baseline órfão (#2763)
- fix: resolver drift doc↔código US-GOV-018 (ledger §E) (#2772)
- fix: scorecard honesto — anchor_coverage fonte única + re-arma ratchets (ledger §A-C) (#2770)
- fix: e2e screen-coverage conta só caminho exato (remove 106 falsos-positivos) (#2783)
- feat: gerador de backlog indexado read-only (US dos SPEC.md) (#2808)
- fix: foundation-ratchet conta uso real de RefreshDatabase, não menção (#2810)
- feat: gate de frescor visual_source (Catraca Viva F0) (#2941)
- feat: gate de tela órfã/morta (Catraca Viva F1) (#2943)
- feat: reincidencia-guard standalone (C3/C4/C5 · advisory) (#2950)
- feat: catraca Contrato de Tela — fidelidade visual (advisory) (#2973)
- feat: status `recusado` first-class no adr.schema + seção Recusadas no índice (#2989)
- fix: sincroniza enum `recusado` nos 4 enforcement points (completa #2989) (#2991)
- feat: stream MEM no sdd-scorecard — une SDD + memória num só sistema (keystone peça 4) (#3012)
- feat: distiller_freshness instrumentado (PR-D · peça 3) (#3020)
- fix: decodifica títulos !!binary base64 no índice de ADR (#3056)
- fix: registra hook R10 (block-pr-without-approval) em settings.json (#3058)
- feat: plan-health Check J (Onda 1 do ADR 0294) (#3076)
- feat: gerador plans-index + fix Check J (resto da Onda 1, ADR 0294) (#3082)
- feat: gerador do Índice de Planos Vivos + teste canário (ADR 0294) (#3086)
- feat: memory-health Check K — detector dos planos perdidos (advisory) (#3087)
- feat: memory-health Check K — decisão em session log sem âncora (#3084)
- fix: índice de planos gerado não propaga Modules/<X> (anti-ghost) (#3089)
- feat: adr-index double-supersede — conflito de herança vira gate (#3085)
- fix: endurece Check K após review adversarial (idade + âncora estrutural) (#3091)
- fix: endurece hook R10 — comentário stale + 3 bypasses (review adversarial) (#3093)
- feat: bateria de sentinelas honesta + bite-tests + agregador (#3098)
- feat: plan-health — sentinela de planos órfãos (ADR 0294 Onda 1) (#3100)
- feat: plan-health no Daily Brief + gate CI advisory (ADR 0294 Onda 1) (#3103)
- fix: drift-sentinel skip-guard honesto — dormant sem OPENAI_API_KEY (#3108)
- feat: jana:plan-drift — drift plano↔tasks MCP (ADR 0294 Onda 2) (#3105)
- feat: baseline-tamper-guard — anti-grandfather (Gap 2 blueprint SDD) (#3128)
- feat: exceção append-only p/ migração legacy→canônico de frontmatter ADR (ADR 0297) (#3139)
- fix: mapeia colisão ADR 0294 no adr-alias-map (13→14) (#3134)
- feat: baseline-tamper-guard cobre +5 baselines (1/6→6/6) — Onda 1 (#3151)
- feat: gitleaks full-history scan + .gitleaks.toml (Onda 1 · segredos) (#3148)
- feat: sentinela transporte CT100→main — mcp_served_drift + index_freshness + escalonamento (Onda 1) (#3160)
- feat: 8 US-GOV do roadmap SDD (sweep ao vivo da sessão) (#3168)
- feat: promover foundation-ratchet a required — 1º dente SDD em L3 (DRAFT pré-14d) (#3143)
- feat: US-GOV-042 — anchor-lint status:arquivado + MemCofre/SRS (A3 do sweep) (#3169)
- feat: reconcilia detector×corretor ghost + renames Classe A + cron distiller (P11 KL-E2/E3) (#3155)
- feat: commit-back do floor SDD pra main (scorecard não-stale · P01/Gap-1a) (#3142)
- feat: instrumenta pcov no CT100 + measureCoverage no scorecard (SDD P07) (#3150)
- fix: isenta _Governanca/roadmap/ no anti-ghost (fecha US-GOV-035 + o vermelho) (#3175)
- feat: tamper-guard fecha grandfather dos 4 baselines (P05 · vetor #2848) (#3144)
- fix: isenção roadmap no allMdLive — fecha o anti-ghost vermelho (de verdade) (#3176)
- feat: 2º dente SDD — SDD scorecard ratchet (GT-G3) required-candidate [DRAFT] (#3181)

### jana — 61 (+7 manutenção)
- feat: health-check de charter (advisory) no jana:health-check (#2055)
- feat: tela Jana Pro paywall (/ia/pro) — F3 do design aprovado (#2069)
- feat: health-check alerta recall backend down (resiliência Meilisearch) + Pest (#2070)
- fix: Pro.tsx usa token semântico success (corrige drift ui:lint R1) (#2091)
- feat: ledger de lições de operação (Reflexion runtime) + graduação no health-check (#2131)
- feat: Advisor Metade A — clarify reativo (Modo Consultor §10.4) (#2134)
- feat: Advisor Metade B — próxima-melhor-pergunta proativa (ADR 0245) (#2139)
- feat: ADR 0245 + liga Metade A (clarify) em homolog (#2137)
- fix: clarify funcional — schema strict-compliant + modelo acessível (gpt-4o-mini) (#2143)
- fix: juiz de UI vira de fato Sonnet 4.6 + G-Eval rationale-first (G2+G3 do parecer #2270) (#2284)
- feat: medição do PR UI Judge — jana_ui_judge_runs + jana:ui-judge-trend (#2287)
- fix: model_suggestions default gpt-4o → gpt-4o-mini (acesso real OpenAI) (#2329)
- feat: Onda 1 — PR UI Judge 6/9 dims LLM→determinístico (#2362)
- fix: KL-C1 - conserta duplo-OFF do peso_real (config nao-null + vocabulario lifecycle alinhado, flag OFF) (#2610)
- feat: porta jana:recall-eval pra main (KL-C2 órfão de stack) (#2730)
- fix: /ia/dashboard sem encavalamento (cockpit vazava sobre Metas) (#2774)
- feat: TasksClaimTool propaga X-Claude-Code-Session pro lease (LEASE-HABITO) (#2789)
- fix: corrige custo MCP — config certa + unidade por-1k (sumiu fator 1000x) (#2786)
- feat: triggers de imutabilidade append-only em mcp_task_events (#2790)
- feat: detecta divergencia descritiva SPEC<->DB no sync (SDD C4) (#2788)
- feat: updateInner ATÔMICO — DB::transaction + lockForUpdate (Fase 2b, ADR 0278) (#2785)
- feat: TasksReconciler detect-only (R-A/R-B/R-E) em jana:reconcile — B-R0 Leva 2 (#2799)
- feat: enforce mcp_tasks FSM transition matrix (proíbe todo→done teleporte) — A1+A8 Leva 2 (#2798)
- feat: server-side scope gate via AuthorizesMcpMutation trait — A4 Leva 2 (#2801)
- feat: whats-active injeta liveness — pipeline cego vira 'NÃO SEI' (B-SPOF-WA, Leva 2) (#2803)
- feat: A5 — aviso SOFT de mutação claim-less (instrumenta sinal pré-A7, Leva 2) (#2804)
- feat: A3 — split tasks.write em advance vs close (Leva 2, último item) (#2805)
- feat: sentinela de fluxo de inbound WhatsApp — detecta recebimento parado (nunca mais #2726) (#2813)
- feat: sensor de valor sells_value_sanity (Frente 1 — caso Guilherme) (#2907)
- fix: McpAuthMiddleware seta user no auth manager — destrava mutações MCP (PR-7c · ADR 0283/0081) (#2934)
- fix: remove nome morto 'Copiloto' do peso_real (ADR 0088) (#2967)
- feat: Onda 3 (webhook) — trava cobertura MCP dos vereditos do ledger (read-only) (#2995)
- fix: `recusado` consultável no decisions-search + fecha landmine de corrupção (#2998)
- fix: `recusado` no filtro da busca híbrida Meili (completa #2998) (#3000)
- fix: mensagem do decisions-search diz 'recusadas' (completa honestidade #2998) (#3008)
- feat: ModuleTruthEventCollector — núcleo puro do distiller (PR-B) (#3016)
- feat: DistillerModuloVerdade — motor diário→manual (PR-C1) (#3023)
- feat: jana:distill-module-truth + cron gated (PR-C2) (#3028)
- feat: DesignDossieAssembler — dossiê de tela puro (PR-1a) (#3032)
- feat: DesignIngestPlanner — núcleo puro da ingestão de design-zip (PR-2a) (#3034)
- feat: comando design:dossie (PR-1b) (#3033)
- feat: design:ingest-zip + cowork-map (PR-2b) (#3037)
- feat: design:mine-raw — chats raw → candidatos 🔍 (PR-3, opcional) (#3036)
- fix: estação de design — diff por conteúdo (sem git) + resolver de paths robusto (#3039)
- feat: estação ingere handoff completo — diff sobre roteados + extras inteligentes (#3041)
- feat: bi-temporal event-time na memória — slice 1 (ADR 0295, T4) (#3073)
- feat: mcp_audit_log tamper-evident por hash-chain SHA-256 (ADR 0294, T5) (#3069)
- feat: bi-temporal time-travel — slice 2 (memoria-historica + buscarHistorico, ADR 0295 T4) (#3078)
- feat: bi-temporal slice 3 — deteccao de supersede event-time atras de flag OFF (ADR 0295, T4) (#3081)
- fix: tasks-create não reusa task_id de row órfã no DB (incidente US-RB-052) (#3106)
- fix: parent_plan dos 22 US plano-perdido em meta-line > (custom_fields) (#3111)
- fix: procedure_drift falso positivo — normalize ignora backticks do SHOW CREATE (#3113)
- fix: regex de SPEC casa #### (4 hashes) — corrige 70 falsos-positivos do mcp:tasks:orphans (#3112)
- fix: agenda ProfileDistiller — job que faltava (COPI-26) (#3115)
- fix: ProfileDistiller não zera created_at em regen (COPI-26) (#3118)
- fix: spec_id_drift robustez — check tolera emoji `?` + parser captura sub-letra (#3116)
- fix: flag `u` no parser de US — raiz dos "? " no cache mcp_tasks (#3124)
- feat: write-canary no health-check — pega GRANT de escrita revogado (#3125)
- feat: db_storage_quota check + causa-raiz real (incidente 2026-06-21) (#3126)
- feat: jana:memory-history-prune — teto preventivo pra history bloat (Fase 2 · 1/2) (#3130)
- feat: recall-eval mock gate em CI + schedule real pronto-pra-ligar (P12) (#3138)

### financeiro — 55 (+18 manutenção)
- fix: agingBucket usa diffInDays absoluto (Carbon 3 signed) (#2050)
- feat: Fase 1 ADR 0236 — conciliação lê extrato API (+ dedupe OFX) (#2060)
- feat: Fase 2 ADR 0236 — backfill OFX→extrato canônico (código, sem exec prod) (#2068)
- fix: caixa_movimento_freshness usa diffInDays absoluto (Carbon 3 signed) (#2052)
- fix: botões honestos em DRE + Cobrança (B6) (#2046)
- fix: match_score real na Conciliação (re-impl #2042 sobre Fase 1/2 ADR 0236) (#2083)
- fix: Extrato — rota /extrato sem id (B4) + session key canon (B5) [re-impl #2043] (#2085)
- fix: Conciliação — audit-log + reabrir/undo BACKEND (re-impl #2044) (#2087)
- feat: UI reabrir + toggle ver-resolvidos na Conciliação (#2044 UI) (#2090)
- refactor: BankStatementLine model + BusinessScope na Conciliação (re-impl #2045 sobre ADR 0236) (#2094)
- fix: realinha chips de filtro + primary do header (DS v4) (#2156)
- feat: filtro por campo de data (paridade WR) na Visão Unificada [M] (#2157)
- fix: alinha visual do filtro de data aos chips/selects da toolbar [M] (#2159)
- fix: date-input com a mesma caixa do select vizinho na toolbar [M] (#2161)
- fix: borda do date-input do filtro WR agora aparece [M] (#2166)
- fix: borda visível no filtro de data + selects da toolbar casam [M] (#2172)
- feat: forma de pagamento no lançamento unificado (#2169)
- feat: cards ignoram baixas de cancelado + filtro Arquivados [M] (#2176)
- fix: forma de pagamento no botão Editar do drawer (TituloEditSheet) (#2188) · `DS`
- feat: drawer exibe campos do lançamento (paridade WR · Fase 1) [M] (#2196) · `DS`
- fix: DS v6 dark flip - corrige selector morto .fin-cowork (#2200)
- feat: diálogo de baixa (valor/conta/forma/plano) + coluna Conta (#2197)
- feat: 8 ajustes na consulta/baixa/edição (pedido Wagner) (#2206)
- feat: drawer — Número/Parcela/Pedido/Vencimento/Data pgto/Valor aberto + Desconto·Juros sempre [M] (#2207) · `DS`
- fix: hero KPI legível no dark — eleva .fin-stat-hero (#2209) · `DS`
- fix: health command consultava coluna deleted_at inexistente em fin_caixa_movimentos (#2253)
- fix: HOTFIX P0 — remove trait órfão RendersMockCowork dos 11 controllers (main quebrado pós-#2256) (#2261)
- fix: /unificado 500 com título de conta vinculada — eager-load coluna inexistente nome (US-FIN-053 Batch 4) (#2257)
- feat: <FinStatStrip> — piloto tokenização passo 2-3 (PROPOSE-ONLY · precisa smoke visual) (#2301) · `DS`
- fix: cards Recebido/Pago somam juros+multa-desconto [E+C] (#2363)
- feat: tela-piloto Prova Viva 100% primitivos (ADR 0253) (#2372)
- feat: wr2:backfill-recurring-2026 — recorrência 2026 biz=1 (assinaturas+invoices+cobranças+boletos Firebird) [E] (#2416)
- fix: wr2:backfill etapa4 sem shell_exec (Hostinger) [E] (#2430)
- fix: wr2:backfill etapa2 created_by FK fin_titulos [E] (#2431)
- fix: wr2:backfill cobrancas.origem_type ENUM válido [E] (#2432)
- feat: US-FIN-053 done — WR2 backfill recorrência 2026 biz=1 [E] (#2434)
- feat: gerar boleto Inter no drawer da Visão Unificada (#2452) · `DS`
- feat: Impostos & obrigações — DAS estimado + costura caixa unificado [F2 PR-2] (#2496)
- feat: drawer Unificado 3 camadas — hero fixo + lentes + Lente Fiscal [F2 PR-3 · re-land] (#2497) · `DS`
- fix: Impostos — data legacy com timestamp renderizava '18 00:00:00/12' (#2499)
- style: snap tipográfico — 479 font-size px → var(--fs-1..9) (FA-2) (#2572)
- style: tempero aplicado — sombras de elevação + transições → tokens (FA-3) (#2574)
- fix: resync-from-core — corrige fin_titulos inflados (incidente num_uf, ROTA LIVRE biz=4) (#2576)
- style: fechamento — cor residual segura + handoff dos achados FX-1..5 (FA-4) (#2577)
- fix: achados do print do Unificado — período/vencida/−0,00/delta/segmented (FX-1..5) (#2582)
- feat: drawer Unificado 9.75 F3 — J/K/R + CopyVal + recibo + hero R3 (FA-5) (#2584) · `DS`
- fix: derivar restante do bridge expense→titulos (coluna total_remaining_amount nunca existiu) (#2744)
- fix: % pt-BR no audit trail + alarme de saldo previsto negativo (adversário Wave 1) (#2830)
- feat: Tribunal Onda 2 — drawer/lista lideram com a conclusão (#2836) · `DS`
- fix: hero KPI vira claro (caixa preta → superfície da identidade) (#2844) · `DS`
- fix: sub-nav segue a entry do active (corrige regressão split ADR 0180) (#2847)
- fix: "realizado" do hero volta ao tamanho de apoio (#2851) · `DS`
- fix: hero bate pixel com o gabarito Cowork (handoff Claude Design) (#2856) · `DS`
- refactor: Dashboard adota PageHeader canon v3.8 + primary roxo (#2863) · `DS`
- feat: Unificado header migra pro <PageHeader> canon v3.8 (#2947) · `DS`

### whatsapp — 39 (+11 manutenção)
- fix: inbound usa hora real do evento, não now() (fuso London na fila) (#2057)
- refactor: dedup formatBytes → re-export de @/Lib/utils (#2346)
- feat: US-WA-302 assignee picker na Caixa Unificada V4 (PR-1/10) (#2503)
- feat: US-WA-303 composer completo — templates + macros + variáveis (PR-2/10) (#2506)
- feat: US-WA-301 filas DB whatsapp_queues + painel QueuesSheet (PR-3/10) (#2507)
- feat: US-WA-305 mover conversa entre filas — queue_override (PR-4/10) (#2509)
- feat: US-WA-304 drawer Canais e contas in-place (PR-5/10) (#2511) · `DS`
- feat: US-WA-307 + Nova conversa — find-or-create + thread aberta (PR-6/10) (#2512)
- feat: US-WA-306 broadcast FASE 1 — pre-flight LGPD + draft auditável (PR-7/10) (#2514)
- hotfix: caixa unificada — queuesAdmin degrade gracioso (incidente 'carregando canais 500') (#2515)
- feat: pacote polish V2 — SLA pill, cheat-sheet, lightbox, mobile tabs, favoritos, transcript, apresentação (PR-8/10) (#2517)
- feat: PR-9 IA na thread — resumir, perguntar, sugerir resposta (PR-9/10) (#2518)
- fix: touch() upsert portável MySQL+SQLite (#2650)
- fix: enforce 1 grant ativo por (canal,user) em channel_user_access (#2648)
- fix: typo $i_ no AuthStateDriftCheckTest derrubava regression-guard Baileys (LC-bug-2) (#2669)
- fix: macro_variant_id no $fillable de Message (A/B tracking persiste) (#2646)
- fix: dispara CSAT na transição open→resolved do inbox (US-GOV-019) (#2672)
- fix: auditor do flip-flop ACL canal (revoke/reativação) + auditor de corruptores SQLite (#2689)
- fix: remove spoofable IP-whitelist fallback from whatsmeow webhook auth (#2726)
- fix: religa recebimento — segredo na URL do webhook whatsmeow (incidente 2026-06-16, pós-#2726) (#2812)
- feat: canário do webhook (Fase 1 perda-zero, detecção <5min do #2726) (#2828)
- fix: caixa unificada — filtro media_inbound_24h lê messages, não whatsapp_messages legacy (#2944)
- feat: banner "canal caiu — religar" na Caixa + whatsmeow:health-probe (US-WA-308/309) (#2956)
- feat: Caixa — Guia (troubleshooters + trilhas) · port inbox-cur (#2971)
- feat: Caixa — notas internas por-mensagem · port inbox-cur (PR-2) (#2972)
- feat: Caixa — Reconectar canal via QR in-place (port Cowork · piloto da catraca) (#2974)
- fix: tela do QR fecha sozinha ao parear o canal (#2979)
- fix: label da tela Caixa Unificada → Atendimento (#2981)
- fix: ESLint ratchet — deps do effect de auto-close (#2979 follow-up) (#2983)
- fix: health-probe corrobora channel_health com mensagem real (anti falso "fora do ar") (#2985)
- feat: Fase B parte 2 — comando re-subscribe LoggedOut nos canais existentes (#2997)
- feat: Fase B — assina LoggedOut no WuzAPI (detecção de logout em segundos) (#2994)
- feat: probe detecta queda invisível (ADR 0287) + ADRs 0288/0289 + índice (roadmap saúde de canal) (#3003)
- feat: observabilidade de canal — snapshot + alerta canal-down + uptime% (ADR 0288) (#3005)
- feat: alerta canal-down via Centrifugo + mcp_alertas_eventos (ADR 0288 fase 2) (#3017)
- fix: remover --stop-when-empty do queue:work — corta latência de msg entrante (~30s→~1-3s) (#3022)
- fix: banner canal-caiu business-wide (qualquer conta vê) — rebase de #2964 (#3029)
- fix: probe whatsmeow no health-probe (detect-only) — queda deixa de ser invisível (#3055)
- feat: US-WA-311 triagem no inbox (promove E2 do plano) (#3074)

### caixa-unificada — 26 (+1 manutenção)
- fix: modo escuro legível + colapsa Customer 360 vazio (#2818) · `DS`
- fix: chip do canal WhatsApp live (whatsmeow) caía em "em breve" (#2822) · `DS`
- fix: alinha bolhas/timestamp/fundo ao protótipo (batch visual 1) (#2838) · `DS`
- fix: botões de ação uniformes (.os-btn baseline) — Onda 2.1 (#2839) · `DS`
- fix: SLA pill 4 estados + dot animado (Onda 2.2) (#2841) · `DS`
- feat: Saldo + Histórico do cliente no Contexto (US-WA-308 · Onda 3) (#2845) · `DS`
- fix: wrapper flex-col alinha bolhas enviadas à direita (#2849) · `DS`
- fix: wrapper da bolha usa primitivo Stack (catraca layout) (#2850) · `DS`
- fix: composer com botões discretos (fiel ao protótipo) (#2852) · `DS`
- feat: Contexto recolhível (trilho 44px, canon Cowork) (#2858) · `DS`
- fix: SLA pill nítida (contorno na lista, sólido no header) (#2859) · `DS`
- fix: header sem ícone-caixa (fiel ao canon Cowork) (#2860) · `DS`
- refactor: remove faixa de canais (Onda 1 — Canal vai pro popover Filtros) (#2875) · `DS`
- feat: filtros em 2 botões — Status (dropdown) + Filtros (popover) — Onda 2 (#2879) · `DS`
- fix: header usa <Inline> — fecha o layout-primitives ratchet do #2879 (#2883) · `DS`
- fix: scrollbar fina visível na lista e no thread (#2888) · `DS`
- feat: redesign ChannelHealthBanner com visual Cowork (warn/err, dispensável, multi-canal) (#2963) · `DS`
- fix: banner de saúde no topo da LISTA + layout via primitivos (follow-up #2963) (#2968) · `DS`
- fix: Reconectar não pinta "sessão ativa" como erro vermelho (#2984) · `DS`
- feat: saúde de canal em tempo real — Caixa consome whatsmeow.* do Centrifugo (último elo Phase B) (#3002) · `DS`
- feat: composer em 2 linhas (input no topo) — C1 handoff Cowork (#3047) · `DS`
- feat: composer polish C2/C3/C4 (botões discretos + Resp/Nota ícone + divisor) (#3050) · `DS`
- feat: composer polish (C2/C3/C4) + thread favorito/cleanup (T2/T3) (#3052) · `DS`
- feat: T1 — Resumir/Perguntar do header → Contexto (seção Inteligência) (#3053) · `DS`
- feat: Contexto vira drawer lateral (handoff [W] 2026-06-19) (#3054) · `DS`
- fix: composer — ordem de foco do teclado = ordem visual (a11y, WCAG 2.4.3) (#3061) · `DS`

### cliente — 25 (+1 manutenção)
- feat: chip "anexos" no header do drawer ao lado de placas (#2082) · `DS`
- feat: carrega anexos existentes no painel Documentos do drawer (#2086) · `DS`
- feat: habilita enviar/excluir anexos no drawer (era read-only) (#2088) · `DS`
- fix: chip "anexos" reflete contagem viva (não somava após upload) (#2092)
- feat: múltiplos endereços por contato — PR1 backend (US-CRM-078) (#2095)
- feat: CRUD endereços do contato — backend (US-CRM-078, ex-#2096) (#2100)
- feat: endereço de entrega no cadastro de cliente (#2114)
- feat: lista de endereços estruturados no drawer (US-CRM-078 fase 2) (#2118) · `DS`
- fix: /cliente?type=other caía em Clientes — whitelist da rota faltava 'other' (ADR 0246) (#2297)
- fix: consolida menu ⋮ da linha no drawer 760 (remove Blade legacy) (#2420) · `DS`
- feat: excluir contato pelo menu ⋮ da listagem (soft delete + confirmação) (#2422)
- feat: duplo-clique na linha de venda abre a venda (#2445)
- fix: abrir a venda via Inertia (Sells/Show estilizada, não partial cru) (#2451)
- fix: demais links de venda abrem via Inertia (Sells/Show estilizada) (#2455)
- feat: abas Pagamentos/Pontos/Assinaturas carregam no drawer (self-fetch) (#2458) · `DS`
- fix: KPIs do placar Cliente reais server-side (fim do número sem prova) (#2622)
- fix: paginação server-side funcionando + FAB não cobre o pager (#2623)
- fix: subtítulo não duplica o nome (P1) (#2624)
- fix: sort server-side — default Recentes + cabeçalho funciona (P1b) (#2625)
- fix: P3 — header do drawer não encavala o X + cor tokenizada (#2626) · `DS`
- feat: tela-linda slice 1 — Pills.tsx tokeniza ESTADO, preserva CATEGORIA (#2655) · `DS`
- feat: tela-linda slice 2 — 9 componentes limpo-semânticos → tokens (#2660) · `DS`
- fix: status-dot compara EN canon (active/inactive) — corrige cor sempre vermelha (#2665)
- fix: lupa da busca não encavala + Auditoria entra em Operações (#2685)
- fix: encaixa atalhos de teclado no rodapé (remove FAB flutuante) (#2778)

### ds — 25 (+6 manutenção)
- feat: cartao de evidencia ds:report --module --json (F1 do ADR 0240) (#2023) · `DS`
- refactor: migra controles + FieldSuccess T1 do Sells -> DS (#2026) · `DS`
- refactor: migra controles RecurringBilling -> DS (baixa baseline) (#1988) · `DS`
- fix: T0-A — --bubble-me azul → roxo canon (último drift de cor) (#2128) · `DS`
- feat: DS v6 — tokens de fundação --stage-* (PR1 token delta) (#2170) · `DS`
- feat: DS v6 — tokens semânticas --pos/--neg/--warn(+soft) + gate /sells (PR3 kickoff) (#2184) · `DS`
- feat: DS v6 PR3 slice 1 — status pills /sells → tokens semânticos [DRAFT · gate] (#2186) · `DS`
- feat: DS v6 PR3 slice 2 — camada --vd-* do /sells → tokens canônicos (#2187) · `DS`
- feat: DS v6 PR3 slice 3 — origem /sells → --origin-* (recolor gabarito) (#2190) · `DS`
- feat: DS v6 PR3 slice 4 — pipeline FSM dots /sells → --stage-* (#2191) · `DS`
- feat: DS v6 PR3 slice 5 — fiscal badges /sells fg → token (#2193) · `DS`
- refactor: higiene token /sells — danger-red oklch → var(--neg) (-16) (#2194) · `DS`
- feat: refino v2 dos primitivos de layout (ADR 0253) — cobertura ERP real (#2371) · `DS`
- feat: gate de enforcement anti-flex-solto (ADR 0253 follow-up) (#2373) · `DS`
- feat: paleta de cor oficial auto-gerada do cockpit.css (#2443) · `DS`
- feat: Type ramp --fs-1..9 — ancora unica tipografica (fundacao + gate) [F2 PR-4] (#2493) · `DS`
- feat: eleva o Design System — tokens oklch extraídos da tela-ouro (Passo 1) (#2639) · `DS`
- fix: camada canônica consome o DS — badge/KpiCard/EmptyState tokenizam status (Onda M1) (#2641) · `DS`
- fix: StatusBadge consome o DS — mapa de status app-wide tokenizado (Onda M1) (#2643) · `DS`
- feat: catraca trava a camada canônica em 0 paleta crua (Onda M1 · keystone) (#2644) · `DS`
- feat: tokens de motion — vocabulário de duração + easing (Onda M1 · D8) (#2645) · `DS`
- refactor: unifica tokens de cor em oklch — fim da mistura hsl/oklch (Onda M1 · D1) (#2651) · `DS`
- feat: adoção em massa do DS — 329 tokenizações verificadas por adversário (132 arquivos, 32 módulos) (#2666) · `DS`
- refactor: font-ramp migration — sells/cockpit/fiscal CSS (FORJA-140) (#2870) · `DS`
- refactor: font-ramp — snap dos 15 off-ramp restantes (FORJA-140) (#2873) · `DS`

### oficina — 25 (+9 manutenção)
- feat: handoff design Produção — charter v3 + drawer Vendas×Oficina + arrasto preditivo (#2228) · `DS`
- feat: re-tokeniza Kanban Oficina → família stage DS (UI-Lint #2228) (#2234) · `DS`
- feat: P0-2 — baixa de estoque ao concluir OS + Tier 0 no product_id (#2314)
- design: converge produção caçamba→modelo (A) reparo (camada visível) + primitivos de layout (#2417)
- feat: Fase 2 do kanban reparo — D-04 SLA · D-05 KPI filtra · D-06 persiste · D-07 teclado (#2421)
- feat: filtro funcional por box + mecânico no kanban (eixo de recurso reparo) (#2423)
- feat: Foco re-pivot do kanban (Etapa / Box / Mecânico) (#2424)
- feat: erradica order_type=locacao no backend — schema + write-path (ADR 0265) (#2468)
- fix: sweep ADR 0265 no front (locação erradicada da UI de OS) + Imprimir OS confiável em Chromium (#2477)
- refactor: erradica order_type=locacao dead-code (G-7 ratchet zera) + P4 fixtures (#2475)
- feat: drawer OS V2 — DVI semáforo inline (OS-V2-2) + Fotos & Laudo upload real (OS-V2-1) (#2482) · `DS`
- feat: consulta de placa no cadastro de veiculo (so dados tecnicos) (#2483)
- feat: fechamento total do drawer de OS — OS-V2-3..6 (gate, timeline, StageGate, item inline) (#2485) · `DS`
- fix: card sem OS fala reparo - Abrir OS + vocabulario ADR 0265 (#2492)
- fix: fio usável ponta a ponta — pipeline correto no create, RBAC sem beco, OS órfãs, labels de locação erradicados (ADR 0265) (#2500)
- fix: board cortado sob a sidebar - remove -m-6 orfao (+ sweep das 11 telas com o mesmo anti-pattern) (#2508) · `DS`
- feat: board com KPIs-filtro, menu Visão, atalhos de teclado, imprimir fila e capacidade de boxes — paridade onda 1 Cowork (#2510)
- feat: board paridade total Cowork — KPIs+sublinha, abas de box, cards ricos e botão de ação FSM (Onda 1.5) (#2520)
- fix: drawer da OS nao quebra (tela branca) com item de tipo fora do enum (#2529) · `DS`
- feat: toolbar canon (toggle+Visão na busca), views Grade/Fila, drawer com tipografia canon do protótipo — fecha paridade Cowork (#2530) · `DS`
- feat: Lista e Fila alinhadas ao canon do Board — KPIs clicáveis, filtros consolidados, meta-grid, timeline e sheets canon (#2533)
- refactor: UNIFICA a tela de OS — 1 workspace com 4 views in-page (sem duplicar) (#2544)
- feat: Onda 2 — Fila com detalhe RICO inline (mesmo corpo do drawer, zero duplicação) (#2548) · `DS`
- fix: workspace preenche o shell — chrome fixo, só o conteúdo rola (corta ao rolar) (#2551)
- refactor: remove shim MercosulPlate — fonte unica shared/ (ADR 0251 cumprida) (#2555)

### ci — 22 (+5 manutenção)
- feat: stylelint ratchet anti-drift CSS (fecha G5 · ADR 0209) (#2054)
- fix: quick-sync robusto — composer dump-autoload + ssh-keyscan não-fatal [M] (#2162)
- fix: quick-sync build single-thread (RAYON_NUM_THREADS=1) — evita 500 por estouro de threads [M] (#2183)
- feat: fundação DS — foundation-guard + conformance-gate (gates-only) (#2216)
- fix: auto-heal de índice git corrompido no quick-sync (deploys travados) (#2214)
- feat: schema-squash real pra visual-regression (US-GOV-013) (#2221) · `DS`
- feat: gate visual REAL — Fase A (smoke público /login) · US-GOV-013 (#2224)
- fix: juiz UI usa gpt-4o-mini (projeto OpenAI sem acesso ao gpt-4o) (#2328)
- fix: restaura ratchet PHPStan em main (gerar-boleto + demo-seeder) (#2457)
- feat: gates de papel de token + probes G2/G3/G4 computed-style (PACOTE-Q9 PR-3) (#2489)
- fix: debug-caixa-logs — path absoluto da prod (#2516)
- fix: deploy keyscan não-fatal — espelha fix canônico do quick-sync (#2162) (#2505)
- fix: nightly full-suite roda contra MySQL real, não sqlite vazio (C1 triage Q2) (#2632)
- fix: US-GOV-018 Frente A — harness de DB do nightly (mariadb-client + FK-off só-no-nightly) (#2640)
- fix: re-land US-GOV-020 — grants Frente C + revert A.2 (floor reproduzível) (#2728)
- feat: PR-5 handoff scope-guard (files_json) — escopo duro do handoff (Fase 0 ADR 0283) (#2908)
- fix: cowork-inbox usa COWORK_BOT_PAT (escopo workflows) no push/PR/merge (#2949)
- feat: handoff-sign-submit — workflow_dispatch re-submete handoff pousado (#2951)
- fix: deploy failsafe boot-gated — 503 gracioso em vez de 500 quando código não boota (#2952)
- fix: deconflita insertAuditLog() global que matava o full-suite CT100 (FV-F3) (#2953)
- fix: ct100-fullsuite quarentena fatal de "Parse error" no load (FV-F3) (#2955)
- fix: comentário de regressão visual distingue enforcing de advisory (#3136)

### team-mcp — 22 (+2 manutenção)
- fix: McpToken SoftDeletes — corrige crash deleted_at + completa audit LGPD (#2742) · `DS`
- feat: heartbeat do ingest pra matar SPOF do watcher (#2791) · `DS`
- feat: IngestLivenessService — heartbeat fresh/stale/dead (B-LIVE-CHECK, Leva 2) (#2796) · `DS`
- fix: IngestLivenessService array shapes — restaura TeamMcp 81 (follow-up #2796) (#2797) · `DS`
- feat: Forja PR-2 — re-skin DS v6 da tela CcSessions (#2821) · `DS`
- feat: Forja PR-4 — re-skin DS v6 conservador da tela Team (tokens MCP) (#2824) · `DS`
- fix: corrige link SPEC morto no empty-state CcSessions (#2827) · `DS`
- feat: Forja PR-1 — re-skin DS v6 da tela Tasks (#2819) · `DS`
- feat: Forja PR-3 — cria a tela Scorecard (Saúde · Facts+Checks) (#2823) · `DS`
- feat: Forja PR-A — shell do cockpit do cowork loop (#2840) · `DS`
- feat: Forja — aba Triagem real (fiel ao protótipo) (#2843) · `DS`
- feat: Forja — completa as 5 abas restantes do cockpit (#2848) · `DS`
- refactor: Forja vira hub único — fusão com TeamMcp (sem telas concorrentes) (#2853) · `DS`
- feat: PR-1 loop handoff zero-paste — persistência + ingest assinado (Fase 0 ADR 0283) (#2904) · `DS`
- feat: PR-2 tools handoff-pending + handoff-ack (Fase 0 ADR 0283) (#2905) · `DS`
- feat: PR-4 handoff:stale-alert — pending-velho → inbox ops (Fase 0 ADR 0283) (#2906) · `DS`
- fix: recupera grade 75→79 — cross-tenant guard do CoworkHandoff + OTel no GitMainResolver (#2914) · `DS`
- fix: A2 guard ignora docblock ao grep Cache::flush (HandoffAckTool) (#2916) · `DS`
- feat: handoff zero-paste sync Cowork→repo (PR-6 · ADR 0283) (#2921) · `DS`
- feat: handoff-lever — liga as levers da fila (PR-7 · ADR 0283) (#2924) · `DS`
- feat: badge 'conflito' cruza ack × required checks reais do PR (ADR 0283 Gap 2) (#2927) · `DS`
- feat: fia os botões de lever da Forja ao handoff-lever (PR-7b · ADR 0283) (#2930) · `DS`

### sells — 21 (+10 manutenção)
- feat: navegação por teclado no dropdown de produto + 1ª infra de teste de componente (#2029)
- feat: endereço de entrega 1ª classe consome contact_addresses (US-CRM-078 PR2) (#2104) — ⚠️ REVERTIDO por #2107 (líquido 0)
- feat: CTA "Enviar pra faturamento" source-agnostic (gap balcão · Passo-0 Dani) (#2146)
- feat: adiciona botão de excluir no modal de detalhes da venda (#2167)
- feat: botão de excluir venda (tela React + fallback blade) (#2168)
- feat: botão de excluir venda na tela React (Sells/Show) (#2180)
- feat: botão "Excluir venda" no Sells/Show + paridade de permissão (#2175)
- fix: data da venda automática (hoje) — venda sem data sumia da consulta (#2208)
- fix: parsear transaction_date d/m/Y do Inertia (venda sumia da consulta) (#2213)
- fix: avisar quando a venda é bloqueada (estoque) em vez de falhar silencioso (#2222)
- feat: confirmar Cancelar quando há venda montada (não perde o carrinho) (#2236)
- fix: venda bloqueada fica na tela (não perde o carrinho) (#2230)
- feat: veículo na venda direta de oficina — seletor + cadastro rápido + placa na consulta (ADR 0251) (#2276)
- fix: valor inflado ×100k — num_uf strippava ponto decimal (incidente prod ROTA LIVRE) (#2279)
- feat: enviar venda pra oficina + breadcrumb "onde a venda está" (#2299)
- refactor: breadcrumb oficina read-only + remove botão "Enviar para a oficina" (#2309)
- design: residual chrome azul 220 → roxo 295 — finaliza Trilho A (modelo único de identidade) (#2425)
- fix: exibe desconto da venda no drawer (Resumo de valores) (#2932) · `DS`
- fix: Resumo de valores usa <Inline> — destrava Layout primitives ratchet em main (#2933)
- fix: tamanho selecionado no popover não era adicionado à venda (#2962)
- fix: total de itens no card de produtos + desconto antes do pagamento (#2965)

### deploy — 14 (+4 manutenção)
- fix: forçar invalidação OPcache pra novas mudanças entrarem em prod (#2288)
- fix: remove --no-dev do dump-autoload OPcache — destrava prod (Scribe 500) (#2316)
- fix: set -o pipefail nos comandos críticos remotos — fim do mascaramento (#2321)
- fix: OPcache step usa composer do PATH + Maintenance OFF if:always() (fix outage #2484) (#2486)
- fix: OPcache cache-bust redundante nunca bloqueia o smoke gate (#2487)
- fix: publish corrige path (public/css) + failsafe maintenance (#2523)
- fix: OPcache-invalidate só touch (best-effort), remove dump-autoload flaky (#2524)
- fix: SSH robusto Hostinger — -4 IPv4 + warm-up + retries (causa raiz dos 255) (#2526)
- fix: SSH robusto de verdade — warm-up na PORTA SSH + retry no comando (resolve 255 recorrente) (#2535)
- fix: restaura ConnectTimeout em MINUTOS (canon hostinger.md) — meu fix anterior encurtou e piorou (#2538)
- fix: boot smoke console gate + dump-autoload no force-rebuild (ADR 0216, reincidência 2026-06-17) (#2912)
- fix: ignora ext-sodium no composer (desbloqueia deploys do main) (#2959)
- fix: ignora ext-sodium no composer (desbloqueia pipeline de prod) (#2960)
- fix: Failsafe/Tail só SSHam se maintenance ligou (para de pendurar no flake do pré-check) (#3060)

### outros — 9
- outros: Reforço AppShell + testes + CSS (handoff Cowork 2026-06-02) (#2119)
- outros: Handoff Cowork (Claude Design) — fix KPI Financeiro @container + mirror CRM trio (#2126)
- outros: EVAL-001: evals de comportamento dos agentes (onda 1/3) (#2478)
- outros: ADR 0266: status proposto -> aceito (#2479)
- outros: US-FIN-029 — Unificado: 3 lentes no header (direção [W] 2026-05-31) [F2 PR-1] (#2494)
- outros: PR-A2: Onda A — memória git=SSOT, demove autoridade da espinha (Tier 0 · decisão [W]) (#2877)
- outros: PR-A3: [W] para de colar — memória via cowork-inbox (Onda A · decisão [W]) (#2878)
- outros: PR Onda B: intake via GitHub Issue + congela fila COWORK_NOTES (decisão [W]) (#2880)
- outros: Onda E: ratificação do Protocolo v2 (colapso) — você numera [W] (#2884)

### design — 8 (+8 manutenção)
- design: 37 telas <70 → ≥70 (US-TR-309..314) + XSS sanitize Cms (#2037)
- design: 7 telas overlap — minha versão superset (complementa #2037) (#2038)
- feat: gerador design:review por tela (charter page viva) + gate de frescor [Tier 0 · espera W] (#2078) · `DS`
- feat: crava DS v6 como nome canonico unico (ADR 0249) — fecha GAP-A (#2237) · `DS`
- feat: primitivos de layout (Box/Stack/Inline/Grid/Container/Text) — F3 / ADR 0253 (#2333) · `DS`
- feat: pilot — ServiceOrderItemRow → primitivos de layout (ADR 0253) (#2335) · `DS`
- feat: grade de identidade DETERMINÍSTICO + ratchet (ADR 0254) (#2336) · `DS`
- fix: corrige link local quebrado no DESIGN.md (§16.8) (#2677) · `DS`

### oficina-auto — 6
- feat: check-in de entrada na OS (combustível + avarias) (#2136)
- feat: vistoria DVI → orçamento na OS (botão +orçamento) (#2138)
- feat: gate de aprovação do cliente na OS (WhatsApp) (#2141)
- feat: painel fiscal NF-e/NFS-e na OS (split por natureza) (#2144)
- feat: Kanban de OS de mecânica (port Cowork do carro) + FSM oficina_mecanica_os (#2142)
- feat: W28 importer Firebird fino + reconcilia domínio caçamba→caminhão (ADR 0194) (#2150)

### payment-gateway — 6 (+2 manutenção)
- feat: InterDriver registerWebhook + artisan command [E] (#2155)
- feat: reconciliacao por polling PIX Inter (fallback do webhook) (#2158)
- feat: cert mTLS base64 inline + testes Inter polling (delta #2158) (#2164)
- feat: import recebimentos Inter -> Financeiro (US-PG-008) (#2177)
- feat: cron diario import recebimentos Inter (US-PG-008) (#2179)
- fix: config_json json→longtext alinha schema ao cast (US-GOV-018 frente B) (#2636)

### sdd — 6 (+18 manutenção)
- fix: mata o comentário factualmente falso do full_suite no scorecard (#2957)
- feat: read-side do floor MEDIR→GOVERNAR (ADR 0279 PR-1 · US-GOV-023) (#2958)
- feat: write-side do floor MEDIR→GOVERNAR (ADR 0279 PR-2) (#2961)
- feat: A5 batch 1 — 16 anchors reais (coverage 5.4→7.5%) (#2970)
- feat: conectar 2 métricas GT (drift_alarms + backfill) + 6ª catraca anchor-lint (#3140)
- feat: tripwire qualitativo do nightly (diff por-arquivo + classe de falha · P15) (#3158)

### test — 6 (+3 manutenção)
- fix: PagarmeDriverTest makeCred — Crypt::encryptString pra config_json (RC-9) (#2713)
- fix: concede permissões no beforeEach ClienteDrawerAutosaveTest RC-17 (#2718)
- fix: guarda sqlite no afterEach de 4 telas Governance (anti-corrupção floor SDD) (#2746)
- fix: guarda sqlite-only em 20 corruptores Whatsapp (floor SDD · −20) (#2753)
- fix: guarda sqlite-only em 28 corruptores Jana/Mcp + Copiloto (floor SDD · −28) (#2756)
- fix: guarda os 9 corruptores genuínos restantes (floor SDD → 0) (#2759)

### forja — 5
- fix: fusão real — Jana não rouba /team-mcp/* (follow-up adversário) (#2855) · `DS`
- refactor: marca única 'Forja' — atalho sidebar + breadcrumbs (#2857) · `DS`
- fix: tab-strip não colapsa nas telas absorvidas (shrink-0) (#2862) · `DS`
- feat: aba MCP projeta handoffs reais de cowork_handoffs (ADR 0283 F1) (#2913) · `DS`
- fix: instrumenta ForjaMcpService com OtelHelper::span — recupera TeamMcp 78→79 (D9.a) (#2915) · `DS`

### sells/create — 5
- feat: paridade Edit — nota interna (staff_note) + assinatura recorrente (is_recurring) (#2239)
- feat: paridade Edit — endereço de cobrança (customer_secondary_address) (#2241)
- feat: paridade Edit — anexar documento à venda (sell_document upload) (#2244)
- feat: paridade Edit — desconto per-linha R$/% (line discount_type) (#2242)
- feat: paridade Edit — IMEI/serial inline por linha (imei_number) (#2243)

### ui — 5 (+1 manutenção)
- feat: Onda G — badge variants semânticas (status pills) (#2025) · `DS`
- feat: F4 — congela PageHeader antigo (ratchet) + política incremental (#2330) · `DS`
- fix: corrige casing import @/Lib/utils em resizable.tsx (TS1149) (#2334) · `DS`
- feat: BR inputs canonicos — NumericInputPtBR promovido + DocumentInput + PhoneInput (#2540) · `DS`
- fix: dark mode real — ativa por [data-theme=dark], não só .dark (ADR 0281) (#2826) · `DS`

### brief — 4
- fix: detector de cycle drift distingue 3 causas (honesto, não cala) (#2410)
- feat: linha SDD + kill-switch GOVERNANCE_SDD_BRIEF_LINE no Daily Brief (GT-G8) (#2630)
- feat: leases ativos + nudge 'claim antes de pegar' no Daily Brief — C2+C3 Leva 2 (#2800)
- feat: skills:tier-review — loop telemetria→tier (Parte B T7, ADR 0095) (#3077)

### contrato-de-tela — 4
- feat: catraca semântica + hardening (anti backdoor-de-prosa) + base do eixo escopo/verdict (ADR 0286 §5) (#2986) · `DS`
- fix: (A) claim honesta (menção≠handling) + 3 fixes do 2º painel adversarial (#2992) · `DS`
- feat: Onda 2 — resolução de escopo + não-vazamento Tier 0 (P0) (#2993) · `DS`
- fix: catraca dispara em backend + hint honesto + motor de escopo dormente (#2999) · `DS`

### mcp — 4 (+1 manutenção)
- feat: D1 — mcp_work_leases, lease de coordenação anti-vazamento (ADR 0278) (#2781)
- feat: automação deploy main→CT100 + sentinela de drift (incidente 2026-06-17) (#2917)
- fix: git na imagem octane pra /health/auth expor o commit servido (#2919)
- feat: /api/mcp/version com token dedicado pro drift sentinel (least-privilege) (#2922)

### memory — 4 (+7 manutenção)
- feat: espinha STATUS + índice temático T1–T9 + ADRs 0236/0237 (ex-0200/0201) (#1990)
- fix: resolve conflito de merge em Admin/UI-CATALOG.md (#2388)
- fix: quota frontmatter date/version em SPECs pro schema gate (#3095)
- fix: zera violações do SPEC schema gate no Dashboard (#3097)

### nfse — 4 (+2 manutenção)
- fix: ambiente de emissão resolvido por-business (cutover fiscal biz=164) (#2147)
- fix: FK nfse_emissao_id INT casa com nfse_emissoes (erro 3780, destrava suíte MySQL) (#2218)
- fix: instrumenta cancelar() com OtelHelper::spanBiz nfse.cancelar (D9 Wave 28) (#2678)
- fix: OtelHelper spanBiz extras array + DatabaseTransactions teste RC-16 (#2717)

### prototipo-ui — 4 (+8 manutenção)
- feat: PROCESSO_MEMORIA_CC + DS-GUARD/integrity (handoff Cowork) (#2116) · `DS`
- feat: kit DS v6 — showcase + receita + gabarito-vendas (#2165) · `DS`
- feat: registra caixa-unificada no cowork-map (destrava ingest da Inbox) (#3040) · `DS`
- feat: cowork-map v2 — handoff completo por prefixo (18 telas + handoff-buckets) (#3042) · `DS`

### recurring-billing — 4 (+2 manutenção)
- feat: ativa botão Nova assinatura (drawer de criação) (#2369) · `DS`
- feat: wira PUT editar cobrança da assinatura (Onda 23) (#2376)
- feat: botão Editar no drawer → PUT cobrança (Onda 24) (#2377) · `DS`
- feat: rb:generate-invoices command + scheduler daily (US-RB-003) (#2384)

### tests — 4 (+1 manutenção)
- fix: green full-suite Pest discovery — guard invokePrivate + remove redundant uses() (#2263)
- fix: eliminate const-collision warnings (+ divergent-value bug) in full-suite Pest discovery (#2266)
- fix: guard FIN_* const collision dos novos Unificado*Test (#2269)
- fix: Ponto CLT roda de fato (#[Test] + registra Tests/Unit) + endurece guard @test (#2768)

### visreg — 4 (+1 manutenção)
- feat: auth bridge cross-process — destrava smoke das telas autenticadas (Fase B) (#2317)
- feat: VisregTenantSeeder minimal — ativa smoke autenticado (US-GOV-013 Fase B) (#2319)
- feat: amplia smoke autenticado pro núcleo-6 de retenção (US-GOV-013 Fase B) (#2320)
- feat: ativa Clientes no gate via MWART_CLIENTE_INDEX — fecha o núcleo-6 (8 telas) (#2323)

### audit — 3 (+1 manutenção)
- feat: worklist de auditoria paralela — harness read-only + GOLDEN-REFERENCE + consolidador (#2035)
- fix: corruptor-linter v2 honesto + meta-teste 2 lados (a medição do floor mentia ~48%) (#2749)
- fix: corruptor-linter v3 — dual-mode if(sqlite){drop} + correção doc (CONVERT→GUARD) (#2758)

### components — 3
- refactor: dominio single-modulo sai da global para Pages/<Mod>/_components (#2539) · `DS`
- refactor: shared/ vira FLAT — CHECK 3 no guard + MOVE shared/ponto → Pages/Ponto/_components (#2547) · `DS`
- refactor: renomeia colisoes de nome (FiscalModuleTopNav, KbCommandPalette) + aperta catraca reuse 25→21 (#2549) · `DS`

### designsystem — 3 (+3 manutenção)
- feat: backlog.mjs + US-_DESIGNSYSTEM-014..018 (backlog de fixes da worklist) (#2036)
- fix: US-014 R9 <main> aninhado -> div (12 telas) + R3 scorer FP (#2039)
- fix: US-015 emoji -> lucide em 19 telas (AP6) [visivel - gate ADR 0114] (#2041)

### errors — 3
- feat: Fase 1 — régua de severidade na origem + cano do S0 (E-1) (#2939)
- feat: Fase 2 — dedup de erros + rate-limit por contador (E-2) (#2940)
- feat: Fase 2 — auto-resolução (retry/backoff/dead-letter) (E-3) (#2946)

### handoff — 3 (+77 manutenção)
- feat: catraca de integridade do handoff (fila ↔ prompts) — gate advisory (#2865)
- feat: + C3 (cabeçalho fundido) na catraca de integridade do handoff (#2869)
- feat: publisher Cowork→repo — fecha o 1º hop zero-paste (ADR 0285) (#2929)

### reuse — 3
- refactor: dedup fmtRelative em @/Lib/datetime-br (gate reuse verde) (#2831)
- refactor: kb/Index reusa fmtRelative canônico de @/Lib/datetime-br (#2832)
- refactor: renomeia 2 locais fmtRelative divergentes (fmtLastUsed / fmtAgoNoDate) (#2835)

### session — 3 (+11 manutenção)
- fix: quote date no frontmatter (schema /date must be string) (#2351)
- fix: related_adrs como slugs (corrige gate session-log de #3009) (#3010)
- fix: authors=['C'] — fecha o gate session-log (fim da cadeia #3009/#3010) (#3011)

### admin — 2 (+1 manutenção)
- feat: US-ADM-021 — tela Admin/MapaTelas (mapa vivo spec-driven) (#2413)
- refactor: HealthPanelV4 usa fmtRelative canônico (data absoluta >7d) (#2837)

### ads — 2 (+1 manutenção)
- fix: model_used reflete OpenAI (provider ativo) em vez de claude hardcoded (#2303)
- fix: registra ads:health no AdsServiceProvider (D9.c) (#2649)

### backlog — 2 (+2 manutenção)
- feat: materializa 22 US plano-perdido (batch 2026-06-20) (#3090)
- feat: US-FIN-060 (boleto-OCR 403) + US-WA-318 (DNS mídia 48k) — A1/A2 do sweep (#3171)

### charters — 2 (+2 manutenção)
- fix: conforma frontmatter dos 9 charters incompletos ao charter.schema.json — SDD Semana-0 Charters (#2664) · `DS`
- feat: related_us canônico + lint + migra 3 legacy (P14) (#3157) · `DS`

### compras — 2
- design: chrome navy → roxo canon + paleta hex → tokens DS (modelo único de identidade) (#2427)
- fix: restaura menu "Lista de compras" no sidebar (gated por compras_module) (#2903) · `DS`

### contacts — 2
- feat: tipo "Outros" como categoria canônica (ADR 0246) (#2205)
- fix: incluir "other" nos whitelists $types e $inertiaTypes (ADR 0246) (#2274)

### crm — 2 (+2 manutenção)
- fix: vendas do cliente aparecem no drawer (SalesTab self-fetch) (#2437) · `DS`
- fix: avisar "já existe cadastro" em vez de erro 500 ao salvar CNPJ duplicado (#2444)

### docs — 2 (+1 manutenção)
- fix: corrige 5 links internos quebrados (slug + profundidade) (#3147)
- fix: corrige 12 links decisions/ com slug defasado em 5 SPECs (#3152)

### essentials — 2 (+2 manutenção)
- fix: stored-XSS no chat de mensagens (+ href scheme no Jana) (#2891)
- fix: sanitiza HTML da KB com HTMLPurifier antes do dSIH (#2895)

### hooks — 2
- feat: gatilho block-test-fora-ct100 — testes/PHPStan só no CT 100 (#2081)
- fix: pii-redactor escaneia só git commit, libera debug por CPF/CNPJ (#2683)

### lint — 2
- fix: remove 28 unused eslint-disable directives + refresh baseline (F6) (#2326)
- fix: desliga no-undef em TS (falso-positivo) — baseline 1202→1073 (F6) (#2327)

### nfe — 2
- fix: retry pós-falha não viola nfe_emissoes_biz_tx_unique (#2120)
- fix: retry-with-backoff em erro de transporte SEFAZ (connection reset) (#2125)

### otel — 2
- fix: provider compatível com SDK atual + Pest provando o boot (T1.b) (#2074)
- fix: session guard + uses(TestCase) nos Settings tests — RC-4+RC-5 SDD floor (#2711)

### phpstan — 2 (+3 manutenção)
- fix: conserta 9 erros level-5 vazados pra main + encolhe baseline (Gov + Brief + Jana) (#1961)
- fix: remove 6 checagens redundantes (destrava ratchet do main) (#2229)

### proposta — 2
- proposta: Protocolo v2 (colapso) — aprovado por [W] (artefato pra ratificação) (#2871)
- proposta: Protocolo v2 · Onda A — memória git=SSOT + carve-out de segurança (decisão [W]) (#2874)

### proveniencia — 2 (+2 manutenção)
- feat: PR-2 — `--map` gera o mapa protótipo→prod (mata o SYNC_LOG manual) (#2976)
- feat: PR-4 bundle-lint — esteira ≠ armazém (régua 6) + apaga resíduo (#2978)

### purchase — 2
- fix: restaura atalho Etiquetas na listagem de Compras [F] (#2898)
- fix: quota last_validated no RUNBOOK (follow-up #2898) [F] (#2899)

### qa — 2 (+1 manutenção)
- feat: especialista de tela + catraca de cobertura sustentável (proposta) (#2215)
- feat: screen-grade enforcement — seed 222 scorecards + catraca anti-regressão (#2223)

### a11y — 1 (+2 manutenção)
- feat: Fase 2 — axe-core runtime (jsdom) nos componentes canon (#2361)

### adr — 1 (+26 manutenção)
- feat: ADR 0240 task ledger git-native + antifragilidade (aceito) (#2022)

### atendimento — 1
- fix: dark flipa sem F5 (toggle sincroniza data-theme) + thread usa token (#3044)

### cash-register — 1
- fix: escopar business_id no fechar-caixa e getRegisterDetails (Tier 0) (#2708)

### charter — 1 (+1 manutenção)
- fix: Financeiro/Fluxo related_adrs + last_validated válidos no schema (#2901) · `DS`

### cowork — 1 (+6 manutenção)
- feat: carteiro do 1º hop — bin/cowork-postman.sh (ADR 0283/0285) (#2935) · `DS`

### deps — 1
- fix: remove ajv/ajv-formats acidentais (quebrava npm ci / Vite build no main) (#2398)

### estoque — 1 (+1 manutenção)
- feat: documento raiz de estoque + fix R1 (consumo FSM auditável) (#2258)

### featureflag — 1
- fix: GrowthBook vazio/down cai no fallback (tela de venda revertia pro Blade) (#2220)

### fiscal — 1
- feat: FiscalStatusBadge unificado (NFC-e/NF-e/NFS-e) (#2130)

### fix — 1
- fix: ExportZip signature braces + OndaComments skip + DetectDrift register (RC-12+13+14) (#2715)

### foundations — 1
- feat: §TEMPERO na fundação — sombras/ease/atmosfera + atmosfera no shell (FA-1) (#2569)

### gate — 1
- feat: scheme ratchet — oráculo de conteúdo parcela 2 (anti href/scheme cru em .tsx) (#2931)

### growthbook — 1
- fix: default do host admin deriva de GROWTHBOOK_API_HOST (evita 404 do frontend) (#2108)

### hook — 1
- fix: endurece R10 — falso-positivo merge, ancora publish, cobre PowerShell (#3065)

### infra — 1 (+6 manutenção)
- feat: cowork-inbox write-path de código com review-gate (Onda D-core) (#2876)

### layout — 1
- fix: destrava o ratchet ADR 0253 no main (drawers de #2821/#2824) (#2834)

### main — 1
- fix: RESTAURA codebase apagado pelo squash do #2413 🚨 (#2415)

### mcp-deploy — 1
- fix: self-update.sh +x + recreate por SHA-do-container (root cause do código-velho) (#3013)

### memory-health — 1
- feat: Check L — ADR vivo-mas-proposto (integridade proposto×realizado) (#3127)

### memory-schema-gate — 1
- fix: try/catch matter() + conserta charters que crashavam o gate (#2902)

### migracao — 1
- fix: status WR INATIVO* → cancelado (precedência sobre datapagto) [M] (#2174)

### multi-tenant — 1
- fix: recalibra guard business_id + ativa no CI (Tier 0) (#2121)

### oficinaauto — 1
- feat: view Fila (master-detail) na Index de OS (#2160)

### project-mgmt — 1 (+1 manutenção)
- feat: Forja PR-5a — Triagem/Analista (dossiê + aprovar/rejeitar/fundir) (#2829)

### recurringbilling — 1 (+2 manutenção)
- feat: re-skin DS v6 — stone+roxo canon (charter Cobrança Recorrente) (#2212) · `DS`

### reference — 1
- fix: corrige 3 fatos stale (MCP no CT100, Baileys OUT, Reverb→Centrifugo) (#2380)

### repair — 1
- fix: resolve conflito de merge commitado no CHANGELOG.md (#2807)

### revert — 1
- revert: PR2 endereço na venda (#2104) — incidente regressão cliente pós-merge (#2107)

### roadmap — 1 (+2 manutenção)
- fix: project: COPI nos 3 SPECs — destrava cycle_id (fecha furo #6 do pipeline) (#3166)

### sells/edit — 1
- fix: pré-fill lê aliases flat reais — corrige "venda em branco" (incidente ROTA LIVRE) (#2501)

### shell — 1
- feat: sidebar vira drawer flutuante no mobile (≤768px) (#2887) · `DS`

### skills — 1 (+2 manutenção)
- fix: corrige drift de path Modules/Copiloto → Modules/Jana (3 skills) (#3063)

### smoke — 1
- feat: harness de aceitação do veredito-ledger (`npm run smoke:veredito`) (#3001)

### spec — 1
- fix: renumera colisões reais de id US (RecurringBilling 9×001, SELL-010 dup) (#3121)

### staging — 1
- fix: deploy.sh força restart + optimize:clear (recarrega PHP) (#2145)

### superadmin — 1
- fix: registra comando superadmin:health no ServiceProvider (#2647)

### vestuario — 1 (+1 manutenção)
- fix: cria DataController com entry de sidebar (etiquetas) (#2673) · `DS`

### whatsapp/tests — 1
- fix: resolve makeChannel() redeclare blocking suite bootstrap (#2251)

### xss — 1
- fix: dangerouslySetInnerHTML em campos de dado (Todo description + contact_address) (#2893)

### adr-0296 — 0 (+1 manutenção)

### arte — 0 (+1 manutenção)

### auditoria — 0 (+1 manutenção)

### automations — 0 (+1 manutenção)

### blueprint — 0 (+1 manutenção)

### browser — 0 (+1 manutenção)

### canon — 0 (+2 manutenção)

### casos — 0 (+2 manutenção)

### casos-gate — 0 (+1 manutenção)

### casos-results — 0 (+1 manutenção)

### chore — 0 (+1 manutenção)

### code-notes — 0 (+1 manutenção)

### core — 0 (+1 manutenção)

### css — 0 (+2 manutenção)

### decisions — 0 (+4 manutenção)

### design-index-gate — 0 (+1 manutenção)

### dominio — 0 (+1 manutenção)

### dominio-guard — 0 (+1 manutenção)

### dossier — 0 (+1 manutenção)

### e2e — 0 (+2 manutenção)

### e2e-gate — 0 (+1 manutenção)

### era-sqlite — 0 (+1 manutenção)

### esteira — 0 (+1 manutenção)

### eval — 0 (+2 manutenção)

### feedback — 0 (+4 manutenção)

### financeiro-pest — 0 (+1 manutenção)

### fsm — 0 (+2 manutenção)

### fundacao — 0 (+2 manutenção)

### fv-f1 — 0 (+1 manutenção)

### gates — 0 (+1 manutenção)

### gitignore — 0 (+2 manutenção)

### grades — 0 (+1 manutenção)

### guard — 0 (+1 manutenção)

### identidade — 0 (+1 manutenção)

### incident — 0 (+2 manutenção)

### inventory — 0 (+1 manutenção)

### kb — 0 (+1 manutenção)

### layout-baseline — 0 (+1 manutenção)

### loop — 0 (+1 manutenção)

### mapa — 0 (+1 manutenção)

### memoria — 0 (+1 manutenção)

### meta-gates — 0 (+1 manutenção)

### mwart — 0 (+2 manutenção)

### nfe-brasil — 0 (+1 manutenção)

### nfebrasil — 0 (+1 manutenção)

### nfse,oficina — 0 (+1 manutenção)

### onda-q1 — 0 (+1 manutenção)

### ondas-q2-q5 — 0 (+1 manutenção)

### processo — 0 (+1 manutenção)

### proibicoes+handoff — 0 (+1 manutenção)

### proposal — 0 (+4 manutenção)

### protocol — 0 (+2 manutenção)

### protocolo — 0 (+1 manutenção)

### quality — 0 (+1 manutenção)

### quarentena — 0 (+1 manutenção)

### ragas — 0 (+1 manutenção)

### regua-9 — 0 (+1 manutenção)

### repo — 0 (+1 manutenção)

### routes — 0 (+1 manutenção)

### rules — 0 (+2 manutenção)

### runbook — 0 (+1 manutenção)

### secrets — 0 (+1 manutenção)

### sessao — 0 (+1 manutenção)

### skill — 0 (+1 manutenção)

### sync — 0 (+1 manutenção)

### sync-log — 0 (+1 manutenção)

### util — 0 (+1 manutenção)

### utils — 0 (+1 manutenção)

### visual-regression — 0 (+1 manutenção)

