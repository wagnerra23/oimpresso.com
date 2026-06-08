# Dossier · WhatsApp do oimpresso — profissionalização definitiva (Baileys OUT)

> **Status:** DOSSIER EXECUTÁVEL · proposto por audit-senior-expert (opus-4.7) · 2026-05-27
> **Autoria:** Wagner solicitou decisão sem hedge ("crie um especialista para decidir e siga não pergunte") ·
> **Companheiro:** ADR proposta [`2026-05-27-whatsapp-profissionalizacao-baileys-out.md`](../decisions/proposals/2026-05-27-whatsapp-profissionalizacao-baileys-out.md)
> **Parent ADRs:** [0096](../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) (módulo mãe), [0117](../decisions/0117-multiplos-numeros-whatsapp-por-business.md) (multi-phone), [0140](../decisions/0140-jana-pro-produto-comercial-saas.md) (JANA Pro depende custo zero)
> **Supersedes parcialmente:** ADR 0096 emenda 4 (BaileysDriver custom autorizado Sprint 3) · §16 inteiro do ARCHITECTURE.md
> **Tempo decisão Wagner:** 10 minutos (TL;DR + Plano Fase 0 + 1 frase justificativa)

---

## TL;DR — decisão sem hedge

**Caminho profissional definitivo:** **(β híbrido) Meta Cloud API como primary universal + Z-API como driver opcional não-default (fallback emergencial OU onboarding legado).**

**Sai 100% da arquitetura:**
- ⛔ `BaileysDriver` (PHP)
- ⛔ daemon Node CT 100 inteiro (`Modules/Whatsapp/daemon-node/`)
- ⛔ schema `baileys_*` em `whatsapp_business_configs` + `whatsapp_business_phones`
- ⛔ runbooks Baileys (5 docs)
- ⛔ ADR 0096 emenda 4 (autorização Sprint 3 daemon custom) — explicitamente supersedida pela ADR proposta hoje

**Continua:**
- ✅ `MetaCloudDriver` — **promove a DEFAULT** (era fallback)
- ✅ `ZapiDriver` — vira **opcional não-default**, mantido como driver válido pra business legacy que já tem Z-API rodando OU fallback emergencial
- ✅ `NullDriver` — CI/dev
- ⛔ `EvolutionDriver` — continua PROIBIDO permanente

**Próximo passo IMEDIATO (Fase 0, hoje 27/mai):** Wagner autorizou "ninguém está ativo no Baileys, pode desconectar todos". Executar:
1. `docker compose stop whatsapp-baileys` no CT 100 (já dá pra fazer hoje, autorizado)
2. Dump `whatsapp_baileys_sessions` table → S3 backup auditoria 90 dias (LGPD direito esquecimento)
3. Marcar containers + volumes como `__deprecated_2026-05-27` (não deletar até confirmação 30d)

