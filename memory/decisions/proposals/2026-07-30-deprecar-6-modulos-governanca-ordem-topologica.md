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

> ⚠️ **O Governance já tem plano, e ele conclui o OPOSTO.** [`requisitos/Governance/DEPRECATION-PLAN.md`](../../requisitos/Governance/DEPRECATION-PLAN.md) ([#5050](https://github.com/wagnerra23/oimpresso.com/pull/5050), sessão paralela, 29/07) fez o inventário **para viabilizar a deleção e concluiu "NÃO deprecar"** — é infraestrutura consumida por 6 módulos vivos, sem receptor para as duas peças centrais, *"caro e o ganho é negativo"*. **Esse veredito técnico não foi refutado por esta medição** — segue valendo como análise. O que mudou é que em 30/07 [W] decidiu deletar assim mesmo, e a decisão é soberana. Este doc não re-abre o mérito: ele responde *"se vai sair, em que ordem, sem quebrar os outros cinco"*.
>
> **Este doc não substitui nenhum plano por módulo.** Os 5 novos estão em `memory/requisitos/<Mod>/DEPRECATION-PLAN.md`; o do Governance ganhou um **adendo** com a medição de produção (o limite nº 1 que ele mesmo declarava aberto), não uma reescrita.

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
