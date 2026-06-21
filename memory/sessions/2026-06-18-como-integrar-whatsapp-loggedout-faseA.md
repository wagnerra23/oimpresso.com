---
date: 2026-06-18
topic: "como-integrar — WhatsApp LoggedOut (Fase A app-side)"
type: session
---

# como-integrar — WhatsApp LoggedOut (Fase A app-side) — 2026-06-18

> Grounding pré-código (NÃO codar, NÃO commitar). Agente `como-integrar` introspectivo.
> Escopo: fechar app-side o gap do `LoggedOut` que o WuzAPI não repassa — fazer `channel_health`
> refletir com precisão um logout remoto (sessão zumbi: WuzAPI "connected" mas deslogado).
> Decisão prévia (doc vivo `2026-06-18-arte-whatsapp-naoficiais.md`): EVOLUIR stack WuzAPI/whatsmeow,
> não migrar pro WAHA. POC Phase 2 provou que WAHA-GOWS emite `session.status=FAILED` no LoggedOut
> e nosso WuzAPI **não** emite o webhook `LoggedOut` nativamente.

---

## Veredito Fase 1 (1 linha)

**PARCIAL — ~70% já existe.** A detecção de LoggedOut por corroboração ativa **já está implementada**
em `WhatsmeowReconciler::reconcile()` (lê `Connected`+`LoggedIn` do `/session/status`, retorna
`WhatsmeowState::LOGGED_OUT`). O que **falta** (Fase A) é: (a) **cadência** — nenhum cron chama
`reconcile()` pros canais whatsmeow; (b) **persistência** — `LOGGED_OUT` é estado read-only que
nunca chega ao `channel_health`; (c) o enum `channel_health` **não tem** valor `logged_out`.
**NÃO criar do zero. Estender o que existe.**

---

## 1 · INVENTÁRIO

| O que procurei | Onde achei | Status |
|---|---|---|
| Detecção LoggedOut por status ativo (Connected && !LoggedIn) | `Modules/Whatsapp/Services/WhatsmeowReconciler.php:91-113` (`reconcile()` + `fetchSessionStatus()`) | **completo** — retorna `WhatsmeowState::LOGGED_OUT` |
| Enum de estado canon com LOGGED_OUT | `Modules/Whatsapp/Services/Drivers/WhatsmeowState.php:50` | completo (mas NÃO persiste em DB — read-only por design, comment l.10-13) |
| Webhook `LoggedOut` handler | `Modules/Whatsapp/Http/Controllers/Api/WhatsmeowWebhookController.php:170,206-238` | existe MAS **morto na prática** — WuzAPI não emite o evento (raiz do gap) |
| Cron reconcile/probe pra whatsmeow | `app/Console/Kernel.php:668,686` + `Modules/Whatsapp/Console/Commands/{HealthProbeChannels,ChannelsReconciler}Command.php` | **AUSENTE pra whatsmeow** — ambos comandos são `TYPE_WHATSAPP_BAILEYS` only |
| `channel_health` enum coluna | `Modules/Whatsapp/Database/Migrations/2026_05_11_000001_create_omnichannel_tables.php:79-80` | `['healthy','degraded','disconnected','banned','never_checked']` — **sem `logged_out`** |
| Coluna `channel_health_consecutive_failures` | mesma migration, l.81 | completo |
| Banner/health no frontend | `resources/js/Pages/Atendimento/CaixaUnificada/_components/ChannelsDrawer.tsx:35-40,105-109` | **defeituoso** — `HEALTH_LABELS` tem chave `down` (não existe no DB) e falta `disconnected`/`banned`/`logged_out` → mostra string crua |
| Persistência de disconnect/ban | `WhatsmeowReconciler::markDisconnectedInDb():224-241` | existe — **mas mapeia `logged_out` reason → `banned` (impreciso)** ver §5 |
| ADR sobre state-machine whatsmeow | `memory/decisions/0206-state-machine-whatsmeow-reconciliacao.md` | **ativo** — Decisão 5 planejou `WhatsmeowHealthProbeCommand` a cada 30min que faria exatamente isto; nunca foi entregue pra whatsmeow |
| ADR 0286 (citado no briefing) | não existe no repo (`grep 0286` → zero em decisions/) | **conceitual** — referência do briefing, não há doc canônico |
| Doc vivo `arte-whatsapp-naoficiais.md` | não checado no canônico (`memory/sessions/` não tem) | provavelmente não-commitado / outra branch |
| Pest do reconciler (cobre LOGGED_OUT) | `Modules/Whatsapp/Tests/Feature/WhatsmeowReconcilerTest.php:129-147` | completo — `it('reconcile() retorna LOGGED_OUT...')` |

