---
slug: 0212-defensive-logging-fallback-paths
number: 212
title: "Defensive logging em fallback paths — eliminar R9-class (silent fallback) via Log::warning canon + PHPStan rule"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-28
module: Infra
quarter: 2026-Q2
tags: [logging, defensive-programming, fail-loud, observability, prevencao-bugs]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0208-larastan-baseline-ratchet
pii: false
review_triggers:
  - "R9-class bug (fallback silencioso) reportado em outra tela/controller"
  - "Sentry/Honeycomb adoptada (substituir log file local)"
---

# ADR 0212 — Defensive logging em fallback paths

## Contexto

R9 da sessão Larissa 2026-05-28: `SellPosController:435` tinha fallback cego pra `transaction_date`:

```php
if (empty($request->input('transaction_date'))) {
    $input['transaction_date'] = \Carbon::now();  // ← hora do submit, não da abertura
}
```

Quando o input chegava vazio (`<input type="datetime-local">` limpado, regex `toDatetimeLocal` falhando em AM/PM, state perdido em navegação), backend gravava `Carbon::now()` — hora do MOMENTO do submit. Larissa abria Create às 18:00 e fechava 2h47 depois → DB gravava 20:47. Bug invisível: nenhum log, nenhum alerta, nenhuma exception.

Fix aplicado em [PR #1830](https://github.com/wagnerra23/oimpresso.com/pull/1830) (R9) adicionou `Log::warning` no fallback + frontend pre-submit guard + transform fallback. Resolveu o caso específico mas pattern continua frágil — outros controllers UltimatePOS legacy têm dezenas de `if (empty(...)) $x = <default>;` cegos.

Estado-da-arte 2026 ([dossier session](../sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md) Frente 3) elenca princípio "fail loud not silent":

- Laravel 12 docs canon: `Log::withContext(['business_id' => ..., 'request_id' => ..., 'user_id' => ...])` em middleware global
- PSR-3 8 níveis (DEBUG/INFO/NOTICE/WARNING/ERROR/CRITICAL/ALERT/EMERGENCY)
- Pattern moderno: **`Log::warning` antes de qualquer `??=`, `Carbon::now()`, ou default** silencioso
- Sentry/Honeycomb 2026 tracam `trace_id`+`span_id` em todo log

oimpresso atual:

- `storage/logs/laravel.log` rotativo daily
- `app/Console/Commands/health-check` (Jana) checa drift do distiller, fallback Brain B, PII leak — **não** checa silent fallbacks de controllers
- Health-check roda diário 06:00 BRT — bugs entre runs ficam invisíveis horas
- Não há middleware global injetando `business_id`/`user_id` em log context

## Decisão

**Adotar princípio "fail loud" em 4 camadas:**

**Camada 1 — Middleware global `LogContextMiddleware`** (Fase 1, S 2h):

- `app/Http/Middleware/LogContextMiddleware.php`
- Registrado em `App\Http\Kernel.php` `'web'` group antes de `AdminSidebarMenu`
- Injeta em `Log::withContext([...])`:
  - `business_id` (de `session('user.business_id')` UPOS canon)
  - `user_id` (de `session('user.id')`)
  - `request_id` (UUID gerado por request)
  - `route_name` (request route name)
- Mantém retro-compat: log existentes ganham automaticamente esses campos

**Camada 2 — Convenção `Log::warning` antes de fallback** (Fase 2, doc S 1h):

- Catalogar como **AP-18 "Fallback default sem `Log::warning`"** em [`LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)
- Pattern canon documentado:

```php
// ❌ Errado (silent)
if (empty($request->input('transaction_date'))) {
    $input['transaction_date'] = \Carbon::now();
}

// ✅ Certo (loud)
if (empty($request->input('transaction_date'))) {
    \Log::warning('SellPosController@store fallback Carbon::now()', [
        'expected_key' => 'transaction_date',
        'received' => json_encode($request->input('transaction_date')),
        'has_payload_key' => $request->has('transaction_date'),
        // business_id/user_id já em context via middleware
    ]);
    $input['transaction_date'] = \Carbon::now();
}
```

**Camada 3 — PHPStan custom rule `NoSilentFallbackRule`** (Fase 3, M 4-6h, depende [ADR 0208 Larastan](0208-larastan-baseline-ratchet.md) instalado):

Detecta padrões:
- `if (empty($var)) $assignment = <expr>;` sem `Log::warning` no mesmo bloco
- `$var = $other ?? <default>;` em controllers/services (warn, não erro — coalesce é OK em values inferiores)
- `if (! isset($x) ?: $x = <default>)` patterns

False-positive ok no início — ratchet baseline absorve. Erro tier 5 PHPStan.

**Camada 4 — Health-check novo `controllers_with_silent_fallback`** (Fase 4, S 2h, depende Camada 3):

Comando `jana:health-check` ganha check novo: roda `grep` diário em `app/Http/Controllers/**/*.php` + `Modules/*/Http/Controllers/**/*.php` por pattern `if \(empty.*\).*Carbon::now\(\)` SEM `Log::warning` em ±10 linhas. Primeiro hit em paths novos dispara alerta `storage/logs/laravel.log` ALERT entry.

**Camada 5 — Trait `HasContextualException`** (Fase 5, S 3h):

`app/Exceptions/HasContextualException.php` trait. Exceptions em `Modules/<Mod>/Exceptions/` que usam o trait retornam `context()` shape canônico:

```php
public function context(): array {
    return [
        'tenant_id' => $this->businessId,
        'tabela' => static::TABLE,
        'id' => $this->entityId,
    ];
}
```

Laravel 12 já merge `context()` automaticamente no log report. Reduz boilerplate em Sentry future.

## Justificativa

**Por que middleware global e não logger custom:** UPOS já tem `'web'` middleware group canon. Adicionar 1 middleware = zero-friction adoption. Custom logger replicaria infra Laravel sem ROI.

**Por que `Log::warning` (não `Log::error`):** fallback NÃO é erro — é "comportamento esperado em path degraded". Warning é o nível correto PSR-3. Manter `Log::error` pra exceptions de fato.

**Por que custom PHPStan rule e não só doc:** R9 prova que doc não é suficiente. UltimatePOS legacy tem dezenas de fallbacks cegos. Sem AST rule, agente novo vai catalogar 4° caso da mesma classe daqui 30d. **Rule = enforcement passivo que torna esquecer impossível.**

**Por que health-check check novo é redundante com PHPStan rule:** safety-net. PHPStan rule blocks new code. Health-check faz auditoria periódica do **existente** sem precisar rodar PHPStan inteiro daily. Custo: 1 grep daily, trivial.

**Por que trait HasContextualException é fase tardia:** ROI menor que camadas 1-4. Exceptions são <1% dos paths. Útil quando Sentry/Honeycomb entrar (futuro), antes é over-engineering.

## Consequências

**Positivas:**

- **R9 raiz eliminado** — fallback cego vira impossível em paths PHPStan-covered
- **Log context global**: toda log entry tem business_id/user_id/request_id automaticamente. Drill-down 1-click em filtros (preludium pra Sentry/Honeycomb future)
- Pattern reutilizável: próximo "if (empty)" em qualquer controller automaticamente cai sob rule
- Health-check auditoria periódica de paths legacy não-cobertos por PHPStan
- AP-18 catalogado vira referência canon pro time futuro (Felipe/Maiara/Eliana/Luiz)

**Negativas / Trade-offs:**

- **PHPStan rule custom dev:** 4-6h IA-pair pra primeira iteração + tuning false-positives
- **Log volume aumenta:** warnings em fallback antes invisíveis agora geram entries. Rotação atual diária + retenção 14d UPOS-default → não-crítico em disco Hostinger
- **Performance middleware:** `Log::withContext` é hashmap merge, ~microsegundos. Trivial.
- **Curva: time futuro precisa entender "warning é OK, é só sinal"** — risco alarm fatigue. Mitigação: docs claras + ALERT vs WARNING separação (ALERT = health-check anomaly; WARNING = fallback acionado, esperado mas rastreado)

**Riscos mitigados:**

- R9-class bug (fallback silencioso) — paths controllers + services Laravel-aware
- Drift de DB column NULL → backend default → cliente vê valor errado sem detecção
- Onboarding novo dev: log mostra context completo, não precisa tribal knowledge

**Riscos não-mitigados:**

- Fallback silencioso em JS/TS frontend — não cobre. ESLint custom rule futuro pode (no-silent-default-coalesce)
- Database-level silent (column DEFAULT NULL agindo silently) — exige PHPStan-Doctrine ou similar Eloquent extension
- Race entre middleware register e exception muito-early — Laravel handle exception bootstrap antes do middleware. Aceito como gap.

## Referências

- ADR 0093 — Multi-tenant Tier 0 isolation (business_id sempre em context)
- ADR 0094 — Constituição v2 §princípio 7 (transparência)
- ADR 0094 — §princípio 8 (confiabilidade com fallback — agora rastreável)
- ADR 0208 — Larastan PHPStan baseline ratchet (habilita rule custom)
- [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — AP-18 a catalogar
- [PR #1830 R9 fix transaction_date drift](https://github.com/wagnerra23/oimpresso.com/pull/1830)
- [Laravel 12 docs — Logging + withContext](https://laravel.com/docs/12.x/logging)
- [iConcept — 8 Laravel logging best practices 2025](https://iconcept.lv/en/blog/logging-best-practices)
- [Dash0 — Laravel logging practitioner guide](https://www.dash0.com/guides/laravel-logging)
- [Sentry — Structured logs available to all](https://sentry.io/about/press-releases/sentry-structured-logs-now-available-to-all/)
