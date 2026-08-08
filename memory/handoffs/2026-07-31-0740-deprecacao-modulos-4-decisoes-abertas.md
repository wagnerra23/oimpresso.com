---
id: handoffs-2026-07-31-0740-deprecacao-modulos-4-decisoes-abertas
slug: 2026-07-31-0740-deprecacao-modulos-4-decisoes-abertas
title: "Deprecação dos módulos de governança — 34 módulos, 4 decisões [W] travando o resto"
tldr: "Módulos 37→34 (Admin/Brief/SRS/MemCofre saíram, ProjectMgmt→Forja, Auditoria revertida e FICA). Restam ADS/Governance/TeamMcp/Vestuario, travados em 4 decisões [W]: destino das 36.607 linhas do dual-brain, receptor dos 12 checkers do Governance, as 8 seções que o brief perde, e o Vestuario (produção ROTA LIVRE)."
date: "2026-07-31"
type: handoff
authority: canonical
lifecycle: ativo
owner: W
---

# Handoff 2026-07-31 07:40 — deprecação dos módulos de governança

> Sessão completa em [`sessions/2026-07-30-deprecacao-modulos-governanca-chips.md`](../sessions/2026-07-30-deprecacao-modulos-governanca-chips.md).

## Onde o trabalho parou

**Módulos: 34** (eram 37 no início da sessão).

| Situação | Módulos |
|---|---|
| ✅ saíram | Admin · Brief (absorvido pela Forja) · SRS · MemCofre |
| 🔄 renomeado | `ProjectMgmt` → **`Modules/Forja`** |
| 🛑 **revertido — FICA** | **Auditoria** ([W] na execução: *"não pode apagar"*) |
| ⏳ na fila | ADS · Governance · TeamMcp · Vestuario |

## As 4 decisões que travam o resto — só [W] resolve

1. **ADS** — `mcp_dual_brain_decisions` tem **36.607 linhas**: ARCHIVE ou DROP? Dado que facilita: **100,00% `outcome='cancelled'`**, `pr_url`=0, `commit_sha`=0 — escrita ativa provava cadência, não uso.
2. **Governance** — receptor dos **12 checkers** do `governance:audit`. O `MultiTenantScopeChecker` (ADR 0218, `critical`/`block`) é **a única varredura do projeto que exige `HasBusinessScope` em Model novo**; os 10 arquivos com `HasBusinessScope` fora dos módulos são testes por-model, não varredura.
3. **Brief/Forja** — o `GenerateBriefCommand` importa **8 serviços de `Modules\Governance`**. Quando o Governance cair, o brief perde **8 das ~10 seções**. Pré-requisito não pago.
4. **Vestuario** — é o vertical em **produção da ROTA LIVRE** (Larissa, `biz=4`, 99% do volume). O chip para na medição de propósito e te apresenta o número antes de qualquer remoção.

## Ordem topológica vigente

`Admin` ✅ → `Brief` ✅ → **`ADS`** → **`TeamMcp`** → **`Governance`**. Fonte: [proposal](../decisions/proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md) + adendo de 30/07 (Auditoria saiu da fila; CT 100 medido).

## Resíduos fechados nesta sessão (não re-medir)

- **CT 100 não é banco separado** — o MCP server de lá aponta pro Hostinger (`srv1818.hstgr.io` / `u906587222_oimpresso`); o staging (377 tabelas) também não tem as tabelas procuradas. As 5 tabelas de observabilidade do Governance **nunca rodaram em lugar nenhum**.
- **O required da ADR 0354 nunca existiu** — proteção viva tem 34 contexts e nenhum é `teammcp-pest`. Errata escrita.
- **O corpus estreito do `fact-anchor` está certo** — 533 docs varridos, 149 hits, **6 mentiras**. Controle-negativo (`decisions/`) deu 275 hits, todos legítimos.

## Regra operacional que a sessão provou

**Apagar e repontar os vínculos no MESMO PR.** Nunca deixar janela em que o repo mente. E antes de apagar, varredura de **CONSUMIDOR**, não de invocador — `git grep -lF 'Modules\<X>\'` **mais** `git grep -n "base_path('Modules/<X>"`, porque teste Pest fazendo `file_exists` não aparece na primeira.

Com sessões paralelas: `governance/catalog.json` é **derivado** dos `SCOPE.md` — conflito nele resolve com `catalog-graph.mjs --write`, nunca à mão. Idem baselines de catraca: **regenerar, não congelar por snapshot**.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `decisions-search "deprecar módulo governança ordem topológica Forja rename"` → 5 ADRs, nenhuma cobrindo a leva de 30/07 (a proposal segue `status: proposal`, não ratificada)
- Brief #442 (gerado há ~8h): HITL pending [W] = 4 · US não atribuída = 660 (517 sem dono) · SDD composta **55,3** (Δ−1,6)
- Tasks em voo já aparecem com módulo **`@ Forja`** — o rename propagou ao TaskRegistry

## Chips ainda disponíveis

`Deprecar ADS` · `Deprecar TeamMcp` · `Deprecar Governance` · `Reavaliar memory/modulos/`. Os quatro trazem embutidas as medições desta sessão — não re-descobrir CT 100 nem o required fantasma.
