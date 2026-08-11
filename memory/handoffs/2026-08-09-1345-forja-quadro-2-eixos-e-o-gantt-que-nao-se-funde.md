---
date: "2026-08-09"
time: "13:45"
slug: forja-quadro-2-eixos-e-o-gantt-que-nao-se-funde
tldr: "Ondas 6b e 7 em produção — Quadro com 2 eixos (Pipeline × Execução) e o Gantt como 3ª vista, mas como ATALHO com filtros, não fusão: 4 colisões medidas provaram que portá-lo é reescrevê-lo, incluindo um hotfix de prod que proíbe defer lá. Lane Forja 42→46 passed, 166→179 assertions. Smoke visual PENDENTE — a sessão do browser expirou e não digito credenciais."
prs: [5492, 5493]
us: [US-FORJA-006]
next_steps:
  - "Smoke visual de /forja/trabalho com [W] logado — R1 exige screenshot; sem ele nada aqui está declarado pronto"
  - "Decidir US-FORJA-006: qual das implementações de backlog sobrevive (recomendação medida: as nativas)"
  - "301 das rotas antigas + remoção da perdedora — travado na decisão acima"
related_adrs:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0253-primitivos-layout
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
---

# Handoff — Forja: o Quadro de 2 eixos e o Gantt que não se funde

> Continuação direta do handoff das **03:45**. Aquele fechou as ondas 0/1/3/6a; este fecha
> **6b e 7**, que eram o que restava do pedido de 2026-08-08 fora da decisão [W].

## O que foi entregue

