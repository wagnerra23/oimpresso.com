---
date: "2026-09-02"
hour: "17:04 BRT"
topic: "Canais Suporte e Jana (biz 1) sem inbound — diagnóstico whatsmeow, separação outage CT 100 vs morte prévia"
authors: ["C"]
related_adrs:
  - "0202-whatsapp-profissionalizacao-baileys-out"
  - "0204-whatsmeow-driver-substituto-baileys"
  - "0096-modulo-whatsapp-meta-cloud-api-direto"
  - "0062-separacao-runtime-hostinger-ct100"
outcomes:
  - "Driver real dos 2 canais medido: whatsmeow, não Baileys (premissa do relato corrigida)"
  - "Morte dos canais precede o outage do CT 100 em 1039h (Suporte) e 1335h (Jana), provado por 3 séries de log"
  - "Suporte = recebimento quebrado (corte seco de 4min); Jana = ambíguo, 2 causas sobreviveram"
  - "Recovery NÃO executado — CT 100 inalcançável; plano pronto no §7"
---

# WhatsApp incident — canais "Suporte" e "Jana" (biz 1) sem inbound · diagnóstico 2026-09-02

> **Natureza deste doc:** post-mortem de **diagnóstico**. Nenhum recovery foi executado (CT 100
> inacessível na janela). Todo número abaixo é **calculado**, com o comando ao lado. Fatos em
> **passado datado** — não afirmam estado presente do sistema.

## Resumo

- **Trigger:** alarme `whatsapp_inbound_flow` (check 8b do `jana:health-check`) reportando os canais
  `Suporte` e `Jana` (biz 1) sem inbound há ~1176h e ~1471h.
- **Severidade:** P1 (canal de atendimento real morto; sem impacto em cliente pagante — biz 1 é
  interno, e a tabela `channels` não tinha canal de nenhum outro tenant nesta janela).
- **Veredito:** **duas histórias diferentes, não uma.** `Suporte` = recebimento quebrado
  (prova forte). `Jana` = ambíguo, as duas causas sobreviveram à medição.
- **A hipótese do relato ("daemon Baileys caiu") estava errada em dois níveis** — ver §1 e §3.

## 1. Premissa corrigida: não é Baileys, é whatsmeow

O agente `whatsapp-doctor` se descreve como plantão do "daemon Baileys CT 100". Medido em
`origin/main` em 2026-09-02, isso estava **desatualizado**:

- [ADR 0202](../decisions/0202-whatsapp-profissionalizacao-baileys-out.md) descontinuou o `BaileysDriver`.
- [ADR 0204](../decisions/0204-whatsmeow-driver-substituto-baileys.md) pôs **whatsmeow (WuzAPI, Go)** no lugar.
- Todos os runbooks Baileys estão em `runbooks/_archive/` com o carimbo *"NÃO aplicar em produção"*.

E os canais do incidente confirmaram por dado, não por leitura de ADR:

```
channels.type de ambos = whatsapp_whatsmeow
```

Nenhum dos dois é Meta Cloud. Logo a família da hipótese ("o daemon caiu") aponta para o lugar
certo, mas o daemon é o **whatsmeow**, e o procedimento do `_archive/` não se aplica.

## 2. O que o alarme MEDE (vs. o que se concluiu)

`HealthCheckCommand::checkWhatsappInboundFlow()` — driver-agnóstico por design:

```
channels WHERE status='active'
  → conversations ON conversations.channel_id = channels.id
  → MAX(messages.created_at) WHERE messages.direction='inbound'
```

- Threshold: `config('whatsapp.inbound_silence_alert_hours', 6)`. Medido em prod: a chave **não
  existe** no `Config/config.php` do módulo, então vale o **default 6h**.
- Baseline: só vigia canal que já recebeu inbound alguma vez.
- Só acende em horário comercial BRT (08–20, seg–sáb).

**O alarme não sabe distinguir "recebimento quebrado" de "ninguém mandou mensagem".** Essa
separação foi o trabalho desta sessão, e ela **não é a mesma para os dois canais**.

Validação interna do entendimento: os dias sem `ALARME` na série (07-19, 07-26, 08-02, 08-09,
08-16, 08-23, 08-30) são **exatamente domingos** — batendo com o guard `dayOfWeek !== SUNDAY`.

## 3. Datas calculadas (não estimadas)

Relógio da app no momento da medição: `2026-09-02T17:04:26-03:00` (`config('app.timezone')` =
`America/Sao_Paulo`).

