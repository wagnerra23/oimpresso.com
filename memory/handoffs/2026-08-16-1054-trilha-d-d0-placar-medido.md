---
date: "2026-08-16"
time: "10:54 BRT"
slug: "trilha-d-d0-placar-medido"
tldr: "[W] perguntou se a Trilha D estava concluída e autorizou o ciclo completo. Não fecha: a D0 tem 2 de 5 AC fechados e a 1ª das 3 partes do gate dela é a credencial MCP, ausente desde 05/08. O que era mecanizável foi pago — matriz de 1 para 4 eixos derivados, com 2 eixos RECUSADOS por medição — e o placar passou a declarar o resíduo em vez de um 'em execução' mudo. PR #5833 mergeado."
prs: [5833]
decided_by: [W]
related_adrs:
  - "0294-plano-status-vivo"
  - "0273-ancora-implementado-em"
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
next_steps:
  - "[W] colar a credencial MCP em .claude/settings.local.json — destrava a 1ª das 3 partes do gate da D0 (plano ligado ao MCP) e a materialização da task com parent_plan=programa-ondas"
  - "[W] decidir a onda seguinte — a ordem declarada no §D.3 põe D1 (infraestrutura crítica) como próxima"
  - "Smoke autenticado em /documentacao/programa pós-deploy: os cartões devem seguir mostrando D0 e o link pra US-INFRA-048"
---

# Handoff 2026-08-16 10:54 BRT — a D0 medida, e a célula de status que era máquina

## Estado MCP no momento do fechamento

**Não obtido — MCP indisponível.** O hook de SessionStart reportou `FALLBACK ATIVADO — settings.local.json
não encontrado e sem cofre local pra restaurar`, e nenhuma tool `mcp__oimpresso__*` esteve acessível
durante a sessão inteira. É a **3ª sessão seguida** nesse estado (as duas anteriores registram o mesmo).

Registro a ausência em vez de omitir: `cycles-active`, `my-work`, `sessions-recent` e `decisions-search`
**não foram consultados porque não havia canal** — o que é diferente de terem sido pulados. Fallback
filesystem conforme [how-trabalhar §Fallback](../how-trabalhar.md): `Glob memory/handoffs/`, leitura
direta de SPEC/plano, e `git show origin/main:<path>` pra validar contra canon.

Consequência direta pro trabalho: o AC3 da D0 pede task na fila com `parent_plan=programa-ondas`, e
**a ausência dela não foi afirmada** — foi declarada não-auditável deste ambiente, porque a fila vive
em `mcp_tasks`, fora do git.

## O que aconteceu

A pergunta era se a Trilha D estava concluída. Medido: **11 ondas (D0–D10), só a D0 tocada**. O plano
está formalmente saudável (`status: ativo`, `plan-health` não o acusa, kill-condition a semanas de
distância, e as 11 máquinas que ele reusa existem todas), então o problema não era plano zumbi — era
placar mudo.

[W] autorizou o ciclo completo. Rodou sobre **uma** unidade, como o §D.4 manda, com 4 threads em
áreas disjuntas (3 read-only de medição + 1 de escrita), parent consolidando e fazendo todo o git.

**O resultado honesto é que a D0 não fecha.** Dos 5 acceptance criteria: 2 fechados, 3 parciais. E o
gate da onda tem 3 partes, das quais a primeira — *"plano ligado ao MCP"* — está travada pela mesma
credencial ausente desde 05/08. Não é mecanizável por agente.

## Artefatos gerados

| PR | Entrega | Onde |
|---|---|---|
| #5833 | placar da Trilha D declara 2/5 AC + resíduo por natureza (mecanizável × bloqueado × histórico) | `PLANO-MESTRE.md` · `Infra/SPEC.md` |
| #5833 | matriz da D0: **1 eixo em 32,7% → 4 eixos derivados** | `maquinas-inventario.mjs` (330→609 ln) · `MAQUINAS-INVENTARIO.md` (regenerado, 587 ln) |
| #5833 | caso que trava o contrato da célula de status pelo consumidor real | `tests/Feature/DocumentacaoRouteTest.php` (+47 ln) |
| #5833 | `US-GOV-059` status-truth reconciliado (corpo provava a triagem; cabeçalho dizia `todo`) | `Governance/SPEC.md` |

CI: **119 checks — 117 success, 2 skipped, zero falhas.** Merge squash em `dd41a047`.

## As duas recusas, que são o miolo da entrega

A thread da matriz mediu **antes** de codar, e dois eixos morreram na medição:

- **`owner`** — `.github/CODEOWNERS` cobre 123/474 (25,9%) e as 20 regras resolvem **todas para o
  mesmo handle**: valor único em 26% das linhas, vazio em 74%. Os dois critérios de ruído ao mesmo
  tempo. A alternativa (matriz §3 do `TEAM.md`) também não serve — é por tipo de task/módulo, sem
  aresta mecânica até um arquivo. Condição de reabertura gravada no gerador: **CODEOWNERS cobrir
  `.claude/**` + `scripts/**` com handles distintos**.
- **`evidência` em workflows** — 0 de 123. Havia um proxy tentador (57 rodam script com bite-test) e
  ele foi recusado com a razão certa: *prova que o **script** morde, não o **workflow***.

Limite declarado, não escondido: a coluna `Documento` afirma **"citador de maior precedência"**, não
"doc dono" — citação não prova posse, e por isso a célula carrega `+N`.

## Lições catalogadas

1. **A célula de status do plano é entrada de máquina, não prosa.** `DocumentacaoController::execucaoDaTrilha()`
   extrai dali a onda (`/\bD(\d+)\b\s+em execução/u`) e a US. Reescrever a linha faz os cartões de
   `/documentacao/programa` sumirem **em silêncio** — o controller devolve `null` de propósito e a
   página segue 200. Mexer ali sem rodar o consumidor é LC-22 literal.
2. **Trocar `_pendente_` por âncora real acorda o gate de entrada.** A US passa a se declarar
   implementada e o `anchor-lint` cobra `@covers-us`. Voltar pra `_pendente_` seria falso ao
   contrário; a saída certa foi o teste.
3. **`--check-covers` não varre `tests/`** — lê só os testes citados em `**Testado em:**` da própria
   US. Presumi varredura global e fiquei 2 rodadas no vermelho.
4. **Relatório de agente que contradiz a sua medição é hipótese a testar, não erro dele.** A thread
   da visão humana provou, com a linha colada, que a regex do meu briefing era fóssil do handoff
   anterior.
5. **`EXIT=0` depois de pipe é do último comando** — um `php … | tail` devolveu 0 com fatal error.
6. **Vermelho de CI se prova de quem é.** Os 2 ratchets ARMADOS foram medidos também em worktree
   limpo de `origin/main`: 2 dos dois lados ⇒ ambiente (`nightly-floor.json` ausente no checkout),
   não o diff.

## Pointers detalhados

- Session log desta sessão: [2026-08-16-trilha-d-d0-placar-medido-e-a-celula-que-e-maquina.md](../sessions/2026-08-16-trilha-d-d0-placar-medido-e-a-celula-que-e-maquina.md)
- Resíduo dos 3 AC parciais, com a natureza de cada um: `memory/requisitos/Infra/SPEC.md` §US-INFRA-048
- Ondas D0–D10 e os gates de saída: `memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md` §D.3
