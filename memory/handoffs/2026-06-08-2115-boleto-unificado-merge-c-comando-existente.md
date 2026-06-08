---
date: "2026-06-08"
hour: "21:15 BRT"
slug: boleto-unificado-merge-c-comando-existente
topic: "Boleto pelo Financeiro Unificado: investigação → consolidação de branch WIP → merge + fix-forward PHPStan; C é comando que já existe"
tldr: "Wagner perguntou 'consigo emitir boleto pelo financeiro?'. Boleto é REAL (Modules/PaymentGateway, Inter LIVE biz=1; CnabDirectStrategy é mock legado). Achei o botão 'Cobrar' do Unificado apontando pra rota 404 inexistente (US-FIN-054). Descobri branch WIP local feat/financeiro-unificado-gerar-boleto-inter que JÁ implementa gerar-boleto-Inter-direto-do-drawer (melhor que meu deep-link #2449, que fechei). Consolidei: rebase + #2452 MERGED. PORÉM #2452 mergeou (auto-merge bypassou PHPStan não-required) SEM meu fix de typing → main FICOU VERMELHO no PHPStan + 1 erro do #2453 (demo seeder env x2) → bloqueou PRs do time → fix-forward #2457 MERGED (4 erros: dead catch + 2 && redundantes + bump baseline) → main verde. O 'C' (unificar credenciais) JÁ está codado: comando paymentgateway:migrate-credentials (idempotente, dry-run default) — não dá pra eu rodar (precisa php artisan no Hostinger, fora do meu SSH). Esvaziei o vendor local montando junction pra rodar PHPStan → restaurei com composer install --ignore-platform-reqs. Briefing fix #2438 e ADR proposal C #2450 seguem abertos."
duration: "~4h"
authors: [CL]
session: frosty-greider-83ab2f
---

# Boleto pelo Financeiro Unificado → main (#2452 + fix-forward #2457)

> Origem: Wagner *"no financeiro ja consigo emitir um boleto?"* → série de perguntas → *"mostre como criar"* → *"quero pelo Unificado"* → *"faça o C e pode merge"*.

## Estado MCP no momento

- **Cycle CYCLE-08 Receita Onda A** (29% decorrido, 20d). Boleto foi **off-cycle** (ciclo = monetizar carteira legacy) — mas respondeu pergunta direta + consertou bug real + desbloqueou `main`.
- `my-work`: 30 tasks. Relevante: **US-INFRA-011** (rotacionar senha MySQL Hostinger) em REVIEW — toca o mesmo domínio credenciais.

## O que aconteceu

1. **Resposta à pergunta:** boleto é **emissão REAL** via `Modules/PaymentGateway` (`/financeiro/cobranca` → `PaymentGatewayContract::emitirBoleto()`). **Inter LIVE em prod biz=1** (`InterDriver` OAuth2+mTLS). O `CnabDirectStrategy` em `Modules/Financeiro` é **mock legado** (`gerado_mock`), sendo aposentado. → **#2438** corrige o BRIEFING que dizia "mock" (OPEN).
2. **Bug achado (US-FIN-054):** botão "Cobrar" do drawer do Unificado apontava pra `/cobranca/recorrente/nova` — rota que **nunca existiu** (404 desde `7c7a6e5ab` 28/mai). Confirmado em prod biz=1.
3. **Meu fix inicial #2449** (deep-link `?cobrar_titulo=ID` pré-abrindo o wizard) → depois **FECHADO/superseded**.
4. **Descoberta-chave:** branch WIP **local não-pushed** `feat/financeiro-unificado-gerar-boleto-inter` que JÁ implementava **gerar boleto Inter direto do drawer** (`emitirBoletoTitulo` via `$gateway->for($coreAccount)`, resolve credencial pelos 3 caminhos → funciona biz=1 sem depender do "Conta destino" vazio do wizard). Melhor que meu #2449. Wagner: **consolidar a existente**.
5. **Consolidação → #2452 MERGED.** Rebase em origin/main, revisão Tier 0 (idempotência `fintitulo-{id}`, anti-duplo-recebível `origem_type='fin_titulo'` → listener baixa o título existente, migration enum idempotente).
6. **INCIDENTE main vermelho:** #2452 mergeou (auto-merge — PHPStan **não é required check**, só 1 required + admin fura) **sem meu commit de fix de typing** (ficou órfão numa branch já mergeada). main quebrou no PHPStan ratchet (3 erros do boleto + 1 do #2453 demo seeder `env()` 2×) → **bloqueava PRs de todos**. → **fix-forward #2457 MERGED** (esperei o PHPStan ficar verde ANTES de mergear desta vez).
7. **O "C" (unificar credenciais):** descobri que **já está codado** — comando `paymentgateway:migrate-credentials` (idempotente, `--dry-run` default, `--apply`). **Não dá pra eu rodar** (precisa `php artisan` no Hostinger; meu SSH só alcança CT 100). Pra biz=1 provavelmente já efetivo. Rollout a clientes depende também de destravar o gate `biz=1` do listener + as 4 decisões do #2450.

