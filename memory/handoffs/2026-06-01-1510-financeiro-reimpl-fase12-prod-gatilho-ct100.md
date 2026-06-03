---
date: "2026-06-01"
hour: "15:10 BRT"
slug: financeiro-reimpl-fase12-prod-gatilho-ct100
topic: "Financeiro Cowork 9.75 re-implementado sobre Fase 1/2 (ADR 0236) + em prod + gatilho block-test-fora-ct100 + Jana drift fechado"
tldr: "Era merge, não build: prod Hostinger builda sozinha no push (quick-sync). Re-implementei os 4 PRs de conciliação Cowork 9.75 sobre a Fase 1/2 (ADR 0236) com Pest no CT 100, + criei o gatilho block-test-fora-ct100 + fechei o Jana drift #2069. 8 PRs em prod."
duration: "~4h"
authors: [CC, Wagner]
session: frosty-greider-83ab2f
---

# Financeiro Cowork 9.75 → produção (re-impl sobre Fase 1/2) + gatilho CT 100

## Estado MCP no momento
- Cycle: **CYCLE-08 Receita — Onda A** (27d restantes). Brain B 0%.
- my-work: 30 tasks (6 review, 6 blocked dormentes NFe Gold, 18 todo). Nenhuma desta sessão (foram PRs diretos, não US do backlog).
- Decisões base: ADR 0236 (extrato+conciliação unificado, Fase 1/2 já em prod do handoff 03:00), ADR 0093 (Tier 0), ADR 0208 (PHPStan ratchet), ADR 0062 (Hostinger≠CT100).

