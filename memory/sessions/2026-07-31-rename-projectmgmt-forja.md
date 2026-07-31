---
id: sessions-2026-07-31-rename-projectmgmt-forja
type: session
authority: historical
lifecycle: ativo
date: "2026-07-31"
---

# Rename `Modules/ProjectMgmt` → `Modules/Forja` — e as 3 rodadas que o gate forçou

> Trabalho atravessou a virada: os merges saíram em **2026-07-30** (21:51Z e 22:4xZ), o fechamento é de **07-31**.
> PRs: [#5089](https://github.com/wagnerra23/oimpresso.com/pull/5089) (rename, merge admin) · [#5096](https://github.com/wagnerra23/oimpresso.com/pull/5096) (US do ADS, **CI limpo**).

## O pedido e o que ele destravou

[W]: *"ProjectMgmt->Forja renomear"*. O rename não é cosmético — o `DEPRECATION-PLAN.md` do TeamMcp (linha 79) marcava a **Forja** como o **único** item sem receptor derivável (*"Não há módulo receptor natural"*). Com `Modules/Forja` existindo, o buraco ganhou dono. (Confirmado depois: em <24h o `main` já absorveu `Modules/Brief` na Forja — [#5098](https://github.com/wagnerra23/oimpresso.com/pull/5098) — e moveu `/api/mcp` da Jana pra lá — [#5101](https://github.com/wagnerra23/oimpresso.com/pull/5101).)

## Escopo: PHP-only virou PHP + Pages, por medição

Comecei pelo padrão da [ADR 0088](../decisions/0088-module-rename-php-only.md) (rename PHP-only, fachada legacy) — que cita este caso nominalmente. Na 1ª versão mantive `resources/js/Pages/ProjectMgmt/` como fachada, e **respondi a [W] que as Pages não mudariam**.

Estava errado, e quem me corrigiu foi a pergunta dele (*"todas as referências vão ser trocadas? por favor diga que sim"*), que me obrigou a medir:

- **Precedente no disco:** `Pages/Copiloto` **não existe** (existe `Pages/Jana`); `Pages/PontoWr2` **não existe** (existe `Pages/Ponto`). O "PR-5 (Pages React)" que a 0088 deixou como follow-up **foi executado** nos renames anteriores — a fachada de Pages era letra morta.
- **Custo medido de não mover:** o `module-grades-gate` acusou **Forja 85 → 82 (-3)**, rastreado até `ModuleGradeService:643` — `$pagesModulePath = Pages/{$name}`; com `Forja` o dir não existia → `tsxFiles=0` → **D3.c (Charter por tela ≥30%, 3 pts) caiu 3→0**. Exato.

Movi as Pages. O `-3` fechou na origem e o baseline seguiu **85** intocado.

**Fachada que ficou** (quebraria prod): URL `/project-mgmt` + route names · alias/config/lang `projectmgmt` · tabelas `mcp_jira_*` · **package key `project_mgmt_module`** e **permission `jana.mcp.usage.all`** (Tier 0 — Camadas 1 e 3 de [proibicoes.md](../proibicoes.md)) · row `system.projectmgmt_version` · span OTel.

### A sutileza que exigiu decisão

`ModuleUtil::isModuleInstalled($n)` assume que o nome nWidart **e** a chave da row `system.{n}_version` coincidem. O rename quebra isso e **nenhum argumento único serve**: `'Forja'` procura `forja_version` (inexistente); `'ProjectMgmt'` falha no `Module::has()`. Trocado por checagem explícita `Module::has('Forja') && System::getProperty('projectmgmt_version')`, com `try/catch` espelhando o ModuleUtil (fresh DB). Comportamento observável idêntico.

## Destrava de migrações: `deadlink-gate` rename-aware

[W]: *"retire as travas dos ADRs para conseguir terminar as migrações"*.

**O problema real:** todo rename de pasta fabrica link morto em ADR aceita — que o append-only **proíbe** editar. Sem saída, a única opção era inflar o baseline a cada migração: o oposto do ratchet só-desce.

**A via direta foi testada e REPROVADA:** jogar `memory/decisions/` no `HISTORY_RE` quebrava **9 checks** do selftest e desligava junto o tombstone-aware ([ADR 0316](../decisions/0316-esquecimento-real-adr-tombstone.md)×0347), que tem infra dedicada (`adr-tombstones.json`, 173 refs resolvidas). Revertido.

**O que ficou:** mesma doutrina do tombstone por outra porta — link pra **path renomeado** não é link morto, resolve pelo rename-map curado. Duas travas: só **classe A** (evidência dura) e só se o **destino existir**; match por segmento (`ProjectMgmtLegado` não casa). 6 checks novos no selftest (2 release + **4 bite**), 23/23 verde.

**Efeito medido: 47 referências resolvidas** — as deste rename **e** dívida histórica de Copiloto→Jana e PontoWr2→Ponto. Baseline **1096 → 1061 (−35)**.

## As 3 rodadas de refutação (GT-G5) — o que elas pegaram

O `ledger-check` classificou o PR como lote-IA (23 arquivos em `memory/requisitos/`) e exigiu refutação adversarial em sessão fresca. Nenhuma rodada aprovou:

| Rodada | error_rate | Achado principal |
|---|---:|---|
| 1 | 5,8% (9/155) | **sujeito histórico trocado** |
| 2 | 2,1% (4/193) | o lote publicava **um gap sobre si mesmo** |
| 3 | 2,6% (22/831) | **colisão de nav — regressão funcional** |

### Rodada 1 — falsificação de fato histórico (o pior)

A varredura mecânica reescreveu o **sujeito** de frases que registravam evento passado: `TaskRegistry/SPEC.md:244` dizia *"rename `Modules/ProjectMgmt → Modules/Project`"* (o plano da Fase 3.9) e virou *"`Modules/Forja → Modules/Project`"* — o repo passou a afirmar um plano que o **próprio PR** declara abandonado. Idem `CAPTERRA-FICHA.md:17`.

Pior: a mesma varredura tinha tocado **`memory/proibicoes.md`, `LICOES_CODE.md` e `08-handoff.md`**. A lápide de 2026-07-28 cita **literalmente** o nome histórico do teste (*"F6 ProjectMgmtHealthCommand registrado"*) e o `ProjectMgmtServiceProvider` da época — trocar ali é a classe que o ledger já condenou textualmente (*"E2, o pior: FALSIFIQUEI fato historico verdadeiro"*). **Revertidos**; o refutador confirmou diff vazio nos três.

### Rodada 2 — o lote publicando um gap sobre si mesmo

`vital-signs.json:335,576` tinha `"mod": "ProjectMgmt"` enquanto `pior_tela`/`screen` do **mesmo objeto** já diziam `Forja/…`. Consequência que o próprio lote commitava: `service-scorecard.json` passou de `"unmatched_screen_dirs": []` para `["Forja"]`, e `orphan_screen_ns` ganhou o fantasma `ProjectMgmt`.

### Rodada 3 — a nav quebrada (regressão funcional, nenhum gate cobria)

`modules_statuses.json["Forja"]` colidiu com `core_topnavs['Forja']` (o cockpit `/forja`, que mora em `Modules/TeamMcp`). O `LegacyMenuAdapter` grava `$out[$moduleName]` em **dois loops** sem checar colisão — o segundo **descartava** o topnav do módulo. **A nav sumiria nas 8 telas** `/project-mgmt/*`. Em `origin/main` as chaves eram distintas; a colisão nasceu do rename.

Renomear qualquer lado não servia (a chave `Forja` do config é asserida por `ForjaRoutesSmokeTest:269`). Corrigido **no adapter**: chave duplicada vira `<nome>__core`. Os dois grupos são legítimos e servem URLs distintas; o front (`usePageProps.ts:65`) casa pelo 1º segmento do **href do item**, não pela chave.

## Erros meus — o padrão desta sessão

1. **Varredura mecânica sem classificar contexto** (rodadas 1–3): trocar PATH sem trocar CHAVE/COLUNA/TÍTULO no mesmo objeto, e trocar sujeito de fato histórico. 35 achados no total.
2. **Colisão que não procurei:** verifiquei colisão de **dir** e de **namespace** no início e considerei suficiente. **Colisão de chave de menu** passou — e era a única com efeito em produção.
3. **LC-08, 2× na mesma sessão — rodei o modo errado do gate.** Local usei `anchor-lint --check` (verde); o job roda `--check-entry --check-covers --baseline`. Mesma classe do `casos-guard` (`--check-baseline-shrink`). *Script com N modos é N gates.*
4. **Apaguei a seção `## Infra Contract`** ao reescrever o corpo do PR, derrubando um gate required que eu mesmo tinha satisfeito.

## Verificação em produção (smoke real)

Deploy `success`. O `deploy.yml` já roda `composer dump-autoload -o --classmap-authoritative` **e** tem gate de classmap/boot que segura o site em **503 gracioso** se falhar — o risco do PSR-4 estava coberto por automação, não por lembrete.

```
/project-mgmt/{board,backlog,roadmap,my-work,inbox,triage,activity,burndown} → 302 (8/8)
/ads/admin/projects → 302 · /forja → 302 · / → 200 · /login → 200
```

O `302 → /login` é a prova: o Laravel bootou e o middleware `auth` rodou. Se o autoload do namespace renomeado tivesse falhado, seria 500.

## PR #5096 — as US do ADS

Fecha o advisory `charter related_us join`. No #5089 **recusei preencher**: o SPEC do ADS tinha 2 US e nenhuma cobria as telas — inventar id seria fabricar âncora (§5). As US foram escritas **do código** (`index`/`store`/`show`/`decompose` + rotas), com `status: done` porque descrevem o que já roda.

O gate ensinou duas coisas: `**Acceptance:**` **não casa** no `DOD_RE` (que aceita `DoD|Aceite|Critérios de Aceite|Acceptance Criteria`), e o gate lê `@covers-us` **nos testes citados em `**Testado em:**`** — marcador solto não basta. Criei o teste que faltava: cruza registro de rotas × classe no disco, com **controle-negativo** (o namespace antigo não pode resolver).

## Lacuna que fica (classe, não deste rename)

[W] perguntou: *"ADS e ADR vão funcionar na Jana e Forja? E a governança funciona nos dois?"*

**Funcionam** — medido: grades `Forja=85`/`Jana=73`, `modules_statuses` ambos, `service-scorecard` ambos, catalog com nós `module:Forja`/`module:Jana` e **91/92 arestas** (incl. `delegatesTo`/`dependsOn` **ADS→Forja**), prod 302.

**Mas a busca não sabe do rename:** **27** ADRs citam `ProjectMgmt`, só **6** citam `Forja`. Não há sinônimo configurado, e o `ghost-rename-map` — que conhece o rename — é consumido por **4 scripts de governança**, não pela busca (`decisions-search`, RAG da Jana). Vale igual para Copiloto→Jana e PontoWr2→Ponto. **Decisão [W] pendente:** sinônimo no Meilisearch · expansão de query no `decisions-search` · aceitar e documentar.

## Referências

[ADR 0088](../decisions/0088-module-rename-php-only.md) (padrão do rename) · [ADR 0070](../decisions/0070-jira-style-task-management-current-md-removed.md) · [ADR 0316](../decisions/0316-esquecimento-real-adr-tombstone.md)/0347 (tombstone, doutrina reusada) · [ADR 0160](../decisions/0160-scoped-scorecards.md) (bucket gate) · [ADR 0275](../decisions/0275-calendario-promocao-gates-sdd.md) (ratchet)
