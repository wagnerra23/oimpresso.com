---
slug: 0126-vault-chunked-encryption-sprint-2
number: 126
title: "Vault chunked encryption Sprint 2 (proposed)"
type: adr
status: proposto
authority: reference
lifecycle: arquivado
decided_by: [W]
decided_at: "2026-05-10"
related: []
pii: false
---
# ADR 0126 — Vault chunked encryption Sprint 2 (proposed)

| Campo       | Valor                                      |
|-------------|--------------------------------------------|
| **Status**  | proposed                                   |
| **Data**    | 2026-05-10                                 |
| **Autores** | Wagner (decisão), Claude Code (draft)      |
| **Refs**    | ADR 0123 §3 (encryption-at-rest), PR #409 (VaultEncryptionService initial), PR #22 (cap 50MB) |

---

## Contexto

`VaultEncryptionService` (PR #409, Sprint 1 dia 4) usa `Crypt::encryptString` (Laravel native,
AES-256-CBC + APP_KEY). A implementação carrega o conteúdo **inteiro** em memória antes de
cifrar — o que implica:

- `file_get_contents` + `Crypt::encryptString` dobra o uso de memória do arquivo (plaintext + ciphertext).
- PHP `memory_limit` padrão é 128–256MB; arquivos >50MB podem disparar OOM.
- O `upload_max_mb=50` em `config/arquivos.php` protege o path de upload via Controller, mas
  chamadas internas (Jobs assíncronos, commands, seeds, API direta sem passar Controller) conseguem
  contornar essa validação e passar conteúdo de qualquer tamanho.

**Cap implementado em PR #22 (Sprint 2):** `VaultEncryptionService` agora valida
`strlen($contents) <= config('arquivos.vault_max_file_size_mb') * 1024 * 1024` antes de
`file_get_contents` e antes de `Crypt::encryptString`. RuntimeException com referência a este ADR
se ultrapassado. Configurável via `.env` (`ARQUIVOS_VAULT_MAX_FILE_SIZE_MB`), não desabilitável.

O cap cobre ~80% do valor com ~5% do esforço de implementação. O problema raiz — arquivos >50MB
legítimos (batch de migração de XMLs grandes, vídeos de OS, etc.) — persiste como débito técnico.

---

## Problema a resolver (Sprint 2+)

Quando um caso de negócio real exigir arquivos vault >50MB (ex: cliente pede upload de XML NFS-e
grande, batch de migração, vídeo de reparo, backup pesado), o cap atual vai rejeitar a operação.
Chunked encryption remove esse limite sem sacrificar a garantia de encryption-at-rest.

---

## Decisão proposta

Implementar **chunked encryption** com formato próprio:

### Formato de arquivo vault v2

```
[ MANIFEST_LEN: 4 bytes big-endian ]
[ MANIFEST: JSON UTF-8 ]
[ CHUNK_0_LEN: 4 bytes big-endian ][ CHUNK_0_CIPHERTEXT ]
[ CHUNK_1_LEN: 4 bytes big-endian ][ CHUNK_1_CIPHERTEXT ]
...
[ CHUNK_N_LEN: 4 bytes big-endian ][ CHUNK_N_CIPHERTEXT ]
```

**Manifest JSON (v1):**
```json
{
  "v": 1,
  "alg": "AES-256-CBC",
  "chunk_size": 1048576,
  "chunks": 12,
  "file_id": "uuid-v4",
  "iv_prefix": "base64-32-bytes-random"
}
```

**IV por chunk:** `HMAC-SHA256(iv_prefix + file_id, chunk_index)` truncado em 16 bytes.
Derivação determinística permite re-encrypt sem reler todo o arquivo pra descobrir IVs.

**Ciphertext por chunk:** `openssl_encrypt($chunk, 'AES-256-CBC', $aes_key, 0, $iv)`
onde `$aes_key` = primeiros 32 bytes da APP_KEY decodificada (mesmo que Crypt::encryptString).

### Novos métodos em VaultEncryptionService

```php
public function putStreamEncrypted(string $disk, string $path, resource $stream, int $chunkSize = 1048576): bool;
public function getStreamDecrypted(string $disk, string $path): \Generator; // yield chunk plaintext
public function getStreamResponse(string $disk, string $path): StreamedResponse; // pra DownloadController
```

### Backward compatibility

- Arquivos v1 (formato Crypt::encryptString) são detectados pelo `isEncrypted()` helper existente.
- `getDecrypted()` e `getStreamDecrypted()` detectam automaticamente o formato pelo header.
- `arquivos:reencrypt-vault` (PR #14) será atualizado pra re-criptografar v1→v2 quando APP_KEY rotacionar.

---

## Alternativas descartadas

| Alternativa | Motivo descarte |
|-------------|-----------------|
| `league/flysystem-encrypted` | Dep nova; transparente mas sem controle de IV por chunk; não resolve OOM |
| Aumentar `memory_limit` PHP | Gambiarra; não escala pra arquivos realmente grandes; Hostinger tem limite fixo |
| S3 Server-Side Encryption | Dep de provider específico; contradiz disk-agnostic (ADR 0123 §2) |
| Sodium `crypto_secretstream` | Libsodium available, mas diferente do APP_KEY scheme atual; migration hard |

---

## Trade-offs e riscos

| Trade-off | Detalhe |
|-----------|---------|
| Complexidade vs cap simples | Cap é 5% do esforço; chunked é ~80h dev + 40h QA estimados |
| Formato custom ≠ Crypt standard | Não transferível pra outro sistema sem lib própria |
| Migration path | Arquivos v1 existentes precisam ser re-encrypted via command ao ativar v2 |
| IV derivation | Determinístico facilita re-encrypt mas exige cuidado: file_id deve ser UUID único por arquivo, nunca reutilizar |

---

## Critérios para ativar esta ADR

Esta decisão está **proposed** — será implementada quando **ao menos um** destes sinais ocorrer:

1. Cliente real (ex: ROTA LIVRE ou ComunicacaoVisual) pede upload >50MB e rejeição causa friction.
2. Job interno (batch migração, backup) precisa escrever arquivo vault >50MB.
3. `VaultEncryptionService::putEncrypted` lança RuntimeException em produção >0 vezes por mês.

Antes de implementar: abrir task Sprint N, linkar este ADR, Wagner aprova scope.

---

## Implementação atual (cap Sprint 2)

Arquivo: `Modules/Arquivos/Services/VaultEncryptionService.php`

- `private const MAX_PLAINTEXT_BYTES = 50 * 1024 * 1024`
- `private function capBytes(): int` — lê `config('arquivos.vault_max_file_size_mb')`, throw se `<=0`
- `putEncrypted()`: valida `strlen($contents) <= capBytes()`, throw RuntimeException referenciando ADR 0126
- `putFileEncrypted()`: valida `$file->getSize() <= capBytes()` antes de `file_get_contents`

Config: `Modules/Arquivos/Config/config.php` → `vault_max_file_size_mb` (env `ARQUIVOS_VAULT_MAX_FILE_SIZE_MB`, default 50).