### O 70% que já existe (não reimplementar)
- `reconcile()` distingue QR_PENDING vs LOGGED_OUT pela heurística "channel já foi active / já está disconnected" (`WhatsmeowReconciler.php:101-110`).
- `fetchSessionStatus()` faz GET `/session/status` com header `Token: {userToken}` e lê `data.{Connected,LoggedIn,Jid}` (l.359-381). **Este é o flag do WuzAPI que distingue logout de desconexão** (`Connected=true` + `LoggedIn=false` = logado-out; `Connected=false` = desconectado de rede).
- `markDisconnectedInDb()` e `markPairedInDb()` já mutam `channel_health` com `forceFill` (evita o pega-property-dinâmica).
- `WhatsmeowState::userMessage()` já tem PT-BR pronto: `LOGGED_OUT => 'Sessão expirou no celular. Re-conecte gerando novo QR.'`

### O 30% faltante (Fase A) — exatamente o gap
1. **Cadência:** nenhum schedule chama `reconcile()` em canais `TYPE_WHATSAPP_WHATSMEOW`. Os 2 crons existentes (`whatsapp:health-probe-channels` daily 03:30, `whatsapp:channels-reconcile` 5min) filtram `TYPE_WHATSAPP_BAILEYS` e batem no endpoint Baileys `/instances/{id}/status` — não funcionam pra whatsmeow.
2. **Persistência:** `reconcile()` é read-only; `LOGGED_OUT` observado nunca vira `channel_health` durável.
3. **Vocabulário:** `channel_health` enum não tem `logged_out` → logout hoje é forçado a virar `disconnected` ou (pior) `banned`.

---

## 2 · PEGADINHAS APLICÁVEIS (filtradas)

| # | Pegadinha | Por que se aplica aqui | Mitigação |
|---|---|---|---|
| 1 | **Multi-tenant Tier 0** (ADR 0093) | Comando CLI cross-business precisa `withoutGlobalScope(ScopeByBusiness)` + iterar `business_id` explícito; Reconciler NUNCA atravessa fronteira | Espelhar `HealthProbeChannelsCommand.php:91-103` (filtro `--business`, `orderBy('business_id')`) + comentário SUPERADMIN |
| 8 | **Identifiers MySQL ≤64 chars** | Migration que altera enum `channel_health` não cria índice novo, mas se mexer no `channels_type_health_idx` (l.95) precisa nome explícito | Não recriar índice; só `MODIFY COLUMN` enum |
| — | **Enum ALTER idempotente + down()** (rules/migrations.md) | Adicionar `logged_out` ao enum exige migration com `up()` idempotente e `down()` reversível; enum em MySQL = `DB::statement("ALTER TABLE ... MODIFY ...")` | `down()` precisa primeiro normalizar rows `logged_out`→`disconnected` antes de remover o valor (senão data-truncation) |
| — | **Append-only audit (Spatie)** | `Channel` loga `channel_health` via `LogsActivity` (`Channel.php:55-68`). Mudar `channel_health` gera activity_log automático — bom, mas **não logar `config_json`** (tem token WuzAPI) | Já coberto — `getActivitylogOptions` não inclui config_json |
| — | **NÃO tocar daemon prod nesta fase** | Fase A é app-side puro. WuzAPI no CT 100 fica intocado; sem fork; sem novo endpoint no daemon | Só ler `/session/status` (já consumido) — zero mutação no daemon |
| 14 | **Pest biz=1, nunca cliente real** (ADR 0101) | Novo teste do comando roda no CT 100. `WhatsmeowReconcilerTest` usa `business_id=99` (sintético, OK). Não usar biz=4 ROTA LIVRE | Usar biz sintético (1 ou factory), Http::fake do daemon |
| 15 | **Eloquent dynamic property** | Persistir health: usar `forceFill([...])->save()` (como `markDisconnectedInDb`), NÃO `$channel->_flag = ...` | Reusar padrão existente |
| — | **format_now_local / now()** (ADR 0066) | `last_health_check_at` = `now()` (carimbo "agora") — manter padrão dos comandos existentes | Já consistente |

> Observação (não-pegadinha-catalogada): **WuzAPI shape variável** (`Connected` vs `connected`, envelope `{data:...}`) — `fetchSessionStatus()` já trata defensivo (l.94-95, 377). Qualquer código novo deve reusar o Reconciler, não re-parsear o JSON do daemon.

