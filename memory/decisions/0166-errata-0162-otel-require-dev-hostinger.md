---
slug: 0166-errata-0162-otel-require-dev-hostinger
number: 166
title: "Errata ADR 0162 — OTel SDK em require-dev (Hostinger shared sem ext-opentelemetry)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-17"
accepted_at: "2026-05-17"
review_at: 2026-11-17
module: Governance
quarter: 2026-Q2
tags: [errata, opentelemetry, composer, hostinger, deploy, runtime-separation, ct100, observability]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0062-separacao-runtime-hostinger-ct100", "0094-constituicao-v2-7-camadas-8-principios", "0143-fsm-pipeline-live-prod-marco-2026-05-12", "0156-module-grade-v3-errata-otel-helper-na-justified", "0162-otel-collector-prod-observability"]
pii: false
review_triggers:
  - Se Hostinger migrar de shared hosting pra VPS dedicada com PECL próprio (ext-opentelemetry passa a estar disponível) — reavaliar move dev→require (cenário ADR 0062 review_triggers)
  - Se composer split entre Hostinger e CT 100 for implementado (ADR 0062 §"Próximos passos" item 1) — esta errata pode ser absorvida no composer separado do CT 100
  - Quando ext-opentelemetry chegar à lista oficial Hostinger (acompanhar release notes hPanel trimestral) — reavaliar manter o `--ignore-platform-req` no deploy.yml
  - Se PR #1018 (instala ext-opentelemetry no CI runner) precisar de revisão (CI quebrando por outra razão) — esta errata não muda, só PR #1018 muda
---

# ADR 0166 — Errata ADR 0162: OTel SDK em `require-dev` (Hostinger shared sem `ext-opentelemetry`)

## Status

