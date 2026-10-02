---
sessao: "00"
titulo: "Recibo — PUXAR as 12 Pages vivas de Repair para o protótipo (uma rota rep-* por Page)"
autor: "[CL]"
data: 2026-10-02
base: origin/main 13bc079894
thread: 01-puxar-vivo.md
veredito: "entregue — 14 rotas rep-* (12 Pages + Repair/Show + DeviceModels/Create e Edit), as 15 renderizadas sem erro no espelho servido; só repair-page.jsx tocado."
---

# _saída 00 · PUXAR o vivo de Repair

> A ficha dá esta thread ao [CC]. Quem executou foi o [CL], a pedido do agente-pai. Nada subiu ao
> Cowork: escrever no Claude Design exige opt-in do dono (ADR 0315). Até a subida, o check
> `espelho — mexeu depois de verificar` acusa o `repair-page.jsx`. Isso é esperado.

## A causa das 6 medidas iguais

Antes desta thread, só 7 abas tinham rota (`rep-producao`, `rep-folhas`, `rep-reparos`,
`rep-status`, `rep-modelos`, `rep-config`, `rep-portal`). A rota `repair` abria a última aba
guardada no localStorage (`oimpresso.repair.aba`). Uma medida feita pela rota `repair` fotografa,
então, a aba que estiver no storage, e foi isso que gerou o mesmo `design.json` (blob
`86af1436070d`) para seis telas.

Agora cada rota fixa a aba. Nas Pages de detalhe e de formulário, a rota também abre o drawer
correspondente com uma folha ou um modelo fixo, para a medida ser reprodutível. A tabela fica
exportada em `window.RepRotas`.

## Mapa rota `rep-*` ↔ Page

| rota | Page Inertia | o que monta |
|---|---|---|
| `rep-painel` | `Repair/Dashboard/Index` | aba Painel |
| `rep-reparos` | `Repair/Index` | aba Reparos |
| `rep-reparo` | `Repair/Show` | Reparos + drawer "Venda de reparo" da folha 11 |
| `rep-producao` | `Repair/ProducaoOficina/Index` | aba Produção (kanban) |
| `rep-folhas` | `Repair/JobSheet/Index` | aba Folhas de OS |
| `rep-folha` | `Repair/JobSheet/Show` | Folhas + drawer da folha 5 (`JS-2026-0405`) |
| `rep-folha-nova` | `Repair/JobSheet/Create` | Folhas + drawer "Nova folha de OS" |
| `rep-folha-editar` | `Repair/JobSheet/Edit` | Folhas + drawer "Editar JS-2026-0405" |
| `rep-folha-pecas` | `Repair/JobSheet/AddParts` | Folhas + drawer "Adicionar peças · JS-2026-0398" |
| `rep-status` | `Repair/Status/Index` | aba Status |
| `rep-modelos` | `Repair/DeviceModels/Index` | aba Modelos |
| `rep-modelo-novo` | `Repair/DeviceModels/Create` | Modelos + drawer "Novo modelo de dispositivo" |
| `rep-modelo-editar` | `Repair/DeviceModels/Edit` | Modelos + drawer "Editar modelo: SureColor S60600" |
| `rep-config` | `Repair/Settings/Index` | aba Configurações |
| `rep-portal` | — (portal do cliente, ainda Blade; thread 04) | aba Portal |

Nenhuma rota nova foi registrada no `app.jsx`. O roteador já manda todo `rep-*` para
`window.RepairPage` (`app.jsx:843`); a vista é escolhida dentro do `repair-page.jsx`.

## Diff por tela — o que o vivo tem e entrou

Fonte: leitura dos 14 `.tsx` em `resources/js/Pages/Repair/` no `main` `13bc079894`. Nenhum
`.tsx` foi editado.

