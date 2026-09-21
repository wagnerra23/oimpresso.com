# Fabricação dentro do DS — lista de ondas

Levantamento do que falta para o módulo **Fabricação (Manufacturing)** consumir o design system
em todos os aspectos, hoje em `manufacturing-page.jsx` · `-recipe` · `-producao` · `-insumos` ·
`-print` + `manufacturing-page.css` (escopo `.mfg-*`).

**Estado de partida, medido:** a família não consome **nenhum** componente compilado do DS — só os
tokens. O próprio pacote declara isso: *"O bundle de componentes do DS (`_ds_bundle.js`) **não** é
necessário: esta família não consome nenhum componente compilado do DS, só os tokens"*
(`handoff_fabricacao/README.md` §14). Cada elemento de tela abaixo é markup local que já existe
como componente no DS.

**Procedência:** `[DS]` citado do espelho compilado `_ds/wagner-office-impresso-design-system-…/_ds_bundle.js`
com a linha da declaração da função · `[TELA]` decidido aqui · `[PAUTA]` vai para
`pauta-design-system.md`. Nenhum valor abaixo foi inventado ou convertido.

### §0 · Que árvore foi medida (para conferência)

Este documento vive **neste projeto** (`PROTÓTIPO OFICIAL - PRODUTO UNIFICADO V2`), na raiz:
`plano-fabricacao-no-ds.md`. Não está no projeto do design system.

| Fonte lida | Caminho | Para quê |
|---|---|---|
| Componentes do DS | `_ds/wagner-office-impresso-design-system-49a36f76-…/_ds_bundle.js` (9.204 linhas) | toda linha `[DS]` citada abaixo |
| A família Fabricação | `handoff_fabricacao/design/*.jsx` + `manufacturing-page.jsx` · `-recipe` · `-producao` · `-insumos` · `-print` + `manufacturing-page.css` (raiz deste projeto) | o "está hoje" |
| O handoff da família | `handoff_fabricacao/README.md` (760 linhas) | as referências §4.1, §10, §12, §14, §15, §18 |

**Não foram medidos** e não sustentam nenhuma linha deste plano: os templates `templates/pt-01-lista`,
`pt-05-dashboard`, `pt-07-os-detail` do projeto do DS, e o repo `wagnerra23/oimpresso.com`.
Os templates PT **de fato** importam `AppSidebar`, `PageHeader`, `TabBar`, `DataTablePro` etc. —
isso é o que torna a Fabricação a exceção, não o contrário.

**A afirmação "não consome nenhum componente do DS" é da própria família, verbatim:**
*"**Nenhum componente compilado do DS é consumido** por esta família: `_ds_bundle.js` não é
carregado. Só os tokens de `colors_and_type.css`."* — `handoff_fabricacao/design/README.md` L87-88.
Conferência independente: `grep -n "_ds_bundle\|OfficeImpressoDesignSystem_49a36f"` em
`handoff_fabricacao/design/` e nos `manufacturing-*.jsx` da raiz volta **0 ocorrência de import**
(a única linha que casa é a frase acima, no README).

**Nada aqui está rodando.** É a lista para você escolher as ondas.

---

## Onda 1 · Moldura da tela

| # | Está hoje | Deve ficar | Teste de aceite |
|---|---|---|---|
| F-01 | `.os-page-h` com `<h1>` + `<p>` locais | `PageHeader` `[DS]` (L5158) — `title`, `stats` `[{value,label,tone}]`, `actions` | o cabeçalho some do JSX da tela; o `<h1>` renderizado tem `font: 600 22px/1.3` vindo do componente |
| F-02 | `.mfg-tabs` / `.mfg-tab` / `.mfg-tab-n` (nav local) | `TabBar` `[DS]` (L6605) — `tabs [{key,label,icon,count}]`, `active`, `onChange`. É aba de **estado**, igual à da tela | as 5 abas somem do CSS; o contador vai em `count` |
| F-03 | `.os-btn` / `.os-btn.primary` / `.mfg-mini` / `.mfg-link` | `Button` `[DS]` (L2246) — `variant`, `size`, `icon`, `kbd` | `grep "os-btn"` na família volta 0 |
| F-04 | `.mfg-toast` | `Toast` `[DS]` (L6900) — `tone`, `icon`, `kbd` | salvar receita mostra o Toast do DS, 2600 ms |
| F-05 | `.mfg-empty` | `EmptyState` `[DS]` (L4126) — `variant='no-results'`, `title`, `description`, `action` | busca sem resultado usa o componente, com a copy atual verbatim |
| F-06 | `.mfg-crumb` (trilha do editor) | `Breadcrumb` `[DS]` (L2077) | ⚠️ **assinatura só tem `items [{label, href}]`** — o editor volta por estado, não por rota. Ou entra um `onSelect` na pauta, ou a trilha fica local **declarada como contorno** |

