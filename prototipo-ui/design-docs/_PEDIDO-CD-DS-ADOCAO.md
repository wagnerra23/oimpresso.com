# Pedido ao [CD] — existem 23 mini-DS por módulo convivendo com o DS v6

> **Cole isto no chat do projeto Cowork.** Escrito por [C] a pedido de [W] em 2026-08-31.
> Este arquivo é **autoral**, não é espelho de nada.

## Contexto em três linhas

O bundle `_ds/…/_ds_bundle.js` entrega **44 componentes** sob
`window.OfficeImpressoPontoWR2DesignSystem_019dd0`. O shell carrega **180 `.jsx`**, todos vivos.
Só **34** consomem o DS.

As outras não estão sem sistema — elas têm **outro**. Medi **23 namespaces de componente por
módulo** (`window.HrmUI`, `window.PontoUI`, `window.AcessosDS`, `window.ModuloPadrao`,
`window.CatchupUI`, `window.CBUI`, `window.PBUI`, …) convivendo com o DS. Mais 24 namespaces de
**dado/mock**, que são legítimos e não entram nesta conversa.

O problema não é ausência de partilha. É **duas taxonomias pro mesmo papel** — e a segunda está
em português abreviado, o que a torna invisível a qualquer busca por nome.

## Os números — não os copie daqui, rode

```bash
node scripts/governance/component-registry-check.mjs --roles
```

Medição de **2026-08-31**, base `origin/main @ 84b62eb785`. Andam a cada export — o comando é o
dono deles, este parágrafo é o retrato do dia.

> Re-medido em `84b62eb785` (29 commits depois da 1ª leitura): **sem mudança**. Os 29 commits não
> tocaram `prototipo-ui/cowork/` nem o `_ds_bundle.js` — `git diff --stat` entre os dois shas nesses
> dois caminhos volta vazio.

---

## A · O de-para — é o pedido nº 1, e só vocês podem fazer

Este é o item que destrava todos os outros. Sem ele, nada aqui é mecanizável.

Exemplos que consegui mapear **no olho**, e que mostram a natureza do problema:

| No namespace do módulo | Provavelmente é, no DS v6 |
|---|---|
| `PontoUI.Pill` · `CBUI.Pill` · `HrmUI.Badge` · `CBUI.Badge` | `StatusBadge` |
| `PontoUI.Vazio` · `AcessosDS.Vazio` · `HrmUI.Vazio` · `ModuloPadrao.Estado` | `EmptyState` |
| `CatchupUI.Grade` · `CBUI.Grade` · `PontoUI.Tabela` · `HrmUI.Tabela` | `DataTable` |
| `AcessosDS.Kpi` · `AcessosDS.Kpis` · `PontoUI.Kpi` · `HrmUI.Kpis` · `ModuloPadrao.Kpis` · `CatchupUI.Kpis` | `KpiCard` |
| `AcessosDS.Sw` | `Switch` |
| `AcessosDS.Chk` | `Checkbox` |
| `HrmUI.Campo` · `PBUI.Fld` | `Input` |
| `HrmUI.Escolha` · `PBUI.Sel` | `Select` |
| `ModuloPadrao.Header` | `PageHeader` |
| `ModuloPadrao.Tabs` · `HrmUI.Seg` | `TabBar` |
| `PontoUI.Nota` · `HrmUI.Nota` · `AcessosDS.Nota` | `Alert` |
| `CatchupUI.Painel` | `Drawer` |
| `PontoUI.Pager` | `Pagination` |
| `HrmUI.Data` | `DatePicker` |
| `AcessosDS.Confirm` | `Modal` |
| `HrmUI.Bulk` · `AcessosDS.Bulk` | `BulkBar` |

**São 39 símbolos com essa cara — e isso é PISO, não teto.** Só entraram os apelidos que eu
reconheci sozinho. `HrmUI` sozinho expõe 29 componentes; mapeei 11. Ninguém além de vocês sabe
se `HrmUI.KV` é um `DrawerSection`, se `CatchupUI.Def` é outra coisa, ou se algum deles é
genuinamente novo.

**O pedido concreto:** cada um dos 44 componentes do DS ganha um `*.prompt.md` de 1 a 3 linhas
dizendo **quando usar**, **com o que pareia** e **que apelidos de módulo ele substitui**. O
formato já existe do nosso lado — `design-system/components/PageHeader/PageHeader.prompt.md`:

> *"Flat index header — title + tabular toned stats + right-aligned actions (DS v4 slot 1 of
> PT-01). Pairs with TabBar (slot 2) to form the Clientes-style header. No icon box; primary
> roxo."*

Repara no que essa linha faz e o `name` não faz: diz **com quem pareia**, **em que slot do
Padrão de Tela vive** e **o que NÃO tem**. Hoje o `@ds-bundle` declara, por componente,
exatamente dois campos — `name` e `sourcePath`. Procurei as oito chaves que documentariam uso
(`description`, `usage`, `props`, `variants`, `whenToUse`, `docs`, `anatomy`, `guideline`):
**zero ocorrências de todas as oito.**

Se der pra embutir esse texto no `@ds-bundle` como campo por componente, melhor ainda — vira
dado que máquina lê, não prosa que alguém precisa lembrar de abrir.

---

## B · Colisão de nome exato — 22 casos, o subconjunto fácil

Estes são os que reescrevem um componente **com o mesmo nome** do DS. É o caso raso; o §A é o
fundo. Separei por natureza, porque a ação é diferente em cada bloco.

### B1 · Renomeação 1:1 — o DS serve, só a assinatura está em outro idioma

