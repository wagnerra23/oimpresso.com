---
date: "2026-11-14"
time: "1900 BRT"
slug: "runbook-deploy-producao"
tldr: "RUNBOOK de deploy escrito (rascunho) e testado em dry-run sem deploy. O pipeline de produção funciona (19/20 verdes, prod no tip da main). Bloqueadores: backup do banco irregular e só no mesmo disco, staging fora do pipeline, rollback nunca ensaiado. Nenhum fix feito: plano aguarda [W]."
decided_by: [W]
cycle: null
prs: []
us: []
next_steps:
  - "[W] aprovar ou ajustar o plano de fixes (G-01 a G-10)"
  - "PR 1: sonda do Passport não derruba deploy (G-03), 2 linhas"
  - "Investigar os zips faltantes do backup do banco (G-01)"
related_adrs:
  - "0062-separacao-runtime-hostinger-ct100"
  - "0269-deploy-automatico-build-no-runner"
  - "0235-staging-ct100-clone-anonimizado"
---

# Handoff 2026-11-14 19:00 BRT — RUNBOOK de deploy + gaps

## TL;DR

RUNBOOK em `memory/requisitos/Infra/RUNBOOK-deploy-producao.md` (status `rascunho`). Dry-run feito só com leitura:
nenhum deploy, nenhum comando que muda servidor. O deploy automático no merge está maduro. O que falta é a rede de
segurança: banco, staging como gate e rollback ensaiado.

## Estado MCP no momento do fechamento

- `brief-fetch`: sem cycle ativo; 4 HITL pendentes para [W] (um deles "Refinar runbook").
- `my-work`: sem tasks ativas para este token.
- `cycles-active`: nenhum cycle ativo em COPI.
- `decisions-fetch 0062`: lida; a exceção do staging está na 0235.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| início | brief, my-work, cycles-active, ADR 0062 |
| — | Worktree da sessão estava vazio e o checkout local 2192 commits atrás; auditoria refeita em worktree novo de `origin/main` (`C:/tmp/wt-runbook-deploy`) |
| — | Leitura de `deploy.yml`, `scripts/deploy/`, `docker/oimpresso-staging/`, RUNBOOKs de staging e de validação pós-deploy, `deploy-recovery-patterns.md`, `hostinger.md` |
| — | Dry-run: `gh run list`, `gh secret list`, testes do classificador, `bash -n`, SSH só-leitura no Hostinger, curl e DNS |
| — | RUNBOOK e este handoff escritos |

## Estado atual dos artefatos

| Arquivo | Status | Notas |
|---|---|---|
| `memory/requisitos/Infra/RUNBOOK-deploy-producao.md` | 🟡 rascunho | vira `ativo` quando [W] aprovar e o rollback B for ensaiado |
| `memory/sessions/2026-11-14-runbook-deploy-producao.md` | ✅ | session log |

## Gaps (priorizados)

