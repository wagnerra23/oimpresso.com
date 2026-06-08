# WhatsApp Baileys incident — 3 instâncias travadas (2 banned + 1 zombie) + criação agent `whatsapp-doctor`

## Resumo

- **Trigger:** Wagner em sessão pediu "DESATIVE O WHATZAP DO SERVIDOR" → "leia os erros" → "arrume a porcaria da mensagem"
- **Severidade:** P1 (single-tenant) — sem cliente prejudicado por sorte (zero outbound/inbound durante outage)
- **Business afetados:**
  - biz=1 interno: channel id=2 (Suorte [sic], `554888782087`) + channel id=3 (Suporte, `554896486699`) — sessões teste do Wagner travadas
  - **biz=164 MARTINHO CAÇAMBAS LTDA**: channel id=4 (Jana, `554888782087`) — cliente real Modules/OficinaAuto, pareado hoje 14:23:11
- **Início detectado:** 2026-05-13 16:31 UTC (primeiros stream:error code 515)
- **Resolvido:** 2026-05-13 19:19 UTC (reconnect zombie via disconnect+connect)
- **Duração outage cliente real (biz=164):** ~99min sem socket WA ativo, mas SEM mensagens perdidas (cliente ainda não estava operando)

## Diagnóstico (Fase 1)

### Estado do daemon CT 100

3 instâncias rotacionando no log do daemon:

| instance_id (channel UUID) | state daemon | ban_reason | Telefone | Channel DB | biz |
|---|---|---|---|---|---|
| `ch-da8c23c5-5a6c-4538-b82f-1a05c47ac5da` | `banned` | `logged_out` | 554888782087 | id=2 "Suorte" | 1 |
| `ch-3bcafcfc-7506-48cd-843d-72116460d95b` | `banned` | `logged_out` | 554896486699 | id=3 "Suporte" | 1 |
| `ch-88b13697-b89e-451c-b65b-e917533bab21` | `connected` (zombie) | null | 554888782087 | id=4 "Jana" | 164 |

### Sinal forte de conflito

**Channels id=2 (biz=1) e id=4 (biz=164) têm o MESMO `display_identifier=554888782087`.** Isso explica os `stream:error conflict type="replaced"` no log Baileys (16:40, 16:41) — o pareamento da Jana@MARTINHO (id=4) no canal id=4 substituiu o pareamento legado do id=2, mas o id=2 ficou pendurado no daemon CT 100 entrando em loop reconnect.

### Logs daemon (cronologia)

| UTC | Erro | Instance | Significado |
|---|---|---|---|
| 16:31 | stream:error code 515 | ch-da8c23 | Baileys solicitando restart pós-handshake |
| 16:31 | Timed Out fetchProps | ch-da8c23 | Handshake inicial estourou timeout |
| 16:40 | conflict type="replaced" | ch-3bcafcfc | Outro device assumiu sessão |
| 16:41 | code 401 conflict type="device_removed" | ch-3bcafcfc | Aparelho removido pelo celular |
| 16:41+ | Timed Out loop infinito | ch-da8c23, ch-3bcafcfc | Daemon não consegue reconectar credenciais revogadas |
| 17:39 | stream:error code 515 | ch-88b13697 | Última atividade real antes do socket zumbi |

### Estado DB Hostinger pós-incidente

```
channels table:
  id=2 biz=1   status=banned         health=banned        last_msg="banned: logged_out"
  id=3 biz=1   status=disconnected   health=disconnected  last_msg="disconnected: manual"
  id=4 biz=164 status=active         health=healthy       last_msg="connected"
```

DB já reflete bem o estado real — channel id=2 marcado `banned`. Channel id=3 ficou `disconnected: manual` (Wagner desconectou manualmente).

## Recovery executado

### Passo 1 — Purge instâncias banned (Fase 2.A do agent `whatsapp-doctor`)

