# Modules/Arquivos — Como o cliente usa

> **Wave 18 RETRY D5 SATURATION (2026-05-16)** — guia "do ponto de vista de quem usa" complementando o BRIEFING (estado consolidado) e o SPEC (regras técnicas).

## TL;DR (1 parágrafo)

O cliente **não usa Arquivos diretamente** — usa os módulos consumidores (NfeBrasil, Consumidores, Repair, MemCofre etc) que **anexam arquivos via trait `HasArquivos`**. O `Modules/Arquivos` é backbone DMS multi-tenant transparente: vault encryption per-business, dedupe SHA-256 cross-módulo, audit log append-only, retention LGPD configurável. UX aparece via dropzones/galerias nos módulos pais; admin vault browser planejado quando ROTA LIVRE pedir sinal qualificado.

## Jornada típica por persona

### Persona 1 — Operador comum (ex: Larissa, ROTA LIVRE biz=4)

1. Faz **upload no módulo dela** (ex: NFe → anexa XML; Repair → anexa foto da peça quebrada)
2. UI do módulo pai abre dropzone → Controller chama `ArquivosService::attach($model, $uploadedFile)`
3. Service deduplica SHA-256 dentro do business (se XML/foto já existe, retorna referência reaproveitada)
4. Classifica via `CuradorEngine` 5-fase (DISCOVER → CLASSIFY → REPORT → REVIEW → APPLY) → decide disk (`public`/`internal`/`sensitive`/`vault`)
5. Encripta no `disk=vault` se sensível (AES-256-CBC envelope, APP_KEY-backed)
6. Devolve UUID + URL assinada (1h) pro frontend
7. Audit log append-only registra: quem, quando, MD5, tamanho, tipo MIME, business_id

### Persona 2 — Admin (Wagner / superadmin)

1. Acessa `/admin/arquivos` (planejado — UI vault browser quando houver sinal)
2. Hoje opera via comandos artisan:
   - `php artisan arquivos:health-check` — sanidade do vault key, audit log writable, retention configurado
   - `php artisan arquivos:dedupe-stats {biz}` — % de economia por dedupe
   - `php artisan arquivos:retention-cleanup --dry-run` — preview do que LGPD elimina hoje
   - `php artisan arquivos:export-zip {biz}` — pacote ZIP cifrado pra LGPD Art. 18 (portabilidade)
   - `php artisan arquivos:reencrypt-vault` — rotaciona APP_KEY (re-encrypt batch)

### Persona 3 — Auditor LGPD (Eliana [E] futura DPO)

1. Vê audit log em `arquivos_audit_log` (append-only por trigger MySQL)
2. Lê `BRIEFING.md` pra entender escopo
3. Roda `arquivos:audit-log {biz} --from=2026-01-01 --format=csv` pra extrair evidência
4. Confere retention policy via `php artisan arquivos:retention-cleanup --dry-run` (padrão 1825 dias = 5 anos fiscal)

## API canônica (pra outro módulo consumir)

```php
use Modules\Arquivos\Concerns\HasArquivos;

class MeuModel extends Model {
    use HasArquivos; // adiciona ->arquivos() relation polimórfico
}

// Upload
$arquivo = app(ArquivosService::class)->attach($meuModel, $request->file('file'), [
    'bucket' => 'sensitive', // ou public/internal/vault
    'context' => 'minha-feature',
]);

// Listar
$lista = $meuModel->arquivos()->paginate(25);

// Download seguro
$signedUrl = app(ArquivosService::class)->signedUrl($arquivo); // 1h TTL
```

## O que o cliente NÃO vê (zero UI hoje)

- Cifragem AES-256 no vault (transparente)
- Pipeline curador 5-fase (heurística-first; Claude-second só em casos ambíguos)
- Audit log append-only
- Retention LGPD (1825d default; rodando como cron daily 03:00 BRT)
- Dedupe SHA-256 cross-módulo (NFe XML 12345 anexado em 3 lugares = 1 storage real + 3 refs)

## Métricas de saúde do ponto de vista do cliente

| Sinal | Saudável | Atenção | Quem detecta |
|---|---|---|---|
| Upload completa em <3s p/ <10MB | ✅ | >10s = vault enc lento | Operador (UX) |
| URL assinada válida 1h | ✅ | 403 imediato = ACL bug | Operador (download) |
| Dedupe ratio >30% | ✅ | <10% = pipeline curador falhou | `arquivos:dedupe-stats` |
| Audit log 100% das ops | ✅ | rows < ops = trigger desligada | Pest `MultiTenantTest` |

## Links

- BRIEFING canônico: [`memory/requisitos/Arquivos/BRIEFING.md`](BRIEFING.md)
- SPEC US-ARQ-*: [`memory/requisitos/Arquivos/SPEC.md`](SPEC.md)
- ADR mãe: [`memory/decisions/0123-modules-arquivos-backbone.md`](../../decisions/0123-modules-arquivos-backbone.md)
- Charters vault+curador: [`memory/requisitos/Arquivos/CHARTERS-vault-curador.md`](CHARTERS-vault-curador.md)
- RUNBOOK ingestão: [`memory/requisitos/Arquivos/RUNBOOK-ingestao-documentos.md`](RUNBOOK-ingestao-documentos.md)
