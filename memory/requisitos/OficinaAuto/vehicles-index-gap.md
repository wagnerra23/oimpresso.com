---
id: requisitos-oficinaauto-vehicles-index-gap
tela: OficinaAuto/Vehicles/Index (/oficina-auto/veiculos)
prototipo: n/a — nenhum protótipo Cowork cobre esta tela (medido 2026-09-09; a exclusão é declarada pela própria fonte da Oficina — recibo no corpo)
map_json: n/a (sem protótipo — o map por REGIÃO liga bloco do protótipo ↔ range do vivo, e sem lado-protótipo toda parte nasceria prototipo.arquivo=TODO: ponte sem a outra margem, não cobertura. Vira candidato no dia em que o Cowork desenhar a tela)
padrao_tela: PT-01 Lista
tela_viva: resources/js/Pages/OficinaAuto/Vehicles/Index.tsx
gerado_em: 2026-09-09
---

# GAP-SPEC — OficinaAuto/Vehicles/Index

> **Este GAP-SPEC não compara protótipo × vivo — não há protótipo.** A referência é a
> **lei de design que já existe** para esta tela: [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md)
> (6 slots + estados obrigatórios) + o **charter** dela (`Index.charter.md`, Goals e UX
> Anti-patterns) + os **componentes canon** do repo. É a forma que a [ADR 0282](../../decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1
> prevê quando falta a fonte visual: o design nasce **ancorado no DS canon**, não se espera insumo.
>
> **Por que a ausência é legítima e não some daqui:** `oficina-forms.jsx:7` declara
> *"FORA DE ESCOPO: Veículos CRUD"*; o `ancora.mjs` classifica o `related_prototype: n/a`
> das 4 telas como *"declaração legítima — a tela nasce do DS"*. O `n/a` do charter
> **permanece**; o que este documento cria é a especificação que faltava, não uma âncora falsa.
>
> **Frescor da medição:** vivo lido no `origin/main` de 2026-09-09 (base 0/0 no momento da
> leitura). O `COWORK-ESTRUTURA-E-TELAS.md` classificava a Oficina como *"frescor não rodado —
> não assuma"*; esta é a rodada que faltava.

## O achado principal — a placa não usa o componente que o charter exige

`MercosulPlate` é componente **shared do repo** (`resources/js/Components/shared/MercosulPlate.tsx`)
com **8 arquivos consumidores** — `Sells/Create.tsx:1206`, `Sells/Show.tsx:458`,
`Sells/_components/SellsTabelaUnificada.tsx:364`, `ServiceOrders/Board.tsx:997` e `:1104`,
`ServiceOrders/_components/board/ServiceOrderKanbanCard.tsx:250`,
`ProducaoOficina/_components/ServiceOrderRichSheet.tsx:366`
(varredura contada: `git grep -n MercosulPlate -- resources/js`, 8 arquivos fora do próprio).

**As 4 telas de `Vehicles/` têm ZERO ocorrências** (contado, uma a uma). O `Index.tsx:398`
imprime `{v.vehicle_number ?? v.plate}` como texto puro.

Isso contraria o charter da própria tela em **dois pontos explícitos**:

- `Index.charter.md:34` — Goal: *"Coluna placa com **componente MercosulPlate** (visual fiel padrão BR)"*
- `Index.charter.md:56` — UX Anti-pattern: *"Placa em texto puro sem componente Mercosul (canon = `<MercosulPlate>` shared)"*

E há registro de valor do cliente: `Index.charter.md:24` cita o feedback do Martinho em
2026-05-26 — *"placa Mercosul ficou top"* — classificando o componente como **diferencial UX
vs concorrentes**. Nenhum UC cobre a **forma** da placa (conferido: os 3 UCs do `Index.casos.md`
são lista, multi-tenant e soft-delete), então não há teste que dispute o charter — pela regra de
precedência do eixo FORMA a lei vigente é o charter, e o código está em violação dela.

**Consequência prática:** hoje a Venda mostra a placa Mercosul e o **cadastro de veículos, não** —
o inverso do esperado numa oficina, onde a placa é a chave de busca do atendente.

> **Nota de fonte:** o Design System também tem `prototipo-ui/design-system/components/PlacaVeiculo/`
> (API mais rica — `padrao: mercosul|antiga`, `size: sm|md|lg`, `categoria`, `uf`). Ele é o
> **componente do DS**; o `MercosulPlate` é a **implementação viva no repo**. Esta tela adota o
> que já roda em prod (`MercosulPlate`) — convergir os dois é decisão de DS, fora do escopo da tela.

## Quadro por parte

| Parte | Estado no vivo | Ação |
|---|---|---|
| **Placa (coluna Veículo)** | `Index.tsx:398` renderiza `vehicle_number ?? plate` em texto puro; `:399-401` põe a placa entre parênteses quando há número interno. Zero import de `MercosulPlate`. | **Construir** — não é decisão em aberto: o charter já decidiu nos dois sentidos (Goal `:34` + Anti-pattern `:56`). Trocar a célula por `<MercosulPlate plate={v.plate} size="sm" />`, mantendo `vehicle_number` como rótulo secundário. Precedente de tamanho em lista: `SellsTabelaUnificada.tsx:364` e `ServiceOrderKanbanCard.tsx:250` usam `size="sm"`. Placa secundária (cavalo+reboque, [ADR 0194](../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)) segue o par de `Sells/Create.tsx:1206-1207`, que renderiza as duas. |
| **Cartões de indicador** | `Index.tsx:252-255` usa um `KpiCard` **local** (`function KpiCard` em `:495`), com `tone` emerald/blue/amber/rose e `icon` como componente lucide. | **Decidir.** Existe `Components/shared/KpiCard.tsx` com `label`/`value`/`tone`/`onClick`/`selected`, mas a API **não é drop-in**: o shared declara `icon?: string` (`KpiCard.tsx:77`) e o local passa o componente (`icon={CheckCircle2}`). Adotar o shared exige mapear ícone por nome **ou** estender o shared para aceitar `ComponentType` — e estender componente do DS é decisão [W] (soberania Tier 0), não ajuste de tela. Ganho real: os 4 cards viram filtro clicável via `onClick`/`selected`, hoje duplicado nas pills de `:201-206`. Construir ou rejeitar por escrito. |
| **Toolbar (slot 3 do PT-01)** | Existe busca (`Input`) e pills de status (`:201-206`). Não há saved views, nem chips de filtro ativo removíveis, nem filtro por `vehicle_type` — que o charter lista como Goal (`Index.charter.md:33`: *"filtros: vehicle_type, plate, contact"*). | **Construir** o filtro de `vehicle_type` (Goal do charter não atendido) e **decidir** sobre saved views. Vocabulário Tier 0: os rótulos vêm do dicionário (`memory/dominio/oficina-auto.md`) — `caminhao`, `cavalo`, `semi_reboque`, `automovel`, `motocicleta`, `outros`. Os valores `cacamba_*` e `recapagem` do enum são **resíduo vestigial declarado** e **não** podem aparecer como opção user-facing: `forbidden_ui_terms` inclui `cacamba` e o `dominio:check` varre `resources/js/Pages/OficinaAuto` (ratchet por contagem — ocorrência nova estoura o CI). |
| **Seleção em lote (slot 4)** | `Index.tsx:362` e `:388` têm `<input type="checkbox" disabled>` com `aria-label` marcando `(P2)`. Não há `BulkActionBar`. | Nada agora — é **placeholder honesto e declarado**, não defeito. Quando a P2 entrar, o slot 4 do PT-01 pede `<BulkActionBar>` shared, com a ação destrutiva sempre por último em vermelho. Registrado aqui para não voltar como "achado" numa próxima leitura. |
| **Estados da lista** | Empty states existem e são contextuais: `:552` para filtro sem resultado e `:568` para primeiro acesso ("Nenhum veículo cadastrado"). Paginação completa em `:601-620`. | Nada nos estados 2 e 3 — o vivo cumpre. **Falta o estado 4 (skeleton):** o charter pede `Inertia::defer` no count de OS por veículo (`Index.charter.md:38`) e lista como anti-pattern *"Eager count(orders) em todas linhas sem defer (N+1)"* (`:59`); a tela não embrulha nada em `<Deferred>`. **Construir** junto com o defer no controller — é meia-entrega hoje. |
| **Colunas de conteúdo** | `:364-369` — Veículo · Capacidade · Cliente atual · Entrada · A receber · Status. As três do meio vêm de `current_rental` (`Index.tsx:36-49`, tipo `CurrentRental`, campo `valor_receber`). | **Decidir — e é decisão [W], não de tela.** O tipo TS ainda se chama `CurrentRental` e a coluna "A receber" deriva dele: é o **resíduo vestigial de locação** que `memory/dominio/oficina-auto.md` declara visível (`vehicles.current_status.locada`) e que o `RUNBOOK-erradicacao-locacao.md` cataloga como pendente. O user-facing **já foi limpo** no sweep de 2026-06-10 (cabeçalho do arquivo `:3-5`: "Caçambas"→"Veículos", colunas Endereço/Diárias removidas, "Locada"→"Em serviço"). O que resta é **nome de tipo e origem do dado**, não label. Não mexer sem decisão [W] — está fora do P0 da [ADR 0265](../../decisions/0265-oficina-reparo-erradica-locacao.md). |
| **Atalhos de teclado** | Nenhum — o arquivo não registra `keydown`. | **Decidir.** O PT-01 lista J/K · Enter · N · `/` · ⌘K · ? como canônicos. Numa tela que o atendente usa com o cliente na frente, `/` (foco na busca) e `N` (novo) são os dois de valor real; o resto é ganho marginal. Construir os dois ou rejeitar por escrito. |
| **Cabeçalho** | `:219` monta o subtítulo `"N cadastrados · N em serviço · N em manutenção"` com `tabular-nums`; a ação "Importar do Firebird" está `disabled` com `title="P1 — em breve"` (`:236-237`). | Nada — cumpre o slot 1 do PT-01 e o Goal do charter. O `disabled` é honesto: o importer é artisan (`officeimpresso:import-vehicles`, US-OFICINA-002) e inline é Non-Goal explícito do charter (`:45`). |

## O que este documento NÃO decide

- **Não** promove `n/a` a âncora. As 4 telas seguem sem protótipo, e isso é declaração legítima.
- **Não** toca no resíduo `CurrentRental`/`current_status.locada` — decisão [W], fora do P0 da ADR 0265.
- **Não** estende componente do Design System (`KpiCard` aceitar `ComponentType`) — soberania [W].