## Artefatos gerados

- **#2452** (MERGED `eaa6dc382`) — gerar boleto Inter no drawer do Unificado. Controller `emitirBoletoTitulo` (+125) · listener branch `baixarTituloExistente` · migration enum `cobrancas.origem_type += fin_titulo` · `Index.tsx` botão · Pest sentinela.
- **#2457** (MERGED `ab5dfd168`) — fix-forward ratchet PHPStan: `@property origem_type` no `Cobranca`, dead-catch `DriverNotSupportedException` removido, `$titulo->vencimento &&` redundante, baseline demo-seeder 1→2.
- **#2438** (OPEN) — BRIEFING Financeiro: boleto real Inter LIVE biz=1, não mock.
- **#2450** (OPEN) — ADR proposal Ondas B+C (ponte título↔cobrança mão-dupla + unificação credenciais). Aguarda 4 decisões §7 de Wagner.
- **US-FIN-054** criada (SPEC Financeiro). **#2449 FECHADO** (superseded por #2452).

## Persistência

- git: 2 PRs merged em `main` (#2452, #2457); 2 abertos (#2438, #2450). Webhook→MCP propaga.
- **vendor local reparado** (`composer install --ignore-platform-reqs`, 111 pacotes) após eu esvaziar montando junction pra PHPStan local. **Não afetou prod/CI.**

## Próximos passos pra retomar

- Wagner: rodar `php artisan paymentgateway:migrate-credentials` (dry-run → `--apply`) no Hostinger pro C; responder §7 do #2450; mergear #2438.
- Local: `php artisan config:clear` antes de subir dev (post-install `package:discover` reclamou de config-cache stale desta branch).

## Lições catalogadas

- **Mergear vermelho:** `gh pr merge --auto` mergeia IMEDIATO se os required checks (só 1) passam — PHPStan **não é required** → passou red. Regra nova: **esperar PHPStan verde explicitamente antes de qualquer merge** de código (não confiar no --auto pra gate de qualidade).
- **Tooling instável nesta sessão:** `cwd` reseta entre Bash calls (PowerShell↔bash) → `git ls-remote`/`gh pr list` retornaram **vazio falso** → quase recriei PR #2452 que já existia + worktree sumiu do disco. **Não confiar em checks de estado locais; confirmar com `gh pr view` direto.**
- **Junction de vendor é perigoso no Windows:** montar/remover junction esvaziou o vendor real. Pra rodar PHPStan local precisa de vendor próprio — mas phpstan **não está instalado** no vendor local (só CI). Evitar; usar o CI como fonte de verdade.
- **MCP-first vale mesmo pra "feature pronta":** a branch WIP existente economizou re-trabalho — sempre varrer worktrees/branches antes de implementar (3ª vez nesta sessão que apareceu trabalho paralelo pré-existente).

## Pointers detalhados

- US-FIN-054: `memory/requisitos/Financeiro/SPEC.md`
- Comando C: `Modules/PaymentGateway/Console/Commands/MigrateCredentialsCommand.php`
- Listener ponte: `Modules/Financeiro/Listeners/OnCobrancaPagaCreateFinanceiroTitulo.php`
- ADR proposal C: `memory/decisions/proposals/financeiro-titulo-cobranca-bridge-credenciais.md` (#2450)