| Page | entrou no protótipo |
|---|---|
| `Repair/Index` | 3 KPIs clicáveis que o vivo tem: Em andamento, Concluídas, Total exibido. Busca "Nº OS, cliente ou nº de série", selects de local ("Todos os locais") e de responsável ("Todos os responsáveis"), "Limpar", chips de status. Colunas OS · Status · Cliente · Aparelho · Série · Resp. · Aberta · Prazo · Total · Pgto. Rodapé "Mostrando X–Y de N" com paginação. Estados vazios "Nenhuma OS no filtro" e "Sem ordens de serviço". A lista passou a ter uma venda por folha; as já faturadas usam o `REPAROS` do legado. **Saíram** os 3 KPIs de faturamento e as colunas Fatura/Folha/Garantia/Saldo, que o vivo não tem. A garantia continua no drawer. |
| `Repair/Show` | Vista nova: drawer "Venda de reparo …" com Detalhes da venda (Status, Pagamento, Data da venda, Prazo de entrega, Aparelho, Nº de série, Defeitos, Valor total, Garantia), Linhas (peças/serviços), Checklist do aparelho, Pagamentos, Timeline e os botões "Via do cliente" e "Editar". |
| `Dashboard/Index` | Nada novo. O protótipo já tem os 3 KPIs do vivo, mais "Ticket médio", e as 5 tendências (status, técnico, marcas, equipamentos, modelos). Só ganhou rota fixa. |
| `DeviceModels/Index` | KPIs Total de modelos, Marcas ativas e Categorias. Filtros Marca ("Todas as marcas") e Categoria ("Todas as categorias") com "Limpar". A coluna "Equipamento" virou "Categoria". Ação "editar" por linha, botão "Novo modelo" e estado vazio "Nenhum modelo cadastrado". |
| `DeviceModels/Create` · `Edit` | Vista nova: drawer `ModeloForm` com Nome do modelo *, Marca, Categoria / Dispositivo e Checklist de reparo (separe itens com \|). Botões Cancelar e Salvar (no Edit, Atualizar). |
| `JobSheet/Index` | Filtros Status ("Todos os status") e Cliente ("Todos os clientes") somados a técnico e local, mais "Limpar". |
| `JobSheet/Show` | Aba "Anexos" no drawer da folha. O vivo tem a seção ao lado de Peças e Timeline. |
| `JobSheet/Create` · `Edit` · `AddParts` | Nada mudou no conteúdo; só ganharam rota. Os formulários moram em `repair-forms.jsx`, fora do prefixo desta thread. |
| `ProducaoOficina/Index` | Contagem "{n} OS · {n} aguardando aprovação". |
| `Settings/Index` | Etiqueta de código de barras, Tipo de código de barras, Problema relatado pelo cliente, Condição do produto, Configuração do produto, Termos e condições, Checklist padrão do reparo. Na impressão: rótulos do cliente, do código do cliente e do documento fiscal, largura e altura da etiqueta (mm). Bloco "Configurações em tela própria" com os botões "Status de OS" e "Modelos de dispositivo". |
| `Status/Index` | Frase "Hoje X de Y status estão em uso." no alerta de exclusão. |

## Ficou fora, e por quê

- **`repair-forms.jsx`** (fora do prefixo). O `JobSheet/Edit` vivo organiza o formulário em abas
  (Cliente · Aparelho · Defeitos · Checklist). O `AddParts` tem a seção "Atualizar status". O
  `JobSheet/Create` tem autocomplete de cliente. Nada disso foi portado.
- **O que o vivo tem e é defeito não entrou.** Ficaram de fora: o texto de desenvolvimento na
  descrição de `Repair/Index` e no painel FSM de `Show`; as seções que sempre mostram o vazio
  (peças/anexos/timeline em `JobSheet/Show`); o drawer da Produção com dados fixos no código; o
  cliente digitado como ID numérico em `JobSheet/Edit`.
- **Sidebar** (`data.jsx`). As rotas novas não viraram atalho. O `data.jsx` está fora do prefixo.
- O título das Pages (24px × 22px) é da thread 02.

## Provas

- Render das 15 rotas, mais `repair`, no espelho servido por `servirEstatico` na porta 5577,
  viewport 1280×900, tema escuro. As 16 montaram `.rep-root` com 0 erro de página e 0 erro de
  console. Cada uma abriu a aba e o drawer da tabela acima. Exemplos: `rep-reparo` →
  "Venda de reparo REP-2026-0294"; `rep-modelo-editar` → "Editar modelo: SureColor S60600".
- `node scripts/design/ds-guard.mjs prototipo-ui/cowork/Wagner/repair-page.jsx` → `limpo`.
- `node scripts/design/protocolo.config.mjs --selftest` → OK.

## O que precisa subir ao Cowork

- `prototipo-ui/cowork/Wagner/repair-page.jsx`
- `prototipo-ui/cowork/Wagner/cowork-inbox/repair/playbook/_saida-00.md`
