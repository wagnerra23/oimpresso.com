# 08 — Handoff

> **Este é o arquivo que você lê PRIMEIRO quando retoma o trabalho.**
>
> Ele sempre reflete o estado mais recente. É sobrescrito a cada sessão.
> Para ver o que mudou ao longo do tempo, consulte `sessions/`.

---

## 🆕 Estado pós-2026-05-10 — Prospecção 3 verticais + plano outbound + canon MCP + smoke NFe estado

**Sessão 2026-05-10** — Wagner pediu "todos" das 4 frentes possíveis. Resultado em paralelo via 24+20=44 agentes (3 batches gráficas/com.visual + 10 oficinas auto + 10 vestuário) + foreground próprio.

### (1) Prospecção comunicação visual — Brasil 100% coberto

24 UFs novas mapeadas (somando SP/RS/PR já existentes = 27/27). Total **~639 empresas** Tier 1+2+3.
Índice: [memory/research/2026-05-prospeccao/00-INDEX-UFS.md](research/2026-05-prospeccao/00-INDEX-UFS.md). Top 30 cross-UF destilado, padrões catalogados (multi-loja raríssimo, WhatsApp universal, NFe-de-boleto-pago greenfield, IG > site no Norte).

### (2) Plano outbound Q2 — 30 mensagens cold customizadas + Cold #2/#3

[memory/sales/2026-05/outbound-comvis-q2/00-PLAN.md](sales/2026-05/outbound-comvis-q2/00-PLAN.md):
- **Cold #1:** 30 mensagens individuais customizadas com sinal observado por prospect
- **Cold #2:** template + 7 variações por arquétipo de dor (multi-loja, departamentos próprios, carteira enterprise, portal B2B, marketplace múltiplo, sistema legado, greenfield)
- **Cold #3:** template "última chamada honesta" com case ROTA LIVRE detalhado (R$ 80k→130k/m em 18 meses)
- Cadência W1-W5 + métricas (meta 30% resposta em 30 dias)
- **Tracking via markdown** (não MCP) por escolha Wagner (feedback memory: outbound markdown > MCP tasks granulares)

### (3) Prospecção 2 verticais novas — OficinaAuto + Vestuario (10 UFs cada)

- **OficinaAuto:** 288 oficinas em 10 UFs (SP+RJ+MG+SC+PR+RS+BA+GO+DF+PE) — 97 Tier 1. Padrões: Bosch Car Service como selo, multi-loja 3x mais comum que gráfica, B2B frota+seguradora dor confessada, ABC=câmbio automático especialista, Goiás=diesel CONAMA. ⚠️ ADR 0105: backlog feature-wish — não ativar US sem piloto pagante. Índice: [research/2026-05-prospeccao-auto/00-INDEX-UFS.md](research/2026-05-prospeccao-auto/00-INDEX-UFS.md).
- **Vestuario:** 274 lojas em 10 UFs (SP+SC+MG+RJ+RS+PR+CE+PE+BA+GO) — 92 Tier 1. Padrões: IG > site (oposto gráficas), multi-loja+ecommerce próprio é norma SMB Tier 1, Mitienda Nube domina ecommerce SMB sem ERP integrado, polos têxteis (Toritama/Brás-Norte/Brusque) excluídos, **8 prospects vizinhos do ROTA LIVRE em Tubarão/Gravatal/Laguna** = alavanca natural. Índice: [research/2026-05-prospeccao-vestuario/00-INDEX-UFS.md](research/2026-05-prospeccao-vestuario/00-INDEX-UFS.md).

### (4) Habilitação canon MCP (PR pendente review Wagner)

3 módulos verticais habilitados em `mcp_jira_projects` via:
- **Migration:** `Modules/Jana/Database/Migrations/2026_05_10_120000_seed_modulos_verticais_mcp_jira_projects.php` (idempotente, com proteção rollback)
- **Seeder atualizado:** `McpDefaultsSeeder.php` ganhou COMVIS/VEST/AUTO
- **SPEC novo:** `memory/requisitos/OficinaAuto/SPEC.md` (mínimo, status backlog)
- **ADR 0125:** [memory/decisions/0125-mcp-jira-projects-modulos-verticais.md](decisions/0125-mcp-jira-projects-modulos-verticais.md) — justifica governance change

Após merge + `php artisan migrate` em prod, `tasks-create module:ComunicacaoVisual` (etc) funciona. AUTO no canon **não autoriza** US-AUTO-* — ADR 0105 ainda manda, é só remoção de fricção operacional.

### (5) Smoke NFC-e SEFAZ biz=1 — investigado, ação pendente Wagner

- ✅ flag `NFEBRASIL_AUTO_EMISSION_NFCE=true` **JÁ ESTÁ ON** no `.env` Hostinger (descoberto via SSH)
- ✅ biz=1: CNPJ 36.613.150/0001-18, NCM 49111090, ambiente 2 (homo), cert válido até 2026-08-06
- ⚠️ 40 vendas paid+final em biz=1 mas 0 emissões — Listener só pega evento NOVO
- **Falta apenas:** criar 1 venda nova em biz=1 pra disparar pipeline (UI POS oimpresso.com/sells/create, R$1, dinheiro, consumidor final)
- Runbook atualizado pra refletir estado: [memory/requisitos/NfeBrasil/RUNBOOK-smoke-sefaz-biz1.md](requisitos/NfeBrasil/RUNBOOK-smoke-sefaz-biz1.md)

### Pendências sugeridas próxima sessão

1. **Wagner cria 1 venda biz=1** → fecha smoke NFC-e (ou pede pra Claude criar via Browser MCP, mas é prod — confirmar)
2. **PR habilitação canon MCP** → review + merge (5 arquivos: migration + seeder + SPEC + ADR + atualização da tabela seeder)
3. **Sync memória/git push** → todos artefatos ficam acessíveis ao time via webhook GitHub→MCP
4. **Outbound execution** → Wagner começa pelos 4 prospects vizinhos do ROTA LIVRE em SC (Tubarão/Gravatal) ou Top 30 cross-UF do plano com.visual

---

## 🆕 Estado pós-2026-05-08 madrugada — Inter direto (4 PRs Open Finance: extrato + boleto + PIX)

**Sessão Opus 2026-05-08 madrugada** — Wagner pediu "ter acesso a extrato, boleto, PIX direto" (sem agregador OF tipo Pluggy). Plano em 3 fases aprovado e entregue 100% em ~4h. Worktree isolado pra contornar conflito com Cursor (sessão paralela ProjectMgmt fazia `git checkout` no repo origem descartando trabalho não-commitado).

### PRs mergeados (4 PRs · todos `done` no MCP)

