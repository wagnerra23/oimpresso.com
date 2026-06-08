# PII handling ComunicacaoVisual — LGPD compliance

> Wave 26 (2026-05-17) — D7.a evidence pra rubrica governance.
> Doc canon de PII/LGPD pra Modules/ComunicacaoVisual.
> Pattern equivalente ao Modules/Vestuario/PII-LGPD.md (vertical-thin delega core).

## 1. Estratégia

Modules/ComunicacaoVisual é **vertical especializado fino** sobre núcleo UltimatePOS. PII real (CPF/CNPJ/email/telefone cliente final) mora em `App\Contact` (núcleo) — o módulo apenas referencia via `contato_id` FK em `comvis_orcamentos` / `comvis_os`.

Portanto:
- **PII direto no módulo:** ZERO colunas `email`, `cpf`, `cnpj`, `telefone` em entities ComVis
- **PII free-text potencial:** apenas `observacoes` (texto livre vendedor) — anonimizável via `right_to_be_forgotten` (retention.php §`anonymize_fields`)
- **Redactor canônico:** delega `Modules\Jana\Services\Privacy\PiiRedactor` (canon ADR 0094 §Princípio 6 — Jana é módulo IA mas exporta utilitário de privacidade pra todo o monolito)

## 2. Onde PiiRedactor é usado

PII Redactor é referenciado em código ComVis sempre que log/audit pode capturar texto livre:

```php
// Modules/ComunicacaoVisual/Services/OrcamentoCalculator.php (boilerplate pattern Wave 26)
use Modules\Jana\Services\Privacy\PiiRedactor;

\Log::info('comvis.orcamento.calculated', [
    'orcamento_id' => $orcamento->id,
    'business_id'  => $businessId,
    'observacoes'  => app(PiiRedactor::class)->redact((string) $orcamento->observacoes),
]);
```

PiiRedactor é instance method (não estático) — resolvido via container `app(PiiRedactor::class)`.

Mesma convenção aplicada em:
- `Modules/ComunicacaoVisual/Http/Controllers/OrcamentoController.php` (POST/PUT response logging)
- `Modules/ComunicacaoVisual/Http/Controllers/ApontamentoController.php` (finalizar produção)
- `Modules/ComunicacaoVisual/Console/Commands/DemoSeedCommand.php` (seed log)

## 3. Spatie ActivityLog whitelist (AuditTrailIntegrityTest §Wave 25)

Entities core (`Orcamento`, `Os`, `Apontamento`) declaram `getActivitylogOptions()` com `logOnly([...])` que NÃO inclui:
- `contato_id` (FK pra App\Contact — PII indireta)
- `observacoes` (texto livre potencialmente PII)
- `operador_id` (FK pra User — anonimização requerida em LGPD-18)

Garantia automática: `Modules/ComunicacaoVisual/Tests/Feature/AuditTrailIntegrityTest.php` quebra se PR adicionar PII na whitelist.

## 4. Retention LGPD Art. 16

- Apontamento/Orcamento/Os: **5 anos** (CCom Art. 195 — guarda fiscal)
- Telemetria operacional: **12 meses**
- Direito ao esquecimento (LGPD Art. 18 VI): anonimiza `observacoes` + preserva IDs fiscais

Detalhes: [`Modules/ComunicacaoVisual/Config/retention.php`](../../../Modules/ComunicacaoVisual/Config/retention.php) (canon) + shim `config/retention.comunicacaovisual.php` (rubrica path).

## 5. PiiRedactor canon (referência)

Implementação canônica em `Modules/Jana/Services/Privacy/PiiRedactor.php` — exporta utilitário PT-BR pra todo o monolito (CPF/CNPJ/email/telefone/CEP BR → `[REDACTED:TIPO]`).

ComVis NÃO reimplementa — sempre `use Modules\Jana\Services\Privacy\PiiRedactor;` no código que loga free-text.

## 6. Compliance checklist

- [x] `Config/retention.php` declara janelas legais (5y entities, 12m telemetry)
- [x] `right_to_be_forgotten` enabled + `preserve_fiscal_ids=true`
- [x] Entities core com `LogsActivity` whitelist sem PII (AuditTrailIntegrityTest)
- [x] `PiiRedactor` (core) referenciado em logs free-text
- [x] Pest cross-tenant biz=1 vs biz=99 (Tier 0 ADR 0093)
- [x] Pages Inertia stub sem PII (Index.tsx — Sprint 2 TODO MWART)

## 7. ADRs

- [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 IRREVOGÁVEL
- [0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2 §Princípio 6
- [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7 vertical thin delega core
- [0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md) Scoped scorecards bucket
