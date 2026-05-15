# inter-credentials — Cadastro Inter PJ sem expor credenciais

Ferramenta pra inserir credenciais Inter PJ em `rb_boleto_credentials` no Hostinger **sem que Claude (ou logs ou git) veja os valores**.

## Por que existe

Cadastrar credenciais Inter exige Crypt::encryptString (Laravel) e Modules\RecurringBilling\Models\BoletoCredential. Caminhos:
- ❌ Cola no chat Claude → viola [user_privacy](../../CLAUDE.md) (financial data) + ADR 0030
- ❌ SSH manual + tinker → Wagner cola em terminal aberto, funciona mas demora e log fica
- ✅ **Esta ferramenta** → você cola num JSON local gitignored, Python encaminha via SCP, PHP remoto encripta+insere, /tmp limpo no fim

## Uso

### 1. Copia template
```bash
cd scripts/inter-credentials
cp credentials.example.json credentials.local.json
```

### 2. Edita `credentials.local.json` com valores do Vaultwarden

Campos obrigatórios:
- `client_id` — do portal Inter
- `client_secret` — do portal Inter
- `conta_corrente` — formato `12345678-9`
- `webhook_secret` — 32 bytes random (mesmo do header `X-Inter-Webhook-Secret` no portal Inter)
- `certificado_crt_path` ou `certificado_crt_b64` — cert PJ Inter
- `certificado_key_path` ou `certificado_key_b64` — key PJ Inter

Gerar webhook_secret random (PowerShell):
```powershell
-join ((48..57) + (97..122) | Get-Random -Count 64 | ForEach-Object {[char]$_})
```

### 3. Dry-run pra conferir fingerprints
```bash
python install-biz.py --business-id 1 --credentials credentials.local.json
```

Output mostra SHA-256[:12] de cada campo — confere visualmente com Vaultwarden pra garantir que pasteou certo.

### 4. Apply
```bash
python install-biz.py --business-id 1 --credentials credentials.local.json --apply
```

Output esperado:
```
✅ OK:id=1 biz=1 banco=inter ambiente=production
```

### 5. (Opcional) Shred local pós-apply
```bash
python install-biz.py --business-id 1 --credentials credentials.local.json --apply --shred
```

`--shred` sobrescreve o JSON com bytes random + deleta. **Não é 100% em SSD** (firmware copy-on-write pode deixar vestígio), mas reduz superfície.

## Garantias

- 🔒 Claude **nunca vê os valores** — JSON é local, SCP direto Wagner→Hostinger, eu só vejo fingerprints
- 🔒 `.local.json`, `.crt`, `.key`, `.pem` **gitignored** (ver `.gitignore`)
- 🔒 Tier 0 multi-tenant — `--business-id` aceita só 1 (Wagner WR2) ou 4 (ROTA LIVRE)
- 🔒 Idempotente — refuse se já existe registro pra `(business_id, banco=inter)`. Pra atualizar, UPDATE manual via tinker
- 🔒 Cleanup robusto — `/tmp/inter-creds-<uuid>.json` deletado mesmo em erro (try/finally Python + finally PHP)
- 🔒 Validação local antes do SCP — base64 válido, cert ≤ 32KB, JSON ≤ 64KB
- 🔒 SSH via `BatchMode=yes` — sem prompt, falha rápido se key não funciona

## ADR 0101 — biz=4 produção real

Cadastrar em **biz=4** (ROTA LIVRE) significa que a próxima cobrança gerada via Inter cobra cliente real. Sequência recomendada:

1. `--business-id 1` (Wagner WR2 — smoke seguro)
2. Validar com `RUNBOOK-inter-pj.md` §3 (saldo) + §4 (extrato) + §5 (PIX cob)
3. Canary 7d em biz=1
4. **Só então** `--business-id 4` pra ROTA LIVRE

## Troubleshooting

| Erro | Causa | Fix |
|---|---|---|
| `ERR_NO_FILE` | INTER_JSON env var vazia ou path errado | Não deveria acontecer — bug no Python orquestrador |
| `ERR_BAD_JSON` | JSON malformado | `python -m json.tool credentials.local.json` |
| `ERR_NO_VENDOR` | Laravel sem composer install | SSH e roda `composer install --no-dev` no public_html |
| `ERR_ALREADY_EXISTS:id=N` | Já tem credencial pra esse biz+banco | Pra trocar, UPDATE manual via tinker remoto |
| `ERR_INVALID_BIZ` | --business-id ≠ 1/4 | Use 1 ou 4 |
| `❌ SSH falhou` | Key id_ed25519_oimpresso ausente ou senha errada | Confere `ls ~/.ssh/id_ed25519_oimpresso` |
| Timeout SCP | Rede com Hostinger instável | Retry; warm-up com `curl https://oimpresso.com/login` antes |

## Pra remover credencial (caso teste)

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd domains/oimpresso.com/public_html && php artisan tinker --execute="
   \Modules\RecurringBilling\Models\BoletoCredential::where(\"business_id\", 1)->where(\"banco\", \"inter\")->delete();
   echo \"Deleted\n\";
  "'
```

## Refs

- RUNBOOK canônico: `memory/requisitos/RecurringBilling/RUNBOOK-inter-pj.md`
- Modelo: `Modules/RecurringBilling/Models/BoletoCredential.php`
- Cliente Banking: `Modules/RecurringBilling/Services/Banking/InterBankingClient.php`
- ADR 0030 — credenciais jamais em git
- ADR 0093 — multi-tenant Tier 0
- ADR 0101 — biz=4 nunca pra testes (smoke em biz=1)
