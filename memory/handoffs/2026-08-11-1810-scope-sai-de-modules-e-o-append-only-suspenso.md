---
slug: scope-sai-de-modules-e-o-append-only-suspenso
date: "2026-08-11"
tldr: "Fase 3 do move de doc de módulo mergeou (#5568, 135/135 verde) após 4 rodadas de refutação adversarial que acharam 2 crons de produção que ficariam mudos. Dívida do Compras paga a pedido de [W]. Append-only de ADR suspenso por label — 13 ADRs pendentes."
autor: "[CL] Claude Code"
sessao: pensive-curran-ff053c
prs: [5568]
related_adrs:
  - 0375-scope-md-sai-de-modules-para-memory-requisitos
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
---

# Handoff — o SCOPE sai de `Modules/`, e o append-only sai do caminho de [W]

> **Cumprindo R12** via skill `encerrar-sessao` (ativação lazy, hook `UserPromptSubmit`).
> MCP indisponível (timeout no `SessionStart`) — checklist por **fallback filesystem**,
> declarado em vez de omitido.

## O que fechou

**[#5568](https://github.com/wagnerra23/oimpresso.com/pull/5568) mergeado 17:20 — CI 135/135 verde.**
33 `SCOPE.md`/`LICOES-OPERACAO.md` saíram de `Modules/<X>/` para `memory/requisitos/<X>/`
([ADR 0375](../decisions/0375-scope-md-sai-de-modules-para-memory-requisitos.md)), com os 42
consumidores reescritos. Verificado no `main`: **0 `.md` soltos em `Modules/<X>/`, zero lápide.**

## O que quase escapou (e é o que vale carregar)

**Quatro rodadas de refutação GT-G5** — 59,8% → 10,4% → 3,1% → **0,95% aprovado**. Duas refutações
independentes acharam **conjuntos diferentes** de graves; uma passada não teria bastado.

Dois **crons de produção** ficariam mudos em silêncio:

| cron | o que aconteceria |
|---|---|
| `governance:detect-drift` (06:15 `live`) | `discoverModules()` acharia **0 módulos** → `SUCCESS` calado |
| `jana:health-check` (diário) | devolveria `"Skipped (ledger ausente)"` **para sempre** |

**Em 8 dos 10 achados o padrão foi o mesmo, e é meu:** consertei o COMENTÁRIO e deixei o CÓDIGO.
No `DetectDriftCommand` cheguei a reescrever o docblock afirmando o path novo — o código passou a
mentir sobre si. **Gate verde não é cobertura:** meus "13/13 gates verdes" mediam gates; nenhum
gate olha **consumidor**, e era lá que o dano estava.

A rodada 3 provou o corolário: **2 dos 5 achados dela eram defeitos que os consertos da rodada 2
criaram** (um `file_put_contents` cru 150 linhas abaixo do helper corrigido; acentuação pt-BR
degradada nas próprias linhas de conserto).

**Baseline de deadlink terminou ABAIXO do pré-PR** (575/1094 → 572/1092): eu tinha absorvido 105
links que eram consertáveis; consertados na fonte, o PR passou a **reduzir** dívida.

## Dívida do Compras paga ([W]: *"pague a dívida, e deixe requerido"*)

Lane segue `required`. Os 4 vermelhos eram **três problemas distintos**:

1. **`UC-CMP-06` — defeito real:** `Index.tsx:256` mandava `stage: localFilter` na busca — rótulo
   de **exibição** no lugar do filtro de **servidor**. Buscar com aba ativa dava **302**.
   O `casos.md` tinha decidido *"a aba emite o status core"*; **medi que não fecha** — "A pagar"
   é `final_total − amount_paid` (pagamento, não status) e "Em trânsito" agrupa **dois** status.
   Registrei o desvio NO `casos.md` em vez de desviar calado.
2. **`UC-CMP-07`:** `SORT_MAP` tinha 7 colunas, whitelist aceitava 4.
3. **`NPlusUm` (2 testes):** `Trait::CONST` virou `Error` no PHP 8.3+; a lane roda 8.4.

## Append-only de ADR — suspenso por label ([W]: *"remover o append-only para mim"*)

Implementado **por label `adr-body-edit-W`**, não por autor. **Medido:** todo PR aberto por agente
sai como `wagnerra23` (o #5568 inclusive) — isentar por autor removeria a proteção de **todo PR de
agente**, em silêncio. Com label, o ato é consciente, por-PR e auditável.

**4 proposals aceitas** por [W] + paths corrigidos. A `jana-ledger` era a única onde o path não era
referência e sim **a decisão** (*"home canônico escolhido"*) — aceitá-la verbatim re-decidiria o
home que a 0375 moveu; corrigi e registrei que **só o endereço mudou**.

## ⚠️ Aberto — próxima sessão

- **13 ADRs append-only** ainda citam paths antigos. [W] autorizou editar; o label já está no gate,
  o YAML valida. **Não feito.** Classificar cada ocorrência entre **ponteiro vivo** (atualizar) e
  **fato datado** (preservar) — reescrever fato histórico falsificaria a trilha.
- **Cascata não feita:** `CONSTITUTION.md` Art. 3, `CLAUDE.md` (tabela *"Mudança ADR canon = ❌ NÃO"*)
  e `proibicoes.md` seguem dizendo append-only absoluto. Com o label existindo, isso é LC-10
  (artefato afirmando enforcement que não tem mais). Tocar a Constituição exige label
  `constitution-amendment` + audit — outro ato.
- **`CONSTITUTION.md:173/229`** cita `Modules/<X>/SCOPE.md` — resíduo declarado desde o #5568.
- **2 BRIEFINGs** (`Superadmin`, `Woocommerce`) com frontmatter legado em grace period.
- **`distiller_freshness` em 6** — volta a 0 rodando `jana:distill-module-truth` no CT 100.
- **3 tarefas [W] abertas:** intermitente do KB (403 em controle positivo), `compras-pest.yml` que
  se declara advisory sendo required, e o RUNBOOK do governance-gate.

## Estado no fechamento (fallback filesystem — MCP timeout)

- Branch `claude/adr-paths-pos-0375`, a partir de `origin/main` fresco.
- `#5568` **MERGED**; `#5600`/`#5601` abertos por sessões irmãs.
- Handoffs anteriores: `2026-08-11-1345` · `2026-08-11-1336`.
