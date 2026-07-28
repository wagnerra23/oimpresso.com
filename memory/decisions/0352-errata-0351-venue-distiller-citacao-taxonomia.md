---
slug: 0352-errata-0351-venue-distiller-citacao-taxonomia
number: 352
title: "Errata 0351 — o refresh de BRIEFING via distiller (Camada 1.3) é follow-up gated numa flag --emit; corrige a citação 'taxonomia 0345' e o '40%'"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: errata
decided_by: [W]
decided_at: "2026-07-24"
module: jana
tags: [sdd, sdd-from-source, distiller, venue, errata, taxonomia, honestidade, adversario]
supersedes: []
superseded_by: []
related:
  - 0351-sdd-from-source
  - 0291-distiller-modulo-verdade-contrato-emenda-0270-f3
  - 0292-errata-0291-distiller-freshness-scorecard-deterministico
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0062-separacao-runtime-hostinger-ct100
pii: false
---

> **Errata de [ADR 0351]**, escrita após uma **revisão adversarial** do próprio PR #4767 (3 céticos
> read-only, [W] pediu "quero adversario" 2026-07-24). O corpo da 0351 é append-only ([ADR 0257]); por
> isso a correção vem em ADR nova `kind: errata`, não em edição inline. Ratificação = merge. O patch do
> agent `.claude/agents/sdd-from-source.md` (mutável) vai no MESMO PR desta errata.

# ADR 0352 — Errata 0351: venue do distiller é follow-up + correções de citação/overclaim

O adversário confirmou o **núcleo sólido** da 0351 (3 camadas, gates que mordem, append-only limpo, zero
tipo/máquina novo, piloto honesto) e derrubou **3 alegações**. Esta errata as corrige.

## Correção 1 (a mais importante) — o refresh de BRIEFING via distiller (Camada 1.3) NÃO funciona como escrito; é follow-up

A 0351 D-D disse *"o agente invoca `jana:distill-module-truth --module=X --dry-run` pra capturar o BRIEFING
destilado (zero write na árvore viva), grava no worktree"* e *"git-backed por construção — `base_path()` é o
worktree"*. **Dois furos, verificados no repo:**

1. **`--dry-run` não emite o conteúdo destilado.** `DistillerModuloVerdade::destilar` devolve `content` no array
   em dry (`DistillerModuloVerdade.php:102`), mas o **comando** o **descarta**: `DistillModuleTruthCommand::reportar`
   (`:195-206`) só imprime `"dry-run (N eventos) — não escrito"`. Não há conteúdo no stdout pra capturar.
2. **Sem `--dry-run`, o write vai pra árvore do container, não pro worktree.** O comando faz
   `file_put_contents(config('jana.requisitos_dir', base_path(...)))` (`:105`), e a Camada 1.3 manda rodar via
   `docker exec oimpresso-staging` (CT100 — testes/artisan são CT100-only, [ADR 0062]). No container, `base_path()`
   é o **deploy do container**, não a sessão/worktree. A frase "git-backed porque base_path é o worktree"
   **contradiz** "roda no container CT100".

**Correção:** o refresh de porta BRIEFING via distiller é **follow-up**, **gated numa flag `--emit`/`--stdout`**
que imprima o `content` destilado no stdout (sem escrever) — que **ainda não existe**. Até ela existir, a
**Camada 1.3 fica DESLIGADA** e o agente documenta o §5/§6 **por leitura de código** (Camadas 1.1/1.2/2/3, que
não dependem do distiller). A frase-manchete *"religa o distiller como motor"* fica reenquadrada: a 0351
**reusa código real do distiller** (o collector puro + o padrão de destilação) e **reautoriza seu uso
sob-demanda em sessão** — mas a **religação como MOTOR de refresh de porta** só fecha com a flag `--emit`. O
cron do `Kernel.php` continua comentado (isso a 0351 já dizia e está correto).

## Correção 2 — "[taxonomia ADR 0345]" é citação errada (autoridade misatribuída)

