---
sessao: "05"
titulo: "Recibo — contratos repair-index + repair-jobsheet-index, âncoras e recorte em abas"
autor: "[CL]"
data: 2026-10-02
base: "claude/repair-thread-02-titulo (revalidado), sobre origin/main"
thread: 05-contratos.md
veredito: "ENTREGUE — 2 contratos gravados e verdes; âncoras nas 2 Pages; JobSheet/Index ganhou o recorte Pendentes/Concluídas/Entrega vencida/Todas (decisão [W] 2026-10-02); UC-JSIDX-07 verde no run 37041333904"
---

# _saída 05 · Contratos de Repair

## O que destravou

Esta thread tinha parado em 2026-10-02 (versão anterior deste recibo, no histórico do git): o job
required `Contratos de tela (fidelidade + intenção)` exige uma âncora `data-contract` no `.tsx` para
cada seção, as duas Pages não tinham nenhuma, e o índice dizia `nao_toca: ${PAGES}/`.

A saída 1 que este recibo recomendava foi a adotada: contrato e âncoras no mesmo PR (#8533). A
seção `repair-jobsheet-recorte`, que não existia no vivo, foi resolvida por decisão do dono:

> [W] 2026-10-02, textual: *"A tela ganha as abas"*.

## O que mudou

| arquivo | mudança |
|---|---|
| `governance/design/contracts/repair-index.contract.json` | gravado, igual ao bloco derivado da A1 |
| `governance/design/contracts/repair-jobsheet-index.contract.json` | gravado; `repair-jobsheet-recorte` com a copy das 4 abas |
| `resources/js/Pages/Repair/Index.tsx` | 6 âncoras (`repair-header` … `repair-rodape`), sem mudar comportamento |
| `resources/js/Pages/Repair/JobSheet/Index.tsx` | 5 âncoras + o recorte em abas (`PageHeaderTabs`) |
| `Modules/Repair/Http/Controllers/JobSheetController.php` | parâmetro opcional `recorte` no endpoint existente |
| `Modules/Repair/Tests/Feature/RepairJobSheetIndexContratoTest.php` | UC-JSIDX-07 |
| `JobSheet/Index.casos.md` · `JobSheet/Index.charter.md` (v4) | recorte declarado |

### O recorte

- Filtro do **backend**, no mesmo endpoint DataTables que serve o Blade (`route('job-sheet.index')`,
  ramo `ajax`). Nenhuma rota nova.
- `recorte=pendentes` (default da tela): status sem `is_completed_status`, ou OS sem status — o que a
  tela sempre mostrou.
- `recorte=concluidas`: status com `is_completed_status = 1`.
- `recorte=vencidas`: pendente com `delivery_date` num **dia** anterior a hoje. "Vence hoje" não
  entra — mesma regra do protótipo (`repair-data.jsx`, `atrasada`).
- `recorte=todas`: sem filtro de conclusão.
- Sem o parâmetro, vale o `is_completed_status` legado que o Blade manda: o Blade não muda.
- O escopo de business, de permissão (`view_all` / só as minhas) e de local roda antes do recorte e
  vale para os quatro.

**Não entrou:** a contagem por aba que o protótipo mostra ("Pendentes 11"). O endpoint DataTables
devolve só o total do recorte pedido; contar as quatro abas exige quatro consultas ou uma prop
nova. Fica para decisão.

### Âncoras

| contrato | âncora | elemento |
|---|---|---|
| repair-index | `repair-header` | wrapper do `<PageHeader>` |
| | `repair-kpis` | grid das 3 `KpiCard` |
| | `repair-filtros` | linha de busca + selects + Limpar |
| | `repair-status` | fileira de chips de status |
| | `repair-lista` | tabela / `EmptyState` |
| | `repair-rodape` | "Mostrando X–Y de N" + paginação |
| repair-jobsheet-index | `repair-jobsheet-header` | wrapper do `<PageHeader>` |
| | `repair-jobsheet-recorte` | as 4 abas |
| | `repair-jobsheet-filtros` | os 3 selects + Limpar |
| | `repair-jobsheet-lista` | tabela / skeleton / `EmptyState` |
| | `repair-jobsheet-rodape` | "{n} OS exibida(s)." |

Copy: só a que o charter declara. Em `repair-index`, título e os dois vazios; em
`repair-jobsheet-index`, "Nova OS", "Limpar" e as 4 abas (declaradas no charter v4, a partir da
decisão do dono). O resto fica vazio: é decisão do dono.

## Provas

- `node scripts/contrato-de-tela.mjs --contract …` nos dois arquivos: **limpo**, todas as seções
  com âncora + copy, ordem coerente.
- `--map --check`: limpo. `--anti-tautologia`: 0 reprovados (a `fonte` é o `repair-page.jsx`, não a
  Page).
- Lane `PHP / Pest (Verticais · MySQL)` no branch, run
  [37041333904](https://github.com/wagnerra23/oimpresso.com/actions/runs/37041333904), **success**:
  - `RepairJobSheetIndexContratoTest`: 7 testes, 43 asserções, 0 falhas, 0 skipped; UC-JSIDX-07 =
    22 asserções;
  - `RepairIndexContratoTest`: 6 testes, 21 asserções, 1 skipped (UC-RIDX-02, dito no `.casos.md`).
- `node scripts/casos-coverage-guard.mjs`: sem violação nova.

## Ficou fora

- Prova visual de produção das abas: só depois do deploy.
- Contagem por aba (acima).
- O subtítulo de desenvolvimento de `Repair/Index` ("Listagem MWART (Sprint 2)…"): copy do dono.
- Nenhum arquivo deste espelho subiu ao Cowork daqui.
