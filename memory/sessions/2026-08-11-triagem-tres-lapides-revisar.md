---
date: "2026-08-11"
topic: "Triagem das 3 lápides §5 marcadas `revisar` pelo lapide-recheck — 3 premissas intactas, zero emenda"
authors: [C]
outcomes:
  - "3 lápides triadas com evidência (PR de deleção/rename de cada âncora)"
  - "Nenhuma cai na classe 3 (premissa expirada) → zero emenda ao §5"
  - "As 3 defesas mecânicas reivindicadas seguem existindo e mordendo (provado por execução)"
  - "Zero edição de lápide existente (§5 append-only Tier 0)"
---

# Triagem das 3 lápides `revisar` — 2026-08-11

Origem: rodada da grade `memoria-conhecimento` de 2026-08-11. Reproduzido com
`node scripts/governance/lapide-recheck.mjs` — **104 lápides · 76 âncoras intactas ·
16 sem âncora · 9 citação não resolvida · 3 REVISAR**. Clone completo
(`--is-shallow-repository=false`), branch `0 0` vs `origin/main` — as datas de `git log`
sustentam conclusão.

O `revisar` é **proxy, não veredito**: o cabeçalho do `lapide-recheck.mjs` declara que a
detecção é mecânica (`existsSync`) e o julgamento — *a premissa ainda vale?* — é humano.
Âncora sumida ≠ lápide inválida.

## Veredito

| Lápide | Âncora sumida | Classe | Veredito |
|---|---|---|---|
| 2026-08-10 (tela derivada do código) | `Tasks/_components/taskBadges.tsx` | **2** — apenas renomeada | premissa vale; o rename É o conserto que a lápide prescreve |
| 2026-07-30 (escape anunciado não implementado) | `MemoryReader.php` + 3 docs MemCofre | **1** — premissa intacta | as âncoras estavam no parágrafo de *pendência*, não na premissa; pendência fechada ~15h depois |
| 2026-07-22 (blindar candidato por julgamento) | `memory/03-architecture.md` | **1** — premissa intacta | o arquivo era o *exemplo* do falso-positivo; foi apagado como fóssil, desfecho coerente |

**Nenhuma na classe 3 → nenhuma emenda proposta.**

## 1. 2026-08-10 — âncora apenas RENOMEADA (classe 2)

