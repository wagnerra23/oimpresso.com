---
id: requisitos-whatsapp-runbooks-archive-baileys-troubleshoot-ban
---

> ⚠️ **ARQUIVADO 2026-05-27** — BaileysDriver descontinuado por [ADR 0202](../../../../decisions/0202-whatsapp-profissionalizacao-baileys-out.md).
> Conteúdo preservado como lição histórica. **NÃO aplicar em produção.**

# RUNBOOK · whatsapp-baileys daemon — Recuperação de ban Meta

> **Decisão mãe:** [ADR 0096 emenda 4](../../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
> **Arquitetura:** [ARCHITECTURE.md §16.10](../ARCHITECTURE.md) (riscos)
> **Severidade:** P1 (cliente sem WhatsApp) → P0 se cross-tenant ≥ 3 em 24h.

## 0. Como você foi paged?

Triggers possíveis (ordem de gravidade):

| Sinal | Severidade | Resposta |
|---|---|---|
| `whatsapp_baileys_ban_detected_total` ≥ 3 em 24h **cross-tenant** | **P0** | Pular pra §6 (cross-tenant alarm) |
| 1 business com `driver_health=banned` em `whatsapp_business_configs` | P1 | Seguir §1–§5 |
| Cliente reclamou "WhatsApp parou" + último `last_health_message` contém `banned`/`logged_out` | P1 | Seguir §1–§5 |
| Webhook event `ban_detected` recebido na Hostinger | P1 | Seguir §1–§5 |

## 1. Confirmar diagnóstico (5 min)

```bash
# 1.1 — Hostinger: estado do business afetado
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 '
  cd ~/oimpresso.com
  php artisan tinker --execute="
    \$c = Modules\Whatsapp\Entities\WhatsappBusinessConfig::withoutGlobalScope(
      Modules\Jana\Scopes\ScopeByBusiness::class
    )->where(\"business_id\", BIZ_ID)->first();
    echo json_encode([
      \"driver\" => \$c->driver,
      \"health\" => \$c->driver_health,
      \"failures\" => \$c->driver_health_consecutive_failures,
      \"last_msg\" => \$c->last_health_message,
      \"last_check\" => \$c->last_health_check_at,
    ], JSON_PRETTY_PRINT);
  "'

# 1.2 — CT 100: estado da instance no daemon
tailscale ssh root@ct100-mcp '
  curl -fsS http://127.0.0.1:3000/health | jq ".instances[] | select(.id==\"bizBIZ_ID-main\")"
'

# 1.3 — Logs daemon últimas 500 linhas filtrando ban
tailscale ssh root@ct100-mcp '
  docker logs whatsapp-baileys --tail 500 2>&1 \
    | grep -iE "ban|forbidden|loggedOut|multidevice" \
    | tail -40
'
```

**Decisão:**
- `state: banned`, `ban_reason: logged_out` → ban Meta confirmado, ir pra §2
- `state: banned`, `ban_reason: multidevice_mismatch` → mesma resposta §2
- `state: disconnected` mas `last_health_message` cita `banned` → daemon ainda não atualizou; aguardar próximo health-check ou reproduzir manualmente: `curl -X GET http://127.0.0.1:3000/instances/bizID-main/status`

## 2. Mitigação imediata — fallback Meta Cloud já está ativo? (2 min)

`DriverFactory::make()` aplica fallback automático em runtime quando `driver_health != healthy`. Confirmar:

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 '
  cd ~/oimpresso.com
  php artisan tinker --execute="
    \$c = Modules\Whatsapp\Entities\WhatsappBusinessConfig::withoutGlobalScope(
      Modules\Jana\Scopes\ScopeByBusiness::class
    )->where(\"business_id\", BIZ_ID)->first();
    echo \"effective driver = \" . \$c->effectiveDriver() . PHP_EOL;
    echo \"meta configured  = \" . (\$c->hasMetaCloudConfigured() ? \"yes\" : \"NO\") . PHP_EOL;
  "'
```

- `effective driver = meta_cloud` → ✅ cliente já está enviando via Meta Cloud, sem perda. Seguir pra §3.
- `effective driver = baileys` (apesar de `driver_health=banned`) → bug; investigar `WhatsappBusinessConfig::effectiveDriver()`. Workaround: `php artisan tinker` → `$c->driver_health = 'banned'; $c->save();`
- `meta configured = NO` → **CRISE**: business violou gating Tier 0 (FormRequest deveria ter bloqueado). Pular pra §5 (notificar cliente, sem WhatsApp até cadastrar Meta).

## 3. Notificar cliente do business afetado (5 min)

Template (PT-BR):

> Olá [nome do admin business],
>
> Detectamos que sua conexão WhatsApp via Baileys foi bloqueada pela Meta. Já ativamos automaticamente seu canal de fallback (Meta Cloud) — suas mensagens **continuam saindo normalmente**, agora pelo número WhatsApp Business oficial.
>
> Próximos passos:
> 1. **Recomendado:** continuar usando Meta Cloud (canal oficial, sem risco de bloqueio).
> 2. **Alternativa:** parear novo número WhatsApp ao Baileys (instruções em anexo). Usar SOMENTE chip dedicado, nunca o número pessoal.
>
> Avise quando puder agendar 30 min para a recuperação.
>
> – oimpresso

Enviar via:
- E-mail (`mail` queue Laravel) com `business.contact_email`
- Mensagem WhatsApp via Meta Cloud (já que está funcionando) pra `business.mobile`

## 4. Reset / recuperação (variantes)

### 4.1 — Cliente quer manter o mesmo número e tentar de novo

> ⚠️ **NÃO recomendado.** Meta detecta padrão. Risco alto de re-ban.

Se insistir:

```bash
# Apagar auth state da instance no daemon
tailscale ssh root@ct100-mcp '
  curl -X DELETE http://127.0.0.1:3000/instances/bizBIZ_ID-main \
       -H "Authorization: Bearer $(cat /run/secrets/whatsapp_baileys_api_key)"
'
# Confirmar pasta limpa
tailscale ssh root@ct100-mcp 'ls /srv/docker/whatsapp-baileys/sessions/'
```

Cliente reabre `/whatsapp/settings`, vai aparecer QR Code novo, escaneia. Reset `driver_health`:

```sql
-- Hostinger MySQL via tinker
UPDATE whatsapp_business_configs
SET driver_health = 'never_checked',
    driver_health_consecutive_failures = 0,
    last_health_message = NULL
WHERE business_id = BIZ_ID;
```

### 4.2 — Cliente quer trocar para chip novo (recomendado)

1. Cliente ativa novo chip WhatsApp Business no celular dedicado.
2. Cliente acessa `/whatsapp/settings`, troca `baileys_instance_id` para `bizBIZ_ID-v2` (sufixo nova versão).
3. Backend cria nova instance no daemon: `POST /instances/bizBIZ_ID-v2/connect`.
4. QR Code novo, escaneia.
5. Apagar instance antiga (após confirmar nova funcionando):
   ```bash
   tailscale ssh root@ct100-mcp '
     curl -X DELETE http://127.0.0.1:3000/instances/bizBIZ_ID-main \
          -H "Authorization: Bearer $(cat /run/secrets/whatsapp_baileys_api_key)"
   '
   ```

### 4.3 — Cliente quer abandonar Baileys e ficar só Meta Cloud

Recomendado para quem foi banido. UI Settings → toggle "Forçar Meta Cloud como driver primário":

```sql
UPDATE whatsapp_business_configs
SET driver = 'meta_cloud',
    driver_health = 'never_checked'
WHERE business_id = BIZ_ID;
```

Limpar instance órfã no daemon (§4.1 comando DELETE).

## 5. Se Meta Cloud NÃO está cadastrado (violação gating)

Cenário improvável (FormRequest deveria ter bloqueado), mas se acontecer:

1. Cliente fica **sem WhatsApp até cadastrar Meta Cloud** (1-3 dias aprovação Meta).
2. Notificar cliente urgência. Templates Meta (HSM) levam 24h-72h aprovação.
3. Considerar Z-API temporário se cliente já tinha conta Z-API antes (config existente preservada — mas mesmo risco de ban).
4. Abrir post-mortem: como o gating foi violado? Ler `BusinessSettingsRequest::withValidator()` e ver se `bypass_business_ids` foi usado indevidamente para este `business_id`.

## 6. Cross-tenant alarm (P0) — ≥ 3 bans em 24h diferentes businesses

Sinal forte de que **Meta detectou padrão no IP do CT 100** (não no número específico).

### 6.1 — Avaliar escala

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 '
  cd ~/oimpresso.com
  php artisan tinker --execute="
    echo Modules\Whatsapp\Entities\WhatsappBusinessConfig::withoutGlobalScopes()
      ->where(\"driver_health\", \"banned\")
      ->where(\"updated_at\", \">=\", now()->subDay())
      ->count() . \" businesses banidos em 24h\" . PHP_EOL;
  "'
```

### 6.2 — Decisões críticas (chamar Wagner)

- **Pausar onboarding novo Baileys** imediatamente (UI Settings desabilita 3ª opção):
  ```bash
  # Hostinger .env
  WHATSAPP_BAILEYS_TEMPORARILY_DISABLED=true
  # (a wired logic em BusinessSettingsRequest precisa checar — adicionar guard se ainda não tem)
  ```
- **Migrar businesses ativos pra Meta Cloud** preventivamente (ver §4.3).
- **Avaliar rotação de IP do CT 100** (talvez não-trivial — coordenar com infra Proxmox).
- **Avaliar pausa total do daemon** até pattern-detection Meta esfriar (24-72h).

### 6.3 — Trigger ADR

Conforme [ADR 0096 §16.11](../../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md):
> Se `whatsapp_baileys_ban_detected_total` cross-tenant ≥ 5/mês sustained → reabrir ADR pra avaliar SaaS BSP enterprise (Take Blip / Twilio).

3 em 24h é o **early warning**. 5/mês sustained é o **trigger de ADR**. Documentar em `memory/sessions/YYYY-MM-DD-baileys-ban-postmortem.md` em ambos os casos.

## 7. Post-mortem obrigatório

Após estabilizar, criar `memory/sessions/YYYY-MM-DD-baileys-ban-recovery-bizID.md`:

- Quando começou (`first_ban_at` no log do daemon)
- Quanto tempo levou pra detectar (alarme funcionou?)
- Quanto tempo até cliente notificado
- Cliente perdeu mensagens? (ver `whatsapp_messages` direction=outbound, status=queued, business_id=BIZ_ID, dia do incidente)
- Ação tomada (4.1 / 4.2 / 4.3 / §6)
- O que melhorar (alarme Grafana, runbook, comunicação)

## 8. Checklist resposta

- [ ] §1 Diagnóstico confirmado (`driver_health=banned` + log daemon)
- [ ] §2 Fallback Meta Cloud ativo verificado (`effectiveDriver = meta_cloud`)
- [ ] §3 Cliente notificado (e-mail + WhatsApp Meta)
- [ ] §4 Recuperação executada (4.1, 4.2 ou 4.3)
- [ ] §6 Cross-tenant alarm? Wagner avisado se ≥ 3 em 24h
- [ ] §7 Post-mortem criado em `memory/sessions/`

## Apêndices

### A. Como Meta detecta ban

Sinais que disparam (não exaustivo):
- Volume alto + spam reports (relatos clientes finais)
- Padrões automáticos suspeitos (mesma mensagem para muitos números)
- Device fingerprint Whatsapp Web fora do normal
- Multiple-device mismatch (a sessão do daemon "briga" com o app oficial do cliente)

Mitigações que reduzem (não eliminam):
- Random jitter entre sends (já no Baileys default)
- Não enviar mais que 60 msg/min por instance
- Sempre dar resposta humana eventualmente (não 100% automatizado)
- Não reusar mesmo número que já foi banido em outro daemon

### B. Métricas Grafana de referência

- `whatsapp_baileys_session_state` cair de 1 → 0 e ficar
- `whatsapp_baileys_ban_detected_total` incrementar
- `whatsapp_baileys_send_total{status="failed"}` spike

### C. Referências
- [baileys-daemon-deploy-ct100.md](baileys-daemon-deploy-ct100.md)
- [baileys-upgrade-lib.md](baileys-upgrade-lib.md)
- [ARCHITECTURE.md §16.10](../ARCHITECTURE.md) (riscos formais)
