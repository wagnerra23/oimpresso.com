---
date: "2026-08-10"
time: "13:30 BRT"
slug: jana-lida-inteira-56-gaps-e-o-eixo-que-faltava
tldr: "Faxina na Jana virou auditoria dos 555 arquivos do módulo. O JanaCockpitV2 (633 ln) estava morto e 7 docs canon diziam que não podia ser apagado, citando um COMENTÁRIO como prova de consumo. 56 gaps catalogados, e a classe é repo-wide: nenhum gate estava quebrado — faltava perguntar o que cada um NÃO olha. Fechado com o eixo 2 do test-lane-coverage (40 testes que a lane alcança e o driver faz pular, incl. trava Tier 0)."
prs: [5515, 5516, 5518, 5520, 5522]
decided_by: [W]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0344-two-strikes-cobre-processo
next_steps:
  - "[W] decidir os 6 chips abertos (todos iniciados em sessão paralela às 13:2x)"
  - "[W] decidir se converte os 56 gaps em backlog durável via skill audit-to-backlog (chip é UI, ADR 0070 manda task no MCP)"
  - "Os 40 testes mudos exigem CT 100 antes de ligar — se falhar, a lane trava merge de todos"
  - "NÃO dropar tabela órfã sem COUNT(*) em prod: a US-COPI-147 já decidiu não dropar 4 delas"
---

# Handoff — a Jana lida inteira, e o eixo que nenhuma máquina olhava

## Estado MCP no momento do fechamento

⚠️ **MCP INDISPONÍVEL.** `cycles-active` → `MCP error -32603: Bridge fetch error`.
`my-work` → `Server unavailable`. **Declarado, não inventado** (mesmo precedente do handoff
de 2026-08-09 23:00). Fallback usado: `git log` + `gh` + portas vivas do repo.

## O que aconteceu

[W] pediu *"faxina na Jana"*. O primeiro achado mudou o resto da sessão.

`resources/js/Pages/Jana/components/JanaCockpitV2.tsx` (633 ln) tinha **0 imports**, e **7
documentos canon** afirmavam que ele *"não podia ser apagado — é consumido por `Sells/Index.tsx:55`"*.
Aquela linha é **COMENTÁRIO**, e diz o oposto: que a tab Insights **saiu** de `/sells`. Cada doc
citou o anterior; nenhum reabriu a linha. A afirmação fez a **onda 4 da US-COPI-148 recuar por
escrito** do arquivo que ela teria limpado.

Daí [W] escalou: *"olhou todos os arquivos `Modules/Jana/*`?"* — não, 54%. Depois: *"leia o módulo
inteiro"*. Fechou em **555 de 555**, com dois adversários read-only (Tests/ e Database/) + verificação
própria com controle positivo em cada instrumento.

**56 gaps**, e a forma é sempre a mesma: **em nenhum deles o código está errado — o que está errado
é o registro sobre o código**, em duas variantes: *(a)* um artefato de governança afirma algo sobre
um alvo que ninguém reabriu; *(b)* **presença de registro ≠ execução** (arquivo de comando no disco
sem `commands([...])`; `Event::listen` + `singleton` pra cadeia que ninguém invoca).

## Artefatos gerados

