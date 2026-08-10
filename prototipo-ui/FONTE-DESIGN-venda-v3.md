# Venda V3 — fonte de design versionada

Âncora de design de **`/sells/create-v3`** ([`CreateV3.charter.md`](../resources/js/Pages/Sells/CreateV3.charter.md)).
Cockpit *"Venda — Guia de Produção"*, camada 4 do sistema de design.

## Proveniência

| | |
|---|---|
| Origem | handoff `design_handoff_cadastro_venda`, projeto de design Oimpresso `019e2365` |
| Data do handoff | 2026-08-06 |
| Versionado aqui em | 2026-08-10 |
| Por que aqui e não em `design-oimpresso/` | o gate **required** `Ancora de design nao-shell` resolve `related_prototype` **dentro de `prototipo-ui/cowork/`** (`anchor-content-check.mjs::anchorRelPath` corta o path até `cowork/`). Uma âncora fora daí classifica como `MISSING` e derruba o gate. |

Antes deste import, o charter declarava a âncora em
`prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx` — path que **não existia
no repo nem no disco**, confirmado por quatro oráculos independentes (`ancora.mjs`,
`git ls-files`, `ls`, `git check-ignore`). Era âncora fantasma.

## O que ESTÁ aqui — e o que não está

✅ **12 fontes de tela** (`sells-*.jsx` + `sells-data.js`) e **8 CSS** das camadas 1–4.
Line endings normalizados para LF no import (o zip vinha CRLF).

⚠️ **A camada 0 (Design System) NÃO veio no handoff.** Medido sobre o zip: `_ds/colors_and_type.css`,
`_ds/styles.css` e `_ds/_ds_bundle.js` não existem em arquivo nenhum do bundle. Consequência:
[`cowork/venda-v3/index.html`](cowork/venda-v3/index.html) carrega, mas sem token de cor e sem ícone (`window.I`).

**Isto não é defeito do import — é o recorte do handoff.** Estes arquivos servem como
**fonte de design de onde as ondas 2–6 derivam**, não como preview executável. Para ver o
cockpit renderizado, use o projeto de design de origem.

Por isso **não há entrada no `.claude/launch.json`**: uma config que servisse este diretório
prometeria um preview que não renderiza — afordância anunciada e não implementada é
a classe [LC-15](../memory/LICOES_CODE.md) do ledger.

## CSS — cascata por camada

A ordem dos `<link>` no HTML **é o contrato**: camada de cima nunca é sobrescrita
por camada de baixo.

| # | Camada | Arquivo | Responsabilidade |
|---|---|---|---|
| 0 | DS | _(ausente — ver acima)_ | tokens e componentes do design system |
| 1 | Fundações | `01-fundacoes/css/tokens-tema-escuro.css` | dá valor escuro a tokens que o DS declara só no claro (sem token novo) |
| 1 | Fundações | `01-fundacoes/css/ds-correcoes.css` | defeitos medidos de componente do DS |
| 2 | Shell | `02-shell/css/base.css` | reset, link, `code`, scrollbar, foco visível, movimento reduzido |
| 2 | Shell | `02-shell/css/shell-responsivo.css` | sidebar/header/conteúdo abaixo de 640px, alvo de toque em `main` |
| 3 | Padrão | `03-padroes-tela/css/campos.css` | campo com afixo (`.dsfa`) |
| 3 | Padrão | `03-padroes-tela/css/tabela-dados.css` | coluna de ações fixa (`.tabela-acoes-fixa`) |
| 3 | Padrão | `03-padroes-tela/css/overlays.css` | Drawer (PT-02) e Modal (PT-04) |
| 4 | Módulo | `css/venda.css` | `.venda-grid`, `.venda-finalizador`, `.venda-acoes`, `.imp-linha` |

**Regra de decisão** — antes de escrever CSS, pergunte onde ele pertence:

