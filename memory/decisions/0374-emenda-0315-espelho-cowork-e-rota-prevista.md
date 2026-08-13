---
slug: 0374-emenda-0315-espelho-cowork-e-rota-prevista
number: 374
title: "Emenda à 0315 — espelhar o projeto Cowork para `prototipo-ui/cowork/` é a rota PREVISTA (o 'nunca o inverso' vale só para o Design System)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-11"
module: governance
tags: [governance, design, prototipo, cowork, designsync, espelho, git, time]
supersedes: []
superseded_by: []
related:
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0299-figma-nao-e-fonte-de-design
  - 0282-protocolo-v2-colapso-ratificacao
  - 0239-governanca-design-system-git-ssot-regressao-ia
---

# Emenda à 0315 — espelhar o Cowork para o git é a rota PREVISTA

## Contexto

A [ADR 0315](0315-design-sync-claude-design-vs-cowork-charter.md) contém, na linha 82, a frase:

> *"Só como canal de export read-mostly … git continua a fonte, claude.ai/design é só vitrine
> derivada. **Nunca o inverso (claude.ai/design → git)**."*

Lida ao pé da letra, ela proíbe trazer qualquer arquivo do claude.ai/design para o repositório.

Mas o repo tem, **mergeado e em uso**, uma ferramenta cujo propósito declarado é exatamente
esse movimento — [`scripts/governance/cowork-mirror-freshness.mjs`](../../scripts/governance/cowork-mirror-freshness.mjs), cabeçalho:

> *"compara cada arquivo-âncora do espelho `prototipo-ui/cowork/` com o design VIVO no Cowork
> (projeto 019dcfd3, lido via `DesignSync.get_file` — método de LEITURA, livre por ADR 0315
> Eixo B). Divergiu = o espelho ficou atrás do vivo → **RE-EXPORTAR**."*

E ~260 arquivos de `prototipo-ui/cowork/` já chegaram por esse caminho. **O texto e a prática
estão em contradição há meses**, e ninguém reconciliou.

## O incidente que forçou a decisão (2026-08-11)

O protótipo do Painel da Jana (`jana-merge.jsx`) vivia **só** no projeto Cowork. Era citado por
**21 sites do repo** — charter, dois `.tsx` de produção, workflow, `gates-registry.json`, RUNBOOK,
testes — e **não estava versionado**. Uma sessão declarou que ele "não existia", outra construiu
uma lápide §5 sobre essa negação, e a lápide passou a proibir, por tabela, o protótipo correto.

Ao ser questionado, [W] deu a razão que decide o mérito — e ela **não é jurídica, é operacional**:

> *"vai ter computadores que não vão ter acesso ao design dessa máquina. e precisarão trabalhar
> só com o git. e eu não vou ter acesso ao claude deles e vou trabalhar no git.
> **por isso baixar para git sempre**"*

Protótipo que existe apenas no `claude.ai/design` de **uma** máquina não é fonte para o time:
[F], [M], [L] e [E] ficariam sem design nenhum.

## Decisão

**A 0315 §82 proíbe `claude.ai/design` virar a FONTE DO DESIGN SYSTEM** — um segundo armazém de
tokens e componentes competindo com o DS em git, que é o risco real que a [ADR 0239](0239-governanca-design-system-git-ssot-regressao-ia.md) endereça.

**Ela NÃO proíbe espelhar o projeto Cowork** para `prototipo-ui/cowork/`. Isso é a rota
**prevista**, já mecanizada, e continua sendo:

| eixo | direção | status |
|---|---|---|
| Design System (tokens, componentes) | git **→** claude.ai/design | vitrine derivada — a 0315 segue valendo |
| Projeto Cowork (protótipos de tela) | Cowork **→** `prototipo-ui/cowork/` | **rota prevista** (`--export-from`) |
| Qualquer escrita para claude.ai/design | git **→** lá | **gated** por opt-in (0315 inalterada) |

O que muda é **só a redação**: onde a 0315 diz "nunca o inverso", leia-se *"nunca o inverso **para
o Design System**"*. O espelho de protótipo sempre foi outra coisa.

## Consequências

- `prototipo-ui/cowork/` é **espelho de leitura** do projeto Cowork, versionado para que o time
  trabalhe só com o git. Não é fonte de tokens nem de componentes — esses seguem no DS em git.
- O export é por **`cowork-mirror-freshness.mjs --export-from <dir>`**, que escreve o
  `raw.content` do `get_file`. Transcrever à mão é proibido (§5 2026-08-11).
- A cobertura do espelho é medida por **`--live-only`**, que lista o que existe no vivo e nunca
  desceu. Advisory por desenho: o que merece descer é decisão [W].
- O `--compare --check` segue sendo a prova de frescor **do que já está no espelho** — as duas
  perguntas são distintas e ambas precisam ser feitas.

## O que esta emenda NÃO decide

- **Não** afrouxa a escrita gated para o claude.ai/design (0315 Eixo A intacto).
- **Não** torna o claude.ai/design fonte de Design System (0239/0299 intactas).
- **Não** decide QUAIS dos 14 protótipos hoje `LIVE-ONLY` devem descer — isso é [W], caso a caso.
- **Não** resolve a cadência: o `--sla` acusa a rotina de frescor **fora do SLA há 34 dias**
  (limite 14, última rodada 2026-07-07). Automatizar o dispatch esbarra na auth interativa do
  DesignSync e continua em aberto.

## Gate de reversão

Se [W] decidir que **nenhum** arquivo deve descer do claude.ai/design, então: (a) o
`cowork-mirror-freshness.mjs` inteiro é ilegal e os ~260 arquivos do espelho estão em violação;
(b) telas cujo protótipo vive só lá ficam permanentemente sem fonte visual em git, e
`related_prototype: n/a` passa a ser a resposta correta, não um defeito. Essa leitura é
**possível pelo texto atual** da 0315 — é justamente por isso que esta emenda existe.
