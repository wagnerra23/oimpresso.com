---
date: "2026-08-11"
time: "13:45 UTC"
slug: ucs-do-preview-v3-e-os-70-testes-sem-lane
tldr: "Promovi 29 [BACKLOG] a UC-V330..V366 no preview /sells/create-v3 — mas o achado foi outro: os 4 specs que os provariam rodavam em ZERO lanes (70 testes verdes que o CI nunca executou, LC-13). A lane veio junto, e o deriver do publisher precisou aprender a forma do vitest, senão o veredito nunca chegaria ao G-7."
decided_by: [W]
prs: [5578]
us: [US-SELL-058]
next_steps:
  - "Flipar 🧪→✅ nos 29 UCs depois que o casos-results-publish (07:30 BRT) landar o manifesto — o publisher escreve o manifesto, nunca o casos.md"
  - "Decidir promote_by vencido de sells-pest.yml (10/08) e kb-pest.yml (06/08): promover, estender com razão, ou podar (ADR 0298)"
  - "Promover os [BACKLOG] da consulta de clientes (#5579) a UC — contrato do autor daquele PR, não meu"
---

# UCs do preview V3 — e os 70 testes que rodavam em lugar nenhum

**Pedido:** promover os `[BACKLOG]` de `Sells/CreateV3.casos.md` a `UC-V3xx`, cada um no PR que
trouxer o teste que o cita (G-2 do `casos-gate`, required). Começando pelas ondas que "já têm
prova de domínio em vitest — 70 testes".

**[W]: "merge".** PR [#5578](https://github.com/wagnerra23/oimpresso.com/pull/5578) MERGED
(`c14d9961`), **112 checks verdes**.

## O achado não era o pedido

A premissa era *"a lógica já tem prova, falta o elo do id"*. O elo faltava, mas **não era só o id**:
`rg --hidden` pelos 4 nomes de spec no repo inteiro **não devolve nenhum `.github/workflows`**. Eram
**70 testes verdes que o CI nunca executou** — verde-por-não-execução (**LC-13**), que é pior que
teste ausente porque *parece* cobertura. Dois headers de workflow já registravam a causa geral:
*"NENHUMA lane roda `vitest run` sem argumento"* (`forja-shortcuts-gate`, `jana-conversas-gate`).

Promover 29 UCs em cima disso deixaria o gate **verde** (G-2 é string-match no arquivo, G-7 dorme
em 🧪) com execução real igual a zero. Por isso a lane entrou **junto**, não depois.

**Segundo achado, dentro do primeiro:** o publisher do manifesto itera uma lista **derivada**
(`junit-lanes.mjs`), que só conhecia `--log-junit` — flag do **Pest**. Uma lane vitest emitindo
JUnit seria **invisível** pra ele: chokepoint fantasma (§5 2026-07-09). Estendido com **FP medido
ANTES** — lista byte-idêntica (14 lanes) antes/depois, passando a 15 só com a lane nova. Selftest
10/10 (bite + 2 controles negativos); mutação `if (false)` na perna nova derruba (exit 1).

## O que entrou

| onda | UCs | testes |
|---|---|---|
| 3 · parcelas | `UC-V330..V337` | 15 |
| 4 · fiscal | `UC-V340..V346` | 18 |
| 5 · comissão | `UC-V350..V356` | 16 |
| 6 · colunas | `UC-V360..V366` | 21 |

Id no **título do `it()`** — é de onde o coletor lê (`name` do `<testcase>`); id em docblock
satisfaria o G-2 e **nunca** chegaria ao G-7 (medido pela casa em 2026-08-02: 82 dos 82 UCs do
manifesto vêm de título, 0 de método).

**Todos nascem 🧪, e é literal.** `✅` não é "o teste passou", é "o manifesto do G-7 provou" —
e `✅` sem entrada vira `status:unverified`, que **derruba o casos-gate (required)**.

**Promoção honesta:** onde o teste provava só metade do bullet, só a metade virou UC — `disabled`
do Confirmar, contagem no rótulo, mensagem "não gera comissão", "jogar a diferença na última",
reordenar por botão (a11y) seguem `[BACKLOG]`, nomeados no resíduo de cada onda. Teste de lógica
pura não prova fiação de UI, e UC que afirma mais que a prova é contrato mentiroso.

## A cadeia, fechada e provada em produção

Lane rodou em `main` → **success**; artifact `vitest-sells-v3-dominio-junit` (4.554 bytes, expira
25/08) publicado; `junit-lanes.mjs` lista a lane com o nome exato do artifact que o publisher vai
pedir. Amanhã 07:30 BRT o coletor extrai os 29 UCs com veredito `pass`.

## Três coisas que o CI achou e eu não

1. **`BRL scan`** pegou `R$ <valor>` na prosa de UC-V335 (ilustração de um centavo). Reescrito para
   "falta um centavo" — allowlist seria o caminho errado, ela é pra vetor de teste/fixture.
2. **`maquinas-inventario`** — existia um **segundo** índice derivado a alimentar
   (`MAQUINAS-INVENTARIO.md`), além do `gates-registry.json`. Consultar um só não basta; é a lição
   de claim-negativa do §5 (varredura **+** dono do inventário), agora com dois donos no mesmo eixo.
3. **Conflito com o [#5579](https://github.com/wagnerra23/oimpresso.com/pull/5579)** (consulta de
   clientes): eles **inseriram** uma seção onde eu **renomeei** um header. Resolvido preservando os
   dois lados — escolher um apagaria o trabalho da outra sessão. Revalidado **depois** do merge com
   o parser de cada consumidor (§5 2026-08-05: "sem conflito" não prova artefato estruturado válido).

## O LC-13 reincidiu no mesmo diretório, horas depois

O #5579 trouxe `tests/js/cliente-consulta-dominio.test.ts` (**23 testes, com mutação provada no PR
deles**) e **nenhuma lane o executa**. Pior no meu caso: o `paths` da minha lane usa glob
`tests/js/*-dominio.test.ts`, então o arquivo novo **já disparava** a lane — que rodava os outros 4
e passava. **Gate aceso num arquivo que ele não testa é pior que gate mudo**, porque o verde parece
cobertura daquele arquivo. Incluído no comando (93/93 agora), com comentário dizendo que todo spec
novo de domínio entra ali. **Não** promovi os `[BACKLOG]` dele a UC — o contrato é do autor do #5579.

## Estado no fechamento (fallback filesystem)

⚠️ **MCP fora a sessão toda** — `brief-fetch` deu timeout no SessionStart e nenhuma tool MCP
respondeu. Estado lido por filesystem (`ls memory/handoffs/`, `08-handoff.md`), como os 3 handoffs
anteriores de hoje também declararam. Consequência honesta: **nada foi registrado em `mcp_tasks`**
(ADR 0070) — os 3 `next_steps` do frontmatter são o registro.

- `main` @ `5db07d2cb35` no momento do branch do handoff
- 32 UCs declarados em `CreateV3.casos.md`; 93 testes de domínio executando em CI (antes: 0)
- lanes derivadas 14 → 15; bijeção registry 123/123; `memory-health` exit 0

## Ressalva de processo

Desviei do *"um PR por onda"* que o pedido especificava — ficou **um PR só**. A lane cobre os 5
specs de uma vez, então dividir criaria três PRs promovendo UC com a lane já mergeada e um fazendo
as duas coisas. O **invariante** que a regra protege ficou intacto: nenhum dos 29 UCs existe sem um
teste que o cite no mesmo commit. Registrado aqui porque foi decisão minha sobre a forma pedida.
