---
title: "distiller_freshness — a fonte do evento é cega num eixo; a correção é SOMAR, não trocar"
status: proposta
date: "2026-09-15"
owners: [W]
parent_module: Governance
related_adrs: [275, 291, 344]
related_specs:
  - governance/sdd-scorecard-baseline.json (distiller_freshness · armed:true · value 1)
---

# Proposta — `distiller_freshness`: somar a data-git do CÓDIGO à do doc

> **Decisão de [W].** A métrica tem catraca **armada** (`armed: true`, baseline `1`). Mudar a
> régua muda o que o CI reprova — não se arma sem aceite. Esta proposta **mede** e **propõe**;
> não altera `scripts/governance/sdd-scorecard.mjs`.

## 1. Como a regra funciona hoje

`measureDistillerFreshness()` pergunta, por porta (`memory/requisitos/<Mod>/BRIEFING.md`), se o
carimbo `distilled_at` está >7d atrás do **doc `.md` mais novo do módulo**. E
`gitNewestModuleDocDate()` **exclui** dessa conta todo `.md` com `authority: generated`.

A exclusão foi instalada pra matar um falso-**positivo** real (PR #5298): o
`memory/requisitos/Jana/ARCHITECTURE.md`, gerado pelo `system-map.mjs`, marcava a porta da Jana
como stale **toda vez que o painel era regenerado** — avermelhando um gate required sem que
conhecimento nenhum tivesse mudado. A troca nome→frontmatter foi medida antes de instalar — *número herdado do docblock de
`gitNewestModuleDocDate`, medição de 2026-08-05, não re-rodada aqui*: 37 de 1.055 `.md` têm o
carimbo; 36 são `SUPERFICIE.md`, 1 é o `ARCHITECTURE.md`; FP=0.

## 2. A ironia — e ela é real

O arquivo que a exclusão foi criada pra silenciar é **exatamente** o que ela hoje esconde. Medido
em 2026-09-15 (repo não-shallow; 14 portas carimbadas de 80):

| fonte do evento | stale | quais |
|---|---|---|
| **A — atual** (docs, exceto `authority: generated`) | **1** | Fiscal |
| B — com os gerados incluídos | 2 | Fiscal + **Jana** |
| **C — data-git do CÓDIGO** (`Modules/<Mod>` + `Pages/<ns>`) | **1** | **Jana** |

A Jana lê **fresca** por A (`2026-09-11`, 5d) e **STALE** por B e C (`2026-09-14`, 8d). Confirmado:
a exclusão esconde um stale, e é no mesmo arquivo do #5298.

## 3. O que a medição REFUTA na hipótese "trocar a fonte"

Trocar doc→código **não é ganho puro** — troca um cego por outro. Os flips A→C são **dois**, em
direções **opostas**:

| porta | A (atual) | C (código) | o evento |
|---|---|---|---|
| **Jana** | ok (09-11) | **STALE** (09-14) | `a29df1bfa0` — 3 arquivos de código (`DesignIngestZipCommand.php`, `DesignMineRawCommand.php`, teste) |
| **Fiscal** | **STALE** (09-14) | ok (09-11) | `32af4af112` em `fiscal-config-gap.md` |

E o evento da Fiscal **não é chore**, embora o título do commit (*"refrescar prototipo_sha dos 64
map.json"*) sugira isso. O diff daquele arquivo reescreveu o **veredito**: de `**Decidir.**` para
`**Nada a fazer** — FECHADO em 2026-09-04`. Isso é conhecimento novo sobre o módulo — precisamente
o que a porta destilada deveria refletir. **A regra atual ACERTA na Fiscal, e a fonte C a perderia.**

> Registro de método: a primeira leitura desta sessão classificou os dois eventos como "chore de
> máquina" **pelo título do commit**. Abrir o diff refutou metade disso. Título de commit não é
> descrição do que o commit fez naquele arquivo.

## 4. A correção proposta: união, não troca

**stale = (doc não-gerado mais novo >7d) OU (código mais novo >7d)** — some a fonte C à A, mantendo
a exclusão `authority: generated` intacta.

- pega **as duas** portas (Fiscal por A, Jana por C) → `stale = 2` — **medido**, não deduzido:
  rodei `measureDistillerFreshness()` com uma 4ª fonte `U = max(A, C)` injetada, e o agregado deu
  `status=measured stale=2 carimbadas=14`;
- **não** reintroduz o FP do #5298 — medido: o commit de regeneração (`5e44636697`, *"regenera
  PAINEL + arquitetura Jana"*) tocou **só docs** (`ONBOARDING-AGENTE-GERADO.md`, `PAINEL-SISTEMA.md`,
  `ARCHITECTURE.md`); **zero** arquivos sob `Modules/Jana` ou `Pages/Jana`. A é cega a ele por
  carimbo, C é cega a ele por construção;
- reusa `PAGES_NS` de `module-surface.mjs` (exportado, L794) pro mapa módulo→namespace: 10 chaves,
  das quais **8 vivas** (`ADS` e `TeamMcp` são inertes declaradas — módulos removidos pela ADR 0363).
  Ex.: `AssetManagement`→`Patrimonio`, `Whatsapp`→`Atendimento`. **Não reimplementar o mapa** — o
  próprio docblock dele avisa que não se confere por leitura, e sim por
  `module-surface.mjs --namespaces --check`.
- o guard `isShallowHistory` continua valendo: em checkout raso a resposta é `not_yet_measured`,
  nunca stale fabricado.

## 5. O que [W] precisa decidir — e o custo

A catraca está **armada** em `1`. A união mede **2** → `direction: down`, 2 > 1 ⇒ **RED, exit 1**.
Armar sem mais nada **quebra o CI** até alguém agir. Os caminhos:

| opção | efeito |
|---|---|
| **(a)** destilar a porta da Jana **antes** de armar | métrica volta a 1 (só Fiscal); catraca segue em 1, CI verde |
| **(b)** [W] edita o baseline pra 2 no MESMO PR | diff visível, ADR 0275 §3; assume a dívida declarada |
| **(c)** não mexer | a Jana segue cega; a Fiscal segue detectada |

Recomendo **(a)**: o número que a catraca guarda continua significando a mesma coisa, e a dívida
é paga em vez de absorvida.

## 6. Escopo — o que esta proposta NÃO propõe

- **Não** incluir doc `authority: generated` na conta (ressuscita o falso-stale do #5298 a cada
  regeneração — é a razão de a exclusão existir).
- **Não** criar medidor novo: o dono é `measureDistillerFreshness` em `sdd-scorecard.mjs`; a união
  é uma segunda fonte **dentro** dele. Abrir paralelo seria [LC-19](../../LICOES_CODE.md).
- **Não** armar nada aqui. [ADR 0344](../0344-two-strikes-cobre-processo.md) + regra "LIGUE A
  MÁQUINA" item 4 de [proibicoes.md](../../proibicoes.md): FP medido **antes**, decisão de [W].

## 7. Resíduo medido — e por que ele NÃO se conserta com um carimbo

`memory/requisitos/Fiscal/fiscal-config-gap.md` é gerado por máquina (declara `gerado_em:` no
frontmatter) mas **não** carrega `authority: generated`. Medido no repo inteiro: **73** `.md` sob
`memory/requisitos/**` declaram `gerado_em:`, e **73 de 73** estão sem o carimbo — todos são
`*-gap.md` (GAP-SPEC por tela). Ou seja, a exclusão não enxerga essa classe inteira.

**O reflexo — carimbar os 73 — está ERRADO, e este PR é a prova.** Esses arquivos nascem gerados e
**recebem edição humana substantiva depois**: foi exatamente o que aconteceu no `fiscal-config-gap.md`,
cujo veredito foi reescrito à mão de `**Decidir.**` para `**FECHADO em 2026-09-04**`. Carimbá-los
como `generated` silenciaria justamente o verdadeiro positivo da §3. A classe "gerado" × "conhecimento"
não é binária nesses arquivos, e nenhum campo de frontmatter decide isso — é semântico
([ADR 0224](../0224-hooks-block-vs-advisory-claude-4.8-aware.md): semântico = advisory).

Isso **reforça** a proposta da §4: não mexer na exclusão (que está calibrada pro que ela mede) e
**somar** a fonte do código, que é imune ao eixo onde a exclusão erra.
