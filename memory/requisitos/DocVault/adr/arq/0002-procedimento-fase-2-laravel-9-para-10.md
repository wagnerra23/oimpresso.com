# ADR ARQ-0002 (DocVault) · Procedimento Fase 2 · Laravel 9.51 → 10.x

- **Status**: blocked (revisado 2026-04-23 — 2 dos 3 P0 eram P1 trivial)
- **Data**: 2026-04-22 · **Revisado**: 2026-04-23
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Implementa**: ADR arq/0001 Fase 2

## 🔄 REVISÃO 2026-04-23 — Reclassificação dos blockers

Wagner pesquisou versões e apontou que alguns "P0" têm upgrade direto disponível. Investigação via `composer show -a` corrigiu o quadro:

| Blocker | Classificação original | **Classificação correta** | Razão |
|---|---|---|---|
| nwidart/laravel-menus (fork) | P0 sem substituto | **P0 ainda** (fork dineshsailor não tem v10+) | Decisão: migrar pra React (ADR arq/0003) |
| yajra/laravel-datatables-oracle | P0 breaking changes | **~~P0~~ P1 trivial** | v10.11 aceita L9+L10 simultâneo — bumpa hoje |
| monolog v2→v3 | P0 transitivo | **~~P0~~ P1 automático** | Vem junto no `composer update` Laravel 10 |

**Novo status**: só **1 P0 real** (nwidart/laravel-menus). Os outros 2 que eu classifiquei como P0 eram P1 trivial que se resolvem em 1 minuto cada. Mea culpa da análise anterior.

**Implicação**: Fase 2 do upgrade Laravel 10 é **muito menos assustadora** do que o ADR original sugeria. Só resta:
1. P0: resolver `nwidart/laravel-menus` (ADR arq/0003 — migrar pra React)
2. P1 batch: bumpar pacotes em pequenas doses
3. Pular o framework depois que 1 e 2 estiverem verdes

## Contexto

Baseline atingida (todos módulos reais ≥88/100, 40/40 telas anotadas, 22/22 testes passando, OpenAI integrado). Pré-requisitos do ADR arq/0001 cumpridos. Momento de executar upgrade Laravel 9.51 → 10.x.

**Rollback point**: tag git `v-pre-upgrade-l10` criada antes de qualquer mudança.

## Decisão (procedimento executável)

### Passo 1 · Bump de dependências no composer.json

Versões compatíveis com Laravel 10 (verificadas em 2026-04-22):

```diff
- "laravel/framework": "^9.51",
+ "laravel/framework": "^10.0",

- "nwidart/laravel-modules": "^9.0",
+ "nwidart/laravel-modules": "^10.0",

- "spatie/laravel-permission": "^5.5",
+ "spatie/laravel-permission": "^6.0",

- "spatie/laravel-activitylog": "^4.4",
+ "spatie/laravel-activitylog": "^4.8",

- "spatie/laravel-backup": "^8.0",
+ "spatie/laravel-backup": "^8.5",

- "laravel/passport": "11.6.1",
+ "laravel/passport": "^12.0",

- "inertiajs/inertia-laravel": "^1.0",
+ "inertiajs/inertia-laravel": "^1.3",

- "barryvdh/laravel-dompdf": "^2.0",
+ "barryvdh/laravel-dompdf": "^2.2",

- "laravel/ui": "4.x",
+ "laravel/ui": "^4.2",
```

**Remover** (Laravel 10 não precisa):
- `laravel/legacy-factories` — migrações já usam `return new class`

**Manter verificando** (possível problema):
- `nwidart/laravel-menus 6.0.x-dev` (fork em github.com/dineshsailor) — blocker possível
- `mpdf` — incompatível com PHP 8.4
- `laravelcollective/html ^6.3` — última versão compatível Laravel 9

### Passo 2 · composer update

```bash
composer update --with-all-dependencies --ignore-platform-req=php
```

Esperar conflitos. Resolver um por vez.

### Passo 3 · Breaking changes aplicados em código

**Eloquent Models** — `$dates` removido, usar `$casts`:
```diff
-    protected $dates = ['published_at'];
+    protected $casts = ['published_at' => 'datetime'];
```

**Str helpers**: `Str::substrCount()` removido → usar `substr_count()`.

**Model observers**: sintaxe atualizada (não há breaking significativo).

**Queue**: `failed_jobs` table schema atualizado (migration disponível).

**Spatie Permission v6**: cache key mudou, rodar `php artisan permission:cache-reset` após upgrade.

### Passo 4 · Validação

```bash
php artisan migrate                   # roda migrations novas se necessário
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Testes
vendor/bin/phpunit Modules/PontoWr2/Tests/Feature/MultiTenantIsolationTest.php
vendor/bin/phpunit Modules/PontoWr2/Tests/Feature/SpatiePermissionsTest.php

# Audit tem que ficar ≥ baseline (DocVault 97, outros 88)
php artisan docvault:audit-module --all
```

### Passo 5 · Testar manualmente no browser
- Login (/login)
- Dashboard (/home)
- /docs (DocVault)
- /ponto/espelho
- /essentials/todo

### Passo 6 · Commit ou Rollback

**Se tudo ≥ baseline + testes passando:**
```bash
git commit -m "chore(upgrade): Laravel 9.51 -> 10.x (Fase 2 do ADR arq/0001)"
git tag v-post-upgrade-l10
```

**Se quebrar:**
```bash
git reset --hard v-pre-upgrade-l10
composer install
php artisan migrate:rollback  # se aplicou migrations
```

Documentar o que falhou num ADR arq/0003 e replanejar.

## Consequências esperadas

