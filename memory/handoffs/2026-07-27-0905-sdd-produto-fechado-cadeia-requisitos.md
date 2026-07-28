---
date: "2026-07-27"
time: "09:05 BRT"
slug: sdd-produto-fechado-cadeia-requisitos
tldr: "Produto fechado (7/7 telas + 4 fluxos Blade = 11 casos.md, 54 UC). A cadeia US→CU→UC→teste ganhou gerador com status derivado e fila de crescimento. 4 máquinas ligadas. 8 defeitos provados por CI, nenhum corrigido (Tier 0). Dívida nº1: o gerador usa o `status:` que a ADR 0302 aposentou."
decided_by: [W]
prs: [4807, 4808, 4809, 4811, 4814, 4816, 4823, 4826]
us: [US-PROD-020, US-PROD-023, US-PROD-028]
next_steps:
  - "Trocar o predicado do requisitos-status.mjs de `status:` para a âncora `Implementado em:` (ADR 0302 — o status é aposentado como done-ness)"
  - "Descer US-PROD-029..032 do MCP pro SPEC.md — hoje não aparecem no backlog nem no painel"
  - "Decidir os 8 defeitos provados (todos [V0] ou Tier 0)"
  - "Tirar o FK de tenant do mass-assignment ($guarded = ['id'] nos 3 models) — mata a família UC-PTAB-04 na raiz"
related_adrs:
  - 0351-sdd-from-source
  - 0302-fonte-unica-doneness-anchor-aposenta-status-spec
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
---

# Handoff — SDD do Produto fechado + a cadeia de requisitos ganhou máquina

> **Pedido de origem ([W]):** *"tenho que documentar todo SDD (…) que seja enterprise e que sobreviva ao tempo, que tenha dono, tenha os requisitos, teste e evolução e aprendizado no módulo"* → depois, dois cortes que mudaram o rumo: *"está muito amador sem propósito"* e *"crie a estrutura e escreva a base, mantenha o sistema crescente"*.

## ⚠️ LEIA PRIMEIRO — a dívida nº1 que eu deixei

**O `requisitos-status.mjs` usa um campo que a [ADR 0302](../decisions/0302-fonte-unica-doneness-anchor-aposenta-status-spec.md) APOSENTOU.**

Ele decide lacuna-vs-backlog lendo o `status:` do blockquote da US (`US_ENTREGUE = {done, doing, review}`). A 0302 é explícita: *"a âncora `**Implementado em:**` é a **única** resposta canônica pra 'essa US está pronta?'; o `status:` é **aposentado** como sinal de done-ness"*.

Descobri no `decisions-search` do fechamento — **depois** de o gerador já estar em main. Não corrigi: seria a 4ª iteração do dia nesse arquivo sem medir, e o padrão da sessão (abaixo) diz que é assim que eu erro.

**O que fazer:** trocar o predicado por `anchored_ok`, re-rodar `--write`, conferir se a fila muda. *Suspeito* que não mude no Produto (a `US-PROD-028` tem `status: done` **e** foi corretamente pega) — mas isso é hipótese, não medição.

## O que entrou em main — 8 PRs