---

## 3 · PONTO DE PLUGUE

### Decisão de design (qual das 3 opções do briefing)

**Opção (b) — probe ativo distingue logout via flag do WuzAPI — é a mais limpa.** Razões:
- O Reconciler **já distingue** LOGGED_OUT via `Connected && !LoggedIn` (a "flag" pedida na opção (b) já existe e já é lida). Não precisa heurística de inbound/self-test (opção (c) é mais frágil e custosa — exigiria enviar mensagem self-test, risco de spam/ban).
- A opção (a) — novo estado `channel_health='logged_out'` — é **complementar**, não alternativa: sem o valor no enum, o probe não tem onde gravar a distinção. Então Fase A = **(b) + (a) juntas**: probe ativo (b) que persiste num estado novo (a).

### Mapa concreto

| Peça | Arquivo + linha | Ação |
|---|---|---|
| Enum `channel_health` (a) | `Modules/Whatsapp/Database/Migrations/<nova>_add_logged_out_to_channel_health_enum.php` ⚠️ criar | `ALTER TABLE channels MODIFY channel_health ENUM('healthy','degraded','disconnected','banned','never_checked','logged_out') DEFAULT 'never_checked'`; `down()` normaliza `logged_out`→`disconnected` antes |
| Comando probe whatsmeow (b) | `Modules/Whatsapp/Console/Commands/WhatsmeowHealthProbeCommand.php` ⚠️ criar | Itera Channels `TYPE_WHATSAPP_WHATSMEOW` status active, chama `WhatsmeowReconciler::reconcile()`, mapeia estado→`channel_health` e persiste. **Nome ADR-0206-Decisão-5.** NÃO confundir com `HealthProbeChannelsCommand` (Baileys) |
| Persistência LOGGED_OUT | `Modules/Whatsapp/Services/WhatsmeowReconciler.php` (novo método `markLoggedOutInDb()` ~l.241) | espelhar `markDisconnectedInDb` mas `channel_health='logged_out'`, `status` mantém ou vira `disconnected`, mensagem `WhatsmeowState::LOGGED_OUT->userMessage()` |
| Corrigir webhook ban-keyword | `Modules/Whatsapp/Http/Controllers/Api/WhatsmeowWebhookController.php:209` | remover `logged_out` de `$banKeywords` e rotear LoggedOut→`markLoggedOutInDb` (caso WuzAPI um dia emita) — separa logout de ban |
| Registrar comando | `Modules/Whatsapp/Providers/WhatsappServiceProvider.php:~108` | adicionar `WhatsmeowHealthProbeCommand::class` ao array de commands |
| Agendar cron | `app/Console/Kernel.php:~695` (após bloco channels-reconcile) | `$schedule->command('whatsapp:whatsmeow-health-probe')->everyTenMinutes()->withoutOverlapping()->environments(['live'])` + `onFailure` log (ADR 0206 pediu 30min; 10min dá detecção mais rápida do zumbi) |
| Mapa health no frontend | `resources/js/Pages/Atendimento/CaixaUnificada/_components/ChannelsDrawer.tsx:35-40` | adicionar `disconnected:'fora do ar'`, `banned:'banido'`, `logged_out:'desconectado — reconecte'`; remover chave morta `down` |
| (opcional) Banner inbox | `Modules/Whatsapp/Http/Controllers/Admin/CaixaUnificadaController.php:565-574` + `InboxController.php:387-407` | payload já expõe `channel_health` — só garantir que `logged_out` propaga (string passa direto, sem mudança backend) |
| Pest do comando | `Modules/Whatsapp/Tests/Feature/WhatsmeowHealthProbeCommandTest.php` ⚠️ criar | Http::fake `/session/status` retornando `{Connected:true,LoggedIn:false}` em canal já-active → asserta `channel_health='logged_out'` persistido. Cobrir healthy + disconnected + multi-tenant scope |
| Charter (se tocar Page) | `resources/js/Pages/Atendimento/CaixaUnificada/*.charter.md` (se existir) | atualizar se ChannelsDrawer tiver charter ao lado |

⚠️ **Plugues a criar:** migration enum, comando probe, método `markLoggedOutInDb`, teste Pest. Os demais são edits em arquivos existentes.

---

## 4 · CHECKLIST PRÉ-CÓDIGO