## O que aconteceu
Wagner abriu com *"tem coisas que foram pro ct 100 aprovadas mas não está em produção, acho que falta build"*. **Diagnóstico desmentiu a hipótese:** prod (Hostinger) estava em dia com `main` e buildada — o `quick-sync.yml` builda `npm run build:inertia` automático a cada push em `main`. O gap real eram **PRs abertos não-mergeados** (a pilha Financeiro "Cowork 9.75", #2042–2052) — testados no staging CT 100 mas nunca mergeados → **era merge, não build**.

Ao tentar mergear, descobri que os 4 PRs de conciliação (#2042/2043/2044/2045) **conflitavam semanticamente com a Fase 1/2 (ADR 0236)** que reescreveu o `ConciliacaoController` pra 2 origens (OFX `fin_bank_statement_lines` + API `fin_extrato_lancamentos` via `conciliacaoTable($origem)`). Não davam merge mecânico → **re-implementei cada um sobre `main` atual**, com **Pest verde no CT 100 (biz=1 dogfooding)** antes de cada merge.

Origem do gatilho: eu tentei rodar PHPStan **local** (Windows, sem php no PATH) — Wagner cobrou *"o caminho é o CT 100, erro denovo coloque nas proibições ou crie um gatilho"*. Reincidência do feedback #2076 (mesmo dia). Criei o hook ativo.

## Artefatos gerados (todos em prod, exceto onde dito)
| PR | O quê | Validação |
|---|---|---|
| **#2081** | hook `block-test-fora-ct100.ps1` (PreToolUse Bash\|PowerShell) + `.test.ps1` 14 casos + proibição | bloqueia pest/phpstan/test local fora do CT 100 |
| #2052 / #2046 | caixa freshness Carbon 3 · botões honestos DRE/Cobrança | CI verde (mergeados cedo) |
| **#2083** | match_score real (era `0.85` fake fixo) — `calcularMatchScore` no ponto único `sugerirParaLinha`; `getAttribute()` em vez de `@property` (evita destravar análise) | Pest 3/3 CT 100 |
| **#2085** | Extrato rota `/extrato` sem id (B4) + `selecionar()` 2-origens + session key `user.business_id` (B5) | Pest 4/4 CT 100 (incl. multi-tenant Tier 0) |
| **#2087** | Conciliação audit-log (`FinanceiroAuditLogger`) + `reabrir()`/undo backend (2 origens, API status=NULL) + `incluir_resolvidos` | Pest 5/5 CT 100 |
| **#2090** | UI reabrir: botão `Reabrir` + toggle "ver resolvidos" em `Conciliacao/Index.tsx` (passa `origem`, `<Checkbox>` DS + `htmlFor`) | Vite/ESLint/Pest verdes; smoke prod 200 |
| **#2084** | Jana drift: declara `ProController` no `Modules/Jana/SCOPE.md` (do #2069) → desbloqueia check-scope global | — |
| **#2091** | Jana drift: `Pro.tsx` cor hardcoded → token `success` (corrige UI Lint R1 do #2069) | UI Lint verde |

PRs **#2042–2045 fechados** (superseded). **#2045 BankStatementLine → backlog** (Wagner, baixo ROI — já há scope manual; chip spawnado).

## Persistência
- **git:** todos mergeados em `main` via `--admin` (conta única `wagnerra23`, `REVIEW_REQUIRED` insatisfazível — padrão do repo). quick-sync deployou cada um (build automático).
- **MCP:** webhook GitHub→MCP propaga em ~2min (não criei US — foram PRs diretos de bugfix).
- **BRIEFING:** Financeiro não alterou capacidade declarada (eram fixes de bugs reportados na sessão Cowork 9.75) — não atualizei BRIEFING.md.

## Próximos passos pra retomar
`brief-fetch` → conferir os 2 chips: **(a)** #2045 BankStatementLine refactor (branch `origin/fix/financeiro-bankstatementline-model`), **(b)** Jana Pro UI Lint já resolvido (#2091). Pra ver a UI reabrir em ação: importar um OFX em prod (biz com conciliação) → toggle + botão aparecem.

## Lições catalogadas
- **Testes/PHPStan SEMPRE no CT 100** (container `oimpresso-staging`, `docker exec -e DB_CONNECTION=mysql`), nunca local — agora com **hook bloqueador** (`block-test-fora-ct100.ps1`) + proibição. Reincidência do feedback #2076 no mesmo dia motivou o mecanismo ativo.
- **CT 100 (MySQL real) pega o que o sqlite do CI mascara:** `DB::table('businesses')`→`business` (singular UPos), CSRF 419 em POST, `Log::spy()` quebrando `Log::channel()->warning()` (usar `Log::listen()`), FK `titulo_id`/`business_id` sintético (criar registro real). Testes "dogfooding biz=1" skipam em sqlite → bug passa.
- **"falta build" quase nunca é build:** prod Hostinger builda sozinha no push (quick-sync). Se algo "aprovado" não aparece, é **merge** (PR aberto) ou **sessão sem dados**.
- **Build cross host/container no CT 100:** host tem node (v20) mas **não php**; container `oimpresso-staging` tem php mas **não node**. O `vite-plugin-wayfinder` roda `php artisan` no build → wrapper `php`→`docker exec` no PATH do host destrava `npm run build:inertia`.
- **`@property` em Eloquent destrava análise larastan e quebra outros arquivos** (valor_aberto float vs string, null-checks "always true") — pra acesso pontual fora do model, `getAttribute()` (mixed) é cirúrgico e sem efeito colateral.
- **Re-impl sobre Fase reescrita ≠ merge:** PR feito antes de uma Fase que reescreve o mesmo arquivo conflita semanticamente; aplicar via `git apply --3way` + resolver lógica (não só markers).
- **Screenshot via Chrome MCP trava (`captureScreenshot timed out`) na tela Conciliação** (SPA pesada) — `read_page` funciona como fallback pra confirmar render. Staging login de teste (`staging2026`) não scopa pra business com dados.

## Pointers detalhados
- Gatilho: `.claude/hooks/block-test-fora-ct100.ps1` + `memory/reference/feedback-testes-no-ct100-nao-local.md` (#2076) + `memory/proibicoes.md` §Ambiente.
- Fase 1/2 base: `memory/handoffs/2026-06-01-0300-fase1-fase2-extrato-conciliacao-prod-artisan-fix.md` + ADR 0236.
- Re-impl: `Modules/Financeiro/Http/Controllers/{Conciliacao,Extrato}Controller.php` + `Routes/web.php` + `Tests/Feature/{ConciliacaoMatchScore,ExtratoNavRedirect,ConciliacaoAuditReabrir}Test.php`.
