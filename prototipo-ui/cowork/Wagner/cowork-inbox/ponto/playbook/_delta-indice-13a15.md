---
tipo: delta de índice (não é thread — não tem _saida)
para: prototipo-ui/design-docs/cowork-inbox/ponto/playbook/00-INDICE.md
base_do_indice: e86130722de1 (§7 diz sha e86130722de1 · gerado 2026-09-06)
medido_em: 2b4a3ec3b48a (2026-09-09)
---
# Delta do 00-INDICE — reexportar o Ponto com ancoragem dupla (threads 13–15 + o que falta medir)

> **Por que delta e não índice novo:** o `00-INDICE.md` do Ponto vive no `main` e eu **não escrevo no git**. Forkar 24.929 B dele aqui seria cópia local de canon — a L-42 que o `CLAUDE.md` proíbe. Então desce o **patch**: 3 objetos no `§7.threads`, 2 em `§7.decisoes`, 3 linhas no `§2`. A pasta inteira continua sendo a unidade de descida.

## 1 · O eixo novo — e por que ele não existia
As threads **08** e **09** são **PUXAR** (produção → protótipo). O que [W] pediu em 2026-09-09 é o **sentido oposto**: *"deixe o visual igual ao seu"* — protótipo → produção. Não é repintar tela viva por gosto: é **paridade de forma** com o alvo medido, ancorada por símbolo.

**O que mudou desde a sha `e86130722de1` do índice** (lido em `2b4a3ec3b48a`):
- **Retratação:** o índice §1 já dizia "🔵 produção; aqui é sobretudo PUXAR". Certo — e eu passei o dia dizendo a [W] que "o Ponto não usa o DS". **Usa.** As 21 Pages importam `@/Components/ui/*` + `@/Components/shared/*`. O outlier era o meu protótipo.
- **W12 morreu** (não estava no índice, estava no meu `COLAR-NO-CODE-ponto-ondas.md`): `shared/BulkActionBar.tsx` aceita `children` ⇒ o "motivo do lote" cabe dentro. Era eu supondo, não medindo.
- **W9 virou número:** protótipo **13 abas planas** × `PontoSubNav` **5 + ⋯ Mais** (`maxVisible={5}`, fonte no `shell.menu`). Segue sem dono.
- **Nasceu W14** e **W11 entrou na fila** (abaixo).

## 2 · Inserir em `§2 · Threads` (3 linhas, no fim da tabela)
| # | thread | dono | prefixo | depende | vaga |
|---|---|---|---|---|---|
| 13 | Paridade de FORMA — Painel | [CL] | `${PAGES}/Dashboard/Index.tsx` | ds-atomos 01 · 02 | 4 |
| 14 | Paridade de FORMA — Espelho · lista | [CL] | `${PAGES}/Espelho/Index.tsx` | ds-atomos 01 · 03 | 4 |
| 15 | Paridade de FORMA — Aprovações | [CL] | `${PAGES}/Aprovacoes/Index.tsx` | ds-atomos 01 · 03 | 5 |

**Dependência externa declarada:** as três dependem de `cowork-inbox/ds-atomos/playbook/` **01/02/03** — os átomos (`Card badge/note/flush` · `KpiCard variant="filter"` · `shared/Toolbar`). Sem eles no `main`, chegar ao alvo exige CSS novo, e aí é dívida com selo, não paridade. **Aditivo ou nada** naqueles três, com prova de guarda: `Card` e `KpiCard` são consumidos fora do Ponto (Backup · Financeiro/Advisor · Financeiro/Unificado).

## 3 · FICHA de capacidade — as 19 Pages medidas, 3 emitidas
Alvo do protótipo colhido do DOM (T1 estável por aba: 907–1062 nós, dark, 1280px). `leitura` = `.tsx` + charter. Tetos §13.2: 40.000 B · 300 ln · 8 arquivos · 3 símbolos · 0 decisões.

