---
date: "2026-07-31"
time: "16:36 BRT"
slug: ads-incorporado-pelo-governance-3-de-7
tldr: "A sessão começou pra APAGAR o Modules/ADS e terminou incorporando-o ao Modules/Governance (decisão [W]). 3 de 7 partes entregues; #5127 mergeado e deployado desligou os 5 crons e o daemon do CT 100 — a fila de 36.862 parou de crescer. 4 adversários derrubaram 3 afirmações minhas, incluindo 'a fusão facilita os gates' (piora 2 dos 3, provado por contrafactual)."
prs: [5127, 5128, 5129, 5130]
decided_by: [W]
related_adrs:
  - 0087-drift-resolution-sem-mover-url
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0145-ia-administradora-pivot-ads-fsm-piloto-cobradora
  - 0130-handoff-append-only-mcp-first
next_steps:
  - "Parte 3 — ToolRegistry · UserScopeService · ProjectDecomposerService → Forja (3 dos 4 consumidores vivos são dela)"
  - "Parte 5 — Forja assume as 9 rotas de Modules/ADS/Routes/web.php, URLs CONGELADAS (ADR 0087)"
  - "Parte 6 — remoção do núcleo + 14 telas + os 3 gates + a decisão do loadMigrationsFrom (sessão limpa)"
  - "Parte 7 — ADR nova com supersedes: [0145] + errata no DEPRECATION-PLAN"
  - "Fora do escopo, mas achado aqui: Modules/Governance sem loadMigrationsFrom faz module:grade-snapshot e observability:aggregate-daily escreverem em tabelas inexistentes"
---

# ADS incorporado pelo Governance — 3 de 7 partes

> **A sessão começou pra APAGAR o `Modules/ADS` e terminou incorporando-o ao `Modules/Governance`.** A mudança de rumo foi decisão [W], tomada depois de a medição mostrar que "apagar" e "fundir" resolviam problemas diferentes. As 3 partes entregues são as que valem nos dois cenários.

## O que está no ar

