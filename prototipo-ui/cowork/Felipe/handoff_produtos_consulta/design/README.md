# Módulo Produtos — arquitetura dos arquivos

Camada 4 do sistema. Consome as camadas 1–3 e **não cria** token, cor, tamanho de fonte nem
padrão de tela novo. Nenhuma cor **inventada**; o único literal OKLCH da tela é transcrito do
`StatusBadge` do DS (citação, não cor crua).

## Onde a tela vive

| Arquivo | Papel |
|---|---|
| `../../Consulta de Produtos.dc.html` | a tela inteira: template, lógica e estilo — **na raiz** (21/09/2026) |
| `../../support.js` | runtime do Design Component (template → React); não editar — **na raiz** |
| `../../_ds/wagner-…-49a36f76-…/_ds_bundle.js` | camada 0 · componentes do DS — **na raiz** |
| `../../_ds/wagner-…-49a36f76-…/colors_and_type.css` | camada 0 · tokens e tipografia — **na raiz** |
| `../../_ds/wagner-…-49a36f76-…/styles.css` | camada 0 · classes do DS — **na raiz** |
| `../../_ds/…/assets/fonts/*.woff2` | IBM Plex Sans/Mono, self-hosted — **na raiz** |

### Como abrir

Abra `Consulta de Produtos.dc.html` no navegador. Sem `npm install`, sem porta, sem build — o
bundle do DS e as fontes vêm no pacote, então funciona offline e sem rede.

## Componentes do DS usados

`PageHeader` · `TabBar` · `KpiFilterCard` · `DropdownMenu` · `Input` · `Button` · `DataTable`
(com `selectable`) · `StatusBadge` · `Tooltip` · `Pagination` · `BulkBar` · `Modal` · `Toast` ·
`Command` (paleta ⌘K) · `Drawer` + `DrawerSection` · `Alert` · `EmptyState` · `Icon`

Nenhum componente do DS foi recriado localmente. Onde o DS não cobria, a tela **contornou** e a
lacuna foi registrada — ver `../contexto/pauta-design-system.md`.

## Contornos em pé (e por quê)

| Onde | Contorno | Registro |
|---|---|---|
| Densidade da tabela | `DataTable` não tem prop de densidade; o modo compacto colapsa *conteúdo* de célula (2ª linha de Produto e de Disponível), não o padding | pauta · P1 |
| Rolagem do drawer | `DrawerSection` não é colapsável; barra fixa de atalhos no topo do painel rola até a seção | pauta · P2 |
| Barra de 1px na `TabBar` | override no `<helmet>` mirando `nav[aria-label="Sub-navegação"]` — não suprimível de fora | ADR 0403 · pauta · D |
| Gatilho de ícone do `DropdownMenu` | forma render-função (o componente acrescenta caret próprio a gatilho-nó) **+** valores do `Button ghost` transcritos, porque o `Button` do DS não repassa `aria-haspopup`/`aria-expanded` | pauta · P2 (§24.2) |
| Lupa no campo de busca da toolbar | `Input` do DS não tem slot de ícone: glyph absoluto no invólucro + `padding-left: 30px !important` no controle (o componente escreve `padding` inline) | pauta · P1 (§24.3) |
| Miniatura sem foto | `package`, por não existir glyph de imagem no pacote | pauta · D |

**Corrigido em 27/08:** as auditorias das §22.5, §23 e §24 do `../README.md` fecharam os valores do
DS e do template que a tela havia reescrito — tipografia das células mono e da 2ª linha da célula
Produto, `--destructive` → `--color-destructive`, `--accent` → `--color-primary` nos 4 usos da tela,
piso de 10,5px, `gap`/régua/contagem/largura da toolbar, `<kbd>` do `/`, e o hover do gatilho `⋯`.
O que **não** foi trocado, com motivo medido: `--warn` (não é alias de `--color-warning` — valores
diferentes) e a prioridade `selected > archived` do `DataTable` (é do componente).

**Corrigido em 25/08:** a moldura da tabela estava como decisão da tela (borda reta, sem raio) quando
o template PT-01 já fixava `--radius-lg` + sombra de 1px. Não era contorno — era template não lido.
Ver a Lei 16 do manual e a marca de procedência **[TPL]**.

## Dívida de empacotamento

O manual de handoff pede o protótipo dividido em `<mod>-data.js`, `<mod>-ui.jsx`, um arquivo por
peça grande e **quatro camadas de CSS**, com o HTML atuando só como manifesto. Esta tela **não
atende a essa regra**: é um Design Component único, com estilos inline e o catálogo de cena
declarado na própria classe de lógica.

Isso é consequência do formato, não descuido: no DC o estilo inline é o que faz a tela pintar
enquanto o arquivo carrega, e classes CSS externas atrasariam a primeira pintura. O custo, sem
constrangimento:

1. **Estilo sem camada.** Nenhuma regra desta tela tem endereço na cascata; não é auditável
   varrendo arquivos `.css`.
2. **AP1 verificado por leitura, não por instrumento** — hoje limpo, mas sem varredura automática.
3. **Nada morre sozinho.** Correção de DS aplicada na origem não apaga o ajuste equivalente feito
   inline.

Condição de saída, na ordem, se o alvo for a arquitetura em camadas: (a) extrair o catálogo para
`prod-data.js`; (b) extrair `03-padroes-tela/css/tabela-dados.css` e `overlays.css`; (c) separar o
painel lateral em peça própria; (d) mover o override da `TabBar` para
`01-fundacoes/css/ds-correcoes.css`, onde ele pode morrer quando a ADR 0403 for aplicada.

**Na reimplementação isso deixa de ser dívida:** no codebase alvo (Inertia + React + Tailwind) o
estilo vira classe utilitária e componente, e a cascata em camadas é a do projeto.

## Shell — vem do template, não desta tela

Estrutura, tokens de densidade, moldura da tabela e arquitetura de rolagem são cópia de
`templates/pt-01-lista` (a tela de índice canônica do DS). Diagrama e valores em
`../README.md` §3.1.1. Duas armadilhas registradas ali, ambas de silêncio — falham sem erro no
console:

1. `min-height:100vh` no shell só funciona no PT-01 porque ele passa `height` ao `DataTablePro`;
   o `DataTable` não tem essa prop.
2. Limitar o shell em vez de dar piso à região de dados inverte a garantia do template e a lista
   colapsa a 1 linha em janela curta.

## Convenções aplicadas

1. Copy visível em PT-BR; chaves de dado em inglês (`stockQty`, `price`, `cost`).
2. Cor sempre por token ou `color-mix` sobre token — zero hex e zero OKLCH literal **inventado**.
   Literal transcrito de componente do DS é citação, e é obrigatório onde o componente traz um
   (ver patch de cor §6 e §8).
3. Espaçamento na grade 4/8.
4. Ícones **só do pacote do DS** (`window.Icon`), sem paths desenhados à mão.
4b. Padding de slot sempre `var(--d-cpad-x)` / `var(--d-cpad-y)`, nunca px cravado — é o que faz a
   densidade valer para a página inteira e não só para a tabela.
5. Sem emoji na UI.
6. Números com `font-variant-numeric: tabular-nums`.
7. `localStorage` com prefixo do produto: `oi.produtos.recorte.v1` guarda aba, KPI, busca, ordem,
   itens por página, colunas ocultas, densidade e recentes.
8. Derivados nunca em estado: custo do kit, saldo vendável, montáveis, margem e totais são
   calculados a cada render.
