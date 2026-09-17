# Errata ao PR-A8 do plano de automação do protocolo — a fonte foi removida pela ADR 0397

> **De:** Claude Code → **Para:** Cowork · **Data:** 2026-09-17
> **O que é:** o `COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md` §"Anexos do PR-A8" manda quem executa
> **ler os dois arquivos-fonte no `main` no turno**, em vez de colar a cópia inline (que foi
> removida em 2026-09-09, corretamente, por envelhecer). Em **2026-09-11** a [ADR 0397](../../decisions/0397-prototipo-minimo-por-dono-e-ds-direto.md)
> removeu do git a árvore onde esses dois arquivos moravam. A instrução ficou apontando para o
> vazio: **a sessão que pegar o A8 não tem o que ler**, e gasta a rodada procurando.
> Esta errata registra o fato datado. Nada do plano foi editado (ele vive no espelho de leitura,
> ADR 0374/0405 — edição minha ali some no próximo import). A correção é decisão [W].

---

## 1 · O que foi medido (2026-09-17, contra `origin/main`)

As 4 peças que o PR-A8 declara como pré-existentes:

| peça | o A8 diz | medido em `origin/main` |
|---|---|---|
| A6 — `scripts/qa/placar.mjs` | "estende" | **ausente** — `scripts/qa/` tem 22 arquivos, nenhum `placar` |
| A7 — `.claude/commands/onda.md` | "estende" | **ausente** — 6 arquivos em `.claude/commands/`, nenhum `onda.md` |
| `prototipo-ui/design-docs/cowork-inbox/_schema/playbook.schema.json` (removido do git em 2026-09-11 · #7224 · ADR 0397) | "leia no main" | **removido** |
| `prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs` (removido do git em 2026-09-11 · #7224 · ADR 0397) | "leia no main" | **removido** |

Reproduzível: `git ls-tree --full-tree origin/main -- scripts/qa/ .claude/commands/` e
`git log origin/main --diff-filter=D --format='%h %cd %s' --date=short -- '<path removido>'`.

Os dois paths **existiram** e eram exatamente os que o A8 nomeia — o `#7063` e o `#7071` os
tocaram (é de onde vêm `constituicao`/`nota_caminho` no schema e `descobrirIndices` no placar,
que o A8 cita com razão). Foram removidos depois, junto com a árvore inteira.

## 2 · Não foi acidente de realocação — foi decisão [W]

O `#7224` (2026-09-11, 2054 arquivos, 504 deletados / 587 renomeados) é a ADR 0397,
*"protótipo mínimo por dono, máquinas fora do artefato e Design System direto"*. Três itens dela
alcançam esses arquivos, e **cada um sozinho** já impediria recriá-los naquele endereço:

- **D1 — árvore mínima:** `prototipo-ui/` contém somente `cowork/{Wagner,Felipe}/` e
  `design-system/`; *"não se admitem arquivos soltos nem outras pastas nessa raiz"*.
  Medido hoje: a raiz tem exatamente `cowork/` + `design-system/`, e `cowork/` exatamente
  `Felipe/` + `Wagner/`.
- **D3 — protótipo contém artefato, não operação:** *"máquinas de inspeção, importação e
  comparação vivem em `scripts/design/`"*. Um `_scripts/` dentro de `prototipo-ui/` é a
  topologia que a D3 desfez.
- **D5 — histórico somente no Git:** nomeia `design-docs/` entre os que *"não são cemitérios
  válidos"*.

**Estado da 0397 em 2026-09-17:** `status: aceito` · `lifecycle: ativo` · `superseded_by: []`.
Tem **uma** emenda parcial, escopada: a [ADR 0401](../../decisions/0401-resolucao-ds-bound-no-servidor-de-preview.md)
(2026-09-16) declara `supersedes_partially: [0397]` e se intitula *"emenda parcial a 0397 **D4**"* —
o eixo Design System / preview. Ela diz no corpo que *"a D5 fica intacta"* e que da D4 *"sentenças
1, 3 e 4 seguem vigentes"*. As ADRs 0398 e 0405 citam a 0397 apenas em `related` (ambas com
`supersedes: []`; a 0405 supersede parcialmente a **0398**, não a 0397). **Nada tocou D1 nem D3.**

## 3 · Correção de conteúdo: `playbook.json` é o formato ANTIGO

O A8 descreve ler `cowork-inbox/<mod>/playbook/playbook.json`. O script removido já havia
superado esse formato — comentário dele, lido no git em `4f51a9ec781^`:

> *"A fonte é o PRIMEIRO bloco ```json embutido no `00-INDICE.md` — só `.md` roteia pelo
> DesignSync, então `.json` solto não chega. Antes daqui o script fazia JSON.parse do arquivo
> cru e só rodava com um playbook.json"*

Confere com o que está vivo: **zero** `playbook.json` no repo, e **12** `00-INDICE.md` em
`prototipo-ui/cowork/Wagner/cowork-inbox/*/playbook/`. A Lei 2 também é real e viva — **5**
arquivos `_saida-NN.md`. Quem retomar o A8 e procurar `playbook.json` vai concluir que o índice
não existe; ele existe, embutido no `.md`, e por um motivo declarado (o transporte).

## 4 · O que sobrevive do A8, e o que é decisão [W]

**Sobrevive:** o valor. "O Code terminou a lista inteira?" continua sem máquina, e o código que
respondia isso está no git (178 linhas + teste), legível em `4f51a9ec781^` — que é o cemitério
que a própria D5 declara **válido**. O formato que ele lê está vivo.

**Revogado:** só o **endereço**. Recriar sob `prototipo-ui/**` reabriria D1+D3+D5 de uma vez.

**Decisão [W]** — o A8, como escrito, pressupõe (a) A6 e A7 prontos, e nenhum dos dois foi feito,
e (b) uma topologia que a 0397 revogou 2 dias depois de o plano ser escrito. Não é escolha de
técnica; é escopo e ADR. O caminho que eu recomendaria: **portar** o placar do git para o
endereço que a D3 nomeia (`scripts/design/`), autossuficiente, sem depender do A6 — que é placar
de **tela**, tema vizinho mas distinto — nem do A7. Fica 1 PR, 1 intent, e o aceite falsificável
do A8 (remover uma prova faz `X` cair para `X−1` nomeando a thread; `_saida` ausente reprova a
thread) é mensurável nele.

## 5 · Por que esta errata existe

O plano afirma **em presente** que os dois arquivos estão no `main`. Afirmação de estado em doc
que a próxima sessão lê como instrução é a classe registrada em `proibicoes.md` §5 2026-09-01:
não produz vermelho, produz **silêncio** — ninguém descobre que a instrução caducou até gastar a
rodada. O registro aqui é datado de propósito: descreve o que era verdade em 2026-09-17, não um
estado permanente.