**Divergência do F-02, medida:** o handoff §4.1 escreveu *"Nunca pill-active"*. O `TabBar` do DS
pinta a aba ativa com `background: color-mix(in oklch, var(--accent-soft) 50%, transparent)` **e**
`borderBottom: 2px solid var(--accent)` `[DS]` (L6646-6648). O DS ganha: aplicar como está e
corrigir a frase do handoff. Nada de "ajustar o componente".

---

## Onda 2 · Consulta (aba Receitas, Insumos, Ordens, Relatório)

| # | Está hoje | Deve ficar | Teste de aceite |
|---|---|---|---|
| F-07 | 2 KPIs de leitura (`.mfg-kpi` `div`) | `KpiCard` `[DS]` (L4650) — `label`, `value`, `unit`, `description`, `tone`, `spark`, `progress`. O sub-rótulo é **`description`**, não `sub` | "Custo médio / unidade" e "Produção do mês" renderizam pelo componente |
| F-08 | 2 KPIs-filtro (`.mfg-kpi` `button` + `.act`) | `KpiFilterCard` `[DS]` (L4856) — `label`, `value`, `sub`, `icon`, `tone`, `selected`, `onClick` | clicar "Margem abaixo de 45%" liga o anel do componente e filtra |
| F-09 | 4 tabelas em CSS grid (`.mfg-table`, `.mfg-tr`, `.mfg-thead`, `.mfg-th.sort`) | `DataTable` `[DS]` (L2894) **ou** `DataTablePro` (L3099) — **decisão sua, ver §Perguntas**. As duas pendências já estão na pauta pela Consulta de Produtos: *"`DataTablePro` não aceita ordenação controlada nem linha selecionada"* (P1) e *"`DataTable` não tem prop `height`, o `DataTablePro` tem"* (P2) | nenhuma `grid-template-columns` de tabela sobra no CSS |
| F-10 | `.mfg-pag` | `Pagination` `[DS]` (L5263) — `page`, `pageCount`, `onChange`, `total`, `pageSize` | com 24 resultados: `1–10 de 24` vindo do componente |
| F-11 | `.mfg-bulk` (sticky + sombra `rgba(0,0,0,.28)`) | `BulkBar` `[DS]` (L2137) — `count`, `label`, `actions [{…, tone:'danger'}]`, `onClose` | some 1 das 4 cores cruas de `rgba` declaradas no §10 do handoff |
| F-12 | `.mfg-s` (busca com ícone dentro) | `Input` `[DS]` (L4481) | ⚠️ **a assinatura é `{label, help, error, value, defaultValue, placeholder, type, disabled, readOnly, onChange, name}`** — sem slot de ícone, **sem `ref`** e sem `onKeyDown`. O ícone **já está na pauta** (`pauta-design-system.md`, *"`Input` sem slot de ícone ou prefixo"*, P1) — não abrir item novo. Falta acrescentar ali o `ref`/foco programático, que é o que o atalho `/` (R-04) exige |
| F-13 | `.mfg-chip` (categoria, seleção única) | `FilterChip` `[DS]` (L4236) **não serve**: é pílula de filtro **ativo com ✕** (`label`, `value`, `onRemove`), não um seletor de categoria | **decisão sua, ver §Perguntas** |

---

## Onda 3 · Overlays e estado

