---
sessao: "_saida-05"
thread: "05 · Gantt: alinhar charter e contrato à âncora decidida na D1"
dono: "[CL]"
data: 2026-10-07
tipo: recibo retroativo
entregue_em: "#8508 (2026-10-02)"
base_lida: wagnerra23/oimpresso.com@main da sessão (origin/main de 2026-10-07)
---
# _saida-05

## Entregue antes do playbook
O alinhamento já estava no `main` desde o #8508 (2026-10-02), cinco dias antes do índice da Forja.
Nenhum arquivo de código ou de contrato mudou nesta thread.

- `Forja/Roadmap/Gantt.charter.md:5` → `related_prototype: prototipo-ui/cowork/Wagner/forja-page.jsx`.
  É a âncora que a D1 escolheu.
- `governance/design/contracts/forja-gantt.contract.json` → `fonte: prototipo-ui/cowork/Wagner/forja-gantt.jsx`.
  O próprio contrato explica (`_nota_fonte`): o hub `forja-page.jsx` monta o Gantt com
  `<FjGanttView>` (view `trabalho`, sub-visão `gantt`), e o `FjGanttView` mora em `forja-gantt.jsx`.
  A fonte do contrato é o arquivo onde a copy está, **dentro da mesma âncora**.
- O charter diz o mesmo na seção do contrato (linha 181): *"A fonte do contrato é `forja-gantt.jsx`,
  o arquivo onde a vista `FjGanttView` mora dentro do hub que o `related_prototype` aponta — mesma
  âncora, não âncora nova."*

## Por que o contrato NÃO foi trocado para `forja-page.jsx`
A resposta da D1 dizia *"o contrato é corrigido para apontar pra ele"*. Medi a troca antes de
aplicar, rodando o gate do contrato com a mudança no lugar (e restaurando o arquivo depois):

| | `--contract forja-gantt.contract.json` | `--anti-tautologia` |
|---|---|---|
| `fonte` = `forja-gantt.jsx` (hoje) | limpo | 51 contratos com toda a copy ancorada na fonte · 18 com aviso |
| `fonte` = `forja-page.jsx` (D1 literal) | limpo | 50 · **19 com aviso**: o Gantt passa a acusar **6/6** copy "existe no ALVO e não na FONTE" |

A copy travada existe só em `forja-gantt.jsx` (contado: 0 ocorrências em `forja-page.jsx`, 1 em
`forja-gantt.jsx`, para cada uma das 4 frases conferidas). Apontar a fonte para o hub faria o
contrato parecer copy lida da tela, que é o defeito que a anti-tautologia existe para mostrar.

A âncora da D1 (`forja-page.jsx`) continua valendo: é o `related_prototype` do charter. O que não
se aplica é a parte da resposta que mandava mexer no contrato.

## Prova
A prova da ficha (`related_prototype` do charter igual à `fonte` do contrato) não fecha, e não deve
fechar: os dois campos dizem coisas diferentes (a âncora da tela e o arquivo da copy). A troca está
pedida em `_ERRATA-INDICE-2026-10-07b.md`.
