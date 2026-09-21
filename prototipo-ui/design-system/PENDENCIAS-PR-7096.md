# Adendo — PR 7096

**Data:** 09/09/2026 · 20:30 · **Origem:** leitura do PR 7096 a pedido do [W], depois do sync do dia

Este adendo é separado do documento principal porque trata de outra direção do loop. O sync de hoje foi **repo → espelho** (trazer o que mudou no `oimpresso.com`). O PR 7096 é o caminho de volta: **espelho → repo**, publicando este design system dentro do `oimpresso.com` em `prototipo-ui/design-system/`.

---

## Antes de tudo: por que eu não tinha visto o PR

Quando você pediu "importe as atualizações do git do main", eu medi **o `main`, e só ele**. Um PR aberto não está no `main` — ele vive no branch dele até o merge. Nenhuma medição do `main`, por mais cuidadosa que fosse, poderia mostrar o 7096. Cheguei nele porque você deu o número.

**O que muda daqui pra frente:** sync que lê só o `main` é incompleto enquanto houver PR de design aberto. Quando pedir sync, mande o número do PR junto — ou me diga que tem PR aberto, que eu leio antes de concluir qualquer coisa.

---

## O problema, em uma frase

**A carga de 31/08 já está no `main`.** O PR 7096 não é o que vai publicar um DS velho — ele já foi publicado. O `main` tem hoje `prototipo-ui/design-system/` com **243 arquivos**, e o `github.md` dentro dessa pasta declara `date: 2026-08-31T20:40:00Z`. Medido: o `StatusBadge.d.ts` que está **no `main` agora** declara `/** Every domain the component actually maps (11). */` — 11 domínios.

> **Errata.** A primeira versão deste documento dizia que mergear o PR publicaria o estado de 31/08. Errado: esse estado já está publicado. O `github_compare` marcou os 250 arquivos como `[added]` e eu li isso como "pasta nova", mas a própria ferramenta avisou que as histórias divergiram (o `main` tem 69 commits que o branch não tem) e que a lista só cobre o lado do branch. Conferido depois por árvore: a pasta existe no `main`, com os mesmos arquivos.

**O que o PR muda de design, medido arquivo a arquivo: nada.** Os três únicos `[modified]` são do PageHeader, e comparei os dois lados: `PageHeader.d.ts` (1619 bytes) e `PageHeader.jsx` (3743 bytes) são **idênticos** no `main` e no PR, texto por texto. O PR é, em conteúdo de design, um no-op.

---

## Onde está a defasagem de verdade

Não é entre o PR e o `main`. É entre **os dois** e este espelho:

| | `StatusBadge` | de quando |
| --- | --- | --- |
| `main` (e o PR) | **11 domínios** | 31/08 |
| este espelho | **15 domínios** | hoje |

Os 4 que faltam nos dois lados: `producao`, `arquivo_prazo`, `ajuste_estoque`, `transferencia_estoque`. E a carga publicada ainda traz a badge sólida com tinta branca — ~2,2:1 sobre `--color-warning`, ~3,2:1 sobre `--color-success`, os dois abaixo do mínimo de 4,5:1 que a v1.3.0 dos tokens existiu para corrigir.

A comparação por sha256 dos 161 arquivos (componentes + CSS + bundle + templates) mede o tamanho disso: **65 idênticos, 72 divergentes, 24 arquivos só no snapshot**. Os 72 são o trabalho de 01/09 a 09/09 — PageHeader com slot `below`, DataTable com `caption`/`scope`, AppSidebar com PLATAFORMA/Forja, os tokens v1.1.0→v1.3.0, o `_ds_bundle.js` e 6 dos 7 templates.

---

## O achado que não é defasagem

Oito componentes existem na carga publicada e **não existem mais neste espelho**:

```
ColumnManager · DataGrid · Kebab · PresenterMode
Segmented · Timeline · Toolbar · Widget
```

O espelho tem 41 pastas em `components/`; o snapshot tinha 49. Nenhum template importa nenhum dos oito, então nada está quebrado hoje. Mas `Segmented` tem par vivo no repo (`resources/js/Components/ui/segmented.tsx`), e quando o snapshot for regerado a partir do espelho atual, **os oito somem da carga publicada junto**.

---

## O que precisa ser feito

**1. Decidir o que fazer com o 7096.** Ele não muda nada de design em relação ao `main` — os únicos arquivos marcados como alterados são idênticos dos dois lados. Ou o branch dele precisa ser regerado antes de servir para alguma coisa, ou ele fecha sem merge.

**2. Rodar o `ds-push.mjs --write` a partir do espelho atual.** Este é o item que importa, e independe do PR: o que está publicado no `main` é de 31/08. Regerar resolve os 72 divergentes de uma vez.

**3. Decidir sobre os oito componentes, antes de regerar.** Duas respostas possíveis, e só você tem a informação:
   - **Foram removidos de propósito** → registro a remoção e o PR regerado sai com 41 componentes, correto.
   - **A remoção não foi deliberada** → eu restauro os oito a partir do próprio PR (que é a única cópia que sobrou) e só então regeramos.

**4. Confirmar quem roda o `ds-push`.** O branch foi atualizado sem que a carga fosse regerada. Se isso é passo manual, é a mesma classe de defeito que a gente vem encontrando nas cópias à mão: a fonte muda e a cópia fica parada. Vale amarrar o `ds-push --write` na mesma ação que atualiza o branch.

---

## Resumo em uma linha cada

| # | Item | Tipo |
| --- | --- | --- |
| 1 | O 7096 não muda design nenhum vs `main` — regerar o branch ou fechar | decisão |
| 2 | Regerar o snapshot publicado no `main` (`ds-push.mjs --write`) — está em 31/08 | execução |
| 3 | Os 8 componentes ausentes: remoção deliberada ou perda? | **decisão [W]** |
| 4 | Amarrar o `ds-push --write` à atualização do branch | processo |
