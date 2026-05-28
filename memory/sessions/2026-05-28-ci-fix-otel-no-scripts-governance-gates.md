---
date: 2026-05-28
hour: "00:00 BRT"
topic: "Fix CI repo-wide — --no-scripts nos gates secrets:scan (ADR 0215) e governance:audit (ADR 0216) que quebravam por OTel _register.php"
authors: [W, C]
---

# Fix CI — OTel `_register.php` quebrava `secrets:scan` + `governance:audit` repo-wide

## TL;DR

Os 2 workflows de governança mergeados ~2026-05-28 — `secrets-governance.yml`
(job **Camada 1 — Auto-discovery (secrets:scan)**, ADR 0215) e
`governance-drift.yml` (job **ADR 0216 PR scan (governance:audit --diff-only)**)
— falhavam em **TODO PR do repo** no step `Install dependencies`, bloqueando
merge geral. Wagner usou admin merge no PR #1886 como workaround.

Erro:

```
The opentelemetry extension must be loaded in order to autoload the
OpenTelemetry Laravel auto-instrumentation
In _register.php line 13
##[error]Process completed with exit code 1
```

## Causa-raiz

`composer install` executa o hook `post-autoload-dump` do pacote OTel
(`open-telemetry/.../_register.php`). Esse script roda
`extension_loaded('opentelemetry')` em **runtime** e dá `throw` no runner
`ubuntu-latest` (que não tem a extensão PECL). `--ignore-platform-req=ext-opentelemetry`
**não resolve** porque a checagem é do *script*, não um platform-req do composer
— por isso o hook continuava executando e estourando.

## Fix

Paridade com os workflows que já funcionam (`phpstan-gate.yml`, `ci.yml`,
`ui-lint.yml`, `adr-lint.yml`):

1. `composer install --no-scripts` → pula o `post-autoload-dump`. O autoload
   continua sendo gerado normalmente; só não roda o registro de
   auto-instrumentação OTel — que em CI é inútil (CI **nunca** emite spans; só o
   CT 100 emite, ADR 0062 Tier 0). OTel vive em `require-dev` (ADR 0166).
2. `extensions: mbstring, xml, ctype, json, opentelemetry` no `setup-php` →
   belt-and-suspenders.

Aplicado nos **4 jobs**: `scan` + `audit` (secrets-governance) e `drift-scan-pr`
+ `drift-audit-scheduled` (governance-drift).

## Validação

Este próprio PR é a validação: toca `memory/**`, disparando os 2 gates
(`secrets:scan` e `governance:audit --diff-only`) com a definição **já corrigida**
dos workflows (PR `pull_request` usa o YAML do head). Confirmar ambos verdes
fecha o loop.

## Achados laterais (fora de escopo deste PR)

- **Case-collision no git tree** (Windows quebra checkout): pares de arquivos que
  diferem só por caixa — `memory/requisitos/Fiscal/{Nfe,nfe}-visual-comparison.md`
  e `Modules/RecurringBilling/Resources/lang/{pt-BR,pt-br}/recurringbilling.php`.
- **ADR 0216 com número duplicado**: `0216-deploy-webhook-rodar-composer-dump-autoload.md`
  e `0216-governance-drift-framework-driftchecker-plugavel.md`.
- `NpmAuditChecker` (ADR 0223) tem `enforcement: warn`, então `--fail-on=block`
  **não** bloqueia o gate por CVE npm (o comentário no checker sugere o contrário).

## Referências

- [ADR 0215](../decisions/0215-secrets-governance-5-camadas-automaticas.md) — Secrets governance 5 camadas
- [ADR 0216](../decisions/0216-governance-drift-framework-driftchecker-plugavel.md) — Governance Drift Framework
- [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md) — Separação runtime Hostinger ≠ CT 100
- [ADR 0166](../decisions/0166-errata-0162-otel-require-dev-hostinger.md) — OTel em require-dev