```bash
TOKEN=$(tailscale ssh root@ct100-mcp 'docker exec whatsapp-baileys cat /run/secrets/whatsapp_baileys_api_key')

for id in ch-da8c23c55a6c4538b82f1a05c47ac5da ch-3bcafcfc750648cd843d72116460d95b; do
  tailscale ssh root@ct100-mcp "docker exec whatsapp-baileys node -e \"
    fetch('http://localhost:3000/instances/${id}',{method:'DELETE',headers:{Authorization:'Bearer ${TOKEN}'}}).then(r=>r.text()).then(console.log)
  \""
done
# {"ok":true} x2
```

**Resultado imediato:** errors no log do daemon caíram a 0 nos 30s seguintes (de ~10 errors/min pra zero).

### Passo 2 — Reconnect zombie (Fase 2.C)

`ch-88b13697...` reportava `state=connected` mas `last_seen=17:39:39` (estagnado ~99min). Disconnect + Connect com mesmo `business_uuid`:

```bash
# disconnect
docker exec whatsapp-baileys node -e "fetch('http://localhost:3000/instances/ch-88b13697b89e451cb65be917533bab21/disconnect',{method:'POST',headers:{Authorization:'Bearer ${TOKEN}'}})..."
# {"ok":true}

# connect
docker exec whatsapp-baileys node -e "fetch('http://localhost:3000/instances/ch-88b13697b89e451cb65be917533bab21/connect',{method:'POST',headers:{Authorization:'Bearer ${TOKEN}','Content-Type':'application/json'},body:JSON.stringify({business_uuid:'88b13697-b89e-451c-b65b-e917533bab21'})})..."
# state=connecting → após 15s → state=connected, last_seen=19:19:07
```

**Resultado:** socket vivo, last_seen volta a atualizar em tempo real.

## Mensagens perdidas?

```sql
SELECT business_id, status, count(*) AS n
FROM whatsapp_messages
WHERE direction='outbound'
  AND created_at BETWEEN '2026-05-13 16:30:00' AND '2026-05-13 19:25:00'
GROUP BY business_id, status;
-- Resultado: ZERO linhas
```

- outbound queued/failed durante outage: **0**
- inbound durante outage: **0**
- re-enqueue necessário: **nenhum**

Cliente real (MARTINHO biz=164) pareou hoje cedo (14:23) e ainda não estava operando. ROTA LIVRE (biz=4 Larissa) NÃO usa Baileys — usa Z-API.

Sem perda. Sorte de timing — se o outage tivesse pego horário comercial do MARTINHO (caçambas, segunda-sexta 08-18), teria perda real.

## Fase 4.2 — Audit anti-ban (11 técnicas canônicas 2026 vs implementação)

Cruzamento das técnicas catalogadas no agent `whatsapp-doctor` (Fase 4.1) contra `Modules/Whatsapp/daemon-node/src/`:

