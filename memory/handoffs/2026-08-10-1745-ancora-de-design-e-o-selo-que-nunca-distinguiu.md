---
date: "2026-08-10"
time: "17:45"
slug: ancora-de-design-e-o-selo-que-nunca-distinguiu
tldr: "Cobrança do [W] ('porque não copiou do protótipo?') expôs raiz sistêmica: o criar-tela.mjs escrevia related_prototype n/a SEMPRE, e o gate required só valida âncora declarada — tela nascia sem ninguém perguntar se havia protótipo. 4 PRs: trava backend×fonte, gerador procura antes, card usa selos canônicos, e `claude` registrado no Mesh (selo saiu de 126 humanos para 8 agentes + 118 humanos). Dois resíduos [W]: 3 migrations órfãs do Forja e o F4."
prs: [5511, 5512, 5513, 5517]
us: [US-FORJA-006]
next_steps:
  - "Decidir F4: o protótipo tem 7 fases, o backend 6 — está como DIVERGENCIA_DECLARADA"
  - "Decidir as 3 migrations órfãs do Forja (ForjaServiceProvider sem loadMigrationsFrom)"
  - "Decidir US-FORJA-006: qual implementação de backlog sobrevive"
related_adrs:
  - 0299-figma-nao-e-fonte-de-design
  - 0282-protocolo-v2-colapso-ratificacao
  - 0081-identity-mesh-actors
  - 0327-anchor-content-required-emenda-0314
---

# Handoff — a âncora de design e o selo que nunca distinguiu nada

> Continuação do handoff das **13:45 de 09/08**. Aquele fechou as ondas 6b/7 da Forja; este cobre o
> que veio depois da cobrança do [W]: **"porque não copiou do protótipo?"**.

## A cobrança, e o que ela expôs

Construí o Quadro do Trabalho ancorado no **código** (`ForjaQuadroService`, `badges`) e **não abri a
fonte de design**. Não rodei `ancora.mjs`, não consultei o `DesignSync`. O protótipo
`prototipo-ui/cowork/forja-page.jsx` já desenhava `KanbanView`/`KanbanCard` com `RoleBadge`,
`TypeChip` e `FrescorPill` — tudo reimplementado pobre ao lado.

Pior: `ActorSeal` e `PriorityDot` **já existiam prontos** em código, e **o meu próprio charter citava
o `ActorSeal`** na tabela comparativa. Escrevi sobre o componente e hand-rolei ao lado.

## A raiz sistêmica (não era disciplina minha)

O gerador canônico `criar-tela.mjs` escrevia, **sempre**:

```yaml
related_prototype: n/a (herda PT-0X)
```

E o `anchor-content-check` (**required**) só valida âncora **declarada** — sem âncora, nada a
validar, gate verde. **Tela nova nascia sem ninguém perguntar "existe protótipo pra esta família?"**.

Medido em 209 charters: **54 sem o campo** (nasceram fora do gerador — 2 deles meus) e ~42 com `n/a`
explícito. A diferença importa: `n/a` é **decisão**; campo ausente é **ninguém perguntou**.

## Os 4 PRs

