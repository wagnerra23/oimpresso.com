---
id: requisitos-repair-6telas-index-visual-comparison
---

# Visual Comparison — as 6 telas Index do Repair × `repair-page.jsx`

> **Fonte (âncora):** `prototipo-ui/cowork/Wagner/repair-page.jsx` (46,5 KB), carregado pelo shell
> `prototipo-ui/cowork/Wagner/oimpresso.com.html` junto de `repair-page.css`, `repair-data.jsx`,
> `repair-forms.jsx`, `repair-portal.jsx`, `repair-print.{css,js}`.
> **Autoridade:** no eixo FORMA o protótipo é soberano — [ADR UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md).
> **Medido em:** 2026-09-09, contra `origin/main` @ `fd6afa2dc0`.

## 0. Como reproduzir esta medição

```bash
grep -oE '(src|href)="[^"]+"' prototipo-ui/cowork/Wagner/oimpresso.com.html \
  | sed 's/^\(src\|href\)="//; s/"$//' | sed 's/?.*$//' | sort -u | grep repair
node scripts/design/ancora.mjs Repair/<Tela> --staging prototipo-ui/cowork
```

## 1. Mapa região → tela (derivado do dispatcher `RepairPage`, L633-745)

O protótipo é **uma** página com 8 abas (`MP.Tabs`); o app real tem **6 rotas Inertia**
separadas sob o Shell canônico. As abas do protótipo existem porque o shell do Cowork é um
HTML único sem roteador — no app o `AppShellV2` já roteia. **Não portar as abas**: seria
importar solução para um problema que não temos (§5 2026-07-16).

| Região do protótipo | Linhas | Tela viva | Fonte de dados real |
|---|---|---|---|
| `Painel` | L45-103 | `Repair/Dashboard/Index` | `DashboardController:78` |
| `Producao` | L104-144 | `Repair/ProducaoOficina/Index` | `ProducaoOficinaController:98` |
| `Folhas` | L145-259 | `Repair/JobSheet/Index` | `JobSheetController:310` (`job_sheets`) |
| `Reparos` | L260-303 | `Repair/Index` | `RepairController:434` (`transactions` `sub_type=repair`) |
| `Status` | L304-337 | `Repair/Status/Index` | `RepairStatusController:75` |
| `Modelos` | L338-369 | `Repair/DeviceModels/Index` | `DeviceModelController:119` |
| `Config` | L370-428 | `Repair/Settings/Index` | fora do escopo destas 6 |

## 2. Estado da âncora — a premissa de "n/a vencido" está REFUTADA

A porta viva per-tela (`ancora.mjs`, a única que implementa a regra dura do docblock — §5
2026-08-28) resolve âncora para **as 6**, via `bundle_source: repair-page.jsx`:

| Tela | `related_prototype` real | `ancora.mjs` |
|---|---|---|
| `Index` | `n/a (herda PT-01 Lista; …)` | `âncora ✓ repair-page.jsx` |
| `Dashboard/Index` | **chave ausente** | `âncora ✓ repair-page.jsx` |
| `DeviceModels/Index` | `n/a (herda PT-01 Lista; …)` | `âncora ✓ repair-page.jsx` |
| `JobSheet/Index` | **chave ausente** | `âncora ✓ repair-page.jsx` |
| `ProducaoOficina/Index` | `n/a (herda PT-05 Kanban; …)` | `âncora ✓ repair-page.jsx` |
| `Status/Index` | **chave ausente** | `âncora ✓ repair-page.jsx` |

Dois fatos que a premissa não previa:

1. **`n/a` são 3, não 6** — as outras 3 simplesmente não declaram a chave.
2. **`n/a (herda PT-0X…)` não é defeito.** É declaração que a máquina reconhece
   (`ehDeclaracaoNa`) e que **coexiste** com a âncora de bundle por desenho — o próprio
   `ancora.mjs` imprime *"declaração legítima — a tela nasce do DS"* na mesma saída em que
   dá `âncora ✓`.

**Por que NÃO promover `bundle_source` → `related_prototype`:** o cabeçalho de
`repair-page.jsx` (L1-2) se declara *"importado dos blades
`Modules/Repair/Resources/views/{dashboard,job_sheet,repair,status,device_model,settings}`"*
— é **porte reverso do código vivo**. Promovê-lo a `related_prototype` (= "design aprovado")
ancoraria a tela **nela mesma**, que é a lápide §5 2026-06-05 (derivar do código). Este
arquivo é nominalmente um dos 5 hubs que a §5 2026-08-28(b) já proíbe promover em leva.

