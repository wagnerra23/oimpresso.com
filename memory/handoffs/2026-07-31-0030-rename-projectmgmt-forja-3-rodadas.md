---
id: handoffs-2026-07-31-0030-rename-projectmgmt-forja
type: handoff
authority: historical
lifecycle: ativo
date: "2026-07-31"
---

# 2026-07-31 00:30 — `ProjectMgmt` → `Forja`: 2 PRs mergeados, 3 rodadas de refutação, 1 nav salva

> Session log completo: [`2026-07-31-rename-projectmgmt-forja.md`](../sessions/2026-07-31-rename-projectmgmt-forja.md)

## Estado no fechamento

**2 PRs MERGED:**

| PR | Commit | O quê | CI |
|---|---|---|---|
| [#5089](https://github.com/wagnerra23/oimpresso.com/pull/5089) | `6229fb4238d` | rename módulo + Pages + governança · `deadlink-gate` rename-aware | **admin** (ledger sem entry — declarado) |
| [#5096](https://github.com/wagnerra23/oimpresso.com/pull/5096) | `ea61c33f18b` | US-ADS-003/004 + teste de contrato das rotas | **CLEAN** (108 pass / 0 fail) |

**Smoke real em prod** (pós-deploy `success`): as 8 telas `/project-mgmt/*` → **302**, `/ads/admin/projects` → 302, `/forja` → 302, `/login` → 200. Nenhuma 500 ⇒ o PSR-4 renomeado carrega.

## O que o próximo precisa saber (e não deduzir)

### 1. O rename é PHP + Pages; a fachada que sobrou é deliberada

Renomeado: namespace `Modules\Forja\`, classes (`Forja{ServiceProvider,HealthCommand,AuditService}`), `module.json name=Forja`, `composer.json` psr-4, **`resources/js/Pages/Forja`**, `memory/requisitos/Forja`, scorecards por tela.

**NÃO renomeado — não "esqueci", quebraria prod:** URL `/project-mgmt` + route names · alias/config/lang `projectmgmt` · tabelas `mcp_jira_*` · **package key `project_mgmt_module`** e **permission `jana.mcp.usage.all`** (Tier 0, Camadas 1/3) · row `system.projectmgmt_version` · span OTel.

⚠️ **`isModuleInstalled()` tem checagem explícita de propósito** em `Modules/Forja/Http/Controllers/DataController.php`: a função assume que o nome nWidart == chave da row `system.{n}_version`, e o rename quebra isso — nenhum argumento único serve. Não "simplifique" pra `isModuleInstalled('Forja')`: procuraria `forja_version`, que não existe.

### 2. `deadlink-gate` agora resolve PATH RENOMEADO (mecanismo novo)

Link pra path renomeado deixou de ser link morto: resolve pelo `governance/ghost-rename-map.json` **classe A**, e só se o destino existir (match por segmento). 6 checks novos no selftest, 23/23. **Efeito: 47 refs resolvidas** (as deste rename + dívida histórica Copiloto→Jana e PontoWr2→Ponto); baseline **1096 → 1061**.

**A via óbvia foi testada e reprovada:** jogar `memory/decisions/` no `HISTORY_RE` quebra 9 checks e desliga o tombstone-aware junto. Não re-proponha.

### 3. A colisão de nav — o achado que justificou tudo

`modules_statuses.json["Forja"]` colide com `core_topnavs['Forja']` (cockpit `/forja` do TeamMcp). O `LegacyMenuAdapter` gravava `$out[$moduleName]` em **dois loops** sem checar — o segundo descartava o topnav do módulo, e **a nav sumiria em 8 telas**. Corrigido no adapter (`<nome>__core`). **"Forja" nomeia duas superfícies** hoje (`/project-mgmt/*` do módulo e `/forja/*` do TeamMcp) — quem for migrar o cockpit precisa saber disso.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` (@wagner) → **6 tasks em REVIEW**: US-COPI-123 `p0` · US-TR-309 `p1` · US-TR-310 `p1` · US-PG-008 `p1` · US-PROD-027 `p1` · US-INFRA-023 `p1`
- `decisions-search "rename módulo PHP-only fachada legacy"` → 0088 (padrão usado), 0092, 0099
- **Nota:** US-TR-309/310 (Triage) são **telas da Forja** — os charters delas moveram pra `Pages/Forja/Triage/` neste trabalho.

## Aberto — decisão [W]

1. 🔴 **Busca não sabe do rename** (classe, não deste PR): **27** ADRs citam `ProjectMgmt`, **6** citam `Forja`. Sem sinônimo configurado; o `ghost-rename-map` é lido por 4 scripts de governança, **não** pela busca (`decisions-search`, RAG Jana). Vale igual pra Copiloto→Jana e PontoWr2→Ponto. Opções: sinônimo Meilisearch · expansão de query · aceitar+documentar.
2. 🟡 **`ledger-check` sem entry** no #5089. 3 rodadas reprovaram (5,8% → 2,1% → 2,6%); **não escrevi entry eu mesmo** — sou o gerador, e `sessao_fresca: true` é a mentira que o campo existe pra impedir. Merge admin com registro auditável no [comment do PR](https://github.com/wagnerra23/oimpresso.com/pull/5089#issuecomment-5136603320).
3. 🟡 **`PAINEL-SISTEMA.md` não regenera** — `system-map --write` recusa por path morto `Modules/SRS` (ADR 0357). **Falha idêntica em `main`**, pré-existente. Não editei à mão (`authority: generated`).
4. 🟡 **`Pest (Ponto)`** falha em `main` desde 07-28 (5 runs) e **`Pest (KB)`** desde 17:51 de 07-30 — ambos anteriores a este trabalho.
5. 🟢 **Migrar o cockpit da Forja** (`ScorecardBuilderService` + `Services/Forja/*` + `team-mcp/Forja/Cockpit`) do TeamMcp pra `Modules/Forja` — é o que este rename destravou (linha 79 do `DEPRECATION-PLAN.md`). O `main` já começou: [#5098](https://github.com/wagnerra23/oimpresso.com/pull/5098) absorveu `Modules/Brief`, [#5101](https://github.com/wagnerra23/oimpresso.com/pull/5101) moveu `/api/mcp`.

## Erros meus, registrados

- **Varredura mecânica sem classificar contexto** — 35 achados em 3 rodadas. Pior: trocou o **sujeito** de fatos históricos e chegou a reescrever a lápide de 07-28 em `proibicoes.md` (revertida antes do commit; refutador confirmou diff vazio).
- **Colisão que não procurei** — checei dir e namespace, não **chave de menu**. Era a única com efeito em produção.
- **LC-08 ×2 na mesma sessão** — rodei `anchor-lint --check` quando o job roda `--check-entry --check-covers`; mesma classe do `casos-guard --check-baseline-shrink`. *Script com N modos é N gates.*
- **Apaguei a seção `## Infra Contract`** ao reescrever o corpo do PR, derrubando um gate que eu já tinha satisfeito.