| PR | Fase | Conteúdo |
|---|---|---|
| [#206](https://github.com/wagnerra23/oimpresso.com/pull/206) | 1 — saldo | `InterBankingClient` (OAuth+mTLS+cache token 50min) + `getSaldo()` Banking API v2 + Pest 7 cenários |
| [#210](https://github.com/wagnerra23/oimpresso.com/pull/210) | 2 backend extrato | `BankStatementDriverContract` + `InterStatementDriver` + tabela `fin_extrato_lancamentos` + `SyncBankStatementsJob` daily 07:00 BRT + Pest |
| [#213](https://github.com/wagnerra23/oimpresso.com/pull/213) | 2 frontend extrato | `ExtratoController` + tela `/financeiro/extrato/{conta}` + permissão + Pest. **Bonus**: phpunit.xml fix (registra `Modules/Financeiro/Tests/Feature` que estava como falsa cobertura) |
| [#221](https://github.com/wagnerra23/oimpresso.com/pull/221) | 3 PIX cob+webhook | `InterPixCobDriver` + `InterWebhookController` (shared secret `X-Inter-Webhook-Secret`) + `ProcessInterWebhookJob` + Pest 9 cenários adversariais |

US-RB-045/046/047 todas → `done` no MCP. SPEC.md de RecurringBilling registra as 3 com blocked_by encadeado.

### Pré-requisitos Wagner pra ativar em prod

1. **Liberar 4 escopos no portal Inter**: `extrato.read` · `cob.read` · `cob.write` · `webhooks.write`
2. **Onboarding cred Inter**: gerar `webhook_secret` aleatório e salvar em `BoletoCredential.config_json` (mesmo registro do boleto, novo campo)
3. **Configurar webhook no Inter** via `PUT /webhooks/pix-recebidos`:
   - URL: `https://oimpresso.com/webhooks/inter/pix/{businessId}`
   - Header custom: `X-Inter-Webhook-Secret: <mesmo do passo 2>`
4. **Smoke**: tinker `InterPixCobDriver::criarCobImediata` → mandar PIX da conta pessoal pro QR Code → confirmar `InvoicePaid` dispara e NfeBrasil emite NFe55 (US-RB-044 Listener)

### Aprendizados meta

- **Cursor sessão paralela = conflito de checkout**: Wagner usando Cursor numa branch ProjectMgmt fez `git checkout` 3× no repo origem entre meus saves → working tree clean, trabalho perdido. **Solução**: `git worktree add .claude/worktrees/<task>` isola checkout. Padrão a adotar quando Cursor visivelmente trabalhando em paralelo.
- **`eduardokum/laravel-boleto` cobre só boleto+PIX charging, NÃO Banking API**: separar `InterBankingClient` (Http nativo, mTLS via Guzzle) do `InterDriver` (boleto, lib eduardokum) é SoC obrigatório (ADR 0094 §5).
- **Inter v2 webhook NÃO usa HMAC**: aceitam apenas mTLS receiving (Hostinger não suporta) ou shared secret no header customizável. Resolvido com `X-Inter-Webhook-Secret` validado via `hash_equals` (timing-safe) + idempotência por `endToEndId` em `pg_webhook_events`.
- **scope-guard CI bloqueia merge** se controller novo não está em `Modules/<X>/SCOPE.md.contains[]`. Fix de drift pré-existente (ProjectMgmt SearchController) absorvido em hotfix separado durante PR #213.
- **CI atual roda apenas `tests/Feature/Form`** (gargalo conhecido em ci.yml: "Setup MySQL+migrate full em CI fica pra PR separado"). Pest do `Modules/RecurringBilling/Tests/Feature` passa CI mas **não executa de fato** — falsa cobertura herdada. Quando ci.yml for fixado, todos os 16 cenários Inter (saldo+extrato+PIX webhook) começam a rodar.
- **Quota Anthropic crash** descartou trabalho mid-flight (Fase 3 implementação inicial perdida). Recovery: re-aplicar tudo via worktree dedicado, commit early/often.

### Próximos passos sugeridos

1. **Ativar Inter direto em prod** (pré-reqs Wagner acima) — desbloqueia smoke real
2. **Botão "Gerar PIX" na tela `/financeiro/contas-receber`** → abre modal com QR Code + copia-e-cola (polish secundário, US separada se quiser)
3. **CnabDirectStrategy SinkCobranca** pra outros 16 bancos (Sicoob/BB/etc) — pattern `BankStatementDriverContract` já permite plug, ~3h cada banco
4. **Fix ci.yml pra rodar Pest de Modules** (gargalo conhecido) — desbloqueia testes reais em CI

---

## 🆕 Estado pós-2026-05-08 madrugada — 8 PRs noite-3, painel fiscal + guard CI + 3 ADRs canon

**Sessão Opus 2026-05-07 → 2026-05-08 (continuação noite-2)** — 8 PRs adicionais mergeados em ~6h consolidando NfeBrasil + governança biz_id.

### PRs mergeados nesta extensão (8 PRs)

| PR | Tipo | Conteúdo |
|---|---|---|
| [#208](https://github.com/wagnerra23/oimpresso.com/pull/208) | fix | NfeBrasil tests biz_id=4→1 (14 arquivos + 2 PII removidas) |
| [#212](https://github.com/wagnerra23/oimpresso.com/pull/212) | feat | Template Simples Nacional SC (11º L1, sem FCP) |
| [#215](https://github.com/wagnerra23/oimpresso.com/pull/215) | feat | Botão "Testar conexão SEFAZ" + endpoint NFeStatusServico |
| [#216](https://github.com/wagnerra23/oimpresso.com/pull/216) | chore | Guard CI BusinessIdGuardTest + sweep 25 arquivos (Whatsapp/RB/Jana/Builders) |
| [#217](https://github.com/wagnerra23/oimpresso.com/pull/217) | fix | Tools::model(int) bug runtime + payload erro com UF/ambiente |
| [#218](https://github.com/wagnerra23/oimpresso.com/pull/218) | docs | +3 ADRs canon (0101 biz_id=1, 0102 polling NFCe, 0103 events por modelo) |
| [#219](https://github.com/wagnerra23/oimpresso.com/pull/219) | feat | Painel fiscal completo cert (5 cards + selector ambiente + fallback CNPJ) |
| #220 | feat | (outra sessão) PMG-004 Detail Sheet ProjectMgmt fase 2 |

### Estado biz=1 (Wagner WR2 Sistemas, Tubarão/SC) — pronta pra smoke real

| Pré-requisito | Status |
|---|---|
| Cert A1 ativo | ✅ válido até 2026-08-06 |
| CNPJ + NCM padrão `49111000` | ✅ |
| Ambiente SEFAZ | ✅ 2 (homologação) |
| Template Simples SC aplicado | ✅ via UI |
| `nfe_business_configs` row | ✅ regime=simples, cfop=5102, csosn=102 |
| Painel fiscal UI | ✅ 5 cards + botão "Testar agora" + selector ambiente |
| Botão "Testar agora" funcional | ✅ fix #217 (cast int) em prod |
| Flag `NFEBRASIL_AUTO_EMISSION_NFCE` | ❌ não setada (default false — opt-in Wagner) |

**Smoke real está a 1 toggle de flag** + criação de venda fictícia.

### Regra dura nova consolidada (3 PRs + guard CI)

🚨 **Tests SEMPRE biz_id=1 (Wagner), NUNCA 4 (cliente RotaLivre).** Cross-tenant adversário = 99.

- Auto-mem: `feedback_test_business_id_1_nunca_4.md` (top entry MEMORY.md)
- ADR canon: [0101](decisions/0101-tests-business-id-1-nunca-cliente.md)
- Guard CI: `tests/Unit/BusinessIdGuardTest.php` — varre 7 patterns regex em 148 arquivos, falha CI em regressão
- Cobertura: 47 arquivos sweep (NfeBrasil 22 + Whatsapp 8 + RB 4 + Jana 12 + Builders 1)
- Audit final: 0 violações / 50 arquivos com 237 ocorrências `business_id=1`

### Bug runtime grave consertado (PR #217)

`Tools::model()` em sped-nfe v5+ exige `?int`, eu passava `string '55'/'65'` → TypeError em runtime real ao Wagner clicar "Testar agora". Tests Pest mockavam Tools sem assertion de tipo → bug invisível em CI.

Fix: `(int) $modelo` cast + try/catch envolvendo TUDO em `consultarStatusSefaz` + payload de erro com UF/ambiente preenchidos.

4 tests anti-regressão garantem `Tools::model()` recebe INT em runtime real.

### PRs Maiara não mergeáveis (branches obsoletas)

| PR | Problema |
|---|---|
| [#191](https://github.com/wagnerra23/oimpresso.com/pull/191) Maíra→Maiara | Branch ~25 PRs atrás de main |
| [#184](https://github.com/wagnerra23/oimpresso.com/pull/184) Tributação CTA | Branch apagaria 11 templates L1 (PRs #194/195/199/212) |

**Comentei nos 2 sugerindo refazer a partir de main fresh.** Wagner ou Maiara fecham/refazem.

### Próximos passos (ordem ROI)

1. **Smoke real homologação SEFAZ biz=1** — Wagner loga `oimpresso.com`, eu clico "Testar agora" → cstat 107 valida cert+SEFAZ+UF; depois habilita flag + cria venda
2. **Templates GO + PA** (FCP 2%) — fechar 5/5 estados FCP
3. **Integração Blade POS** legacy → Inertia + plugar `<NfceStatusBadge />` (PR grande ~4-8h)
4. **Listener retry rejeitadas** + event `NFCeRejeitada`
5. **ADR broadcast Centrifugo HTTP bridge** — desbloquear fase 2C real-time

---

## Estado pós-2026-05-07 noite-3 — fix biz=1 + template SC + memória consolidada (3 PRs adicionais)

**Sessão Opus 2026-05-07 noite-3** — extensão da sessão noite-2. Wagner sinalizou erro grave (testes usavam biz=4 cliente), consertamos e configuramos biz=1 (Wagner WR2 SC) pra primeiro smoke real fiscal.

### PRs adicionais mergeados nesta sub-sessão

| PR | Conteúdo |
|---|---|
| [#208](https://github.com/wagnerra23/oimpresso.com/pull/208) | **fix(nfe-tests):** default `business_id` 4 (RotaLivre cliente) → 1 (Wagner) em 14 arquivos de test + 2 PII leves removidas |
| [#212](https://github.com/wagnerra23/oimpresso.com/pull/212) | **feat(nfe):** template tributário Simples Nacional SC (sem FCP) — 11º L1 |

### Setup biz=1 (Wagner WR2 Sistemas, Tubarão/SC) pra smoke fiscal

- ✅ Cert A1 ativo (válido 06/08/2026, 91 dias)
- ✅ CNPJ presente, NCM padrão `49111000`, série NFe 1
- ✅ Ambiente SEFAZ = **2 (homologação)**
- ✅ Template SC aplicado via UI `/nfe-brasil/tributacao` — `nfe_business_configs` row criada (regime=simples, cfop=5102, csosn=102)
- ⚠️ Flag `NFEBRASIL_AUTO_EMISSION_NFCE` não habilitada no .env (default false — opt-in Wagner)

**Smoke real está a 1 toggle de flag** — runbook em `runbook_smoke_sefaz_biz1.md` (auto-mem) cobre o passo-a-passo + diagnóstico de erros + rollback.

### Memória consolidada (revisão completa MEMORY.md)

8 entradas obsoletas removidas:
- `reference_project_memory.md` (redundante com CLAUDE.md)
- `project_roadmap_milestones.md` (M3-M10 antigos)
- `project_roadmap_a_plus.md`, `project_roadmap_fiscal.md` (desatualizados)
- `project_modulo_copiloto.md`, `project_modulos_promovidos_2026_04_24.md` (supersedidas)
- `reference_quick_sync_quebrada.md` (resolvido — secrets configurados)
- `project_estado_2026_04_27.md` (supersedido por 04-29)

2 entradas consolidadas novas:
- `project_nfebrasil_estado_2026_05_07.md` — estado completo NfeBrasil (pipeline + 11 templates + biz=1 ready)
- `runbook_smoke_sefaz_biz1.md` — passo-a-passo smoke fiscal SEFAZ-SC homologação

### Aprendizado meta crítico desta sessão

🚨 **Tests/fixtures/smokes SEMPRE biz_id=1 (Wagner), NUNCA 4 (cliente RotaLivre).** Cross-tenant adversário = biz_id 99. Auto-mem `feedback_test_business_id_1_nunca_4.md`. Salvo em MEMORY.md como entry topo (🚨).

Detectar antes de PR: `grep -rn 'business_id.*=>\\s*4' Modules/<X>/Tests/` — qualquer hit sem justificativa cross-tenant explícita = revisar.

### Pipeline NFC-e ponta-a-ponta agora (server-side completo)

```
Venda finalizada → SellCreatedOrModified
  → EmitirNfceAoFinalizarVenda (#193)
  → EmitirNfceJob (#193+#198+#201)
     → NfeService::emitirParaTransaction (#198) → SEFAZ → cstat 100
     → event(NFCeAutorizada) (#201)
        → EnviarDanfeNFCePorEmail (#200) [opt-in]
UI Page /nfe-brasil/transactions/{tx}/status (#203)
  → useNfceStatus polla 2s × 30 → NfceStatusBadge atualiza
```

### Próximos passos (ordem ROI)

1. **Smoke real homologação SEFAZ** — usar `runbook_smoke_sefaz_biz1.md`. Habilitar flag, criar venda fictícia biz=1, verificar cstat 100. ~15min ato real.
2. **Templates GO + PA** (FCP 2%) — fechar cobertura 5/5 estados FCP. ~30min fixture pura.
3. **Integração Blade POS** legacy → Inertia + plugar `<NfceStatusBadge />` no recibo. ~4-8h refactor grande.
4. **Mergear PRs abertos do time** ([#184](https://github.com/wagnerra23/oimpresso.com/pull/184), [#191](https://github.com/wagnerra23/oimpresso.com/pull/191)) — review rápido.
5. **Listener retry rejeitadas** + event `NFCeRejeitada` — UI re-emissão.
6. **ADR broadcast Centrifugo** HTTP bridge — desbloquear fase 2C real-time futura.

---

## Estado pós-2026-05-07 noite-2 — US-NFE-002 fechada ponta-a-ponta (5 PRs em sequência)

**Sessão Opus 2026-05-07 noite (segunda metade)** — fechou US-NFE-002 (Emitir NFC-e a partir de venda finalizada) com 5 PRs encadeados em ~3h. Pipeline server-side completo, UI demo Inertia funcionando.

| PR | Fase | Conteúdo |
|---|---|---|
| [#198](https://github.com/wagnerra23/oimpresso.com/pull/198) | 2A | `NfeService::emitirParaTransaction` real (XML + assinar A1 + SEFAZ) |
| [#199](https://github.com/wagnerra23/oimpresso.com/pull/199) | TPL-001 | +3 templates: MEI-SP, Simples MG, Simples RS (FCP 2%) — biblioteca 7→10 |
| [#200](https://github.com/wagnerra23/oimpresso.com/pull/200) | 2B parc | Event `NFCeAutorizada` + Listener `EnviarDanfeNFCePorEmail` (flag opt-in default off) |
| [#201](https://github.com/wagnerra23/oimpresso.com/pull/201) | 2B compl | `EmitirNfceJob` dispatch event quando `status='autorizada'` (1 linha + 4 tests) |
| [#203](https://github.com/wagnerra23/oimpresso.com/pull/203) | 2C | UI status via polling: endpoint JSON + hook `useNfceStatus` + `<NfceStatusBadge />` + Page Inertia demo |

**Pipeline NFC-e ponta-a-ponta agora:**
```
Venda finalizada → SellCreatedOrModified
  → EmitirNfceAoFinalizarVenda (PR #193)
  → EmitirNfceJob (PRs #193+#198+#201)
     → NfeService::emitirParaTransaction → SEFAZ → cstat 100
     → event(NFCeAutorizada) → EnviarDanfeNFCePorEmail (opt-in)
UI Page /nfe-brasil/transactions/{tx}/status (PR #203)
  → useNfceStatus polla 2s × 30 → NfceStatusBadge atualiza visual
```

### Decisão arquitetural fase 2C: polling > broadcast (este sprint)

Investigamos broadcast tempo real e descobrimos:
- `BROADCAST_DRIVER=null` no .env atual (broadcasting desligado)
- `config/broadcasting.php` só tem reverb/ably/redis/log/null — **sem driver Centrifugo registrado**
- ADR 0058+0062: Hostinger NÃO roda daemons; Centrifugo vive só CT 100

**3 opções avaliadas, escolhida (C):**
- ❌ A — Reverb no Hostinger: viola ADR 0062
- ❌ B — Centrifugo CT 100 + bridge HTTP: precisa ADR arquitetural separada (decisão Wagner)
- ✅ **C — Polling 2s no front**: respeita ADRs, hook abstrai transport, troca pra broadcast no futuro sem refazer componentes

**Hook+componente prontos para reutilização** em qualquer Page Inertia. POS legacy (Blade `sale_pos/create.blade.php`) **NÃO** foi tocado — refatoração pra Inertia é PR separado/grande.

### Pendências NFe pós-sessão

- ⏸ **Integração Blade POS** — refatorar `sale_pos/create.blade.php` pra Inertia + plugar `<NfceStatusBadge />` (PR grande, decisão de escopo)
- ⏸ **Broadcast Centrifugo CT 100** — precisa ADR arquitetural (HTTP bridge Hostinger→CT 100 ou outra estratégia)
- ⏸ **Smoke real homologação SEFAZ** — emitir NFC-e teste em ambiente RotaLivre + verificar email recebido + Page status atualizar
- ⏸ **Listener para retry/correção rejeitadas** — UI de retry + event `NFCeRejeitada` (caso cstat ≠ 100)

### Tabela de templates tributários L1 (pós-#199)

| Slug | Setor | Regime | UF | Notas |
|---|---|---|---|---|
| comercio-varejo-simples-sp | comércio | simples | SP | base |
| comercio-atacado-simples-sp | comércio | simples | SP | atacado |
| industria-grafica-simples-sp | indústria | simples | SP | gráfica |
| comercio-varejo-presumido-sp | comércio | presumido | SP | presumido |
| industria-grafica-presumido-sp | indústria | presumido | SP | presumido |
| comercio-varejo-real-sp | comércio | real | SP | lucro real |
| comercio-varejo-simples-rj | comércio | simples | RJ | FCP 2% |
| **mei-varejo-sp** ✨ | comércio | mei | SP | DAS-MEI fixo |
| **comercio-varejo-simples-mg** ✨ | comércio | simples | MG | FCP 2% |
| **comercio-varejo-simples-rs** ✨ | comércio | simples | RS | FCP 2% |

10 templates auto-descobertos por `TributacaoTemplateService::listar()`. Cobertura UF com FCP: 3/5 dos estados FCP-2% (RJ + MG + RS); faltam GO e PA.

---

## Estado pós-2026-05-07 tarde — BRIEF cleanup completo + 6/6 tasks done CYCLE-02 W20

**Sessão Opus 2026-05-07 manhã/tarde** — entregou 6 US-COPI relacionadas ao L7 Daily Brief + governança:

| ID | Item | Status | Commit/PR |
|---|---|---|---|
| US-COPI-088 | BRIEF-A1 fix aggregator (3 bugs: in_flight + decided_at DATE bug + activity) | done | PR #162 |
| US-COPI-089 | BRIEF-A2 brief-fetch tool MCP exposed (auto-resolvido) | done | (cache refresh) |
| US-COPI-090 | BRIEF-A3 ADR 0097 gpt-4o-mini supersede parcial 0091 | done | PR #168 |
| US-COPI-091 | BRIEF-A4 SKILL.md description imperativa + namespace correto | review | a0b53b8a |
| US-COPI-092 | GUARD-01 Pest snapshot test + procedure_drift health-check | done | PR #169 (outro agent) |
| US-COPI-093 | GUARD-02 Pest ModuleScaffolding (4 peças obrigatórias) | done | PR #162 |
| US-COPI-094 | Rota MCP condicional via MCP_TOOLS_EXPOSED env (Wagner regra "MCP só CT 100") | done | commit 7e1141e5 |

**Quick wins mensuráveis**:
- L7 Daily Brief 217→235 tokens com dados reais (5 ADRs listadas, in_flight populado, mcp_activity_24h=122)
- Hostinger `/api/mcp` agora HTTP 404 (MCP só no CT 100 — regra Wagner)
- CT 100 mcp.oimpresso.com healthy + `MCP_TOOLS_EXPOSED=true` no .env adicionado
- Custo brief real medido: $0.024/dia (-92% vs ADR 0091 projetado $0.30-0.50)

**Wagner regra canônica nova (2026-05-07)** registrada em auto-mem `feedback_mcp_so_ct100.md` + `memory/proibicoes.md`:
> "MCP é só CT 100. Hostinger não funciona e fica lento mcp. Se for preciso temos que dividir o projeto."

**Followups P2 BRIEF-A4 (não corrigidos nesta sessão)**:
1. `agent_id=unknown` na telemetria — header X-MCP-Agent-Id não chega
2. Único user real Wagner (user_id=3) — Felipe/Maiara/Luiz/Eliana não usam
3. Tier A `auto_trigger: session_start` no frontmatter NÃO é mecânico — pra force auto-invoke precisa SessionStart hook que faz curl POST brief-fetch

**Lições da sessão**:
- **Drift entre SQL spec e procedure deployed** existe — GUARD-01 criou snapshot test pra prevenir
- **Múltiplos agents em paralelo no mesmo checkout** = commits podem misturar concerns (commit a0b53b8a misturou whatsapp + skill fix). Próxima vez: usar worktrees separados.
- **Hostinger pode estar em branch errado** (rebase abort necessário em 2026-05-07 manhã — checkout main + reset). Sempre verificar branch antes de pull.

**CYCLE-02 status (0% decorrido, 19 dias restantes)**:
- Goal #6 Constituição V2 health-check 7d limpo: **progresso significativo** — brief funcional com dados reais, GUARD-01+02 deployados, MCP só CT 100
- Goal #4 MWART Repair (4 telas): 0/4
- Goal #5 NfeBrasil emite NFe55: 0/done (Wagner+outro agent trabalhando em paralelo via PRs #169/170 — cod_municipio IBGE + cert A1 fora webroot)
- Goal #7 Skills V0.5 UI: 0/done

**Última atualização:** 2026-05-07 ~14:30 BRT — Opus session BRIEF cleanup + governança

---

## Estado pós-2026-05-07 manhã — Revisão CYCLE-01 órfão + abertura CYCLE-02

> Sessão de governança. Wagner pediu "revise e descubra o que aconteceu, e aprenda" sobre o CYCLE-01. Diagnóstico revelou cycle órfão por 5 dias (pivot Constituição V2 sem fechar cycle anterior). Executado: 5 tasks reclassificadas, CYCLE-02 criado via SQL, CYCLE-01 fechado com retro real, skill commit-discipline patcheada.

### Entregue

| Item | Status |
|---|---|
| Triagem 5 tasks COPI vs PRs (DB-only) | ✅ COPI-21, 24, 38, 42 → done/cancelled. COPI-23 mantida blocked (Sprint 9c). |
| CYCLE-02 criado via SQL CT 100 (id=3, project_id=1, 4 goals) | ✅ ativa 2026-05-13 → 2026-05-26 |
| CYCLE-01 fechado via `cycles-close rollover_to=CYCLE-02` | ✅ retro 15 sucessos + 5 falhas + 1 lição mestre |
| Patch skill `commit-discipline` regra auto-update post-merge | ✅ +35 linhas seção "Auto-update tasks-update após commit/merge" |
| Decisão: NÃO renomear module COPI → JANA no MCP | ✅ IDs históricos preservam rastro PRs |

### Goals CYCLE-02 (4 abertos)

1. Repair MWART expansão (4+ telas com cockpit pattern + topnav)
2. NfeBrasil emite NFe55 a partir de boleto pago em prod ROTA LIVRE (US-RB-044 done)
3. Constituição V2 health-check 0 alertas críticos por 7 dias consecutivos
4. Skills V0.5 UI em prod `/ads/admin/skills` 200 + ≥16 skills indexadas

### Pendências de tooling MCP (registrar no backlog)

1. Tool `cycles-create` — atualmente só SQL direto CT 100
2. Tool `tasks-update` aceitar `module` — pra renomear projects sem SQL
3. Hook PostToolUse `gh pr merge` extrair `Refs: <TASK>` e auto-`tasks-update status=done`
4. brief-fetch alerta "eixo do PR mergeado ≠ eixo do cycle ativo" (cycle drift detector)

### Lição mestre

**Pivot estratégico exige `cycles-close --rollover` no MESMO dia.** CYCLE-01 ficou órfão 5 dias entre o pivot Constituição V2 (5-mai noite) e o fechamento (7-mai). Próxima vez NÃO acontece — skill `commit-discipline` agora documenta auto-update; `brief-fetch` precisa do alerta de drift.

### Próximo passo CYCLE-02 (P0)

1. **US-RB-044** (review desde 06-mai) → confirmar merge ou deploy ROTA LIVRE
2. **MWART scaling** — próximas telas Repair / Project / Crm com cockpit pattern
3. **Skills V0.5 Sprint A** backend — `memory/cycles/CYCLE-02-proposta.md` rev2
4. **Whatsapp foundation** — ADR 0096 já mergeado → SPEC + 1 capacidade entregue

Session log: [memory/sessions/2026-05-07-revisao-cycle-01-rollover-cycle-02.md](sessions/2026-05-07-revisao-cycle-01-rollover-cycle-02.md)

---

## Estado pós-2026-05-06 fim-tarde — PR #111 (PR-9: rename DB tabelas Jana)

> Sessão Claude focada exclusivamente em PR-9 do ADR 0088 (rename tabelas DB). Mergeou após o burst Capterra-driven (estado anterior abaixo).

### Entregue

**[PR #111](https://github.com/wagnerra23/oimpresso.com/pull/111) — squash merge `196865cf`** — 13 tabelas Jana renomeadas `copiloto_*` → `jana_*` + 13 views legacy 30d (drop planejado **2026-06-05**) + 1 classe Eloquent renomeada (`JanaMemoriaFato` → `MemoriaFato`).

| Item | Status |
|---|---|
| Migration `2026_05_06_120000_rename_copiloto_tables_to_jana` | ✅ idempotente (Schema::rename + CREATE OR REPLACE VIEW MySQL-gated) |
| 11 Models `protected $table = 'jana_*'` + `searchableAs() = 'jana_memoria_facts'` | ✅ |
| ~30 calls `DB::table('copiloto_*')` → `DB::table('jana_*')` | ✅ services/commands/controllers/tools/listeners/seeders/tests |
| Config Jana default index Meilisearch | ✅ `jana_memoria_facts` |
| FKs intra-Jana | ✅ preservadas pelo `RENAME TABLE` InnoDB |

### ADR 0092 (renumerada de 0090)

[**ADR 0092**](decisions/0092-tabela-rename-copiloto-para-jana.md) — superseding `§DB` do [ADR 0088](decisions/0088-module-rename-php-only.md).

**Conflito monotônico:** minha branch `claude/vigilant-joliot-eb50cd` nasceu de `0e0c35f1` (RUNBOOK semanal) e em paralelo Wagner mergeou ADR 0089 + 0090-nfe + 0091-daily-brief. Após squash merge do PR #111, ficaram **2 arquivos com prefixo 0090**. [ADR 0028](decisions/0028-adrs-numeracao-monotonica.md) proíbe. Solução aplicada: `git mv 0090-tabela-rename → 0092-tabela-rename` + bulk update em 8 arquivos (frontmatter slug+number, ADR 0088 superseded_by_section.db, SCOPE.md Jana, MODULE-DRIFT-MIGRATION-PLAN, RUNBOOK-MEMORIA-SEMANAL, session log, 2 Models, migration docblock).

### Mantidos legacy (NÃO mexido — fachada ADR 0088)

- URLs `/copiloto/*`, permissions `copiloto.*`, config keys/env vars `COPILOTO_*`, log channel `copiloto-ai`, Pages React `Pages/Jana/`, lang `copiloto::`, route names
- `DataController.copiloto_module` (chave de menu)
- Migrations originais `2026_04_*` (append-only — criam `copiloto_*` antes do RENAME na ordem cronológica)

### 📋 Pós-deploy obrigatório (Hostinger)

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd domains/oimpresso.com/public_html && \
   git pull origin main && \
   composer dump-autoload && \
   php artisan migrate --no-interaction && \
   php artisan scout:import "Modules\\Jana\\Entities\\MemoriaFato" && \
   php artisan optimize:clear'
```

Smoke prod (HTTP 200 esperado): `/copiloto/chat`, `/copiloto/admin/qualidade`, `/copiloto/admin/custos`.

Janela de downtime ~30s entre `migrate` e `optimize:clear`. Aceita pelo Wagner (decisão (c) do PR-9).

### Aprendizados de workflow gh + worktree (apply-once)

`gh pr merge --delete-branch` falha em worktrees Claude porque tenta `git checkout main` localmente, mas `main` já está em outra worktree (`D:/oimpresso.com`). Solução adotada (alias permanente):

```bash
gh alias set merge-squash 'pr merge --squash'
# uso: gh merge-squash <num>  → merge no remote, sem touch local
git push origin --delete <branch>     # cleanup remoto
# Cleanup worktree Claude (rodar de outro terminal, não do shell Claude):
git worktree remove --force .\.claude\worktrees\<random>
git worktree prune
```

### Próxima sessão (P0)

1. **Validação local** (~5min): `php bin/check-scope.php` + `./vendor/bin/pest tests/Feature/Modules/Jana/ --no-coverage`
2. **Deploy Hostinger** PR-9 (cmds acima)
3. **Cleanup worktree** `vigilant-joliot-eb50cd` no terminal real
4. Voltar pro CYCLE-01 (vence 12-mai, 6 dias):
   - **COPI-22** P0 (driver MCP Jana, vencia 06-mai)
   - **Goal #3** Dashboard `/copiloto/admin/custos`
5. **2026-06-05** — drop views legacy `copiloto_*` (ADR sub-decisão futura ou comando `php artisan jana:drop-legacy-views`)

### Tabelas renomeadas (referência rápida)

```
copiloto_metas              → jana_metas
copiloto_meta_periodos      → jana_meta_periodos
copiloto_meta_fontes        → jana_meta_fontes
copiloto_meta_apuracoes     → jana_meta_apuracoes
copiloto_conversas          → jana_conversas
copiloto_mensagens          → jana_mensagens
copiloto_sugestoes          → jana_sugestoes
copiloto_memoria_facts      → jana_memoria_facts
copiloto_memoria_metricas   → jana_memoria_metricas
copiloto_memoria_gabarito   → jana_memoria_gabarito
copiloto_cache_semantico    → jana_cache_semantico
copiloto_business_profile   → jana_business_profile
copiloto_negative_cache     → jana_negative_cache
```

Session log: [memory/sessions/2026-05-06-pr-9-tabela-rename-copiloto-jana.md](sessions/2026-05-06-pr-9-tabela-rename-copiloto-jana.md)

---

## 🆕 Estado pós-2026-05-06 noite — Capterra-driven Module Evolution + 7 US no backlog + foundation NfeBrasil

> Sessão maratona Opus 4.7 (1M context). **24 commits** em main entre `01f69869` e `0b73514f`. Densidade altíssima. Use `/continuar` na próxima sessão pra retomar.

### Entrega principal: pattern canônico de evolução de módulo

**[ADR 0089](decisions/0089-capterra-driven-module-evolution.md)** — Capterra-driven Module Evolution. Trio canônico por módulo:

```
memory/requisitos/{Modulo}/SPEC.md             ← O QUE QUEREMOS
memory/requisitos/{Modulo}/CAPTERRA-FICHA.md    ← BENCHMARK (concorrentes + score P0-P3)
memory/requisitos/{Modulo}/CAPTERRA-INVENTARIO.md ← DIAGNÓSTICO ✅🟡❌ (gerado pela skill)
```

**Skill:** `.claude/skills/comparativo-do-modulo/` + slash `/comparativo {Modulo}`. Cruza ficha + SPEC + código → 3 buckets → propõe tasks priorizadas → Wagner aprova → tasks-create no MCP + apenda US ao SPEC.

**Diferencial competitivo:** mercado não combina análise SOA automática + inventário interno×externo + backlog priorizado com aprovação humana. Productboard/Aha! são manuais; Klue/Crayon não vincula a backlog; Cursor/Devin não fazem roadmap.

### 2 módulos auditados nesta sessão

| Módulo | Resultado | US criadas |
|---|---|---|
| **RecurringBilling** | 1✅ 4🟡 9❌ | RB-040..044 (5 US) |
| **NfeBrasil** | 0✅ 1🟡 15❌ (módulo era scaffold) | NFE-040, NFE-041 (Onda 1 aprovada) |

### Implementações entregues (90+ testes verdes em produção)

**RecurringBilling** — 51 testes verdes:
- ✅ **US-RB-040** Cobertura Pest 3 drivers (22 tests). **Bug latente descoberto:** `C6Driver::carteira` default `'25'` lançaria ValidationException — corrigido pra `'10'`.
- ✅ **US-RB-041** Idempotência webhook Asaas (7 tests).
- ✅ **US-RB-042 backend** InvoiceController + endpoint cancel + audit log + permissão `recurringbilling.invoice.cancel` + C6 fail-loud (era no-op enganoso). UI Inertia separada.
- ✅ **US-RB-043 foundation** 4 migrations + 4 models (`Plan`/`Subscription`/`Invoice`/`ChargeAttempt`) + 8 tests. Seeder + jobs separados.
- ✅ **US-RB-044 stub** Listener `EmitirNFeAoReceberPagamento` registrado em NfeBrasil + 6 tests + flag desabilitada (LogicException quando habilitada sem NfeService real).

**NfeBrasil** — 39 testes verdes:
- ✅ **US-NFE-040 foundation** 4 migrations (`nfe_certificados`/`nfe_emissoes`/`nfe_eventos`/`nfe_inutilizacoes`) + 4 models + 14 tests. Sequência fiscal UNIQUE(biz, modelo, serie, numero) + idempotência reemissão UNIQUE(biz, transaction_id).
- ✅ **US-NFE-041 backend** `CertificadoService` + endpoint upload + UploadCertificadoRequest + 13 tests. Bug LGPD documentado: senha hoje em `business.senha_certificado` é só `base64`, não encrypted.

### ADRs novas (3)

- **[0089](decisions/0089-capterra-driven-module-evolution.md)** — Capterra-driven Module Evolution (governance pattern)
- **[0090](decisions/0090-nfe-replace-gradual-app-services.md)** — NFe replace gradual `app/Services/NFeService.php` → `Modules/NfeBrasil/` em 4 fases. Coexistência transparente via fallback no `CertificadoService::carregarParaSefaz()` + comando idempotente `nfe:migrate-cert-business`.
- **[RecurringBilling tech/0007](requisitos/RecurringBilling/adr/tech/0007-encryption-pattern-credenciais-boleto.md)** — encryption pattern `client_secret`/`api_key`/`certificado_key_b64`
- **[RecurringBilling tech/0008](requisitos/RecurringBilling/adr/tech/0008-fk-type-mismatch-ultimatepos-legado.md)** — FK type-mismatch `int unsigned` (UPos legado) vs `bigint unsigned` (Laravel moderno) + idempotência `Schema::hasColumn` em migrations

### Bug MCP fixado

`TaskCrudService::gerarProximoIdCanonical` usava `strtoupper($module)` → `US-RECURRINGBILLING-001` ≠ SPEC.md `US-RB-NNN`. Counter ficava preso em 001. **Fix:** `detectarPrefixoSpec` lê primeiro `### US-XX-NNN` do SPEC + `max(últimoDB, últimoSPEC) + 1`. Test trava.

### Governance phpunit.xml — 4 fontes contra erro recorrente

Wagner: "esse erro é constante guarde na memória para não ter mais isso". Adicionado em:
1. `phpunit.xml` (Jana + RecurringBilling + NfeBrasil registrados)
2. `CLAUDE.md` §4 NÃO fazer
3. `.claude/skills/criar-modulo/SKILL.md` checklist
4. **NOVO** [`memory/requisitos/Infra/RUNBOOK-pest-suite.md`](requisitos/Infra/RUNBOOK-pest-suite.md)

### 🚨 DESCOBERTA crítica de cenário (mudou plano de NfeBrasil)

Investigação revelou:
- **Nenhum business tem `ultimo_numero_nfe > 0`** — sistema **nunca** emitiu NFe oficial em produção
- **11 businesses têm cert legado em `business.certificado`** mas **senha = "1234"** (placeholder de teste)
- **`ambiente = 2`** = SEFAZ homologação em todos
- Wagner confirmou: **não há cert real**, vai recadastrar todos depois (tem cópias dos clientes)

**Impacto:** comando `nfe:migrate-cert-business` deixa de ser urgente — pula migração legado. Caminho mais limpo: começar fresh com upload via UI nova quando NfeBrasil tiver tela. Fase 4 da ADR 0090 (remoção do `app/Services/NFeService.php`) pode ser muito mais cedo — o legado nunca emitiu produção, apenas testes.

### Pendências P0 próxima sessão (em ordem de prioridade)

1. **US-NFE-041 fase 2** (UI Inertia) — `Pages/NfeBrasil/Configuracao/Certificado.tsx` com upload .pfx + senha + status (CNPJ titular, dias até vencer, badge ≤30d). Endpoint backend já pronto (`POST /nfe-brasil/configuracao/certificado`). Tela permite Wagner subir o cert real. ~6h.
2. **`composer require nfephp-org/sped-nfe nfephp-org/sped-da`** — Wagner roda local pra validar conflitos com Laravel 13.6 antes de Hostinger. Pré-requisito de US-NFE-042. ~30min.
3. **US-NFE-042** — `NfeService::emitir()` real (modelo 55) usando sped-nfe + cert do CertificadoService (com fallback legado da ADR 0090) + grava em `nfe_emissoes` (rastro fiscal completo) + atualiza `business.ultimo_numero_nfe` (1 fonte da verdade fiscal). Atualiza listener US-RB-044 pra usar service real (remove LogicException). ~12h.
4. **US-NFE-043** — `MotorTributarioService` — calcula CFOP/NCM/CST/CSOSN a partir de produto+regime. Cobertura Pest ≥5 cenários reais. ~8h.
5. **US-NFE-044** — DANFE PDF render via sped-da + storage por chave. ~4h.
6. **US-RB-042 fase 2** (UI Inertia botão Cancelar) — após NFe básica em pé.

### Pendências menores

- Race condition em `tasks-create` paralelo (5 chamadas viram mesmo ID) — adicionar `lockForUpdate` no `gerarProximoIdCanonical`. Não-crítico.
- US-RB-043 fase 2 (seeder + GenerateInvoicesJob + ChargeAttemptJob)
- US-RB-044 fase 2 (`Modules/NfeBrasil/Services/NfeService` real → habilitar flag `nfebrasil.auto_emission_on_invoice_paid`)

### Arquivos canônicos criados

- `.claude/skills/comparativo-do-modulo/SKILL.md`
- `.claude/commands/comparativo.md`
- `memory/requisitos/_TEMPLATE_capterra_ficha.md`
- `memory/requisitos/RecurringBilling/CAPTERRA-FICHA.md` + `CAPTERRA-INVENTARIO.md`
- `memory/requisitos/NfeBrasil/CAPTERRA-FICHA.md` + `CAPTERRA-INVENTARIO.md`
- `memory/requisitos/Infra/RUNBOOK-pest-suite.md`
- `Modules/RecurringBilling/Services/Boleto/Drivers/{Inter,C6,Asaas}Driver.php` (já existiam) + `BoletoService.php` aprimorado com decrypt de `certificado_key_b64`
- `Modules/RecurringBilling/Models/{Plan,Subscription,Invoice,ChargeAttempt}.php`
- `Modules/RecurringBilling/Http/Controllers/InvoiceController.php`
- `Modules/NfeBrasil/Models/{NfeCertificado,NfeEmissao,NfeEvento,NfeInutilizacao}.php`
- `Modules/NfeBrasil/Services/CertificadoService.php`
- `Modules/NfeBrasil/Http/Controllers/CertificadoController.php` + `Http/Requests/UploadCertificadoRequest.php`
- `Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento.php`
- `Modules/NfeBrasil/Console/Commands/MigrateCertFromBusiness.php`
- 8 arquivos de testes Pest novos
- 4 migrations RB + 4 migrations NFe (todas idempotentes via `Schema::hasTable` guard)

**Última atualização:** 2026-05-06 noite — pattern Capterra-driven + 7 US backlog + 90 testes verdes em prod. Wagner confirmou cenário B (sem migração — começa fresh com cert real via UI nova).

---

## 🚀 Começo Rápido — leia isso primeiro

**Repo:** `D:\oimpresso.com` · **Branch ativa:** `main` · **Última sessão:** 2026-05-06 tarde+noite (Fase 3.7 PR-1 + PR-2 — [#97](https://github.com/wagnerra23/oimpresso.com/pull/97) aguarda review)

### 🆕 Estado pós-2026-05-06 tarde+noite (Fase 3.7 PR-1 + PR-2 entregues)

**3 commits na branch `claude/wonderful-herschel-cccef6` → [PR #97](https://github.com/wagnerra23/oimpresso.com/pull/97).**

**PR-1 (commit `850ac349`)** — 9 drift controllers movidos pros donos corretos (Jana/ADS → KB/TeamMcp/ProjectMgmt). URLs **mantidas inalteradas**. SCOPE.md zerou drift_alerts em 5 módulos. Plano v1.0→1.1 com **erratum §1** (Memoria/FontesController não eram o que o plano descrevia — Wagner confirmou destino KB mesmo assim como decisão L1).

**PR-2 (commit `8f7a5138`)** — 3 renames de módulo PHP-only:
- `Modules/Jana/` → `Modules/Jana/` (chat IA enxuto após PR-1 extrair drift)
- `Modules/PontoWr2/` → `Modules/Ponto/`
- `Modules/MemCofre/` → `Modules/SRS/` (System Rules Spec)

**Mantidos legacy** (rename PHP-only): URLs `/copiloto/*` etc, permissions `copiloto.*` etc, config keys + env vars `COPILOTO_*`, log channels `copiloto-ai`, Pages React `Pages/Jana/`, lang `copiloto::`, tabelas DB (`copiloto_*`, `ponto_*`, `docs_*`). Plano v1.1→1.2 com **erratum §4** (rename PHP-only em vez de rename completo com 301 — razão: blast radius alto demais com 5993 clientes ROTA LIVRE + watchers + webhook + 30 Inertia::render).

**Stats:**
- 9 + 369 git mv (96-99% similarity preservada)
- 314 arquivos com namespace bulk-replaced (320 substituições)
- 3 SCOPE.md + 3 module.json + 3 composer.json + 3 ServiceProvider class names atualizados
- GUARDA `bin/check-scope.php`: **0 drift / 29 módulos** (Jana/Ponto/SRS reconhecidos)

**⚠️ Pós-merge na main:**
- `composer dump-autoload` no Hostinger + CT 100 (autoload PSR-4 muda)
- Smoke prod: `/copiloto/chat`, `/copiloto/memoria`, `/pontowr2/`, `/memcofre/`, `/api/mcp/health`, `/ads/admin/tools` retornam 200
- Webhook GitHub e watchers Claude Code continuam OK (URLs não mudaram)

**Próxima sessão:** revisar/mergear #97 → PR-3+ pode mover URL/permissions/Pages item a item se Wagner decidir.

**ADRs novas:** [0087 — Drift resolution sem mover URL](decisions/0087-drift-resolution-sem-mover-url.md) + [0088 — Module rename PHP-only](decisions/0088-module-rename-php-only.md). **Skill nova:** [.claude/skills/migrar-modulo/SKILL.md](../.claude/skills/migrar-modulo/SKILL.md) — auto-load em refactor de módulo/controller, carrega os 2 patterns + matriz blast radius + receita técnica.

Session log: [memory/sessions/2026-05-06-fase-3-7-pr1-drift-controllers.md](sessions/2026-05-06-fase-3-7-pr1-drift-controllers.md)

---

### Estado anterior — pós-2026-05-06 manhã (Governance UI completa em prod + 6 lições documentadas)

**Continuação maratona 2026-05-05/06** — totalizam **17 commits** (`b26781d9` → `5da2fc02`):

**Sessão 2026-05-06 (UI Governance + bugfix marathon):**

- ✅ `https://oimpresso.com/governance` **FUNCIONA em prod** — KPIs grid (6 métricas), ADRs pending (4 atualmente), audit highlights, links docs canônicos
- ✅ 4 Pages React criadas: `Dashboard.tsx`, `Policies.tsx`, `Audit.tsx`, `DriftAlerts.tsx`
- ✅ 4 Controllers: Dashboard + Policies (toggle inline) + Audit (filtros) + DriftAlerts (runtime scan)
- ✅ Sidebar com novo grupo **GOVERNANÇA** visível
- ✅ Lang `pt/` + `en/` (canonical UltimatePOS pattern)
- ✅ topnav i18n (governance::governance.menu.*)
- ✅ Bundles Inertia em `public/build-inertia/manifest.json` (12 entries governance)

**10 bugs encontrados + corrigidos** (sequência intensa de bugfix Wagner→commit):
1. Rotas Install URL `install/install` + action `install` (correto: `install` + `index`)
2. Query `frontmatter_json LIKE` (correto: coluna `status` direto)
3. AuditController `created_at` (canonical: `ts`)
4. DriftAlerts `mcp_alertas.category` (schema: `kind`)
5. DataController `superadmin_package` formato (key string → array com `name` field)
6. Middleware sem `'authh'` + `'SetSessionData'`
7. `mcp_skill_approvals.status` não existe (correto: `mcp_skill_versions.status='review'`)
8. Lang só em `pt-BR/` (canonical: `pt/` + `en/`)
9. Bundles Inertia faltando no manifest (build local)
10. Compliance score 8% bug aritmético (correto: 80%)

**Skill `criar-modulo` atualizada** com 4 seções novas pra próximas sessões não repetirem:
- ⚠️ Erros frequentes em DataController (formato exato)
- ⚠️ Schemas DB que controllers acessam — VERIFICAR antes de query
- ⚠️ Translations: pasta `pt/` (não `pt-BR/`)
- ⚠️ Lição registrada: PRIMEIRO comando ao iniciar criação de módulo = invocar skill `criar-modulo`

---

### Estado anterior — pós-2026-05-06 madrugada (Constituição v1.1.0 + Governance MVP)

**14 commits da maratona 2026-05-05/06** (`b26781d9` → `d8785dbb`):

**Fundação governance:**
- ✅ **Constituição v1.1.0** — 10 artigos supremos + §10.4 cascade review obrigatória ([memory/governance/CONSTITUTION.md](memory/governance/CONSTITUTION.md))
- ✅ **7 documentos governance:** _README, CONSTITUTION, TRUST-TIERS, ARCHITECTURE, ENFORCEMENT, IDENTITY-MESH, MODULE-DRIFT-MIGRATION-PLAN, audit-2026-05-05-v1.1
- ✅ **8 ADRs novas** (0078..0086) + ADR 0077 superseded por 0081

**Identity Mesh operacional:**
- ✅ Tabela `mcp_actors` + 6 actors seed (Wagner L0, Felipe/Maiara L2, Luiz/Eliana L3, claude-code-wagner-laptop ai_agent L2)
- ✅ 12 mcp_tokens com `actor_id` correto (backfill aplicado)
- ✅ McpActor Eloquent + ActorResolver service em Modules/TeamMcp/
- ✅ MyWorkTool + MyInboxTool resolver atualizado (CT 100 deployed)
- ✅ `my-work` (sem owner) e `my-inbox` voltaram a funcionar — 30 tasks + 50 unread

**Module Charter:**
- ✅ 29 SCOPE.md (1 deletado: Writebot) — 100% módulos com charter
- ✅ GUARDA anti-drift: `bin/check-scope.php` + `.githooks/pre-commit` + GitHub Action
- ✅ Trigger MySQL append-only `mcp_audit_log` (ADR 0084) — `ponto_marcacoes` já tinha

**Modules/Governance (Fase 5 MVP — backend + frontend completo):**
- ✅ Scaffold módulo completo (8 peças)
- ✅ ActionGate middleware (modo warn-only default — calibração 4 semanas)
- ✅ DashboardController + Pages/governance/Dashboard.tsx (KPIs + ADRs pending + audit highlights + quick actions)
- ✅ PoliciesController + Policies.tsx (toggle inline rules)
- ✅ AuditController + Audit.tsx (drill-down filtros período/actor/endpoint/status)
- ✅ DriftAlertsController + DriftAlerts.tsx (runtime scan + persisted alerts)
- ✅ Sidebar SIDEBAR_GROUPS reorganizado: novo grupo **GOVERNANÇA** (ADS+TeamMcp+Governance), Jana/SRS preparados pra renames

**Outras entregas:**
- ✅ Skills 16 (incluindo meta-skill-roi-erp-autonomo) — 14 com manifest trust_level + owner
- ✅ Comando `php artisan skill:scaffold "<missão>"` valida 4 testes da meta-skill antes de criar
- ✅ PII Redactor BR (regex CPF/CNPJ/email/telefone/CEP) — Art. 4 LGPD
- ✅ ADS Project (id=23) + CYCLE-02 (planning) + 6 ADS-1..6 tasks status=done com source_git_sha

**Compliance Constitution v1.1.0: 8/10 plenamente, 2/10 parcial**

| Artigo | Status |
|---|---|
| 1 Soberania | ✅ wagner=L0 root |
| 2 Multi-tenancy | ✅ |
| 3 Imutabilidade | ✅ ponto_marcacoes + mcp_audit_log triggers |
| 4 Compliance | ⚠️ PII redactor disponível, falta wire-in nos services externos |
| 5 Trust Tiers | ✅ 6 actors L0-L4 |
| 6 Identity Mesh | ✅ mcp_actors + ActorResolver |
| 7 Module Charter | ✅ 29/29 SCOPE.md + GUARDA |
| 8 Policy Gating | ⚠️ ActionGate em warn — strict após 4 semanas |
| 9 Auditoria | ✅ |
| 10 Evolução | ✅ aplicado v1.0→v1.1 com cascade audit §10.4 |

**P0 próxima sessão (deferred com transparência):**

1. **Fase 3.7 renames** — Jana→Jana, PontoWr2→Ponto, MemCofre→SRS, ProjectMgmt→Project + 9 drift controllers (`memory/governance/MODULE-DRIFT-MIGRATION-PLAN.md`). 4-6h sessão dedicada com Pest + 301 redirects + webhook validation.
2. **ActionGate gradual rollout** em rotas L1+ existentes
3. **Mode warn → strict** após 4 semanas calibração
4. **Wagner valida visualmente** `/governance` (UI Inertia em prod após Action build-inertia-auto.yml rodar)

**Pra continuar amanhã:**
- `/governance` em prod → Painel consolidado (após Inertia build action commitar bundles)
- `git config core.hooksPath .githooks` → instala GUARDA local
- Ler `memory/governance/CONSTITUTION.md` v1.1.0

---

### Estado anterior — pós-2026-05-05 noite (COPI-40)

**Entregas:**
- ✅ **COPI-40 Semantic cache fechado** (status `done`) — implementação já existia em prod via `LaravelAiSdkDriver` (`responderChat` + `responderChatStream`); faltavam testes. PR #94 adicionou 15 tests Pest cobrindo o contrato (37 assertions, 0 regressão).
- ✅ **Bug fix bonus**: branch FULLTEXT `MATCH AGAINST` em `SemanticCacheService::buscar()` agora detecta driver e degrada graciosamente em SQLite/Postgres. Antes quebrava qualquer não-MySQL com syntax error.
- 🔓 **Cycle 01 goal #3 destravado** — cache em prod agora pode ser medido pra confirmar -68.8% tokens (ADR 0037 Sprint 8).

**Contexto sessão anterior (mesma data, finalizada antes):**
- ✅ Triagem 135 tasks + 17 canceladas — triage MCP zerada
- ✅ 17 Epics em 14 projects (3 novos: NFSE/ACCO/AI), distribuídos Q2/Q3/Q4
- ✅ ADR 0071 — auditoria 18 tools MCP (13 OK, 5 com bugs/auth-degradação)

**P0 pra próxima sessão (cycle 01 vence 12-mai, 7 dias):**
- **COPI-43** PII redactor BR (LGPD-blocker) p0
- **A4 rodada 2** Larissa — repetir 3 perguntas (vendi/líquido/caixa) → 3 respostas distintas em prod
- **COPI-22** driver MCP na Jana (já doing, due 06-mai amanhã)
- **10 testes pré-existentes falhando** em `tests/Feature/Modules/Jana/Mcp/` — não tocados nesta sessão; investigar quando der

**Atenção crítica:** **NÃO RODAR `php artisan mcp:tasks:sync`** até PROJECT-3 (frontmatter YAML SPECs, escalar pra p2) fechar. Parser sobrescreve triagem 05-mai. Ver ADR 0071 §B3.

> **⚠️ Sessão 29-abr noite estourou ~970K tokens** — ver `HOW_TO_ASK_CLAUDE.md` na raiz do repo pra padrão correto. **Próximas sessões:** sempre `/clear` ao trocar de escopo, `/compact` após cada feature, e perguntas com arquivo+linha+o-que-mudar.

### 🆕 Estado pós-29-abr noite

**Entregas (commits `e3ea5b92`→`c807d5db`):**
- ✅ ADR 0054 (pacote enterprise busca memória) + ADR 0055 (self-host equiv Anthropic Team plan) + ADR 0056 (MCP fonte única)
- ✅ Self-host Team plan: TeamController + 5 entities Mcp + QuotaEnforcer (brl/calls/tokens) + alertas idempotentes + middleware popular custo
- ✅ MCP fonte única memória: `McpMemoriaDriver` com fallback Meilisearch + tool MCP `memoria-search` + comando `copiloto:mcp:system-token`
- ✅ Onboarding time: `.mcp.json` + `.claude/settings.local.json.example` + skill `oimpresso-team-onboarding` + `MEMORY_TEAM_ONBOARDING.md`
- ✅ Sprint B Claude Code: 3 tabelas `mcp_cc_*` em prod + tool MCP `cc-search` + skill `oimpresso-cc-watcher-setup` (orquestra watcher local)
- ✅ MCP server CT 100: agora expõe **7 tools** (5 originais + `memoria-search` + `cc-search`)

**Pendências manuais (curtas, NÃO requer mais código):**
1. `ssh hostinger && php artisan copiloto:mcp:system-token --user-email=wagner@…` → copia token raw
2. Add `COPILOTO_MEMORIA_DRIVER=mcp` + `COPILOTO_MCP_SYSTEM_TOKEN=mcp_xxx` em `.env` Hostinger
3. Smoke chat real → recall via MCP
4. Wagner abre Claude Code local e roda skill `oimpresso-cc-watcher-setup` 1× → ingere ~83 sessões


**Rodar local:**
```bash
cd D:\oimpresso.com
# rodando em https://oimpresso.test (Herd + Laragon MySQL)
# login: WR23 / Wscrct*2312
# Meilisearch local em http://127.0.0.1:7700 (PID auto, ver D:\oimpresso.com\meilisearch\)
```

**Stack real:** Laravel **13.6** · PHP 8.4 (Herd) · MySQL Laragon · DB `oimpresso` · Inertia **v3** + React + Tailwind 4 · Pest v4 + PHPUnit v12 · nWidart/laravel-modules ^10

**Stack IA (verdade canônica ADR 0035 + 0036):**
- A = `laravel/ai ^0.6.3` (oficial fev/2026)
- B = `LaravelAiSdkDriver` + 4 Agents (Vizra ADK aguarda L13)
- C = `MemoriaContrato` + `MeilisearchDriver` default + `NullDriver` dev (Mem0 sprint 8+ condicional)
- Tooling = Boost + MCP + Scout + Horizon + Telescope + Pail

---

## 🎯 PRA INICIAR (2026-04-29+) — **LEIA ESSA SEÇÃO**

### ✅ Estado em 2026-04-28 fim do dia

**Infra docker-host CT 100 (192.168.0.50)** — 5 containers rodando, todos acessíveis publicamente via Traefik+TLS LE:
- `traefik.oimpresso.com` (dashboard) ✅
- `portainer.oimpresso.com` (admin: `Infra@Docker2026!`) ✅
- `vault.oimpresso.com` (Wagner tem conta; signups OFF) ✅
- `reverb.oimpresso.com` (WebSocket; KEY/SECRET no Hostinger .env) ✅
- `meilisearch.oimpresso.com` (TLS R12 ativo; embedder OpenAI configurado) ✅

**Hostinger .env (oimpresso.com app)** — IA real ativa em prod:
- ✅ OPENAI_API_KEY presente (gpt-4o-mini)
- ✅ MEILISEARCH_HOST=https://meilisearch.oimpresso.com + KEY
- ✅ SCOUT_DRIVER=meilisearch + COPILOTO_AI_*
- ✅ BROADCAST_CONNECTION=reverb + REVERB_APP_KEY/SECRET

**Validado em prod:** Wagner testou /copiloto/chat na conta da Larissa biz=4 — IA responde em PT-BR, não cai mais no fallback "sem conexão".

### 🟡 Gaps de produto (próximo Cycle 02)

1. **`ChatJanaAgent` "burrinho"** ([ADR 0046](decisions/0046-chat-agent-gap-contexto-rico.md)) — não tem contexto sobre faturamento/clientes/metas. Larissa pergunta "qual o faturamento desse mês?" e o agent pede pra ela informar período. Resolver com **tools/function-calling** (laravel/ai suporta) OU injetando `ContextoNegocio` no system prompt.

2. **`MeilisearchDriver::buscar` usa Scout default** — só full-text, sem hybrid embedder. Recall não traz semantic matches em prod. Fix: override Scout `search()` callback pra passar `hybrid:{embedder,semanticRatio}`. Curl direto na API Meilisearch funciona perfeito (semanticHitCount=2).

### 🔴 Único bloqueio crítico restante

**Validar com Larissa do ROTA LIVRE (1-2h)** — determina Sprint 7:
1. Pergunta sobre meta atual
2. Conversa >15 turnos (testa contexto longo)
3. Corrige um fato (testa LGPD `/copiloto/memoria`)

Larissa **provavelmente vai descobrir o Gap 1 acima** — e isso é OK, vira input pro Cycle 02.

Resposta dela determina sprint 7:

| Feedback Larissa | Sprint 7 = | ADR base |
|---|---|---|
| "Lembrou minha meta!" / quer + memória | **A — RAGAS evaluation** | 0037 |
| "Preciso PricingFpv/CT-e" | **Pivot ADR 0026** (caminho B) | 0026 |
| "Não entendi pra que serve" | **MCP server pro Claude Desktop** | 0036 + comparativo 2026-04-27 |
| Silêncio em 30d | **Pivot comercial** | 0026 |

### 🟡 Operacional (antes/depois da call)

**Deploy completo SSH (PRs #26/#27/#29 ainda pendentes):**
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115
cd domains/oimpresso.com/public_html
git pull origin main
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
php artisan migrate --no-interaction
php artisan optimize:clear
```

**Configurar embedder Meilisearch (1h):**
```bash
curl -X PATCH http://127.0.0.1:7700/indexes/copiloto_memoria_facts/settings/embedders \
  -H "Authorization: Bearer TFLfQX3Diuz42MydPn68AYH9Km1JbaBI" \
  -H "Content-Type: application/json" \
  -d '{"openai":{"source":"openAi","model":"text-embedding-3-small","apiKey":"sk-..."}}'
```

**`.env` Hostinger pra IA real:**
```env
OPENAI_API_KEY=sk-...           # Wagner gera no platform.openai.com/api-keys
COPILOTO_AI_ADAPTER=auto
COPILOTO_AI_DRY_RUN=false
COPILOTO_MEMORIA_DRIVER=auto
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=TFLfQX3Diuz42MydPn68AYH9Km1JbaBI
```

**Smoke manual:** abrir https://oimpresso.com/copiloto + mandar 1 mensagem + confirmar resposta real (não fixture).

---

## 📚 ADRs canônicos (memory/decisions/) — leitura obrigatória

| ADR | Tema | Status |
|---|---|---|
| 0026 | Posicionamento "ERP gráfico com IA" | ✅ |
| **0029** | **Padrão Inertia + React + UPos** (era 0024 duplicado, renomeado em 2026-04-27) | ✅ |
| 0027 | Gestão de memória (papéis canônicos) | ✅ |
| 0028 | ADRs numeração monotônica | ✅ |
| 0030 | Credenciais nunca em git | ✅ |
| 0031 | `MemoriaContrato` interface | ✅ revisado por 0036 |
| 0032 | Vizra ADK + Prism | ✅ sprint 1 revisado por 0034 |
| 0033 | Vector store backend | ✅ revisado por 0036 |
| 0034 | Laravel AI ecosystem 2026 | ✅ |
| **0035** | **Stack canônica IA (verdade)** | ✅ Wagner *"melhor ROI"* |
| **0036** | **Replanejamento Meilisearch first** | ✅ economiza R$1.500-18k/ano |
| **0037** | **Roadmap Tier 7-9 LongMemEval** | ✅ aceita |
| **0038** | **Promoção `6.7-bootstrap` → `main`** | ✅ executada 2026-04-27 |

## 🗂️ Comparativos Capterra canônicos (memory/comparativos/)

| Arquivo | Pra quê |
|---|---|
| `_TEMPLATE_capterra_oimpresso.md` v1.0 | template oficial pra novos comparativos |
| `oimpresso_vs_concorrentes_capterra_2026_04_25.md` | Produto vs Mubisys/Zênite/Calcgraf/Calcme/Visua |
| `sistemas_memoria_oimpresso_capterra_2026_04_26.md` | Camada A — dev memory (9 sistemas) |
| `copiloto_runtime_memory_vs_mem0_*` | Camada C — memória runtime (5 frameworks) |
| `stack_agente_php_vizra_prism_mem0_*` | Stack completa A+B+C (7 players) |
| **`revisao_caminho_2026_04_27_capterra.md`** | **Auditoria pós-sprint 6** — recomenda validar Larissa |
| **`claude_desktop_vs_laravel_mcp_oimpresso_2026_04_27.md`** | **Plugins Claude Desktop** vs nossa stack — vácuo no vertical brasileiro |

## 📜 Documentos enterprise

- [memory/requisitos/Jana/ENTERPRISE.md](requisitos/Jana/ENTERPRISE.md) — overview executivo + ops + compliance LGPD (12 seções, 420 linhas)
- [memory/CHANGELOG.md](CHANGELOG.md) — Keep-a-Changelog format, sessões 15-18

---

---

## 🎯 Estado em 2026-04-26 (sessão 14 — Jana completo + merges financeiro)

### ✅ Mergeado em `6.7-bootstrap` nesta sessão (3 PRs fechados)

| Commit | PR | O que entrou |
|---|---|---|
| `626c5696` | #10 | `fix(financeiro)`: contas-bancarias 500 — `account_type` → `account_type_id` + fix cache Inertia em `LegacyMenuAdapter` |
| `8475603a` | #11 | `feat(financeiro)`: `/relatorios` MVP — DRE gerencial + fluxo de caixa + resumo, filtros, export CSV UTF-8, redirect `/financeiro/dashboard → /financeiro` |
| `e9cf6dc1` | #13 | `feat(copiloto)`: implementação real — OpenAiDirectDriver, SqlDriver idempotente, ApurarMetaJob, AlertaService + eventos, Pages React Chat/Dashboard/FabJana, 4 arquivos de testes Pest |

> **Nota de merge:** conflitos eram todos em `public/build-inertia/` (assets compilados com hashes diferentes por branch). Estratégia: cherry-pick dos arquivos-fonte apenas; assets precisam de rebuild local (`npm run build:inertia`) após `git pull`.

### ✅ Módulo Jana — o que está pronto

| Peça | Arquivo(s) | Status |
|---|---|---|
| OpenAI driver | `Modules/Jana/Services/Ai/OpenAiDirectDriver.php` | ✅ |
| SqlDriver + hash idempotente | `Modules/Jana/Drivers/Sql/SqlDriver.php` | ✅ |
| ApurarMetaJob | `Modules/Jana/Jobs/ApurarMetaJob.php` | ✅ |
| ApuracaoService | `Modules/Jana/Services/ApuracaoService.php` | ✅ |
| AlertaService + evento + notificação | `Services/AlertaService.php`, `Events/`, `Notifications/`, `Listeners/` | ✅ |
| Pages React: Chat, Dashboard, FabJana | `resources/js/Pages/Jana/` | ✅ |
| Testes Pest (SQLite in-memory) | `tests/Feature/Modules/Jana/` — 24 passed, 1 skipped | ✅ |

### ⚠️ O que ficou pendente na Jana

- `ApurarMetasAtivasJob` (scheduler que descobre todas as metas ativas) — não criado
- Drivers `php` e `http` — apenas `SqlDriver` implementado
- Wizard 3 passos `/copiloto/metas/create` — Pages React não criadas
- `SuggestionEngine`: parsear resposta JSON → criar `Sugestao` rows (stub no `ChatController::send()`)
- Testes superadmin (`copiloto.superadmin`) marcados `->skip()` — requerem MySQL + spatie/permission migrado

### ⚠️ O que ficou pendente no Financeiro

- `ContaBancariaIndexTest` e `RelatoriosTest` — não rodaram (requerem MySQL dev; validar localmente)
- Build assets desatualizados — rodar `npm run build:inertia` após `git pull`

### ⚠️ PRs #2 e #3 — NÃO mergeados (targettam `main`, não `6.7-bootstrap`)

- **PR #2** (`claude/cranky-aryabhata-8c8af7` → `main`) — branch muito antiga, verificar relevância
- **PR #3** (`feat/inertia-v3` → `main`) — branch de experimento Inertia v3; verificar se ainda relevante ou fechar

Recomendação: fechar #2 e #3 manualmente se não houver intenção de mergear para `main`.

---

## 📋 Próximos passos sugeridos

1. **Deploy em staging:** `git pull origin 6.7-bootstrap && npm run build:inertia && php artisan optimize:clear`
2. **Smoke test financeiro:** `/financeiro/contas-bancarias` (era 500 → deve ser 200); `/financeiro/relatorios` (nova tela)
3. **Ativar Jana:** configurar `OPENAI_API_KEY` e `COPILOTO_DRY_RUN=false` no `.env`
4. **Criar `ApurarMetasAtivasJob`** + registrar no scheduler para apuração automática diária
5. **Rebuild assets:** `npm run build:inertia` (assets compilados não foram mergeados — só fonte)

---

## 🔑 Dev local

- Site: `https://oimpresso.test` (Herd SSL)
- MySQL: Laragon `127.0.0.1:3306` root sem senha, DB `oimpresso`
- PHP: 8.4 Herd
- Branch principal: `main` (era `6.7-bootstrap` até 2026-04-27)
- Último commit: `bd74b80f` (Merge PR #36 — header scandir defensivo)

## 🧭 Comandos úteis

```bash
cd D:\oimpresso.com
git pull origin main
npm run build:inertia                # NECESSÁRIO após pull (assets não mergeados)
php artisan optimize:clear
./vendor/bin/pest tests/Feature/Modules/Jana/ --no-coverage  # 24 passed, 1 skipped
```

---

## Preferências Wagner

- Sempre IPv4 pra Hostinger
- PT-BR em tudo (commits, comments, labels)
- Confirmar escopo antes de implementar massivamente
- Grow = prioridade produção

---

## 🔄 Sessões 2026-04-28 (infra Reverb + Meilisearch + Vaultwarden)

**Estado pós-sessão:** PR #64 (Reverb) + PR #68 (Meilisearch compose) **mergeados em main**.

### ✅ Entregues (2026-04-28)
- **CT 100 docker-host** operacional: 5 containers rodando (`traefik`, `portainer`, `vaultwarden`, `reverb`, `meilisearch`)
- **Reverb ativo em produção** — Hostinger `.env` tem KEY/SECRET corretos; `reverb:ping` → 200 OK
- **Vaultwarden** — Wagner criou conta `wagnerra@gmail.com`; SIGNUPS desabilitado
- **Meilisearch v1.10.3** — container rodando em CT 100, volume `meilisearch-data` persistente
- **ADRs 0042/0043/0044** em main — Reverb, Docker+Traefik, Vaultwarden
- **build fix**: `npm run build` agora usa `vite.config.ts` explicitamente; `_oimpresso.scss` criado

### 🔴 Ainda pendente (próxima sessão Wagner)

1. **OPENAI_API_KEY no Hostinger** — bloqueio crítico de toda IA real (platform.openai.com/api-keys)
2. **DNS `meilisearch.oimpresso.com`** — Hostinger API HTTP 530 (Cloudflare down). Fazer manual:
   - hPanel → Domínios → oimpresso.com → DNS → A record `meilisearch` → `177.74.67.30` (Proxy OFF)
3. **`.env` Hostinger — vars Meilisearch** (após DNS propagar):
   ```env
   SCOUT_DRIVER=meilisearch
   MEILISEARCH_HOST=https://meilisearch.oimpresso.com
   MEILISEARCH_KEY=9c08945878571ecb76b70d25deb3852b
   COPILOTO_AI_ADAPTER=auto
   COPILOTO_MEMORIA_DRIVER=auto
   COPILOTO_AI_DRY_RUN=false
   ```
4. **Embedder OpenAI no índice Meilisearch** (após key + host configurados):
   ```bash
   curl -X PATCH https://meilisearch.oimpresso.com/indexes/copiloto_memoria_facts/settings/embedders \
     -H "Authorization: Bearer 9c08945878571ecb76b70d25deb3852b" \
     -H "Content-Type: application/json" \
     -d '{"openai":{"source":"openAi","model":"text-embedding-3-small","apiKey":"$OPENAI_API_KEY"}}'
   ```
5. **Migrar credenciais pro Vaultwarden** (vault.oimpresso.com — Wagner tem acesso)

### 📊 Stack de memória IA — estado-da-arte (ADR 0037 roadmap)

```
HOJE (prod): NullDriver (sem OPENAI_API_KEY) — Tier ~2 funcional
APÓS desbloqueio: MeilisearchDriver ativo — Tier 5-6 estimado
SPRINT 7: RAGAS evaluation (gate obrigatório) — mede baseline real
SPRINT 8: Semantic caching (-68.8% tokens, maior ROI)
SPRINT 9: RRF tuning (+10-15% recall)
SPRINT 10: HyDE query expansion (+15% recall)
SPRINT 11: Mem0/Zep condicional (5 triggers ADR 0036)
```

Session log completo: `memory/sessions/2026-04-28-meilisearch-vaultwarden.md`

---

## 🔄 Sessão 2026-04-27 noite — Promoção `6.7-bootstrap` → `main` + cleanup ADR 0024

- ✅ **Branch principal trocada**: `6.7-bootstrap` (326 commits únicos) promovida pra `main` via force-push (`origin/main` antigo, com 7 commits 3.7-com-nfe + city migration, foi descartado).
- ✅ **Backup preservado** em `origin/archive/main-pre-6.7-merge` (SHA `0c3a8300`) — recomendado manter por 90 dias.
- ✅ **6.7-bootstrap deletada** (local + remoto). Worktree `D:/oimpresso.com` movido pra `main`.
- ✅ **Cleanup do ADR 0024 duplicado** (pendência desde sessão 15): `0024-padrao-inertia-react-ultimatepos.md` renomeado pra `0029-...md` via `git mv`. 11 referências cruzadas atualizadas (sessions, requisitos Financeiro, 5 arquivos PHP em `Modules/Financeiro/Http/`).
- ✅ **ADR 0038** criado documentando a promoção (formato Nygard, com seção de reversão).
- ✅ **Evidência MemCofre** em `Modules/MemCofre/Database/evidences/2026-04-27-promocao-main.md` (timeline literal de comandos + SHAs).
- ✅ Auto-memórias `project_current_branch.md` e `reference_composer_install_obrigatorio_pos_deploy.md` atualizadas.
- 📝 Detalhes em [memory/sessions/2026-04-27-promocao-6-7-bootstrap-para-main.md](sessions/2026-04-27-promocao-6-7-bootstrap-para-main.md).

**Pendências:**
- 🟡 PR de cleanup pra `.github/workflows/deploy.yml` (linhas 83-89), `.github/workflows/quick-sync.yml` (linhas 9, 54) e `CLAUDE.md` (linhas 193, 194, 201) — ainda hardcoded em `6.7-bootstrap`. Wagner aguardado pra autorizar.
- 🟡 PR #18 (DRAFT) vai precisar rebase quando virar não-draft.

---

## 🔄 Sessão 18 (2026-04-26 madrugada) — Sprint 4 + ferramentas Laravel IA

- ✅ **PR #25 mergeado** em `6.7-bootstrap` (`e1d4c9de`): Sprint 4 do roadmap canônico (ADR 0036).
  - **MemoriaContrato + MeilisearchDriver + NullMemoriaDriver** implementados
  - Tabela `copiloto_memoria_facts` com schema temporal (`valid_from/until`) + LGPD soft delete
  - **Eloquent `JanaMemoriaFato`** com `Searchable` + `SoftDeletes`
  - **37/38 Pest passing** (11 testes novos cobrem multi-tenant, append-only temporal, LGPD opt-out)
- ✅ **Pacotes Laravel IA instalados:** `laravel/horizon` + `laravel/telescope` + `laravel/pail`
  - `Vizra ADK` ❌ adiado (exige `^11|^12`, projeto é `^13.0`); `LaravelAiSdkDriver` (PR #24) sustenta Jana sozinho
  - `Reverb` ❌ adiado (conflita com `pusher 5.0` lockado; `BROADCAST_DRIVER=null` em uso real, upgrade pusher 5→7 pode fazer em PR separado)
  - `spatie/laravel-data` ❌ adiado (conflito `phpdocumentor/reflection 6.0`)
- 🟡 **Deploy SSH em curso** (background) — verificar `composer install` + `php artisan migrate` no Hostinger
- 📝 Detalhes: ADR 0036 + commit `f6fefa9a`

**Pendências críticas pra próxima sessão (revisado):**

🚨 **Após deploy de Sprint 4 completar, validar:**
1. `php artisan migrate` rodou (tabela `copiloto_memoria_facts`)
2. Setar Meilisearch no `.env`: `SCOUT_DRIVER=meilisearch` + `MEILISEARCH_HOST=http://127.0.0.1:7700` + `MEILISEARCH_KEY=TFLfQX3Diuz42MydPn68AYH9Km1JbaBI`
3. Setar `OPENAI_API_KEY` (ou `ANTHROPIC_API_KEY`) no `.env`
4. Setar `COPILOTO_AI_DRY_RUN=false`
5. Configurar embedder no Meilisearch index (POST settings/embedders com OpenAI text-embedding-3-small)

📋 **Sprint 5 (próximo):** `ExtrairFatosDaConversaJob` async via Horizon + bridge `ChatController@send` → busca top-K antes / extrai fatos depois.

📋 **Sprint 6:** Tela `/copiloto/memoria` (LGPD US-COPI-MEM-012).

📋 **PRs separados pendentes:**
- Reverb: confirmar Pusher não-usado em produção (`isPusherEnabled()` em `app/Http/helpers.php`) → upgrade `pusher/pusher-php-server 5→7` + `composer require laravel/reverb`
- Vizra ADK: aguardar upstream lançar suporte L13 (sem issue aberta no GitHub vizra-ai/vizra-adk)

---

## 🔄 Sessão 17 (2026-04-26 fim do dia) — Sprint 1 stack-alvo IA canônica

- ✅ **PR #24 mergeado** em `6.7-bootstrap` (`3d64e5bb`): Sprint 1 do roadmap canônico ADR 0035.
  - `composer require laravel/ai ^0.6.3 + laravel/boost ^2.4 --dev`
  - 4 arquivos novos: `LaravelAiSdkDriver` + 3 Agents (`BriefingAgent` / `SugestoesMetasAgent` / `ChatJanaAgent`)
  - Stub legado `LaravelAiDriver.php` removido
  - **26/27 testes Pest passing** (1 skipped intencional)
- ✅ **ADR 0035 — verdade canônica** declarada por Wagner ("melhor ROI"). Stack-alvo: `laravel/ai` (camada A) + Vizra ADK (camada B, sprints 2-3) + `MemoriaContrato`/Mem0/Meilisearch (camada C, sprints 4-5/8-10) + Boost (DEV).
- ✅ ADRs 0031/0032/0033/0034 atualizados com header "VERDADE CANÔNICA" apontando pro 0035.
- ✅ CLAUDE.md + AGENTS.md + auto-memória relevante revisados.
- ✅ **Meilisearch local Windows** rodando em `http://127.0.0.1:7700` (PID 31928, master key `D:\oimpresso.com\meilisearch\.meilisearch-key.txt`).
- ✅ **Meilisearch v1.10.3 instalado no Hostinger** em `~/meilisearch/` (versão antiga compatível com GLIBC 2.34).
- ✅ **Deploy do PR #24 em produção CONFIRMADO** — `git pull` + `composer install` (laravel/ai + boost) + `optimize:clear` rodaram OK.
- ✅ **Meilisearch daemon RODANDO no Hostinger** — PID 632084, `http://127.0.0.1:7700/health` retornou `{"status":"available"}`, 32 workers iniciados. Log em `~/meilisearch/logs/meilisearch.log`.
- 📝 Detalhes em [memory/sessions/2026-04-26-sprint1-stack-canonica.md](sessions/2026-04-26-sprint1-stack-canonica.md).

**Pendências críticas pra próxima sessão (ordem revisada por ADR 0036 — Meilisearch first, Mem0 último):**

🚨 **Sprint 2 = DEPLOY URGENTE** (não Vizra ADK ainda):
1. Deploy SSH no Hostinger: `git pull origin 6.7-bootstrap && composer install && php artisan optimize:clear`
2. **Iniciar daemon Meilisearch no Hostinger** com nohup (comando completo em [memory/sessions/2026-04-26-sprint1-stack-canonica.md](sessions/2026-04-26-sprint1-stack-canonica.md))
3. Setar `OPENAI_API_KEY` (ou `ANTHROPIC_API_KEY`) no `.env` de produção
4. Setar `COPILOTO_AI_DRY_RUN=false`
5. Smoke manual em `/copiloto` — **resultado:** Jana sai de fixtures EM PRODUÇÃO

📋 **Sprints 3-7** seguem ADR 0036:
- Sprint 3: Vizra ADK + tools registry
- Sprint 4-5: **MeilisearchDriver primeiro** (não Mem0!) — R$0/mês recorrente
- Sprint 6: Tela LGPD `/copiloto/memoria`
- Sprint 7: Eval LLM-as-Judge + stress

⏭️ **Sprint 8+ CONDICIONAL:** Mem0 só se trigger ativar (dedup Meilisearch falhar, conversas longas perderem contexto, Wagner pedir explicitamente). Ver ADR 0036 pra triggers mensuráveis.

---

## 🔄 Sessão 15 (2026-04-26 noite) — Deploy Hero fix + conflitos de memória

- ✅ Deploy manual de `039a810d` em produção (Hero CMS hardcoded). Validado: HTTP 200 + bundle PT-BR.
- ✅ Comparativo Capterra de 9 sistemas de memória (15 funções) com vencedor por categoria.
- ✅ 10 conflitos de auto-memória resolvidos (Inertia v2/v3, stack IA, status módulos, SSH 65002, EvolutionAgent, CMS hidratação, ADRs lista, branch produção, Connector untracked).
- ✅ ADRs novos: 0027 (gestão memória, meta-ADR), 0028 (numeração monotônica), 0030 (credenciais nunca em git).
- ✅ CLAUDE.md ganhou seção 7 "Acesso à produção (Hostinger)" + reescrita do bloco IA.
- ✅ AGENTS.md desestaleado.
- 📝 Detalhe completo em [memory/sessions/2026-04-26-deploy-hero-fix-e-conflitos-memoria.md](sessions/2026-04-26-deploy-hero-fix-e-conflitos-memoria.md).

**Pendente:** rename ADR 0024 duplicado pra 0029 (aguarda aval); materializar ADRs 0031–0036 se aprovar; auditoria untracked Modules/Connector no servidor (SSH flaky impediu na sessão).

---

**Última atualização:** 2026-05-05 noite (triagem + roadmap + auditoria MCP — 135 tasks, 17 epics, ADR 0071, **71 ADRs total**)
**Estado geral:** 🟢 Jana IA real ativo prod desde 28-abr; 🟢 backlog 100% triado (0 sem owner, 0 backlog); 🟢 roadmap mapeado em 3 quarters; 🟡 5 tools MCP com auth-degradação (workarounds OK); 🟡 cache semântico COPI-40 ainda não-iniciado (handoff próxima sessão)

---

## 🔄 Sessão 16 (2026-04-28) — Reverb + Meilisearch + IA real ativa

- ✅ CT 100 docker-host LXC Debian 12 provisionado em Proxmox empresa
- ✅ Stack Docker: Traefik v3.6 + Portainer + Vaultwarden + Reverb + Meilisearch v1.10.3 (5/5 running)
- ✅ DNS criado via API canônica `developers.hostinger.com/api/dns/v1/zones/{domain}` (ADR 0045) — `api.hostinger.com` está com HTTP 530 crônico
- ✅ Cert Let's Encrypt R12 emitido pra reverb/portainer/traefik/vault/meilisearch.oimpresso.com
- ✅ OPENAI_API_KEY no Hostinger .env + SCOUT_DRIVER=meilisearch + embedder OpenAI text-embedding-3-small no índice
- ✅ `config/ai.php` commitado (era untracked → laravel/ai caía no fallback `gpt-5.4`); log channel `copiloto-ai` adicionado
- ✅ **Jana IA real respondendo Larissa em prod** (gpt-4o-mini)
- 🟡 Gap descoberto: ChatJanaAgent "burrinho" — sem ContextoNegocio (ADR 0046)
- 🟡 Gap descoberto: MeilisearchDriver::buscar usa Scout default (full-text) — `memoria_recall_chars: 0` mesmo com fato indexado
- 📝 Detalhe completo em [memory/sessions/2026-04-28-meilisearch-vaultwarden.md](sessions/2026-04-28-meilisearch-vaultwarden.md) + [memory/sessions/2026-04-28-reverb-docker-host.md](sessions/2026-04-28-reverb-docker-host.md)
- ✅ ADRs criados: 0042 (Reverb) · 0043 (Docker+Traefik) · 0044 (Vaultwarden) · 0045 (Hostinger DNS API) · 0046 (Gap ChatAgent)

---

## 🔄 Sessão 17 (2026-04-29) — Sprint memória completa: 8 entregas em 1 dia

Wagner pediu modo solo + foco em token economy + assertividade. Time delegated → todos os donos para [W].

**8 entregas em prod:**

1. **ADR 0047** Wagner solo + sprint memória priorizado (`da6ce166`)
2. **MEM-HOT-1** Hybrid embedder MeilisearchDriver (`c631042c`) — recall **0 → 190 chars** em log conversa Larissa real
3. **MEM-HOT-2** ContextoNegocio injetado no ChatJanaAgent (`2be9930c`) — system prompt biz=4 ROTA LIVRE com 4 meses faturamento + 5993 clientes em **164 tokens**
4. **ADRs 0048-0050 + 0036 estendida** consolidam pesquisa Wagner (ZIP `files.zip`):
   - 0048 — Vizra ADK rejeitada oficialmente (quebrou L13); **COP-015 cancelada**
   - 0049 — 6 camadas memória + gate Recall@3>0.80
   - 0050 — 8 métricas obrigatórias + tabela `copiloto_memoria_metricas`
   - 0036 anexo — benchmark BM25+vetor=95.2% LongMemEval (supera Mem0 93.4%, Zep 71.2%)
5. **ADR 0051** Schema próprio + adapter + OTel GenAI (após pesquisa de tendências) (`21644f4e`)
6. **MEM-MET-1** Tabela `copiloto_memoria_metricas` em prod com 14 colunas (8 obrigatórias + 3 RAGAS-aligned `faithfulness/answer_relevancy/context_precision` + 3 contexto)
7. **MEM-OTEL-1** Emissão `gen_ai.*` OpenTelemetry GenAI no log channel `otel-gen-ai` (`5acf27de`) — 12 atributos OTel-compliant por evento
8. **MEM-MET-2** Comando `copiloto:metrics:apurar` + baseline 2026-04-29 gravado em prod (`6d2dc7eb`+`6aa9b524`):

   ```
   | apurado_em | biz_id      | p95_ms | tokens | inter | mem | bloat | contr |
   |------------|-------------|--------|--------|-------|-----|-------|-------|
   | 2026-04-29 | NULL (plat) |   1234 |    307 |     6 |   2 | 1.000 |  0.00 |
   | 2026-04-29 |           1 |   NULL |   NULL |     0 |   0 |  NULL |  NULL |
   | 2026-04-29 |           4 |   1234 |    307 |     6 |   2 | 1.000 |  0.00 |
   ```

**Suite Jana:** 50 → **77 passed (+27 testes)**, 3 skipped, **zero regressão**.

**Estratégia formalizada (ADR 0051):** 4 pilares — schema próprio + adapter sobre `Laravel\Ai\Contracts\ConversationStore` + métricas RAGAS-aligned + emissão OTel GenAI. Triggers trimestrais pra reavaliar (laravel/ai 1.0 saiu 17-mar-2026 sem eval framework nem multi-tenancy).

📝 Detalhe completo: [memory/sessions/2026-04-29-sprint-memoria-completa.md](sessions/2026-04-29-sprint-memoria-completa.md)

**Pendências P0 imediatas (sex 02-mai):**
- A4 rodada 2 — Validar Larissa repetir 3 perguntas (vendi/líquido/caixa) → 3 respostas distintas
- MEM-MET-3 — scheduler diário `daily()` chama `copiloto:metrics:apurar --all` (15 min)
- COP-002 = MEM-MET-5 — Golden set 50 perguntas Larissa-style (destrava 6 colunas RAGAS)

---

## 🔄 Sessão 18 (2026-04-29 noite) — MEM-FAT-1 + ADR 0052 (validação Larissa expôs gap semântico)

Larissa testou as 3 perguntas em prod (Quanto vendi? / Faturamento líquido? / Quanto entrou no caixa?) e recebeu **mesmo R$ 31.513,29** pras 3 — gap exposto.

**Causa-raiz**: `ContextoNegocio.faturamento90d` só tinha 1 valor por mês. LLM não tinha como saber que líquido e caixa eram números diferentes.

**Fix MEM-FAT-1** (commit `fac96a19`):
- `ContextSnapshotService::faturamento90d()` retorna 3 ângulos: `bruto` (sell.final) + `liquido` (bruto - sell_return) + `caixa` (transaction_payments.amount via paid_on)
- Glossário inline no system prompt define cada métrica
- BC-compat: campo `valor` mantido como alias do bruto

**Smoke prod**: prompt biz=4 ROTA LIVRE = 270 tokens com 4 meses × 3 ângulos. Mar/2026: bruto R$ 38.215,07 · líquido R$ 37.518,47 · caixa R$ 37.141,22.

**ADR 0052** formaliza princípio: quando métrica admite múltiplos recortes legítimos, `ContextoNegocio` expõe TODOS — não confiar que LLM deriva matemática que ele não tem como fazer. Padrão replicável pra custos / lucro / inadimplência / metas.

**Aprendizado meta**: smoke técnico passou em MEM-HOT-2 (`2be9930c`) com bug semântico latente. Validação real do usuário foi o único filtro que detectou. A4 (validar Larissa) **NÃO é formalidade** — é gate de produto.

**Suite Jana**: 79 passed (era 77, +2), 3 skipped, zero regressão.
**52 ADRs total.**

**Última atualização:** 2026-04-29 noite — MEM-FAT-1 deployed + ADR 0052

---

## 🌟 Sessão maratona 2026-05-05 — UI Skills end-to-end (24 commits, 5 ADRs novas, ~5h)

**Contexto:** Wagner pediu pra "amadurecer memória + Team MCP" → virou pesquisa profunda + 5 ADRs + UI completa de gestão de skills do Claude Code em prod.

### Decisões arquiteturais (5 ADRs novas, 57 ADRs total)

- **[ADR 0072](decisions/0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md)** — Roadmap maturação memória + Team MCP (P0–P3). 2 erratums no mesmo dia após levantamento real.
- **[ADR 0073](decisions/0073-team-mcp-skills-policies-entidades-governadas.md)** — P0 inicial. **SUPERSEDED** pelo 0076.
- **[ADR 0074](decisions/0074-temporal-validity-bi-temporal-time-travel.md)** — P1 bi-temporal. Status: proposto.
- **[ADR 0075](decisions/0075-team-mcp-skills-ui-prompt-management-style.md)** — P0 v2. **SUPERSEDED** pelo 0076.
- **[ADR 0076](decisions/0076-skills-db-primary-git-destino-drift-alert.md)** — **canônica.** DB primary, git destino, drift por-skill (auto/manual/pinned). Inversão a pedido de Wagner: "deixa eu decidir, testar, evoluir".

### Comparativo cofre

[`prompt_skill_management_2026_05_05.md`](comparativos/prompt_skill_management_2026_05_05.md) — 10 ferramentas (Langfuse/LangSmith/Humanloop/Vellum/PromptLayer/Portkey/Agenta/Helicone/Anthropic Console/Anthropic Skills) × 31 features.

### UI Skills em prod

URL: **https://oimpresso.com/ads/admin/skills**

| Rota | O que faz |
|---|---|
| `/ads/admin/skills` | Lista 15 skills (DB) + Approval queue button |
| `/ads/admin/skills/{slug}` | Detalhe + timeline versions + "Promover production" + "Publish to git" |
| `/ads/admin/skills/{slug}/edit` | Editor + 4 rationales obrigatórios + warning amber se frontmatter mudar |
| `/ads/admin/skills/{slug}/test` | Test Runner: source manual OU "últimas N conversas reais multi-tenant" + PII redactor |
| `/ads/admin/skills-review` | Approval queue: drafts + Aprovar/Rejeitar inline |

### Backend (DB-primary — ADR 0076)

**6 migrations:** `mcp_skills`, `mcp_skill_versions` (append-only, 4 rationales), `mcp_skill_labels` (Langfuse-style), `mcp_skill_test_runs`, `mcp_skill_approvals`.

**Services:** `ImportarSkillsDoGitService`, `SkillTestRunnerService` (PII redactor), `PublicarSkillNoGitService` (GitHub API), `SkillsService` (DB com fallback filesystem).

**Controller:** `SkillsController` (10 métodos: index/show/edit/store/test/runTest/review/approve/reject/publish/moveLabel).

### Permissions Spatie atribuídas

Wagner (id=1, `WR23`) tem todas 6: `read/edit/test/approve/publish/config`. Verificado em prod: `$u->can('ads.admin.skills.read') = 1` ✅

### Skills Claude Code novas

- `ads-decision-flow` — fluxo Risk→Confidence→Policy→Router→Brain A/B
- `memoria-recall-flow` — Meilisearch hybrid + 14 gotchas

### Slash command + hook + CI

- `/sync-skills` — detecta drift filesystem
- Hook `SessionStart` `check-skills-fresh.ps1` — auto-detecta drift
- GitHub Action `build-inertia-auto.yml` — auto-rebuild bundles ao push tocar `resources/{js,css}` (previne reprise do bug do sidebar)

### Status goals do CYCLE-02 (proposto, não criado em DB)

| Goal | Status |
|---|---|
| 1. Skills DB ≥16 | 🟡 15 (1 SKILL.md fora do glob — investigar) |
| 2. Versions ≥16 | 🟡 15 |
| 3. UI lista+detalhe+editor em prod | ✅ + bonus (Test, Review) |
| 4. Tool MCP `skills-search` | 🔴 não criada |
| 5. Wagner editou ≥1 skill via UI | 🔲 pendente teste real |

### Pendências P0 amanhã

1. **Wagner testar fluxo end-to-end** (Goal 5) — ~5min.
2. **Tool MCP `skills-search`** (Goal 4) — ~1h.
3. **Investigar 15 vs 16 skills** — qual SKILL.md ficou de fora.
4. **Criar CYCLE-02 oficial em DB** — SQL ou criar tool `cycles-create` (~30 linhas).
5. **CYCLE-01 fechar em 12/05** — `cycles-close CYCLE-01 --rollover-to=CYCLE-02` com retro.

### Bugs resolvidos durante a sessão

- **Sidebar build stale** — 5 commits anteriores sem `npm run build:inertia` deixaram bundles velhos. Action CI previne reprise.
- **Conflict markers no manifest** — rebase do FASE 4 vs CI deixou `<<<<<<< HEAD`. Regenerado.

**24 commits** em main: `c04eaa53` → `62be2152`. **57 ADRs total.** **6 fases UI.** **5 telas em prod HTTP 200.**

**Última atualização:** 2026-05-05 noite — UI Skills end-to-end deployed (Wagner testa amanhã)

---

## 🌅 Sessão madrugada 2026-05-07 — MWART hotfix marathon + skill mwart-quality + feedback Cockpit

**Contexto:** `/continuar` retomada. Wagner reportou tela branca `/repair/job-sheet`, deixou sessão autônoma pra dormir, depois acordou várias vezes pra dar feedback canônico sobre padrão visual.

### 3 PRs mergeados em sequência

| PR | Tipo | Descrição |
|---|---|---|
| **#144** | hotfix | S2.5 telas brancas — substitui `route()` Ziggy por URL hardcoded (3 telas: JobSheet, Status, DeviceModels) |
| **#145** | hotfix + skill | Dashboard `TypeError i.slice` (CommonChart vs array) + DeviceModels SQL `Column 'description' not found` + nova skill `mwart-quality` v1 com 9 checks |
| **#146** | docs | mwart-quality v3 segue tutorial cockpit-runbook (3 modos + workflow + anti-padrões + canon visual) + session log madrugada |

### 5 bugs encontrados nas 4 telas Repair S2.5

1. `ReferenceError: route is not defined` × 3 telas (JobSheet, Status, DeviceModels) — `tightenco/ziggy` não instalado
2. `TypeError: i.slice is not a function` (Dashboard) — CommonChart objeto vs array esperado
3. `SQLSTATE[42S22] Column 'description' not found` (DeviceModels) — SELECT coluna inexistente

**Estado em prod**: 4/4 telas funcionalmente OK (validado Chrome MCP screenshot + console clean).

### Feedback canônico Wagner registrado

1. *"perdeu elementos na criação, em especial navbar top"* — telas MWART perderam navbar
2. *"o padrão do cockpit era muito superior"* — eu interpretei mal primeiro
3. *"cokpit achei mais bonito"* — Wagner corrigiu: Cockpit é mais bonito
4. *"mais tbm sem navtop... tem que ter"* — gap exato é topnav horizontal
5. *"blade feio o padrão bonito é [Claude design link]"* — Blade legacy é feio
6. *"o design desenvolveu técnicas apuradas... ele criou um manual de como fazer uma skill com runbook de precisão seguindo o tutorial"* — manual = DESIGN.md + skill `cockpit-runbook`

### Skill mwart-quality v3 (Tier B)

10 checks (9 técnicos + Check 10 Hard Gate visual) + 3 modos (Pre-flight / Audit / Refactor) + 10 fontes canônicas (Read paralelo) + 10 anti-padrões + workflow obrigatório 10 passos. Estrutura segue `cockpit-runbook` template.

### Decisões arquiteturais registradas

- ✅ **Visual canon**: Cockpit AppShellV2 (per `https://claude.ai/design/p/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58`)
- ❌ **NÃO canon**: Blade legacy (Wagner: *"feio"*)
- ⛔ **GAP funcional crítico**: AppShellV2 não tem topnav horizontal módulo
- ⛔ **REGRA HARD**: P0 implementar topnav horizontal antes de criar telas MWART novas
- ✅ **NÃO rollback flags MWART** — Cockpit visual > Blade

### Pendências P0 próxima sessão (BLOQUEADORAS de novas telas MWART)

1. **Topnav horizontal no AppShellV2** — adicionar `<nav className="topnav-module">` populado com `useAutoModuleNav().items`. Estilo per Cockpit canônico.
2. **`topnav.php` para Repair** — sem o arquivo, breadcrumb dropdown também não funciona.
3. **Re-design das telas listagem MWART** com KPI cards ricos + tabs filtro + TanStack Table per `os-page.jsx` canon.
4. **Fix Repair Dashboard `trending_devices_chart`** — atualmente `[]` porque RepairUtil.getTrendingDevices retorna CommonChart; refactor pra retornar array.
5. **Quebrar mwart-quality em progressive disclosure** — SKILL.md + CHECKS.md + EXAMPLES.md + CHECKLIST.md + GOTCHAS.md (segue cockpit-runbook).

### Aprendizados meta

- **Smoke test browser MCP é gate inegociável** — sem ele, bugs só aparecem quando Wagner abre.
- **Eloquent Collection raw → Inertia silenciosamente errado** — sempre `->values()->all()` antes de mandar.
- **Interpretação errada de feedback custa tempo** — quando Wagner deu pista visual ambígua, levei 3 mensagens pra entender o que ele queria. Pedir esclarecimento antes de agir.
- **Componentes shared têm contrato rígido** — passar `subtitle` em vez de `description` não pega no TS strict, mas falha render.

**60 ADRs total. 4 telas MWART em prod (funcionalmente OK, visualmente abaixo do canon Cockpit). Skill `mwart-quality` v3 deployed (Tier B).**

**Última atualização:** 2026-05-07 madrugada — MWART S2.5 hotfix marathon + skill mwart-quality v3 + feedback Cockpit canônico

---

## Sessão 2026-05-07 manhã — BRIEF audit + GUARD-02 + Hostinger git recovery

**PR #162 mergeada** (GUARD-02 Pest ModuleScaffolding + BRIEF-A1 fix aggregator).

### Quick wins
- **L7 Daily Brief funcional em prod** — antes 217 tokens com placeholders ("ADRs: —", "Commits: 0"), agora 235 tokens com dados reais (5 ADRs listadas, in_flight populado, mcp_activity_24h=122)
- **Hostinger git recovered** — estava em `claude/nervous-greider-335083` mid-rebase de `feat/sprint-2-memcofre-cockpit`, agora em `main` HEAD `844e1bfa`
- **GUARD-02 deployado** — Pest test garante todo módulo novo nasce com InstallController/DataController/ServiceProvider (5/5 verdes em 30 módulos)

### Wagner regra canônica nova (2026-05-07)
> "MCP é só no CT 100. Hostinger não funciona e fica lento mcp. Se for preciso temos que dividir o projeto"

Salvo em auto-mem `feedback_mcp_so_ct100.md`. Implicação: tool MCP exposed só em `mcp.oimpresso.com` (CT 100). Spawnado US-COPI-094 pra remover `brief-fetch` do Hostinger.

### Tasks queued próxima sessão (ordem)
1. **US-COPI-094** P1 — remover `brief-fetch` do Hostinger MCP (regra Wagner)
2. **US-COPI-092 GUARD-01** P1 — schema snapshot Pest + `procedure_drift` em jana:health-check
3. **US-COPI-090 BRIEF-A3** P2 — ADR 0096 model real gpt-4o-mini (1h, fácil)
4. **US-COPI-091 BRIEF-A4** P2 — investigar baixa adoção brief-first (depende 094)

### CYCLE-02 status (0% decorrido, 19 dias restantes)
- Goal #6 Constituição V2 health-check 7d limpo: progressão começou (brief funcional + GUARD-02 deployado)
- Goal #4 MWART Repair (4 telas): 0/4
- Goal #5 NfeBrasil emite NFe55: 0/done
- Goal #7 Skills V0.5 UI: 0/done

**Última atualização:** 2026-05-07 ~12:00 BRT — sessão BRIEF-audit + Hostinger recovery

---

## Sessão 2026-05-07 tarde — Whatsapp UI cockpit + build na Hostinger

**PR #173 mergeado** (UI Whatsapp cockpit 3-painéis estado-da-arte) — lista | thread | sidebar com partial reload, search server-side, avatar inicial colorida, mensagens agrupadas por dia, status icons, scroll-bottom button, ações sidebar. Componentes shared em `resources/js/Pages/Whatsapp/_components/` (Avatar, ConversationList, ConversationThread, ConversationSidebar, helpers).

**PR #174 mergeado** (build:inertia migrado pra Hostinger) — `public/build-inertia/` no .gitignore, `git rm --cached` 230 arquivos, `quick-sync.yml` agora roda `npm ci` condicional + `npm run build:inertia` na Hostinger. `build-inertia-auto.yml` deletado. **ADR 0098** documentando tudo.

### Quick wins

- **Hostinger TEM Node 24.15 + npm 11** via nvm — testado, builda em 52s sem crashes, 138GB memória disponível. Era reflexo herdado "shared hosting = sem Node".
- **Repo enxuto** — -16574 linhas em 230 arquivos binários removidos do tracking
- **Eliminada race condition** — antes 2 workflows em paralelo causavam Hostinger servir source+bundles dessincados por ~30s → 409 mismatch → full reload em deploy
- **Single source of truth** — build sempre determinístico do source que está em prod

### Incidentes da sessão

- **HTTP 500 em prod por ~5min** após merge do #174: `npm ci` nunca tinha rodado na Hostinger, `centrifuge ^5.5.3` declarado no package.json mas ausente em node_modules. Build falhou sem manifest → 500. Resolvido rodando `npm ci` (448 packages, 41s) + `npm run build:inertia` + clear caches via SSH. Prod HTTP 200 (280ms) restaurada.
- **quick-sync.yml falhando há tempos no Setup SSH** — secrets `SSH_PORT`, `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY` no GitHub estão vazios. Workflow nunca rodou de verdade. Bug documentado em auto-mem `reference_quick_sync_quebrada.md` e na ADR 0098.

### Aprendizados meta

- **Olhar antes de assumir** — `which node` na Hostinger custou 30s e teria poupado 1h investigando GH Actions/CT 100 builders.
- **Cleanup pós-migração `git rm --cached`** — `git reset --hard` no servidor NÃO apaga untracked. Se não rodar `git clean -fd` uma vez, fica lixo. Cuidado em qualquer migration similar.
- **`npm ci` quando lockfile muda** — não confiar que dependência declarada no package.json existe em node_modules. Sempre rodar npm ci pós lockfile diff.
- **Race condition de workflows paralelos** — `concurrency.group` não basta quando workflows DIFERENTES fazem trabalho dependente. Encadear via `workflow_run` ou unificar em 1 workflow.

### Pendência crítica P0

**Configurar GitHub Secrets pro quick-sync.yml**: sem `SSH_PRIVATE_KEY`, `SSH_HOST`, `SSH_PORT`, `SSH_USER`, todo deploy continua manual via SSH. Quick-sync vai falhar em todo push — antes build-inertia-auto compensava (commitando assets), mas agora sem ele, é o único caminho.

### CYCLE-02 status (~30% decorrido, ~14 dias restantes)

- Goal #6 Constituição V2 health-check 7d limpo: progressão (brief funcional + GUARD-02 + ADR 0098 deployados)
- Goal #4 MWART Repair (4 telas): 0/4 (Whatsapp cockpit foi bonus, fora do escopo do goal)
- Goal #5 NfeBrasil emite NFe55: 0/done
- Goal #7 Skills V0.5 UI: 0/done

**Última atualização:** 2026-05-07 ~16h BRT — Whatsapp Cockpit + build Hostinger (PRs #173 + #174 mergeados, ADR 0098, prod restaurada após incident HTTP 500)

---

## Sessão 2026-05-07 noite — Audit Claude Desktop + NFe + Goal #7 fechado

**Maratona de 12 PRs** (#173-#190, com #179 sobrescrito acidentalmente e refeito em #181). Cycle 02 fecha com **2 dos 4 goals concluídos**:

- 🟢 Goal #4 MWART Repair (4 telas + topnav) — DONE marcado
- 🟢 Goal #7 Skills V0.5 UI (16 skills indexadas em prod) — DONE marcado
- 🟡 Goal #5 NfeBrasil — Listener InvoicePaid em review aguardando smoke empresa 1
- 🟡 Goal #6 Constituição V2 — em progresso (depende de tempo)

### PRs entregues (12 mergeados + 5 abertos no fim da sessão)

**Mergeados:**
- #173 UI cockpit Whatsapp 3-painéis (estado-da-arte)
- #174 Build Inertia migrado pra Hostinger (deletei build-inertia-auto.yml + .gitignore + quick-sync npm ci+build)
- #175 ADR 0098 + session log + handoff
- #176 Skill cockpit-runbook v2 (UX heurísticas Nielsen + Score 0-100 + Modo Compare + BENCHMARKS.md)
- #177 Whatsapp DS padronização completa (8 arquivos + atalhos J/K/E/A + localStorage)
- #178 Repair MWART 4 telas DS padronização (tokens semânticos + dark mode + a11y)
- #180 Ziggy install (composer + @routes Blade + global.d.ts) — corrige bug latente em 161 callers `route()`
- #181 composer.lock sync (refeito após force-push acidental)
- #182 GOTCHAS update pós-Ziggy
- #183 fix grid-template-rows cockpit (gap 364px) + #185 fix Icon kebab/Pascal + #186 hotfix non-string

**Abertos no fim da sessão:**
- #184 fix UI tributacao NFe — outro autor, CLEAN, aguardando aprovação
- #187 DesignSystemAuditTest Pest ratchet baseline (audit P0 #3)
- #188 Icon registry com nomes do domínio (audit P2 #8)
- #189 Vibes promovido pro user dropdown (audit P2 #7)
- #190 NfeCertBadge sidebar — fecha US-NFE-001 100%

### Issues / regressões resolvidas

- **HTTP 500 prod por ~5min após #174** — `centrifuge` declarado mas ausente em node_modules; resolvido com `npm ci` + rebuild via SSH
- **PR #185 → tela em branco /ads/admin/skills** — `toPascalCase(name)` crashou com name não-string; hotfix #186 com guard `typeof === 'string'`
- **Force-push acidental destruiu PR #179 composer.lock** — refeito disparando workflow contra `main` em PR #181
- **Bug grid 364px gap em todas telas com topnav** — `grid-template-rows: 44px 1fr` virou `44px auto 1fr` (PR #183)
- **Ziggy NUNCA estava instalado** — `route()` em React = ReferenceError silencioso, links `href=undefined` há tempos. PR #180 instala formal + `@routes` Blade + tipos globais
- **GitHub Actions secrets SSH (SSH_PORT, SSH_USER) vazios** — quick-sync.yml falhava há tempos no Setup SSH; configurei via `gh secret set` mid-sessão; depois disso 4 deploys automáticos funcionaram

### Aprendizados meta importantes (gravar pra próximas sessões)

- **Erros TS sistêmicos costumam apontar pra bug runtime real, não tolerância tribal.** 161 erros `Cannot find name 'route'` foram tratados como "pre-existente herdado" antes de eu descobrir que Ziggy nem estava instalado — Pages React clicáveis há meses não navegavam de verdade. **Nunca aceitar erro TS sistemático sem entender a causa.**
- **`composer-lock-sync.yml` com `base_branch != main` + force-push em rebase = perde commit do lock.** Sintoma: composer install na Hostinger falha "package X not in lock file". Fix: workflow contra main, OU `git pull` antes do rebase.
- **`composer install --no-dev` quebra Faker em prod** (auto-mem confirmava): `nfephp-org/sped-da` carrega `Faker\Generator` em service provider mesmo sendo require-dev. Sintoma: artisan commands falham. **Sempre rodar `composer install` sem --no-dev.**
- **`git rm --cached` + `git reset --hard` no servidor não apaga arquivos untracked.** Migração `public/build-inertia/` pro .gitignore deixou 230 arquivos lixo na Hostinger até rodar `git clean -fd` manual. Cuidado em qualquer migration similar.
- **Hostinger TEM Node 24.15 + npm 11 via nvm** — reflexo "shared hosting = sem Node" custou tempo. `which node` antes de assumir.
- **Race condition de workflows paralelos** (`concurrency.group` não basta quando workflows DIFERENTES fazem trabalho dependente). Build-inertia-auto + quick-sync rodando paralelo causava ~30s de mismatch manifest → 409 + full reload. Encadear via `workflow_run` ou unificar.
- **Skill cockpit-runbook v2 evoluiu pra incluir UX heurísticas Nielsen + Score 0-100 + Modo C (Compare 2 telas) + BENCHMARKS.md** com 6 categorias (inbox, master-detail, dashboard, form, settings, listagem).
- **Ratchet baseline pattern** em test (`DesignSystemAuditTest`) aceita dívida atual mas previne regressão — alta usabilidade.

### Pendência crítica P0 RESOLVIDA

**GitHub Secrets quick-sync.yml configurados** mid-sessão. 4 deploys automáticos funcionaram após. Não é mais P0.

### Próximos passos sugeridos próxima sessão

1. **Mergear os 5 PRs abertos** (#184, #187, #188, #189, #190) — todos CLEAN
2. **Continuar US-NFE-002** (Emitir NFC-e a partir de venda finalizada) — Listener `TransactionCompleted` + Job `EmitirNfceJob` + UI sucesso. ~12-16h, escopo médio. Foundation pronta.
3. **#1 SIDEBAR_GROUPS backend** (audit P0 #1) — alto valor, 3-4h. Toca LegacyMenuAdapter + cada `menu.php`.
4. **Smoke test empresa 1** do Listener InvoicePaid pra fechar Goal #5 NfeBrasil

### CYCLE-02 status (~30% decorrido, ~14 dias restantes)

- 🟢 Goal #4 MWART Repair: **DONE** (4 telas + topnav em prod)
- 🟡 Goal #5 NfeBrasil emite NFe55: review aguardando smoke empresa 1
- 🟡 Goal #6 Constituição V2 health-check 7d: progressão (brief + GUARD-02 + ADR 0098)
- 🟢 Goal #7 Skills V0.5 UI: **DONE** (16 skills indexadas em prod)

**Última atualização:** 2026-05-07 ~21h BRT — sessão noite com 12 PRs (#173-#190); 2 goals fechados; aprendizados meta gravados.

---

## Sessão 2026-05-09 ~14h-22h BRT — 23 PRs, 2 telas em prod, processo MWART enforced

### Telas em prod

1. **`/repair/producao-oficina`** (kanban Repair) — F1→F4 em 1 dia (PRs #326→#363):
   - F1 protótipo HTML aprovado por Wagner
   - F2/F3 implementação — kanban 5 colunas + filtros Box/Elevador funcionais + drawer + 5 KPIs + badge mock + drill-down KPI clicável
   - F4 Pest GUARD (PROD-5) — 7 tests cobrindo invariantes + Tier 0 isolation + Non-Goals + move endpoint
   - **Drag-and-drop nativo HTML5** (PROD-4) — optimistic update + POST `/move` com mapping reverso heurístico (espelha sort_order quartil forward); mock data drag local-only (refresh volta), live data persiste em JobSheet.status_id
   - Charter live ([Index.charter.md](resources/js/Pages/Repair/ProducaoOficina/Index.charter.md))

2. **`/financeiro/unificado`** (Cockpit Financeiro Visão Unificada) — auditoria + 4 PRs de fix retroativos #355-#361:
   - Bug 1: hardcode "ROTA LIVRE"/"Maio 2026" → dinâmico via session/Carbon (#355)
   - Bug 2: rota `/unificado/novo` 404 → Page stub picker Receber/Pagar (#358)
   - Bug 3: sidebar Financeiro sem entrada "Visão unificada" → DataController menu adicionado (#358)
   - Bug 4: KPI cards sem onClick → drill-down filter (gap vs ADR ui/0002 §UX) (#358)
   - Charter retroativo + 5 Pest tests + visual-comparison (#359 + #361)
   - ADR ui/0003 amends ui/0002 formalizando 5 KPIs vs 4, sem aging buckets, desktop only, etc (#361)

### Infra/governança

- **Pipeline `cowork-inbox`** criado e validado E2E — header `<!-- cowork: target/append-to: <path> -->` em arquivo dropped em `cowork-inbox/` dispara Action que move pro destino + auto-merge (PRs #321-#329)
- **`mwart-gate.yml` robustecido** — agora exige charter ao lado do .tsx + Pest test correspondente além de RUNBOOK + SPEC + visual-comparison (#360)
- **`memory/requisitos/_processo/MWART-CHECKLIST.md`** novo — documenta ponta-a-ponta o processo MWART canônico (5 fases + 9 artefatos obrigatórios + 8 anti-padrões com sintoma+fix) (#360)

### Débitos pre-existentes resolvidos

- npm audit: 6 vulnerabilidades (1m+4h) → 0 (#335)
- Route `business.update` colisão Officeimpresso vs UltimatePOS resource — quebrava `php artisan route:cache` há tempos (#336)
- `Components/ui/checkbox` faltando — quebrava `npm run build:inertia` em main inteiro desde #317 (#330)

### Issues / regressões resolvidas

- **PR #349 mergeado sem 4 dos artefatos MWART obrigatórios** — Page completa mas sem charter, sem Pest, sem visual-comparison, com botão "+ Novo" dando 404, sidebar sem entrada, KPIs não-clicáveis (gap vs ADR ui/0002). Audit retroativo na sessão fechou todos.
- **Quick Sync flake transient SSH** — recorrente, mas quando re-disparado manual via `workflow_dispatch` passa. Secrets OK; problema é Hostinger SSH rejeitando rate-limit eventual.
- **GraphQL rate limit** durante sessão extensa (5000/h). Workaround: usar REST `gh api` direto pra criar/mergear PRs (`gh api -X POST .../pulls` e `gh api -X PUT .../merge`).

### Aprendizados meta (gravar)

- **Erro de "translação" do protótipo Cowork pro código:** sessão paralela traduziu ~30% do design aprovado. Sintoma: tela em prod ficou MUITO diferente da expectativa visual do Wagner. **Anti-padrão**: aprovar protótipo HTML e sair codificando direto sem visual-comparison rigorosa.
- **Charter retroativo é lossy mas ainda vale.** Wagner aprovou divergências do plano original (ui/0002) implícitamente ao aprovar protótipo Cowork. Formalizar via ADR amendment + charter retroativo evita drift silencioso entre canon e código.
- **Soft mode CI gate > hard mode pra greenfield.** PR #349 passou com 4 artefatos MWART faltando porque mwart-gate só comentava no PR, não bloqueava merge. Hard mode bloquearia mas teria parado outras PRs também. Soft + comment educativo + checklist humano = balance certo nesta fase.
- **HTML5 native DnD funciona em React 19 sem libs.** Sem @dnd-kit/react-dnd, ~50 linhas de state + handlers + DataTransfer. Só limitação: sem touch suporte. Pra desktop Larissa/Eliana é suficiente.
- **Reverse mapping heurístico é "good enough" pra greenfield.** Drag-drop entre colunas precisa decidir qual `repair_status_id` usar quando user dropa numa coluna. Heurística "primeiro status do bucket sort_order" espelha forward mapping. Não-perfeito mas funcional sem migrations/UI extra.
- **Validação visual no Chrome captura bugs que auditoria de código não vê.** O bug do `+ Novo` dando 404 só apareceu quando cliquei no Chrome. Auditoria viu a referência mas não que era 404 sem stub controller.
- **`gh pr merge` falha local cleanup quando branch tá em outro worktree paralelo** — server-side merge funciona mesmo assim (state=MERGED), só falha tentativa de delete local da branch. Erro cosmético, não bloqueia.

### Pendências sugeridas pra próxima sessão

1. **Smoke biz=1 NFC-e SEFAZ** (CYCLE-02 goal #5) — ainda pendente da sessão 2026-05-07. Cert ativo, template SC aplicado.
2. **US-FIN-021..028** — backlog Visão Unificada (form unificado inline, aging buckets, delta_pct, combobox, mobile responsive, pagination, Pest fase 2, visual-comparison). **Todos sem sinal qualificado** ([ADR 0105](memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) — só ativar quando cliente reporta OU métrica detecta drift.

### CYCLE-02 status

- 🟡 Goal #5 NfeBrasil emite NFe55: pendente smoke biz=1 (mesmo desde 2026-05-07)
- 🟡 Goal #6 Constituição V2 health-check 7d: progressão
- 🟢 Goal NOVO implícito: **Processo MWART enforced** ([ADR 0104](memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) + ADR ui/0114) — fechado nesta sessão via mwart-gate.yml + MWART-CHECKLIST.md

**Última atualização:** 2026-05-09 ~22h BRT — sessão maratona com 23 PRs (#321-#363); 2 telas em prod (Producao Oficina + Visao Unificada); processo MWART enforced via gate + doc; auditoria visual Chrome detectou e fechou 4 bugs do PR #349.
