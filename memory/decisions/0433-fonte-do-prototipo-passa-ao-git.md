---
slug: 0433-fonte-do-prototipo-passa-ao-git
number: 433
title: "A fonte do protótipo do [W] passa a ser o git; o Cowork vira ferramenta opcional"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-15"
module: governance
quarter: 2026-Q4
tags: [design, cowork, espelho, prototipo, ssot, claude-design]
supersedes: []
supersedes_partially:
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0389-emenda-0374-escrita-do-espelho-quando-o-get-file-volta-inline
  - 0398-espelho-cowork-recebe-a-arvore-da-conta
  - 0404-ultimo-importado-e-autoridade-do-espelho
  - 0406-o-que-ultimo-importado-decide-emenda-0404
superseded_by: []
related:
  - 0282-protocolo-v2-colapso-ratificacao
  - 0412-retorno-canon-mergeado-ao-cowork-dispensa-opt-in-emenda-0315
  - 0405-espelhos-cowork-independentes-por-conta
pii: false
review_triggers:
  - "a Anthropic publicar o caminho de migração de projetos do Claude Design para o Claude (PROTOCOL.md §10.8, 'coming soon' em 2026-10-15)"
  - "o [W] voltar a desenhar no Cowork e precisar trazer a versão de lá por cima do git"
  - "uma edição no git ser desfeita por import do Cowork (a trava [6b] do receber-handoff falhou)"
---

# ADR 0433 — a fonte do protótipo do [W] passa a ser o git

## Contexto

Até aqui, o protótipo das telas era desenhado no projeto Cowork (claude.ai/design) e descia ao
repo em `prototipo-ui/cowork/Wagner/` como **espelho**. Sete ADRs fixaram isso: o espelho só
recebe, o último pacote importado vence, e quem edita o espelho à mão comete remendo
(0315 §Contexto, 0374, 0389, 0398 D1, 0404, 0406 D1/D2). Um check obrigatório
(`espelho — mexeu depois de verificar`) reprovava qualquer PR que mudasse um arquivo já
verificado sem nova prova do Cowork.

Dois fatos mudaram o custo dessa regra:

1. **O Claude Design standalone fecha em 2026-12-14** (artigo oficial da Anthropic, lido em
   2026-10-15; [PROTOCOL.md §10.8](../reference/prototipo-ui/PROTOCOL.md)). Os projetos não
   migram ainda, e chats e comentários são apagados. A cópia no git é a que sobrevive.
2. O [W] quer pedir as mudanças de tela no chat do Claude Code e vê-las ali mesmo, no painel do
   navegador, sem passar pelo Cowork. Com o espelho só-leitura, toda edição feita aqui era
   reprovada pelo check e desfeita no import seguinte.

O canon já se contradizia: `prototipo-ui/cowork/Wagner/CLAUDE.md:3` e o hook
`lembrar-subida-cowork.mjs` diziam "git é a fonte" desde 2026-09-29, enquanto as ADRs acima e o
check diziam o oposto.

## Decisão

[W] 2026-10-15, textual: *"Sim: passa a fonte do protótipo para o git. Primeiro traz a versão
atual do Cowork (com o celular) para o espelho; depois eu peço as mudanças aqui e você edita
direto."*

- **D1 — fonte.** A partir de 2026-10-15, a fonte do protótipo do dono Wagner é
  `prototipo-ui/cowork/Wagner/` no git. Edição commitada ali é autoria, não remendo. A declaração
  executável é `FONTE_GIT` em [`scripts/design/fonte-prototipo.mjs`](../../scripts/design/fonte-prototipo.mjs).
- **D2 — o Cowork vira ferramenta opcional.** Desenhar no Cowork continua permitido. O que vem de
  lá entra pela rota ZIP como hoje, mas não tem mais precedência sobre o git: quando o pacote
  traz um arquivo que o git mudou depois, a trava `[6b]` do `receber-handoff` recusa, e passar
  por cima (`--descartar-local`) é decisão do [W] caso a caso.
- **D3 — o check obrigatório deixa de acusar autoria.** `cowork-mirror-freshness --unverified`
  passa a separar o commit posterior à virada (relatado como "editado no git", sem reprovar) do
  commit anterior (julgado pela regra antiga). A história não é reescrita.
- **D4 — âncora STALE contra o Cowork deixa de bloquear.** O hook `charter-validate` não nega
  mais a edição de Page cuja âncora sob `cowork/Wagner/` diverge do Cowork vivo: essa divergência
  passou a ser o esperado.
- **D5 — o retorno ao Cowork segue como está.** O canal `cowork-inbox/` mergeado sobe sem
  opt-in (ADR 0412). Levar `*.jsx`/CSS do git para o Cowork continua exigindo opt-in do [W].

O que **não** muda: a 0315 no eixo do Design System (o claude.ai/design não é fonte do DS), a
0398 D2 (`.md` aceito no espelho), a 0405 (um espelho por conta) e a 0412.

## Consequências

- Edito o protótipo direto no repo a pedido do [W], com o painel do navegador servindo o espelho
  (`servirEspelho` do `design-diff-lote.mjs`), e o PR passa no check.
- O espelho deixa de ter "prova de fidelidade" contra o Cowork como requisito. A fidelidade que
  importa passa a ser protótipo × produção, medida por `design-diff` como hoje.
- Ficam para PRs seguintes, sem bloquear este: o texto das regras antigas que ainda dizem
  "espelho só-leitura" (`PROTOCOL.md`, `PROCESSO_MEMORIA_CC.md`, `COWORK-ESTRUTURA-E-TELAS.md`,
  `prototipo-ui/cowork/Wagner/CLAUDE.md`, `proibicoes.md` 2026-08-14 e 2026-09-24), e a rota
  legada `importar-bundle.mjs` (`robocopy /PURGE`), que sobrescreve o espelho inteiro e deve ser
  aposentada.
- O nome do check obrigatório fica igual: renomear um context required exige atualizar os PRs
  abertos (§5 2026-08-08).