| PR | Entrega | CI |
|---|---|---|
| [#5492](https://github.com/wagnerra23/oimpresso.com/pull/5492) | **Quadro unificado** — 2 eixos sobre a mesma lista | ✅ |
| [#5493](https://github.com/wagnerra23/oimpresso.com/pull/5493) | **Gantt como 3ª vista** — atalho com filtros | ✅ |

**Lane Forja no main (run 31314148083): `46 passed · 179 assertions`** — era `42 · 166`. Os 4 UC
novos (`UC-TRAB-07..10`) aparecem no log com duração própria; não é skip.

## As duas decisões que valem mais que o código

### 1. O eixo Pipeline **filtra** em vez de inventar coluna "sem fase"

Task de infra/gate/ADR **não tem** `forja_fase`, e isso é **correto** — não é dado faltando.
Forçá-la numa coluna mentiria sobre ela. Em troca, o board mostra **"N fora"** com o motivo: sem
isso, quem conta os cards e compara com o KPI conclui que o sistema perdeu task.

### 2. O Gantt é **atalho**, não fusão — e as 4 razões são medidas

O pedido chama o Gantt de "3ª sub-visão". Do ponto de vista de quem procura trabalho, ele é. Mas
**continua morando** em `/forja/roadmap-gantt`, e o botão diz isso (seta de saída + `title`, sem
`aria-pressed`). Portar as 681 linhas colidiria em quatro pontos:

| # | colisão | onde está a evidência |
|---|---|---|
| 1 | **Payloads opostos** — Trabalho é defer-first; o Gantt é **eager por HOTFIX DE PRODUÇÃO** (com `defer`, dropdown chegava `undefined` e o `.map()` estourava em prod) | `RoadmapGanttController:139`, comentário `⛔ DESENHO CONSCIENTE`, [#1552](https://github.com/wagnerra23/oimpresso.com/pull/1552) |
| 2 | **A prop `tasks` colide** — shapes diferentes (~20 campos que a lista não tem) | os dois `Inertia::render` |
| 3 | **Mutação própria** — `PATCH /roadmap-gantt/tasks/{id}/schedule` | `routes.php:384` |
| 4 | **Trio próprio** — charter · casos · `RUNBOOK-gantt.md` | `Pages/Forja/Roadmap/` |

**Fundir de verdade é reescrever a tela, não movê-la.** Está no charter como Non-Goal com as 4
razões, pra a próxima sessão não redescobrir do zero.

## Armadilhas que custaram tempo (não repita)

1. **`toContain` é VARIÁDICO no Pest.** Passei a mensagem como 2º argumento e ele foi procurar *a
   frase* no haystack — o assert falha **sempre**, e o erro sai apontando pro lado do código
   (`Expected: <?php\n`), não pro teste. É reincidência de classe já lapidada (§5, 2026-07-28), e
   o gate que a pegaria **foi medido e reprovado** (100% FP — `toContain` é o assert certo quando
   o contrato *é* a presença). Sem máquina, a defesa é lembrar. Fix:
   `expect(str_contains($h,$n))->toBeTrue('msg')`.
2. **Contei 5 filtros compartilhados; eram 4.** O grep pegou `status` porque o Gantt o serializa na
   **saída** — ele não o aceita como filtro de **entrada**. Mandá-lo no link seria parâmetro
   ignorado em silêncio. Confira sempre no `$request->get(...)`, não no `map` de saída.
3. **Espelho de constante entre PHP e TSX cobra trava.** Na 6b as fases precisaram do `UC-TRAB-07`
   pra não divergir. Na 7 dava pra **evitar na origem**: o controller entrega a lista como prop
   (`filtrosGantt`), e não há 2ª declaração pra divergir. Prefira esse caminho.
4. **Derivados nunca à mão** — o `SUPERFICIE.md` reprovou porque o `TrabalhoQuadro.tsx` entrou na
   contagem (2→3 componentes). `module-surface.mjs Forja --write`.
5. **O `casos-gate` roda DOIS modos** e eu rodei um em ondas passadas: sem-arg **e**
   `--check-baseline-shrink <baseline do main>`. O baseline mora em
   `scripts/casos-coverage-baseline.json` — derive o path do próprio script, não de memória.
6. **Deploy falhou de novo com `ssh: Connection timed out`** no pré-check (3ª vez em 2 dias).
   Não é código. Conserto: `gh workflow run deploy.yml --ref main`.

## O que NÃO está provado (e por quê)

**O smoke visual não foi feito.** A sessão do browser expirou e caiu no `/login`; digitar
credenciais é proibido. Sem screenshot, **nada aqui está declarado "funcionando"** (R1).

O que **foi** possível provar sem sessão, e prova coisas diferentes:

- **O artefato chegou em produção** — o manifest do Vite serve `Index-DLtRzZZb.js` com
  `trabalho-visao-gantt` + `/forja/roadmap-gantt`, e `TrabalhoQuadro-6ebHC4AU.js` com
  `quadro-card` + `quadro-recorte`. Cada marcador no chunk certo, sem vazamento entre eles.
- **Os controllers bootam** — `/forja/{trabalho,roadmap-gantt,aprovacoes,handoffs}` respondem
  redirect de auth (não 500), com **controle negativo**: `/forja/rota-inexistente-xyz` → **404**.
  Ou seja, o redirect não é indiscriminado.

Isso descarta erro de servidor e prova entrega. **Não** prova render — que é o que a R1 pede.

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ativo** em COPI.
- `my-work`: 8 tasks em `review` (US-TR-309/310/311, US-PROD-027, US-INFRA-023/048, US-TR-305/306)
  — nenhuma tocada por esta sessão.
- `main` em `f41a24a834e`; deploy re-disparado com sucesso (run 31314935622).
- Nenhum PR meu aberto pendente.

## Próxima ação verificável

1. **[W] logado → abrir `/forja/trabalho`**, girar Lista → Quadro → eixo Pipeline → Gantt, e
   conferir que o "N fora" bate com o KPI. É o passo que falta pra declarar pronto.
2. Resolver a **`US-FORJA-006`** — as quatro telas seguem no ar de propósito, pra a comparação ser
   olhando e não lendo diff.
