---
slug: 0179-cliente-drawer-760px-substitui-show-fullpage
number: 179
title: "Cliente — drawer lateral 760px substitui Show.tsx full-page (paradigma cadastral 8 tabs)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-21"
accepted_at: "2026-05-21"
accepted_via: "Wagner aprovou em sessão Wave A-G+Z 2026-05-21 16:40 BRT — comando exato: 'aprovado merge'"
quarter: 2026-Q2
module: crm
tags: [paradigma, cliente, drawer, mwart, cowork-blueprint, tier-A, multi-tenant]
supersedes: []
superseded_by: []
related: ["0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios", "0104-processo-mwart-canonico-unico-caminho", "0107-emendation-0104-visual-comparison-gate-f3", "0110-cockpit-pattern-v2-canon-list-detail", "0114-prototipo-ui-cowork-loop-formalizado", "0149-mwart-screen-pattern-reuse-cowork", "0167-errata-0130-indice-handoff-historico-longo", "0177-mwart-excecao-cliente-show-wave-paralela"]
charter_impact:
  - "Pages/Cliente/Show.charter.md v2 → status: superseded"
  - "Pages/Cliente/Index.charter.md draft → published v3 com drawer_pattern: 760px-lateral + 8 tabs"
amended-by: []
---

# ADR 0179 — Cliente: drawer lateral 760px substitui Show.tsx full-page (paradigma cadastral 8 tabs)

## Contexto