**Consequência prática:** nenhum charter precisa de correção de âncora. A fonte já está
declarada e resolvida. O trabalho real é **portar a forma**, item 3.

## 3. Diff de forma, por tela

Legenda: **`SÓ_PROTO`** = existe no protótipo, falta na tela · **`DIVERGE`** = existe nos
dois, forma diferente · **`SÓ_PROD`** = existe só na tela (legítimo).

### 3.1 `Repair/Index` × `Reparos` — o achado mais grave

| Item | Protótipo | Tela viva | Veredito |
|---|---|---|---|
| Título | aba **"Reparos"** | `title="Ordens de Serviço"` (L194), `AppShellV2 title="Ordens de Serviço · Repair"`, breadcrumb `OS` (L449) | **DIVERGE** |
| Identidade | "o reparo é a **venda derivada** da folha" | apresenta-se como a OS | **DIVERGE** |
| Colunas | Nº do reparo · Fatura · Folha de OS · Cliente · Garantia · Pagamento · Total · Saldo devedor | sem Fatura, sem Garantia, sem Saldo | **SÓ_PROTO** |
| KPIs | Reparos faturados · Valor faturado · Em aberto | Em andamento · Concluídas · Total exibido | **DIVERGE** |
| Nota de fronteira | *"pagamento, garantia e cobrança são de Vendas/Financeiro. Aqui só se lê."* | ausente | **SÓ_PROTO** |
| Linha em aberto | `state: "urgent"` quando saldo maior que zero | ausente | **SÓ_PROTO** |

**Colisão medida:** `Repair/Index.tsx:194` e `JobSheet/Index.tsx:101` usam **o mesmo título**
("Ordens de Serviço" / "Ordens de serviço") para dados diferentes — `transactions`
(`type=sell`, `status=final`, `sub_type=repair`, `RepairController:504-509`) contra
`job_sheets`. O protótipo separa nominalmente as duas abas. Renomear é forma, e é a correção.

### 3.2 `Repair/Dashboard/Index` × `Painel`

| Item | Protótipo | Tela viva | Veredito |
|---|---|---|---|
| KPI 1 | **Folhas pendentes** (hero, spark, "N sem técnico atribuído") | "Status únicos" | **DIVERGE** |
| KPI 2 | **Concluídas** (tone success) | "Service staff" | **DIVERGE** |
| KPI 3 | **Entrega vencida** (tone danger condicional) | ausente | **SÓ_PROTO** |
| KPI 4 | **Ticket médio** | ausente | **SÓ_PROTO** — toca valor |
| Alerta topo | `Alert danger` "N folha(s) com entrega vencida" + ação "Ver as atrasadas" | ausente | **SÓ_PROTO** |
| Barras por status | clicáveis, navegam pro filtro | estáticas | **DIVERGE** |
| Charts | 5 (status, técnico, marca, equipamento, modelo) | 5 equivalentes, deferidos | **~ paridade** |

**Defeito de medição, não só de forma:** `DashboardController.php:80` calcula
`'total_repairs' => count($job_sheets_by_status)` — conta **linhas do agrupamento**, ou seja
quantos status distintos aparecem, não quantas OS existem. Num negócio com 6 status o número
é ~6 para sempre, com 3 ou 3.000 OS. A tela rotula honestamente ("Status únicos"), mas o
nome da prop promete outra coisa. Mesmo caso em `service_staff_count` (L81).

### 3.3 `Repair/JobSheet/Index` × `Folhas`

| Item | Protótipo | Tela viva | Veredito |
|---|---|---|---|
| Colunas | 11 (inclui **Pipeline/fase**, **Nº de série**, **Custo estimado**) | a medir na onda | **SÓ_PROTO** (parcial) |
| Filtros-chip com contagem | Pendentes · Concluídas · **Entrega vencida** · Todas, cada um com `n` | a medir | **SÓ_PROTO** |
| Ordenação default | `entrega asc` (o prazo primeiro) | a medir | **DIVERGE** |
| `SeloPrazo` | "atrasada Nd" / "vence hoje" / "entregue no prazo" | a medir | **SÓ_PROTO** |

> Esta tela tem sessão paralela ativa (`claude/repair-us-004-listagem`, charter) — ver §5.

