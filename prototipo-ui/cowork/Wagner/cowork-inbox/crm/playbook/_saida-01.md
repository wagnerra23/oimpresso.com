---
sessao: "01"
titulo: "Trio + contratos do lote 1 do Crm — saída da thread"
autor: "[CL]"
data: 2026-10-01
base: origin/main 9ac332386
thread: 02-trio-lote1.md §01
veredito: "NÃO entregue como arquivo — os 3 contratos derivados do protótipo estão abaixo, mas gravá-los em governance/design/contracts/ deixa VERMELHO um check REQUIRED enquanto as Pages não existem. Decisão [W]/Cowork pendente."
---

# _saida-01 · Contratos das 3 telas do lote 1 (Leads · Acompanhamentos · Painel)

## Estado das decisões

O placar ainda mostra `decisão pendente D1,D4` porque o índice do Cowork não foi reescrito.
As duas foram respondidas por [W] em 2026-10-01 (`_DECISOES-W-2026-10-01.md`):
D1 = Leads → Acompanhamentos → Painel → Campanhas → Propostas · D4 = `Modules/Crm/Resources/js/Pages`.
A dependência A1 está entregue (#8340). Este era o único motivo de não-`proximo`.

## Bloqueio medido — por que os 3 arquivos NÃO foram gravados

As provas da thread exigem `governance/design/contracts/crm-{leads,acompanhamentos,painel}.contract.json`.
Todo `*.contract.json` versionado entra no step "Contratos de tela ativos" do job
**`Contratos de tela (fidelidade + intenção)`**, que é **required**
(`governance/required-checks-baseline.json`). O `scripts/contrato-de-tela.mjs --contract` exige que o
`alvo` tenha `.tsx` com as âncoras `data-contract`. Sem a Page, ele reprova. Sonda real, contra um
contrato com `alvo: Modules/Crm/Resources/js/Pages/Leads/Index.tsx`:

```
X nenhum .tsx/.ts no alvo do contrato (Modules/Crm/Resources/js/Pages/Leads/Index.tsx)
❌ 1 falha(s).   rc=1
```

O `--map --check` do mesmo job reprova também (seções sem âncora e sem `design-deviation`).
Varredura dos 43 contratos ativos no `main`: **0** têm `alvo` inexistente. Não existe hoje o
estado "contrato antes do código" no gate. E a Lei IT2 impede criar a Page só para satisfazê-lo:
a Page pertence às threads 02/03/04.

Saídas possíveis, **decisão [W]/Cowork** (nenhuma tomada aqui):

1. **(recomendada, sem mexer em gate)** cada contrato entra no MESMO PR da sua Page: `crm-leads` na
   thread 02, `crm-acompanhamentos` na 03, `crm-painel` na 04. A thread 01 vira só esta derivação,
   e as provas dela passam para as threads 02-04. É edição do `00-INDICE.md`, que é do Cowork.
2. Ensinar o gate um estado "pré-código" (ex.: `"estado": "pre-codigo"` pulado no `--contract` e
   no `--map --check`). Mexe num required. É governança, fora do `prefixo` desta thread.

## O que foi entregue: os 3 contratos DERIVADOS do protótipo

- **Fonte:** `prototipo-ui/cowork/Wagner/crm-blade.jsx` (`TelaLeads`, `TelaAcompanhamentos`,
  `TelaPainel`), a mesma rota que a A1 mediu. Nada vem de `.tsx` (não existe nenhum).
- **IDs:** os 13 `data-contract`/`contrato=` do protótipo, todos usados (16 seções, porque
  `crm-filtros`, `crm-toolbar` e `crm-rodape` se repetem em Leads e Acompanhamentos).
- **Copy:** 53 strings literais. Conferidas uma a uma contra a fonte: **0 ausentes**.
- **`ordem`:** a ordem das âncoras no protótipo, de cima para baixo.
- **`alvo`:** caminho **provisório** sob `MPAGES` (D4). O nome exato do arquivo é da thread que
  criar a Page; corrija o `alvo` lá, não a copy.

Basta copiar o bloco para `governance/design/contracts/` no PR que criar a Page.

#### `crm-leads.contract.json`

```json
{
  "tela": "Crm/Leads/Index",
  "fonte": "prototipo-ui/cowork/Wagner/crm-blade.jsx",
  "alvo": ["Modules/Crm/Resources/js/Pages/Leads/Index.tsx"],
  "secoes": [
    { "id": "crm-filtros", "copy": ["Filtros", "Fonte", "Estágio de vida", "Atribuído a"] },
    { "id": "crm-leads", "copy": ["Todos os leads", "Nada com esses filtros"], "estados": ["list_view", "kanban", "vazio"] },
    { "id": "crm-toolbar", "copy": ["Buscar por nome, ID ou celular", "Exibição de lista", "Kanban", "Adicionar", "Confortável", "Compacto"] },
    { "id": "crm-rodape", "copy": ["Adicionar ao local", "Remover do local"] }
  ],
  "ordem": ["crm-filtros", "crm-leads", "crm-toolbar", "crm-rodape"]
}
```

#### `crm-acompanhamentos.contract.json`

```json
{
  "tela": "Crm/Acompanhamentos/Index",
  "fonte": "prototipo-ui/cowork/Wagner/crm-blade.jsx",
  "alvo": ["Modules/Crm/Resources/js/Pages/Acompanhamentos/Index.tsx"],
  "secoes": [
    { "id": "crm-filtros", "copy": ["Filtros", "Contato", "Atribuído", "Status", "Tipo de acompanhamento", "Intervalo de datas", "Acompanhamento por", "Categoria"] },
    { "id": "crm-acompanhamentos", "copy": ["Todos os acompanhamentos", "Acompanhamentos", "Acompanhamento recorrente"], "estados": ["todos", "recorrente", "vazio"] },
    { "id": "crm-toolbar", "copy": ["Buscar por título ou contato", "Recorrente", "Acompanhamento antecipado", "Adicionar"] },
    { "id": "crm-rodape", "copy": ["Total:"] }
  ],
  "ordem": ["crm-filtros", "crm-acompanhamentos", "crm-toolbar", "crm-rodape"]
}
```

#### `crm-painel.contract.json`

```json
{
  "tela": "Crm/Painel/Index",
  "fonte": "prototipo-ui/cowork/Wagner/crm-blade.jsx",
  "alvo": ["Modules/Crm/Resources/js/Pages/Painel/Index.tsx"],
  "secoes": [
    { "id": "crm-painel-meus", "copy": ["Meus acompanhamentos"] },
    { "id": "crm-painel-chamadas", "copy": ["Meus registros de chamadas", "Chamadas hoje", "Chamadas ontem", "Chamadas neste mês"] },
    { "id": "crm-painel-fontes", "copy": ["Fontes", "Fonte", "Total", "Conversão"] },
    { "id": "crm-painel-fases", "copy": ["Estágios de vida", "Estágio"] },
    { "id": "crm-painel-aniversarios", "copy": ["Aniversários", "Enviar desejos", "Hoje", "Próximos"] },
    { "id": "crm-painel-por-usuario", "copy": ["Acompanhamentos por usuário", "Nenhum", "Acompanhamentos totais"] },
    { "id": "crm-painel-conversao", "copy": ["Leads convertidos em cliente", "Convertido por"] },
    { "id": "crm-painel-chamadas-todos", "copy": ["Registro de chamadas — todos os usuários", "No mês", "Todas"] }
  ],
  "ordem": ["crm-painel-meus", "crm-painel-chamadas", "crm-painel-fontes", "crm-painel-fases", "crm-painel-aniversarios", "crm-painel-por-usuario", "crm-painel-conversao", "crm-painel-chamadas-todos"]
}
```

## Fora dos contratos, de propósito

- **Header e barra de abas** (seções `header`/`nav` da A1): são do shell (`Header` + `CliTabs`
  compartilhados), sem `data-contract` no protótipo. Mesmo critério do `jana-painel` (`_nota_titulo`).
- **As duas grades de KPI do Painel** (seções `kpis_pessoais`/`kpis_totais` da A1): o protótipo não
  dá âncora a elas. Criar o id seria inventar. A copy delas, se a thread 04 quiser pinar:
  "Acompanhamentos de hoje", "Meus leads", "Meus leads convertidos", "Clientes", "Leads".
- **Rótulos de coluna das grades e textos do drawer/modal:** a grade é o `DataTablePro` do DS e o
  drawer não tem âncora. Ficam com os casos/testes das threads 02-04.
- **Vistas secundárias** (kanban de Leads, aba recorrente de Acompanhamentos): entram só como
  `estados`. A A1 não as mediu (`_saida-A1` §Pendente 1).

## Charter e casos (a ficha pede)

Não criados. Lei IT2: `.charter.md`/`.casos.md` não nascem sem o `.tsx` irmão. Entram nas threads
02/03/04, junto com as Pages, no endereço da D4.

## Observação para o Cowork/[W] (não consertada, fora do prefixo)

`.github/scripts/contrato-de-tela-detect.sh` só considera relevante `^resources/js/Pages/.+\.tsx?$`.
Um PR que mexa SÓ numa Page sob `Modules/*/Resources/js/Pages/` (a D4 do Crm, e já hoje os 4
contratos do Superadmin) pula o job inteiro como skip-as-pass, inclusive se apagar uma âncora.
Antes de o Crm ganhar contratos, vale estender o filtro. É mudança num required, logo decisão [W].

## Provas do json conferidas

- `governance/design/contracts/crm-leads.contract.json` — **ausente** (bloqueio acima).
- `governance/design/contracts/crm-acompanhamentos.contract.json` — **ausente**.
- `governance/design/contracts/crm-painel.contract.json` — **ausente**.

## Placar

entregue 0 de 3 arquivos de prova · 3 de 3 contratos derivados (16 seções, 53 copy, 0 ausentes na
fonte) · a thread fica `em curso`, não `feito`, até a decisão acima.

## PR

O PR que adiciona este arquivo — branch `claude/crm-thread-01`.