| Canal | id | último inbound | idade | último outbound | idade |
|---|---|---|---|---|---|
| Suporte | 11 | 2026-07-15 16:36:43 | **1176,46h** (49d) | 2026-07-15 16:32:43 | 1176,53h |
| Jana | 12 | 2026-07-03 09:22:18 | **1471,70h** (61d) | 2026-06-19 07:28:24 | 1809,60h |

Comando que produziu as idades (reproduzível):

```python
from datetime import datetime
now = datetime.fromisoformat('2026-09-02T17:04:26')
(now - datetime.fromisoformat('2026-07-15 16:36:43')).total_seconds()/3600   # 1176.46
(now - datetime.fromisoformat('2026-07-03 09:22:18')).total_seconds()/3600   # 1471.70
```

As idades do relato (~1176h / ~1471h) **conferiram**. `conversations.last_inbound_at` bateu com
`MAX(messages.created_at)` nos dois canais — sem divergência entre as duas fontes.

## 4. Outage do CT 100 × morte prévia — separado por prova

O CT 100 caiu por volta de 2026-08-28 (≈137h antes da medição). As mortes são **muito anteriores**:

| Canal | morreu antes do outage |
|---|---|
| Suporte | 1039h (43 dias) |
| Jana | 1335h (55 dias) |

**Prova independente da aritmética**, do `storage/logs/laravel.log` (janela 2026-06-21 → 2026-09-02):

- `whatsmeow.message_persisted`: 200–490/dia até **2026-07-15 (260)**; **zero todo dia desde 07-16**.
- `whatsmeow.webhook.connected_processed` (sessão de fato conectou): último dia **2026-07-15**.
- `whatsmeow.reconcile.daemon_unreachable`: 1–21/dia até 07-14 → **143 em 07-15** → **343 em 07-16**.
- O mesmo contador voltou a picos de **442–484/dia de 08-28 a 09-02** — esse é o outage do CT 100,
  um **segundo evento, posterior**, empilhado sobre uma falha que já durava 6 semanas.

**Conclusão:** o outage do CT 100 é **ruído no diagnóstico, não a causa**. Ele explica por que o
daemon estava inacessível *hoje*; não explica o silêncio que começou em julho.

O evento de 2026-07-15/16 foi **CT 100-wide**, não específico do whatsmeow — no mesmo dia falharam
`mcp:sync-mem` (114×), `whatsapp:channels-reconcile` (168×) e o `[backup]` por
*"Connection could not be established"*. Os quatro hostnames do CT 100
(`whatsapp-whatsmeow`, `whatsapp-baileys`, `realtime`, `mcp`) resolveram para o **mesmo IP** e
**todos** deram timeout na medição de 2026-09-02.

**Não foi deploy da app:** `git log origin/main --since=2026-07-13 --until=2026-07-18 -- Modules/Whatsapp/`
retornou **um único commit, de 07-17** — depois da morte. Entre 07-01 e 07-09, **nenhum**. Isso
descarta a classe do incidente #2726 (mudança de auth/rota matando o recebimento).

## 5. As duas causas — e por que só uma fecha

### 5.1 Suporte (ch 11) — recebimento QUEBRADO (prova forte)

- Inbound e outbound pararam com **4 minutos de diferença** (16:32:43 out / 16:36:43 in). Corte
  seco, não decaimento.
- Volume: **5.740 inbound / 3.295 outbound** em 144 conversas; ~117 inbound/dia; **260 mensagens
  persistidas no próprio dia da morte**.
- Um canal nesse regime não vai a zero porque "ninguém mandou mensagem". A hipótese "canal ocioso"
  **está descartada** para este canal.
- Estado final observado: `channel_health='disconnected'`,
  `last_health_message='whatsmeow disconnected: health-probe: provision_pending'`, marcado em
  2026-07-16 17:30:47 — pelo **health-probe**.
- `provision_pending` = *"user existe no daemon mas o socket não conectou"* (`WhatsmeowState`).
  Ou seja: a sessão morreu e **nunca foi re-provisionada**.

**Por que nada se recuperou sozinho** — e isso é comportamento **projetado**, não bug:
`WhatsmeowHealthProbeCommand::decideAction()` devolve `ACTION_NONE` para `PROVISION_PENDING` quando
o canal **já está** `disconnected`. Re-parear exige **QR escaneado por humano** no celular. Nenhuma
automação faz isso.

### 5.2 Jana (ch 12) — AMBÍGUO, as duas causas sobreviveram

- Volume: **40 inbound / 18 outbound** em 6 conversas — ~1 inbound/dia.
- Outbound parou em 06-19; inbound em 07-03 — **337,9h de intervalo entre os dois**. É o **oposto**
  do corte seco do Suporte.
