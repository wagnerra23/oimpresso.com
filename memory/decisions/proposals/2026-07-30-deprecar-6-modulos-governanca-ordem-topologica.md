---
status: proposal
title: "Deprecar os 6 módulos de governança como CONJUNTO, em ordem topológica — o acoplamento é entre eles, não com o produto"
proposed_by: Claude — decisão [W] 2026-07-30 "todos esses eu vou deletar: Admin, ADS, TeamMcp, Brief, Auditoria, Governance, SRS"
proposed_at: 2026-07-30
relates_to:
  - 0357-deprecar-srs-sucessor-kb-jana-governance
  - 0354-teammcp-pest-required-emenda-0314
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0237-jana-reconcile-loop-unico
  - 0053-mcp-server-governanca-como-produto
---

# Deprecar os 6 módulos de governança como conjunto

> **SRS já saiu** (E1→E6 em 2026-07-29, [ADR 0357](../0357-deprecar-srs-sucessor-kb-jana-governance.md), [#5036](https://github.com/wagnerra23/oimpresso.com/pull/5036)). Restam **6**: Admin · ADS · TeamMcp · Brief · Auditoria · Governance.

> ⚠️ **O Governance tinha plano, e ele concluía o OPOSTO** ([#5050](https://github.com/wagnerra23/oimpresso.com/pull/5050), sessão paralela, 29/07): o inventário foi feito **para viabilizar a deleção e concluiu "NÃO deprecar"** — infraestrutura consumida por módulos vivos, sem receptor para as duas peças centrais, *"caro e o ganho é negativo"*. Em 30/07 [W] decidiu deletar assim mesmo (decisão soberana; este doc respondia *"se vai sair, em que ordem"*, sem reabrir o mérito). **Em 31/07 [W] reverteu — o módulo FICA**, e o plano foi deletado. Ver o adendo do fim + a [lápide §5](../../proibicoes.md).
>
> **Este doc não substitui nenhum plano por módulo.** Os 5 novos estão em `memory/requisitos/<Mod>/DEPRECATION-PLAN.md`; o do Governance ganhou um **adendo** com a medição de produção (o limite nº 1 que ele mesmo declarava aberto), não uma reescrita.

## ⚠️ ERRATA 2026-07-30 — um risco FALSO, um número 3× subcontado, e um DROP que quebra sobrevivente

> Origem: revisão adversarial pedida por [W] (*"quero um adversário aqui"*) — **dois agentes de
> mandato oposto** (um refutando a deleção, outro refutando a consolidação) + medição em produção
> que nenhum deles tinha acesso. **Não reabre o mérito**; corrige medição. O corpo fica como registro.

### E1 — O **R3 é falso**: o `teammcp-pest` nunca foi promovido a required

O corpo trata como risco que deletar o TeamMcp deixe `main` sem poder mergear, por causa do flip da
[ADR 0354](../0354-teammcp-pest-required-emenda-0314.md). A **autoridade viva** diz o contrário:

```bash
gh api repos/wagnerra23/oimpresso.com/branches/main/protection \
  --jq '.required_status_checks.contexts[]' | grep -i teammcp
# → (vazio).  Total de contexts: 34
```

O flip **não aconteceu**. (Crédito: o `TeamMcp/DEPRECATION-PLAN.md` já tinha achado e rebaixado isso
no §Achado — foi este doc de topo que ficou desatualizado.) **Dos 34 required, exatamente 1** nasce
de PHP dos condenados: `ADR 0216 PR scan` → `governance:audit`.

### E2 — A tabela de medição declara **6 crons**. São **17**, e todos rodam em `live`.

Medido pelo **oráculo de runtime**, não por parse do `Kernel.php` — a lápide §5 2026-07-17 registra
que gate de ambiente tem **≥2 formas sintáticas** e que parsear uma é o erro:

```php
// prod, APP_ENV=live
foreach (app(Schedule::class)->events() as $e) { $e->runsInEnvironment('live'); }
// → 110 registrados · 108 rodam
```

| Módulo | Doc declara | Runtime (`live`) |
|---|---:|---:|
| ADS | **0** | **6** |
| Brief | **0** | **1** |
| Governance (família: `governance:` 6 · `governanca:` · `module:grade` · `observability:` · `charter:`) | 6 | **10** |
| **Total** | **6** | **17** |

Consequência direta: o **R2 do plano do ADS** (*"achar e desligar o produtor — não medido"*) tem
resposta, e ela estava no mesmo `Kernel.php` que o doc leu.

### E3 — 🔴 A Fase 4 do ADS marca `mcp_projects` / `mcp_project_parts` como DROP — e quebra o ProjectMgmt

`Modules/ProjectMgmt` **não está na fila** e escreve nas duas (`ProjectService.php`, `insertGetId`,
com `grep -c catch` = **0**). Ambas têm **0 linhas**, mas a dependência é de **schema**: o DROP vira
`SQLSTATE 42S02` → **500** em módulo sobrevivente. Ver errata do `ADS/DEPRECATION-PLAN.md`.

### E4 — O dado do ADS é escapamento de cron, não patrimônio (facilita a Fase 4)

`mcp_dual_brain_decisions`: **36.658** linhas · `outcome='cancelled'` em **100,00%** · `pr_url` e
`commit_sha` **NOT NULL = 0** · `resolved_by` = 41 (0,11%). Nenhuma decisão virou PR ou commit.
O corpo usa *"está escrevendo hoje"* como sinal de vida — o sinal prova **cadência**, não uso.

### E5 — Incoerência já materializada na ordem

O [#5045](https://github.com/wagnerra23/oimpresso.com/pull/5045) resgatou o `UiCatalogGenerateCommand`
do `Modules/Admin` (#2, já removido) **para dentro de `Modules/Governance`** — que é o **#6 da própria
fila**. Está na [ADR 0360](../0360-deprecacao-admin-center-supersede-0122.md) aceita, com a nota
*"passou a funcionar pela primeira vez"*. Terá de ser resgatado duas vezes. (O irmão
[#5046](https://github.com/wagnerra23/oimpresso.com/pull/5046) → `Modules/Arquivos` está correto —
Arquivos sobrevive.)

### O que a revisão NÃO mudou

O veredito de [W] segue soberano e a ordem topológica segue válida — **Auditoria** primeiro é a
única unanimidade dos dois adversários. O que mudou é que **Governance** ganhou um motivo acionável
pra não ser executado hoje (o `MultiTenantScopeChecker`, único enforcement Tier-0 arquitetural sem
substituto — ver errata do plano dele), e que **ADS** precisa de 2 correções antes da Fase 4.

## O que este doc adiciona, e por que não cabe num plano por módulo

O precedente é **um `DEPRECATION-PLAN.md` por módulo** — e ele continua valendo (os 5 planos estão ao lado deste, em `memory/requisitos/<Mod>/`). Mas a descoberta central desta medição é **cross-módulo** e não tem dono em nenhum deles:

**Os 6 quase não tocam o produto. Eles se seguram uns nos outros, em ciclo.**

Se cada plano for executado isolado, na ordem em que der na cabeça, o primeiro delete quebra os outros cinco.

## A medição (recibo — leia antes de discutir o plano)

Esta é a correção do erro central do plano do SRS, que a própria [reconciliação dele](../../requisitos/SRS/DEPRECATION-PLAN.md) registra: *foi escrito sem acesso ao banco, todas as linhas de volume com `?`*, e a medição de julho **virou o plano** (T1..T7 saíram de MIGRATE pra DROP, a ordem das fases mudou, o acoplamento que era "40+" era 6). Aqui a medição vem **primeiro**.

**Sistema medido:** produção — `APP_ENV=live`, `APP_URL=https://oimpresso.com`, database `u906587222_oimpresso`, **385 tabelas**. Existência por `information_schema.tables` (não por exceção capturada — "não existe no Hostinger" pode significar **vive no CT 100**, [ADR 0062](../0062-separacao-runtime-hostinger-ct100.md)).
**Data:** 2026-07-30.
**Controle positivo:** `business=82` · `users=124` · `transactions=75.255`.

| Módulo | Linhas em prod | Tabelas ausentes no Hostinger | Escrita mais recente | Acopladores externos (código) |
|---|---:|---|---|---:|
| **Auditoria** | **0** | 1 de 1 (`auditoria_audit_notes` **nunca migrou**) | — | **1** (só um teste) |
| **Admin** | **0** | 0 de 1 (`mcp_admin_audit_log` existe, vazia) | — | 2 |
| **Brief** | **438** (`mcp_briefs`) | — | — | 4 |
| **TeamMcp** | **61** | — | `mcp_ingest_heartbeat` 2026-07-21 | 10 |
| **Governance** | **20** | **5 de 6** | `mcp_sdd_scorecard_history` **2026-07-30 11:10** | 11 |
| **ADS** | **36.607** | 0 de 11 | `mcp_dual_brain_decisions` **2026-07-30 08:02** | 5 |

Três coisas que a tabela diz e que nenhum plano isolado diria:

1. **ADS e Governance estão ESCREVENDO hoje.** Não são zumbis. O ADS acumulou **36.607** decisões de dual-brain em `business_id=1` e gravou há poucas horas; o Governance gravou scorecard SDD às 11:10 de hoje. Deletar sem decidir o destino do dado é perda de trilha auditável, não limpeza.
2. **O oposto também é verdade, e é o caso mais limpo:** `Auditoria` tem **uma** tabela que **nunca chegou em produção** e **um** acoplador, que é um teste. É o delete mais barato do conjunto.
3. **5 das 6 tabelas do Governance não existem no Hostinger.** Isso não é "não existe" — as tabelas de observabilidade (`mcp_observability_spans`, `_aggregates_daily`, `mcp_scorecard_runs`, `mcp_governance_initiatives`, `mcp_module_grades_history`) provavelmente vivem no **CT 100**. **Medir o CT 100 é pré-requisito da E3 do Governance** e não foi feito aqui.

## ⚠️ ADENDO 2026-07-30 (tarde) — o CT 100 foi medido, e são **5** módulos, não 6

Duas coisas mudaram depois que este doc foi escrito. Ambas são medição, não opinião.

### 1. `Auditoria` SAI da fila — [W] reverteu na execução

*"acho que auditoria deve ficar"* · *"ele registra as alterações em cada registro é super importante"* · *"não pode apagar"* ([W], 2026-07-30, com a E3 já começando; nada de código foi removido — o `block-destructive` barrou o `git rm`). O **plano dele foi DELETADO** a pedido de [W] (*"remova o plano"*): plano suspenso ainda é armadilha. O registro do episódio vive na **lápide §5** de [`proibicoes.md`](../../proibicoes.md) (*"Deprecar/apagar Modules/Auditoria"* — não re-propor), e o módulo ganhou [`SCOPE.md`](../../../Modules/Auditoria/SCOPE.md) corrigido + [SDD completo](../../requisitos/Auditoria/SDD-auditoria-v1.0.md).

O ponto 2 da medição acima (*"`Auditoria` … é o delete mais barato do conjunto"*) **fica refutado**: ele media `auditoria_audit_notes` (0 linhas), que nunca foi o valor do módulo. Ele é o **leitor/revertedor** do `activity_log` — **117.510 linhas, última escrita 2026-07-30 11:22:14**. A ordem passa a ser **5**: Admin → Brief → ADS → TeamMcp → Governance.

### 2. Não existe banco no CT 100 — o pré-requisito dos 6 planos cai

Todos os planos carregam *"CT 100 não medido — pré-requisito da E3"*, e o ponto 3 acima hipotetiza que as 5 tabelas do Governance *"provavelmente vivem no CT 100"*. **Medido — a hipótese é falsa:**

| Fonte | Comando | Resultado |
|---|---|---|
| MCP server do CT 100 | `docker exec oimpresso-mcp printenv \| grep ^DB_` | `DB_HOST=srv1818.hstgr.io` · `DB_DATABASE=u906587222_oimpresso` — **é o banco do Hostinger** |
| staging CT 100 | `information_schema` em `oimpresso_staging` | **377 tabelas**, e as 6 procuradas **ausentes** também |

**Não há terceiro banco.** As 5 tabelas de observabilidade/scorecard do Governance + `auditoria_audit_notes` **não existem em lugar nenhum** — as migrations nunca rodaram, e essas features jamais produziram dado em produção.

**Consequências:** o resíduo *"CT 100 não medido"* dos 5 planos restantes fica **fechado — aponte pra este bloco, não repita o número** ([proibicoes §5 2026-07-17](../../proibicoes.md)). E o R1 do TeamMcp fica mais barato: `mcp_tokens`/`mcp_actors` vivem no MESMO banco que o MCP lê, logo "migrar pra Jana" é troca de dono **no código**, não migração de dados.

**Resíduo honesto:** medi **existência** de tabela e **contagem**, não o caminho de escrita. E staging ≠ prod — a ausência lá indica que a migration não roda, não prova sobre prod (essa parte vem da medição de 30/07 da manhã, que não é minha).

## O acoplamento, medido (e o instrumento que quase me enganou)

`git grep -lF 'Modules\<Mod>\'` fora da própria pasta, excluindo `memory/` e `.claude/`:

```
ADS         ← KB · ProjectMgmt · TeamMcp ×2 · 1 teste
TeamMcp     ← ADS ×2 · Governance ×4 · Jana ×4 · 1 charter
Brief       ← Governance · Jana · Manufacturing · scripts/governance/system-map.mjs
Auditoria   ← 1 teste
Governance  ← Admin ×3 · Brief ×2 · Cms · Connector · Jana ×2 · TeamMcp · 1 teste
```

**Ciclos reais:** `Governance ↔ Brief` · `Governance → TeamMcp → ADS → TeamMcp`.

> ⚠️ **Nota de método (LC-08).** A primeira medição deu `0` acoplador para **todos** os 6 — número que eu quase publiquei. Era o meu quoting em aspas duplas colapsando o padrão. Peguei rodando **controle positivo**: `Modules\Jana\` fora de `Modules/Jana/` tem **414** acopladores; se o filtro devolvesse 0 ali, estaria quebrado. *Quando o resultado de um filtro sustenta conclusão forte, teste-o contra um caso que você SABE que existe.*

## A ordem proposta (topológica — menos dependentes primeiro)

| # | Módulo | Por que aqui | Pré-requisito duro |
|---|---|---|---|
| 1 | **Auditoria** | 1 acoplador (teste) · tabela nunca migrou · 0 linhas | nenhum |
| 2 | **Admin** | 0 linhas · e sair daqui **remove 3 dos 11** acopladores do Governance | nenhum |
| 3 | **Brief** | 438 briefs + alimenta o hook `SessionStart` e o `system-map.mjs` | decidir o destino do `brief-fetch` (Tier A) |
| 4 | **ADS** | 36.607 linhas vivas | decisão [W] sobre o dado (ARCHIVE ou DROP) |
| 5 | **TeamMcp** | `mcp_tokens`=26 — é o **acesso do time ao MCP** | migrar 4 tools MCP + os tokens |
| 6 | **Governance** | 11 acopladores; cai por último porque 5 deles saem com #1-#5 | medir CT 100 antes |

## Os 3 riscos Tier 0 do conjunto

| # | Risco | Onde |
|---|---|---|
| **R1** | **Time perde acesso ao MCP.** `mcp_tokens` (26 linhas, `Modules/TeamMcp`) é o que autentica Felipe/Maiara/Luiz nas tools. Sair sem migrar = time cego. | `Modules/Jana/Mcp/OimpressoMcpServer.php` registra 4 tools do TeamMcp |
| **R2** | **`brief-fetch` morre e o protocolo de sessão vai com ele.** É skill **Tier A always-on** (CLAUDE.md) + hook `SessionStart`; 438 briefs em prod. | `Modules/Brief` + `scripts/governance/system-map.mjs` |
| **R3** | **Gates required perdem o dono.** O `teammcp-pest` foi promovido a **required há 3 dias** ([ADR 0354](../0354-teammcp-pest-required-emenda-0314.md), `decided_at: 2026-07-27`). Deletar o módulo com o gate required ligado deixa `main` sem poder mergear. | `governance/required-checks-baseline.json` |

## O que precisa de ADR nova, não de plano

Duas decisões deste conjunto **contradizem canon aceito** e por append-only não se corrigem editando:

- **[ADR 0354](../0354-teammcp-pest-required-emenda-0314.md)** (2026-07-27, `decided_by: [W]`) promoveu `teammcp-pest` a **required** — investimento, 3 dias antes de [W] decidir deletar o módulo. Deprecar exige **emenda** que rebaixe o gate primeiro.
- **[ADR 0357](../0357-deprecar-srs-sucessor-kb-jana-governance.md)** (2026-07-29) nomeia **Governance** como um dos **sucessores canônicos** do SRS ("validação"). Se o Governance também sai, o sucessor precisa ser re-apontado — senão o SRS fica com sucessor inexistente.

## Resíduo honesto

- **CT 100 não foi medido.** As 5 tabelas ausentes do Governance e o estado do MCP server (`mcp.oimpresso.com`) exigem `tailscale ssh root@ct100-mcp`. Sem isso, a E3 do Governance nasce com o mesmo `?` que condenou o plano do SRS.
- **Pest não rodado** — Tier 0 manda no CT 100. Os acopladores listados são leitura de código, não veredito de execução.
- **Telas não inventariadas aqui**: ADS 19 `.tsx` · Governance 7 · Auditoria 2 · Admin 8. O dono desse número é `npm run screen-coverage:report`, não este doc.
- **`Brief` não tem tabela própria** — o `mcp_briefs` é de outro dono. Quem apagar `Modules/Brief` **não** deve apagar a tabela sem checar quem mais escreve nela.

## ⚠️ ADENDO 2026-07-31 — `Governance` SAI da fila, e a fila que sobrou tem **1** módulo

Duas coisas mudaram, e as duas são fato medido em `origin/main`, não previsão.

### 1. `Governance` fica — [W] reverteu na execução

*"entendi o modulo ele não vai mais ser removido"* ([W], 2026-07-31, com a E1 já em andamento). **Nada de código foi removido**; o único PR que a tentativa produziu ([#5112](https://github.com/wagnerra23/oimpresso.com/pull/5112)) não tocou um arquivo sequer do módulo. O `DEPRECATION-PLAN.md` dele foi **deletado** — mesmo precedente do Auditoria (*"plano suspenso ainda é armadilha"*); o registro do episódio é a **lápide §5** em [`proibicoes.md`](../../proibicoes.md), não um plano com selo de "não execute".

Ao contrário do Auditoria, aqui **a análise e a decisão convergiram**: o plano já concluía "não deprecar" desde 29/07. O que a tentativa deixou de valor foi um achado **independente da deprecação** — o required `ADR 0216 PR scan` era incapaz de reprovar (`--diff-only` lê `git diff --cached`, vazio em CI → `models_scanned: 0` em todo PR; e ele é o único dos 12 checkers com `enforcement=block`). A varredura Tier-0 foi reconstruída na lane que já é required. Detalhe na lápide.

### 2. O destino de `Brief` e `TeamMcp` foi **absorção**, não deleção — e isso muda o placar

O corpo deste doc previa DELETAR os dois. O que aconteceu em 30-31/07 foi outra coisa:

| Módulo | Previsto aqui | O que de fato aconteceu |
|---|---|---|
| Auditoria | #1 deletar | **fica** (adendo anterior) |
| Admin | #2 deletar | **removido** ([ADR 0360](../0360-deprecacao-admin-center-supersede-0122.md)) |
| Brief | #3 deletar | **absorvido pelo `Modules/Forja`** ([#5098](https://github.com/wagnerra23/oimpresso.com/pull/5098) — tool, cron e serviços migraram) |
| ADS | #4 deletar | **de pé** — único remanescente |
| TeamMcp | #5 deletar | **desmontado pro `Modules/Forja`** em 7 etapas ([#5114](https://github.com/wagnerra23/oimpresso.com/pull/5114)…[#5122](https://github.com/wagnerra23/oimpresso.com/pull/5122)), 89 → 0 arquivos |
| Governance | #6 deletar | **fica** (item 1 acima) |

**Restou 1: `ADS`.** Dos 6 originais, 2 ficaram por decisão [W], 1 foi removido e 2 foram **absorvidos** — que não é o mesmo desfecho que este doc planejava, e vale registrar: absorção preserva a capacidade e move o dono; deleção não.

Consequência direta pro que este doc chamava de risco: o **R2** (*"`brief-fetch` morre e o protocolo de sessão vai com ele"*) **não se materializou** — o `GenerateBriefCommand` vive em `Modules/Forja` e segue injetando os 8 `*BriefLineService`, que continuam no Governance. O **R1** (time perde acesso ao MCP) idem: o TeamMcp foi movido, não apagado.

**Resíduo honesto:** com Governance ficando, o `MultiTenantScopeChecker` segue no `drift_checkers` **e** existe agora um teste Pest medindo o mesmo tema na lane required — dois medidores para um tema, o que este projeto proíbe por §5. Isso é decisão [W] em aberto, registrada na lápide.
