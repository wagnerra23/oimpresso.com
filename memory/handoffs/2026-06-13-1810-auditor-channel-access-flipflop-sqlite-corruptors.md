---
date: "2026-06-13"
hour: "18:10 BRT"
topic: "Auditor do flip-flop ACL canal (revoke↔reativação) + auditor de corruptores SQLite — PR #2689 merged"
duration: "~2h"
authors: [W, C]
---

# Auditor channel_user_access + auditor de corruptores SQLite

## Estado MCP no momento
- **Cycle CYCLE-08** (Receita Onda A) ativo · trabalho desta sessão **off-cycle** (governança/Whatsapp), drift conhecido — mesmo contexto do handoff 19:23 de ontem.
- Sessão disparada por pedido cru do [W]: *"[channel access] está em conflito, uma hora é removido, uma hora é reativado, por? cria um auditor. E a corrupção sqlite já gastou muito recurso."*
- A migration `2026_06_13_120000_enforce_single_active_channel_user_access` (enforce 1 ativo via coluna gerada `revoked_marker`) e o cluster Whatsapp não-commitado seguem no working tree da **sessão paralela** no worktree `frosty-greider-83ab2f` — NÃO tocados. Trabalho saiu isolado de `origin/main`.

## O que aconteceu
1. **Decodificação** (pedido cru → 2 problemas): confirmado com [W] via pergunta estruturada que o flip-flop é `channel_user_access` e que ele quer detectar **+ corrigir a causa**.
2. **Causa-raiz do flip-flop achada** lendo o código: o admin **revoga** pela UI (`revoked_at` + `revoked_by_user_id` humano); depois `whatsapp:backfill-channel-access` re-concede a TODO user com `whatsapp.send/access` olhando só `whereNull('revoked_at')` → recria grant *system* (`granted_by_user_id=0`) e **desfaz o revoke deliberado**. Resposta literal ao "por?": removido pelo admin, reativado pelo backfill.
3. **Corrupção SQLite**: a suíte roda em SQLite `:memory:`; testes com schema sintético manual (`Schema::create/drop` de tabelas compartilhadas) corrompem o schema pro próximo teste da mesma conexão → cascata. Eram re-descobertos 1-a-1 em ~19 worktrees `era-sqlite`.
4. **PR #2689** (squash `c07c42e05`, 6 arquivos, +1246/−10) — **mergeado por [W]** após 32 checks verdes (PHPStan + E2E + 2 Pest suites inclusos).

## Artefatos gerados (PR #2689)
- `Modules/Whatsapp/Console/Commands/AuditChannelAccessCommand.php` (332) — `whatsapp:audit-channel-access`, read-only, `--fix`/`--json`/`--strict`. Detecta `DUP_ATIVO` + `FLIP_BACKFILL`.
- `Modules/Whatsapp/Console/Commands/BackfillChannelAccessCommand.php` (+57) — respeita tombstone de revoke humano (não re-concede salvo `--force`); memoização → `??=` (mata pattern silentFallback ADR 0212).
- `Modules/Whatsapp/Providers/WhatsappServiceProvider.php` (+2) — registra o command.
- `Modules/Whatsapp/Tests/Feature/AuditChannelAccessCommandTest.php` (395) — 12 guard tests, quarentena `era-sqlite`.
- `scripts/audit/sqlite-test-corruptors.mjs` (183) — linter read-only ranqueia corruptores **sem rodar a suíte**. Run: 1228 escaneados → **237** (S=58, A=89, B=64, C=26).
- `memory/sessions/2026-06-13-audit-sqlite-test-corruptors.md` (287) — snapshot ranqueado completo.

## Persistência
- git: PR #2689 merged em `main` (`c07c42e05`); branch/worktree limpos. Este handoff em branch própria off-main.
- MCP: webhook GitHub→MCP propaga ~2min após push.
- BRIEFING Whatsapp: NÃO atualizado (sessão pequena, sem mudança de capability visível ao cliente).

## Próximos passos pra retomar
1. **Limpar passivo em prod** (Tier-0, [W]-gated): `php artisan whatsapp:audit-channel-access --business=1` (relatório) → conferir → `--fix`.
2. **Burn-down SQLite**: atacar os **58 corruptores S-tier** por ordem (lista em `sessions/2026-06-13-audit-sqlite-test-corruptors.md`) — converter pra `RefreshDatabase` OU quarentenar `markTestSkipped` não-sqlite. Coordenar com a sessão `Parallel turbo stages` (dona do floor era-sqlite) pra não colidir.
3. Opcional: promover `sqlite-test-corruptors.mjs` a ratchet advisory no CI quando o S-tier zerar.

## Lições catalogadas
- **Worktree `frosty-greider-83ab2f` é órfão** — não está no `git worktree list`; o git resolve toplevel pro repo principal → `git status` mostra `../../../Modules/...`. Bate com a nota de cleanup pendente. Confirma: trabalhar SEMPRE em worktree registrado off-main quando há sessão paralela.
- **Worktree filha não tem `vendor/`** → não dá pra rodar Pest local; `composer install` só pra 1 teste é desperdício. Verificação local = `php -l` (Herd 8.4.21) + PHPStan apontando o repo principal + linter node; Pest funcional fica pro CI (convenção CT 100).
- **PHPStan em arquivo de worktree contra autoload do repo principal** gera falso-positivo `larastan.console.undefinedOption` (reflete a classe ANTIGA carregada, não o arquivo novo). Sintoma: acusa só a opção nova. Some no CI (lá o arquivo É a classe). O `silentFallback` (real) some com `??=`.

## Pointers detalhados
- Snapshot corruptores SQLite: `memory/sessions/2026-06-13-audit-sqlite-test-corruptors.md`
- Causa-raiz/contexto ACL canal: ADR 0135 omnichannel + `memory/requisitos/Whatsapp/SPEC.md` US-WA-068
- Floor era-sqlite (lever real): `memory/handoffs/2026-06-13-1730-sdd-floor-frente-c-era-sqlite.md`