| aba do protótipo | nós | âncora `arquivo :: símbolo (:linha)` | leitura | símb. | veredito |
|---|---:|---|---:|---:|---|
| Painel | 959 | `Dashboard/Index.tsx :: DashboardIndex(:183) · NotaFechamento(:131) · ApprovalRow(:487)` | 23.410 | 3 | **CABE → 13** |
| Espelho · lista | 1015 | `Espelho/Index.tsx :: EspelhoIndex(:42)` | 9.853 | 1 | **CABE → 14** |
| Aprovações | 912 | `Aprovacoes/Index.tsx :: AprovacoesIndex(:119)` | 26.999 | 1 | **CABE → 15** |
| Espelho · mês | (dentro de 1015) | `Espelho/Show.tsx :: EspelhoShow(:104) · Totalizador(:419) · DiaCard(:470) · TotalDoDia(:529) · Dado(:565) · EstadoDia(:584)` | 28.033 | **6** | **DIVIDE** — 16a (`EspelhoShow`+`Totalizador`+`TotalDoDia`) · 16b (`DiaCard`+`Dado`+`EstadoDia`) |
| Intercorrências | 986 | `Intercorrencias/{Index(:71) · Create(:70)+Field(:431) · Edit(:71)+Field(:318) · Show(:49)+Row(:169)}` | 13.615 / 20.392 / 17.582 / 9.980 | 1/2/2/2 | CABE — **4 threads**, uma por Page |
| Banco de horas | 938 | `BancoHoras/Index.tsx :: BancoHorasIndex(:58)` · `Show.tsx :: BancoHorasShow(:65)` | 10.379 / 12.281 | 1 / 1 | CABE — 2 |
| Escalas | 928 | `Escalas/Index.tsx :: EscalasIndex(:43)` · `Form.tsx :: EscalaForm(:50)+DIAS(:48)` | 8.679 / 11.544 | 1 / 2 | CABE — 2 |
| Colaboradores | 1062 | `Colaboradores/Index.tsx :: ColaboradoresIndex(:48)` · `Edit.tsx :: ColaboradorEdit(:47)` | 10.686 / 9.847 | 1 / 1 | CABE — 2 |
| Importações | 942 | `Importacoes/{Index(:58) · Create(:28) · Show(:44)+Row(:143)}` | 9.745 / 8.404 / 8.650 | 1/1/2 | CABE — 3 |
| Relatórios | 907 | `Relatorios/Index.tsx :: RelatoriosIndex(:105)` | 12.010 | 1 | CABE — 1 (⚠ 7 chaves `abort(501)`: forma sim, geração é a **12**) |
| Configurações | 986 | `Configuracoes/Index.tsx :: ConfiguracoesIndex(:55)+Row(:148)` · `Reps.tsx :: ReposIndex(:47)` | 9.099 / 10.686 | 2 / 1 | CABE — 2 |
| Fechamento · Conformidade | 959 / 959 | **sem receptor** | — | — | ⛔ threads 04 · 05 (W1–W4) |
| REP-P (celular) | — | **sem rota** | — | — | ⛔ thread 06 (W10) · e o build viola a ADR 0383 → **10 primeiro** |

**Total do eixo: 20 threads** (13–15 emitidas · 16a/16b + 15 outras medidas e **não** emitidas · 3 bloqueadas). Não emiti as 17 restantes de propósito: o §13 manda emitir o que entra em vaga, não a fila inteira — playbook antecipado é cache que envelhece.