### 3.4 `Repair/Status/Index` × `Status`

| Item | Protótipo | Tela viva | Veredito |
|---|---|---|---|
| Estrutura | lista de linhas (`rep-status-row`) | tabela de 5 colunas | **DIVERGE** |
| Selo | pill `rep-st` com `--st` = cor **do dado** (`repair_statuses.color`) | bolinha + hex em mono | **DIVERGE** |
| Contagem de uso | "N folha(s)" por status | ausente | **SÓ_PROTO** |
| Coluna do kanban | "coluna X" | ausente | **SÓ_PROTO** |
| Template SMS | `SMS: "…"` na linha | ausente | **SÓ_PROTO** — coluna `sms_template` **existe** |
| Flag concluído | texto "marcado como concluído"/"pendente" | ícone check / travessão | **DIVERGE** |
| Alerta FK | `Alert warn` "apagar status usado deixa folha órfã — migre antes" | ausente | **SÓ_PROTO** |
| Permissão exposta | `access_job_sheet_status` no rodapé | ausente | **SÓ_PROTO** |
| EmptyState | — | presente | **SÓ_PROD** (legítimo) |

Schema conferido (`2020_07_11_120308` + `2020_08_22_104640`): `sms_template`,
`email_subject`, `email_body`, `is_completed_status` **existem**. Só "coluna do kanban" não
tem coluna própria — é derivada, e fica fora até haver decisão.

### 3.5 `Repair/DeviceModels/Index` × `Modelos`

| Item | Protótipo | Tela viva | Veredito |
|---|---|---|---|
| Coluna **Folhas** (uso do modelo) | `n` por modelo, sortable | ausente | **SÓ_PROTO** |
| Checklist | chips (`TagChip`) por item | Badge com contagem | **DIVERGE** |
| Hint do legado | *"o checklist é o que aparece na folha ao escolher o equipamento"* | ausente | **SÓ_PROTO** |
| KPIs | — | Total · Marcas · Categorias | **SÓ_PROD** (legítimo) |
| Filtros marca/categoria | — | presentes | **SÓ_PROD** (legítimo) |

### 3.6 `Repair/ProducaoOficina/Index` × `Producao`

Não usa `PageHeader` (única das 6 fora do padrão). Tem **duas sessões paralelas ativas**
mexendo nela — medição adiada, ver §5.

## 4. Fronteira que este documento NÃO cruza

- **FSM é intocável aqui.** O Repair roda pipeline FSM LIVE em produção
  ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)): 13 stages ×
  ~15 actions × 6 roles. Mudança de **forma** é livre pelo protótipo; mudança de
  **transição/estado** não é deste escopo. `current_stage_id` só muda por
  `ExecuteStageActionService` — o trait `GuardsFsmTransitions` lança exceção em UPDATE direto.
- **"Ticket médio" (§3.2) toca valor monetário.** Entra sob a Regra Mestre de VALOR — dupla
  prova mais antes/depois — ou fica fora. Não é decisão do agente.
- **"Coluna do kanban" no Status (§3.4)** não tem coluna no schema. Escopo novo, declarado e
  deixado fora — não se fabrica número em tela.

## 5. Sessões paralelas ativas neste módulo (medido, ADR 0119)

| Branch | Toca | Colide com |
|---|---|---|
| `claude/repair-producao-e2e-a11y` | `ProducaoOficina/Index.casos.md`, `tests/Browser/Repair/…` | §3.6 |
| `claude/repair-us-004-listagem` | `JobSheet/Index.charter.md` (`related_us`), `SPEC.md` | §3.3 |

Ambas **abertas** (não ancestrais de `origin/main`). As ondas de §3.3 e §3.6 esperam elas
mergearem — evita conflito e retrabalho.

## 6. Ordem sugerida das ondas (1 PR = 1 intent)

| # | Tela | Intent | Bloqueio |
|---|---|---|---|
| 1 | `Status/Index` | forma: pill do dado, SMS, contagem, alerta FK | — |
| 2 | `Index` + `JobSheet/Index` | desambiguar título (§3.1) | espera `repair-us-004-listagem` |
| 3 | `Dashboard/Index` | KPIs operacionais (§3.2) | "Ticket médio" = decisão [W] |
| 4 | `DeviceModels/Index` | coluna Folhas + chips + hint | — |
| 5 | `ProducaoOficina/Index` | medir e portar | espera as 2 sessões |