**Accepted** — errata complementar à [ADR 0162](0162-otel-collector-prod-observability.md). NÃO substitui 0162 (append-only Tier 0 IRREVOGÁVEL — [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §3 Charter > Spec + proibições.md §Memória/governança). Corrige um campo `require` errado introduzido na Wave 26 OTel.

## 1. Contexto — bug operacional pós Wave 26

[ADR 0162](0162-otel-collector-prod-observability.md) §2 Decisão declarou textualmente:

> *"PHP SDK: `open-telemetry/opentelemetry-auto-laravel` + `open-telemetry/sdk` (já listados como **opcional** no `composer.json`)"*

E §8 Backward-compat:

> *"Wave 26 inverte default `otel.enabled` de `false` → `true` E ativa exporter OTLP HTTP (...) **composer require opcional, env flag opcional**"*

A ADR 0162 está correta em intenção. **O bug operacional**: a Wave 26 adicionou os 3 pacotes em `require` (não `require-dev`) do `composer.json` raiz:

```json
"require": {
    "open-telemetry/exporter-otlp": "^1.0",
    "open-telemetry/opentelemetry-auto-laravel": "^1.0",
    "open-telemetry/sdk": "^1.0",
    ...
}
```

Isso quebra o deploy Hostinger:

1. `open-telemetry/opentelemetry-auto-laravel:1.7.0` declara `requires: ext-opentelemetry *`
2. **Hostinger shared hosting NÃO tem `ext-opentelemetry`** (extensão PECL lançada out/2025 — confirmado via hPanel pelo Wagner 2026-05-17)
3. `composer install` em Hostinger falha:
   ```
   open-telemetry/opentelemetry-auto-laravel 1.7.0 requires ext-opentelemetry *
   -> it is missing from your system.
   ```
4. PR #1018 já destravou CI (instala ext-opentelemetry no runner GitHub via PECL). Hostinger continua bloqueado.

[ADR 0062](0062-separacao-runtime-hostinger-ct100.md) §"Runtime separado" já é explícita: **Hostinger ≠ CT 100, Hostinger é shared hosting sem permissão de root pra instalar extensões PECL.** ADR 0162 §10 Tier 0 IRREVOGÁVEIS reafirma:

> *"Hostinger NÃO recebe collector daemon ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md) §Runtime separado). Apps Hostinger fazem APENAS OTLP HTTP push pra CT 100."*

A intenção da Wave 26 nunca foi exigir `ext-opentelemetry` em Hostinger. O `require` errado é um descuido operacional que esta errata corrige.

## 2. Decisão

**Mover os 3 pacotes OTel diretos de `require` pra `require-dev`** no `composer.json` raiz + adicionar `--ignore-platform-req=ext-opentelemetry` no comando `composer install` do deploy Hostinger (`.github/workflows/deploy.yml`).

### 2.1 Por que `require-dev` e não `require`

| Caminho | Hostinger | CT 100 | CI | Local Wagner |
|---|---|---|---|---|
| **`require` (estado errado pré-errata)** | ❌ falha (sem `ext-opentelemetry`) | ✅ ok (PECL instalado) | ✅ ok (PR #1018 instala PECL no runner) | ✅ ok |
| **`require-dev` (decisão errata 0166)** | ✅ ok com `--ignore-platform-req=ext-opentelemetry` (deploy.yml) | ✅ ok (PECL instalado, composer install com dev no compose) | ✅ ok (PR #1018 + dev incluso) | ✅ ok |

`require-dev` reflete a realidade: **OTel é instrumentation de runtime CT 100 + testes locais/CI**, não dependência de runtime Hostinger. O OtelHelper já é fail-safe ([`app/Util/OtelHelper.php`](../../app/Util/OtelHelper.php) gates 1+2+3) — quando SDK ausente, vira no-op zero-cost.

### 2.2 Por que `--ignore-platform-req=ext-opentelemetry` no deploy.yml

O `deploy.yml` Hostinger usa `composer install` SEM `--no-dev` por causa do **incidente Faker 2026-04-25** (tela branca Inertia "null.component" — Faker é usado em prod por queries de demo data). Comentário explícito no workflow:

```yaml
# NÃO usar --no-dev: Faker é usado em prod (incidente 2026-04-25, tela branca Inertia "null.component").
```

Como Hostinger instala com dev, os 3 pacotes OTel + 4 transitivos viriam junto e re-disparariam o erro `ext-opentelemetry missing`. A flag `--ignore-platform-req=ext-opentelemetry` permite o install seguir mesmo sem a extensão — defensivo: o OtelHelper continua fail-safe (class_exists guard), apenas as classes carregam sem hooks de runtime do PECL.

Trade-off aceito: vendor Hostinger ganha ~7 pacotes OTel ocupando ~5MB. Custo trivial vs alternativa (split composer.json Hostinger ↔ CT 100, fora de escopo desta errata).

### 2.3 Por que NÃO usar `--no-dev`

Caminho `--no-dev` (sugestão original do prompt B2) quebraria Faker em prod — incidente catalogado 2026-04-25. Manter compat com Faker é Tier 0 operacional (auto-mem `reference_composer_install_obrigatorio_pos_deploy.md`).

## 3. Status quo afirmado (fora de escopo desta errata)

Esta errata **NÃO mexe** em:

- **CT 100 daemon FrankenPHP** continua usando OTel SDK normalmente. Cluster docker-compose lá tem ext-opentelemetry via dockerfile próprio. `composer install` no CT 100 com dev (ou sem `--no-dev`) pega OTel via `packages-dev` lock — funciona igual antes.
- **CI GH Actions** já resolvido por [PR #1018](https://github.com/wagnerra23/oimpresso.com/pull/1018) que instala ext-opentelemetry via PECL no runner — não muda.
- **OtelHelper / OtelServiceProvider** continuam canônicos, fail-safe, multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) — não muda.
- **`config/otel.php`** + **`OTEL_ENABLED=false`** default Hostinger — não muda.
- **Schema `mcp_observability_spans`** ([ADR 0162](0162-otel-collector-prod-observability.md) §5) — pendente Wave 26.5+, sem impacto desta errata.
- **3 services iniciais instrumentados** ([ADR 0162](0162-otel-collector-prod-observability.md) §4: Jana agents + Repair FSM + Sells FSM) — código continua igual.

## 4. Validação empírica antes/depois

Executada localmente pelo agente B2 com Composer 2.x + Herd PHP 8.4 (Windows):

### 4.1 BEFORE (estado quebrado pré-errata)

```bash
# composer install --no-dev --dry-run (simula Hostinger se ele usasse --no-dev)
composer install --no-dev --dry-run

# Output:
# - open-telemetry/opentelemetry-auto-laravel is locked to version 1.7.0 (...)
# - open-telemetry/opentelemetry-auto-laravel 1.7.0 requires ext-opentelemetry *
#   -> it is missing from your system. Install or enable PHP's opentelemetry extension.
```

Confirmou empiricamente que o erro do Hostinger vinha exatamente daqui.

### 4.2 AFTER (estado corrigido pós-errata)

```bash
# composer install --no-dev --dry-run (mesmo cenário)
composer install --no-dev --dry-run

# Output: 190 installs, 0 updates, 0 removals
# (NENHUM pacote open-telemetry instalado — sumiram porque agora estão em packages-dev no lock)
```

Cenário CI (com dev) mantém os 7 pacotes OTel:

```bash
composer install --dry-run --ignore-platform-req=ext-opentelemetry

# Output: 278 installs, 0 updates, 0 removals
# - Installing open-telemetry/sem-conv (1.38.0)
# - Installing open-telemetry/context (1.5.0)
# - Installing open-telemetry/api (1.9.0)
# - Installing open-telemetry/sdk (1.14.0)
# - Installing open-telemetry/gen-otlp-protobuf (1.9.0)
# - Installing open-telemetry/exporter-otlp (1.4.0)
# - Installing open-telemetry/opentelemetry-auto-laravel (1.7.0)
```

Lock state após `composer update --no-install --no-plugins`:

```text
OTel/SPI em packages (require, instalado com --no-dev):       []  (era 7+1 antes)
OTel/SPI em packages-dev (require-dev, NAO instalado --no-dev): [open-telemetry/api,
                                                                  open-telemetry/context,
                                                                  open-telemetry/exporter-otlp,
                                                                  open-telemetry/gen-otlp-protobuf,
                                                                  open-telemetry/opentelemetry-auto-laravel,
                                                                  open-telemetry/sdk,
                                                                  open-telemetry/sem-conv,
                                                                  tbachert/spi]
Total packages: 190     (era 201 — perdeu 11 OTel+transitivos)
Total packages-dev: 88  (era 77 — ganhou 11)
```

Zero upgrades, zero downgrades, zero conflitos. Versões intactas — apenas a separação `packages` ↔ `packages-dev` mudou.

## 5. Arquivos modificados

| Arquivo | Mudança |
|---|---|
| `composer.json` | 3 pacotes OTel removidos de `require`, adicionados em `require-dev` (ordem alfabética preservada) |
| `composer.lock` | Regenerado via `composer update --no-install --no-plugins` — `content-hash` + reposicionamento dos 7 OTel + `tbachert/spi` em `packages-dev` (zero mudança de versão) |
| `.github/workflows/deploy.yml` | `--ignore-platform-req=ext-opentelemetry` adicionado ao `composer install` Hostinger + comentário explicativo |
| `memory/decisions/0166-errata-0162-otel-require-dev-hostinger.md` | Esta ADR |

## 6. Não-decisões (escopo fora)

- **NÃO** alterar [ADR 0162](0162-otel-collector-prod-observability.md) — append-only Tier 0 IRREVOGÁVEL. Errata é ADR nova complementar, não edit.
- **NÃO** mexer em `--no-dev` ou Faker — incidente 2026-04-25 mantido tratado pelo deploy.yml atual.
- **NÃO** propor split `composer.json` Hostinger ↔ CT 100 (sugestão em [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) §"Próximos passos" item 1) — backlog separado quando time MCP estiver onboardado.
- **NÃO** mexer em `OtelHelper.php`, `OtelServiceProvider.php` ou `config/otel.php` — código continua canônico fail-safe.
- **NÃO** mexer em CT 100 dockerfile, docker-compose ou env CT 100 — status quo ADR 0162 permanece.

## 7. Tier 0 IRREVOGÁVEIS reafirmadas

- ⛔ **Hostinger ≠ CT 100** ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md) §Runtime separado). Esta errata reafirma: Hostinger NÃO emite spans OTel reais — só CT 100 emite.
- ⛔ **NUNCA editar [ADR 0162](0162-otel-collector-prod-observability.md) ou outras ADRs canon aceitas** — append-only ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §3 + proibições.md §Memória/governança). Erratas viram ADRs novas com `related: [N]`.
- ⛔ **Multi-tenant Tier 0** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) intacto — esta errata não toca DB, modelos, scope global ou PII guard.
- ⛔ **PT-BR** em comentários e docs (mantido — código/identificadores OTel em inglês mantém compat SDK).