Em 2026-05-21 mergeamos a **Wave Final paridade Cliente/Show** (PRs #1298-1307, formalizada por [ADR 0177](0177-mwart-excecao-cliente-show-wave-paralela.md)) que entregou `resources/js/Pages/Cliente/Show.tsx` como página full-page com 8 sub-tabs operacionais sob `Pages/Cliente/_show/`: `LedgerTab`, `SalesTab`, `PaymentsTab`, `DocumentsTab`, `ActivitiesTab`, `PessoasContatoTab`, `SubscriptionsTab`, `RewardPointsTab`. A wave fechou a paridade funcional 40%→85% vs Blade legacy e justificou um `/mwart-override` no gate visual regression. O paradigma vigente é: clicar num cliente em `/cliente` carrega `/cliente/{id}` que renderiza `Show.tsx`.

Em paralelo, o protótipo Cowork em `prototipo-ui/prototipos/clientes/` (HTML + 13 .jsx, score KB-9.75 9,4/10 — Refinos #1 + #2 + #3) propõe paradigma diferente: **drawer lateral 760px** abrindo a partir de `Index.tsx`, com **8 tabs cadastrais** (Identificação · Contato · Endereço · Comercial · Classificação · OSs · IA · Auditoria). O `HANDOFF_CLIENTES.md` (381 linhas) detalha schema BR completo, 4 endpoints IA, 6 dropdowns de filtro, FrescorPill, avatar HSL determinístico, Spatie ActivityLog reuso.

O Charter atual `Pages/Cliente/Show.charter.md` v2 lista 4 das tabs novas (Atividades, Pessoas, Assinaturas, Reward Points) como **Non-Goals explícitos** — bandeira amarela: a Wave Final 2026-05-21 invadiu os Non-Goals da própria carta sem atualizá-la. O Charter `Pages/Cliente/Index.charter.md` está em `status: draft` desde 2026-05-09, esperando essa decisão de paradigma.

A cliente alvo é **Larissa biz=4 ROTA LIVRE**, monitor 1280×1024, não-técnica — drawer 760 + AppShellV2 sidebar 240 + main padding cabe sem scroll horizontal. Wagner é dev/PO; biz=1 WR2 SC é canary de prod.

Wagner aprovou opção (A) — "refazer o paradigma" — em 2026-05-21 após dossiê wagner-understand completo em `memory/sessions/2026-05-21-understand-cliente-drawer-760px-opcao-A.md` (~300 linhas, status `ready-for-execution`). Esta ADR formaliza o paradigma novo, supersede o Charter Show v2 obsoleto, e libera Wave A-G de execução.

## Decisão

Substituir página full-page `Show.tsx` por **drawer lateral 760px** abrindo a partir de `Index.tsx`, com **8 tabs cadastrais** conforme protótipo Cowork (R2 cópia literal aprovada — [ADR 0149](0149-mwart-screen-pattern-reuse-cowork.md) pattern reuse blueprint).

**Decisões refinadas Q1-Q4 do Wagner (2026-05-21):**

- **Q1 — Show.tsx deleta agora (sem sunset 30d, sem modo "ficha completa")**: no MESMO PR do drawer, `Show.tsx` é removido. Os 8 sub-componentes Wave Final (`_show/LedgerTab`, `SalesTab`, `PaymentsTab`, `DocumentsTab`, `ActivitiesTab`, `PessoasContatoTab`, `SubscriptionsTab`, `RewardPointsTab`) precisam **caber dentro da tab "OSs" do drawer 760px** via sub-tabs aninhadas (vertical pills left 120px + content 640px) OU dropdown "Ver: [SalesTab ▼]". Decisão final de layout fica na Wave D quando chegar.

- **Q2 — Edição inline com autosave on blur**: 5 endpoints PATCH cadastrais (`/cliente/{id}/identificacao`, `/contato`, `/endereco`, `/comercial`, `/classificacao`) com debounce 800ms, optimistic UI + rollback em 4xx/5xx. Sem Edit.tsx separado.

- **Q3 — ALTER TABLE `contacts` aditivamente**: 1 migration adiciona 7+ colunas NULL (`tipo`, `fantasia`, `ie`, `rg`, `nascimento`, `cargo`, `tel2`, `canal`, `tabela_preco`, `pgto`, `obs_comercial`, `segmento`, `tags` JSON, `vip`, `favorito_users` JSON, `site`). Reversível. Não toca core UPOS — sem tabela `clientes` paralela.

- **Q4 — Tab IA default ON pra todos**: sem gate quota/permission inicial. 3 endpoints Jana (`POST /cliente/{id}/ia/resumo`, `/ia/segmento`, `/ia/proxima-acao`) consomem `LaravelAiSdkDriver` + 1 determinístico (score risco — **NÃO** chama LLM). Wagner pode regredir pra gate `copiloto.admin.custos` depois se custo Brain B virar problema.

**Mecânica complementar:**

- Deeplink `/cliente/{id}` faz redirect 302 → `/cliente?contact_id={id}&tab=identificacao` (preserva URL compartilhável; drawer abre automático on-load).
- Rota legacy `/contacts/{id}` Blade permanece intacta (dual-render via `config('mwart.cliente_show.enabled')`).
- Charter `Pages/Cliente/Show.charter.md` v2 vira `status: superseded` + `superseded_by: [Pages/Cliente/Index.charter.md v3]`.
- Charter `Pages/Cliente/Index.charter.md` publica v3 `status: live` com `drawer_pattern: 760px-lateral` + 8 tabs.
- Spatie ActivityLog ^4.8 (composer já tem) reusa em tab Auditoria — `forSubject(Contact)` via `Modules/Auditoria/Services/AuditEntryService`. **NÃO** criar audit_log paralelo.
- Tab IA usa `Modules/Jana` (NÃO `Modules/Copiloto` — não existe). Botão header "Falar com Copiloto →" aponta `/jana/chat?context=cliente:{id}`.
- Novo `Modules/Crm/Services/BrLookupService.php` proxy ViaCEP/BrasilAPI server-side com cache Redis (CEP 90d, CNPJ 30d) — **OBRIGATÓRIO** pra evitar rate limit do biz=4 Larissa.
- Gating canary: `MWART_CLIENTE_INDEX=true` biz=1 primeiro; rollout biz=4 só após smoke verde 48h.

## Alternativas consideradas

- **(A) Drawer 760px lateral substitui Show.tsx** ← ESCOLHIDA. Paridade visual protótipo Cowork score 9,4/10. Formaliza Wave Final 2026-05-21 que invadiu Non-Goals do Charter v2. 1 paradigma único.
- **(B) Manter Show.tsx full-page + adicionar 4 tabs cadastrais novas** — descartada. Paridade parcial; perde a pele drawer; mantém 2 paradigmas (full-page + drawer 480 light atual) competindo no mesmo módulo.
- **(C) Híbrido — Index drawer + Show.tsx fallback "ficha completa"** — Q1 explicitamente rejeitada por Wagner. Sunset zero é mais limpo; manter Show como fallback dobra superfície de manutenção sem ganho proporcional.
- **(D) Aplicar pele Cowork só na listagem (Index)** — descartada. Deixa ~70% do gap visual em aberto (8 tabs cadastrais, IA, Auditoria, FrescorPill no detail — todos no protótipo aprovado 9,4/10).

## Consequências

### Positivas

- Paridade Cowork 9,4/10 entrando em prod (avatar HSL hash determinístico, FrescorPill 4 estados, tag chips coloridas semânticas, 8 tabs cadastrais, IA Brain B, Auditoria LGPD).
- Charter Show v2 obsoleto **formalizado** — Non-Goals invadidos pelos PRs #1304-1307 ficam justificados retrospectivamente via supersede.
- Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) reforçado em 10 endpoints novos (5 autosave + 2 lookup + 3 IA).
- Spatie ActivityLog v4.8 já instalado — zero código novo de audit log; LGPD Art. 18 atendido por composição.
- **1 paradigma único** no módulo Cliente (drawer 760) — elimina dual-render full-page + drawer 480 que existiria no caminho (B).
- Pattern reuse [ADR 0149](0149-mwart-screen-pattern-reuse-cowork.md) — Fornecedor pode seguir igual no futuro sem novo Cowork round.

### Negativas / riscos

- **7-8 PRs sequenciais** (Waves A-G + Z) — ~70h elapsed; janela 2 semanas Wagner solo ou ~1 semana com Felipe paralelizando Wave G.
- **Sub-tabs aninhadas em tab "OSs" 760px é apertado** — vertical pills 120 + content 640 deixa pouco espaço pra DataTable Sales/Payments. Risco visual Wave D; mitigação: Wave D pode escolher dropdown "Ver: [tab ▼]" se layout pills não couber.
- **Larissa biz=4 1280×1024** — drawer 760 + AppShellV2 sidebar 240 + main padding ≈ 1024px. Pest charter test obrigatório em viewport 1280×1024 sem scroll horizontal.
- **ViaCEP/BrasilAPI sem proxy server-side = rate limit em prod biz=4** — Larissa faz ~30 cadastros/dia; sem cache Redis dispara rate limit ViaCEP/IP em pico. Mitigação obrigatória: `BrLookupService` cache Redis 90d/30d antes de Wave C ir pra prod.
- **Show.tsx deleta zero-sunset** — rollback caro se bug aparecer pós-merge. Mitigação: gating `MWART_CLIENTE_INDEX=true` canary biz=1 primeiro; rota legacy `/contacts/{id}` Blade dual-render via `config('mwart.cliente_show.enabled')` permanece como fallback emergencial.
- **Tab IA Default ON sem gate** — custo Brain B (Sonnet/Opus) pode disparar se hit rate alto. Mitigação: telemetria `Modules/Jana/Services/CustosService::log()` desde dia 1; Wagner regride pra gate se custo/dia ultrapassar baseline.
- **Charter v2 → superseded em supersede chain** — primeiro caso no projeto de Charter de tela inteira sendo formalmente substituído por Charter de tela diferente. Precedente; documentar em RUNBOOK.

## Plano de implementação (7 Waves + Z)

| Wave | Entrega | Estimate IA-pair | Bloqueia |
|---|---|---|---|
| **A** | Charter v3 publish + ADR 0179 (este, aprovação Wagner) + RUNBOOK-cliente-drawer-760.md + visual-comparison.md | 2.5h | Wagner aprova `accepted` → libera B |
| **B** | Migration aditiva `contacts` + tabela `anotacoes` + esqueleto drawer 760 (8 tabs skeleton vazio) + deeplink redirect | 4h | C-F |
| **C** | 5 tabs cadastrais inline autosave + `BrLookupService` + endpoints `lookup/cep` + `lookup/cnpj` proxy Redis | 11h | — |
| **D** | Tab OSs wrapper das 8 sub-tabs Wave Final (sub-tabs aninhadas OU dropdown — decisão Wave D) | 3h | — |
| **E** | Tab IA 4 cards via `Modules/Jana/Services/Ai/LaravelAiSdkDriver` (Default ON, sem gate) + 3 endpoints + RiscoController determinístico | 6h | — |
| **F** | Tab Auditoria timeline Spatie ActivityLog LGPD Art. 18 + Exportar log | 3.5h | — |
| **G** | Listagem turbinada (avatar HSL, FrescorPill, 6 filtros, tag chips, Star pessoal, Saldo destacado, Export) | 7h | — |
| **Z** | Smoke Brave prod biz=1 + brief-update + handoff + SYNC_LOG.md | 1.5h | — |

**Total:** ~35h IA-pair × margem 2x ([ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md)) = **~70h elapsed** ≈ **2 semanas Wagner solo** OU **~1 semana com Felipe paralelizando G** enquanto Wagner faz C/E.

## Pegadinhas / não inventar (Tier 0)

- **`business_id` global scope** obrigatório em TODA query nova (lookup CNPJ/CEP cache, 3 endpoints IA, Auditoria, 5 autosave). `withoutGlobalScopes()` proibido sem comentário `// SUPERADMIN: <razão>`. ([ADR 0093](0093-multi-tenant-isolation-tier-0.md) IRREVOGÁVEL).
- **PII**: `tax_number_masked` em todo defer payload; telefone mascarado server-side (chain leak Larissa→cliente final do cliente).
- **Charter Show.charter.md v2 supersede formal** — `status: superseded` + `superseded_by: [Pages/Cliente/Index.charter.md v3]`. Não deletar arquivo; manter como histórico.
- **Spatie ActivityLog v4.8 reusa** — composer já tem; usar `Activity::forSubject($contact)` via `Modules/Auditoria/Services/AuditEntryService`. **NÃO** criar tabela `audit_log` paralela.
- **`Modules/Copiloto` NÃO existe** — tudo é `Modules/Jana`. Botão "Falar com Copiloto →" aponta `/jana/chat?context=cliente:{id}`, NÃO `/copiloto/*`.
- **ViaCEP/BrasilAPI proxy server-side obrigatório** com cache Redis (CEP 90d, CNPJ 30d) — biz=4 Larissa furaria rate limit `consulta/IP`. NUNCA chamar do client.
- **Worktree atual**: `D:/oimpresso.com/.claude/worktrees/frosty-greider-83ab2f` — TODOS Edits via path absoluto desse worktree (lição R8 — PR #1032 quase perdeu 4h).
- **LICOES_F3_FINANCEIRO_REJEITADO.md PRÉ-FLIGHT** obrigatório antes Wave C-F (6 meta-anti-padrões + 15 técnicos catalogados — sessão 2026-05-09 Wave Financeiro rejeitada).
- **R2 cópia literal aprovada** ([ADR 0149](0149-mwart-screen-pattern-reuse-cowork.md)) — PR pode ultrapassar 300 LOC com label `design-literal-copy` + link prototype Cowork. Override formal do `commit-discipline`.
- **`Inertia::defer` + partial reload por tab** — cada tab carrega via `Inertia::defer` + `<Deferred data="tabIdentificacao">`; trocar tab faz `router.reload({ only: ['tabContato'] })`. ([ADR 0107](0107-emendation-0104-visual-comparison-gate-f3.md) gate F3).
- **MWART exceção override Show** — esta ADR substitui o paradigma que [ADR 0177](0177-mwart-excecao-cliente-show-wave-paralela.md) entregou. ADR 0177 permanece `aceito` (justifica o `/mwart-override` retrospectivo); ADR 0179 dispara a deleção do Show.tsx que ADR 0177 entregou.
- **Score risco determinístico** (handoff §5.4) — NÃO chama LLM. Não conta quota `cliente_ia`.
- **biz=1 não biz=4** smoke prod — Wagner@oimpresso.com WR2 SC. JAMAIS biz=4 Larissa (cliques disparam OS/WhatsApp pra clientes reais).
- **Charter chain de supersede** primeiro caso no projeto — Charter de tela inteira sendo substituído por Charter de tela diferente. Documentar precedente em RUNBOOK + lembrar em sessão de auditoria knowledge-architecture.

## Critério de aceitação

- Screenshot drawer aberto em prod (`MWART_CLIENTE_INDEX=true` biz=1) com **8 tabs renderizando**, salvo em `prototipo-ui/SYNC_LOG.md` ([R1] smoke real).
- Pest cobertura — 6 arquivos test mínimos:
  - `ClienteIndexDrawer760CharterTest` (11 GUARDs charter v3 + Non-Goal violations + cross-tenant)
  - `ClienteDrawerCadastroAutosaveTest` (5 tabs autosave on blur + mod 11 + multi-tenant)
  - `ClienteLookupCnpjCepTest` (BrLookupService cache + 404 + rate limit graceful)
  - `ClienteIaQuotaTest` (3 endpoints IA + telemetria custo)
  - `ClienteAuditoriaSpatieTest` (timeline 6 tipos + Exportar log + LGPD Art. 18)
  - `ClienteShowSupersededTest` (`/cliente/{id}` redirect 302 → drawer; `/contacts/{id}` legacy intacto)
- `php artisan jana:health-check` verde (5 checks SQL: multi_tenant_isolation, brief_uptime_24h, custo_brain_b_24h, pii_leak_in_assistant_responses, profile_distiller_drift).
- Charter `Index.charter.md` v3 `status: live` + Show.charter.md v2 `status: superseded`.
- ADR 0179 `status: accepted` (Wagner aprova manualmente após Wave A).
- Rota legacy `/contacts/{id}` Blade intacta (dual-render via `config('mwart.cliente_show.enabled')`).
- Viewport 1280×1024 sem scroll horizontal (Pest charter test obrigatório Larissa biz=4).

## Referências

- Protótipo Cowork: `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` (381 linhas — schema BR + 4 endpoints IA + checklist)
- Dossiê wagner-understand: `memory/sessions/2026-05-21-understand-cliente-drawer-760px-opcao-A.md`
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canônico
- [ADR 0107](0107-emendation-0104-visual-comparison-gate-f3.md) — Visual comparison gate F3
- [ADR 0110](0110-cockpit-pattern-v2.md) — Cockpit Pattern V2
- [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) — Prototipo-ui Cowork loop formalizado
- [ADR 0149](0149-mwart-screen-pattern-reuse-cowork.md) — Pattern reuse blueprint Cowork
- [ADR 0167](0167-errata-0130-handoff.md) — Errata handoff
- [ADR 0177](0177-mwart-excecao-cliente-show-wave-paralela.md) — MWART exceção Cliente/Show Wave paralela (esta ADR supersede o paradigma que 0177 entregou)
- `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` — pré-flight obrigatório Wave C-F
- `memory/requisitos/Crm/SPEC.md` (US-CRM-068 drawer 760 + US-CRM-069 listagem turbinada — Wagner criar)

## Histórico

- **2026-05-21** — Proposta gerada após dossiê wagner-understand completo. Wagner aprovou opção (A) "refazer paradigma". Q1-Q4 refinadas: Show deleta zero-sunset / autosave inline / ALTER aditivo `contacts` / IA Default ON sem gate. Status `proposed` aguardando Wagner aprovar `accepted` no PR de Wave A.

## Pontos de re-revisão

- Após Wave D fechada — validar se sub-tabs aninhadas em "OSs" couberam em 760px sem regredir paridade Wave Final (8 sub-componentes `_show/*`). Se não couber, ADR 0179 emenda dizendo "tab OSs usa dropdown OU expande pra modal full-screen".
- Após Wave E em prod 30d — validar custo Brain B/dia vs baseline `Modules/Jana/Services/CustosService`. Se custo dispara, Wagner regride pra gate `copiloto.admin.custos` (sub-emenda).
- Após canary biz=4 Larissa 48h — validar viewport 1280×1024 e cache ViaCEP/BrasilAPI hit rate. Se hit rate <70%, aumentar TTL Redis.
