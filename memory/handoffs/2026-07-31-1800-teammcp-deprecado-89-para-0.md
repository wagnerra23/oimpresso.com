---
date: "2026-07-31"
time: "18:00 BRT"
slug: teammcp-deprecado-89-para-0
tldr: "Modules/TeamMcp apagado em 7 etapas + 12 PRs mergeados (89 → 0 arquivos). Nada perdido: 14/14 endpoints e 4/4 comandos artisan provados em prod, tabelas intocadas (zero DDL). Aberto: fusão das abas /forja, decisão [W]."
prs: [5083, 5084, 5101, 5107, 5111, 5114, 5116, 5117, 5118, 5120, 5122, 5124]
decided_by: [W]
related_adrs:
  - 0361-errata-0354-teammcp-pest-required-nunca-executado
  - 0087-drift-resolution-sem-mover-url
  - 0081-identity-mesh-mcp-actors
  - 0283-handoff-loop-zero-paste
next_steps:
  - "Fusão das abas /forja × telas da Forja (4-de-4 sobrepostas) — decisão de PRODUTO [W], não bloqueia nada"
  - "6 chaves órfãs em modules_statuses.json (Accounting, CustomDashboard, Ecommerce, FieldForce, Hms, InboxReport) — anteriores a esta sessão"
  - "Pest (Ponto · MySQL) vermelho em main desde antes (run 30585057152, 11 failed) — não é desta leva"
---

# TeamMcp deprecado — 89 → 0 arquivos em 7 etapas

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ativo em COPI**
- `my-work` → **8 tasks em REVIEW** (US-COPI-123 p0 · US-TR-309/310/305/306 · US-PG-008 · US-PROD-027 · US-INFRA-023) — nenhuma tocada aqui
- handoffs irmãos do dia: `2026-07-31-0030-rename-projectmgmt-forja`, `-0740-deprecacao-modulos-4-decisoes-abertas`, `-1100-srs-memcofre-purga-e-abortos`
- ADR nova desta sessão: **0361** (errata à 0354), `status: aceito`

## O que aconteceu

Execução do `DEPRECATION-PLAN` do TeamMcp, 5º de 5. O plano previa E1→E8; virou **7 etapas de conteúdo + 12 PRs**, todos mergeados e deployados.

**A E1 (medir) derrubou 4 afirmações do próprio plano** antes de qualquer código:

| Plano dizia | Medido |
|---|---|
| acopladores de ADS/Governance "morrem no 4º/6º" | **estavam VIVOS** — o TeamMcp saía antes, então tinham de ser repontados |
| `mcp_tokens` → "MIGRATE obrigatório" | **já era da Jana** (migration + Entity + `McpAuthMiddleware`) |
| E6 = migration cara | `migrations` indexa pelo **nome do arquivo** → mover entre módulos não re-executa |
| heartbeat "parado há 9 dias" | linhas são por `host` = worktrees do [W]; produtor é o **watcher local ocioso**, não código morto |

**CT 100 medido** (era o resíduo mais grave): roda o mesmo repo contra o banco do Hostinger. Não há banco separado — toda "migração" foi **troca de dono no código, zero DDL**.

**Correção de rota no meio:** as etapas 1–2 mandaram o MCP pra **Jana**, seguindo o plano. [W] corrigiu (*"Mcp vai para forja"*) depois que o #5089 renomeou `ProjectMgmt → Forja`. O #5101 refez o receptor. O **aberto #2** da proposal (`SyncMemoryWebhookController` — *"escreve em `mcp_memory_documents`, tabela da Jana e tem `business_id`"*) fechou **por medição**: as **2082 linhas são todas `business_id=1`** — coluna nominal, canon é conteúdo de plataforma.

## Artefatos gerados