## 8. Métricas de sucesso

| Métrica | Target | Como medir |
|---|---|---|
| **Deploy Hostinger não-falha** após merge desta errata | `composer install` sai exit 0 | Próximo `gh workflow run deploy.yml` sai verde, log "Composer install (produção)" sem erro `ext-opentelemetry missing` |
| **CI GH Actions continua verde** | 100% suites passando | Tests Pest + ADR linter (`AdrFrontmatterLinterTest`) continuam verdes |
| **Vendor Hostinger não pesa >100MB extra** com OTel | <10MB extra | `du -sh vendor/open-telemetry vendor/tbachert` ≈5-7MB |
| **OtelHelper continua zero-cost no-op em Hostinger** | Spans NÃO emitidos | Hostinger sem `OTEL_ENABLED=true` → `config('otel.enabled') === false` → `OtelHelper::span` retorna direto, sem custo |
| **`composer install --no-dev` futuro** funciona limpo | 190 pacotes instalam sem warning OTel | Validação empírica §4.2 |

## 9. Comportamento esperado pós-merge

| Ambiente | `composer install` | OTel instalado? | OTel emite spans? | ext-opentelemetry? |
|---|---|---|---|---|
| **Hostinger prod** | `--ignore-platform-req=ext-opentelemetry` (sem `--no-dev`, mantém Faker) | ✅ instala em `vendor/open-telemetry/` (`packages-dev` mas Hostinger não usa `--no-dev`) | ❌ não (`OTEL_ENABLED=false` default + ext ausente = no-op) | ❌ ausente — ok, OtelHelper fail-safe |
| **CT 100 Proxmox** | `composer install` normal (docker-compose) | ✅ instala | ✅ emite (`OTEL_ENABLED=true` no `.env` CT 100 + ext-opentelemetry no dockerfile) | ✅ presente |
| **CI GH Actions** | `composer install` normal (PR #1018 instala PECL) | ✅ instala | ❌ não (test env, OTEL_ENABLED=false por default — só `OtelHelperTest` ativa pontualmente) | ✅ presente (PR #1018) |
| **Local Wagner** | `composer install` normal | ✅ instala (já que dev é incluso) | ❌ não default — Wagner ativa manual quando quer testar | depende do Herd local |

## Referências

- [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) — Separação dura runtime Hostinger ≠ CT 100 IRREVOGÁVEL (esta errata operacionaliza a separação no `composer.json`)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (§3 Charter > Spec + append-only ADRs + Princípio 8 confiabilidade com fallback validado pelo `OtelHelper`)
- [ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline LIVE prod biz=1 (consumidor de `OtelHelper::spanBiz` em `ExecuteStageActionService`, ADR 0162 §4)
- [ADR 0156](0156-module-grade-v3-errata-otel-helper-na-justified.md) — OtelHelper canônico ratificado (regex D9.a inclui facade)
- [ADR 0162](0162-otel-collector-prod-observability.md) — **ADR pai desta errata.** OTel Collector ativo em prod CT 100 — esta errata 0166 corrige `composer.json` field, NÃO altera arquitetura/decisão ADR 0162
- [PR #1018](https://github.com/wagnerra23/oimpresso.com/pull/1018) — Instala ext-opentelemetry via PECL no CI runner (já mergeado, complementar a esta errata)
- [proibicoes.md](../proibicoes.md) §Ambiente — "Nunca instalar `laravel/mcp` ou `laravel/octane` no Hostinger" (mesmo princípio de runtime separation aplicado a `ext-opentelemetry`)
- Sessão 2026-05-17 maratona OTel — origem catalogada do drift Wave 26 que esta errata fecha