| Arquivo | Local | DS v6 |
|---|---|---|
| `officeimpresso-page.jsx` | `Drawer{titulo, sub, badge, children, rodape, onClose}` | `Drawer{open, onClose, title, subtitle, badge, width, children, footer}` |
| `hrm-ui.jsx` | `Drawer{title, sub, onClose, children, footer, largo}` | idem |
| `superadmin-page.jsx` | `BulkBar{count, rotulo, acoes, onClose}` | `BulkBar{count, label, actions, onClose}` |
| `funcoes-page.jsx` · `modulos-page.jsx` | `Switch{on, onToggle, travada\|label}` | `Switch{checked, onChange, label, sublabel, disabled, name}` |
| `modulo-padrao.jsx` | `Skeleton{compacto}` | `Skeleton{variant, width, count}` |
| `tasks.jsx` | `TaskCard{task, active, onClick}` | `TaskCard{task, selected, onDragStart, onClick}` |
| `clientes-page.jsx` · `cobranca-recorrente-page.jsx` | `Avatar{name, size}` | `Avatar{name, initials, c, size, title, status}` |

### B2 · A tela precisou de algo que o kit não tem — **isto é pedido de extensão, não erro**

Este bloco é o mais valioso do documento e eu quase o classifiquei como dívida. Não é.

| Arquivo | Local | O que o DS não tem |
|---|---|---|
| `boletos-page.jsx` · `pg-shared.jsx` | `KpiCard{…, icon, contextual}` | o DS tem `spark`, `delta`, `progress` — não tem `icon` nem `contextual` |
| `produtos-page.jsx` | `BulkBar{total, foraDaPagina, …}` | `foraDaPagina` é conceito de seleção que atravessa página; o DS só tem `count` |
| `boletos` · `pg-shared` · `financeiro` | `StatusBadge{status}` / `{status, compact}` | o DS pede `kind`+`value`+`tone`; a tela quer passar **um** status de domínio |
| `fsm-stepper.jsx` | `FsmStepper{domain, current, variant, terminal, onClick}` | o DS pede `steps` prontos; a tela quer passar o **domínio** e deixar o kit derivar |
| `financeiro-page.jsx` | `PeriodBar{11 props}` | o DS tem 5; o resto é controlador de filtro |

**Cada linha aqui é um requisito real que apareceu ao desenhar.** A pergunta que eu não sei
responder: o kit deve absorver isso, ou a tela deve mesmo compor por cima? É de vocês.

### B3 · Não são cópias — são telas que por acaso se chamam `Drawer`

`cobranca-recorrente-page.jsx` `Drawer{sub, onClose}` · `financeiro-page.jsx`
`Drawer{row, onClose, onMark, onCobranca, pos, onNav, allRows}` · `oficina-page.jsx`
`Drawer{os, …15 props}`.

Recebem a **entidade** e montam o conteúdo. Não há o que migrar: o correto é usarem o
`Drawer` do DS **por dentro**, como casca, e continuarem sendo tela.

### B4 · Já estão certos — copiem a forma

`produto-blade.jsx` (`Modal`) e `planilhas-page.jsx` (`Toast`):

```jsx
function Modal({ titulo, onClose, children, acoes, largura = 720 }) {
  const { Modal: DsModal } = DS();
  if (DsModal) return <DsModal open onClose={onClose} title={titulo} width={largura}>…</DsModal>;
  // fallback local se o bundle não carregar
}
```

Wrapper que **renomeia pro vocabulário da tela** e **delega ao DS**, com fallback. Se o §B1
seguir essa forma, ele não quebra assinatura de tela nenhuma.

---

## C · Carga morta — 3 componentes que ninguém consome

`AppSidebar`, `KpiFilterCard` e `Logo` viajam nos 287 KB do bundle e nenhuma das 180 telas os
usa. Ou saem, ou alguma tela deveria estar usando. O consumo é zero — a decisão é de vocês.

---

## O que NÃO estou pedindo

**Não peço migração em massa dos 23 namespaces.** Eles são a estrutura de partilha que o
protótipo de fato usa, e funciona. Reescrever isso de uma vez é o tipo de big-bang que já deu
errado dos dois lados. O caminho é **forward-only**: tela nova nasce no DS, tela existente migra
quando já for ser tocada por trabalho real.

**Não peço codemod.** Tentei imaginar um e ele não se paga: o de-para é semântico (`Sw`→`Switch`,
`Vazio`→`EmptyState`), então o script precisaria do §A **pronto** pra existir — e com o §A pronto
a parte mecânica vira trivial. O de-para é o trabalho; o script é o troco.

## O que acontece do meu lado

Com o §A na mão, eu escrevo **um** detector que lê o bundle, os 23 namespaces e o de-para, e
reporta quem sombreia quem. Ele serve duas vezes: produz a lista de migração hoje e vira o guard
que barra a próxima cópia amanhã. Hoje, dos componentes acima, **só o `StatusBadge` tem regra de
lint** que proíba hand-rolar ele.

## Limite honesto

O §B mede **colisão de nome exato** — é completo pra esse critério. O §A mede **apelido**, e ali
eu escrevi o dicionário no olho: é **piso, não teto**, e vocês vão achar casos que eu não vi.
Abri e conferi um a um só os 5 de `Drawer` e os 2 do §B4. Três achados que eu tinha contado como
cópia e **não são**: `ponto-telas.jsx` (`const Drawer = ds.Drawer` — já consome o DS),
`cliente-drawer760.jsx` (`window.CliAvatar`) e `cobranca-recorrente-page.jsx`
(`window.FinPeriodBar`) — os dois últimos consomem do registro paralelo.

Eu **não escrevo no projeto Cowork**. Este documento é o pedido; a execução é de vocês.
