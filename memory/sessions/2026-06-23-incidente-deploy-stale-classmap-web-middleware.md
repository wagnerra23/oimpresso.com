---
date: 2026-06-23
hour: "16:00 BRT"
duration: "2h"
topic: "Incidente prod 500 (recorrente) por classmap-authoritative stale — variante middleware WEB; hardening do deploy.yml + verify-classmap + avaliação release atômico"
authors: [W, C]
outcomes:
  - "deploy.yml endurecido: verify-classmap pós-dump (re-dump 1×), boot gate WEB atrás do maintenance bypass antes do up, self-heal do opcache_reset_token, failsafe honra os gates"
  - "scripts/deploy/verify-classmap.php — gate determinístico léxico+classmap (cobre Handler + middleware web/global)"
  - "Avaliação de release atômico (symlink swap) documentada — recomendado como ADR separado de esforço médio"
prs: []
us: []
related_adrs: ["0269-deploy-automatico-build-no-runner"]
---

# Session log 2026-06-23 — Prod 500 recorrente por classmap stale (variante middleware WEB)

## TL;DR

Prod (oimpresso.com) caiu **500 em /login e toda rota web** porque o deploy do commit `a9d0593ff` (#3284) adicionou `App\Http\Middleware\VisregStateMiddleware` ao grupo `web` (`app/Http/Kernel.php:59`), mas o `vendor/composer/autoload_classmap.php` **`--classmap-authoritative` não continha a classe** → `BindingResolutionException: Target class […VisregStateMiddleware] does not exist` em `handle()` e `terminateMiddleware()`. O run do deploy `a9d0593ff` aparece **`cancelled`** — cancelado/superado no meio, entre `git reset --hard` (source avançou) e o `composer dump-autoload` (classmap ficou stale). É a **mesma família** do incidente 2026-06-18 (`ErrorReporter` no Handler), mas numa **variante WEB** que o boot-smoke console (`php artisan about`) **não pega**: o kernel CLI não monta o pipeline HTTP. Recuperação manual aplicada (dump-autoload -o --classmap-authoritative + optimize:clear + `find -exec touch` + reset OPcache) → login 200. Esta sessão endurece o `deploy.yml` pra não recorrer.

## Contexto

Incidente recorrente já catalogado (`incidente-deploy-stale-classmap-500`, [PR #2952](https://github.com/wagnerra23/oimpresso.com/pull/2952), `deploy-recovery-patterns.md` §9/§10). O `--classmap-authoritative` **desliga o fallback PSR-4 de scan de filesystem**: uma classe nova fora do classmap = irresolvível = 500. As defesas existentes (boot-smoke console `php artisan about` #2912 + failsafe boot-gated #2952) protegem o **console/cron**, mas **`about` boota mesmo com middleware WEB quebrado** — o gap explorado em 2026-06-23.

## Cronologia (2026-06-23, runs do deploy.yml)

| Quando (UTC) | Evento |
|---|---|
| 18:25 | deploy `a9d0593ff` (#3284, "L5 serve --no-reload") → **cancelled** (superado/cancelado no meio) |
| 18:32 | deploy `9bd43d263` → success |
| 18:36 | deploy `8b976389c` ("diag L5 curl probe TEMPORARIO") → success |
| ~18:4x | prod observado 500 em /login (middleware web irresolvível); `grep -c VisregStateMiddleware autoload_classmap.php` = 0 |
| ~18:5x | recuperação manual: `composer dump-autoload -o --classmap-authoritative …` + `optimize:clear` + `find … -exec touch` + reset OPcache → login 200 |

Pista de race: o `.php` do middleware tinha mtime 18:25 enquanto `bootstrap/cache/config.php` (do `config:cache`) era 18:22 — source à frente do classmap, assinatura de deploy cancelado/sobreposto (mesma física de `deploy-recovery-patterns.md` §10).

Desta vez o erro **apareceu** no `laravel.log` (resolução de middleware é pós-logger) — diferente do caso Handler/ErrorReporter de 2026-06-18, que é pré-logger.

## Causa raiz

`--classmap-authoritative` + classe nova fora do classmap = 500. O classmap fica stale quando **um deploy é cancelado/superado entre `git reset --hard origin/main` e `composer dump-autoload`** (ou source à frente do classmap num flurry de merges). A variante 2026-06-23 atingiu **middleware do grupo web**, que **só** é instanciado no pipeline HTTP — logo o `php artisan about` (CLI) **não reproduz** a falha e o deploy/failsafe a deixam passar.

## Entregas (PR de hardening — `fix/deploy-stale-classmap-hardening`)

Escopo **deploy/CI only** — zero mudança em runtime de cliente.

- **`scripts/deploy/verify-classmap.php`** (~110 linhas) — gate determinístico. Escaneia (léxico, `token_get_all`, ignora comentários e declarações de `namespace`) as classes `App\` referenciadas em `app/Http/Kernel.php`, `app/Exceptions/Handler.php`, `app/Console/Kernel.php`, `bootstrap/app.php` e confere pertencimento ao `vendor/composer/autoload_classmap.php`. Sem bootar o app, sem incluir classe (zero efeito colateral). `isset($classmap[$fqcn])` reproduz fielmente a resolução autoritativa. Saída `CLASSMAP_OK`/`CLASSMAP_MISSING: <fqcns>` + exit 0/1. Validado E2E (com/sem `VisregStateMiddleware`, e false-positive de namespace eliminado).
- **`deploy.yml` — objetivo #1 (verificação pós-dump):** novo step "Verifica classmap autoritativo (re-dump 1× se stale)" logo após o `dump-autoload`, AINDA em maintenance ON e ANTES do migrate. Se faltar classe → re-roda dump-autoload 1× e re-verifica; se ainda faltar → **falha o deploy** (site fica em 503 gracioso, nunca expõe o 500).
- **`deploy.yml` — objetivo #3 (boot gate WEB antes do up):** `php artisan down --secret=<token aleatório>` no "Maintenance mode ON" cria rota de bypass; novo "Reset OPcache (pré-gate)" + "Boot gate WEB" smokam `/login` no **SAPI real** com o cookie de bypass **ANTES** de tirar o maintenance (usuário comum segue vendo 503). 5xx confirmado → mantém 503 + deploy vermelho. **Fail-safe-degradante**: resultado ambíguo (bypass não engatou) **não** bloqueia. O failsafe foi ensinado a honrar `classmap`/`webgate` (o `about` não reproduz middleware web).
- **`deploy.yml` — objetivo #4 (opcache_reset_token):** "Garante token" agora é **self-heal** (seed do secret → reusa arquivo válido → gera no servidor; `test -s` garante não-vazio) e o reset HTTP (pré-gate e pós-up) **lê o token do arquivo do servidor** — a mesma fonte que `_ops_opcache_reset.php` usa — então o token do curl **sempre casa**, mesmo com secret/arquivo divergentes.

## Objetivo #2 — Avaliação de release atômico (symlink swap)

**Pergunta:** montar a release nova numa pasta, rodar composer/dump-autoload/migrate lá, e só então trocar um symlink `current` atomicamente — pra que um deploy interrompido/cancelado **nunca toque o que está no ar** (que é exatamente a causa-raiz das recorrências).

**Veredito: FEASÍVEL no Hostinger, porém esforço MÉDIO — recomendado como ADR separado, não neste PR.** Esta entrega (verify + boot gate WEB) é o paliativo de baixo risco que fecha a recorrência imediata; o release atômico é o fix durável da classe inteira "estado parcial por deploy cancelado".

Restrições/decisões de design a resolver no ADR:

1. **Docroot fixo.** Hoje o app vive em `~/domains/oimpresso.com/public_html` e o git tracked tree é o próprio webroot. Capistrano-style exige app FORA do webroot + `public_html` (ou `public_html/public`) virar **symlink** pra `releases/<ts>/public`. Hostinger permite symlink no home, mas trocar o `public_html` por symlink precisa ser validado no painel (alguns recursos do hPanel assumem `public_html` diretório real). **Item de verificação #1.**
2. **Cota de disco.** Cada release carrega `vendor/` (centenas de MB) + bundles. Manter N releases multiplica disco — e já há pressão de cota (`handoff cota-disco`). Mitigação: manter **2 releases** (current + previous pra rollback), `shared/` pra `storage/` + `.env` symlinkados.
3. **Migrations não são atômicas com o swap.** DDL roda contra o banco compartilhado entre releases. Exige **disciplina expand/contract** (migration aditiva primeiro, remoção só depois do swap consolidado) — senão a release velha quebra durante a janela. É a mudança de processo mais cara.
4. **OPcache + realpath cache no symlink swap.** LSPHP indexa por realpath; trocar o alvo do symlink pode servir bytecode/realpath velho. Precisa reset de OPcache + considerar `opcache.revalidate_path` / realpath cache TTL após o swap (gotcha clássico Capistrano+OPcache).
5. **Build no runner (ADR 0269) compõe bem:** os bundles buildados no runner são publicados na `public/` da release nova antes do swap — sem build no shared host.

**Recomendação:** abrir ADR "deploy por release atômico (symlink swap)" com PoC dos itens 1 e 4 (os de maior incerteza no Hostinger) antes de comprometer. Ganho: deploy cancelado/interrompido vira **no-op** sobre o que está no ar — elimina a causa-raiz, não só o sintoma.

## Aprendizados / pegadinhas

- **`php artisan about` (CLI) NÃO reproduz middleware WEB quebrado** — o kernel CLI não monta o pipeline HTTP. Boot-smoke console e web são eixos distintos; precisa dos dois.
- **Cancelar deploy no meio = estado parcial** (já avisado em §10): com `concurrency: cancel-in-progress: false`, runs pendentes superados são cancelados; mas um run **em andamento** cancelado (manual ou pelo GitHub) pode parar entre `git reset` e `dump-autoload` → classmap stale. **NÃO trocar pra `cancel-in-progress: true`** (pioraria — §10). Mitigação de processo: **serializar/bachar merges** e deixar o deploy fechar antes do próximo (crítico de madrugada).
- **Token OPcache deve ser lido do arquivo do servidor, não só do secret** — desacopla de drift e garante que o curl casa com o que o endpoint lê.
- **Verify fail-closed**: um bug no `verify-classmap.php` bloquearia deploys. Mitigado por testes (php -l + E2E) e por o erro vir com a classe nomeada; o reviewer vê o motivo.

## Próximos passos (não-bloqueante)

- [ ] Abrir ADR "release atômico (symlink swap)" com PoC dos itens 1 (docroot symlink) e 4 (opcache/realpath) — esforço médio.
- [ ] Avaliar guard shell no crontab do Hostinger (pular `schedule:run` em maintenance) — §10, defesa-em-profundidade do cron na janela de flurry.
- [ ] Quando o time ganhar cadência de merge, documentar "bach de merges + watch do deploy" como norma operacional.

## Referências

- Memória: `incidente-deploy-stale-classmap-500` (auto-mem Wagner) · [PR #2952](https://github.com/wagnerra23/oimpresso.com/pull/2952)
- Canon: [deploy-recovery-patterns.md](../reference/deploy-recovery-patterns.md) §9/§10/§11 · [ADR 0269](../decisions/0269-deploy-automatico-build-no-runner.md)
- Código: [.github/workflows/deploy.yml](../../.github/workflows/deploy.yml) · [scripts/deploy/verify-classmap.php](../../scripts/deploy/verify-classmap.php) · [public/_ops_opcache_reset.php](../../public/_ops_opcache_reset.php)
