---
date: 2026-05-29
topic: "PHPStan baseline shrink — 15 erros level-5 que vazaram pra main sem ratchet (Gov + Brief + Jana)"
status: pronto pra PR
branch: fix/phpstan-baseline-shrink-gov-brief-jana
base: origin/main e14f8ac11
---

# RUNBOOK — PHPStan baseline shrink (Governance + Brief + Jana)

## Por que existe

O ratchet PHPStan (level 5, `phpstan.neon.dist` + `phpstan-baseline.neon`) **só roda em PR**, não em push direto pra `main`. Resultado: trabalho recém-mergeado (NpmAuditChecker #1887, Meilisearch/DriftChecker config-as-code #1945/#1948, RoutesZombieChecker ADR 0221) acumulou **15 erros level-5 que NÃO estavam no baseline** — vazaram silenciosamente. Detectado ao consertar o ratchet no PR #1939; lá o baseline foi regenerado pra absorver os 15 só pra destravar. Este PR é a **dívida reversa**: consertar no código e REMOVER as entradas do baseline (baseline deve ENCOLHER).

Reproduzir na main limpa: `vendor/bin/phpstan analyse --memory-limit=1G`.

## Os 15 erros → 9 itens → resolução

| # | Arquivo:linha | Identifier | Resolução | Tipo |
|---|---|---|---|---|
| 1 | `Brief/Services/BriefGeneratorService.php:50` | `method.unused` (`targetTokens()`) | **Removido** método morto (0 refs no repo) | fix código |
| 2 | `Brief/Services/BriefGeneratorService.php` | `larastan.noEnvCallsOutsideOfConfig` (era 1, virou 2) | `env('OPENAI_API_KEY')` → `config('services.openai.api_key')` + nova chave `services.openai` em `config/services.php` | fix código |
| 3 | `Governance/Config/config.php` | `larastan.noEnvCallsOutsideOfConfig` (3→5) | **Baseline bump deliberado 3→5** (env() dentro de config file é idiomático Laravel) | baseline |
| 4 | `Governance/Services/Checkers/NpmAuditChecker.php:208` | `nullCoalesce.offset` | `$advisory['title'] ?? '...'` → `$advisory['title']` (offset sempre existe — `isset($via['title'])` filtra antes, linha 173) | fix código |
| 5 | `Governance/Services/Checkers/RoutesZombieChecker.php:39` | `classConstant.unused` (`DEFAULT_ZOMBIE_GRACE_DAYS`) | **Removida** const não usada | fix código |
| 6 | `Governance/Services/Checkers/RoutesZombieChecker.php:149` | `foreach.nonIterable` | `Route::getRoutes()` → `Route::getRoutes()->getRoutes()` (facade devolve `RouteCollectionInterface` não-iterável; `->getRoutes()` devolve `Route[]`) | fix código |
| 6b | `Governance/Services/Checkers/RoutesZombieChecker.php:156` | `function.alreadyNarrowedType` (exposto pelo #6) | `is_string($route->getActionName()) ? ... : 'Closure'` → `$route->getActionName()` (core já devolve `'Closure'` p/ closures; `getActionName(): string`) | fix código |
| 7 | `Governance/Services/Concerns/PersistsDriftAlert.php:27` | `trait.unused` | **`ignoreErrors` pontual** (NÃO baseline, NÃO remoção) | falso-positivo |
| 8 | `Governance/Services/Concerns/PublishesDriftToCentrifugo.php:32` | `trait.unused` | **`ignoreErrors` pontual** | falso-positivo |
| 9 | `Jana/Config/config.php` | `larastan.noEnvCallsOutsideOfConfig` (68→76) | **Baseline bump deliberado 68→76** | baseline |

### Por que #7 e #8 são FALSOS-POSITIVOS (não débito)

Os dois traits **são usados** por `Modules/Governance/Console/Commands/GovernanceAuditCommand.php`:
- linhas 44-45 `use PersistsDriftAlert; use PublishesDriftToCentrifugo;`
- linha 165 `$this->persistirDriftAlert(...)` · linha 171 `$this->publishDriftToCentrifugo(...)`

Esse command é o orchestrator do `governance:audit` (ADR 0216), **agendado em produção** (`app/Console/Kernel.php:752`, daily 06:35 BRT). Mas `Modules/*/Console/*` está em `excludePaths` do PHPStan (bug larastan 3.10 com PHP 8.4 + Symfony Command — documentado em `phpstan.neon.dist:30-35`). Como o consumidor não é analisado, o PHPStan acha que os traits são "usados zero vezes". **Remover os traits quebraria `governance:audit` em prod.** Solução canônica: `ignoreErrors` por `identifier: trait.unused` + `path:`, com comentário explicando — não baseline (baseline é pra débito real), não remoção.

### Por que #3 e #9 são baseline bump LEGÍTIMO

`env()` dentro de `Modules/<X>/Config/config.php` é o padrão idiomático do Laravel pra ler config. O larastan `noEnvCallsOutsideOfConfig` só whitelista a raiz `config/`, não `Modules/*/Config/`. Por isso **dezenas** de módulos já têm essa entrada no baseline (KB=13, NfeBrasil=8, Admin=7, Financeiro=4...). Os novos env() vieram de merges recentes (Governance: drift framework; Jana: bloco Meilisearch config-as-code do #1948). São config legítima → o tratamento correto é o **bump documentado da contagem** (não mover pra fora de config, que quebraria o padrão).

⚠️ **Jana é 76, não 71.** O brief da task estimou +3 (68→71); na `main` real (`e14f8ac11`, pós-#1948) são +8 (68→76) — o bloco `meilisearch_indexes` config-as-code adicionou mais `env()` do que a task previa. Os 76 são todos env() de config legítima (verificado).

## Resultado

- `phpstan analyse --generate-baseline=phpstan-baseline.neon` → exit 0.
- `phpstan-baseline.neon`: **37146 → 37140 linhas** (`git diff` = 2 inserções / 8 deleções).
- Diff do baseline = **exatamente 3 mudanças, nada mais**:
  - entrada `Brief/Services/BriefGeneratorService.php` **REMOVIDA**
  - `Governance/Config/config.php` count **3→5**
  - `Jana/Config/config.php` count **68→76**
- **Validação final: `phpstan analyse --memory-limit=1G` → `[OK] No errors`.**
- Pest verde: Brief Wave28 (2), Governance drift framework (33: Registry+AuditCommand+Meili+Deploy), novo RoutesZombieCheckerTest (2). AuditCommandTest cobre os traits → prova `governance:audit` runtime OK.

## Garantia anti-drift (o ponto-chave deste PR)

O baseline foi **regenerado** (não hand-editado) e depois **diffado** pra garantir que SÓ as 3 entradas esperadas mudaram. Regenerar cegamente arriscaria absorver QUALQUER outro drift acumulado — exatamente o problema que este PR conserta. Confirmado também que regenerar no Windows não introduz platform-drift vs o baseline Linux commitado: contagens de identifiers platform-sensitive idênticas (nullsafe.neverNull/booleanOr/identical/equal = 0 ambos; function.alreadyNarrowedType 28=28; trait.unused 1=1; só `larastan.noEnvCallsOutsideOfConfig` 79→78 = a remoção do Brief).

## Arquivos do PR (6)

```
Modules/Brief/Services/BriefGeneratorService.php       (-6)  método morto + env→config
Modules/Governance/Services/Checkers/NpmAuditChecker.php (1) nullCoalesce
Modules/Governance/Services/Checkers/RoutesZombieChecker.php (-2/+4) const + foreach + is_string
config/services.php                                    (+13) chave services.openai
phpstan.neon.dist                                      (+14) 2 ignoreErrors trait.unused
phpstan-baseline.neon                                  (+2/-8) shrink
Modules/Governance/Tests/Feature/RoutesZombieCheckerTest.php (novo) guard do fix foreach
```

## Pegadinhas catalogadas

1. **Ratchet só roda em PR** — pushes diretos pra main não passam pelo gate. Erros vazam. (Causa-raiz desta dívida — considerar gate em push pra main no futuro.)
2. **composer install no worktree FALHA** (horizon precisa `ext-pcntl`, ausente no PHP Herd CLI) → usar **junction NTFS** `vendor` → `D:\oimpresso.com\vendor`.
3. **Larastan boota o Laravel** durante análise → worktree precisa do esqueleto `storage/framework/{views,cache,sessions}` ou erro "Please provide a valid cache path".
4. **`config:cache` + env() fora de config/** = bug silencioso em prod (env() retorna null). Por isso a chave OpenAI foi pra `config/services.php` e o service lê via `config()`.
5. **`main` deriva mais rápido que estimativas de task** — sempre regenerar+diffar o baseline, nunca hand-editar contagens (Jana era +8 real vs +3 estimado).