| ID | Prio | Gap | Evidência | Fix proposto | Estimativa |
|---|---|---|---|---|---|
| G-01 | P0 | **Backup do banco irregular e sem cópia fora do servidor.** Faltam os zips do spatie de 7, 8, 11, 12 e 13/nov. Todos ficam em `public/uploads/UltimatePOS/` no mesmo disco. O `backup:monitor` alerta por e-mail, e o SMTP está quebrado (535), então ninguém soube. Sem banco confiável, o rollback D não existe. | `ls public/uploads/UltimatePOS/*.zip` via SSH só-leitura | (1) achar a causa no `laravel.log` dos dias sem zip; (2) mandar o alerta do `backup:monitor` para `mcp_alertas`/brief em vez de e-mail; (3) cópia diária para fora do Hostinger (decisão [W]: CT 100 ou storage externo; LGPD); (4) escrever e ensaiar o import (RUNBOOK §7-D) no staging | 2–4h + decisão [W] |
| G-02 | P0 (decisão) | **O staging não é etapa do pipeline.** Merge publica direto em produção. O staging do CT 100 é manual, compartilhado, e o CT 100 fica atrás do Tailscale (o runner do GitHub não alcança sem chave nova). | `deploy.yml` sem job de staging | Opções para [W]: (a) manter, e exigir staging manual para PR com label `risco-deploy` (migration destrutiva, pacote, middleware); (b) workflow `deploy-staging.yml` por `workflow_dispatch` via Tailscale Action + authkey; (c) staging obrigatório antes de todo merge (custo alto, trava a fila). Recomendo (a) agora e (b) depois. | (a) 1h · (b) 3–4h |
| G-03 | P1 | **A sonda do Passport derruba deploy que deu certo.** O passo diz "sem derrubar o deploy", mas um `curl` com erro de rede (exit 56) dentro de `$(...)` no `bash -e` encerra o job. Run 43485819612 (2026-11-13) ficou vermelho com o site no ar. | log do run | `|| echo 000` nos dois `curl` do passo | 15 min, PR de 2 linhas |
| G-04 | P1 | **Arquivos fora do git no servidor de produção (30).** Entre eles, código em `Modules/` (`RecurringBilling/.../ApplyWrComercialRetroatividadeCommand.php`, uma migration `Financeiro/2026_06_08_*`), 6 `temp_*.php`, 5 `.env.bak-*`, scripts SQL/PHP soltos e `build-inertia*.tar.gz`. O `.tar.gz` é baixável em `https://oimpresso.com/build-inertia.tar.gz` (só bundle público). `.env*` e `temp_*.php` respondem 403/404. Código fora do git em `Modules/` é carregado pelo app: é drift. | `git status --porcelain` via SSH só-leitura | Listar com [W] o que é de quem, arquivar fora do webroot e só então remover. Não remover sem [W]. | 30 min com [W] |
| G-05 | P1 | **Rollback nunca ensaiado, e a receita documentada está errada.** `hostinger.md` "Rollback rápido" faz `reset --hard origin/main` (vai para frente). O rollback B (redeploy de SHA bom por branch) é plausível pelo código do workflow, mas nunca rodou. | leitura de `hostinger.md` e `deploy.yml` | Corrigir `hostinger.md` apontando para o RUNBOOK §7; ensaiar B numa janela combinada com [W], com um SHA igual ao atual (ensaio sem mudança real) | 1h + janela [W] |
| G-06 | P2 | CT 100 inacessível por `tailscale ssh` a partir desta máquina (timeout na porta 22). Staging e MCP não puderam ser conferidos por dentro (HTTP de fora está 200). | dry-run | Conferir se o CT está de pé e se o SSH do Tailscale está habilitado | 15 min |
| G-07 | P2 | O smoke do `deploy.yml` não confere `uniafibro.com.br`, que é servida pelo mesmo app. | `deploy.yml` | Acrescentar um curl não-bloqueante (warning) no smoke | 15 min |
| G-08 | P2 | `RUNBOOK-validacao-pos-deploy.md` está velho (2026-06-08: cita `composer dump-autoload --optimize` manual, Chrome MCP obrigatório). | leitura | Apontar para o RUNBOOK novo §6 e atualizar a camada 2 | 30 min |
| G-09 | P3 | `mcp.oimpresso.com/` responde 500 depois de ~15s (o health responde 200 em 0,1s). | curl | Investigar: rota raiz ausente ou algo lento no boot | 30 min |
| G-10 | P3 | Sem `php` no PATH do Git Bash nem `actionlint` na máquina: o `php -l` do `verify-classmap.php` e o lint do workflow não rodaram no dry-run. | dry-run | Rodar no CI ou instalar `actionlint` | — |

## Bloqueios / pendências

- [ ] Decisão G-02 (modelo do staging) — owner: W
- [ ] Decisão G-01 (destino da cópia externa do banco) — owner: W
- [ ] Janela para ensaio do rollback B (G-05) — owner: W
- [ ] Triagem dos arquivos soltos no servidor (G-04) — owner: W

## Próximos passos (ordem)

1. [W] aprova ou ajusta o plano.
2. PR G-03 (2 linhas no `deploy.yml`) — rollback do próprio PR = revert, testado pelo próximo deploy.
3. Investigação G-01 (só leitura) e proposta da cópia externa.
4. PR G-05 (doc `hostinger.md`) + ensaio do rollback B com [W].
5. G-07, G-08 em PRs pequenos.