- Marcado `disconnected` em 2026-07-08 23:06:36 com `whatsmeow disconnected: unknown` — origem
  `WhatsmeowWebhookController` (evento **do daemon**), não o health-probe. Mecanismo diferente do
  Suporte.
- A série do alarme mostra `value=1` (**um** canal mudo) **antes de 07-15**, virando `value=2` em
  2026-07-15. Esse `1` era o Jana: ele já era o único a disparar o alarme **antes** de o Suporte cair.

**O que é certo:** o canal está *incapaz* de receber (sessão morta, igual ao Suporte).
**O que não é decidível daqui:** se alguém *tentou* falar com ele. Um canal de ~1 msg/dia produz o
mesmo vermelho estando quebrado ou estando ocioso.

**O que falta medir para desempatar:** dado do **lado do daemon** — se a conta WhatsApp recebeu
mensagens que o daemon não repassou. Isso vive no CT 100, que estava fora na janela desta sessão.
Enquanto o CT 100 não voltar, a hipótese "ocioso" é **infalsificável pelo lado da app**, porque
sessão morta não gera tentativa de entrega visível.

## 6. Achados colaterais (que mudam a leitura do painel)

1. **`whatsmeow.webhook.no_channel` (~90/dia, constante nos 74 dias) NÃO é tráfego real descartado.**
   É o **canário do webhook** (`WebhookCanaryCommand`): evento `Presence` sintético que
   propositalmente não casa canal e é ACKado 200 sem escrever no DB. Constância imune ao estado do
   daemon confirma. É exatamente o par que o #2726 ensinou a ler: **canário verde + 8b vermelho**.
   Ler esses ~90/dia como "mensagens perdidas" teria fabricado uma causa-raiz falsa.
2. **`last_health_check_at` congelado (07-16 e 07-08) é ESPERADO, não uma segunda falha.** O probe
   só escreve quando há flip; com `ACTION_NONE` não há UPDATE. Confirmado pelo oráculo de runtime:
   o probe **rodou** às 17:02:34 do dia da medição (`probed:2, flipped:0`), a cada ~5min. O
   timestamp velho não prova cron morto — e quase foi lido assim.
3. **O alarme não falhou: foi ignorado.** Disparou **395 vezes em 59 dias distintos**, desde
   **2026-06-22**. O `Jana` já disparava antes de o `Suporte` cair. O buraco é de **resposta a
   alarme**, não de detecção.
4. **`WHATSMEOW_DAEMON_URL` apontava para o hostname legado `whatsapp-baileys.oimpresso.com`.**
   Medido: resolve para o **mesmo IP** do `whatsapp-whatsmeow`. Não é causal (funcionou até 07-15) —
   mas é confuso e merece renomear.

## 7. Recovery — plano NÃO EXECUTADO

**Nada abaixo foi rodado.** O CT 100 não respondeu (`Dial(...:22)` → dial failure) na janela.
Ordenado do menos destrutivo ao mais. Cada passo diz **o que prova**.

### Pré-condições (bloqueantes)

| # | Verificação | Comando | Critério de parada |
|---|---|---|---|
| P1 | CT 100 responde | `tailscale ssh root@ct100-mcp 'uptime'` | Se falhar, **pare** — todo o resto depende disto |
| P2 | Container de pé | `docker ps --filter name=whatsapp-whatsmeow --format '{{.Status}}'` | Se ausente: `cd /opt/oimpresso/whatsmeow && docker compose up -d` |
| P3 | Volume de sessões íntegro | `ls -la /srv/docker/whatsapp-whatsmeow/sessions/` | Vazio ⇒ sessões perdidas ⇒ **QR novo obrigatório** |
| P4 | Daemon responde | `docker exec whatsapp-whatsmeow wget -q -O - http://localhost:8080/health` | Não-200 ⇒ ver `docker compose logs --tail=200` |

### Passo 1 — observar sem tocar (risco zero)

```bash
curl -H "Authorization: Bearer $(cat /run/secrets/whatsmeow_admin_token)" \
  https://whatsapp-whatsmeow.oimpresso.com/admin/users | jq '.data[] | {name, connected, jid}'
```

Prova: se a sessão do canal existe no daemon e se está `connected`. **Este passo decide os demais.**
`connected=false` com user existindo = `provision_pending`, que foi o estado observado em 2026-07-16.

### Passo 2 — reconciliar o DB com a verdade do daemon (read-mostly)

```bash
php artisan whatsmeow:health-probe --detail
```

