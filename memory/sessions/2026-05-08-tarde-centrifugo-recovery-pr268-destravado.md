# Sessão 2026-05-08 tarde — Recovery Centrifugo/MCP + destrava PR #268 (WhatsApp TemplatePicker)

**Modelo:** Opus 4.7
**Branch base:** main
**PR mergeado:** [#268](https://github.com/wagnerra23/oimpresso.com/pull/268)
**US fechadas:** US-WA-003 · US-WA-004 · US-WA-013

## Origem

Wagner reportou que Centrifugo caiu ("erro no Proxmox"). Sessão começou tentando acessar CT 100/Proxmox a partir de máquina Daniel/Windows, descobrindo gap de infra (sem Tailscale instalado), pivotando pra teste do MCP via Chrome público após Wagner restartar o serviço manualmente. Em paralelo, destravou PR #268 (WhatsApp TemplatePicker) que estava bloqueado por 2 gates CI.

## Entregas

### 1. Diagnóstico de acesso CT 100 a partir de Daniel/Windows

- Tentativas de SSH/Chrome falharam: `tailscale` CLI ausente, sem GUI Tailscale, sem rota LAN. Esta máquina (Daniel) **não foi onboarded** ([skill `oimpresso-team-onboarding`](../../.claude/skills/oimpresso-team-onboarding/SKILL.md)).
- Chrome local também não alcança `pve-empresa:8006`, `192.168.0.50:8006`, nem `100.116.24.69:8006` (Tailscale IP).

**Conclusão:** Pra esta máquina operar CT 100 precisa instalar Tailscale + Wagner aprovar device + cola token MCP em `.claude/settings.local.json`.

### 2. MCP server confirmed UP via Chrome público

Após Wagner restartar Centrifugo, validei stack CT 100 via `mcp.oimpresso.com` (público):

| Camada | Sinal |
|---|---|
| DNS + TLS | resolveu, handshake OK |
| Traefik + FrankenPHP Caddy | header `Server: FrankenPHP Caddy` |
| Laravel app | `/robots.txt` 200 + `/login` HTML servido |
| MCP `/api/mcp/health` | `{"status":"ok","service":"oimpresso-mcp","version":"0.1","spec_mcp":"2025-06-18","ts":"2026-05-08T21:43:39+01:00"}` |

`/api/cc/health` retorna 404 (não-crítico — rota cc-watcher pode não estar deployada).

### 3. Drift fix `NfeEmissaoController` em `Modules/NfeBrasil/SCOPE.md`

[PR #262](https://github.com/wagnerra23/oimpresso.com/pull/262) (US-NFE-MANUAL "Emitir NFCe/NFe" no SaleSheet) adicionou `NfeEmissaoController` sem atualizar SCOPE. `scope-guard.yml` strict mode escaneia TODOS os módulos, então qualquer PR novo que toque controllers (ou SCOPE.md) ficava preso nesse drift até alguém declarar.

Adicionei em `contains[]`:
```
- "NfeEmissaoController — emissão fiscal manual + reenvio DANFE email + download PDF (US-NFE-MANUAL, PR #262)"
```

### 4. PR #268 destravado e mergeado

PR de TemplatePicker (US-WA-013) + audit fixes Pest (US-WA-003/004) estava `MERGEABLE` mas com 2 checks vermelhos:
- `mwart-gate` (Pages tocadas sem RUNBOOK + visual-comparison)
- `check-scope` (drift NfeEmissaoController herdado)

Caminho de destrava:
1. Drift fix #3 (acima) → `check-scope` ✅
2. **Rebase contra `origin/main`** (force-with-lease) — limpou diff fantasma de PRs Sells (#269/#270/#271/#273) mergeados em paralelo
3. Comentário `/mwart-override <razão>` no PR explicando que Inbox WhatsApp foi migrada pré-ADR 0104/0107 e este PR só toca `_components/` + propaga prop `templates` em Index/Show.tsx
4. CI re-run → todos 6 checks ✅
5. Merge via `gh pr merge --merge --delete-branch`

Merge commit: `7f7b49e9` em main 2026-05-08 20:57 UTC.

## Aprendizados meta importantes

- **Daniel/Windows não tem nada de infra oimpresso** — sem Tailscale CLI/GUI, sem `.claude/settings.local.json`. Pra operar CT 100 precisa onboarding completo (skill `oimpresso-team-onboarding`). Hoje só consegue tocar coisas via web público (`mcp.oimpresso.com`, `oimpresso.com`).
- **HTTP 404 num endpoint = sinal POSITIVO de up.** DNS resolveu + TLS OK + servidor respondendo. Distinto de `ERR_CONNECTION_REFUSED` ou `ERR_NAME_NOT_RESOLVED` (que indicam down).
- **`scope-guard.yml` em strict mode é global** — escaneia TODOS os módulos a cada PR que toca controllers ou SCOPE.md. Drift herdado em **qualquer** módulo trava **todos** PRs novos. Padrão: "absorver drift fix em hotfix do PR atual" (vide PR #213 que absorveu drift ProjectMgmt SearchController). Regra: **sempre rodar `bin/check-scope.php` localmente antes de abrir PR que toca controller**.
- **`mwart-gate` detect usa `refs/pull/N/merge` (merge virtual com main atualizada)**, não `pull_request.base.sha` puro. Diff `$base_sha...HEAD` no GA expande pra incluir mudanças de PRs mergeados em paralelo entre criação do PR e CI run. **Solução**: `git rebase origin/main` antes de re-run pra atualizar base. Sem isso, gate detecta Pages que sequer foram tocadas pelo PR.
- **`/mwart-override <razão>` no comentário PR libera o gate**. Match regex `^/mwart-override\s+` na primeira linha. Override vira ADR per-tela `lifecycle: historical` (skill `mwart-process` Tier A). Razão precisa ser substantiva — não usar pra burlar processo, só pra exceções legítimas (tela já migrada pré-ADR, PR só toca `_components/`, etc).
- **Padrão de merge do projeto: `--merge` (não `--squash`).** Histórico `git log origin/main` mostra `Merge pull request #N from ...` + commits originais visíveis. `gh pr merge --merge --delete-branch` falha o `--delete-branch` se main está em outra worktree, mas o merge no GitHub side já vai. Resolver com `git push origin --delete <branch>` manual depois.

## Próximos passos sugeridos

1. **Investigar a queda do Centrifugo** post-mortem — root cause? Foi container OOM, host Proxmox, deploy ruim, network? Anexar findings em [PR #272](https://github.com/wagnerra23/oimpresso.com/pull/272) (RUNBOOK Centrifugo público) pra dar valor concreto ao runbook.
2. **Onboarding MCP em Daniel/Windows** — Wagner gerar token em `/copiloto/admin/team`, criar `.claude/settings.local.json`. Sem isso esta máquina não consulta MCP tools (cycles-active, tasks-list, my-work, etc).
3. **Próximo escopo WhatsApp** depende do onboarding MCP pra ler backlog WA. Sem MCP: filesystem `Modules/Whatsapp/SPEC.md` mostra US-WA-001..014, mas não diz quais estão `done`/`in_progress`/`open`.
4. **Rodar `bin/check-scope.php` em CI sem strict mode regularmente** pra detectar drift cedo (antes de bloquear PRs futuros).