| # | Está hoje | Deve ficar | Teste de aceite |
|---|---|---|---|
| F-14 | `.mfg-scrim` + `.mfg-drw` (3 drawers: receita, ordem, insumo) | `Drawer` + `DrawerSection` `[DS]` (L3753) — `open`, `onClose`, `title`, `subtitle`, `badge`, `width`, `footer` | ✅ **mata a pendência §18.4 do handoff**: o `Drawer` do DS tem focus trap próprio (`Tab`/`Shift+Tab` circulam, `Escape` fecha — L3766-3795). Passar `width={680}` mantém a medida atual (default do componente é 480) |
| F-15 | `.mfg-modal` (Nova receita) | `Modal` `[DS]` (L5041) — `open`, `onClose`, `title`, `children`, `footer`, `width` | `width={520}` reproduz a medida de hoje |
| F-16 | `.mfg-modal.sm` (confirmar exclusão) | mesmo `Modal`, `width={400}` | o texto de perda (ficha + N ingredientes + ordens preservadas) sai verbatim |
| F-17 | `.mfg-pill` de situação da ordem (Finalizada / Rascunho) | `StatusBadge kind="documento"` `[DS]` (L6282; `MAP` L6389-6395) — o docblock do componente **destina `documento` à OP**: *"Documento / aprovação — workflow genérico de ERP (pedido, requisição, OP…)"* | ⚠️ `documento` tem `rascunho` mas **não tem estado terminal `finalizada`** (as chaves são rascunho · pendente · aprovado · rejeitado · aplicado · cancelado). Só isso vai à pauta — **não** um `kind: 'producao'` novo |
| F-18 | `.mfg-pill.ok/.warn/.bad` das faixas de margem | `StatusBadge` com **`tone`** direto (a prop existe na assinatura, L6282) — `success` / `warning` / `danger` + `label` | 76% verde · 49% âmbar · 38% vermelho, sem CSS local de pílula |

---

## Onda 4 · Formulários (editor de ingredientes, ordem, configurações)

| # | Está hoje | Deve ficar | Teste de aceite |
|---|---|---|---|
| F-19 | `.mfg-fld` + `.mfg-inp` + `.mfg-inp.sel` + `.mfg-check` | `Input` (L4481) · `Textarea` (L4511) · `Select` (L4546) · `Checkbox` (L2511) · `Switch` (L6526) `[DS]` | os 2 switches de Configurações são `Switch`; `grep "mfg-inp"` volta 0 |
| F-20 | `input[type=number]` com `step=0.001` na quantidade do ingrediente | `Input type="number"` | ⚠️ **a assinatura não tem `min`/`max`/`step`** — sem eles a quantidade perde o passo de milésimo. `[PAUTA]` proposta: `step`/`min`/`max` no `Input`. Sem isso, o campo do editor fica local, declarado |
| F-21 | Avisos em `<p class="mfg-note">` / `.mfg-err` (estoque insuficiente · permissão só leitura · quantidade bloqueada) | `Alert` `[DS]` (L738) — `tone` info/success/warn/danger, `title`, `children`, `action`, `onClose` | os 3 avisos viram `Alert`; o aviso de estoque leva `action` com a ponte para Compras |
| F-22 | `title="custo congelado na data da produção"` (tooltip nativo) | `Tooltip` `[DS]` (L6948) — `content`, `side`, `kbd`, acessível por foco | passar o teclado no sufixo `fix` mostra o balão (hoje só mouse, e só depois de 1 s do SO) |

---

## Onda 5 · Ordens e Relatório

| # | Está hoje | Deve ficar | Teste de aceite |
|---|---|---|---|
| F-23 | `input[type=date]` cru nos filtros De/Até (ordens + relatório) | `DatePicker` `[DS]` (L3360) — calendário PT-BR dd/mm/aaaa. Se quiser presets Dia/Semana/Mês: `PeriodBar` (L5483) | os dois campos abrem o calendário do DS |
| F-24 | `.mfg-bar-mini` (barra de % do período) | `Progress` `[DS]` (L5796) — `variant='bar'`, `value`, `max`, `tone`, `formatValue` | a barra sai do CSS local |
| F-25 | Rodapés de total (`.mfg-foot`) | **o DS não tem** rodapé de tabela | fica na tela, declarado como contorno no handoff `[PAUTA]` |

---

## Onda 6 · Folha de prova PT-07 (ficha técnica impressa)

O DS tem o grupo **Print-craft** inteiro, e a folha desenha os quatro à mão em CSS de impressão:

| # | Está hoje | Deve ficar |
|---|---|---|
| F-26 | `.mfg-reg` (SVG local da mira) | `RegistrationMark` `[DS]` (L6147) — `size`, `color`, `strokeWidth` |
| F-27 | `.mfg-sheet .cm.tl/.tr/.bl/.br` (4 cantos em `border-width`) | `ProofFrame` `[DS]` (L5931) — `cropMarks`, `grid`, `padding`, `radius` |
| F-28 | `.mfg-sheet-cotas .ln` (linha de medida em `border`) | `Dimension` `[DS]` (L3642) — `value`, `orientation`, `surface` |
| F-29 | `.mfg-sheet-f .strip` + as 4 tintas cruas `#00AEEF #EC008C #FFF200 #231F20` | `ProofStrip` `[DS]` (L6023) — `kind='cmyk'` e `kind='density'` |