| PR | O quê |
|---|---|
| [#4807](https://github.com/wagnerra23/oimpresso.com/pull/4807) | 8 asserts apontando pra `requisitos/Inventory/` inexistente — **vermelhos reais** no nightly, não latentes |
| [#4808](https://github.com/wagnerra23/oimpresso.com/pull/4808) | trio de `Show`+`Index` (13 UC) + fix do **500 latente** (`business_id` ambíguo no join de categorias) |
| [#4809](https://github.com/wagnerra23/oimpresso.com/pull/4809) | `SDD-TEMPLATE.md` + agent calibrado + `sdd-output-lint` (C2 desligado por 100% FP medido) |
| [#4811](https://github.com/wagnerra23/oimpresso.com/pull/4811) | lint **ligado** no CI + regra *"ligue a máquina"* no §Sempre fazer |
| [#4814](https://github.com/wagnerra23/oimpresso.com/pull/4814) | SDD entra no radar de frescor — era o único artefato do programa sem sentinela |
| [#4816](https://github.com/wagnerra23/oimpresso.com/pull/4816) | **cadeia de requisitos** `US→CU→UC→teste` derivada, com status e fila |
| [#4823](https://github.com/wagnerra23/oimpresso.com/pull/4823) | trio do `BulkEdit` (7/7 telas) + anti-gaming v1 |
| [#4826](https://github.com/wagnerra23/oimpresso.com/pull/4826) | **4 contratos Blade** + anti-gaming v2 + `continue-on-error` no meu step |

Fechados sem merge: **#4821** (duplicava o #4819 — o `dup-detector` pegou) e **#4825** (conflito pós-squash, substituído pelo #4826).

## Estado do módulo Produto

```
9 US · 14 CU · 7 telas · 11 casos.md · 54 UC · 54 com teste que os cita
fila de crescimento: CU-PROD-09 (barras/etiqueta) · CU-PROD-12 (correção de valor)
```

Os `casos.md` de fluxo **Blade** vivem em `memory/requisitos/Produto/_telas/` — casa criada nesta sessão por decisão do [W] (*"tem que ter tudo do blade"*), porque o `casos-coverage-guard` varre só `Pages/**`. **Não estendi o gate required**: o G-1 exige trio de toda página roteada, e apontá-lo pra `resources/views/**` faria ~600 Blades nascerem em violação (big-bang de legado, lápide 2026-07-12).

## Os 8 defeitos que os contratos provaram (com recibo de CI)

Nenhum corrigido — todos tocam valor/estoque ou isolamento.

| # | Defeito | Recibo |
|---|---|---|
| 1 | **Custo vaza na ficha e na lista** pra quem o Blade gateia com `@can` e o Delphi faz sumir (`AR-PROD-015`) | `UC-PSHOW-01` e `UC-PIDX-03` vermelhos |
| 2 | **`default_sell_price_inc_tax` NÃO EXISTE** — nem coluna nem accessor; 4 telas leem → `null` → **preço de venda 0**. Corrige a premissa do `CU-PROD-14` e do `Show.casos.md` | medido no schema |
| 3 | `App\Product` **sem global scope** (`addGlobalScope` = 0) | o SDD §3.1 afirmava que tinha |
| 4 | Lista **trunca em 200** sem paginação enquanto o KPI conta o catálogo inteiro | `UC-PIDX-01` vermelho |
| 5 | `ProductBom` grava sem validar tenant — **3ª instância** da família `UC-PTAB-04` | `UC-PBOM-02` vermelho |
| 6 | Quick-add: **0** `request->validate`, 34 campos crus via `only()` — **4ª instância** | `UC-PQCK-02` vermelho |
| 7 | **`success: 1` para operação que não ocorreu** — pendente em **3 telas ao mesmo tempo**, logo é política, não tela | — |
| 8 | Menu de ações: Blade **10** por linha, React **1**; 5 sem Non-Goal declarado | — |

**A correção mais barata (grade vs mercado, 2026-07-27):** os 3 models usam `$guarded = ['id']` — tudo mass-assignable. O mercado é explícito: *"mantenha o FK de tenant fora do `$fillable`"*. Isso mata a família dos itens 5/6 na raiz, e é **menor** que adicionar global scope — que, aliás, **não teria resolvido nenhum deles** (global scope filtra leitura; esses são de escrita). Grade completa das 6 dimensões no chat da sessão; média ≈ 4,2, com o anti-hardcode (9) como único ponto acima da barra — e essa claim é **busca negativa, não prova de ausência**.

## Máquinas ligadas nesta sessão

| Máquina | O que vigia | Força |
|---|---|---|
| `requisitos-status.mjs` | a cadeia `US→CU→UC→teste` por módulo + fila de crescimento | advisory |
| `sdd-output-lint.mjs` C1 | ref `arquivo:NNN` sem sha em `casos.md` **novo** (forward-only) | advisory |
| `doc-freshness-score` | agora inclui `SDD-*` no corpus (363 → 365 docs) | advisory |
| regra *"ligue a máquina"* | `proibicoes.md` §Sempre fazer — 6 passos com as ressalvas medidas | canon |

## O padrão que essa sessão expôs (vale mais que os PRs)

**Três mecanismos meus foram corrigidos por quem os usou, nunca por mim:**

1. `includes(id)` gamificável — o agent do BulkEdit testou, viu fechar, desfez e reportou.
2. Regex que só aceitava bullet (`- **Âncora:**`) quando o corpus usa blockquote — acusou 3 CU legítimos.
3. Step sem `continue-on-error` num job **required** — meu lint "advisory" bloqueou o merge do próprio PR que o criou.

O item 3 tem raiz herdada: copiei um comentário do `tasks-index-generate` que afirma *"este job NÃO é required"* — **é**, e o cabeçalho do mesmo arquivo já dizia isso 50 linhas acima. Corrigi a justificativa pra ninguém mais herdar.

> **A lição:** o que eu escrevo sozinho passa; o que é exercitado quebra.

Corolário medido no mesmo dia: tentei mecanizar a lição do assert acoplado (`C2` do lint) e deu **100% de falso-positivo** (6/6 legítimos, porque quando a prop é `Inertia::defer` a ausência da chave **é** o contrato). **Nem toda lição vira máquina** — essa virou lápide no §5.

## Pendências que são decisão [W]

1. Os 8 defeitos acima — todos `[V0]` ou Tier 0.
2. **`US-PROD-029..032` estão só no MCP, ausentes do git.** O `tasks-create` responde *"adicionada em SPEC.md"* e escreve na árvore do servidor (mesmo bug de venue da [ADR 0352](../decisions/0352-errata-0351-venue-distiller-citacao-taxonomia.md)). Enquanto não descerem, **não aparecem no `_BACKLOG-GENERATED.md`** (886 US indexadas) nem no painel — a resposta a *"vou ver tudo no backlog?"* é **não**.
3. `last_grade_at` dos scorecards de bucket: carimbar ou aposentar ([#4819](https://github.com/wagnerra23/oimpresso.com/pull/4819) atacou a raiz propondo deprecar o v4).
4. 3 `PROMPT_PARA_CODE_*` órfãos no `handoff-integrity` — citar na fila ativa ou mover abaixo da linha d'água. **Não toquei: são ações opostas e é conteúdo do fluxo de design.**

## Estado MCP no momento do fechamento

```
cycles-active   → nenhum cycle ATIVO em COPI
my-work         → 8 tasks em REVIEW (@wagner), incl. US-PROD-027 [V0] e US-PROD-025
decisions-search "sdd-from-source cadeia rastreabilidade requisitos status"
                → 0351 · 0144 · 0302 · 0275   ← foi aqui que a dívida nº1 apareceu
```

Session log: [`2026-07-26-sdd-from-source-loop-avaliacao.md`](../sessions/2026-07-26-sdd-from-source-loop-avaliacao.md) — custo medido: **~287k tokens · ~20 min por tela**.