- vale para **qualquer** tela do cockpit → camada 2;
- vale para **qualquer** tela que use este padrão (lista, formulário, overlay) → camada 3;
- é **correção de defeito do DS** → camada 1 + ADR (para morrer quando a origem for corrigida);
- só faz sentido **nesta família de telas** → camada 4.

Se a regra usa uma classe do módulo (`.venda-*`), ela é camada 4 por definição.

## JS — ordem de carga e responsabilidade

Cada arquivo exporta para `window` no fim (escopos Babel não se comunicam).

| Arquivo | Papel | Onda |
|---|---|---|
| `sells-data.js` | dados de cena e catálogo: clientes, produtos, pessoas comissionáveis, FSM, permissões, CU do SDD. **Fonte única** — nenhuma lista de domínio nasce em arquivo de UI | — |
| `sells-ui.jsx` | primitivos locais: `Sec`, `Grid`, `Lbl`, `Money`, `Campo`, `DataCampo`, `Pill`, `Trilho`, `Icon`, `tomFg` | — |
| `sells-create.jsx` | **tela de venda (nova e edição) — a âncora** | 1 ✅ |
| `sells-lancamento.jsx` | lançamento do item (medidas, valores, execução) + consulta de produto | 1 ✅ |
| `sells-entrega.jsx` | entrega, frete, volumes, endereço alternativo | 2 |
| `sells-parcelas.jsx` | geração e edição de parcelas | 3 |
| `sells-item-detail.jsx` | drawer de detalhe do item (7 abas, tributação com DIFAL) | 4 |
| `sells-comissao.jsx` | modelo de comissão: beneficiários, base, regra, gatilho, rateio por parcela | 5 |
| `sells-colunas.jsx` | catálogo de colunas do grid + modal de escolha/ordenação | 6 |
| `sells-fsm.jsx` | máquina de estados da venda: `SituacaoVenda`, `CancelarVenda` | 1 ✅ |
| `sells-telas.jsx` | demais telas da família: lista, ficha, caixa, cotações, rascunhos, assinaturas | **não é onda** — Non-Goal do SDD |
| `sells-app.jsx` | shell: sidebar, header, navegação entre telas | — |

A coluna **Onda** mapeia para o plano de portes do preview. `sells-telas.jsx` desenha telas
*vizinhas* (assinatura, devolução, POS rápido, cotação, impressão), que o SDD marca como
Non-Goal desta tela — elas têm rota própria.

## Convenções aplicadas

- Copy visível em **PT-BR**; chaves de dado e `data-*` em inglês.
- Cor **sempre** por token (`var(--*)`) ou `color-mix` sobre token — hex e OKLCH
  literal em arquivo de módulo são anti-padrão AP1.
- Espaçamento na grade 4/8.
- `localStorage` sempre com prefixo `oimpresso.`.
- Sem emoji na UI; ícones via `Icon` (lucide).

## Documentos irmãos

- **SDD** — `memory/requisitos/Sells/SDD-tela-venda-v1.0.md`. O handoff trazia uma cópia;
  medido byte a byte (normalizando line ending), é **idêntica** à já versionada. Não foi
  duplicada.
- **Contrato da tela** — [`CreateV3.casos.md`](../resources/js/Pages/Sells/CreateV3.casos.md).
- **RUNBOOK** — `memory/requisitos/Sells/RUNBOOK-create-v3.md`.

## Nota sobre o gate de âncora

`anchor-content-check` conta ocorrências do nome do módulo (`sells`) no **corpo** do arquivo
apontado. `sells-create.jsx` tem **0** — a fonte é escrita em PT-BR (`venda`, 42 ocorrências),
e `sells` existe só no nome do arquivo. O veredito é `NO-MODULE`, que é **warn, não hard-fail**
(`podre` = `MISSING`/`SHELL` apenas), então não derruba o required.

A âncora aponta para `sells-create.jsx` porque **é a tela**. Apontar para `sells-data.js`
(39 ocorrências de `sells`) deixaria o gate verde e a âncora errada — otimizar a métrica em
vez do fato.
