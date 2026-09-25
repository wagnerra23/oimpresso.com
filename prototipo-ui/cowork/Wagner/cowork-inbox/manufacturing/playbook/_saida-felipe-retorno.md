---
sessao: "retorno-felipe"
titulo: Retorno do Felipe — o que existe só na cópia de trabalho e o que foi recebido do Wagner
dono: "[F]"
data: 2026-09-25
---
# Retorno do Felipe · Fabricação (25/09/2026)

**Modelo combinado (Felipe, 25/09/2026):** o protótipo do Wagner é a **fonte**. A pasta
`prototipo-ui/cowork/Felipe/` é a **cópia de trabalho** do Felipe (veio do zip do Wagner) onde ele
faz as alterações de design da Fabricação. A sincronia é nos dois sentidos: o que o Wagner muda lá
chega aqui; o que o Felipe muda aqui vai para o Wagner — para os protótipos não divergirem.

> ⚠️ **Sobre a thread 03 (aposentar `cowork/Felipe/manufacturing-*`):** a pasta do Felipe é a cópia
> em que ele está trabalhando. Antes de executar a 03, combinar com o Felipe — apagar os 7 arquivos
> encerra a cópia de trabalho. Este recibo já é a lista "só no Felipe" que a 03 pede.

## 1. Recebido do Wagner (handoff 41 / #7979) → aplicado na cópia do Felipe

Aba **Ordens de produção**, escrita com os componentes do DS da cópia do Felipe (ondas A/B):

| # | Item do PEDIDO | Estado na cópia do Felipe |
|---|---|---|
| 1 | 4 KPIs: Total · Finalizadas (filtra) · Pendentes · Valor total | aplicado — `KpiCard` ×3 + `KpiFilterCard` "Finalizadas" (o `KpiCard` do DS não tem ícone; só o que filtra leva) |
| 3 | Botão Limpar com filtro ativo | aplicado |
| 4 | Vazio "Nenhuma produção no filtro" / "Sem produções cadastradas" + "Limpar filtros" | aplicado |
| 5 | Situação à esquerda | aplicado |
| 6 | Linha não abre detalhe | aplicado |
| 7 | Sem ordenação por coluna | aplicado |
| 8 | Sem paginação | aplicado (`DataGrid pagination={false}`) |
| 9 | Datas vazias = todas | aplicado |

Medido no navegador: KPIs 6 · 5 · 1 · valor total; "Finalizadas" liga o mesmo filtro do checkbox
(6 → 5 linhas); Limpar aparece com filtro e some depois; clique na linha não abre drawer; com
"De" = 01/09/2026 a lista esvazia e mostra o vazio "no filtro".

**Não aplicado — conflito, aguarda decisão:** item 2 (título/subtítulo). Na cópia do Felipe o
título é "Fabricação" em todas as abas e o subtítulo é só "N receitas · M ordens de produção" (a
frase "custo recalculado…" saiu de todas as abas porque o `PageHeader` do DS corta o subtítulo em
56ch; ela continua no drawer da receita). No Wagner: "Produção" na aba Ordens e "Manufacturing"
nas demais, com a frase nas demais abas.

## 2. Existe só na cópia do Felipe → levar para o protótipo do Wagner

Todas medidas no navegador; detalhe nos commits do PR e em `cowork/Felipe/pauta-design-system.md`.

- **DS nas ondas A/B:** `PageHeader`, `TabBar`, `KpiCard`/`KpiFilterCard`, `DataGrid`, `Drawer`,
  `PresenterMode` e primitivos de impressão no lugar do markup local.
- **Coluna vazia à direita da tabela:** `.mfg-grid` colidia com `mockup-pages.css` ("MANUFACTURING —
  BOM", grade 1.5fr 1fr). Reset escopado em `.mfg-root`.
- **Botões do rodapé cortados nas telas de edição** (Editar ingredientes, Editar ordem): um `*/`
  dentro do comentário de cabeçalho de `manufacturing-page.css` fechava o comentário e o navegador
  descartava `.mfg-root{display:flex}`. Medido em 960×540 a 1920×1080.
- **Drawers:** título na linha do ×, como o `Sheet` do produto; tabela "Receitas afetadas" sem cortar
  a coluna Margem.
- **Faixa de 15px à direita das grades** (`scrollbar-gutter:stable` do `DataGrid`).
- **Cabeçalho alinhado com abas e cartões** (20px).
- **Ficha com custo / Via de produção:** papel saía escuro no tema escuro; total do grupo não
  alinhava com "Subtotal".

## 3. Achado no protótipo do Wagner (fora da Fabricação)

- **Menu Aparência (sidebar.jsx + styles.css):** `.um-vibe.active .label{color:var(--text)}` — no
  tema claro `--text` é escuro e o menu é sempre preto (UI-0023): o nome da opção ativa some. Na
  cópia do Felipe foi trocado para `var(--sb-text-hi)`.

## Decisões pendentes

- **D-RET-01 · Título/subtítulo da Fabricação** (item 2 acima): "Fabricação" fixo × "Produção"/
  "Manufacturing" por aba; frase do custo fora do cabeçalho × presente nas abas que não são Ordens.
- **D-RET-02 · Thread 03:** a cópia de trabalho do Felipe fica ou é aposentada.
