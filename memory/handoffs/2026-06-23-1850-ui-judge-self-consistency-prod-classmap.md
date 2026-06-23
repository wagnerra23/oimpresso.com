---
date: "2026-06-23"
time: "18:50 BRT"
slug: ui-judge-self-consistency-prod-classmap
tldr: "Robustez do PR UI Judge (L6) contra alucinar 'ok': UiJudgeConsensus roda N amostras, agrega a mediana, deriva confiança da concordância e abstém (zona cinza) — sem trocar de modelo (gpt-4o-mini, decisão [W]). PR #3280 MERGED (Jana 71→73). No meio, P0 de prod: 500 site-wide (classmap --authoritative stale sem VisregStateMiddleware do deploy #3284) — recuperado via dump-autoload+optimize:clear+opcache. Chip task_0817ee73 pra endurecer deploy."
decided_by: [W]
cycle: CYCLE-08
prs: [3280]
related_adrs: ["0252-provider-llm-default-openai-camada-a"]
next_steps:
  - "Calibrar a visão do gpt-4o-mini pro golden-visual (screenshot novo vs ouro) antes de cablar"
  - "PR2: roteamento da zona-cinza + composição com o double-threshold do pixel-diff (§3a/§3c)"
  - "Endurecer deploy vs classmap stale (task_0817ee73): dump-autoload pós-arquivos / release atômico / boot-gate curl /login==200; regenerar opcache_reset_token"
---

# PR UI Judge robusto (self-consistency) + P0 de prod (classmap stale)

## Estado MCP no momento
MCP `oimpresso` **conectado** (cycles-active + my-work responderam). Cycle **CYCLE-08** "Receita — Onda A" 82% decorrido, 5 dias restantes — este trabalho é **off-cycle** (juiz de UI L6, não está nos goals de receita). Branch de trabalho: worktree `ui-judge-robusto` off `origin/main`.

## O que aconteceu
Tarefa: robustecer o LLM-judge de UI (`PrUiJudgeAgent`, camada L6) pra **parar de alucinar "ok"**. O pré-req bloqueante era decisão [W] sobre modelo com **visão** (golden-visual). Apresentei o estado real + a descoberta-chave do dossiê `2026-06-23-arte-validacao-L3`: **visão só é necessária pro golden-visual**; self-consistency + confiança rodam no `gpt-4o-mini` atual sem trocar de modelo. [W] decidiu: **(1)** golden-visual no próprio gpt-4o-mini (testar a visão do mini antes de escalar pra gpt-4o/Claude), **(2)** construir a robustez sem-visão **agora**.