| PR | O quê |
|---|---|
| [#5516](https://github.com/wagnerra23/oimpresso.com/pull/5516) | Catraca de deadlink travada — 1081 → 1074 (o gate avisava havia dias e ninguém gravou) |
| [#5515](https://github.com/wagnerra23/oimpresso.com/pull/5515) | `JanaCockpitV2` removido + afirmação falsa corrigida em 7 docs + 2 consertos de instrumento no RUNBOOK |
| [#5518](https://github.com/wagnerra23/oimpresso.com/pull/5518) | Errata `TEAM.md` §3.3 — dizia "dono em lugar nenhum" e o CODEOWNERS já enforçava |
| [#5520](https://github.com/wagnerra23/oimpresso.com/pull/5520) | [`AUDIT-GAPS-2026-08-10.md`](../requisitos/Jana/AUDIT-GAPS-2026-08-10.md) — 555/555, 56 gaps, 9 clusters |
| [#5522](https://github.com/wagnerra23/oimpresso.com/pull/5522) | Eixo 2 do `test-lane-coverage` + **B8** do [`GUIA-DO-SISTEMA`](../GUIA-DO-SISTEMA.md) (v1.5.0) |

**Todos mergeados.** Mais **6 chips** abertos e já iniciados por [W] em sessões paralelas.

## O achado Tier 0

`Modules/Jana/Tests/Feature/Ai/BriefDiarioAgentTest.php` está na **última linha do bloco
`ALLOWLIST VERDE (catraca)`** de `jana-pest.yml` — lane que roda **MySQL**. O arquivo pula quando o
driver **não é sqlite**. Não está na lista sqlite. A nightly também é MySQL. **Nunca roda, sai
verde** (skip = exit 0). Um dos 6 casos é *"Tier 0 cross-tenant: 5 Tools(biz=1) NUNCA expõem dados
de biz=99"* ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md), IRREVOGÁVEL).

Medido repo-wide: **40** arquivos nesse estado. **38 são a matriz do `modules-pest.yml`**, que roda
**sqlite** enquanto os arquivos exigem **mysql**.

## Persistência

- **git:** 5 PRs em `origin/main` (`dd56f6f1356` é o do inventário).
- **MCP:** ⚠️ **não propagado** — servidor fora do ar no fechamento. O webhook sincroniza quando voltar.
- **BRIEFING:** não tocado — a sessão não alterou capacidade do módulo, só o registro sobre ele.

## Lições catalogadas (minhas, nesta sessão)

Todas da mesma classe **LC-08** — e o ledger já foi incrementado no [#5515](https://github.com/wagnerra23/oimpresso.com/pull/5515) (75 → 77):

1. **`rc=$?` depois de pipe, 3×** — media o `tail`, não o `node`. Uma delas me fez registrar
   "anchor-lint verde" quando ele estava vermelho por dívida pré-existente.
2. **`npm run build` como prova de mudança em `.tsx`** — aquele config é o do Tailwind: **1 módulo**,
   zero `.tsx`. O certo é `build:inertia`.
3. **Regex de import ancorado em linha** — deu falso-positivo no `SellsTabelaUnificada` (import
   multi-linha). Peguei porque eu tinha visto o import horas antes.
4. **Contei 115 e depois 103 antes de chegar em 101** — não tinha visto que o `modules-pest` é
   sqlite, e não distinguia guard top-level de guard por-caso (**15** eram FP).
5. **Briefing errado ao adversário** — escrevi *"fora da lane = vermelho invisível"*; ele foi à porta
   viva `test-lane-coverage.mjs` e mostrou que **"FORA DO PR ≠ NUNCA RODA"**. Ele estava certo.

⚠️ **O padrão:** em 3 momentos a correção veio de [W] perguntando, não de mim medindo — *"olhou
todos?"*, *"quem é o responsável?"*, *"pode ser melhorado?"*. O instrumento sempre devolvia um
número plausível.

## Próximos passos pra retomar

```
node scripts/governance/test-lane-coverage.mjs --mudos    # os 40, atualizados
```
Depois: `memory/requisitos/Jana/AUDIT-GAPS-2026-08-10.md` (os 56, com prova e ✅/🔶 por linha).

## Pointers detalhados

- Inventário completo: [`requisitos/Jana/AUDIT-GAPS-2026-08-10.md`](../requisitos/Jana/AUDIT-GAPS-2026-08-10.md)
- Quem pode alterar + as 4 camadas + como um arquivo sobrevive: [`GUIA-DO-SISTEMA.md`](../GUIA-DO-SISTEMA.md) §B8
- A lápide da classe: [`proibicoes.md`](../proibicoes.md) §5 2026-08-10
- Session log: [`sessions/2026-08-10-jana-modulo-inteiro-e-o-comentario-que-virou-lei.md`](../sessions/2026-08-10-jana-modulo-inteiro-e-o-comentario-que-virou-lei.md)
