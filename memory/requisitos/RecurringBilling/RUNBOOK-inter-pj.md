---
title: "RUNBOOK — Inter PJ Banking API v2 (saldo + extrato + PIX cob)"
type: runbook
module: RecurringBilling
created: 2026-05-12
authority: canonical
owner: wagner
last_validated: <WAGNER_FILL: data primeira validação real>
related_adrs: [0030, 0062, 0093, 0094, 0101]
related_us: [US-RB-045, US-RB-046, US-RB-047, US-RB-048]
related_cycle_goals: [CYCLE-05 #1 — Inter PJ Banking em prod com canary 7d]
status: live
charter_version: 2
---

# RUNBOOK — Inter PJ Banking API v2 (saldo + extrato + PIX cob)

> **Quando usar:** validar `InterBankingClient` ponta-a-ponta antes de promover Inter PJ Banking pra prod, ou debugar incidentes (token expirado, webhook não chega, valor errado, divergência de saldo) durante o canary 7d.
>
> **Onde rodar:** `business_id=1` (Wagner WR2 Sistemas — Tubarão/SC). Cross-tenant tests usam `biz=99` (convenção [feedback `test_biz_99_cross_tenant`](../../../C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/feedback_test_biz_99_cross_tenant_convention.md)).
>
> **🚨 NUNCA rodar em `business_id=4`** (RotaLivre/Larissa — 99% do volume real, [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)). Testar Inter em biz=4 contamina extrato/cobranças do cliente piloto.

Origem: gap detectado por skill `module-completeness-audit` em 2026-05-10 (Dim 4 RUNBOOK ausente — escalado a P0 como **US-RB-048**). Materializa Goal #1 do CYCLE-05 ("Inter PJ Banking em prod com canary 7d sem incidente"). Materializa o "como" das US-RB-045 (saldo Fase 1), US-RB-046 (extrato Fase 2), US-RB-047 (PIX cob + webhook Fase 3).

---

## 1. Pré-requisitos checklist

| Item | Onde verificar | Valor esperado |
|---|---|---|
| Cert PJ Inter (`.crt` + `.key` PEM) ativo | Vaultwarden: handle `Inter PJ cert biz=1` | Vencimento `valido_ate` ≥ 30 dias a partir de hoje |
| Aplicação PJ no portal Inter | https://contadigital.bancointer.com.br > Aplicações | Status `Ativa`, produto **Banking** + **PIX Cobrança** habilitados |
| Escopos liberados | Portal Inter > Aplicação > escopos | `extrato.read` (US-RB-045/046), `cob.write` + `cob.read` (US-RB-047) |
| `BoletoCredential` row criada | SQL abaixo | 1 row em `rb_boleto_credentials WHERE banco='inter' AND ativo=1` |
| `config_json` keys obrigatórias | Mesma row | `client_id`, `client_secret`, `certificado_crt_b64`, `certificado_key_b64`, `conta_corrente`, `webhook_secret` |
| Conta-corrente alvo | `config_json.conta_corrente` | <WAGNER_FILL: número da conta sem agência, ex: `123456789-0`> |
| Webhook URL registrada no portal Inter | Portal Inter > PIX > Webhooks (`PUT /webhooks/pix-recebidos`) | `https://oimpresso.com/webhooks/inter/pix/1` |
| Header secret webhook | Portal Inter > Webhook > "Header personalizado" | Nome `X-Inter-Webhook-Secret`, valor = `config_json.webhook_secret` |

> ⚠️ O webhook Inter usa **shared secret** no header `X-Inter-Webhook-Secret`, **NÃO HMAC-SHA256**. Wagner configura o header custom no portal Inter via `PUT /webhooks/pix-recebidos` durante o setup.

Comando consolidado de check (SSH Hostinger):

```bash
# Warm-up Hostinger (sempre antes de SSH — auto-mem reference_hostinger)
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done

ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -o ServerAliveCountMax=200 -o ConnectionAttempts=5 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && \
     mysql -u u906587222_oimpresso -p"$(grep DB_PASSWORD .env | cut -d= -f2 | tr -d \"\\\"\")" \
       u906587222_oimpresso -e "
       SELECT business_id, banco, ambiente, ativo,
              JSON_KEYS(config_json) AS keys_in_config,
              JSON_UNQUOTE(JSON_EXTRACT(config_json, \"$.conta_corrente\")) AS conta
       FROM rb_boleto_credentials
       WHERE banco=\"inter\" AND business_id=1"'
```

**Resultado esperado:**

- 1 row, `ativo=1`, `ambiente='production'` (ou `'sandbox'` para fase inicial)
- `keys_in_config` contém: `["client_id", "client_secret", "certificado_crt_b64", "certificado_key_b64", "conta_corrente", "webhook_secret"]`
- `conta` ≠ NULL e ≠ vazio

Se algum item falha → seguir Seção 2 (Setup credenciais).

---

## 2. Setup credenciais (1ª vez por business)

Credenciais Inter NUNCA em git ([ADR 0030](../../decisions/0030-credenciais-jamais-em-git.md)). Fluxo canônico:

### 2.1 Portal Inter

1. Login https://contadigital.bancointer.com.br (Wagner manual)
2. **Aplicações** → criar app PJ → produto Banking + PIX Cobrança
3. Baixar cert `.crt` + `.key` (PEM) — gerar senha forte aleatória, anotar
4. Anotar `client_id` + `client_secret` (mostrados 1 única vez)
5. **PIX > Webhooks** → registrar `https://oimpresso.com/webhooks/inter/pix/1` + header personalizado `X-Inter-Webhook-Secret: <random_32_bytes>`

### 2.2 Vaultwarden

Criar item secure note `Inter PJ biz=1` com campos:

```
client_id:              <do portal Inter>
client_secret:          <do portal Inter>
conta_corrente:         <WAGNER_FILL>
certificado_crt_b64:    <base64 do .crt>
certificado_key_b64:    <base64 do .key>
webhook_secret:         <random 32 bytes — mesmo valor do header no portal>
valido_ate:             <data vencimento cert>
```

Gerar `webhook_secret` random local:

```powershell
# PowerShell — gera 32 bytes random hex
-join ((48..57) + (97..122) | Get-Random -Count 64 | ForEach-Object {[char]$_})
```

### 2.3 Inserir no DB Hostinger

> ⚠️ Não existe artisan command de setup ainda — INSERT manual via tinker (gap conhecido — criar US futuro `rb:credenciais:setup`).

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && php artisan tinker'
```

No tinker:

```php
use Illuminate\Support\Facades\Crypt;
use Modules\RecurringBilling\Models\BoletoCredential;

// Wagner cola valores Vaultwarden manualmente (NUNCA via chat Claude)
BoletoCredential::create([
    'business_id'      => 1,
    'banco'            => 'inter',
    'ambiente'         => 'production', // ou 'sandbox'
    'ativo'            => true,
    'nome_display'     => 'Inter PJ — biz=1 Wagner',
    'config_json'      => [
        'client_id'           => '<COLAR>',
        'client_secret'       => Crypt::encryptString('<COLAR>'),
        'conta_corrente'      => '<COLAR>',
        'certificado_crt_b64' => '<COLAR base64>',
        'certificado_key_b64' => Crypt::encryptString('<COLAR base64>'),
        'webhook_secret'      => '<COLAR>',
    ],
]);
```

⚠️ **NUNCA colar cert/secret/conta em chat Claude** ([feedback `nunca_publicar_credenciais_no_chat`](../../../C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/feedback_nunca_publicar_credenciais_no_chat.md)). Wagner cola direto no tinker via clipboard local.

---

## 3. Smoke 1 — saldo via `getSaldo()` (US-RB-045)

Valida OAuth `extrato.read` + cert mTLS + parse de `disponivel/bloqueado/limite`.

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && \
     php artisan tinker --execute="
       use Modules\\RecurringBilling\\Services\\Banking\\InterBankingClient;
       use Modules\\RecurringBilling\\Models\\BoletoCredential;
       use Illuminate\\Support\\Facades\\Crypt;

       \$cred = BoletoCredential::where(\"business_id\", 1)->where(\"banco\", \"inter\")->firstOrFail();
       \$cfg = \$cred->config_json;
       foreach ([\"client_secret\", \"certificado_key_b64\"] as \$f) {
           \$cfg[\$f] = Crypt::decryptString(\$cfg[\$f]);
       }

       \$client = new InterBankingClient(\$cfg, 1);
       print_r(\$client->getSaldo());
     "'
```

**Esperado:**
```
Array
(
    [disponivel] => 1234.56
    [bloqueado] => 0.0
    [limite] => 5000.0
)
```

Onde no código: [Modules/RecurringBilling/Services/Banking/InterBankingClient.php:41](../../../Modules/RecurringBilling/Services/Banking/InterBankingClient.php) método `getSaldo()` chama `GET /banking/v2/saldo` com `Authorization: Bearer <token>` + header `x-conta-corrente` + cert mTLS.

cURL direto pra isolar bug Laravel (executar local, com cert PEM extraído do Vaultwarden em `/tmp/`):

```bash
# 1. OAuth client_credentials com mTLS
TOKEN=$(curl -s --cert /tmp/inter_crt.pem --key /tmp/inter_key.pem \
  -X POST 'https://cdpj.partners.bancointer.com.br/oauth/v2/token' \
  -d 'client_id=<CID>&client_secret=<CSEC>&grant_type=client_credentials&scope=extrato.read' \
  | jq -r '.access_token')

# 2. Saldo
curl -s --cert /tmp/inter_crt.pem --key /tmp/inter_key.pem \
  -H "Authorization: Bearer $TOKEN" \
  -H "x-conta-corrente: <CONTA>" \
  'https://cdpj.partners.bancointer.com.br/banking/v2/saldo' | jq
```

---

## 4. Smoke 2 — extrato D-7 via `getExtrato()` (US-RB-046)

Valida endpoint `/banking/v2/extrato/completo` + paginação (100 itens/pg, cap 10pg).

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && \
     php artisan tinker --execute="
       use Modules\\RecurringBilling\\Services\\Banking\\InterBankingClient;
       use Modules\\RecurringBilling\\Models\\BoletoCredential;
       use Illuminate\\Support\\Facades\\Crypt;
       use Carbon\\Carbon;

       \$cred = BoletoCredential::where(\"business_id\", 1)->where(\"banco\", \"inter\")->firstOrFail();
       \$cfg = \$cred->config_json;
       \$cfg[\"certificado_key_b64\"] = Crypt::decryptString(\$cfg[\"certificado_key_b64\"]);
       \$cfg[\"client_secret\"]       = Crypt::decryptString(\$cfg[\"client_secret\"]);

       \$client = new InterBankingClient(\$cfg, 1);
       \$tx = \$client->getExtrato(Carbon::now()->subDays(7), Carbon::now());
       echo count(\$tx) . \" transações nos últimos 7d\\n\";
       echo json_encode(array_slice(\$tx, 0, 2), JSON_PRETTY_PRINT);
     "'
```

**Esperado:**
- Saída: `N transações nos últimos 7d` (N ≥ 0; tipicamente ≥1 se conta movimenta — fee mensal + entradas PIX)
- Cada transação tem: `dataEntrada`, `tipoTransacao`, `tipoOperacao`, `valor`, `titulo`, `descricao`, `idTransacao`
- Re-rodar 2x: contagem igual (paginação determinística)

**Comando job real** (sincrônico — popula `fin_contas_bancarias.saldo_cached`):

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && \
     php artisan rb:sync-bank-balances --sync'
```

> Comando real: [Modules/RecurringBilling/Console/Commands/SyncBankBalancesCommand.php](../../../Modules/RecurringBilling/Console/Commands/SyncBankBalancesCommand.php). Schedule **hourly** (NÃO daily) em `app/Console/Kernel.php:361`. Com `--sync` roda agora sem fila.

---

## 5. Smoke 3 — PIX cob imediata + QR code (US-RB-047)

Valida `PUT /cobranca/v3/cob/{txid}` (cob.write) + `GET /cobranca/v3/cob/{txid}/qrcode` (cob.read). NÃO envolve recebimento — só geração. Recebimento real é o smoke webhook (Seção 6).

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && \
     php artisan tinker --execute="
       use Modules\\RecurringBilling\\Services\\Banking\\InterBankingClient;
       use Modules\\RecurringBilling\\Services\\Banking\\Drivers\\InterPixCobDriver;
       use Modules\\RecurringBilling\\Models\\BoletoCredential;
       use Illuminate\\Support\\Facades\\Crypt;
       use Illuminate\\Support\\Str;

       \$cred = BoletoCredential::where(\"business_id\", 1)->where(\"banco\", \"inter\")->firstOrFail();
       \$cfg = \$cred->config_json;
       foreach ([\"client_secret\", \"certificado_key_b64\"] as \$f) { \$cfg[\$f] = Crypt::decryptString(\$cfg[\$f]); }

       \$client = new InterBankingClient(\$cfg, 1);
       \$driver = new InterPixCobDriver(\$client, \"<WAGNER_FILL: chave PIX da conta>\");
       \$txid   = Str::random(26);

       \$result = \$driver->criarCobImediata(\$txid, 1.00, [], \"Smoke RUNBOOK Inter PJ\", 3600);
       echo \"txid: {\$result->txid}\\nstatus: {\$result->status}\\ncopia-e-cola: \" . substr(\$result->pixCopiaECola, 0, 80) . \"...\\nqrcode_base64_size: \" . strlen(\$result->qrcodeBase64 ?? \"\") . \"\\n\";
     "'
```

**Esperado:**
- `status: ATIVA`
- `pixCopiaECola` começa com `00020126...` (EMV BR Code)
- `qrcode_base64_size` > 0 (PNG base64 não-vazio)
- Em ~2s pode pagar o QR pelo PIX no celular (R$ [redacted Tier 0]) pra disparar o webhook (Smoke 4)

Onde no código: [Modules/RecurringBilling/Services/Banking/Drivers/InterPixCobDriver.php](../../../Modules/RecurringBilling/Services/Banking/Drivers/InterPixCobDriver.php) — `criarCobImediata()` retorna `PixCobResult` DTO.

---

## 6. Smoke 4 — webhook receiver (US-RB-047)

Endpoint: `POST /webhooks/inter/pix/{businessId}` ([Modules/RecurringBilling/Routes/web.php:40](../../../Modules/RecurringBilling/Routes/web.php), [InterWebhookController.php:32](../../../Modules/RecurringBilling/Http/Controllers/InterWebhookController.php)).

Fluxo real:
1. Smoke 3 gera cob imediata R$ [redacted Tier 0] + retorna `pixCopiaECola`
2. Pagar pelo celular (PIX → "Pix copia e cola") usando outra conta
3. Em até 30s o Inter dispara `POST /webhooks/inter/pix/1` com:
   - Header `X-Inter-Webhook-Secret: <webhook_secret>` (shared secret, NÃO HMAC)
   - Body `{ "pix": [{ "endToEndId": "E18...", "txid": "...", "valor": "1.00", "horario": "2026-05-12T14:32:18Z", ... }] }`
4. Controller valida `hash_equals(expected, provided)` → se OK, insere em `pg_webhook_events` + dispatch `ProcessInterWebhookJob` na queue `rb_webhooks`
5. Job atualiza `rb_invoices.status='paid'` (se `txid` mapeia invoice) + cria `account_transactions` credit + dispara `InvoicePaid` event (NfeBrasil escuta pra emitir NFe55)

Verificar resultado:

```sql
-- Webhook recebido + idempotência
SELECT id, provider, event_id, event_type, processed, created_at
FROM pg_webhook_events
WHERE business_id=1 AND provider='inter'
ORDER BY id DESC LIMIT 3;

-- Invoice marcada paid (se txid bateu com gateway_ref)
SELECT id, business_id, gateway_ref, status, updated_at
FROM rb_invoices
WHERE business_id=1 AND gateway_ref='<TXID_SMOKE_3>';

-- Saldo conta Inter incrementado
SELECT id, banco_codigo, saldo_cached, updated_at
FROM fin_contas_bancarias
WHERE business_id=1 AND banco_codigo='077';
```

**Secret inválido (falha esperada — testar):**

```bash
curl -X POST https://oimpresso.com/webhooks/inter/pix/1 \
  -H 'Content-Type: application/json' \
  -H 'X-Inter-Webhook-Secret: fake-secret-errado' \
  -d '{"pix":[{"endToEndId":"E18-test","txid":"abc","valor":"1.00"}]}'
# Esperado: HTTP 401, body {"ok":false,"reason":"secret_mismatch"}
```

Log: `storage/logs/laravel.log` filtrar `InterWebhookController.reject` → registra `business_id`, `reason`, `body=[REDACTED]`.

---

## 7. Debug — token expirado / 401 em `/oauth/v2/token`

Sintoma: `RequestException 401` no log, `InterBankingClient.oauth failed`.

Causas comuns + ação:

| Causa | Como confirmar | Ação |
|---|---|---|
| `client_id`/`client_secret` errados | Comparar com Vaultwarden | Re-criar `BoletoCredential` (Seção 2.3) |
| Cert PJ vencido | Vaultwarden item: campo `valido_ate` | Renovar no portal Inter → atualizar Vaultwarden + DB |
| Escopo solicitado sem habilitação | Portal Inter > Aplicação > escopos | Pedir habilitação produto (1-2 dias úteis) |
| Cert mTLS corrompido em `/tmp` | `ls -la /tmp/inter_*.pem` (deve ter 0600) | Force regravação: `rm /tmp/inter_*.pem` (próxima call regrava) |
| Cache `inter:token:1:<sha1(scope)>` com token velho | Improvável (TTL 50min) | Forçar refresh (comando abaixo) |

Forçar refresh do cache de token:

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && \
     php artisan tinker --execute="
       use Illuminate\\Support\\Facades\\Cache;
       foreach ([\"extrato.read\", \"cob.write\", \"cob.read\"] as \$scope) {
           Cache::forget(\"inter:token:1:\" . sha1(\$scope));
       }
       echo \"Cache de tokens Inter limpo para biz=1\\n\";
     "'
```

Cache key pattern: `inter:token:{businessId}:sha1({scope})` ([InterBankingClient.php:180](../../../Modules/RecurringBilling/Services/Banking/InterBankingClient.php)).

---

## 8. Debug — webhook não chega

Sintoma: pagou PIX no celular, mas 0 row em `pg_webhook_events`, `rb_invoices.status` segue `pending`.

Checklist:

| # | Verificação | Comando/onde |
|---|---|---|
| 1 | Webhook URL registrada no portal Inter | Portal Inter > PIX > Webhooks (deve aparecer `oimpresso.com/webhooks/inter/pix/1`) |
| 2 | URL responde 200 do externo | `curl -I https://oimpresso.com/webhooks/inter/pix/1` (espera 405 GET — endpoint só POST, mas DNS+TLS OK) |
| 3 | Header `X-Inter-Webhook-Secret` configurado no portal | Portal Inter > Webhook > "Header personalizado" |
| 4 | Secret no portal == `config_json.webhook_secret` no DB | Comparar Vaultwarden vs portal Inter |
| 5 | Hostinger sem firewall bloqueando IP Inter | `tail -f storage/logs/laravel.log` enquanto paga PIX — ver se request chega |
| 6 | `ProcessInterWebhookJob` rodando (queue `rb_webhooks`) | `php artisan queue:work rb_webhooks --once` |
| 7 | Smoke webhook manual com cURL (Seção 6) bate 200 ok | Indica que controller funciona — problema é entrega Inter |

**IPs Inter (whitelist se Hostinger ativar firewall):** Inter não publica lista oficial — chamar suporte Inter PJ pra obter range atual. Documentar em ADR quando descobrir.

Forçar reprocessamento de webhook stuck:

```sql
-- Marca event como não-processado (job vai re-disparar no próximo work)
UPDATE pg_webhook_events SET processed=false WHERE id=<EVENT_ID>;
```

```bash
# Worker pega
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && php artisan queue:work rb_webhooks --once'
```

---

## 9. Erros comuns + fix

| Sintoma | Causa provável | Ação |
|---|---|---|
| `getSaldo()` retorna `disponivel=0` mas conta tem saldo | `x-conta-corrente` errado | Conferir `config_json.conta_corrente` vs titular do app no portal Inter |
| `RequestException 403` em `/banking/v2/saldo` | App PJ sem produto Banking (só PIX habilitado) | Portal Inter > Aplicação > habilitar Banking |
| `RequestException 422` em PIX cob | Chave PIX inválida ou `valor.original` errado | `valor.original` deve ser **string** `"1.00"` (não float `1.0`) — driver já converte com `number_format` |
| `RequestException 404` em `/cobranca/v3/cob/{txid}/qrcode` | txid nunca existiu (cob falhou antes) | Confirmar `criarCobImediata` retornou status `ATIVA` — checar log `InterBankingClient.criarCobImediata failed` |
| Job daily falha sem log | `Crypt::decryptString` lança em `certificado_key_b64` malformado | Re-criar credential (Seção 2.3) — fix recorrente |
| Token sumiu antes de 50min | Cache backend (redis/file) reiniciou | OK — próximo call refaz token (custo: 1 OAuth extra) |
| `cdpj.partners.bancointer.com.br` 5xx intermitente | Inter degradação | https://status.bancointer.com.br — pausar canary se >1h |
| `MessageException ssl certificate problem` | Cert `.crt` ou `.key` truncado em base64 | Re-extrair PEM completo do Vaultwarden — `openssl x509 -in /tmp/inter_crt.pem -text -noout` valida |
| Webhook chega mas job falha "ContaBancaria not found" | Sem row em `fin_contas_bancarias WHERE banco_codigo='077' AND business_id=1` | Criar conta Inter no Financeiro UI antes do canary |

---

## 10. Sandbox vs prod

> ⚠️ **GAP — VERIFICAR COM WAGNER:** Inter Banking API v2 documentação Inter sugere **mesma URL** (`cdpj.partners.bancointer.com.br`), com app marcado sandbox/prod no portal. `BASE_URL` está hardcoded em [InterBankingClient.php:28](../../../Modules/RecurringBilling/Services/Banking/InterBankingClient.php). Se Inter exigir URL separada (`cdpj-sandbox.*`), criar **US-RB-050** antes do canary pra parametrizar.

Hipótese atual (a confirmar no portal Inter):
- **Sandbox:** app marcado sandbox no portal → cert dele → token só funciona em endpoints sandbox
- **Prod:** app marcado prod → cert dele → token só funciona em prod
- Código **NÃO muda**; só credenciais diferentes em Vaultwarden + `ambiente='sandbox'` ou `'production'` no `BoletoCredential`

Coluna `BoletoCredential.ambiente` já existe ([Models/BoletoCredential.php:18](../../../Modules/RecurringBilling/Models/BoletoCredential.php)) — usar pra desambiguar.

---

## 11. Rollback — valor errado / PIX recebido por engano

⚠️ **PIX recebidos NÃO podem ser "deletados".** Caminhos legítimos:

| Cenário | Caminho |
|---|---|
| Cliente pagou por engano | MED (Mecanismo Especial de Devolução) Bacen — devolução voluntária pelo portal Inter (até 80 dias) |
| Valor errado a maior | Devolver parcial via PIX manual no portal Inter; documentar via session log |
| Cob duplicada (smoke gerou QR redundante) | Sem ação — cob expira após `calendario.expiracao` segundos automaticamente |
| Webhook falso (secret inválido) | Já barrado no controller (`secret_mismatch` → 401 sem processar) |
| `rb_invoices` marcada `paid` errada | UPDATE manual: `UPDATE rb_invoices SET status='pending' WHERE id=X AND business_id=1` + nota session log |

**Desabilitar Inter temporariamente sem perder credenciais:**

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && \
     mysql -u u906587222_oimpresso -p"$(grep DB_PASSWORD .env | cut -d= -f2 | tr -d \"\\\"\")" \
       u906587222_oimpresso -e "
       UPDATE rb_boleto_credentials SET ativo=0
       WHERE business_id=1 AND banco=\"inter\""'
```

Efeitos:
- `SyncBankBalancesJob` pula contas Inter (não falha — só não atualiza `saldo_cached`)
- Webhook `POST /webhooks/inter/pix/1` retorna 404 `credential_not_found`
- Re-habilitar: `UPDATE rb_boleto_credentials SET ativo=1 WHERE ...`

**Refund Asaas-style não se aplica** — Inter NÃO tem endpoint de refund automático via API; tudo manual no portal/MED.

---

## 12. Monitoramento (canary 7d)

Durante 7 dias após habilitar Inter PJ em `business_id=1`:

| Métrica | Alvo | Onde medir |
|---|---|---|
| `fin_contas_bancarias.saldo_cached` da conta Inter atualizado | a cada hora (schedule `rb:sync-bank-balances` hourly) | `SELECT updated_at FROM fin_contas_bancarias WHERE banco_codigo='077' AND business_id=1` |
| `InterBankingClient.oauth failed` no log | 0 ocorrências | `grep -c "oauth failed" storage/logs/laravel.log` |
| `InterBankingClient.saldo failed` no log | ≤2 ocorrências (margem latência) | `grep -c "saldo failed" storage/logs/laravel.log` |
| `InterBankingClient.criarCobImediata failed` | 0 ocorrências (se gerou cob no canary) | `grep -c "criarCobImediata failed" storage/logs/laravel.log` |
| `InterWebhookController.reject reason=secret_mismatch` | 0 ocorrências | `grep "InterWebhookController.reject" storage/logs/laravel.log` |
| Diff saldo Banking API vs portal Inter | <R$ [redacted Tier 0] | Wagner abre portal Inter 1×/dia + compara `saldo_cached` |
| Custo OAuth tokens emitidos | ≤24/dia (3 scopes × 1 token/50min × 24h, mas reuso aggressivo cache) | `grep -c "POST /oauth/v2/token" storage/logs/laravel.log` |
| `pg_webhook_events` recebidos esperados | =1 por PIX recebido (sem dups) | `SELECT COUNT(*), provider FROM pg_webhook_events WHERE business_id=1 GROUP BY provider` |

**Schedule conferido:** `rb:sync-bank-balances` é **hourly** (NÃO daily) — `app/Console/Kernel.php:361`. Se conta Inter `saldo_cached` ficar >2h sem update, alerta.

Logs Hostinger:
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && \
     tail -100 storage/logs/laravel.log | grep -E "Inter(BankingClient|WebhookController|PixCobDriver)"'
```

---

## 13. Escalation — quando ligar pro Inter PJ

Cenários que exigem contato direto Inter (Wagner liga, não Claude):

| Sintoma | Razão |
|---|---|
| Cert vence em <7 dias e portal não permite renovar self-service | Inter precisa liberar |
| Escopo `cob.write` ou `extrato.read` solicitado >2 dias atrás sem habilitação | Atendimento PJ acionar |
| 5xx persistente em `cdpj.partners.bancointer.com.br` >1h sem nada em status.bancointer | Pode ser issue específico do app/conta |
| MED (devolução PIX) precisa ser disputado | Operacional Inter, não API |
| Whitelist de IP Hostinger (se Inter ativar) | Documentar IP atual: `curl -s https://api.ipify.org` rodado no Hostinger |

**Contato:** <WAGNER_FILL: telefone/email gerente PJ Inter da conta Wagner>. Documentar em Vaultwarden item `Inter PJ contato comercial`.

---

## Vinculação com governance

- **Goal CYCLE-05 #1** (Inter PJ Banking em prod com canary 7d sem incidente) — este RUNBOOK materializa o "como"
- **US-RB-045** (Inter PJ saldo Banking API v2 Fase 1) — Smoke 1 valida `InterBankingClient::getSaldo()` em prod
- **US-RB-046** (Inter PJ extrato sync Fase 2) — Smoke 2 valida `InterBankingClient::getExtrato()` + schedule `rb:sync-bank-balances` hourly
- **US-RB-047** (Inter PJ PIX cob + webhook Fase 3) — Smokes 3+4 validam `InterPixCobDriver` + `InterWebhookController` + `ProcessInterWebhookJob`
- **US-RB-048** (este RUNBOOK) — Dim 4 governance ([skill `module-completeness-audit`](../../../.claude/skills/module-completeness-audit/SKILL.md))
- **[ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)** Multi-tenant Tier 0 — RUNBOOK respeita `business_id=1` scope; NUNCA biz=4
- **[ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)** — biz=1 default smoke, biz=99 cross-tenant
- **[ADR 0030](../../decisions/0030-credenciais-jamais-em-git.md)** — cert + secrets em Vaultwarden; RUNBOOK usa `<WAGNER_FILL>` + Vaultwarden refs
- **[ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)** — RUNBOOK roda APP em Hostinger; smokes via SSH
- **[ADR 0094 §5](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)** SoC brutal — `InterBankingClient` (banking) separado de `InterDriver` (boleto), com drivers próprios pra extrato (`InterStatementDriver`) e PIX cob (`InterPixCobDriver`)

---

## Próximo passo após smoke OK

Quando Smokes 1+2+3+4 ✅ em `business_id=1`:

1. **Trocar frontmatter `last_validated: 2026-MM-DD`** + manter `status: live`
2. **Promover Goal #1 do CYCLE-05** parcialmente: `cycle-goals-track goal_id:1 status:doing achieved_value:"4 smokes OK biz=1 2026-MM-DD — canary 7d iniciado"`
3. **Session log** `memory/sessions/2026-MM-DD-inter-pj-smoke-canary.md` com saldo retornado (sem PII), txid PIX criado, webhook recebido
4. **Marcar US-RB-048 done** via `tasks-update task_id:US-RB-048 status:done`
5. **NÃO habilitar Inter em outros businesses** (especialmente biz=4 RotaLivre) sem 7d canary verde + aprovação Wagner explícita
6. Ao final dos 7d → Goal #1 status `done` + ADR de aceitação `NNNN-inter-pj-banking-prod-validado.md` documentando os 7+ critérios canary atingidos

---

## Lacunas (`<WAGNER_FILL>` + GAPs detectados)

Antes de promover este RUNBOOK pra `last_validated: <data>`, Wagner precisa preencher/decidir:

1. `<WAGNER_FILL>` em `last_validated` (frontmatter) — após primeira execução real
2. `<WAGNER_FILL>` na Seção 1 — número da `conta_corrente` Inter PJ alvo
3. `<WAGNER_FILL>` na Seção 5 — chave PIX da conta-corrente (CPF/CNPJ/email/aleatória)
4. `<WAGNER_FILL>` na Seção 13 — contato gerente PJ Inter

**GAPs detectados (não-bloqueantes mas valem US futuras):**

- **GAP-1:** Não existe artisan command `rb:credenciais:setup` (Seção 2.3 manual via tinker). Criar US `php artisan rb:credenciais:setup --banco=inter --business=N` interativo (lê stdin, criptografa, persiste). P2 (operacional, não bloqueia canary).
- **GAP-2:** `InterBankingClient::BASE_URL` hardcoded (Seção 10). Se Inter exigir URL sandbox separada, criar **US-RB-050** antes do canary. P1 se aplicável.
- **GAP-3:** Inter não publica IPs whitelist oficial (Seção 8 #5). Se Hostinger ativar firewall, documentar IP atual em ADR.
- **GAP-4:** Não há endpoint interno `/api/internal/inter/saldo` pra debug local sem SSH (mencionado em drafts antigos). Se virar necessidade recorrente, criar Controller admin protegido.
- **GAP-5:** Refund/MED é manual no portal Inter — não há automação API (Seção 11). `RefundCobrancaInterJob` análogo ao Asaas (`ASAAS_REFUND_ENABLED` flag) não faz sentido até Inter publicar endpoint refund.

---

**Última revisão:** 2026-05-12 (US-RB-048 — RUNBOOK criado por agent Wave 4 cruzando draft prévio + código real `InterBankingClient`/`InterPixCobDriver`/`InterWebhookController`/`ProcessInterWebhookJob`).