| Onde | O quê |
|---|---|
| [ADR 0361](../decisions/0361-errata-0354-teammcp-pest-required-nunca-executado.md) | errata: o required de `teammcp-pest` **nunca chegou à proteção viva** (34 contexts medidos) |
| [DEPRECATION-PLAN](../requisitos/TeamMcp/DEPRECATION-PLAN.md) | §E1 com as 4 correções + decisões [W] + recibo do R6 |
| [SUPERFICIE.md](../requisitos/TeamMcp/SUPERFICIE.md) | **lápide** — destino de cada cluster (3 docs vivos linkam pra ela) |
| [BRIEFING.md](../requisitos/TeamMcp/BRIEFING.md) | `status: deprecated` + `lifecycle: arquivado` (dizia `✅ live` sobre módulo apagado) |
| `governance/ghost-rename-map.json` | TeamMcp em `excluded` **classe C** (dissolução, não rename 1:1 — precedente SRS) |
| [proibicoes §5](../proibicoes.md) | lápide `git grep -F` + `\E` → rc=128 lido como zero |
| [LICOES_CODE](../LICOES_CODE.md) | LC-08 5 → 6 |

## Persistência

- **git:** 12 PRs em `main`, deleção em `c9da3549d`, BRIEFING em `db76ba5c6`
- **prod:** Hostinger serve o commit da deleção; `Modules/TeamMcp` **não existe** no disco
- **MCP:** webhook do canon `last_response 200` — atravessou as 7 etapas intacto

## Prova em produção (R6 + superfície)

**14 de 14 endpoints corretos**, medidos com `/login` como controle **primeiro**: `/api/mcp/health` 200 · `/team-mcp/*` 302 · `/forja/*` 302 · `/ads/admin/*` 302 · `/api/cc/ingest` e `/api/mcp/sync-memory` 401. **Zero 404** (rota perdida) e **zero 500** (classe não resolvida) — os dois modos de falha que a deleção arriscava. **4 de 4** comandos artisan migrados registrados.

## Lições catalogadas

1. **`git grep -F` quebra em padrão com `\E`** — sai `rc=128` com **zero linhas**, e script desatento lê como "sem ocorrências". Sweep dizia 14 arquivos, eram **18**. Lápide §5 + regra: assertar o `rc`, e claim de ausência exige **2 métodos independentes**.
2. **Smoke durante deploy não é medição** — declarei "regressão em produção" vendo `500`; o `/login`, meu controle, estava `503` junto. Controle FORA do escopo, sempre antes de concluir.
3. **Baseline indexado por CAMINHO acorda gate Tier 0** — o `multi-tenant-scope-baseline` reprovou porque o `McpActor` mudou de pasta no #5111 sem a entrada acompanhar. O #5114 pagou dívida que o #5111 deixou em `main`.
4. **Lane pode morrer calada num rename** — `teammcp-pest` roda **um** arquivo e filtrava `Modules/TeamMcp/**`; sem repontar, o teste que a ADR 0354 existe pra fazer executar voltaria a nunca rodar. O rename arrastou um consumidor funcional (`casos-results-publish` coleta JUnit por par arquivo:artefato).
5. **`BASELINE-ABSORB` precisa estar no commit que TOCA o baseline** (`markerInCommitsTouching`) — pus num commit vazio e o guard seguiu vermelho, com razão.

## Pointers detalhados

- Session log: [`memory/sessions/2026-07-31-teammcp-deprecacao-7-etapas.md`](../sessions/2026-07-31-teammcp-deprecacao-7-etapas.md)
- Destino por cluster: a lápide `SUPERFICIE.md` (não duplicar aqui)
- Proposal que mudou o receptor: [`2026-07-30-mcp-e-forja-jana-e-usuario`](../decisions/proposals/2026-07-30-mcp-e-forja-jana-e-usuario.md)

## Próximos passos pra retomar

```
brief-fetch && gh pr view 5122 --json state
```

O que fica aberto é **decisão, não trabalho**: a fusão das 4 abas do `/forja` com as telas homônimas da Forja. Foram **movidas, não fundidas** — fundir deleta uma implementação, e isso é produto.
