---
date: 2026-05-29
hour: "11:57 BRT"
topic: Revisão adversarial 31 agentes → 8 findings corrigidos, mergeados e deployados live (Jana retrieval/freshness/governance)
duration: ~3h (continuação)
authors: [Wagner, Claude Opus 4.8]
---

## Estado MCP no momento

- Cycle ativo: retrieval/freshness pipeline Jana (Gaps RET + D7 + Governance reconcilers).
- PRs mergeados nesta sessão (fix-cycle): **#1950, #1951, #1952, #1953, #1955** (+ #1956 handoff irmão IA-OS).
- Deploy: container `oimpresso-mcp` (CT 100) em `origin/main` (407ac9823 no momento do deploy; main avançou pra 5ad7727c2 com handoffs).
- `governance:audit --all` live: **7/8 checkers limpos**; único drift = `multi_tenant_scope (92)` pré-existente (ADR 0218 backlog).

## O que aconteceu

Continuação da revisão adversarial (31 agentes, turno anterior) que confirmou **8 findings reais** sobre o trabalho da sessão de retrieval/freshness. Corrigi todos, cada um em PR isolado (1 PR = 1 intent), com teste + verificação, mergeados via `--admin` e deployados live no CT 100.

- **#1 (Tier 0):** `McpMemoryDocument::buscarHybrid` não aplicava `doBusiness($businessId)` enquanto o FULLTEXT aplicava — assimetria que vira vazamento quando biz=4 ganhar docs. Adicionado `$businessId` + `doBusiness` simétrico. **PR #1950**.
- **#3 (unblock CI):** `readEditController()` declarado global em Wave1(Sells)+Wave2(Purchase) → fatal redeclare bootando a suíte Feature. Renomeado. **PR #1951**.
- **#2 (regressão):** `StalenessDetectorService` stateful sem singleton → command configurava `base_path()` numa instância, dispatcher recebia outra (null) → drift git-SHA detectado mas nunca reindexado. Singleton com `base_path()`. **PR #1952**.
- **#4/#5/#6 (robustez commands):** `~~` no `aplicarContagens` (regex capturava til + re-adicionava) · `resolver()` quebrava com backslash Windows (`..` popava raiz toda) · `jana:meilisearch-setup --index=typo` retornava SUCCESS silencioso. Os 3 corrigidos + teste regressão #6. **PR #1953**.
- **#7/#8:** guarda cross-tenant do `MemoriaSearchTool::handle` existia sem teste → 2 testes (violação biz=1→99 bloqueada early-return; superadmin passa) · SPEC `retrieval-tools-mcp-unificado` tinha header "ATIVADO EM PROD" vs corpo "⏳ Pendente" — verifiquei container live (docs=ON, memoria=ON, qwen3_local) e reconciliei. **PR #1955**.

**Verificação live pós-deploy:** `buscarHybrid("isolamento multi-tenant")` com `doBusiness(1)` → `adr-laravelai-tech-0002 · 0093 · 0218` (recall intacto, sem regressão). Os 2 reconcilers desta linha de trabalho (`deploy_drift` + `meilisearch_settings_drift`) = **0 findings** após reconcile do SHA file.

## Artefatos gerados

- 5 PRs de fix (código + testes) — ver lista acima.
- `memory/sessions/2026-05-29-arte-reconcile-loop-kb-self-healing.md` — dossier estado-da-arte do reconcile loop / KB self-healing (turno anterior).
- `memory/decisions/proposals/drafts/automation-registry-mcp.md` — draft proposta automation registry.
- Chip de follow-up (spawn task): investigar entrega do webhook deploy-SHA (file `deploy-latest-main-sha.txt` fica stale — GitHub não atualiza SHA nos pushes de código; reconciliei manual).

## Persistência

- **git:** 5 PRs mergeados em main + este handoff.
- **MCP:** webhook GitHub→MCP propaga em ~2min pós-push.
- **prod:** deploy CT 100 confirmado (octane:reload + smoke live verde).

## Próximos passos pra retomar

```
brief-fetch && my-work
# Pendência aberta: chip "Investigar entrega do webhook deploy-SHA (CT 100)"
#   → GitHub Webhooks delivery log + permissão @file_put_contents no bind-mount + fallback cron `git rev-parse origin/main`
# multi_tenant_scope 92 findings (ADR 0218) = backlog pré-existente, não desta sessão
```

## Lições catalogadas

- **Reconciler é só tão bom quanto a fonte de dado dele:** o `DeployDriftChecker` flagou drift legítimo, mas a causa-raiz era o file stale (webhook não entregando) — não o código do checker. Verificar a fonte ANTES de assumir bug no consumidor.
- **Verificar realidade no servidor antes de reconciliar doc:** a contradição #8 só fechou certo porque conferi `config()` dentro do container (`docs_pipeline=ON`) em vez de assumir pelo `.env` do host (que estava vazio — flags vêm do env do container).
- **Container `oimpresso-mcp` sem git/php no PATH do host:** código é bind-mount `/opt/oimpresso-mcp/code` → `/var/www/html`; deploy = `git reset --hard origin/main` no host + `docker exec oimpresso-mcp php artisan octane:reload`.

## Pointers detalhados

- Dossier reconcile loop: `memory/sessions/2026-05-29-arte-reconcile-loop-kb-self-healing.md`
- SPEC retrieval: `memory/requisitos/Jana/SPEC-retrieval-tools-mcp-unificado.md`
- ADRs: 0093 (multi-tenant Tier 0), 0053 (MCP server), 0216 (DriftChecker framework), 0218 (multi-tenant scope checker).