| PR | entrega | prova |
|---|---|---|
| [#5511](https://github.com/wagnerra23/oimpresso.com/pull/5511) | trava backend × **fonte de design** (`UC-PIPE-01..04`) | lane 46→50 passed, 179→196 assertions |
| [#5512](https://github.com/wagnerra23/oimpresso.com/pull/5512) | gerador **procura** antes de escrever `n/a` | mutação derruba os 2 BITE (`SELFTEST FALHOU (2)`) |
| [#5513](https://github.com/wagnerra23/oimpresso.com/pull/5513) | card usa selos canônicos (`TaskBadges`→`shared/`) | mutação derruba `design-spec --check` |
| [#5517](https://github.com/wagnerra23/oimpresso.com/pull/5517) | `claude` no Identity Mesh | smoke prod: `{human:126}` → `{agent:8, human:118}` |

Lane Forja no main: **51 passed · 200 assertions** (a sessão começou em 42/166).

## O elo que ninguém travava

```
protótipo (fonte)  ←— NINGUÉM TRAVAVA —→  backend  ←— UC-TRAB-07 —→  front
```

O `UC-TRAB-07` que eu tinha escrito liga front↔backend — mas trava **o espelho contra o espelho**.
Se o backend divergir da fonte, os dois concordam entre si e ficam verdes enquanto a tela contradiz
o protótipo.

Achado ao ligar o `UC-PIPE-03`: **o protótipo tem 7 fases, o backend 6** — falta `F4 Merge (owner
W2)`. E meu charter afirmava *"F4 NÃO é coluna"* sem consultar a fonte. **Não escolhi lado**: entrou
como `DIVERGENCIA_DECLARADA` com data e dono. Os dois caminhos possíveis esvaziam a lista.

## O selo nunca distinguiu nada — e não era regressão desta onda

O smoke achou o que o teste não pega: 126 `ActorSeal` renderizados, **todos "humano"**.

| | valor |
|---|---|
| `agents` servidos | `["claude-code-wagner-laptop"]` (slug de **token**) |
| `owner` das tasks | `wagner` · `[w]` · `claude` · `eliana` · `felipe` · `luiz` · `maiara` · `maira` · `[f]` |

Nenhum casava. Provado que era **pré-existente**: `/team-mcp/tasks`, mesma lógica desde antes,
mostrava **383 selos, 100% `human`**.

Consertado por **dado, não heurística** — registrar `claude` no Mesh ([ADR 0081](../decisions/0081-identity-mesh-actors.md)), sem token e com
zero capability. `startsWith('claude')` está proibido como Non-Goal no charter: heurística de nome
erra e faz o selo mentir com confiança.

## Armadilhas que custaram tempo (não repita)

1. **As migrations do `Modules/Forja/` NÃO RODAM.** O `ForjaServiceProvider` não tem
   `loadMigrationsFrom` (outros módulos têm). Nada ali executa no `migrate --force` — nem no CI, nem
   no deploy (`deploy.yml:312`). Descobri porque o `UC-TRAB-12` reprovou; se tivesse mergeado sem
   investigar, o PR anunciaria "1 linha nova" e **nada aconteceria**.
2. **`module-surface --all --check`, nunca só o módulo tocado.** PR que **move** arquivo entre
   buckets (ex.: `_components/` → `shared/`) drifta o `_Geral`, não o módulo de origem.
3. **`$fillable` não é lista de obrigatórios.** Escrevi fixture de `mcp_actors` pelo `$fillable` e
   levei `Check constraint violated` — a tabela exige 5 JSON + `display_name` NOT NULL, e coluna
   JSON no MySQL 8 carrega `json_valid` implícito. São perguntas diferentes.
4. **Mutação só prova se você conferir que ela foi aplicada.** Minha primeira mutação não casou (o
   `replace` usava 4 espaços; o código tem 2) e eu **quase publiquei "a mutação não matou o teste"**
   — conclusão falsa de instrumento cego, dentro do PR que combate essa classe.
5. **O defer chega depois.** Medir o DOM logo após `navigate` dá 0 cards; recarregar e esperar ~9s
   dá os 500. Duas vezes quase reportei tela vazia.

## Estado no fechamento

- `main` em `7d587b623dc`+; os 4 PRs mergeados, deploy verde, smoke real feito com screenshot.
- ⚠️ **Sem snapshot MCP**: o servidor não respondeu no SessionStart (timeout) e as tools
  `cycles-active`/`my-work` ficaram indisponíveis. Registro a ausência em vez de omitir — o
  protocolo pede o snapshot, e ele não existiu nesta sessão.
- Sessões paralelas ativas no mesmo período (Jana, Sells, scope-guard) — vários PRs entre #5497 e
  #5528 **não são desta sessão**.

## Próxima ação verificável

Três decisões [W], nenhuma delas conserto de agente:

1. **F4** — 7 fases no protótipo × 6 no backend.
2. **As 3 migrations órfãs do Forja** (`create_mcp_ingest_heartbeat_table`,
   `create_cowork_handoffs_table`, e a que movi). Ligar o `loadMigrationsFrom` dispara **as três de
   uma vez**, incluindo dois `create table` de junho cujo estado em prod não medi.
3. **`US-FORJA-006`** — qual implementação de backlog sobrevive. As quatro telas seguem no ar de
   propósito, pra a comparação ser olhando.