`git show f2867a5ee76 --stat -M` (PR [#5513](https://github.com/wagnerra23/oimpresso.com/pull/5513),
2026-08-10) mostra o rename:

```
.../team-mcp/Tasks/_components/taskBadges.tsx  →  resources/js/Components/shared/TaskBadges.tsx
.../team-mcp/Tasks/_components/taskTokens.ts   →  resources/js/Lib/taskTokens.ts
```

O arquivo existe hoje em `resources/js/Components/shared/TaskBadges.tsx`.

**A âncora sumiu porque a lição foi APLICADA.** A lápide registra que `ActorSeal` e
`PriorityDot` *"já existiam prontos em código (`Tasks/_components/taskBadges.tsx`)"* e que o
agente hand-rolou ao lado. O #5513 é o conserto: o Quadro passou a consumir os selos
canônicos, e o componente foi **promovido** de `_components` de uma tela para
`Components/shared`. O ponteiro envelheceu como consequência do próprio desfecho.

Defesa mecânica reivindicada (`criar-tela.mjs` procura protótipo antes de escrever) **não
depende do path**: `--selftest` OK, com os 2 BITE (`módulo COM protótipo sem
--prototipo/--sem-prototipo → exit 2`; `a recusa NOMEIA o candidato`) e os 3 controles
negativos.

## 2. 2026-07-30 — pendência declarada, fechada no MESMO dia (classe 1)

As 4 âncoras vivem num parágrafo rotulado **"Pendência que esta lápide NÃO fecha"** — não na
premissa. A premissa é sobre o `knowledge-drift --check` anunciar um escape
`"(planejado — não existe)"` que o código nunca implementou; nada nela depende do MemCofre.

- `Modules/SRS/Services/MemoryReader.php` — deletado em
  [#5036](https://github.com/wagnerra23/oimpresso.com/pull/5036) (2026-07-29). **A própria
  lápide já o descreve como "código deletado"** — ele estava morto quando ela foi escrita; o
  `existsSync` só não tinha como saber disso.
- `MemCofre/{GLOSSARY,ARCHITECTURE,BRIEFING}.md` — purgados em
  [#5092](https://github.com/wagnerra23/oimpresso.com/pull/5092), `692ce12db45`,
  **2026-07-30 16:13:34**. A lápide foi mergeada em `a1b86822382`, **2026-07-30 00:46:46** —
  ou seja, a pendência que ela declarou aberta foi fechada **~15h30 depois, no mesmo dia**.

O desfecho foi **deleção**, não a forma que a lápide prescrevia (*"presente falso → fato
datado em passado"*). Resolve o problema — não há mais afirmação em presente sobre código
inexistente — por outro caminho. `SRS/*` segue preservado como canon histórico, conforme a
decisão de §5 2026-07-29.

Defesa mecânica intacta e **mordendo**, provada por execução da fixture do jeito que o CI a
executa (sandbox por **cwd**, não argumento — ver nota de método abaixo):

```
good → rc=0  "OK — nenhum ghost novo fora do baseline."
bad  → rc=1  "⛔ NUNCA adicione ao baseline…"
```

Fixture `knowledge-drift-prosa` em `tests/governance-fixtures/`, registrada no
`gate-selftest.mjs:482` — job **required** `gate selftest (as catracas mordem · GT-G6)`.

⚠️ **Resíduo de ponteiro, declarado e NÃO consertado:** uma sessão futura que ler aquele
parágrafo vai procurar `MemCofre/GLOSSARY.md` para corrigi-lo e não vai achar. É ponteiro
envelhecido dentro de pendência já resolvida — **não** é premissa expirada, e por isso não
justifica emenda: o `lapide-recheck` mede que o §5 já é **83.9%** do `proibicoes.md` e entra
em TODA sessão via `@import`; gastar contexto perene para consertar um ponteiro morto de um
parágrafo resolvido é custo sem retorno.

## 3. 2026-07-22 — o exemplo do falso-positivo foi apagado (classe 1)

`memory/03-architecture.md` foi deletado em `beed9cfc7d7`,
[#5086](https://github.com/wagnerra23/oimpresso.com/pull/5086) (2026-07-30) — *"apaga os
fósseis 01-project-overview e 03-architecture, reponta os vínculos"*.

O arquivo era o **exemplo** do falso-positivo que a lápide registra (o classificador dava
`APPROVE conf 0.93` num doc que se auto-declarava `⚠️ STALE / PontoWr2-era`). A deleção é o
desfecho **coerente com o classificador consertado**, que passou a dizer *"candidato a
tombstone, nunca move automático"* — e ele foi apagado, não movido.

Defesa mecânica intacta: `document-relocation-classifier.mjs --selftest` → **20/20**,
incluindo `banner-stale-autodeclarado-nunca-approve`, o controle negativo
`mencao-stale-em-prosa-nao-dispara` e `consolidacao-banner-stale-tambem-cai`. **As 3 fixtures
são sintéticas** — usam `memory/03-arch.md` (nome inventado) com texto inline, nunca o
arquivo real. A deleção do fóssil não podia quebrá-las, e não quebrou.

## Nota de método (near-miss meu, peguei antes de publicar)

Ao exercitar a fixture do `knowledge-drift`, rodei primeiro
`node knowledge-drift.mjs --check <dir>` — **assinatura inventada**. Os dois lados deram
`rc=0`, e eu estava a um passo de concluir *"a fixture não morde"*. O `gate-selftest.mjs:483`
mostra o contrato real: `runNode(script, ['--check'], join(FIX, id, kind))` — o terceiro
argumento é o **cwd**, e a fixture é sandbox por diretório. Rodado do jeito certo, morde
(`good` 0 · `bad` 1).

É LC-08 na cena do crime: eu ia afirmar ausência de mordida a partir de um instrumento
invocado errado, dentro de uma triagem cujo objeto são lápides sobre medir com a fonte certa.
Fica registrado, não apagado.

## DoD

- ✅ 3 triadas com veredito + evidência (PR de deleção/rename de cada âncora)
- ✅ Nenhuma na classe 3 → **nenhuma emenda** proposta
- ✅ Zero edição de lápide existente (§5 append-only Tier 0)
- ✅ Nenhum campo auto-declarado introduzido para silenciar o alarme — o `lapide-recheck`
  segue sem gravar nada, e as 3 continuarão aparecendo como `revisar` na próxima rodada
  (é o desenho: o proxy é mecânico, o julgamento é humano e não se persiste no detector)
