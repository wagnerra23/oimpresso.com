---
id: requisitos-jana-pii-redaction
---

# PII Redaction — Modules/Jana

> Documento D7 LGPD — declara onde e como PII é tratada antes de log/telemetria.

## Implementação canônica

PII de prompts/responses/telemetria Jana é redactada via:

- **Serviço:** `Modules\Jana\Services\Privacy\PiiRedactor`
- **Cobertura:** logs estruturados, traces OTel/Langfuse, snapshots de contexto

## Pontos de uso atuais

| Arquivo | Uso |
|---|---|
| `Modules/Jana/Http/Controllers/ChatController.php` | redact em mensagens antes de log |
| `Modules/Jana/Services/Telemetry/LangfuseClient.php` | redact em payloads telemetry |
| `Modules/Jana/Services/Ai/LaravelAiSdkDriver.php` | redact em prompt/response |

## Padrões redactados

CPF/CNPJ, telefone BR (com DDD), e-mail, cartão de crédito, RG (heurística), endereço completo.

## Kill-switch `COPILOTO_PII_REDACT_DISABLE` — escape de emergência (estado medido 2026-07-28)

`LaravelAiSdkDriver::mascararDocumentos()` tem um escape que, se acionado, troca o
`PiiRedactor` canônico (5 tipos BR) pelo regex inline antigo — que cobre **apenas
CPF e CNPJ formatados**. Ou seja: acionar a flag **degrada** a redação (e-mail, CEP
e telefone BR deixam de ser mascarados antes de ir pro provedor externo).

```php
if (env('COPILOTO_PII_REDACT_DISABLE', false)) { /* regex antigo, só CPF+CNPJ */ }
```

**Ela está INOPERANTE em produção — e falha para o lado seguro.** Medido em prod
(Hostinger) em 2026-07-28:

| medição | resultado |
|---|---|
| `configurationIsCached()` | `true` (deploy roda `php artisan config:cache`, [deploy.yml:388](../../../.github/workflows/deploy.yml)) |
| `env('COPILOTO_PII_REDACT_DISABLE', false)` | `false` |
| `env('APP_ENV')` — **controle negativo** | `NULL` (a chave existe no `.env`) |
| a chave no `.env` de prod | ausente |

O controle negativo é o que fecha: `APP_ENV` existe no `.env` e ainda assim `env()`
devolve `NULL`. Com o config cacheado, `LoadEnvironmentVariables::bootstrap()` retorna
antes de carregar o Dotenv, então **nenhuma** chamada `env()` fora de `config/` enxerga
o `.env`. Consequências:

- ✅ **Sem risco de vazamento hoje** — a flag resolve sempre `false`, logo o
  `PiiRedactor` completo está sempre ativo. O fail-safe aponta para o lado certo.
- ⚠️ **O escape de emergência não existe na prática** — escrever
  `COPILOTO_PII_REDACT_DISABLE=true` no `.env` de produção não desliga nada. Quem
  precisasse dele numa regressão acharia que desligou e não teria desligado.

**Decisão: manter a capacidade, não removê-la em silêncio** — mas ela é hoje uma
promessa não cumprida, e isso fica registrado aqui em vez de ficar só no código.

### Passo pendente (não executado nesta rodada)

Migrar o consumidor para `config('copiloto.pii.redact_disable')` e **logar em
`copiloto-ai` quando acionada** (hoje o desvio é silencioso: nada distingue nos logs
uma resposta redigida pelo `PiiRedactor` de uma redigida pelo regex degradado).

Não foi feito porque `Modules/Jana/Services/Ai/LaravelAiSdkDriver.php` estava sob
edição por sessão paralela nesta janela. A chave **não** foi adicionada ao
`Modules/Jana/Config/config.php` de propósito: config presente sem consumidor
migrado leria como cobertura que não existe. As irmãs desta mesma família
(`COPILOTO_PROMPT_CACHE_*`, `AI_*`) já foram migradas — ver seção seguinte.

## Chaves da camada de IA migradas para `config/` (2026-07-28)

Mesma causa-raiz, mesmo diagnóstico:

| chave | era | virou | default (inalterado) |
|---|---|---|---|
| `COPILOTO_PROMPT_CACHE_ENABLED` | `env()` em `PromptCacheConfig` | `copiloto.prompt_cache.enabled` | `true` |
| `COPILOTO_PROMPT_CACHE_MIN_CHARS` | `env()` em `PromptCacheConfig` | `copiloto.prompt_cache.min_chars` | `4096` |
| `AI_ENABLED` | `getenv()`/`env()` | `pontowr2.ai.enabled` | `false` |
| `AI_CLASSIFICACAO_INTERCORRENCIA` | `getenv()`/`env()` | `pontowr2.ai.classificacao_intercorrencia` | `false` |
| `AI_EXPLICACAO_DIVERGENCIA` | `env()` no middleware | `pontowr2.ai.explicacao_divergencia` | `false` |
| `AI_GERACAO_JUSTIFICATIVA` | `env()` no middleware | `pontowr2.ai.geracao_justificativa` | `false` |
| `OPENAI_MODEL` | `env()` no classifier | `pontowr2.ai.model` | `gpt-4o-mini` |

`OPENAI_MODEL` não constava do levantamento original — apareceu na varredura do
mesmo arquivo. Avaliadas dentro de `config/`, as `env()` rodam durante o próprio
`php artisan config:cache`, quando o `.env` ainda está carregado, e o valor fica
congelado no cache: as flags voltam a responder a `.env` + `config:cache`.

## Tests

`Modules/Jana/Tests/Unit/PiiRedactorTest.php` — cobre os 6 padrões + edge cases.

## Cross-ref

- ADR 0093 (multi-tenant Tier 0) — PII NUNCA cruza tenant
- skill `commit-discipline` (Tier A) — bloqueia PII real em commit/PR

## Compliance LGPD D7

- ✅ D7.a Pii Redaction — `PiiRedactor` ativo
- ✅ D7.b Audit Trail — telemetria não persiste PII bruto

---
**Última atualização:** 2026-07-28 — kill-switch `COPILOTO_PII_REDACT_DISABLE` documentado
(inoperante em prod por `config:cache`, fail-safe pro lado seguro) + tabela das 7 chaves
da camada de IA migradas para `config/`.

**2026-05-16** — Onda 3 mass D7 application
