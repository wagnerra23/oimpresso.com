---
sessao: "_saida-03"
thread: "03 · DS: TabBar com setas (tablist) + PageHeader com role=banner"
dono: "[C]"
data: 2026-09-23
tipo: recibo de parada — não executada
base_lida: wagnerra23/oimpresso.com@main 1061dbf2e
---
# _saida-03

## Estado: NÃO executada — a mudança nasce no Cowork, não no Code

O placar dava `proximo` e nenhum PR aberto tocava `components/TabBar/`, `components/PageHeader/`
nem `Components/PageHeader/PageHeader.tsx` (thread 04). A thread parou no passo de conferir
**quem pode editar o prefixo**. A resposta é: o Code não pode.

## Por que o Code não edita `prototipo-ui/design-system/components/**`

1. `prototipo-ui/design-system/CLAUDE.md` — a tabela *Vocabulário das cópias* define
   `prototipo-ui/design-system/` como **carga publicada**: *"cópia do espelho commitada no repo,
   só pra leitura"*. A seção *Direção do fluxo* diz: decisão [W] → git **vivo** → o Cowork puxa
   para o **espelho** → espelho → carga publicada. Editar a carga publicada à mão inverte esse fluxo.
2. [ADR 0406](../../../../../../memory/decisions/0406-o-que-ultimo-importado-decide-emenda-0404.md)
   linha 114 põe `design-system/` como **caminho de dono alheio**, recusado por regra.
3. O `_ds_bundle.js` (`@ds-bundle format 4`, namespace `OfficeImpressoPontoWR2DesignSystem_019dd0`)
   é gerado pelo projeto DS do Claude Design. **Nenhum script do repo o regenera.**

### Correção do próprio playbook (`03-ds-a11y.md`)

A thread manda *"regenerar `_ds_bundle.js` pelo caminho normal do DS (`ds-push.mjs`)"*. **Esse
caminho não existe.** Pelo docblock dele, o `scripts/design-sync/ds-push.mjs` monta e valida
**tokens** (`colors_and_type.css` + `cockpit_domains.css`). Ele não faz upload e não toca em
componentes nem no `_ds_bundle.js`. Seguir a instrução obrigaria a escrever o bundle à mão, e
isso é proibido (§5 2026-08-11: transcrever conteúdo entre sistemas).

## O vivo já está certo (medido em 1061dbf2e)

O defeito é do espelho, que está atrasado em relação ao vivo:

| comportamento | vivo (`resources/js/Components/**`) | carga publicada (`prototipo-ui/design-system/components/**`) |
|---|---|---|
| setas no tablist | `shared/PageHeaderTabs.tsx:178-181` (ArrowLeft/ArrowRight/Home/End), `:228` `role="tablist"`, `:245` `aria-selected` | `TabBar/TabBar.jsx`: 0 ocorrências de `ArrowRight`/`tablist` |
| header como landmark | `PageHeader/PageHeader.tsx:140` `role="banner"` | `PageHeader/PageHeader.jsx`: 0 ocorrências de `banner` |

## O que destrava (fora do Code)

1. **[CC] no projeto DS do Cowork:** trazer as duas mecânicas do vivo para `TabBar.jsx` e
   `PageHeader.jsx`. A `03-ds-a11y.md` diz para decidir entre tablist e `<nav>` seguindo o que o
   `PageHeaderTabs` faz, e ele usa **tablist**.
2. Emitir o bundle **como último ato do ciclo** (PROTOCOL §0, fase −1). É esse bundle que
   atualiza `components/**` e o `_ds_bundle.js` no repo, pela rota de import.
3. Depois disso, as duas provas do `00-INDICE.md` passam sem ninguém editar nada no Code.

Até lá o placar vai mostrar esta thread como `em curso`. Esse é o estado honesto: a sessão
passou por aqui, mediu e não havia nada que o Code pudesse fazer.

## Placar
entregue 0 de 2 · ausentes: `TabBar.jsx` (setas/tablist) e `PageHeader.jsx` (`role="banner"`),
os dois porque o prefixo pertence ao projeto DS do Cowork e não ao Code
