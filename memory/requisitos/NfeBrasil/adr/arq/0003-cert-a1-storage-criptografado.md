# ADR ARQ-0003 (NfeBrasil) · Certificado A1 com storage criptografado por business

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ARQ-0001, R-NFE-006, R-NFE-007

## Contexto

Cert A1 (.pfx) é **chave privada digital com valor jurídico**. Vazamento = quem tem o pfx + senha pode emitir NFe em nome do business → fraude fiscal. Multi-tenant SaaS = N businesses com N certs no mesmo storage; um cert vazando expõe os outros se não houver isolamento.

Padrões de storage cogitados:

1. **Plain in storage/** — fácil, INSEGURO. Vaza com qualquer ataque ao filesystem.
2. **Encrypted at rest com chave única do app** — todos os certs cifrados com mesma chave; vazar a chave do app = vaza tudo.
3. **Encrypted with key per business** — chave separada por business, rotacionável; cert + chave estão isolados; vazar 1 expõe só esse cert.
4. **HSM externo (AWS KMS, Azure Key Vault)** — máxima segurança, custo alto, latência adicional.
5. **Cofre dedicado (HashiCorp Vault)** — robusto, infra extra.

## Decisão

**Caminho 3** com possibilidade futura de migrar pra HSM (4) em plano Enterprise.

- Cert .pfx criptografado com `openssl_encrypt(AES-256-CBC)` + chave por business
- Chave por business salva em `nfe_business_keys` (tabela protegida; encrypted at rest com chave do app)
- Senha do cert criptografada via `Laravel encrypt()` (chave do app)
- File system permissões 0600 (só o user da app lê)
- Storage `storage/app/nfe-brasil/{business_id}/cert/{uuid}.pfx.enc` (não `public/`)
- Senha NUNCA em logs (validado em teste R-NFE-007)
- Rotação manual via UI: gerar nova chave business + re-encrypt todos os certs

## Consequências

**Positivas:**
- Vazamento de filesystem por si só não dá acesso aos certs (precisa também das chaves business)
- Vazamento de DB sem filesystem não dá os pfx (precisa do encrypted file)
- Vazamento da chave do app sem o filesystem não dá os pfx
- Rotação de chave business é operação local (sem mexer em todos os tenants)
- Compliance: atende LGPD (dados sensíveis cifrados at rest)

**Negativas:**
- Performance: cada emissão decifra cert (~5ms) — mitigado por cache em memória do worker
- Backup precisa incluir DB + filesystem em snapshot consistente
- Recuperação após perda da chave business = cert tem que ser re-uploaded pelo cliente

## Pattern obrigatório

```php
class CertificadoService {
    public function upload(Business $business, UploadedFile $pfx, string $senha): Certificado {
        $key = BusinessKeyService::ensure($business);  // gera/retorna chave do business

        $rawPfx = file_get_contents($pfx->getRealPath());
        $encryptedPfx = openssl_encrypt($rawPfx, 'AES-256-CBC', $key->derived(), 0, $key->iv());

        $path = "nfe-brasil/{$business->id}/cert/" . Str::uuid() . '.pfx.enc';
        Storage::put($path, $encryptedPfx, ['visibility' => 'private']);

        return Certificado::create([
            'business_id' => $business->id,
            'pfx_path' => $path,
            'senha_encrypted' => encrypt($senha),  // chave do app
            // ... cn, validade extraídos
        ]);
    }

    public function load(Certificado $cert): array {
        $key = BusinessKeyService::for($cert->business);
        $encrypted = Storage::get($cert->pfx_path);
        $pfxBinary = openssl_decrypt($encrypted, 'AES-256-CBC', $key->derived(), 0, $key->iv());
        return ['pfx' => $pfxBinary, 'senha' => decrypt($cert->senha_encrypted)];
    }
}
```

## Tests obrigatórios (R-NFE-006, R-NFE-007)

- `CertStorageEncryptionTest` — pfx no disco ≠ pfx original; só decifra com chave certa
- `CertSenhaNaoLogadaTest` — fazer upload + grep `laravel.log` + grep `activity_log` por senha → 0 hits
- `CertCrossBusinessIsolationTest` — chave business A não decifra cert business B

## Decisão futura: HSM em plano Enterprise

Tenant Enterprise R$ 599 pode ganhar opção de HSM externo:
- Tier extra `enterprise_hsm` em `superadmin_packages`
- Driver alternativo `HsmCertificadoStorage` implementa mesma interface
- Switch via config do business (`cert_storage_driver = local|aws_kms|vault`)

Decidir depois de 50 clientes Enterprise — provavelmente 12-18 meses fora.

## Alternativas consideradas

- **Caminho 1 plain** — rejeitado: insegurança crítica
- **Caminho 2 chave única** — rejeitado: 1 chave vaza → todos os tenants expostos
- **Caminho 4 HSM agora** — rejeitado pra MVP: custo + latência; útil só pra Enterprise futuramente
- **Caminho 5 Vault** — rejeitado pra MVP: infra extra; reavaliar Enterprise

## Referências

- LGPD Art. 46 — segurança de dados pessoais
- R-NFE-006 + R-NFE-007 (SPEC)
- `auto-memória: reference_hostinger_server.md` — produção é shared hosting; isolamento extra-importante
