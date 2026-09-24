repo: wagnerra23/oimpresso.com
branch: main
path: resources/js/Pages/Produto/Unificado

## Last sync
date: 2026-09-01T17:53:00Z
tree: f026223995a3
escopo desta leitura: design system (resources/css/**, memory/decisions/**, memory/requisitos/_DesignSystem/**)

### Updated in this project
- **Varredura do DS no `main` a pedido: três atualizações do Wagner posteriores à sync de 28/08, todas de FORMA, nenhuma de token.** ADR UI-0028 (28/08) — os 8 `--sb-*` do sidebar passam a ser os do protótipo, hue **295** (`cockpit.css` L193-200), medindo 47 divergências shell↔protótipo; ADR UI-0029 (ratificada 31/08) — **protótipo Cowork é soberano na FORMA** sobre ADR UI, com cadeia de precedência bifurcada e residual "não enforçável hoje" declarado; ADR 0386 (31/08) — **âmbar escopado da Oficina revogado**, roxo 295 é a única identidade de chrome. Fora do DS mas do mesmo lote: ADR 0377 (append-only de ADR canon admite exceção por label `adr-body-edit-W`) e a emenda [W] de **01/09** à ADR 0315 (design→git deixa de ser proibição absoluta; segue proibido para o **Design System**).
- Importados para `importado_ds_git/`: `css/cockpit.css` (atualizado — traz o hue 295 e o CSS novo de ghosts no sidebar, `.sb-item.sb-sub` · `.sb-ghost-count` · `.sb-ghost-more` · slot `.sb-item-end`/`.sb-kbd` do atalho `G X`, ausentes na cópia de 27/08), `css/tokens/{CHANGELOG.md,version.json}` e `adr/` com as três ADRs acima.
- Medido, sem ação: o DS espelhado deste projeto (`_ds/…/colors_and_type.css` L308-315) e os `_generated-cockpit-*.css` do próprio `main` seguem em **hue 240** — divergência que a UI-0028 assume como dívida e remete a ADR própria do DS. Registrado em `pauta-design-system.md` como pergunta ao dono do DS; nenhuma cor desta tela foi alterada.
- Pacote de tokens **parado na v1.1.0 / 26/08** (308 tokens, fingerprint `d797f34a…`): nada novo em rampa de tipo, cores funcionais ou fundações desde a última sync.
- Segunda passada no `main`, agora nos dois domínios que a primeira não abriu (`colunas-dominio.ts`, `item-fiscal-dominio.ts`) — o grid de 30 colunas do protótipo é mais rico que o da produção, mas as **defesas** são de lá.
- `venda-v3.jsx` · imposto do item: ICMS com CST que não tributa (40/41/60/04) passa a valer **zero** — a aba mostrava 18% ao lado do próprio aviso de incoerência. Predicado `cstNaoTributa` único, com o guard do `102` (3 dígitos, começa com `10`), servindo o cálculo e a validação.
- `venda-v3.jsx` · preferência de colunas: `carregarColunas` ganhou o `sanearColunas` de quatro defesas (não-array, chave extinta, chave repetida, coluna fixa ausente reinserida). Antes, um `localStorage` com `["desc"]` deixava o grid sem produto, quantidade e total, e chave repetida duplicava a coluna.
- `venda-v3.jsx` · coluna fixa não sai do lugar nem cede lugar (`moverColuna`): setas desabilitam, `draggable` desligado nas fixas.
- Conferido igual nos dois lados, sem alteração: DIFAL (piso zero no destino + `invertido`), ordem padrão com `R$ total` por último, `abaDaAcao` (lupa → geral, Impostos → tributação), validações de formato NCM/CFOP/CEST/GTIN/cBenef.

### Sync 2026-08-28T12:22:00Z
- `venda-v3.jsx`: importadas do `main` as correções que a produção (`/sells/create-v3`) tem e o espelho do protótipo não tinha — todas de `resources/js/Pages/Sells/_components/v3/`.
- `calculo-item.ts`: área do item deixou de passar por `submitSafe` (guard de dinheiro, não de medida) e a quantidade faturada passou a arredondar a 4 casas — item fino (0,50 × 0,004 m) não zera mais nem trava o botão "Adicionar à venda". Piso da alçada virou constante `PISO_DA_TABELA`.
- `numeros.ts`: `fmtQtd` — quantidade exibe 2 casas, e expande a 4 só quando o valor não é zero mas arredondaria pra zero.
- `parcelas-dominio.ts`: `dia0` monta `yyyy-mm-dd` pelo construtor local (o parse UTC jogava todo vencimento um dia pra trás a oeste de Greenwich); "mês fechado" passou a usar `mesmoDiaNoMes` com grampo no último dia do mês, em vez de somar 30 dias; `ratear` e a quantidade de parcelas ganharam o saneamento (`Math.max(1, floor)`, teto 48).
- `cliente-consulta-dominio.ts`: consulta de clientes casa sem acento e por dígito do documento — `83169623` e `itajai` agora acham, que é o que o placeholder já prometia.

### Sync 2026-08-27T22:15:00Z
- Importado o design system do git (SSOT) em `importado_ds_git/`: `css/tokens/` (DTCG `base`/`semantic` + 6 CSS gerados), `foundations.css`, `inertia.css`, `cockpit.css`, `components.json`, `DESIGN.md`.
- Importados os protótipos do `prototipo-ui` e o bundle `public/cowork-preview/erp-shell-v2/` (71 arquivos, em `erp-shell-v2/`) — contém `Produto Unificado.html` + `produto-app/data/icons.jsx`, o par desta tela.
- Medido: a pasta local do Wagner está atrás do `main` (sem `resources/css/tokens/` e `foundations.css`; com 6 CSS que o `main` não tem).
- Defeito de origem: `prototipo-ui/prototipos/clientes/` referencia `chat-icons.jsx` e `chat-sidebar.jsx`, que não existem em nenhum lugar do diretório — o bundle não abre.

### Sync anterior (2026-08-26T13:05:39Z)
- Lidos os documentos de comportamento da tela no `main`: `Index.charter.md` e `Index.casos.md` — é onde o comportamento é normativo (charter) e verificável (UCs).
- Modelo de rolagem do protótipo alinhado ao charter: a **página** rola; o cabeçalho da tabela prende no topo da janela (`thead th` sticky, sem `overflow` em nenhum ancestral).
- Gerado `handoff_produtos_consulta/contexto/patch-charter-casos-2026-08-26.md`: blocos de substituição para o charter (saídas do drawer, visibilidade de KPI, anti-padrão do `auto-fit`, painel, rolagem, entrada de Histórico) e dois UCs novos para o `casos`.
- Registrada na pauta do DS a exceção assinada: o modelo de rolagem do template PT-01 não se aplica a esta tela.

## Screen map
| Tela | Arquivos de origem |
| --- | --- |
| venda-v3.jsx (Venda V3) | resources/js/Pages/Sells/CreateV3.tsx · CreateV3.charter.md · _components/v3/{numeros,calculo-item,parcelas-dominio,comissao-dominio,entrega-dominio,cliente-consulta-dominio}.ts · prototipo-ui/cowork/venda-v3/* |
| Consulta de Produtos.dc.html | resources/js/Pages/Produto/Unificado/Index.tsx · Index.charter.md · Index.casos.md · _components/{Colunas,KpiFiltros,FiltroTrigger,DetalheProduto,Disponibilidade,MiniaturaProduto,Observacao,BulkBar,Mono,SubTelas,catalogo}.tsx · routes/web.php:450 |

## Sync history
- 2026-08-28T12:38:00Z — segunda passada nos domínios de coluna e item fiscal (`colunas-dominio.ts`, `item-fiscal-dominio.ts`); ICMS com CST que não tributa passa a valer zero; `sanearColunas` com quatro defesas; coluna fixa imóvel.
- 2026-08-19T11:30:00Z — recriação inicial da tela `/products/unificado` como Design Component (PageHeader + abas + KPI-filtros + toolbar + tabela + drawer; gate de custo/margem por `perfil`).