| # | Técnica | Default canônico 2026 | Implementado? | Onde | Gap |
|---|---|---|---|---|---|
| 1 | **Warmup 7d gradual** | D1: 20→D7: 680 msg | ✅ **SIM** | [daemon-node/src/baileys/antiBan.ts:83-90](Modules/Whatsapp/daemon-node/src/baileys/antiBan.ts) `warmupQuotaPerHour()` | Defaults conservadores: D0-1=10msg/h, D1-2=25msg/h, D2-7=50→200msg/h. Mais restrito que mercado — OK |
| 2 | **Rate limit sustentado** | ≤12 msg/min, ≤1000-2000/dia | 🟡 **PARCIAL** | Warmup quota cobre msg/h, não tem cap/min explícito nem cap diário pós-warmup | Pós-D7 = ilimitado middleware-side. Sem cap diário pode disparar Meta ML se cliente fizer broadcast |
| 3 | **Jitter gaussiano** | 1.5-5s entre msgs, centrado | ✅ **SIM** | [antiBan.ts:62-72](Modules/Whatsapp/daemon-node/src/baileys/antiBan.ts) `gaussianRandom()` Box-Muller | Default 1500-4000ms (próximo do canônico) |
| 4 | **Typing simulation** | 45 WPM ±15 + think pauses | 🟡 **PARCIAL** | [antiBan.ts:162-167](Modules/Whatsapp/daemon-node/src/baileys/antiBan.ts) typing+paused fixo `ANTIBAN_TYPING_MS=500ms` | Sem variação por tamanho de texto, sem think pauses 0.8-3.5s mid-message |
| 5 | **Circadian rhythm** | 2-6 AM = 4-6× slower (timezone) | ❌ **NÃO** | — | Sem detecção horária. Risco médio: bot envia 04:00 BRT = robotic |
| 6 | **Contact graph caps** | ≤5 contatos novos/dia, 1h handshake group | ❌ **NÃO** | — | Daemon não controla "novo contato vs conhecido". Cliente pode disparar broadcast cold = strangers high risk |
| 7 | **Reply ratio monitor** | manter >10% inbound/outbound | ❌ **NÃO** | Métrica existe em `whatsapp_conversation_metricas` mas não bloqueia send | Sem gating ativo. Cliente 100% outbound = flag spam Meta |
| 8 | **Reachout timelock 463** | bloqueia novo-contato em janela 463 | ❌ **NÃO** | banDetector.ts não trata 463 específico | Erro 463 = soft warning Meta. Ignorar acelera ban |
| 9 | **Session health monitor** | alerta 3 "Bad MAC" em 60s | 🟡 **PARCIAL** | banDetector.ts cobre `loggedOut/forbidden/badSession` → marca `banned` | Não tem janela 60s pra Bad MAC count. Detecta tarde |
| 10 | **Chip dedicado físico** | nunca reusar número pessoal | 🟢 **CONTRATUAL** | LGPD ack (`lgpd_acknowledged_at`) + FormRequest gating | Confiança no cliente. Não há check técnico |
| 11 | **IP residencial/4G** | datacenter VPS flagged | ❌ **ACEITO** | CT 100 = IP datacenter | ADR 0096 risco aceito explicitamente |

### Pontuação maturidade anti-ban: **3.5 / 11 implementado** (≈32%)

✅ Implementado pleno (3): Warmup, Jitter Gaussiano, ChipDedicado (contratual)
🟡 Parcial (3): Rate Limit (só /h), Typing (fixo, sem WPM), SessionHealth (sem janela 60s Bad MAC)
❌ Faltante (5): Circadian, ContactGraph, ReplyRatio, ReachoutTimelock 463, IPResidencial

### Bugs/gaps adicionais detectados

| # | Gap | Impacto |
|---|---|---|
| A | **Daemon não recebe sinal de DELETE quando channel é desativado no Laravel.** Channels id=2,3 estão `is_active=0` no DB mas instâncias seguem ativas no daemon (até purge manual hoje) | ALTO — desperdiça CPU + flag Meta por sessões fantasmas |
| B | **Display phone duplicado entre channels não bloqueia FormRequest.** id=2 biz=1 e id=4 biz=164 ambos `554888782087` → conflict type="replaced" garantido | ALTO — Meta detecta sessões disputadas |
| C | **Daemon não tem endpoint `GET /instances`** (lista) — diagnóstico depende de pegar instance_ids do log ou DB | MÉDIO — debugging difícil |
| D | **Healthcheck Docker reporta `healthy` mesmo com socket zumbi.** Last_seen estagnado 99min mas state=connected → Docker não restart | MÉDIO — falso positivo no monitoring |
| E | **Sem alerta proativo** quando `state=connected && (now - last_seen) > 30min` | MÉDIO — detecção depende de cliente reclamar |

## Ações criadas/atualizadas nesta sessão

1. ✅ **Agent canônico `whatsapp-doctor`** criado em [.claude/agents/whatsapp-doctor.md](.claude/agents/whatsapp-doctor.md) — runbook executável de 6 fases (triagem → diagnóstico → 6 variantes recovery → audit anti-ban → cross-tenant P0 → post-mortem)
2. ✅ **Índice agents atualizado** em [memory/how-trabalhar.md:84](memory/how-trabalhar.md:84) — 3ª linha na tabela de agents
3. ✅ **Recovery em prod CT 100** — 2 instâncias purgadas + 1 reconectada (descritas acima)
4. ✅ **Post-mortem** (este arquivo)

