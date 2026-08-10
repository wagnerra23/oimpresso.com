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

⚠️ **Faltam 4 arquivos, e o entry não renderiza sem eles.** Medido sobre o zip:

| ausente | o que é | sem ele |
|---|---|---|
| `_ds/_ds_bundle.js` | os 45 componentes do DS (`window.OfficeImpressoDesignSystem_d7f886`) | `TypeError: … reading 'Button'` no destructuring de [`sells-ui.jsx:2`](cowork/venda-v3/sells-ui.jsx) |
| `_ds/colors_and_type.css` | tokens de cor e tipografia | sem `--accent`/`--surface`, fonte cai pra Times New Roman |
| `_ds/styles.css` | entry do DS (só um `@import` do anterior) | — |
| `sells-roteiro.jsx` | define `CuDrawer` (drawer dos CU) | `ReferenceError: CuDrawer is not defined` em [`sells-app.jsx:62`](cowork/venda-v3/sells-app.jsx) |

**Consequência, medida no browser:** [`cowork/venda-v3/index.html`](cowork/venda-v3/index.html)
**não renderiza — tela em branco**, `#root` com 0 caracteres.

### Como VER o protótipo assim mesmo (receita reproduzida em 2026-08-10)

O DS vive no projeto **"Office Impresso — Design System"** do `claude.ai/design`
(`019dd02f-d2d0-7ba6-a57f-24b3ddd073ac`), legível por `DesignSync` — leitura é livre.
Passos, todos fora do repo (scratchpad):

1. `DesignSync{get_file _ds_bundle.js}` — **atenção: o arquivo tem ~265 KB e a tool corta em
   256 KiB** (`truncated: true`). O corte leva junto o epílogo que publica os componentes.
2. Fechar o que o corte deixou aberto: descartar o bloco `try` incompleto do fim, reconstruir
   o epílogo (`Object.assign(__ds_ns, __ds_scope)` — cada bloco registra em `__ds_scope`) e
   fechar o IIFE externo aberto na linha 3.
3. **Alias de namespace:** o handoff foi construído contra `OfficeImpressoDesignSystem_d7f886`;
   o DS vivo é `OfficeImpressoPontoWR2DesignSystem_019dd0` (sucessor). Os 33 componentes que a
   tela usa existem nos dois — medido, `faltando: []`.
4. Tokens: em vez do CSS do DS, servem os do próprio repo —
   `resources/css/tokens/_generated-{foundations,cockpit}-light.css`. Confere:
   `--accent` deve dar `oklch(0.55 0.15 295)` (o roxo da ADR 0190) e a fonte, IBM Plex Sans.
5. `CuDrawer` stubado como `() => null` (é documentação, não a tela).

Resultado: renderiza. A **lista** de vendas abre primeiro; o cadastro é o botão "Nova venda".

> 📌 **Duas erratas desta sessão, registradas e não apagadas.**
> **(1)** Escrevi que o entry *"carrega, mas sem token de cor e sem ícone"* — inferência a
> partir de ler quais arquivos faltavam. Medido, é pior: não carrega nada.
> **(2)** Escrevi que a tag `sells-roteiro.jsx` era **órfã** e a removi do `index.html`,
> "provado" por um grep de `Roteiro` que só achou texto dentro de um dado. O grep procurava o
> nome do **arquivo**; o símbolo que ele exporta é **`CuDrawer`**, e sem ele o app não monta.
> A tag foi restaurada. Nos dois casos o erro é o mesmo: medir a fonte errada e chamar de prova.

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