**Positivas:**
- Base pra Laravel 11/12/13 (cada major depende do anterior).
- Security patches ativos novamente (Laravel 9 EOL).
- Novos recursos: invokable validation rules, `Str` melhorias.

**Negativas possíveis:**
- Quebras em dependências fork custom (nwidart/laravel-menus).
- mpdf pode explodir em PHP 8.4 + Laravel 10.
- Testes 22/22 podem virar 19/22 por regressão.

## Blockers detectados em 2026-04-22

Comando `composer prohibits laravel/framework 10` revelou **9 pacotes bloqueando** o upgrade:

| # | Pacote | Versão atual | Problema | Solução |
|---|---|---|---|---|
| 1 | `arcanedev/support` | 9.0.0 | `illuminate/contracts ^9.0` | Upgrade pra `^11.0` (compat L10) |
| 2 | `milon/barcode` | 9.0.1 | `illuminate/support ^7-9` | Upgrade pra `^11.0` |
| 3 | `nwidart/laravel-menus` | 6.0.x-dev (fork) | `illuminate/* ^9.44` | Verificar se fork dineshsailor tem v7 (L10); caso não, **criar fork próprio** ou migrar pra nwidart oficial |
| 4 | `openai-php/laravel` | v0.4.1 | Aceita `^9.46 OR ^10.4.1` ✅ | **Já compatível** — sem mudança |
| 5 | `shalvah/upgrader` | 0.3.0 | `illuminate/support ^8-9` (dev) | Remover — lib só-dev, não crítica |
| 6 | `spatie/laravel-ignition` | ^1.4 | `illuminate/support ^8.77-9.27` | Upgrade pra `^2.0` |
| 7 | `unicodeveloper/laravel-paystack` | 1.0.9 | `illuminate/support ~6-9` | Upgrade pra `^2.0` (ou remover se não usa Paystack) |
| 8 | `yajra/laravel-datatables-oracle` | v9.21.2 | `illuminate/* ^5-9` | Upgrade pra `^10.0` — **breaking changes em API** |
| 9 | `monolog/monolog` | 2.9.1 | Laravel 10 requer `^3.0` | Upgrade transitivo, mas **pacotes velhos podem exigir v2** |

### Classificação de risco

**P0 — Blocker crítico** (sem substituição imediata):
- **nwidart/laravel-menus** — fork dedicado (dineshsailor). Se autor não atualizar pra L10, precisamos forkar ou abandonar (usar menu manual em Blade/React).
- **yajra/laravel-datatables-oracle** — muito usado no código legado Blade. v10 mudou API. Cada tela Blade com DataTables vai precisar ajuste.
- **monolog v2 → v3** — breaking. Alguns pacotes antigos (consoletvs/charts, knox/pesapal) podem exigir v2.

**P1 — Upgrade padrão disponível:**
- arcanedev/support (v11 existe), milon/barcode (v11), spatie/laravel-ignition (v2).

**P2 — Remover se não usa:**
- shalvah/upgrader (ferramenta CLI desacoplada), unicodeveloper/laravel-paystack (se Paystack não é usado).

**P3 — Revisar antes:**
- openai-php/laravel já compatível ✅.
- knox/pesapal, myfatoorah, razorpay, stripe v7.122 — gateways antigos, precisam teste pós-upgrade.
- mpdf ^8.1 — incompatível com PHP 8.4 (já conhecido), pode explodir em L10.
- consoletvs/charts ^6.5 — **abandonado desde 2020**, possível blocker transitivo.

### Decisão de execução

**NÃO executar `composer update` hoje.** O risco de quebrar 30 módulos + portal cliente é real. Cada P0 precisa ser resolvido **primeiro**, em sub-ADRs dedicados:

- **ADR arq/0003** (proposto): migrar `nwidart/laravel-menus` — forkar, substituir por React menu, ou outro caminho
- **ADR arq/0004** (proposto): `yajra/laravel-datatables-oracle` v10 — inventariar usos, plano de migração por tela
- **ADR arq/0005** (proposto): `consoletvs/charts` abandonado — substituir por Chart.js direto ou Recharts
- **ADR arq/0006** (proposto): gateways de pagamento antigos — testar e atualizar conforme demanda

### Caminho prático sugerido

**Opção A — Upgrade completo "big bang"** (1 sessão de ~8h):
- Wagner aloca dia dedicado
- Resolve os 9 blockers em paralelo
- Commit único "Laravel 10" com tudo

**Opção B — Incremental "strangler fig"** (recomendado):
1. Semana N: remover deps desnecessárias (shalvah, paystack se não usa)
2. Semana N+1: atualizar P1s (arcanedev, milon, ignition)
3. Semana N+2: resolver P0 nwidart/laravel-menus (ADR dedicado)
4. Semana N+3: resolver P0 yajra/datatables
5. Semana N+4: fazer o bump do framework — quando só sobraram deps compatíveis

Opção B mantém app sempre green. Opção A arrisca quebrar produção.

### Plano imediato (esta sessão)

1. ✅ ADR arq/0002 documentando blockers (este arquivo)
2. ✅ Tag `v-pre-upgrade-l10` mantida como rollback
3. ⏳ Aguardar Wagner decidir entre Opção A e B
4. ⏳ Se B: criar ADR arq/0003..0006 com detalhes de cada P0

Nenhum código em `composer.json` modificado hoje. **Sistema fica estável em Laravel 9.51.**

## Alternativas consideradas

- **Executar `composer update` mesmo assim**: rejeitado — 9 pacotes bloqueiam, certeza de quebra.
- **Não fazer upgrade agora**: rejeitado, ADR arq/0001 já fechou essa porta.
- **Pular pra Laravel 13 direto**: rejeitado no próprio ADR arq/0001 (breaking changes acumuladas).