A 0351 (D-B/D-E) e o agent ancoram o guardrail "zero tipo de doc novo" em *"a taxonomia [ADR 0345] já define"*.
**A [ADR 0345] é sobre "tópicos vivos e aprendizado por crítica"** — a palavra "taxonomia" aparece **0 vezes**
nela, e ela **não define** `SDD-tela`/`ANTI-REGRESSAO`/`PARIDADE` (3 dos 5 tipos que o agente preenche). A
*substância* está certa (os tipos **pré-existem** no repo, cada um com seu gate), mas a *autoridade citada*
está errada — é a família §5 "restatear/ancorar no oráculo errado" (proibicoes, 2026-07-17).

**Correção:** o guardrail é *"só preenche tipos que **já existem no repo** (`SDD-tela-*`, `*.casos.md`,
`ANTI-REGRESSAO-*`, `PARIDADE-*`, `SPEC.md` `Implementado em:`), cada um defendido pelo próprio gate"* — **não**
"a 0345 define a taxonomia". Nenhuma ADR única cataloga os tipos; a fonte é a árvore + os gates.

## Correção 3 — "40% da visão" é número sem fonte

A 0351 (`:96`) afirma o distiller = "40% da visão", 2×, como fato. Não há medição desse "40%" no repo — é
estimativa apresentada como número (viola em miniatura o §"Comportamento Claude" — número sem fonte).
**Correção:** leia como *"reusa código real do distiller"* (qualitativo), sem cravar percentual.

## O que esta errata NÃO muda (o núcleo da 0351, que sobreviveu ao adversário)

As 3 camadas; a triangulação das 3 fontes (React+Blade+Delphi); DOCUMENTAR nos tipos existentes; CONFERIR por
`casos-gate`+`anchor-lint`; as travas Tier 0 (multi-tenant, `[V0]`, anti-tautologia, PII, PT-BR, "PERGUNTE se
falta fonte"); o cron comentado; a honestidade do piloto (⬜/🔶, zero ✅ falso). **Tudo permanece.** O
adversário também rendeu um **achado Tier 0 real** — `ProductController@update` cross-tenant devolvia 500, não
404 — corrigido em PR separado (não é assunto desta errata).

## Nota honesta sobre o piloto (não é correção de decisão, é registro)

O "piloto Produto/Edit" do PR #4767 foi feito **à mão**, no mesmo PR que criou o agent — o subagente
`sdd-from-source` **não foi executado** como tal. "Dogfood prova as 3 camadas" deve ser lido como *"o PROCESSO
das 3 camadas é executável e foi exercido à mão"*, não *"o agent-definition foi validado rodando"*. A validação
do prompt como subagente fica pra um uso real futuro.

## Implementado em

- Esta ADR (`docs`) + patch de `.claude/agents/sdd-from-source.md` (Camada 1.3 desligada até `--emit`; citação
  "0345" corrigida; overclaim suavizado) no mesmo PR. A flag `--emit` no `DistillModuleTruthCommand` = follow-up
  não implementado aqui.

## Referências
- [ADR 0351] `sdd-from-source` (alvo desta errata) · [ADR 0291]/[0292] contrato do distiller
- [ADR 0345] tópicos vivos (a ADR MIScitada como "taxonomia") · [ADR 0264] casos-gate · [ADR 0062] CT100
- Revisão adversarial 2026-07-24 (3 céticos read-only sobre o PR #4767)

[ADR 0257]: 0257-adr-status-lifecycle-kind-modelo-canonico.md
[ADR 0351]: 0351-sdd-from-source.md
[ADR 0291]: 0291-distiller-modulo-verdade-contrato-emenda-0270-f3.md
[0292]: 0292-errata-0291-distiller-freshness-scorecard-deterministico.md
[ADR 0345]: 0345-topicos-vivos-aprendizado-por-critica-revisada.md
[ADR 0264]: 0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0062]: 0062-separacao-runtime-hostinger-ct100.md
