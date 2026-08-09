---
date: "2026-08-09"
time: "00:57 UTC"
slug: ledger-lc15-e-o-ac-sem-executor
tldr: "2ª fatia da sessão do primary: o que o handoff 22:30 não cobria porque veio depois. Fechado o ledger (LC-08 63 · LC-15 3) e corrigido o campo `Gate` do LC-15, que declarava cobertura de CLASSE tendo cobertura de INSTÂNCIA e por isso silenciava o alarme. E a busca por duplicata antes de criar task revelou a causa REAL do escape fantasma: o `/mwart-override` é AC aberto da US-MWART-001 e ficou SEM EXECUTOR quando a ADR 0271 deletou a Camada 3 que o leria."
prs: [5466, 5472]
decided_by: [W]
next_steps:
  - "Decisão [W] sobre o `/mwart-override` (agora com a causa correta): NÃO é bug do hook — é AC de US-MWART-001 que perdeu o executor quando a ADR 0271 onda 2 deletou o `mwart-gate.yml`. Duas saídas legítimas descritas no AC: implementar com bite-test no chokepoint, ou remover a promessa da mensagem"
  - "ADR 0190 → superseded pela 0235 (DS v4): citação morta nos comentários de `PageHeaderPrimary` e nos 3 PRs do fix. Dívida ampla e pré-existente — não consertar tocando tela a tela, acorda gates"
  - "3 recibos pendurados no ledger (LC-08 cita 05-15 · LC-08 cita 06-08 · LC-10 cita 07-02) — PROVADOS pré-existentes por controle contra `origin/main` puro; 2 vieram do #5464. Dívida alheia, não tocada"
---

# A 2ª fatia — o ledger, e o AC que perdeu o executor

Continuação do [handoff 22:30](2026-08-08-2230-primary-sem-estilo-13-telas-e-o-escape-fantasma.md),
que fechou os 3 PRs do fix. Este cobre o que veio **depois** dele e por isso não cabia lá
(append-only): o ledger e a evidência no SPEC.

## Por que existiu esta fatia

[W] pediu *"salvar tudo"* e eu respondi que estava tudo salvo. **Estava errado.** O handoff
registrou os erros **em prosa**; o **contador** que o hook `licoes-code-two-strikes` lê no
SessionStart não tinha sido tocado. A regra do próprio ledger é explícita — *"consertou um erro
dessa classe? o ledger é SEU"* — e sem o incremento a próxima sessão abriria sem saber que aquelas
classes reincidiram.

Lição de processo: **"o trabalho está em `main`" ≠ "o trabalho está salvo"**. Um handoff conta o
episódio; o ledger é o que muda o comportamento da próxima sessão.

## O que foi ao ledger ([#5466](https://github.com/wagnerra23/oimpresso.com/pull/5466))

Duas lápides no §5 + contadores: **LC-08 → 63** (medidor cujo walk pulava o que queria contar) e
**LC-15 → 3** (o escape anunciado).

**O achado que só apareceu ao mexer no ledger:** o campo `Gate` do LC-15 declarava cobertura pela
fixture `knowledge-drift-prosa` — e **por isso o hook não alarmava a classe**. Minha 3ª ocorrência
prova que ela reincidiu em **outro** mecanismo com o gate verde: nada varre a *população* de
mensagens que oferecem escape. Marcado `parcial (1 script de N)`, no molde do `parcial (4/9)` do
LC-08. Efeito medido: o hook **voltou a alarmar** `LC-15 (3x, sem gate)`.

> **Cobertura de INSTÂNCIA não é cobertura de CLASSE** — e um campo `Gate` otimista silencia
> exatamente o alarme que existe pra isso. Vale reler quando for preencher `Gate` de qualquer LC.

## O conflito que quase corrompeu o contador

O [#5464](https://github.com/wagnerra23/oimpresso.com/pull/5464) mergeou em paralelo e conflitou.
**Os dois lados diziam `Ocorrências: 62`** — mas por episódios diferentes (ele: *"gate
insatisfazível"*; eu: CSSOM). **Escolher qualquer lado perderia um incremento e deixaria o contador
mentindo** — o defeito exato que o ledger existe pra impedir. Resolvido para **63**, com os dois
episódios no texto, e minha lápide renumerada de `nº 62` → `nº 63` com nota do porquê.

No `proibicoes.md` (append-only) as **3 lápides** foram preservadas na ordem de chegada. Nenhum lado
descartado.

## A causa REAL do escape fantasma ([#5472](https://github.com/wagnerra23/oimpresso.com/pull/5472))

Ia criar task MCP pras 2 decisões pendentes. **A busca por duplicata mudou a ação** — e revelou o
que eu ainda não sabia:

1. **O `/mwart-override` já tem dono:** é acceptance criteria da **`US-MWART-001`** (owner: wagner,
   p0), **aberto** (`- [ ]`) desde que a US foi escrita. Criar US nova seria duplicar dono (LC-19).
2. **E a causa não é o hook:** a **Camada 3 (`mwart-gate.yml`)**, que leria o `/mwart-override` em
   PR, foi **DELETADA** pela [ADR 0271](../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md)
   onda 2 (era gate de teatro). **O AC ficou sem executor.**

Isso reenquadra a decisão de [W]: não é *"consertar um hook que mente"* — é *"o processo MWART
perdeu a perna que dava a exceção, e a mensagem da camada vizinha continuou prometendo o que
dependia dela"*. Registrei a evidência **no próprio AC**, sem marcá-lo como feito, com diff
puramente aditivo (`18+/0−`).

> Padrão reaproveitável: **remover uma camada exige reconciliar as mensagens das camadas vizinhas
> que a citam.** A ADR 0271 removeu o gate certo (era teatro) e deixou uma afordância órfã atrás.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` (@wagner) → **6 tasks**, todas `REVIEW` (US-TR-309/310/305, US-PG-008, US-PROD-027,
  US-INFRA-023). Caiu de **10 → 6** durante a sessão — outras sessões fecharam 4. Nenhuma é deste
  trabalho: o `/mwart-override` vive como **AC de US-MWART-001**, não como US própria

## Método que se pagou nesta fatia

- **Controle antes de culpar a si mesmo:** os "recibos pendurados" subiram de 1 → 3 após minha
  resolução de conflito e pareciam regressão minha. Rodei o hook contra um checkout **limpo de
  `origin/main`** — os 3 já estavam lá. Suspeitar de si é certo; **concluir sem controle é a
  própria LC-08** que eu acabara de catalogar 2×.
- **Controle também no falso alarme de CI:** o `governance script tests` falhou por
  `hooks-manifest-generate --check`, e meu PR não toca `.claude/`. Controle em `main` puro: passava.
  Causa real = o [#5467](https://github.com/wagnerra23/oimpresso.com/pull/5467) (flip de required
  40→41) deixou o `_HOOKS-INDEX.md` — **derivado** — stale, e o gate acusou o próximo PR que passou
  perto. **Gate certo, autor errado** — a mesma forma do `SUPERFICIE.md` que meus deletes acordaram.
  Consertado por outra sessão no [#5470](https://github.com/wagnerra23/oimpresso.com/pull/5470).
- **Hooks que morderam corretamente nesta fatia:** `block-memory-drift` (barrou Edit em canon a
  partir de branch de inspeção) e `memory-schema` (barrou este handoff **duas vezes** — `tldr` > 500
  na 1ª versão, slug com maiúsculas na 2ª).
