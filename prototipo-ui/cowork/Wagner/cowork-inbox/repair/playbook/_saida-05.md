---
sessao: "05"
titulo: "Recibo — contratos repair-index + repair-jobsheet-index (PARADO: o gate required exige âncora no .tsx)"
autor: "[CL]"
data: 2026-10-02
base: "claude/repair-thread-02-titulo, sobre origin/main 13bc079894"
thread: 05-contratos.md
veredito: "PARADO — os 2 contratos foram derivados dos alvos da A1 e estão abaixo, mas NÃO foram gravados em governance/design/contracts/: gravar deixa vermelho um required, e o conserto toca Pages/ (nao_toca)."
---

# _saída 05 · Contratos de Repair

## Por que os 2 arquivos não foram gravados

Todo `*.contract.json` versionado entra no step "Contratos de tela ativos" do job
**`Contratos de tela (fidelidade + intenção)`**, que é **required** (a lista mora em
`governance/required-checks-baseline.json`). O `scripts/contrato-de-tela.mjs --contract` exige
uma âncora `data-contract="<id>"` no `.tsx` do `alvo` para cada seção.

As duas Pages têm **zero** âncoras:

- `git grep -c data-contract` em `resources/js/Pages/Repair/Index.tsx` → 0
- o mesmo em `resources/js/Pages/Repair/JobSheet/Index.tsx` → 0

Sonda real com os blocos abaixo, contra os `.tsx` do branch:

```
tela: Repair/Index · alvo: resources/js/Pages/Repair/Index.tsx · 1 arquivo(s)
X seção "repair-header" sem âncora data-contract no alvo
… (6 seções)
❌ 6 falha(s).

tela: Repair/JobSheet/Index · …
❌ 5 falha(s).
```

A copy passou nos dois casos. Só faltam as âncoras.

O índice desta thread diz `nao_toca: ${PAGES}/`, e pôr âncora é editar a Page. É o mesmo bloqueio
que a thread 01 do CRM registrou em 2026-09-30 (`cowork-inbox/crm/playbook/_saida-01.md`):
"não existe hoje o estado 'contrato antes do código' no gate".

## Saídas — decisão [W] ou Cowork (nenhuma tomada aqui)

1. **Recomendada, sem mexer em gate.** O contrato entra no mesmo PR que põe as âncoras nos dois
   `.tsx`. Pode ser uma thread nova de 1 PR, com prefixo nos dois `.tsx` e nos dois
   `.contract.json`. Pode ser também a 02 reaberta, porque ela já toca exatamente esses
   arquivos. Isso é edição do `00-INDICE.md`, que pertence ao Cowork.
2. Ensinar o gate um estado "pré-código". Mexe num required e é governança, fora deste prefixo.

## Os 2 contratos, derivados da A1

- **Seções.** Uma por seção do alvo da A1, com `header` e `tabs` fundidos em `*-header`. As abas
  do protótipo são do módulo inteiro; as Pages vivas não as têm.
- **Copy.** Ficou vazia onde o charter não declara texto literal. Copy de contrato é decisão do
  dono (`how-trabalhar.md`: "Contrato visual (copy literal + ordem)", quem informa é o [W]). Onde
  o charter declara, foi usada:
  - `Repair/Index`: "Ordens de Serviço", "Nenhuma OS no filtro", "Sem ordens de serviço";
  - `JobSheet/Index`: "Nova OS", "Limpar".

  Toda essa copy já existe nos `.tsx`.
- **`fonte`.** O `repair-page.jsx` (protótipo). Não é a Page, então o anti-tautologia passa.

#### `repair-index.contract.json`

```json
{
  "_nota": "Derivado do alvo governance/design/targets/repair--index.alvo.json (thread A1 do playbook repair), não do .tsx. Uma seção por seção do alvo; ids com prefixo repair-. Copy só onde o charter de Repair/Index a declara literal (título e os dois vazios do Goals); o resto da copy é decisão do dono.",
  "tela": "Repair/Index",
  "fonte": "prototipo-ui/cowork/Wagner/repair-page.jsx",
  "alvo": ["resources/js/Pages/Repair/Index.tsx"],
  "secoes": [
    { "id": "repair-header", "copy": ["Ordens de Serviço"] },
    { "id": "repair-kpis", "copy": [] },
    { "id": "repair-filtros", "copy": [] },
    { "id": "repair-status", "copy": [] },
    { "id": "repair-lista", "copy": ["Nenhuma OS no filtro", "Sem ordens de serviço"], "estados": ["empty", "no-results"] },
    { "id": "repair-rodape", "copy": [] }
  ],
  "ordem": ["repair-header", "repair-kpis", "repair-filtros", "repair-status", "repair-lista", "repair-rodape"]
}
```

#### `repair-jobsheet-index.contract.json`

```json
{
  "_nota": "Derivado do alvo governance/design/targets/repair--jobsheet--index.alvo.json (thread A1 do playbook repair), não do .tsx. Copy só onde o charter de JobSheet/Index a declara literal (botões \"Nova OS\" e \"Limpar\"); o resto da copy é decisão do dono.",
  "tela": "Repair/JobSheet/Index",
  "fonte": "prototipo-ui/cowork/Wagner/repair-page.jsx",
  "alvo": ["resources/js/Pages/Repair/JobSheet/Index.tsx"],
  "secoes": [
    { "id": "repair-jobsheet-header", "copy": ["Nova OS"] },
    { "id": "repair-jobsheet-recorte", "copy": [] },
    { "id": "repair-jobsheet-filtros", "copy": ["Limpar"] },
    { "id": "repair-jobsheet-lista", "copy": [], "estados": ["loading", "error", "empty", "no-results"] },
    { "id": "repair-jobsheet-rodape", "copy": [] }
  ],
  "ordem": ["repair-jobsheet-header", "repair-jobsheet-recorte", "repair-jobsheet-filtros", "repair-jobsheet-lista", "repair-jobsheet-rodape"]
}
```

### Âncoras que o PR do código precisa pôr

| contrato | âncora `data-contract` | elemento no `.tsx` |
|---|---|---|
| repair-index | `repair-header` | wrapper do `<PageHeader>` |
| | `repair-kpis` | grid das 3 `KpiCard` |
| | `repair-filtros` | barra de busca + selects |
| | `repair-status` | fileira de chips de status |
| | `repair-lista` | a tabela/EmptyState |
| | `repair-rodape` | "Mostrando X–Y de N" + paginação |
| repair-jobsheet-index | `repair-jobsheet-header` | wrapper do `<PageHeader>` |
| | `repair-jobsheet-recorte` | **não existe no vivo**: o protótipo tem as abas Pendentes/Concluídas/Entrega vencida/Todas, e o `JobSheet/Index` não. Ou a Page ganha o recorte, ou a seção sai do contrato, ou entra com `design-deviation`. Decisão de forma, do dono. |
| | `repair-jobsheet-filtros` | barra dos 3 selects + Limpar |
| | `repair-jobsheet-lista` | a `<table>`/EmptyState |
| | `repair-jobsheet-rodape` | "{n} OS exibida(s)." |

## PARAR SE

Disparou: o contrato depende de editar a Page, e isso está no `nao_toca`. Parei aqui.
