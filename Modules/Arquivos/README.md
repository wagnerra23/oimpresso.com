# Modules/Arquivos — DMS backbone do oimpresso

> **ADR mãe:** [0123](../../memory/decisions/0123-modules-arquivos-backbone.md)
> **SPEC:** [memory/requisitos/Arquivos/SPEC.md](../../memory/requisitos/Arquivos/SPEC.md)
> **Princípio:** *todo arquivo anexado do oimpresso deve cair aqui*.

## Por que existe

Sem um backbone DMS, cada módulo (Repair, NfeBrasil, Financeiro, Ticket, Jana) reinventa upload + dedupe + signed URL + encryption-at-rest. Drift mata governança LGPD.

`Modules/Arquivos` centraliza:

- **Storage isolado por bucket** (`public`, `internal`, `sensitive`, `vault`) com policy uniforme
- **Dedupe SHA256** cross-modulo dentro do mesmo `business_id` (cap de custo)
- **Signed URLs** com expiração curta (default 1h)
- **Encryption-at-rest** automática pra bucket `sensitive`/`vault`
- **Audit log append-only** (`arquivos_audit_log`) — quem fez upload/download/reclassify/purge
- **Política de retenção LGPD** declarada (`Config/retention.php` operacional + shim D7.c)

## Arquitetura — 3 tabelas + 1 trait + 3 Services

```
arquivos              ← metadados (1 row por upload distinto)
arquivos_dedupe       ← lookup MD5 → primeira ocorrência
arquivos_audit_log    ← append-only (upload, signed_url_issued, soft_delete, retention.expired, retention.purged)

App\Concerns\HasArquivos  ← trait morphMany pra opt-in por entidade

Modules\Arquivos\Services\
├── ArquivosService              ← attach/classify/signedUrl/softDelete/restore (5 spans OTel)
├── ArquivosRetentionService     ← scanExpired/expireOne/purgeOne/run/preview/report (6 spans OTel)
└── VaultEncryptionService       ← putFileEncrypted/getDecrypted (2 spans OTel)
```

Total: **3 Services, 13 spans OTel canônicos** (D9.a saturated — Wave 18 baseline 11 + Wave 27 polish 2).

## Persona — Auditor LGPD

> **Wave 27 (2026-05-17):** persona consolidada pra orientar features e auditorias futuras.

Esta persona é o **leitor implícito** de todos os artefatos de governança deste módulo. Quando você ler `Config/retention.php`, audit log SQL, signed URL flow, ou qualquer relatório `arquivos.retention.report`, pergunte: **"o Auditor LGPD consegue rastrear isso sem me chamar?"**

### Quem é

Pessoa externa (DPO, advogado LGPD, auditor regulador ANPD), ou interna senior (Eliana[E] estudando LGPD), que precisa **provar conformidade** sem ter acesso ao código-fonte. Lê dashboards, exporta CSV/PDF, valida que política declarada == política executada.

### Dores típicas

1. **"Quantos arquivos estão acima da retenção declarada AGORA?"** — precisa dashboard read-only com filtro por bucket. Wave 27 `ArquivosRetentionService::preview()` cobre.
2. **"Quem decidiu mudar bucket de `sensitive` pra `internal` no arquivo X?"** — `ReclassifyArquivoRequest` força `motivo` obrigatório + audit log. Wave 27 D8.c.
3. **"Esse hard-delete foi solicitado pelo titular ou foi limpeza retroativa?"** — `RetentionRunRequest` exige `motivo` quando `purge=true` (Art. 18 §VI). Wave 27 D8.c.
4. **"Esse relatório de purge é confiável? Posso assinar?"** — `ArquivosRetentionService::report()` gera payload determinístico append-only com timestamp UTC + base legal (Art. 16 + §VI) explicitada.
5. **"Qual a janela legal pra cada categoria?"** — `Config/retention.php` declara `retention_days_policy` por contexto; `Config/retention.spreadsheet.php` shim D7.c expõe em formato rubrica governance v3.

### Garantias que o módulo dá ao Auditor

| Pergunta | Resposta canônica |
|---|---|
| Política declarada? | `Config/retention.php` + shim D7.c |
| Política executada? | `arquivos_audit_log` (append-only) + spans OTel `arquivos.retention.*` |
| Multi-tenant isolado? | ADR 0093 IRREVOGÁVEL + Pest `MultiTenantIsolationTest` |
| Encryption-at-rest? | bucket `vault` força `VaultEncryptionService` (AES-256-CBC via APP_KEY) |
| Direito eliminação Art. 18 §VI? | `RetentionRunRequest` `purge=true` + `motivo` obrigatório |
| Quem disparou batch? | `arquivos.retention.report` payload tem `meta.user_id` + `meta.batch_tag` |
| Quanto vai ser apagado amanhã? | `ArquivosRetentionService::preview()` dry-run agregado por bucket |

## Tier 0 IRREVOGÁVEL

- **Multi-tenant** ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)): toda query de `Arquivo` usa `business_id` global scope; jobs recebem `$businessId` no constructor; cross-tenant dedupe NUNCA vaza.
- **LGPD Art. 16:** retenção declarada em `Config/retention.php`. Mudança REAL altera AMBOS arquivos (`retention.php` + shim D7.c) — acoplamento explícito.
- **Audit append-only:** `arquivos_audit_log` NÃO permite UPDATE/DELETE. Adição de coluna via migration; mudança de row = nova linha.

## Quick-start integração (consumer module)

```php
use App\Concerns\HasArquivos;

class MeuModel extends Model
{
    use HasArquivos; // morphMany pra arquivos
}

// upload
$arquivo = app(ArquivosService::class)->attach($meuModel, $request->file('anexo'), [
    'context' => 'nfe-xml', // CuradorEngine usa pra decidir bucket
]);

// download signed
$url = app(ArquivosService::class)->signedUrl($arquivo, expiresMinutes: 60);
```

## Estado das US (Sprint 1+2 concluído)

Ver [SCOPE.md](SCOPE.md) pra matriz US-ARQ-001..US-ARQ-010 + US-PRE pendentes Wagner.

## CHANGELOG

Ver [CHANGELOG.md](CHANGELOG.md) — Wave 25 + Wave 27 polish governance v3.
