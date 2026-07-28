---
id: requisitos-recurring-billing-adr-tech-0007-encryption-pattern-credenciais-boleto
---

# ADR TECH-0007 (RecurringBilling) · Encryption pattern de credenciais boleto

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Categoria**: tech
- **Supersedes**: nenhum
- **Relacionado**: TECH-0004 (drivers plugáveis), TECH-0005 (cert separado)

## Contexto

`rb_boleto_credentials.config_json` armazena dados sensíveis (`client_secret`, `api_key`, chave privada `.key`) e dados não-sensíveis (`client_id`, certificado público `.crt`, `conta_corrente`, `cnpj_beneficiario`).

Inconsistência descoberta no PR #101 quebrou emissão Inter:
- Controller salvou `certificado_key_b64 = base64_encode(Crypt::encryptString($v))` (base64 da string criptografada)
- `BoletoService::decryptConfig()` decifrava só `['client_secret', 'api_key', 'certificado_senha']`, **não** `certificado_key_b64`
- `InterDriver` fazia `base64_decode($config['certificado_key_b64'])` direto → bytes criptografados ilegíveis

## Decisão

**Padrão único de encryption por sensibilidade do campo:**

| Campo | Sensível? | Storage | Decrypt |
|---|---|---|---|
| `client_id` | não | plain | plain |
| `client_secret` | sim | `Crypt::encryptString($v)` | `Crypt::decryptString` |
| `api_key` | sim | `Crypt::encryptString($v)` | `Crypt::decryptString` |
| `certificado_senha` | sim | `Crypt::encryptString($v)` | `Crypt::decryptString` |
| `certificado_crt_b64` | não (público) | `base64_encode($pem)` | `base64_decode` no driver |
| `certificado_key_b64` | sim (chave privada) | `Crypt::encryptString(base64_encode($pem))` | `Crypt::decryptString` no service → `base64_decode` no driver |
| `conta_corrente` | não | plain | plain |
| `cnpj_beneficiario` | não | plain | plain |

**Regra do `BoletoService::decryptConfig()`:** loop sobre todos os campos sensíveis, incluindo `certificado_key_b64`. Após `decryptString`, a chave volta a ser base64 — driver consome com `base64_decode` igual ao `_crt_b64`.

**Regra invariante:** chave privada **NUNCA** é armazenada em plain ou só base64. Sempre `Crypt::encryptString(base64_encode($pem))`.

## Consequências

- **Positivas:**
  - Chave privada criptografada em rest (LGPD + boa prática segurança)
  - Pattern uniforme entre Inter (.key) e Asaas (api_key) — `decryptConfig` cobre todos
  - Cert público (.crt) não polui o decrypt loop — performance OK

- **Negativas:**
  - Migrações de credenciais antigas (caso existam) precisam re-encryption
  - Operador que rotacionar `APP_KEY` precisa re-encriptar todos os campos sensíveis (mesmo problema dos secrets do Laravel em geral)

## Implementação

`Modules/Financeiro/Http/Controllers/ContaBancariaController.php::saveGatewayCredential()`:
```php
if ($v = $request->input('gateway_certificado_key')) {
    $config['certificado_key_b64'] = Crypt::encryptString(base64_encode($v));
}
if ($v = $request->input('gateway_client_secret')) {
    $config['client_secret'] = Crypt::encryptString($v);
}
```

`Modules/RecurringBilling/Services/Boleto/BoletoService.php::decryptConfig()`:
```php
foreach (['client_secret', 'api_key', 'certificado_senha', 'certificado_key_b64'] as $field) {
    if (isset($config[$field])) {
        $config[$field] = Crypt::decryptString($config[$field]);
    }
}
```

## Validação

- [ ] Wagner consegue salvar credencial Inter sandbox via UI
- [ ] `BoletoService::driver(business_id)` retorna `InterDriver` sem erro
- [ ] `InterDriver::api()` consegue ler arquivos temp sem MalformedPemException
- [ ] Teste Pest cobrindo round-trip (encrypt → decrypt → match)

## Referências

- PR #101 + PR #102 (oimpresso.com)
- Sessão 2026-05-06 (Opus reanalisou e identificou bug)