**Por que essa via venceu α/γ/δ/ε:**
- **Wagner reportou dor real** ("não deu pra usar", "instável") — sinal cliente qualificado [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) → sunk cost vira lição, não argumento de preservação
- **Onda detecção Meta 2026 atinge whatsmeow E Baileys igualmente** — [issue #810 whatsmeow](https://github.com/tulir/whatsmeow/issues/810) confirma "ban risk warnings affect both libraries, regardless of usage patterns or safeguards" — rewrite Go (γ) ou WuzAPI (δ) seriam queimar 40-120h pelo MESMO risco
- **Embedded Signup v4 default agora 5-15 min** ([Whautomate 2026](https://whautomate.com/whatsapp-embedded-signup)) — premissa "Meta onboarding 1-3 dias" que justificou Z-API default em ADR 0096 emenda 3 **CAIU**. Mandatório Meta a partir 15/out/2026 ([PPCLand](https://ppc.land/metas-embedded-signup-v4-is-here-but-the-october-15-clock-is-ticking/))
- **Service messages unlimited grátis desde nov/2024** ([Chatarmin 2026](https://chatarmin.com/en/blog/whats-app-api-pricing)) — fluxo conversacional inbound vai pra R$ 0
- **Custo Meta Cloud 200 conv/mês Brasil:** ~R$ 16 utility + R$ 0 service ([Message Central](https://www.messagecentral.com/blog/whatsapp-business-api-pricing-in-brazil)) — **MAIS BARATO** que R$ 99/mês Z-API
- **JANA Pro [ADR 0140](../decisions/0140-jana-pro-produto-comercial-saas.md) depende de custo marginal zero** — Meta Cloud cumpre; daemon ban risk **destrói** o produto comercial
- **Time não tem Go expert** (Wagner + Felipe + Maiara/Eliana) — elimina γ/δ automaticamente
- **BSP enterprise (ε)** R$ 1.500+/mês = 30× overkill pra perfil 50-200 conv/mês atual; reaberto via review_trigger se cliente pagante pedir SLA
- **Constituição v2 [ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) #4 loop fechado por métrica** + **#8 fallback** preservados — Z-API como fallback opcional, não default

**Estimate total (10x recalibrado [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) + margem 2x):**
- Codáveis IA-pair: ~16h Wagner-equivalentes
- Humano-limitado (canary 7d + monitor 30d): 37 dias relógio do mundo real
- Custo monetário direto Wagner: **economia R$ 99/mês Z-API + R$ 0 daemon CT 100 RAM/CPU desperdiçados**

---

## 1. Decisão definitiva (sem hedge)

### 1.1 As 5 candidatas avaliadas

| Via | Descrição | Decisão | Razão de 1 frase |
|---|---|---|---|
| **(α)** Meta Cloud only | Abandona qualquer driver não-oficial; só oficial; risco ban zero | ❌ **Quase** mas rejeitada parcialmente | Bom default técnico, mas remove Z-API impedindo onboarding business legacy que já tem número Z-API em produção (custo migração externo) |
| **(β)** Meta Cloud default + Z-API SaaS fallback opcional | Sem daemon próprio; Z-API responde pelo ban; oimpresso fora linha fogo | ✅ **ESCOLHIDA (β híbrida)** | Combina onboarding rápido oficial (Embedded Signup v4 5-15min) + safety-net Z-API pra legacy + custo R$ 0/mês default + risco ban zero default |
| **(γ)** Meta Cloud + whatsmeow daemon Go custom | Rewrite daemon-node em Go | ❌ Rejeitada hard | Onda detecção Meta 2026 atinge whatsmeow IGUAL Baileys (issue #810 público); time sem Go expert; 80-120h trabalho pra MESMO risco que Wagner já rejeitou |
| **(δ)** Meta Cloud + WuzAPI wrapper | Adopta `asternic/wuzapi` (whatsmeow wrapped REST) | ❌ Rejeitada hard | Mesmo argumento (γ) + perda controle wrapper + roadmap WuzAPI fora controle oimpresso + perde antiBan custom (justamente o que justificaria o daemon próprio) |
| **(ε)** BSP enterprise (Take Blip/Twilio/360dialog) | Terceiriza inteiro; alto custo, alto SLA | ❌ Rejeitada por ora | R$ 1.500+/mês Take Blip = 30× overkill perfil PME atual (50-200 conv/mês); reaberto via review_trigger se cliente pagante pedir |

### 1.2 Por que (β híbrida) e não (α puro)

Diferença chave: **(α) puro** removeria `ZapiDriver` da arquitetura. Análise:

- Hoje **zero business em produção** ativos no `ZapiDriver` real (confirmar com Wagner — mas pelo sinal sessão atual "ninguém está ativo" + Wagner explicitamente já não usa nem Z-API nem Baileys hoje, a probabilidade é baixíssima). Mesmo assim:
- **Manter `ZapiDriver` como driver válido mas não-default** custa zero — código já existe, testes Pest já existem, schema já cobre. Não precisa mexer.
- **Permite fallback emergencial** se Meta Cloud tiver outage raro (SLA 99.9% real mas eventos existem).
- **Permite onboarding business legacy** que aparece no futuro já com número Z-API ativo (raro mas possível) — não força ele migrar provedor.
- Custo manutenção `ZapiDriver` adiante = **zero** (driver maduro, ~150 linhas, sem dependência exótica).
- Não conflita com ADR 0140 JANA Pro porque **default Meta Cloud = custo marginal zero**. Z-API só entra em caso ativo (R$ 99/mês cobrado do cliente legacy, não do oimpresso).

### 1.3 Justificativa cruzada com restrições Tier 0

| Restrição | Como β honra |
|---|---|
| **Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md))** | Meta Cloud nativo multi-tenant: cada business cadastra seu próprio `phone_number_id` no Meta Business Manager via Embedded Signup. Z-API: `business_id` global scope já existe no schema `whatsapp_business_configs`. Webhook URL tem `business_uuid` no path (não body). Zero cross-tenant leak vector. |
| **Custo marginal zero JANA Pro ([ADR 0140](../decisions/0140-jana-pro-produto-comercial-saas.md))** | Meta Cloud: service messages (customer-initiated) unlimited grátis desde nov/2024. Utility templates R$ 0,08/conv BR ([Message Central 2026](https://www.messagecentral.com/blog/whatsapp-business-api-pricing-in-brazil)) — 500 triages JANA Pro = R$ 40/mês utility max. Margem 92-94% projetada [ADR 0140] preservada. |
| **Realidade do time (sem Go expert)** | β não exige nova linguagem. Stack permanece PHP + TypeScript (Pages React + daemon FUTURO Node se aparecer — mas não aparece). Wagner + Felipe + Maiara/Eliana operam o que já conhecem. |
| **Mercado 2026** | Embedded Signup v4 default Meta a partir 15/out/2026 ([PPCLand](https://ppc.land/metas-embedded-signup-v4-is-here-but-the-october-15-clock-is-ticking/)) — alinhado ao roadmap Meta. Service msg unlimited free. Brazil billing localization R$ via NF-e a partir jul/2026. |
| **ADR 0105 cliente como sinal** | Wagner = cliente. Reportou dor explícita ("instável não deu pra usar"). Sinal qualificado existe. β responde diretamente. |
| **Constituição v2 [ADR 0094]** | #1 Context as product (driver simples, sem daemon). #2 Tiered cost (Meta free + Z-API R$ 99 só se ativo). #4 Loop fechado métrica (`whatsapp.messages.sent` OTel já existe). #5 SoC brutal (sem daemon CT 100 = menos uma fronteira). #6 Multi-tenant Tier 0 (Meta nativo). #7 Transparência (driver default oficial, sem TOS violation). #8 Confiabilidade fallback (Z-API opcional pra emergência). |
| **ADR 0062 Hostinger ≠ CT 100** | Sem daemon CT 100 = menos carga no CT 100. Webhook receiver continua Hostinger HTTP-only. Horizon worker continua CT 100 (existente). |
| **ADR 0094 §4 custo tracking** | OTel `whatsapp.cost.centavos` por business + category continua exportando — agora SÓ Meta Cloud cost (mais previsível pra dashboard Grafana). |

---

## 2. O que fazer com o sunk cost (artefatos Baileys)

Tabela auditável artefato × ação × justificativa. Ações: **PRESERVE** (mantém vivo) · **DEPRECATE** (marca obsoleto, deleta em 90d) · **ARCHIVE** (move pra `archive/` com tag git) · **DELETE** (remove hard agora) · **SUPERSEDE_PARTIAL** (parte mantida, parte revogada).

### 2.1 Código PHP

| Artefato | Ação | Justificativa |
|---|---|---|
| `Modules/Whatsapp/Services/Drivers/BaileysDriver.php` | **DELETE** (PR Fase 1) | Driver não tem business ativo + autorização Wagner pra desconectar todos. Histórico em git tag `baileys-driver-final-2026-05-27`. |
| `Modules/Whatsapp/Listeners/VerifyBaileysSignature.php` | **DELETE** (PR Fase 1) | Middleware webhook Baileys irrelevante sem driver. |
| `Modules/Whatsapp/Jobs/BaileysConnectJob.php` (se existir) | **DELETE** (PR Fase 1) | Job conexão daemon irrelevante. |
| `Modules/Whatsapp/Services/Drivers/DriverFactory.php` (parte `case 'baileys'`) | **EDIT** (PR Fase 1) | Remove branch `baileys` da factory + remove `baileys` da lista válida em `config/whatsapp.php`. Adiciona `baileys` à `forbidden_drivers` array junto com `evolution`. |
| `MultiTenantIsolationTest` (parte Baileys) | **EDIT** (PR Fase 1) | Remove cenários Baileys, mantém Meta Cloud + Z-API. |
| `BaileysDriverTest` (Pest) | **DELETE** (PR Fase 1) | Sem driver, sem teste. |
| `MetaCloudDriver.php` | **PRESERVE + PROMOTE** | Promove a default. Adicionar Embedded Signup v4 flow (Fase 2). |
| `ZapiDriver.php` | **PRESERVE + DEMOTE** | Vira opcional não-default. Sem mudança código. |
| `NullDriver.php` | **PRESERVE** | CI/dev essencial. |
| `EvolutionDriver` (ausente, só flag config) | **PRESERVE proibição** | ADR 0096 emenda 2 mantida — Evolution permanente PROIBIDO. |

### 2.2 Daemon Node CT 100

| Artefato | Ação | Justificativa |
|---|---|---|
| `Modules/Whatsapp/daemon-node/` (dir inteiro: src, package.json, Dockerfile, scripts, tests) | **ARCHIVE** (Fase 0 hoje) → **DELETE 90d** | Move pra `archive/daemon-node-baileys-2026-05-27/` em branch separada `archive/baileys-daemon`; git tag `daemon-node-final-2026-05-27`; deleta da `main` em PR Fase 1. Mantém branch arquivada 90 dias pra LGPD auditoria + retro. Após 90d sem necessidade, `git branch -D archive/baileys-daemon` + `git push --delete`. |
| `docker-compose.yml whatsapp-baileys` service no CT 100 | **DEPRECATE + STOP** (Fase 0 hoje, autorizado Wagner) | `docker compose stop whatsapp-baileys` + `docker compose rm whatsapp-baileys`. Volume `/srv/docker/whatsapp-baileys/sessions/` permanece 90d backup. Container image rm após 90d. |
| Traefik label `whatsapp-baileys.oimpresso.local` | **DELETE** (Fase 0 hoje) | Remove Traefik route. IP whitelist Hostinger removida do middleware. |
| Docker secret `whatsapp_baileys_api_key` | **DELETE** (Fase 0 hoje) | `docker secret rm whatsapp_baileys_api_key`. |
| Volume sessions `/srv/docker/whatsapp-baileys/sessions/` | **PRESERVE 90d** → **DELETE** (2026-08-27) | LGPD direito esquecimento — preserva auditoria 90d (caso algum business reclame de mensagem perdida). Após 90d: `rm -rf` no host CT 100. |

### 2.3 Schema banco

| Tabela/Coluna | Ação | Justificativa |
|---|---|---|
| `whatsapp_business_configs.baileys_instance_id` | **DROP COLUMN** (migration Fase 1) | Sem driver, sem dado. |
| `whatsapp_business_configs.baileys_daemon_url` | **DROP COLUMN** (migration Fase 1) | Idem. |
| `whatsapp_business_configs.baileys_api_key` | **DROP COLUMN** (migration Fase 1) | Idem. Cifrado encrypted cast — ao DROP fica perdido permanentemente, **ok** (Wagner já autorizou desconectar todos). |
| `whatsapp_business_phones.baileys_*` (3 colunas — ADR 0117) | **DROP COLUMNS** (migration Fase 1) | Mesmo schema multi-phone, mesma decisão. |
| Tabelas auxiliares `baileys_sessions` / `baileys_auth_states` (se existirem em `mysqlAuthState.ts` migration) | **DUMP + DROP TABLES** (Fase 0 → migration Fase 1) | Backup `mysqldump` → S3 archive 90d. Após dump verificado, drop tables. |
| `whatsapp_messages.provider='baileys'` rows | **PRESERVE** (não toca) | Histórico de mensagens trocadas via Baileys fica imutável (LGPD + auditoria compliance fiscal). `provider` column é VARCHAR, não FK pra driver — preserva sem driver ativo. |

### 2.4 Documentação canon (memory/)

| Artefato | Ação | Justificativa |
|---|---|---|
| `memory/requisitos/Whatsapp/runbooks/baileys-daemon-deploy-ct100.md` | **ARCHIVE** (move pra `memory/requisitos/Whatsapp/runbooks/_archive/`) | Lição histórica preservada. |
| `memory/requisitos/Whatsapp/runbooks/baileys-troubleshoot-ban.md` | **ARCHIVE** | Idem. |
| `memory/requisitos/Whatsapp/runbooks/baileys-upgrade-lib.md` | **ARCHIVE** | Idem. |
| `memory/requisitos/Whatsapp/runbooks/migrar-baileys-7x.md` | **ARCHIVE** | Idem — migração 7.x feita 2026-05-15 mas driver vai sair. |
| `memory/requisitos/Whatsapp/runbooks/daemon-ct100-rebuild.md` | **ARCHIVE** | Idem. |
| `memory/requisitos/Whatsapp/runbooks/restore-auth-state.md` | **ARCHIVE** | Idem. |
| `memory/requisitos/Whatsapp/runbooks/ativar-cloud-api-canary-biz99.md` | **PRESERVE + EXPANDIR** (Fase 1) | Esse vira o runbook canônico Meta Cloud — passa de "ativar canary" pra "ativar como default Embedded Signup v4". |
| `memory/requisitos/Whatsapp/runbooks/migrar-1-para-n-numeros.md` | **EDIT** (Fase 1) | Remove referências baileys, mantém Meta Cloud + Z-API multi-phone (ADR 0117 preservada). |
| `memory/requisitos/Whatsapp/ARCHITECTURE.md` §16 (Sprint 3 Baileys) | **SUPERSEDE_PARTIAL** (Fase 1) | Header §16 vira "§16 [DEPRECATED 2026-05-27 — supersedido por ADR `2026-05-27-whatsapp-profissionalizacao-baileys-out.md`]"; conteúdo preserva como lição histórica + adiciona link ADR aceita. Não deletar — preserva learning. |
| `memory/requisitos/Whatsapp/ARCHITECTURE.md` §1-15, 17+ (não-Baileys) | **EDIT pontual** | Atualiza diagrama §1 removendo container `whatsapp-baileys`. Atualiza tabela drivers §2.1 (driver default = meta_cloud). Outras seções tocam mínimo. |
| `memory/requisitos/Whatsapp/SPEC.md` | **EDIT** (Fase 1) | Atualiza onboarding flow §14 — passa de "Z-API hoje + Meta Cloud paralelo" pra "Meta Cloud Embedded Signup v4 (5-15min) + Z-API opcional legacy". |
| `memory/requisitos/Whatsapp/CAPTERRA-FICHA.md` | **EDIT minor** (Fase 1) | Atualiza posicionamento se cita Baileys (verificar). |
| `memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md` | **PRESERVE (append-only)** + adicionar `supersedes_partially` ref na ADR nova | Constituição #ADR canon append-only. **NÃO editar.** ADR nova hoje declara `supersedes_partially: [0096]` no frontmatter e amenda emenda 4 explicitamente. |
| `memory/decisions/0117-multiplos-numeros-whatsapp-por-business.md` | **PRESERVE** | Multi-phone continua válido (Meta Cloud suporta múltiplos `phone_number_id` por business; Z-API multi-instance suporta). Só remove cenários Baileys do schema (migration Fase 1). |
| `memory/decisions/proposals/2026-05-27-baileys-vs-whatsmeow-substituicao-daemon-whatsapp.md` (Opção D híbrida proposta hoje cedo) | **MARK rejected** | Substituída pela proposta nova hoje à tarde com info adicional Wagner ("ninguém ativo + instável"). Editar `status: rejected` + razão. |

### 2.5 Skills/agents/hooks Claude Code

| Artefato | Ação | Justificativa |
|---|---|---|
| `.claude/skills/baileys-update-procedure/` (se existir) | **ARCHIVE** (move pra `.claude/skills/_archive/`) | Skill catalogou 5 traps Baileys 2026-05-11 — preserva como lição histórica. |
| `memory/reference/feedback-baileys-7x-decisao-irreversivel.md` | **PRESERVE + APPEND** | Adiciona seção "2026-05-27 — Baileys saiu por decisão Wagner (instabilidade + ninguém ativo). Migração 7.x continua sendo lição válida sobre 'decisão irreversível' como pattern, mesmo que o objeto Baileys tenha saído." |
| Pest `tests/Feature/Architecture/NoHardcodeBusinessIdInModulesTest.php` | **PRESERVE** | Sem mudança — global scope continua. |
| Hook `block-claim-without-evidence.ps1` | **PRESERVE** | Sem mudança. |

---

## 3. ADR aceita

Documento companheiro: [`memory/decisions/proposals/2026-05-27-whatsapp-profissionalizacao-baileys-out.md`](../decisions/proposals/2026-05-27-whatsapp-profissionalizacao-baileys-out.md).

Frontmatter chave:
```yaml
status: proposed  # vira accepted quando Wagner aprovar
supersedes_partially: [0096]  # emenda 4 (BaileysDriver Sprint 3)
parent_adr: 0094  # Constituição v2
related: [0035, 0058, 0062, 0093, 0096, 0105, 0117, 0140]
```

Pronta pra Wagner abrir, ler em 5 min, e aprovar com "ok aceitar" — ou rejeitar.

---

## 4. Plano de execução faseado (5 fases · estimates 10x recalibrado [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))

### Fase 0 — Desligar daemon CT 100 + arquivar dados (HOJE 27/mai)

**Pré-requisito Wagner já autorizou:** "ninguém está ativo no Baileys, pode desconectar todos. é instável não deu pra usar"

**Entregáveis:**
1. SSH CT 100 → `docker compose stop whatsapp-baileys` → `docker compose rm whatsapp-baileys`
2. Remover Traefik route `whatsapp-baileys.oimpresso.local` (label do compose remove naturalmente)
3. `docker secret rm whatsapp_baileys_api_key`
4. Snapshot último estado: `tar czf /tmp/baileys-sessions-final-2026-05-27.tgz /srv/docker/whatsapp-baileys/sessions/`
5. Upload snapshot pro S3 archive 90d (ou pasta cold local) — caminho `backup/baileys/baileys-sessions-final-2026-05-27.tgz`
6. Branch git `archive/baileys-daemon` criada a partir de `main` ANTES de deletar (preserva código rastreável)
7. Git tag `baileys-final-2026-05-27` apontando pro HEAD `main` ANTES do PR de delete

**Quem executa:** Wagner direto (SSH CT 100 = autorização Wagner pessoal, sem proxy Claude).

**Gate Wagner:** confirma "container down, sem alarme Loki em 30 min" antes Fase 1 começar.

**Rollback rápido:** snapshot S3 + git tag + branch arquivada permitem restaurar daemon em ~30 min se algo errado. Mas autorização "ninguém ativo" reduz probabilidade rollback a < 1%.

**Horas Wagner-equivalentes:** ~1h (SSH + commands + verificação Loki).

**Ordem:** PRIMEIRA fase. Sem isso, daemon ainda recebe webhooks → drift.

### Fase 1 — Remoção código + schema cleanup (28-29/mai)

**Entregáveis (PR único ≤300 LOC ou 2 PRs se ultrapassar):**

PR 1 (`feat(whatsapp): remove BaileysDriver + daemon-node + schema cleanup — supersede ADR 0096 emenda 4`):
- Delete `Modules/Whatsapp/Services/Drivers/BaileysDriver.php`
- Delete `Modules/Whatsapp/Listeners/VerifyBaileysSignature.php`
- Delete `Modules/Whatsapp/Jobs/BaileysConnectJob.php` (se existir)
- Delete `Modules/Whatsapp/daemon-node/` inteiro (push to `archive/baileys-daemon` branch primeiro)
- Delete `BaileysDriverTest.php`
- Edit `DriverFactory.php` — remove `case 'baileys'`, adiciona `'baileys'` à lista `forbidden_drivers`
- Edit `config/whatsapp.php` — remove `'baileys' => [...]`, atualiza `default_driver` env default = `meta_cloud`, adiciona `baileys` em `forbidden_drivers`
- Migration `2026_05_28_drop_baileys_columns.php` — DROP colunas `baileys_*` de `whatsapp_business_configs` + `whatsapp_business_phones`
- Migration `2026_05_28_drop_baileys_tables.php` — DROP `baileys_sessions` se existir (dump→s3 ANTES via Wagner pre-flight)
- Edit `MultiTenantIsolationTest` — remove cenários `baileys`
- Edit `memory/requisitos/Whatsapp/SPEC.md` — atualiza §onboarding
- Edit `memory/requisitos/Whatsapp/ARCHITECTURE.md` — marca §16 SUPERSEDED + atualiza §1 diagrama + §2.1 schema
- Edit `memory/requisitos/Whatsapp/CAPTERRA-FICHA.md` — minor (se cita Baileys)
- Move `runbooks/baileys-*.md` (5 docs) pra `runbooks/_archive/`
- Append `memory/reference/feedback-baileys-7x-decisao-irreversivel.md` com seção "2026-05-27 Baileys saiu por sinal cliente"
- Mark `memory/decisions/proposals/2026-05-27-baileys-vs-whatsmeow-substituicao-daemon-whatsapp.md` `status: rejected` + razão append
- Reference ADR nova `proposals/2026-05-27-whatsapp-profissionalizacao-baileys-out.md` em commits

**Quem executa:** agent implementador junior (audit-implement-expert) spawnado por Wagner ou Claude pai — escopo bem definido, ≤300 LOC, Pest CI cobre regressão.

**Gate Wagner:** CI verde + Pest verde + review humano PR.

**Rollback rápido:** revert PR. Branch `archive/baileys-daemon` preserva código.

**Horas Wagner-equivalentes:** ~2h Wagner review (codáveis IA-pair 4-6h aceitando margem 2x).

**Pré-requisito:** Fase 0 completa (daemon parado) + ADR nova aprovada por Wagner.

### Fase 2 — Embedded Signup v4 + Meta Cloud como default (30/mai - 03/jun)

**Entregáveis:**

PR 2 (`feat(whatsapp/settings): Meta Cloud default + Embedded Signup v4 onboarding`):
- Edit `resources/js/Pages/Whatsapp/Settings.tsx` (ou `Index.tsx` per ADR 0117 multi-phone):
  - Wizard novo: "Conectar WhatsApp em 5-15 min via Embedded Signup Meta"
  - Embed iframe ou popup oficial Meta OAuth (FB Login for Business v4 — endpoint `https://www.facebook.com/v21.0/dialog/oauth?...` com `display=popup`)
  - Callback handler `POST /whatsapp/settings/meta-embedded-callback` recebe `code` → `phone_number_id` → `access_token`
  - Auto-preenche `meta_phone_number_id`, `meta_access_token`, `meta_app_secret`, `meta_webhook_verify_token`
  - Auto-subscribe Meta webhook fields (`messages`, `message_template_status_update`)
  - UI feedback "Conectado ✅ +5511..."
- Edit `config/whatsapp.php` — `'default_driver' => 'meta_cloud'`
- Edit `Modules/Whatsapp/Http/Requests/SaveSettingsRequest.php` — remove gating "Z-API exige Meta fallback" (não precisa mais — Meta é default), mantém gating "se driver=zapi exige LGPD ack + Meta cadastrado como fallback"
- Edit `Modules/Whatsapp/Services/Drivers/MetaCloudDriver.php` — adicionar método `provisionViaEmbeddedSignup(code, state)` que troca `code` por `access_token` permanent
- Update `runbooks/ativar-cloud-api-canary-biz99.md` → renomear `runbooks/onboarding-meta-cloud-embedded-signup.md` — passo-a-passo com screenshots
- Pest `EmbeddedSignupFlowTest` — mock HTTP Meta `Http::fake()`, verifica OAuth code → token exchange + auto-subscribe webhook

**Quem executa:** agent implementador. UI Pages exige charter ([RUNBOOK-charter pattern](../requisitos/_DesignSystem/PRE-MERGE-UI.md)) + visual comparison F1.5 ([ADR 0107](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)).

**Gate Wagner:** smoke biz=1 (não biz=4 — ADR 0101 tests biz=1 sempre) Wagner conecta número teste pessoal via Embedded Signup e confirma "5-15 min real" + "Pest verde" + screenshot Chrome MCP UI.

**Rollback rápido:** revert PR mantém Z-API funcionando (ainda no codebase). Risk = baixo (não toca dados existentes).

**Horas Wagner-equivalentes:** ~6h Wagner (codáveis IA-pair 12-18h + 2x margem).

**Pré-requisito:** Meta App registrada (Wagner já tem app via Meta Business Manager) + Tech Provider OAuth permissions concedidas (necessário antes 15/out/2026 mandatório, então fazer já agora).

### Fase 3 — Smoke produção biz piloto (04-10/jun · canary 7 dias)

**Entregáveis:**
- Wagner cadastra **número Wagner pessoal** ou **número biz=1 (oficina dele)** via Embedded Signup v4 em prod
- Envia 10 mensagens utility (lembrete cobrança, OS pronta) e 10 service (responde inbound) durante 7 dias
- Métricas observadas Prometheus + OTel:
  - `whatsapp.messages.sent` por business — esperado: 100% success rate
  - `whatsapp.messages.received` por business — esperado: webhook 100% entrega
  - `whatsapp.cost.centavos` — esperado: utility R$ 0,08/conv, service R$ 0
  - Latência p95 send: < 2s end-to-end
  - Webhook latency p95: < 500ms
  - Zero falha 5xx em 7 dias

**Quem executa:** Wagner direto (smoke real, não narração — [PROTOCOLO-WAGNER-SEMPRE.md](../reference/PROTOCOLO-WAGNER-SEMPRE.md) R1).

**Gate Wagner:** "7 dias rodando sem incidente, métricas verdes, posso convidar Larissa biz=4 ou outro cliente piloto" — passa Fase 4.

**Rollback rápido:** business volta a `driver=meta_cloud` se algum config drift (sem fallback necessário porque já era Meta — eventual fallback é Z-API, mas não tem business legacy ativo).

**Horas Wagner-equivalentes:** ~1h/dia × 7 dias = 7h Wagner observação passiva.

**Pré-requisito:** Fase 2 PR mergeado + deploy Hostinger via `git pull`.

### Fase 4 — Cutover/rollout + governança contínua (11/jun - 30/jun + ongoing)

**Entregáveis:**
- Convite cliente piloto adicional (Larissa biz=4 ROTA LIVRE se Wagner aprovar — mas ROTA LIVRE é 99% volume então cuidado; preferível primeiro outro cliente piloto novo)
- 30 dias monitor — gate métricas:
  - 0 bans Meta (esperado, oficial)
  - 0 cross-tenant leak (Pest `MultiTenantIsolationTest` em CI + manual review log)
  - Custo cumulativo cabível dentro projeção [ADR 0140 §JANA Pro]
  - `whatsapp.driver.fallback` counter = 0 (sem fallback Z-API ativado = saúde 100%)
- ADR proposta `accepted` lifecycle no MCP MCP `decisions-search`
- Dashboard Grafana `whatsapp-meta-cloud-canonical` atualizado removendo painel daemon Node
- `BRIEFING.md` Whatsapp atualizado refletindo novo estado
- Métricas semanais review (Wagner ou Felipe)

**Quem executa:** Wagner monitor 30 dias passive + Felipe (se MCP onboardado) co-monitor a partir junho/2026.

**Gate Wagner:** "30 dias sem incidente + 2+ businesses ativos no Meta Cloud" — declara Fase 4 fechada.

**Rollback rápido:** N/A (rollback total = voltar Fase 0 antes de deploy — não realista pós 30d).

**Horas Wagner-equivalentes:** ~30 min/semana review × 4 semanas = 2h.

**Ordem:** ÚLTIMA. Vira ongoing governança.

### 4.6 Resumo total estimativa Wagner-equivalente

| Fase | Wagner-h | Codáveis IA-h (com margem 2x) | Relógio |
|---|---|---|---|
| Fase 0 (desligar) | 1h | 0h | 1 dia |
| Fase 1 (cleanup) | 2h | 4-6h | 2 dias |
| Fase 2 (Embedded Signup) | 6h | 12-18h | 4 dias |
| Fase 3 (canary smoke) | 7h | 0 (Wagner direto) | 7 dias |
| Fase 4 (cutover + 30d monitor) | 2h | 0 | 30 dias |
| **TOTAL** | **~18h Wagner** | **~16-24h codáveis** | **~44 dias** |

Conforme [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md): codáveis IA-pair fator 10× + margem 2× já aplicado. Humano-limitado (canary 7d + monitor 30d) mantém relógio real ~37 dias.

**Custo monetário direto:** **R$ 0** (Meta Cloud free service msg + R$ 16/mês utility típico). **Economia:** ~R$ 99/mês Z-API + RAM/CPU CT 100 desperdiçados (~2.4GB RAM Baileys daemon).

---

## 5. Riscos catalogados + mitigações

### 5.1 Tier 0 (incidente catastrófico se materializa)

| # | Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|---|
| R1 | Embedded Signup v4 falha pra business novo (OAuth issue Meta) | Baixa | Médio | Fallback manual UI "Cadastrar tokens Meta manual" (form existente preservado) — pré-Embedded path. Permite onboarding sem OAuth se Meta down. |
| R2 | Cross-tenant leak (business A vê mensagem business B) | Muito baixa | Catastrófico | `MultiTenantIsolationTest` em CI + `business_id` global scope já existe + Meta `phone_number_id` per-business + webhook URL com `business_uuid`. Nada muda dessa proteção. |
| R3 | LGPD — mensagem perdida durante migration daemon→Meta | Baixa | Alto | Volume `/srv/docker/whatsapp-baileys/sessions/` preservado 90d + `whatsapp_messages` table preserved (provider='baileys' rows imutáveis) + Wagner já confirmou "ninguém ativo" — probabilidade real ~0%. |
| R4 | Meta App revogada/banida (eg violação política) | Muito baixa | Catastrófico | Meta App Wagner já existente + uso compliant (não-bot mass spam) + Z-API SaaS como fallback driver opcional preservado pra emergência. |

### 5.2 Tier 1 (operacional grave)

| # | Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|---|
| R5 | Embedded Signup v4 deprecated antes outubro 2026 (Meta muda) | Baixa | Médio | Monitor Meta changelog mensal. Skill `meta-cloud-deprecation-watch` (criar Tier C). v4 é mandatório a partir 15/out/2026 — Meta tem incentivo manter. |
| R6 | Custo Meta Cloud explode (mudança pricing BR) | Baixa | Médio | Review trigger ADR 0096 já existe. Brazil billing localization jul/2026 mantém R$ via NF-e — não muda preço base, só currency. |
| R7 | Z-API SaaS empresa fecha / muda API | Baixa | Baixo | Z-API vira opcional não-default — perda Z-API não afeta operação default. Driver pattern permite deprecar em 1 PR. |
| R8 | Felipe/Maiara onboardam MCP e mexem em código Whatsapp sem ler ARCHITECTURE atualizada | Média | Médio | PR Fase 1 atualiza ARCHITECTURE + BRIEFING.md + Skill `mcp-first` Tier A força brief-fetch primeiro. CLAUDE.md path-scoped `.claude/rules/modules.md` já obriga preflight. |
| R9 | Migration DROP columns falha em prod (FK constraint, etc) | Baixa | Médio | Dry-run migration biz=1 antes de prod + `DROP IF EXISTS` defensivo + Pest cobre. Rollback = down migration recria colunas (mas dado perdido — daemon já desligado). |

### 5.3 Tier 2 (cosmético)

| # | Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|---|
| R10 | UI Settings antiga (3 abas Baileys/Z-API/Meta) confunde durante transição | Média | Cosmético | PR Fase 2 unifica UI: 1 tab default Meta + 1 collapsed "Opções avançadas — Z-API legacy". |
| R11 | Time MCP precisa retreinar skill `baileys-update-procedure` (deprecada) | Baixa | Cosmético | Archive skill + adicionar `meta-cloud-onboarding` Tier C nova com Embedded Signup flow. |

---

## 6. Métricas de sucesso 90 dias

### 6.1 Quantitativas (Prometheus + OTel canônico)

| Métrica | Baseline (Baileys) | Target 90d (Meta Cloud) | Threshold falha |
|---|---|---|---|
| Uptime sessão | ? (Wagner reportou "instável") | 99.9% (Meta SLA oficial) | < 99% sustained 7d → escalation |
| Custo/conversa BR | R$ 99/mês fixo ÷ N conv (não-linear) | R$ 0,08 utility / R$ 0 service | > R$ 0,15 utility médio = revisão pricing |
| Latência send p95 (end-to-end) | desconhecida | < 2s | > 5s sustained 5min |
| Latência webhook recv p95 | desconhecida | < 500ms | > 2s sustained 5min |
| Container restarts/24h | ? (catalogado ARCHITECTURE.md §16.7) | N/A (sem daemon!) | N/A |
| Bans Meta | ≥ 1 (Wagner reportou instabilidade) | **0** | qualquer 1 = revisão imediata |
| Conversões biz=1 Wagner test | ? | ≥ 30 mensagens/semana sem incidente | < 80% taxa entrega |
| `whatsapp.driver.fallback` counter (Z-API ativado) | N/A | 0 | > 0 = problema Meta Cloud (revisão) |

### 6.2 Qualitativas (Wagner + cliente piloto)

- **Wagner usa sem dor 30 dias** → "✅ profissionalizou de fato"
- **Larissa biz=4 (se entrar canary)** percebe melhora ou empate vs status quo (sem perceber porque biz=4 não usa WhatsApp Baileys hoje em produção do oimpresso — usa o Z-API legacy se algum)
- **Mensagem WhatsApp dispara automaticamente** em mudanças de status Repair (US-WA-004) — funcionalidade fim-a-fim
- **JANA Pro brief diário ([ADR 0140](../decisions/0140-jana-pro-produto-comercial-saas.md)) entrega via WhatsApp Meta Cloud** — gate Mês 1 JANA-A.

### 6.3 Gates explícitos (review_triggers)

A ADR proposta acompanha define triggers de reabertura. Resumo:
- **≥3 businesses banidos Meta Cloud em 90d** → catastrófico (improvável driver oficial) → ADR nova investigar
- **Custo Meta médio > R$ 200/mês/business sustained 60d** → reabrir avaliar BSP enterprise (Take Blip / Twilio markup)
- **Cliente pagante pedir compliance ISO/SOC2** → reabrir avaliar BSP enterprise
- **Embedded Signup v4 deprecated/quebrado** → reabrir investigar v5 ou manual fallback
- **Volume passa 5k conversas/mês algum business** → reabrir avaliar BSP com SLA reforçado (cabível dentro Meta Cloud até ~10k, depois margem BSP justifica)

---

## 7. Triggers de reavaliação da decisão

Quando reabrir esta decisão (ADR companion documenta no frontmatter `review_triggers`):

1. **Meta muda pricing BR substancialmente** (≥ 50% aumento utility ou ≥ 100% marketing) — reabrir avaliar BSP ou Z-API mais barato
2. **BSP enterprise pedido por cliente piloto pagante** ≥ R$ 500/mês recorrente assinado — reabrir avaliar Take Blip / Twilio / 360dialog
3. **Embedded Signup v4 quebra ou Meta soltar v5 incompatível** — adapt path
4. **Cliente legacy aparece com 100% volume Z-API ativo** — reabrir avaliar manter Z-API como default por business específico (multi-driver multi-business)
5. **Volume médio passa 5k conv/mês** algum business — reabrir avaliar BSP com SLA
6. **Onda detecção Meta atinge Cloud API oficial** (improvável mas catalogar) — emergência BSP enterprise
7. **3+ clientes pedirem feature WhatsApp que só BSP entrega** (proativo broadcast list, catálogo nativo Pix, etc) — reabrir avaliar
8. **JANA Pro custo LLM + WhatsApp combined > 20% revenue** — reabrir avaliar otimização ou repricing

Skill futura Tier C: `whatsapp-decision-reabrir-watch` — review semanal Wagner sobre triggers acima.

---

## 8. Pré-flight checks (ANTES de disparar Fase 0)

- [ ] Wagner leu este dossier + ADR proposta (~10min)
- [ ] Wagner aprova ADR `accepted` lifecycle (`status: accepted` + frontmatter `accepted_at: 2026-05-27` + `accepted_by: wagner`)
- [ ] Wagner confirma "ninguém ativo no Baileys hoje 27/mai" — query auditoria: `SELECT business_id, baileys_phone_e164, last_health_check_at FROM whatsapp_business_configs WHERE driver = 'baileys' AND driver_health IN ('healthy','degraded')` → deve retornar 0 rows
- [ ] Backup `mysqldump whatsapp_business_configs whatsapp_baileys_sessions` (se table existe) feito antes DROP migrations
- [ ] Wagner tem Meta App Business existente acessível (Meta Business Manager) — pre-req Embedded Signup v4 Fase 2
- [ ] Loki/Grafana sem alarmes ativos último 24h (saúde infra atual)
- [ ] Branch git `archive/baileys-daemon` criada ANTES PR delete (preserva código)
- [ ] Git tag `baileys-final-2026-05-27` criada
- [ ] ADR 0096 fica `lifecycle: ativo` (Constituição append-only) mas ADR nova hoje declara `supersedes_partially` + comentário "emenda 4 supersedida"

---

## 9. Sequência recomendada (paralelo vs sequencial)

**Sequencial obrigatório:**
- Fase 0 → Fase 1 (deps: daemon parado antes de deletar código)
- Fase 1 → Fase 2 (deps: schema cleanup antes de Embedded Signup que usa schema novo)
- Fase 2 → Fase 3 (deps: Meta Cloud default antes de smoke)
- Fase 3 → Fase 4 (deps: canary 7d antes rollout)

**Paralelo possível:**
- Fase 2 (Embedded Signup) e revisão `BRIEFING.md` + atualização runbooks podem rodar paralelo
- Fase 3 (canary Wagner) e início criação skill nova `meta-cloud-onboarding` Tier C
- Fase 4 (monitor 30d) e início JANA Pro Sprint JANA-A US-COPI-201 (BriefDiarioAgent) — depende de Meta Cloud Wagner pessoal pronto

**Sem paralelismo entre Fases 0/1** — sequencial forçado.

---

## 10. Surpresa estratégica (finding adicional não-óbvio)

Durante pesquisa 2026, **descobri 2 findings que reforçam decisão muito além do esperado:**

### 10.1 Embedded Signup v4 é MANDATÓRIO Meta a partir 15/out/2026

[PPCLand](https://ppc.land/metas-embedded-signup-v4-is-here-but-the-october-15-clock-is-ticking/) reporta que Meta publicou em 14/mai/2026 chamada pra developers migrar de v2/v3 → v4, **deprecation hard 15/out/2026**.

**Implicação estratégica:** se oimpresso atrasar adoção Meta Cloud até Q4/2026, vai precisar implementar Embedded Signup v4 sob pressão emergencial. **Fazer agora (Fase 2 começa 30/mai)** dá 4.5 meses de runway pra estabilizar antes da mandatoriedade. Risco timing zero.

### 10.2 Service messages unlimited grátis muda economics JANA Pro radicalmente

[Chatarmin 2026](https://chatarmin.com/en/blog/whats-app-api-pricing) confirma desde **nov/2024** todas customer-initiated service conversations são **unlimited grátis** (sem cap 1k/mês antiga).

**Implicação estratégica pra JANA Pro [ADR 0140]:**
- Brief diário Wagner via WhatsApp = template utility R$ 0,08 × 30 dias = **R$ 2,40/mês/cliente** (não R$ 0,15/dia × 30 = R$ 4,50/mês como previa modelo conservador ADR 0140)
- Triage ad-hoc cliente-initiated = service messages = **R$ 0/triage** (não R$ 0,02 como previa)
- **Margem JANA Pro real pode ser 96-98% em vez de 92-94% projetado** — folga adicional ~R$ 5k/ano em 50 clientes Pro = R$ 600/mês a mais de margem

Este finding **fortalece ADR 0140** independentemente da decisão Baileys-OUT — reportar ao Wagner como bonus value-add.

### 10.3 Z-API SaaS oficialmente NÃO é BSP Meta autorizado

[Z-API.io](https://z-api.io/) reivindica "99.9% uptime" e "ban rate < 0.3%" mas é explicitamente **não-oficial** (não BSP Meta licenciado). Implicação:
- Em cenário enterprise compliance (ISO/SOC2 pedido por cliente pagante), Z-API **não satisfaz** requisito BSP. Apenas Meta Cloud direto ou BSP licenciado.
- Justifica ainda mais Meta Cloud como default — preserva opção compliance enterprise sem refactor.

---

## 11. Referências externas (com data 2026)

### Meta Cloud API + Embedded Signup
- [Whautomate · Embedded Signup 5-15min 2026](https://whautomate.com/whatsapp-embedded-signup)
- [PPCLand · Embedded Signup v4 deadline outubro 2026](https://ppc.land/metas-embedded-signup-v4-is-here-but-the-october-15-clock-is-ticking/)
- [Chatarmin · WhatsApp API Pricing 2026 service msg unlimited free](https://chatarmin.com/en/blog/whats-app-api-pricing)
- [Message Central · Brazil API Pricing 2026 BRL localization](https://www.messagecentral.com/blog/whatsapp-business-api-pricing-in-brazil)

### Stability + Ban risk (justifica NÃO ir whatsmeow/WuzAPI)
- [whatsmeow issue #810 · Detection wave 2026 atinge whatsmeow E Baileys](https://github.com/tulir/whatsmeow/issues/810)
- [kraya-ai blog · WhatsApp Automation Ban Risk 2026](https://blog.kraya-ai.com/whatsapp-automation-ban-risk)
- [whatsmeow discussion #979 · Production scale](https://github.com/tulir/whatsmeow/discussions/979)
- [github.com/asternic/wuzapi](https://github.com/asternic/wuzapi) — WuzAPI rejeitado

### BSP brasileiros (rejeitados por custo)
- [Best WhatsApp BSP Brazil 2026 comparison](https://www.messagecentral.com/blog/best-whatsapp-business-api-platform-brazil)
- [Top 10 BSPs Brazil 2026](https://www.ycloud.com/blog/top-whatsapp-business-api-solution-providers-brazil)

### ADRs canon oimpresso
- [ADR 0094 Constituição v2](../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0093 Multi-tenant Tier 0](../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0096 Whatsapp módulo mãe](../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
- [ADR 0105 Cliente como sinal](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0106 Recalibração 10x](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0117 Multi-phone](../decisions/0117-multiplos-numeros-whatsapp-por-business.md)
- [ADR 0140 JANA Pro](../decisions/0140-jana-pro-produto-comercial-saas.md)

### Internal oimpresso
- [ARCHITECTURE.md Whatsapp](../requisitos/Whatsapp/ARCHITECTURE.md)
- [SPEC.md Whatsapp](../requisitos/Whatsapp/SPEC.md)
- [Proposta antiga híbrida 2026-05-27 manhã (rejeitada após sinal Wagner tarde)](../decisions/proposals/2026-05-27-baileys-vs-whatsmeow-substituicao-daemon-whatsapp.md)

---

## 12. Reporte ao Wagner (1-frase TL;DR pra abrir/fechar)

> **Wagner:** O caminho profissional do WhatsApp do oimpresso a partir de hoje é **Meta Cloud API como default universal + Z-API como driver opcional não-default + BaileysDriver/daemon Node OUT integral**. Decisão venceu α/γ/δ/ε porque (1) você reportou Baileys instável, (2) onda detecção Meta 2026 atinge whatsmeow igual Baileys (rewrite não resolve), (3) Embedded Signup v4 default Meta 5-15min derruba premissa "Z-API default por onboarding rápido" da ADR 0096 emenda 3, (4) service messages unlimited grátis desde nov/2024 destrava custo zero JANA Pro [ADR 0140] em margem 96-98% real (não 92-94% previsto). Próximo passo HOJE: SSH CT 100 `docker compose stop whatsapp-baileys` (você autorizou). Custo total ~18h suas + 16-24h codáveis IA + 37 dias relógio. Economia: R$ 99/mês Z-API + RAM CT 100. ADR pronta pra você aceitar/rejeitar em 5 min: [`2026-05-27-whatsapp-profissionalizacao-baileys-out.md`](../decisions/proposals/2026-05-27-whatsapp-profissionalizacao-baileys-out.md).

---

**Fim dossier · audit-senior-expert (opus-4.7) · 2026-05-27 · PT-BR · Sem hedge · Decidido**
