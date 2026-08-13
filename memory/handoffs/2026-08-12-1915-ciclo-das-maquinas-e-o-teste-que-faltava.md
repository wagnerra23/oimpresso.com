---
date: "2026-08-12"
time: "19:15 BRT"
slug: ciclo-das-maquinas-e-o-teste-que-faltava
tldr: "Continuação do handoff das 17:24. [W] pediu o ciclo completo de cada máquina tocada; a medição achou ZERO testes cobrindo o que eu mudei no #5680. Teste criado exercendo comportamento, registrado na lane, e a mordida provada no CT 100 pelo acaso do container estar no código velho."
prs: [5697, 5706]
decided_by: [W]
related_adrs: ["0062-separacao-runtime-hostinger-ct100", "0130-handoff-append-only-mcp-first"]
next_steps:
  - "US-GOV-061 segue aberta (module:specs numa máquina com vendor) — criada nesta sessão, não é regressão"
---

## Estado MCP no momento do fechamento

- `my-work` → **5** tasks, todas REVIEW (US-TR-309/310/305, US-PROD-027, US-INFRA-023) — nenhuma tocada aqui
- `brief-fetch` (hook SessionStart) → nenhum cycle ativo · 161 commits/24h · 0 incidentes
- Handoffs irmãos varridos em `origin/main`: 3 posteriores ao meu das 17:24 (`1755` glob inertia, `1810` fronteira módulos, `1822` deploy) — **zero** sobreposição de tema
- ⚠️ O guard `git-base-freshness` acusou o checkout **38 commits atrás**; este handoff foi escrito a partir de `origin/main` fresco (`0 0`)

## O que aconteceu

Continuação direta do [handoff das 17:24](2026-08-12-1724-build-por-modulo-morto-e-o-teto-do-gt-g5.md), que fechou com o #5680 mergeado. [W] então pediu duas coisas que mudaram o desfecho.

**Primeiro: "essas pendências foram movidas de lugar para requisitos, olhe o ciclo completo".** Eu tinha deixado as pendências como prosa no handoff — lugar errado por construção. O backlog canônico são as `US-*` dos `SPEC.md` (ADR 0070), de onde `tasks-index-generate.mjs` deriva o `_BACKLOG-GENERATED.md` (881 tarefas / 50 módulos). Virou **US-GOV-061**, com o dono saindo de medição: o purpose do `SCOPE.md` do Governance cobre *"drift de escopo/deploy/**índice**"*, e nenhum outro SPEC cita `module:specs`. A segunda pendência — a branch órfã — **não existia**: `gh api` → 404, o GitHub a apagara no merge. Eu a declarara a partir do erro do `--delete-branch` **local**, sem medir o servidor.

**Depois: "pode fazer o ciclo completo de cada máquina? se não tiver casos de uso e testes crie, e faço testes para ver se as máquinas cumprem o prometido".** Antes de inventar um ciclo, procurei o dono — e existe: a proposal [`ciclo-completo-responsabilidade-por-maquina`](../decisions/proposals/2026-08-04-ciclo-completo-responsabilidade-por-maquina.md) (2026-08-04), 8 etapas com força de cobrança medida. E a porta viva `maquinas-inventario --check` → **469 máquinas, 0 faltando, 0 ghost**.

Aí a lacuna apareceu, e era minha: `rg --hidden -l "scanAssets|has_mix|has_vite|## Assets" -g '*Test.php'` → **rc=1, ZERO**. Mudei exatamente isso no #5680 e nada guardava. Os 2 testes que existem sobre o gerador assertam Rotas/Controllers/Migrations/Permissões — nenhum toca Assets.

## Artefatos gerados

| PR | estado | conteúdo |
|---|---|---|
| #5697 | **MERGED** 18:16Z | handoff 17:24 + session log + lápide §5 + LC-08→88 + **US-GOV-061** + índice |
| #5706 | **MERGED** 19:09Z `e0ac1d51d54` | `ModuleBuildLayerAbsentTest` (5 casos) + registro na lane |

## A prova de mordida (o que [W] pediu)

O container do CT 100 está no código de **2026-07-23**, antes da minha mudança (`stubs vite`=1, `has_mix`=4). Isso deu o bite-test sem encenação:

| container | resultado |
|---|---|
| código **velho** | `3 failed, 2 passed` — 9 assertions |
| código **novo** (2 arquivos copiados) | `1 failed, 4 passed` — **15** assertions |
| **CI, branch real** | `PASS ModuleBuildLayerAbsentTest` · suíte do gate **18 → 23 passed (59 assertions)** |

As 3 falhas do velho caíram nos casos certos, e a da árvore **listou os 15 arquivos** — validação independente da contagem `12 webpack + 3 vite` que eu medira à mão no começo. O 1 que restava era a árvore do container (velha), coberta pelo CI. Assertions 9→15→59 provam **execução**, não skip (LC-13).

Container restaurado do backup e verificado: `git status` dos meus 3 paths = **0**.

## Persistência

- **git:** os 2 PRs acima, em `origin/main`
- **verificado em main:** teste presente · **2** registros na lane (step + `paths:`) · US-GOV-061 no SPEC · **zero** `Modules/<X>/{webpack.mix.js,vite.config.js,package.json}`
- **MCP:** nenhuma task tocada — o trabalho nasceu de revisão adversarial, não de US

## Lições catalogadas

**Duas decisões de desenho do teste que o mantêm honesto** — e que valem além dele: exercer o **consumidor real** (`config()` do container, markdown que `renderMarkdown(inspect('Cms'))` emite) em vez de grepar o fonte, que seria o presence-gate do LC-11; e **2 dos 5 casos como controle negativo**, sem os quais *"não contém Laravel Mix"* passaria trivialmente num markdown que tivesse perdido a seção inteira.

**Registro na lane não é detalhe.** O `ui-architecture-gate.yml` roda por lista explícita de arquivos. Sem registrar, o teste nasceria mudo e eu teria dito "criei o teste" sobre algo que nunca executa (§5 2026-08-02).

**LC-08, mais duas instâncias** (não incrementadas — o recibo desta sessão já foi contado em 87→88, e gastar o mesmo 2× é ruído): declarei pendência de branch a partir do erro local sem consultar o servidor; e reincidi no mangling MSYS de `git show origin/main:<path>` no Git Bash, que já tem lição própria — o `:` vira `;` e a saída some.

**O hook `php-syntax-after-write` pagou o próprio custo:** minha 1ª versão do docblock tinha um glob cujo `*/` fechava o comentário. Pegou no ato e citou o incidente de 2026-07-28. Nota deixada no topo do docblock.

## Pointers detalhados

- Session log da etapa anterior: [`2026-08-12-build-por-modulo-morto-e-o-guard-que-o-sed-contornou.md`](../sessions/2026-08-12-build-por-modulo-morto-e-o-guard-que-o-sed-contornou.md)
- Dono do ciclo: [`proposals/2026-08-04-ciclo-completo-responsabilidade-por-maquina.md`](../decisions/proposals/2026-08-04-ciclo-completo-responsabilidade-por-maquina.md)
- O teste: `tests/Feature/Architecture/ModuleBuildLayerAbsentTest.php`
