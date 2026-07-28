---
title: "RUNBOOK — Pattern consumer migration (accessor preferido + fallback legacy + double-write)"
owner: W
status: ativo
last_validated: "2026-06-08"
---

# RUNBOOK — Pattern consumer migration (accessor preferido + fallback legacy + double-write)

> **Use sempre que migrar Producer/Consumer Module pra adotar `Modules/Arquivos` backbone OU qualquer migração de schema legacy → schema novo com convivência temporária.**
>
> Validado em produção 2026-05-10 com 2 consumer migrations: `Modules/NfeBrasil` (DanfeService + NfeEmissaoController) e `Modules/Repair` (JobSheet anexos accessor).

## Origem

Sessão 2026-05-10 PRs:
- [#404](https://github.com/wagnerra23/oimpresso.com/pull/404) NfeService double-write XML (legacy `xml_path` + arquivos backbone)
- [#410](https://github.com/wagnerra23/oimpresso.com/pull/410) DanfeService prefere `xml_arquivo` accessor
- [#412](https://github.com/wagnerra23/oimpresso.com/pull/412) NfeEmissaoController serialize `xml_url` + `danfe_url`
- [#418](https://github.com/wagnerra23/oimpresso.com/pull/418) JobSheet `anexos` accessor prefere arquivos backbone

## Quando usar

Sempre que precisar migrar produção sem janela de down-time:
- Coluna legacy → schema novo (ex: `xml_path` → `arquivos` table)
- Storage local → vault encrypted
- Spatie media-library → Modules/Arquivos backbone
- Tabela monolítica → schema normalizado

Pré-condição: existe **schema novo já operacional em paralelo** ao legacy. Migration backfill já populou rows novas a partir de dados legacy.

## Princípio: 4 fases, **sem flag-day**

```
Fase 1: BACKBONE        — Schema novo + Models + Service operacional
Fase 2: BACKFILL        — Migration idempotente popula schema novo a partir de legacy
Fase 3: DOUBLE-WRITE    — Producer escreve em AMBOS schema legacy + novo
Fase 4: CONSUMER MIGRATE — Consumers preferem schema novo, fallback legacy graceful
Fase 5: STABILIZATION   — 7-30d smoke prod, métricas em paridade
Fase 6: REMOVE LEGACY   — Drop coluna/tabela legacy + remove fallback (PR separado, gate Wagner)
```

## Pattern Producer double-write (Fase 3)

```php
// Modules/NfeBrasil/Services/NfeService.php (PR #404)

private function writeArquivoXml(NfeEmissao $emissao, string $xmlPath, string $xmlContent): void
{
    // Legacy continua escrita (não quebrar consumers ainda não-migrados)
    Storage::put($xmlPath, $xmlContent);

    // Schema novo (arquivos backbone) — best-effort, não bloqueia fluxo fiscal
    if (! Schema::hasTable('arquivos')) {
        return; // Modules/Arquivos não-instalado
    }

    try {
        $alreadyExists = DB::table('arquivos')
            ->where('arquivable_type', NfeEmissao::class)
            ->where('arquivable_id', $emissao->id)
            ->where('sub_destination', 'nfe-xml')
            ->exists();

        if ($alreadyExists) {
            return; // idempotência — re-emissão não duplica
        }

        // Insert direto (não via ArquivosService.attach pra não acoplar fiscal pipeline)
        DB::table('arquivos')->insert([
            'business_id' => $emissao->business_id,
            'arquivable_type' => NfeEmissao::class,
            'arquivable_id' => $emissao->id,
            'disk' => 'local',
            'storage_path' => $xmlPath,
            'original_name' => "nfe-{$emissao->numero}.xml",
            'mime_type' => 'application/xml',
            'size_bytes' => strlen($xmlContent),
            'md5' => md5($xmlContent),
            'bucket' => 'archive',
            'sub_destination' => 'nfe-xml',
            'classified_by' => 'nfe-service-double-write',
            'classified_at' => now(),
            'encrypted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('nfe.xml.double_write_ok', ['emissao_id' => $emissao->id, 'xml_path' => $xmlPath]);
    } catch (\Throwable $e) {
        // Graceful — fluxo fiscal NÃO bloqueia se backbone falhar
        Log::warning('nfe.xml.double_write_failed', [
            'emissao_id' => $emissao->id,
            'error' => $e->getMessage(),
        ]);
    }
}
```

Princípios:
- **Legacy escrita primeiro** — preserva contrato existente
- **Backbone graceful** — try/catch + log warning, não throw
- **Schema check** (`Schema::hasTable`) — não falha se Modules/Arquivos ainda não instalado em algum biz
- **Idempotência** — exists check antes de insert (re-emissão NFe não duplica row)

## Pattern Consumer migration (Fase 4)

```php
// Modules/NfeBrasil/Services/DanfeService.php (PR #410)

public function renderizar(NfeEmissao $emissao): string
{
    $xml = $this->obterXmlContents($emissao);

    $danfe = $this->danfeFactory !== null
        ? ($this->danfeFactory)($xml)
        : new Danfe($xml);

    $logo = $this->resolverLogoPath((int) $emissao->business_id);
    return $danfe->render($logo ?? '');
}

/**
 * Lê XML preferindo Modules/Arquivos backbone, fallback `xml_path` legacy.
 * Suporta vault encrypted transparente via VaultEncryptionService.
 */
private function obterXmlContents(NfeEmissao $emissao): string
{
    // Caminho preferido: arquivos backbone
    $arquivo = $emissao->xml_arquivo; // accessor define em Model
    if ($arquivo !== null) {
        $diskName = $arquivo->disk ?: 'arquivos';
        if ($arquivo->encrypted) {
            $vault = app(VaultEncryptionService::class);
            $contents = $vault->getDecrypted($diskName, $arquivo->storage_path);
        } else {
            $contents = Storage::disk($diskName)->exists($arquivo->storage_path)
                ? Storage::disk($diskName)->get($arquivo->storage_path)
                : null;
        }
        if (is_string($contents) && $contents !== '') {
            return $contents;
        }
        // Cai pro fallback se row existe mas file físico ausente
    }

    // Fallback legacy — coluna xml_path direto
    if (! $emissao->xml_path) {
        throw new RuntimeException(
            "NfeEmissao {$emissao->id} sem xml_path — nem arquivos backbone nem coluna legacy."
        );
    }
    if (! Storage::exists($emissao->xml_path)) {
        throw new RuntimeException("XML não encontrado em storage: {$emissao->xml_path}");
    }
    return Storage::get($emissao->xml_path);
}
```

Princípios:
- **Tenta novo primeiro** — usa accessor definido no Model
- **Fallback graceful** — sem `xml_arquivo` ou file físico ausente → tenta legacy
- **Mensagem de erro substring estável** — preserva tests existentes (`'sem xml_path'` segue no throw mesmo após migração)
- **Decrypt transparente** — vault encrypted é resolvido aqui, consumer não precisa saber

## Pattern Accessor no Model

```php
// Modules/NfeBrasil/Models/NfeEmissao.php

use Modules\Arquivos\Concerns\HasArquivos;

class NfeEmissao extends Model
{
    use HasArquivos; // ADR 0123 — adopcão trait Sprint 3 US-ARQ-019

    /**
     * Accessor — retorna Arquivo XML preferindo arquivos table (ADR 0123),
     * null se ainda usando coluna legacy xml_path.
     */
    public function getXmlArquivoAttribute(): ?Arquivo
    {
        if (! method_exists($this, 'arquivos')) return null;
        return $this->arquivos()
            ->where('sub_destination', 'nfe-xml')
            ->where('bucket', 'active') // ou 'archive' conforme tipo
            ->latest('created_at')
            ->first();
    }
}
```

Princípios:
- **Defensivo** — checa `method_exists('arquivos')` (trait pode não estar adopted em algum business)
- **Filtro explícito** — `sub_destination` + `bucket` pra encontrar arquivo certo
- **`latest()`** — se houver múltiplos uploads (re-emissão), pega o mais recente
- **Não executa I/O** — só retorna Model row, leitura efetiva fica em consumer

## Pattern API Controller serialize URLs (consumer público)

```php
// Modules/NfeBrasil/Http/Controllers/NfeEmissaoController.php (PR #412)

public function __construct(private ArquivosService $arquivos) {}

private function serializeEmissao(NfeEmissao $emissao): array
{
    $xmlArquivo = $emissao->xml_arquivo;
    $danfeArquivo = $emissao->danfe_arquivo;

    return [
        'id' => $emissao->id,
        'numero' => $emissao->numero,
        'chave_44' => $emissao->chave_44,
        'status' => $emissao->status,
        'cstat' => $emissao->cstat,
        'valor_total' => (float) $emissao->valor_total,
        'emitido_em' => $emissao->emitido_em?->toIso8601String(),

        // URLs signed temporárias (60min) preferindo backbone, null se ausente
        'xml_url' => $xmlArquivo ? $this->arquivos->signedUrl($xmlArquivo) : null,
        'danfe_url' => $danfeArquivo ? $this->arquivos->signedUrl($danfeArquivo) : null,

        // Backward compat: paths legacy ainda expostos pra consumers não-migrados
        'xml_path_legacy' => $emissao->xml_path,
        'danfe_path_legacy' => $emissao->danfe_path,
    ];
}
```

Princípios:
- **URLs assinadas temporárias** — não exposição de path interno
- **Multi-tenant Tier 0 automático** — `signedUrl` route já valida `business_id` no DownloadController
- **Backward compat** — `*_legacy` campos seguem expostos durante transição
- **DI Service** — recebe `ArquivosService` no constructor, não acopla a `app(...)` em runtime

## Anti-padrões catalogados

| ❌ Anti-padrão | Por quê | ✅ Correto |
|---------------|---------|-----------|
| Producer escreve só em backbone, não em legacy | Quebra consumers não-migrados | Double-write ambos durante Fase 3-5 |
| Consumer migra direto sem fallback | Migration backfill pode ter falhado pra alguns rows | Fallback graceful pra legacy |
| Throw exception em accessor quando arquivo ausente | Bloqueia outros campos serializados | Retornar `null` + consumer decide |
| Remover coluna legacy junto com migration consumer | Sem janela de stabilization → regressão prod | PR separado pós-7d smoke (gate Wagner) |
| `xml_url` sempre signed mesmo sem cookie auth | Vaza URL pra browser logs | Só serializar quando consumer autenticado solicita |
| Producer double-write em DB transaction com fluxo principal | Backbone falha → fluxo fiscal trava | Backbone fora da transaction, try/catch graceful |

## Fase 6: REMOVE LEGACY (gate Wagner)

Pós-stabilization (7-30d smoke prod):
1. Verificar métricas paridade — `php artisan arquivos:health-check` + audit log
2. Verificar que **0% reads vão pro fallback legacy** — instrumentar log temporário no fallback path: `Log::info('consumer.legacy_fallback', [...])` por 7d
3. Se 0 logs em 7d → seguro remover legacy
4. Migration nova: `drop column xml_path` + remove fallback do Service
5. PR separado `chore(<modulo>): remove legacy <coluna> — Sprint N+2` com aprovação Wagner

**Nunca** remover legacy junto com adopção. Sempre PR separado pós-stabilization.

## Tests Pest pra cada fase

| Fase | Test obrigatório | Localização |
|------|-----------------|-------------|
| 1 BACKBONE | Schema/Service unit tests | `Modules/Arquivos/Tests/Feature/` |
| 2 BACKFILL | Migration idempotência (rodar 2x) + multi-tenant preservado | `Modules/Arquivos/Tests/Feature/Backfill*Test.php` |
| 3 DOUBLE-WRITE | Producer escreve em ambos + idempotente em re-emissão | `Modules/<X>/Tests/Feature/<Producer>DoubleWriteTest.php` |
| 4 CONSUMER MIGRATE | (a) Backbone presente → lê backbone, (b) backbone ausente → fallback legacy, (c) row backbone com file físico ausente → fallback graceful | `Modules/<X>/Tests/Feature/<Consumer>PrefersArquivosTest.php` |
| 5 STABILIZATION | Health check + audit log no Service tests | `Modules/Arquivos/Tests/Feature/` |
| 6 REMOVE LEGACY | Tests existentes seguem passando após drop coluna | matriz CI valida |

## Histórico de uso

- 2026-05-10 PR #410 — DanfeService prefere xml_arquivo + 3 Pest tests cenários Fase 4
- 2026-05-10 PR #418 — JobSheet anexos accessor + 3 Pest tests
- 2026-05-10 PR #412 — NfeEmissaoController API serialize xml_url + danfe_url

---

**Owner:** Felipe (sprint dev), Wagner (gate Fase 6 remove legacy)
**Última atualização:** 2026-05-10 — origem sessão massiva 30 PRs
**Refs:** ADR 0123 (Modules/Arquivos backbone), ADR 0093 (multi-tenant Tier 0), [RUNBOOK-validacao-pos-deploy.md](RUNBOOK-validacao-pos-deploy.md)
