---
title: "RUNBOOK — Inter PJ Banking API v2 (saldo + extrato + PIX cob)"
type: runbook
owner: wagner
last_validated: <WAGNER_FILL: data primeira validação real>
related_adrs: [0030, 0062, 0093, 0094]
related_us: [US-RB-045, US-RB-046, US-RB-047, US-RB-048]
related_cycle_goals: [CYCLE-05 #1 — Inter PJ Banking em prod com canary 7d]
status: draft
charter_version: 1
---

# RUNBOOK — Inter PJ Banking API v2 (saldo + extrato + PIX cob)

> **Quando usar:** validar `InterBankingClient` ponta-a-ponta antes de promover Inter PJ pra prod, ou debugar incidentes (token, webhook, valor errado) durante o canary 7d.
>
> **Onde rodar:** `business_id=<WAGNER_FILL: 1 (Wagner) ou outro biz controlado>` — **NUNCA biz=4** (RotaLivre cliente, ADR 0101 + [feedback memory `test_biz_99_cross_tenant`](../../../C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/feedback_test_biz_99_cross_tenant_convention.md)).
>
> **Status:** `draft` enquanto Wagner não preencher os `<WAGNER_FILL>` + 1ª validação real. Após smoke ok → `live`.

Origem: gap detectado por skill `module-completeness-audit` em 2026-05-10 (Dim 4 RUNBOOK — escalado a P0 como US-RB-048). Materializa Goal #1 do CYCLE-05 ("Inter PJ Banking em prod com canary 7d sem incidente").

---

## Pré-requisitos checklist

| Item | Onde verificar | Valor esperado |
|---|---|---|
| Cert PJ Inter ativo | Vaultwarden: <WAGNER_FILL: handle item Vaultwarden, ex: "Inter PJ cert biz=1"> | Vencimento ≥ 30d a partir de hoje |
| `BoletoCredential` row | `mysql … SELECT * FROM rb_boleto_credentials WHERE business_id=<biz> AND banco='inter'` | 1 row, `config_json` contém `client_id`, `client_secret`, `certificado_crt_b64`, `certificado_key_b64`, `conta_corrente`, `secret_webhook` |
| Escopos liberados no portal Inter | <WAGNER_FILL: print/screenshot portal Inter > "Aplicações" > escopos> | `extrato.read` ✓ (US-RB-045/046), `cob.write` + `cob.read` ✓ (US-RB-047) |
| Conta-corrente alvo | `config_json.conta_corrente` | <WAGNER_FILL: conta de teste/canary, sem prefixo agência> |
| Webhook URL registrada | <WAGNER_FILL: portal Inter > Webhooks> | `https://oimpresso.com/webhooks/inter/pix/<business_id>` |
| `secret_webhook` registrado | Mesma row `config_json.secret_webhook` | string ≥32 chars |

Comando consolidado de check:

```bash
ssh -4 -o ConnectTimeout=900 -i ~/.ssh/id_ed25519_oimpresso -p 65002 \
    u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && \
     mysql -u u906587222_oimpresso -p"$(grep DB_PASSWORD .env | cut -d= -f2 | tr -d \"\\\"\")" \
       u906587222_oimpresso -e "
       SELECT business_id, banco,
              JSON_KEYS(config_json) AS keys_in_config,
              JSON_UNQUOTE(JSON_EXTRACT(config_json, \"$.conta_corrente\")) AS conta
       FROM rb_boleto_credentials
       WHERE banco=\"inter\""'
```

Se algum item falha → restaurar via `php artisan rb:credenciais:setup --banco=inter --business=<biz>` (comando interno, consulta [`Modules/RecurringBilling/Console/Commands/SetupCredencialBoleto.php`](../../../Modules/RecurringBilling/Console/Commands/) — <WAGNER_FILL: confirmar nome real do command>).

---

## Setup credenciais (1ª vez por business)

Credenciais Inter nunca em git. Fluxo canônico ([ADR 0030](../../decisions/0030-credenciais-jamais-em-git.md)):

1. **Portal Inter** → "Aplicações" → criar app PJ → baixar cert `.crt` + `.key` (PEM)
2. **Vaultwarden** → criar item secure note `Inter PJ <biz=N>` colando: `client_id`, `client_secret`, base64 do crt+key, `conta_corrente`, `secret_webhook` (gerado random 32 bytes)
3. **Hostinger** → rodar:
   ```bash
   ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@<HOSTINGER_IP> \
       'cd domains/oimpresso.com/public_html && \
        php artisan rb:credenciais:setup --banco=inter --business=<N>'
   ```
   (comando interativo — pede colar cada campo, criptografa com `Crypt::encryptString`, persiste em `rb_boleto_credentials.config_json`)
4. **Smoke saldo** (passo seguinte) — confirma cert + OAuth ponta-a-ponta antes de seguir

⚠️ **Nunca colar cert ou senha no chat do Claude** ([feedback memory `nunca_publicar_credenciais_no_chat`](../../../C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/feedback_nunca_publicar_credenciais_no_chat.md)). Usar Vaultwarden ref + clipboard.

---

## Smoke 1 — saldo via `getSaldo()`

Valida OAuth `extrato.read` + mTLS + parse de `disponivel/bloqueado/limite`.

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && \
     php artisan tinker --execute="
       use Modules\\RecurringBilling\\Services\\Banking\\InterBankingClient;
       use Modules\\RecurringBilling\\Models\\BoletoCredential;
       use Illuminate\\Support\\Facades\\Crypt;

       \$cred = BoletoCredential::where(\"business_id\", <BIZ>)->where(\"banco\", \"inter\")->firstOrFail();
       \$cfg = \$cred->config_json;
       foreach ([\"client_secret\", \"certificado_key_b64\"] as \$f) {
           \$cfg[\$f] = Crypt::decryptString(\$cfg[\$f]);
       }

       \$client = new InterBankingClient(\$cfg, <BIZ>);
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

Equivalente PowerShell pra debug local sem Hostinger:

```powershell
# Pré-requisito: rodar 'php artisan tinker' local apontando .env.testing com cert sandbox
# (Hostinger é canônico pra smoke prod; local só pra investigação rápida)
$body = @{ businessId = <BIZ> } | ConvertTo-Json
Invoke-RestMethod -Uri 'http://oimpresso.test/api/internal/inter/saldo' `
                  -Method POST -Body $body -ContentType 'application/json'
```
(endpoint interno protegido por middleware admin — <WAGNER_FILL: confirmar se existe ou se rota é só artisan>)

---

## Smoke 2 — extrato D-7 via `getExtrato()`

Valida endpoint `/banking/v2/extrato/completo` + paginação + idempotência `idempotency_key`.

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && \
     php artisan tinker --execute="
       use Modules\\RecurringBilling\\Services\\Banking\\InterBankingClient;
       use Modules\\RecurringBilling\\Models\\BoletoCredential;
       use Illuminate\\Support\\Facades\\Crypt;
       use Carbon\\Carbon;

       \$cred = BoletoCredential::where(\"business_id\", <BIZ>)->where(\"banco\", \"inter\")->firstOrFail();
       \$cfg = \$cred->config_json;
       \$cfg[\"certificado_key_b64\"] = Crypt::decryptString(\$cfg[\"certificado_key_b64\"]);
       \$cfg[\"client_secret\"] = Crypt::decryptString(\$cfg[\"client_secret\"]);

       \$client = new InterBankingClient(\$cfg, <BIZ>);
       \$tx = \$client->getExtrato(Carbon::now()->subDays(7), Carbon::now());
       echo count(\$tx) . \" transações nos últimos 7d\\n\";
       echo json_encode(array_slice(\$tx, 0, 2), JSON_PRETTY_PRINT);
     "'
```

**Esperado:**
- ≥1 transação se conta movimenta (saída de fee mensal, entrada PIX, etc)
- Cada transação tem: `dataEntrada`, `tipoTransacao`, `tipoOperacao`, `valor`, `titulo`, `descricao`, `idTransacao`
- Re-rodar 2x: contagem igual, sem dups em `fin_extrato_lancamentos` (após US-RB-046 mergeada)

cURL direto (sem app — útil pra isolar bug do Laravel):

```bash
# 1. Obtém token OAuth
TOKEN=$(curl -s --cert /tmp/inter_crt.pem --key /tmp/inter_key.pem \
  -X POST 'https://cdpj.partners.bancointer.com.br/oauth/v2/token' \
  -d 'client_id=<CID>&client_secret=<CSEC>&grant_type=client_credentials&scope=extrato.read' \
  | jq -r '.access_token')

# 2. Extrato D-7
curl -s --cert /tmp/inter_crt.pem --key /tmp/inter_key.pem \
  -H "Authorization: Bearer $TOKEN" \
  -H "x-conta-corrente: <CONTA>" \
  "https://cdpj.partners.bancointer.com.br/banking/v2/extrato/completo?dataInicio=$(date -d '7 days ago' +%Y-%m-%d)&dataFim=$(date +%Y-%m-%d)&paginacao=0&itensPorPagina=100" \
  | jq '.transacoes | length, .[0]'
```

---

## Smoke 3 — PIX cob imediata + QR code

Valida `PUT /cobranca/v3/cob/{txid}` + GET qrcode. **Não envolve recebimento** — só geração. Recebimento real é o smoke webhook (próximo passo).

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && \
     php artisan tinker --execute="
       use Modules\\RecurringBilling\\Services\\Banking\\InterBankingClient;
       use Modules\\RecurringBilling\\Models\\BoletoCredential;
       use Illuminate\\Support\\Facades\\Crypt;
       use Illuminate\\Support\\Str;

       \$cred = BoletoCredential::where(\"business_id\", <BIZ>)->where(\"banco\", \"inter\")->firstOrFail();
       \$cfg = \$cred->config_json;
       foreach ([\"client_secret\", \"certificado_key_b64\"] as \$f) { \$cfg[\$f] = Crypt::decryptString(\$cfg[\$f]); }

       \$client = new InterBankingClient(\$cfg, <BIZ>);
       \$txid = Str::random(26);

       \$cob = \$client->criarCobImediata(\$txid, [
           \"calendario\" => [\"expiracao\" => 3600],
           \"valor\"      => [\"original\" => \"1.00\"],
           \"chave\"      => \"<WAGNER_FILL: chave PIX da conta-corrente>\",
           \"solicitacaoPagador\" => \"Smoke RUNBOOK Inter PJ\",
       ]);

       echo \"txid: \$txid\\nstatus: \" . (\$cob[\"status\"] ?? \"?\") . \"\\ncopia-e-cola: \" . substr(\$cob[\"pixCopiaECola\"] ?? \"\", 0, 80) . \"...\\n\";
     "'
```

**Esperado:**
- `status: ATIVA`
- `pixCopiaECola` começa com `00020126...` (EMV BR Code)
- Em ~2s pode pagar o QR pelo PIX no celular (R$ 1,00) pra disparar o webhook (próximo passo)

---

## Debug — token expirado / 401 em `/oauth/v2/token`

Sintoma: `RequestException 401` no log, `InterBankingClient.oauth failed`.

Causas comuns + ação:

| Causa | Como confirmar | Ação |
|---|---|---|
| `client_id`/`client_secret` errados | Comparar com Vaultwarden | Re-rodar `rb:credenciais:setup` |
| Cert PJ vencido | Vaultwarden item: campo `valido_ate` | Renovar no portal Inter → atualizar Vaultwarden + DB |
| Escopo solicitado sem habilitação | Portal Inter > Aplicação > escopos | Pedir habilitação (geralmente 1 dia útil) |
| IP origem bloqueado | `Log::warning` registra `status:401` | Adicionar IP Hostinger no whitelist do portal (se Inter exigir) |

Forçar refresh de cache do token (caso suspeite de token cacheado inválido):

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && \
     php artisan tinker --execute="
       use Illuminate\\Support\\Facades\\Cache;
       Cache::forget(\"inter:token:<BIZ>:\" . sha1(\"extrato.read\"));
       Cache::forget(\"inter:token:<BIZ>:\" . sha1(\"cob.write\"));
       Cache::forget(\"inter:token:<BIZ>:\" . sha1(\"cob.read\"));
       echo \"Cache de token Inter limpo para biz <BIZ>\\n\";
     "'
```

---

## Debug — 401 em `/banking/v2/saldo` ou `/extrato/completo`

Diferente do 401 OAuth (acima) — aqui o token foi aceito mas o request específico foi negado. `InterBankingClient.saldo failed` no log, body sempre `[REDACTED]` (ADR 0094 §7 PII).

Causas:

- Cert mTLS não-correspondente ao app (cert do app A, token do app B) → comparar `client_id` no token decodado vs app que emitiu cert
- `x-conta-corrente` divergente da conta do app → `config_json.conta_corrente` ≠ conta titular do app no portal
- Token tem escopo errado (ex: token `cob.write` chamando `/saldo` que exige `extrato.read`) → `oauthToken(scope)` deve estar sendo chamado com escopo correto

Como inspecionar o token cacheado (sem expor secret):

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && \
     php artisan tinker --execute="
       use Illuminate\\Support\\Facades\\Cache;
       \$tk = Cache::get(\"inter:token:<BIZ>:\" . sha1(\"extrato.read\"));
       \$parts = explode(\".\", \$tk);
       echo \"Header: \" . base64_decode(\$parts[0]) . \"\\nClaims: \" . base64_decode(\$parts[1]) . \"\\n\";
     "'
```

---

## Sandbox vs prod

<WAGNER_FILL: Inter Banking API v2 tem URL sandbox separada (`cdpj-sandbox.partners.bancointer.com.br`?) OU usa mesma URL com cert/app marcado como sandbox no portal?>

Cenário A — URL separada (precisaria parametrizar `InterBankingClient::BASE_URL`):
- Mudar `BASE_URL` pra constante condicional baseada em `$config['ambiente']`
- AC de mudança: skill `multi-tenant-patterns` Tier A + Pest cobre os 2 paths

Cenário B — mesma URL, app marcado:
- Sandbox = app sandbox no portal → cert dele → token só funciona em sandbox endpoints
- Prod = app prod → cert dele → token só funciona em prod
- Nada muda no código; só credenciais diferentes em Vaultwarden

Setting atual no código ([InterBankingClient.php:28](../../../Modules/RecurringBilling/Services/Banking/InterBankingClient.php:28)): `BASE_URL = 'https://cdpj.partners.bancointer.com.br'` hardcoded. Se cenário A se aplica, criar US-RB-050 antes do canary.

---

## Webhook receiver — HMAC + idempotência

Endpoint: `POST /webhooks/inter/pix/{business_id}` (após US-RB-047 mergeada).

Smoke webhook real:
1. Smoke 3 acima gera cob imediato R$ 1,00 + retorna `pixCopiaECola`
2. Pagar pelo celular (PIX → "Pix copia e cola")
3. Em até 30s o Inter dispara `POST /webhooks/inter/pix/<BIZ>` com payload `{ pix: [{ endToEndId, txid, valor, ... }] }` + header `x-inter-signature: HMAC-SHA256`
4. Verificar tabela `pg_webhook_events` (após US-RB-047):
   ```sql
   SELECT id, gateway, event_type, idempotency_key, processed_at
   FROM pg_webhook_events
   WHERE business_id=<BIZ> AND gateway='inter'
   ORDER BY id DESC LIMIT 3
   ```
5. Confirmar event `InvoicePaid` disparado no log Laravel (`storage/logs/laravel.log` filtrar `InvoicePaid`)

**HMAC inválido (signature do header não bate com `secret_webhook`):**
- 401 retornado IMEDIATAMENTE (antes de qualquer processamento — Tier 0 segurança)
- Loga `Webhook Inter inválido HMAC business=<BIZ>` (sem body, sem signature — só fato)
- Action: reverificar `config_json.secret_webhook` igual ao que está no portal Inter > Webhooks

---

## Rollback — valor errado / PIX recebido por engano

⚠️ **PIX recebidos NÃO podem ser "deletados".** Caminhos legítimos:

| Cenário | Caminho |
|---|---|
| Cliente pagou por engano | MED (Mecanismo Especial de Devolução) do Bacen — devolução voluntária pelo portal Inter |
| Valor errado (a maior) | Devolver parcial via PIX manual no portal Inter; documentar via session log |
| Cob duplicada (smoke gerou QR redundante) | Sem ação — cob expira após `calendario.expiracao` segundos automaticamente |
| Webhook falso (HMAC inválido) | Já barrado no receiver — 401 sem processar |

Pra desabilitar Inter temporariamente sem perder credenciais:

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && \
     mysql -u u906587222_oimpresso -p"$(grep DB_PASSWORD .env | cut -d= -f2 | tr -d \"\\\"\")" \
       u906587222_oimpresso -e "
       UPDATE rb_boleto_credentials SET ativo=0
       WHERE business_id=<BIZ> AND banco=\"inter\""'
```

`SyncBankBalancesJob` passa a retornar `null` pra contas inter (job não falha, só pula).

---

## Canary 7d — métricas + critério go/no-go

<WAGNER_FILL: confirmar/ajustar critérios abaixo. Default proposto:>

Durante 7 dias após habilitar Inter PJ em `business_id=<BIZ>`:

| Métrica | Alvo | Onde medir |
|---|---|---|
| `fin_contas_bancarias.saldo_cached` da conta Inter atualizado | 7/7 dias úteis (job daily 06:00 BRT) | `SELECT updated_at FROM fin_contas_bancarias WHERE banco='inter'` |
| `InterBankingClient.oauth failed` no log | 0 ocorrências | `grep -c "oauth failed" storage/logs/laravel.log` |
| `InterBankingClient.saldo failed` no log | ≤2 ocorrências (margem latência) | idem |
| Webhook HMAC inválido | 0 ocorrências | log filtrado `Webhook Inter inválido HMAC` |
| Diff saldo Banking API vs portal Inter (verificação manual) | <R$ 0,01 | Wagner abre portal Inter 1×/dia |
| Custo OAuth requests | ≤24/dia (1 token por scope, cache 50min, 3 scopes) | `Http::sent` total no log filtered |

**Go-live em outros businesses** após 7d todos verdes + Wagner aprovar via session log.

**Rollback canary:** se qualquer alvo falhar 2 dias consecutivos → rodar comando "desabilitar Inter temporariamente" (seção rollback acima) + ADR pós-mortem.

---

## Possíveis erros + diagnóstico

| Sintoma | Causa provável | Ação |
|---|---|---|
| `getSaldo()` retorna `disponivel=0` mas conta tem saldo no app Inter | Wrong `x-conta-corrente` header | Conferir `config_json.conta_corrente` vs titular do app |
| `RequestException 403` em `/banking/v2/saldo` | App PJ sem permissão Banking (só Cobrança habilitada) | Portal Inter > Aplicação > habilitar produto Banking |
| `RequestException 422` em PIX cob | Chave PIX inválida ou valor com formato errado | `valor.original` precisa ser string `"1.00"` não number `1.0` |
| `RequestException 404` em `/cobranca/v3/cob/{txid}/qrcode` | txid não existe (cob nunca criada) | Confirmar `criarCobImediata` retornou status `ATIVA` antes |
| Job daily falha sem log | `Crypt::decryptString` lança em `certificado_key_b64` malformado | Re-criptografar via `rb:credenciais:setup` — fix histórico do PR #331 |
| Token sumiu antes de 50min | Cache backend `redis` reiniciou | OK — próximo call refaz token (custo: 1 OAuth extra) |
| `cdpj.partners.bancointer.com.br` 5xx intermitente | Inter degradação | Verificar [status.bancointer.com.br](https://status.bancointer.com.br) — pausar canary se >1h |

---

## Vinculação com governance

- **Goal CYCLE-05 #1** (Inter PJ Banking em prod com canary 7d sem incidente) — este runbook materializa o "como"
- **US-RB-045** (saldo Banking API v2 Fase 1) — runbook smoke 1 valida o cliente em prod
- **US-RB-046** (extrato sync Fase 2) — runbook smoke 2 valida driver de extrato
- **US-RB-047** (PIX cob + webhook Fase 3) — runbook smokes 3 + webhook validam fluxo PIX
- **[ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)** Multi-tenant Tier 0 — runbook respeita `business_id` scope; NUNCA biz=4
- **[ADR 0030](../../decisions/0030-credenciais-jamais-em-git.md)** — cert + secrets em Vaultwarden; runbook usa `<WAGNER_FILL>` + Vaultwarden refs
- **[ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)** — runbook roda APP em Hostinger; cron daily 06:00 BRT em `app/Console/Kernel.php`
- **[ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §5 SoC** — `InterBankingClient` separado de `InterDriver` (boleto): banking ≠ boleto

---

## Próximo passo após smoke OK

Quando os 3 smokes (saldo + extrato + PIX cob) + webhook ✅ em `<BIZ>`:

1. **Trocar frontmatter `status: draft → live`** + preencher `last_validated: 2026-MM-DD`
2. **Promover Goal #1 do CYCLE-05** parcialmente: `cycle-goals-track goal_id:1 status:doing achieved_value:"3 smokes OK biz=<N> 2026-MM-DD — canary 7d iniciado"`
3. **Session log** `memory/sessions/2026-MM-DD-inter-pj-smoke-canary.md` com saldo retornado, txid PIX criado, webhook recebido
4. **NÃO habilitar Inter em outros businesses** sem 7 dias de canary verde + aprovação Wagner explícita
5. Ao final dos 7d → goal status `done` + ADR de aceitação `NNNN-inter-pj-banking-prod-validado.md` documentando os 6 critérios canary atingidos

---

## Lacunas (`<WAGNER_FILL>`)

Antes de promover este RUNBOOK pra `status: live`, Wagner precisa preencher:

1. `<WAGNER_FILL>` em `last_validated` (frontmatter)
2. `<WAGNER_FILL>` em "Onde rodar" — qual `business_id` será o canary (proposta: biz=1 Wagner)
3. `<WAGNER_FILL>` na tabela pré-requisitos — handles Vaultwarden + escopos confirmados + conta-corrente alvo
4. `<WAGNER_FILL>` em "Setup credenciais" — confirmar nome real do `php artisan rb:credenciais:setup` (ou alternativa)
5. `<WAGNER_FILL>` em smoke 1 PowerShell — endpoint interno `/api/internal/inter/saldo` existe ou só artisan?
6. `<WAGNER_FILL>` em smoke 3 — chave PIX da conta-corrente alvo
7. `<WAGNER_FILL>` em "Sandbox vs prod" — qual cenário se aplica (URL separada vs app marcado)
8. `<WAGNER_FILL>` em "Canary 7d" — critérios go/no-go finais (proposta da tabela serve?)