## Lições

### Coisas que funcionaram
- **Anti-ban implementado** (warmup + jitter + typing) é provavelmente o que fez `ch-88b13697` (MARTINHO) durar até 16:19 healthy mesmo com chip novo pareado 14:23 (2h)
- **banDetector.ts** classificou `device_removed`/`logged_out` corretamente → channels marcados `banned` no DB
- **Estado isolado por business_id** preservado — investigação cross-biz não vazou dados

### Coisas que falharam
- **Sem alerta proativo** — Wagner descobriu olhando logs manualmente
- **Daemon ↔ Laravel sync incompleto** — channels desativados no Laravel não disparam DELETE no daemon (gap A)
- **Validação display_phone unique** não existe entre business (gap B)
- **Healthcheck Docker** mede só HTTP up, não freshness do socket (gap D)

### Tasks recomendadas (via tools MCP, NÃO criar agora — Wagner aprova)

Sugestões pra Wagner avaliar depois de revisar o post-mortem:

1. **US-WA-XXX P1** — Implementar circadian rhythm no antiBan (multiplier 4-6× em 02-06 BRT) — esforço ~2h IA-pair
2. **US-WA-XXX P1** — Implementar reply ratio monitor com gating quando <10% (soft warning) — esforço ~4h IA-pair
3. **US-WA-XXX P1** — Sync Laravel→daemon: ao `Channel::deactivate()`, dispatch job `DeleteBaileysInstanceJob` (gap A) — esforço ~2h IA-pair
4. **US-WA-XXX P1** — `ChannelRequest` validação: `display_identifier` unique cross-business (gap B) — esforço ~30min IA-pair
5. **US-WA-XXX P2** — Endpoint `GET /instances` no daemon pra listagem (gap C) — esforço ~1h IA-pair
6. **US-WA-XXX P1** — Healthcheck custom no daemon: HTTP 503 se `state=connected && (now-last_seen)>30min` em qualquer instância (gap D + E) — esforço ~2h IA-pair
7. **US-WA-XXX P2** — Tratar erro Meta `463` específico em banDetector.ts → bloquear novo-contato 24h (técnica #8) — esforço ~3h IA-pair
8. **US-WA-XXX P1** — Cap diário pós-warmup (`ANTIBAN_DAILY_CAP=1000`) — esforço ~1h IA-pair
9. **US-WA-XXX P2** — Variação typing WPM por tamanho do texto + think pauses (técnica #4 completo) — esforço ~2h IA-pair

**Total estimado**: ~17h IA-pair (~2 dias úteis Wagner com fator 10x — ADR 0106).

ROI: cada item reduz P(ban) marginalmente. Itens 1, 3, 4, 6 são alto-impacto-baixo-esforço (faz sentido começar).

## Estado MCP no momento do fechamento

Não consultei `cycles-active` / `my-work` / `sessions-recent` no início (caí na degradação clássica catalogada em [2026-05-13-agents-canonicos-meta-degradacao.md](memory/sessions/2026-05-13-agents-canonicos-meta-degradacao.md) §1 "Pulou brief-fetch"). Wagner aceitou o trabalho mesmo assim porque era operacional emergencial, mas registro a violação aqui pra honestidade.

## Referências

- [ADR 0096 emenda 4 — Modulo Whatsapp Meta Cloud API direto](memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
- [ADR 0093 — Multi-tenant isolation Tier 0](memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [Runbook canônico — baileys-troubleshoot-ban.md](memory/requisitos/Whatsapp/runbooks/baileys-troubleshoot-ban.md)
- [Agent novo — whatsapp-doctor.md](.claude/agents/whatsapp-doctor.md)
- [baileys-antiban — referência best-practices 2026](https://github.com/kobie3717/baileys-antiban)