Construí a peça sem-visão (**PR #3280**): `UiJudgeConsensus` roda o juiz **N=3** vezes (`#[Temperature(0.7)]` é obrigatório — sem variância as amostras seriam idênticas e a confiança seria FALSA), agrega a **MEDIANA** das 3 dims semânticas (mata o single-shot com sorte), deriva **confiança** da concordância (`1 - spread/10`, geral = a menor dim), e o command **rebaixa um "approve" de baixa-confiança pra "comment" + zona-cinza** (anti-"alucina ok"; seguro pro CI — comment = exit 0). Sem ADR-superseding-0252.

**No meio, P0 de prod**: [W] reportou `500` em `/financeiro/unificado`. Diagnóstico via SSH (`laravel.log`): era 500 **site-wide** (`/login` também 500), causa = autoloader **`--classmap-authoritative` stale** — `App\Http\Middleware\VisregStateMiddleware` (deploy `a9d0593ff` #3284, registrado em `app/Http/Kernel.php:59` grupo web) **ausente do classmap** (`grep -c`=0) → `BindingResolutionException` em todo request web. `artisan about` bootava (CLI não monta middleware HTTP) → parecia route-specific. Recuperado pela receita canônica.

## Artefatos gerados
- **PR #3280** (MERGED squash `600adcc86`, by [W]) — `UiJudgeConsensus` (puro/testável) + `#[Temperature(0.7)]` no agent + gate de confiança no `UiJudgePrCommand` + config `copiloto.ui_judge` (hardcoded 3/0.6) + colunas `confidence`/`samples` (migration idempotente) + 10 testes da agregação pura + catraca **R-JANA-UI-JUDGE-005** + registro no `ci-sqlite-pest.list`. Jana module-grade **71→73**. 4 commits (feature + 3 fixes PHPStan).
- **Prod recuperado** — `composer dump-autoload -o --classmap-authoritative` (19889 classes, classe agora indexada) + `optimize:clear` + `find -exec touch` (bustou OPcache por mtime). Sem mutação de dados/schema. Verificado: `/login` 200, `/`, `/home`, `/financeiro/unificado` saudáveis.
- **Chip `task_0817ee73`** — endurecer deploy vs classmap stale (recorrência do 18/jun) + regenerar `opcache_reset_token` vazio.

## Persistência
- Git canon: #3280 em `main` (`600adcc86`). Este handoff via PR `docs/handoff-2026-06-23-ui-judge-prod-classmap`.
- MCP: trabalho off-cycle, sem US/task (não mapeia goal de receita do CYCLE-08). Chip de follow-up criado.
- BRIEFING: n/a (Jana, sem tela de produto tocada).

## Próximos passos pra retomar
1. **golden-visual**: calibrar a visão do `gpt-4o-mini` (screenshot novo vs ouro do arquétipo) — [W] escolheu testar o mini antes de escalar.
2. **PR2**: roteamento da zona-cinza + composição com o double-threshold do pixel-diff — só o que AMBOS marcam duvidoso (ou o juiz marca baixa-confiança) sobe pro olho do [W].
3. **deploy hardening** (`task_0817ee73`): garantir `dump-autoload` **depois** de todos os arquivos aterrissarem / release atômico (symlink swap) / boot-gate `curl /login==200` antes de tirar maintenance; regenerar o `opcache_reset_token`.

## Lições catalogadas
- **Stale classmap RECORREU** (18/jun `ErrorReporter` pré-logger → hoje `VisregStateMiddleware` pós-logger). `--classmap-authoritative` não faz fallback PSR-4 → classe nova no disco mas fora do classmap = "does not exist". O `.php` aterrissou (18:25) **depois** do cache rebuild (18:22) = a race. Diagnóstico-chave: `grep -c <classe> vendor/composer/autoload_classmap.php`.
- **Declarei PHPStan-verde 2× antes de estar.** Runs parciais locais (só 2 arquivos; worktree fresco **sem `vendor/`**) não pegam: (a) `is_array()` sempre-true em param tipado `array`, (b) regra custom `oimpresso.silentFallback` (`if(!isset){assign}` = ADR 0212 Camada 2), (c) ratchet de **COUNT** do baseline (`env()` em config conta por arquivo: 80→82). Fechei o loop local rodando o **phpstan do `vendor/` do main-tree** contra os arquivos do worktree (`php -d memory_limit=2G vendor/bin/phpstan.phar analyse <paths> -c phpstan.neon.dist`). Verde só vale quando o **CI** confirma.
- **`gh pr checks --watch | tail` mascara o exit code** do gh (vira o exit do `tail`) → inútil pra saber pass/fail; checar `conclusion` via `gh pr view --json statusCheckRollup`.
- **SSH key-based Hostinger funciona não-interativo** (`~/.ssh/id_ed25519_oimpresso`, `u906587222@148.135.133.115 -p 65002`) — leitura de log + recuperação de classmap viável do desktop, sem stdin.

## Pointers detalhados
- Dossiê de design: `memory/sessions/2026-06-23-arte-validacao-L3-humano-judge.md` (§3b robustez do juiz; §3a double-threshold)
- Receita classmap + recorrência: `incidente-deploy-stale-classmap-500` (auto-mem) + PR #2952 (boot-gate failsafe)
- Provider canon: [ADR 0252](../decisions/0252-provider-llm-default-openai-camada-a.md) (gpt-4o-mini; trocar **provider** exige ADR superseding)
- Código: `Modules/Jana/Ai/UiJudgeConsensus.php` + `app/Console/Commands/UiJudgePrCommand.php` (gate de confiança) + `Modules/Jana/Ai/Agents/PrUiJudgeAgent.php` (`#[Temperature]`)