## 4 · Inserir em `§7.threads` (JSON)
```json
[
 {
  "id": "13",
  "titulo": "Paridade de FORMA — Painel",
  "dono": "CL",
  "vaga": 4,
  "arquivo": "13-forma-painel.md",
  "prefixo": [
   "${PAGES}/Dashboard/Index.tsx"
  ],
  "nao_toca": [
   "${PAGES}/_components/",
   "Modules/Ponto/",
   "prototipo-ui/contrato/ponto-painel.contract.json"
  ],
  "depende_threads": [
   "ds-atomos/01",
   "ds-atomos/02"
  ],
  "provas": [
   {
    "tipo": "contem",
    "path": "${PAGES}/Dashboard/Index.tsx",
    "padrao": "variant=\"filter\""
   },
   {
    "tipo": "arquivo",
    "path": "${PAGES}/_components/PresenceStrip.tsx",
    "guarda": true,
    "nota": "PRESERVAÇÃO — seção que só existe na produção não é removida por paridade"
   }
  ]
 },
 {
  "id": "14",
  "titulo": "Paridade de FORMA — Espelho · lista",
  "dono": "CL",
  "vaga": 4,
  "arquivo": "14-forma-espelho-lista.md",
  "prefixo": [
   "${PAGES}/Espelho/Index.tsx"
  ],
  "nao_toca": [
   "${PAGES}/Espelho/Show.tsx",
   "${PAGES}/_components/MonthHeatmap.tsx",
   "Modules/Ponto/"
  ],
  "depende_threads": [
   "ds-atomos/01",
   "ds-atomos/03"
  ],
  "provas": [
   {
    "tipo": "contem",
    "path": "${PAGES}/Espelho/Index.tsx",
    "padrao": "shared/Toolbar"
   },
   {
    "tipo": "arquivo",
    "path": "prototipo-ui/contrato/ponto-espelho.contract.json",
    "guarda": true,
    "nota": "PRESERVAÇÃO — contrato vigente manda mais que o alvo"
   }
  ]
 },
 {
  "id": "15",
  "titulo": "Paridade de FORMA — Aprovações",
  "dono": "CL",
  "vaga": 5,
  "arquivo": "15-forma-aprovacoes.md",
  "prefixo": [
   "${PAGES}/Aprovacoes/Index.tsx"
  ],
  "nao_toca": [
   "${PAGES}/Intercorrencias/",
   "Modules/Ponto/",
   "resources/js/Components/shared/BulkActionBar.tsx"
  ],
  "depende_threads": [
   "ds-atomos/01",
   "ds-atomos/03"
  ],
  "provas": [
   {
    "tipo": "contem",
    "path": "${PAGES}/Aprovacoes/Index.tsx",
    "padrao": "BulkActionBar"
   },
   {
    "tipo": "contem",
    "path": "${PAGES}/Aprovacoes/Index.tsx",
    "padrao": "PageFilters",
    "guarda": true,
    "nota": "PRESERVAÇÃO — Toolbar entra onde não há moldura, não substitui o PageFilters"
   }
  ]
 }
]
```

## 5 · Inserir em `§7.decisoes` (JSON)
```json
[
 {
  "id": "W14",
  "pergunta": "Canon do label do KPI-filtro: accent 13.3px/400 (bundle, look CRM) ou 11px/600 uppercase muted (ADR 0110, documentado em shared/KpiCard.tsx)?",
  "respondida": false,
  "destrava": [
   "13"
  ]
 },
 {
  "id": "W11",
  "pergunta": "As 19 Tabela+usePagina viram DataGrid no cliente ou seguem LengthAwarePaginator no servidor?",
  "respondida": false,
  "destrava": [
   "16+"
  ]
 }
]
```
E no `§6 · RESÍDUO`, em texto: **W11** grade (trava 16+) · **W14** canon do label do KPI-filtro (trava 13). A **W9** ganha número (13 × 5+⋯) e segue aberta.

## 6 · O que este delta NÃO resolve
- **Não medi a produção.** O alvo é o protótipo; o diff real contra a Page viva é **T7** (`design-diff --compare --check` nos dois renders, prod deployada) e **não roda daqui**. Nada aqui afirma "igual".
- **Colisão de prefixo com 08/09/10, declarada:** as 4 ondas de DS de 2026-09-09 mexeram em `ponto-ui.jsx` (prefixo da **09**), `ponto-page/telas.jsx` (08/09) e `ponto-mobile.jsx` + host (**10**), cujo `base:` é `e86130722de1`. Quem abrir 08/09/10 **remede antes de escrever**.
- **A thread 10 segue violada:** `ponto-mobile.jsx` ainda tem selfie (`:38` · `:72-76` · `:184` · `:261`) contra a ADR 0383. Nenhuma thread de forma toca o REP-P antes disso.
- **Ciclo fechado sem pacote:** o build mudou e eu **não** regenerei (`gerar-payload-partes.mjs` quer os arquivos em disco). Comando no `github.md`.