Prova: converge `channel_health` ao estado real. Se o daemon voltou e a sessão sobreviveu, o canal
volta a `healthy` sozinho (`ACTION_PAIRED`). **Se isso resolver, pare aqui.**

### Passo 3 — reconectar + reimportar o gap (1 canal por vez)

```bash
php artisan whatsapp:reconnect-and-import --channel=11 --since=2026-07-15 --wait=120 --dry-run
# só depois de conferir o preview, repetir sem --dry-run
```

Prova: restabelece o socket e reimporta o histórico da janela morta. `--dry-run` primeiro,
**sempre**. Começar pelo 11 (Suporte); só depois o 12.

### Passo 4 — re-parear (EXIGE DECISÃO E CELULAR DO [W])

Se o Passo 1 mostrar user inexistente / `logged_out`, ou o Passo 3 estourar o `--wait`, a sessão
precisa de **QR novo**: `/atendimento/canais` → "Conectar" → escanear no celular.

> **Isto não é decisão do agente.** Re-parear exige o aparelho do [W] em mãos, derruba qualquer
> sessão residual, e para o `Jana` a pergunta anterior é **se o canal ainda deve existir** (§8).

### Passo 5 — confirmar por resultado, não por status

```bash
php artisan whatsapp:webhook-canary --json     # a VIA responde 200
php artisan jana:health-check                  # o RESULTADO: whatsapp_inbound_flow verde
```

Critério de sucesso real: **uma mensagem de teste chegando** e `MAX(messages.created_at)` avançando —
`channel_health='healthy'` é proxy de conexão, não prova de recebimento (foi a lição do #2726).

## 8. Em aberto — e para quem

| # | Item | Dono |
|---|---|---|
| A1 | **Rotacionar `WHATSMEOW_API_KEY` e `WHATSMEOW_WEBHOOK_URL_SECRET`.** Ao inspecionar o `.env` desta sessão, a máscara aplicada foi larga demais e os valores apareceram no transcript. Nenhum valor foi escrito neste doc, mas devem ser tratados como comprometidos. | [W] |
| A2 | **O canal `Jana` ainda deve existir?** 40 inbound na vida inteira, mudo desde 07-03. Se aposentado, o conserto é `status` no registro — **não** recovery. Se mantido, precisa de QR novo. | [W] (produto) |
| A3 | Voltar o CT 100 — bloqueia todo o §7 e é pré-requisito de qualquer desempate do §5.2. | [W] / infra |
| A4 | **Lacuna de resposta a alarme:** 395 disparos em 59 dias sem ação. O detector funcionou; o que faltou foi alguém receber. Avaliar destino do ALERT (task no `mcp_tasks`, não só `Log::error`). | [W] |
| A5 | Renomear `WHATSMEOW_DAEMON_URL` para o hostname `whatsapp-whatsmeow` (hoje aponta para o legado `whatsapp-baileys`, mesmo IP). Cosmético, mas induz erro em diagnóstico. | backlog |
| A6 | Atualizar a definição do agente `whatsapp-doctor` (§1): ela ainda descreve Baileys e aponta runbooks de `_archive/`. | backlog |

## 9. Lições

- **"Sem inbound" não é um diagnóstico — é um sintoma com duas causas.** O check 8b é
  driver-agnóstico *de propósito* e não separa "quebrado" de "ocioso". A separação veio da razão
  **inbound×outbound** (4 min ⇒ corte seco; 338h ⇒ decaimento) e do **volume histórico**, não do alarme.
- **Um alarme agregando 2 canais escondeu que eram 2 incidentes distintos**, com datas, mecanismos
  (probe × webhook) e vereditos diferentes. O campo `value` (1→2) foi o que permitiu desempilhar.
- **Contador constante e imune ao estado do sistema é instrumento, não sintoma.** Os ~90/dia de
  `no_channel` eram o próprio canário; lê-los como perda teria produzido causa-raiz falsa.
- **Timestamp congelado em coluna escrita só no flip não mede se o cron roda.** O oráculo foi o log
  de runtime (`health_probe.done` às 17:02), não a coluna.
- Metade do valor desta sessão foi **derrubar** hipóteses plausíveis (Baileys, outage do CT 100,
  deploy da app, `no_channel` como perda, cron morto) — todas mediram falso.

---

*Diagnóstico executado em 2026-09-02 via SSH read-only no Hostinger (Laravel + MySQL prod) e uma
sonda ao CT 100. Nenhuma escrita em produção, nenhuma operação git. Telefones, tokens e conteúdo de
mensagem omitidos por política Tier 0.*