```markdown
## Pré-código checklist — WhatsApp LoggedOut Fase A (app-side)

### Antes de Edit/Write
- [ ] Ler RUNBOOK: memory/requisitos/Whatsapp/runbooks/whatsmeow-daemon-deploy-ct100.md (referenciado em Config/config.php:60) + whatsmeow-troubleshoot.md se existir (ADR 0206 Decisão 7)
- [ ] Feature flag necessária? NÃO — Fase A é leitura passiva + novo cron; reversível removendo schedule. (ADR 0206 usou WHATSMEOW_USE_RECONCILER pra refactor controller; aqui não há refactor de caminho crítico)
- [ ] Schema migration necessária? SIM — add 'logged_out' ao enum channel_health (idempotente up + down normaliza dados)
- [ ] ADR nova necessária? NÃO — ADR 0206 Decisão 5 já cobre conceitualmente (WhatsmeowHealthProbeCommand). Anexar nota de implementação ao dossier companion 2026-05-27-dossier-profissionalizacao-whatsmeow.md OU criar emenda curta se Wagner quiser rastreio formal do novo estado de enum

### Pegadinhas a respeitar
- [ ] Multi-tenant Tier 0 (ADR 0093) — comando cross-business com withoutGlobalScope + comentário SUPERADMIN + iterar business_id (espelhar HealthProbeChannelsCommand:91-103)
- [ ] Migration enum: up() idempotente, down() normaliza logged_out→disconnected ANTES de remover valor (anti data-truncation)
- [ ] forceFill()->save() pra persistir health (nunca property dinâmica)
- [ ] Pest biz sintético (não biz=4); Http::fake do daemon (não bater CT 100 real no teste)
- [ ] NÃO tocar daemon WuzAPI / docker-compose / endpoint Go — só consumir /session/status já existente
- [ ] Spatie activity_log: channel_health já logado, config_json nunca logado (já OK)

### Pontos de plugue (em ordem)
- [ ] Migration: <nova>_add_logged_out_to_channel_health_enum.php — MODIFY enum + down()
- [ ] Backend service: WhatsmeowReconciler.php — novo markLoggedOutInDb()
- [ ] Backend command: WhatsmeowHealthProbeCommand.php — probe whatsmeow + persist health
- [ ] Backend webhook: WhatsmeowWebhookController.php:209 — separar logged_out de banKeywords
- [ ] Provider: WhatsappServiceProvider.php — registrar comando
- [ ] Kernel: app/Console/Kernel.php — schedule everyTenMinutes live
- [ ] Frontend: ChannelsDrawer.tsx:35-40 — HEALTH_LABELS completo (disconnected/banned/logged_out, remover 'down')
- [ ] Test: WhatsmeowHealthProbeCommandTest.php — logged_out persist + healthy + tenant scope

### Smoke pós-deploy
- [ ] biz=1 (test/CT 100): Http::fake Connected=true/LoggedIn=false em canal active → channel_health='logged_out' (Pest)
- [ ] biz=4 (ROTA LIVRE prod, canary opcional): desvincular dispositivo no celular → aguardar ≤10min → ChannelsDrawer mostra "desconectado — reconecte" (não "connected" zumbi)

### Estimativa total (IA-pair, ADR 0106)
- 2-4h IA-pair (1 migration + 1 comando ~80 LOC + 1 método ~20 LOC + edits + 1 Pest) + ~0.5h Wagner smoke canary
```

---

## Riscos / nota final

- **Maior risco:** o bug latente `markDisconnectedInDb` tratando `logged_out` como **ban** (`WhatsmeowWebhookController.php:209` + `WhatsmeowReconciler.php:226`). Se a Fase A introduzir `channel_health='logged_out'` sem corrigir esse mapeamento, um eventual webhook LoggedOut (caso versão WuzAPI passe a emitir) ainda marcaria `banned` — contradizendo o probe. **Corrigir os dois caminhos juntos** ou o estado fica inconsistente entre probe-ativo e webhook-passivo.
- **Risco secundário:** heurística QR_PENDING vs LOGGED_OUT em `reconcile()` (l.101-110) depende de `channel.status==='active' || channel_health==='disconnected'`. Para um canal recém-pareado que cai logo, a heurística está correta; mas vale um teste de borda.
- **Reuso máximo:** NÃO escrever novo parsing de `/session/status` no comando — chamar `WhatsmeowReconciler::reconcile()` e só traduzir o `WhatsmeowState` retornado para `channel_health`. Isso mantém o Reconciler como single source of truth (ADR 0206 invariante).
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