| PR | Intent | Estado |
|---|---|---|
| [#5127](https://github.com/wagnerra23/oimpresso.com/pull/5127) | desliga os 5 crons `ads:*` + o daemon `ads-brain-a` do CT 100 | ✅ **MERGED e deployado**, smoke real feito |
| [#5128](https://github.com/wagnerra23/oimpresso.com/pull/5128) | `PolicyEngine` + `PolicyResult` + `GovernanceRulesService` → **Governance** | ✅ 100 SUCCESS, 0 falhas |
| [#5129](https://github.com/wagnerra23/oimpresso.com/pull/5129) | `SkillsService` + `ScaffoldSkillFromMission` + `skill:scaffold` → **Jana** | ✅ 99 SUCCESS, 0 falhas |

**Prova em prod (pós-deploy do #5127):** `/login` `< HTTP/1.1 200 OK`; `/ads/admin/policy`, `/ads/admin/meta-skills`, `/governance/policies`, `/ads/admin/decisoes`, `/governance/dashboard` todas `< HTTP/1.1 302 Found`. E o efeito no runtime: `schedule:list` de **103 → 98**, com **0** crons `ads:`.

**A fila parou de crescer.** Era o objetivo do primeiro passo: `mcp_dual_brain_decisions` vinha a **+204 linhas/dia** e agora não tem nenhum produtor — nem cron nem daemon.

## As medições que mudaram o plano

**O `outcome='cancelled'` em 100% não significa "canceladas".** É o `->default('cancelled')` da migration, e o `DecisionPresenter:37` o exibe como *"Aguardando você decidir"*. O retrato correto: **36.862 itens que nunca saíram do estado inicial**, `resolved_by` em 41 (0,11%), `pr_url` e `commit_sha` em **zero**. Sem outcome o `PatternLearning` não aprende, sem aprender nada é promovido, e tudo continua indo pra fila — um loop que dependia de um passo humano que nunca teve dono.

**O produtor não era só cron.** `ads-brain-a.service` — systemd no CT 100, `Restart=on-failure`, ativo desde maio — fazia poll de `/api/ads/*` a cada ~5s. Journal dos ~73 dias: **16.832 `HTTP 503`**, 5.074 decisões enviadas, **2.199** classificadas `unknown_commit`. Desligado (`inactive`/`disabled`, 0 processos, 33min20s de CPU consumidos ao todo). Reversível por `systemctl enable --now`.

**São 5 crons, não 6.** O `DEPRECATION-PLAN` dizia 6. `schedule:list` em prod (oráculo de runtime) dá **5**. A reconstrução do "6": filtrando `ads` **sem** os dois-pontos aparecem 7 linhas — as 5 reais mais duas do WhatsApp com "downlo**ads**"; como a linha do Job não tem string de comando, um filtro sobre `$e->command` daria 5+1.

**O `Modules/Governance` não tem `loadMigrationsFrom`** — é 1 dos 4 módulos (de 33) sem, e não há loader global. É a causa de **4 das 5 migrations dele nunca terem rodado em prod**. Consequência viva, fora do escopo mas registrada: os crons `module:grade-snapshot` (06:05) e `observability:aggregate-daily` (02:00) **rodam todo dia escrevendo em tabelas que não existem**.

## A dúvida do [W] que reorientou tudo

*"ads e governance são a mesma coisa?"* — não, e a confusão tem causa medível: **a palavra "governance" nomeia três coisas.**

1. **`scripts/governance/*.mjs`** — pasta na raiz, **não é módulo**. É de onde vêm **34 dos 35** checks required.
2. **`Modules/Governance`** — o módulo Laravel. **1** required (`governance:audit`), 7 telas, 5 tabelas (4 inexistentes em prod).
3. **`GovernanceRulesService` + `mcp_governance_rules`** — que viviam **dentro do ADS**.

O que morde não é nenhum dos dois módulos: é a pasta de scripts. Medido: só **2 workflows** invocam artisan de `Modules/Governance`, e um deles é advisory desde a ADR 0314.

## O review adversarial derrubou 3 afirmações minhas

Quatro agentes de mandato oposto, ~1,1M tokens.

**1. "A fusão deixa os 3 gates mais fáceis" — REFUTADA por contrafactual rodado.** Deleção: deadlink **6** arquivos, `anchored_dead` **3**. Fusão: **7** e **5**. Dois pioram. O mecanismo: o baseline do deadlink é chaveado por **caminho**, então mover um arquivo zera a folga no destino — e o violador extra é um arquivo movido que continua vivo apontando pra paths que a própria fusão destruiu.

**2. "ADS e Governance têm acoplamento zero" — REFUTADA.** Eu medi só por `use` de namespace, exatamente a cegueira que eu tinha criticado no plano. Por **tabela** eles se tocam: `mcp_governance_rules` tem migration no ADS e é lida/escrita pelo Governance em 8 arquivos + 4 testes.

**3. "`SkillsService` vai pro Governance" — ERRADA.** O plano canônico já dizia Jana (*"o service está do lado errado da fronteira desde sempre"*), o service **já importava** `Modules\Jana\Entities\Mcp\McpSkill`, e como as 5 telas de Skills morrem depois, o Governance receberia um service com zero consumidor. Corrigido.

**E dois bloqueadores de canon que o adversário achou:** a [ADR 0087](../decisions/0087-drift-resolution-sem-mover-url.md) (`aceito`/`canonical`/`ativo`) decide *"URL fica onde está"*, e o `governance/ghost-rename-map.json` — curado **em 31/07** — diz textualmente *"`/ads/admin/*` **preservadas** (ADR 0087)"*. A [ADR 0086](../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) é dona da fronteira *decision flow ≠ governance UI*, e a linha do `SCOPE.md` foi **relida e mantida em 31/07**. Por isso o recorte final **congela URLs, nomes de rota, permissions e `ads_module`**.

## Erros meus, e o que cada um ensinou

**`Infra Contract`.** Conferi o matcher pensando no `Console/Kernel.php` do PR 1 e não reconferi pros PRs que tocam ServiceProvider — `Modules/**/Providers/*ServiceProvider.php` está na lista. Corrigido com `curl -sv` literal.

**`PHPStan` — errei duas vezes seguidas.** Repontei os 5 `path:` do baseline achando que bastava; seguiu vermelho. **O erro real não estava no log** (o job usa `--error-format=github`, então vira anotação) — só apareceu pela API: a entrada casa por `path:` **E** por `message:`, e a mensagem carregava o FQCN antigo. Uma linha. Lição perene: **veredito de gate sem o erro em mãos é adivinhação** — e eu adivinhei uma vez antes de ir buscar.

**Falso alarme de sessão paralela.** Três agentes reportaram "outra sessão mutando o worktree". Era o meu quarto agente, que precisava mover arquivos pro contrafactual e rodava junto com três read-only no mesmo worktree. **Não rodar agente mutante em paralelo com read-only no mesmo worktree.**

**Quase reportei incidente em prod.** O site inteiro deu 503 no meio da sessão. Antes de gritar, medi: `storage/framework/down` existia e o `git HEAD` de prod era o meu próprio PR — era a janela de manutenção do deploy do #5127. Mesma família do erro catalogado em 2026-07-31 (*"declarei regressão medindo durante deploy"*), evitado desta vez por conferir antes de concluir.

## O que falta — partes 3, 5, 6, 7

| # | Intent | Nota |
|---|---|---|
| 3 | `ToolRegistry` · `UserScopeService` · `ProjectDecomposerService` → **Forja** | 3 dos 4 consumidores vivos são a Forja |
| 5 | Forja assume as 9 rotas que hoje moram em `Modules/ADS/Routes/web.php` — **URLs congeladas** | o `Http/routes.php` da Forja tem 55 rotas e **nenhuma** é essas |
| 6 | Remoção do núcleo + 14 telas + reconciliação dos 3 gates + decisão do `loadMigrationsFrom` | **a arriscada** |
| 7 | ADR nova com `supersedes: [0145]` + errata no `DEPRECATION-PLAN` | 0145 é `aceito`/`ativo` com 22 refs |

**Por que a 6 merece sessão limpa:** ela tem os 6 arquivos de deadlink (3 dos quais são ADR append-only), a catraca de `screen-coverage` que compara **conjunto** e não contagem (falha com `charter: 221 → 221`), e a decisão do `loadMigrationsFrom` que, feita no automático, **acorda 4 migrations dormentes e cria 4 tabelas em prod que ninguém pediu**.

## Estado das decisões [W]

| Decisão | Estado |
|---|---|
| Governance **incorpora** o ADS (em vez de apagar) | ✅ tomada 31/07 |
| Recorte revisado pós-adversário (política→Governance · registro→Forja · skills→Jana · núcleo morre · URLs congeladas) | ✅ aprovado |
| `mcp_dual_brain_decisions`: **ARCHIVE → DROP**, preservando as 41 linhas com decisão humana | ✅ decidido (dump no CT 100, nunca em git; 87,75 MB) |
| Daemon religado depois? | ✅ **não** — só se o Governance ganhar consumidor da fila |
| `Modules/Governance` fica ou sai (passo 6 da fila original) | ⏳ **em aberto** — não é deste passo |

## Resíduo honesto

- **Snapshot MCP não tirado.** O protocolo de fechamento pede `cycles-active` + `my-work` + `sessions-recent` + `decisions-search`. Não rodei — a sessão saturou de medição (prod, CT 100, 4 agentes adversariais, 3 refactors) e preferi registrar a ausência a fingir a consulta.
- **Pest não rodado** — Tier 0 manda no CT 100. Os 3 PRs passaram no CI, que é o gate de merge.
- **As 12 telas do núcleo não foram avaliadas uma a uma.** O adversário achou um defeito concreto no recorte: `Decisoes.tsx:219` linka **cada linha da lista** pra `DecisaoShow`, que estava na lista de morrer — matar o detalhe sem patchar a lista deixa a sobrevivente com tudo em 404. Tratar na parte 6.
- **`tests/Feature/Skills/SkillsControllerTest.php`** tem 7 testes batendo em `/ads/admin/skills*` — quebram quando as telas de Skills saírem (parte 6), e ele vive **fora** de `Modules/`, então varredura módulo-escopada não o enxerga.
- **Conflito de merge conhecido:** #5128 e #5129 nascem de `origin/main` e as duas regeneram `memory/requisitos/ADS/SUPERFICIE.md`. Quem mergear em segundo roda `node scripts/governance/module-surface.mjs ADS --write`.

## Estado MCP no momento do fechamento

Não consultado nesta sessão (ver Resíduo honesto). O estado verificável está no git e no GitHub: 3 PRs abertos/mergeados, `main` com o #5127 já deployado em prod às ~16:19 BRT, e as branches `claude/ads-desliga-produtores`, `claude/ads-politica-para-governance` e `claude/ads-skills-para-jana` publicadas.