**Ressalva a medir antes de fechar a onda:** os quatro são componentes de tela, com cor por token
do cockpit; a folha é papel, e `[DS]` o cockpit não publica paleta de impressão (ADR `0413`, §12 do
handoff). Rodar esta onda exige **imprimir uma folha e conferir** — se o token não sobreviver ao
`@media print`, o resultado é a medição, não uma correção por dentro do componente.

---

## Onda 7 · Fechar o laço nos documentos

| # | O quê |
|---|---|
| F-30 | Reescrever no `handoff_fabricacao/README.md`: §14 (a frase "não consome nenhum componente do DS" morre), §10 (as 4 cores cruas de `rgba` caem para as que sobrarem depois do F-11/F-14/F-15), §12 (ADR `0412` precisa ser **remedida**: `PageHeaderTabs` navega por `href`, mas o `TabBar` do espelho é de estado — a ADR pode estar descrevendo só o alvo), §15.3 (mapa componente→arquivo real) |
| F-31 | Levar para `pauta-design-system.md` o que o DS não cobre: `Input` sem `ref`/ícone/`step` (F-12, F-20), `Breadcrumb` só com `href` (F-06), `StatusBadge` sem `producao` (F-17), sem seletor de categoria (F-13), sem rodapé de tabela (F-25), sem `Slider`/range para o simulador de ±% da aba Insumos |
| F-33 | **Defeito de origem achado na medição:** `components/StatusBadge/StatusBadge.d.ts` (projeto do DS) declara **8** `StatusKind` — `intercorrencia · prioridade · payment · rep · sla · atendimento · frescor · tipo`; o `MAP` do componente implementa **11**, com `documento` · `fiscal` · `os` a mais (`StatusBadge.jsx` L41-85; espelho compilado L6389-6470), e o README do DS também lista os 11. O tipo está atrás da implementação — quem tipar em TS perde 3 domínios. Vai para a pauta como **D**, não se corrige aqui |
| F-32 | Enxugar `manufacturing-page.css` — listar classe a classe o que morre e o que fica; o que ficar entra no handoff **declarado como contorno**, com o motivo |

---

## O que o DS não cobre — fica na tela, e é para declarar, não esconder

1. **Grupo de ingredientes** (cabeçalho com nome + contagem + subtotal, linhas editáveis de 6
   colunas, `＋ Ingrediente em <grupo>`) — não há componente equivalente. É a peça central do editor.
2. **Simulador de variação de preço** (`input[type=range]` −30%…+60%) — não há `Slider` no espelho.
3. **Campo de dinheiro pt-BR** — o alvo tem `ui/numeric-input-ptbr.tsx`; o espelho do DS não.
4. **Rodapé de total de tabela** e **sufixo `fix`** — composição da tela.
5. **Ícones**: a família usa `window.I` (espelho de 22 glifos); o DS manda Lucide inteiro. Contorno
   já declarado no §14 do handoff — permanece, e permanece declarado.

---

## Perguntas antes de rodar qualquer onda

1. **F-09 — `DataTable` ou `DataTablePro`?** O `DataTable` (L2894) faz ordenação e seleção por
   estado externo (`sortKey`/`sortDir`/`onSort`/`selectedIds`), que é exatamente o que a tela já
   tem — **mas é um `<table>` sem cabeçalho fixo e sem largura mínima**, e a tela depende de
   `position: sticky` no `thead` e de `min-width` 960/1100/900/940 para rolar na horizontal. O
   `DataTablePro` (L3099) tem header fixo, resize e densidade, **mas pede `height`** e faz
   ordenação/seleção por dentro (a tela perde o controle do estado). Qual dos dois?
2. **F-13 — chips de categoria.** Viram `Select` do DS (perde a leitura de um clique), ficam locais
   como contorno declarado, ou vale propor um `ChoiceChips` à pauta?
3. **F-17 — situação da ordem.** O DS já destina `documento` à OP; falta só `finalizada`. Uso
   `kind="documento"` com `value="rascunho"` + `label` livre na finalizada, ou proponho a chave
   `finalizada` no `documento` e espero?
