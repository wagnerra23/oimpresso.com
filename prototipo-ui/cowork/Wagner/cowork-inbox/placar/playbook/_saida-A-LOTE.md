---
sessao: "A-LOTE"
titulo: "design-diff-lote: rota por tela + medir um lado só — saída da thread"
executor: "[CL]"
data: 2026-10-06
base: 73ba361e01
pr: "#8754"
veredito: "entregue 1 de 1 na prova do json — `scripts/design/design-diff-lote.mjs` contém `--lado`."
---
# _saida A-LOTE

Placar: a única prova do json (`design-diff-lote.mjs` contém `--lado`) fica verde com o #8754.
O trabalho foi no dono (`design-diff-lote.mjs`), sem script novo. O `design-diff-lote.test.mjs`
do prefixo não foi criado: o script já tem `--selftest` embutido, rodado pela lane
`governance-script-tests.yml` ("design-diff-lote morde"). Abrir um segundo teste seria paralelo ao dono.

## Os 3 itens da ficha

| # | item | como ficou |
|---|---|---|
| 1 | mapa tela → rota vindo do protótipo | `rotasDaAncora` lê a tabela `const ROTAS = { "tok": { page: "Mod/Tela" } }` da âncora; `tokenDaTela` usa essa rota antes da rota do grupo. Âncora com `ROTAS` sem a page da tela sai sem rota, não cai no grupo. Vista que declara `data-page` diferente da tela esperada: NÃO MEDI. |
| 2 | `--lado design\|prod\|ambos` | default `ambos`. Um lado só não compara; o `resultado.json` registra `lado` e o sha do arquivo gravado. Com `--lado design` a rota viva não bloqueia, e vice-versa. Valor fora da lista sai 2. |
| 3 | sanidade de hash | `colisoesDeHash`: duas telas do mesmo módulo com `design.json` byte-idêntico viram NÃO MEDI, exit 2. Antes do render, `colisoesDeRota` já tira do plano duas telas (com âncora) do mesmo módulo no mesmo token, contando as irmãs fora do `--tela`. |

## Medido

- `--selftest`: 59 → **75/75**. Cada função nova tem fixture boa e ruim; e há o caso real: o
  `ROTAS` do `repair-page.jsx` dá 6 rotas distintas para as 6 telas medidas do Repair.
- `--dry` por tela:
  - `Repair/Index` → `route=rep-reparos (ROTAS da âncora)`;
  - `superadmin/Negocios/Index` → bloqueada: `route=superadmin` também abre Assinaturas,
    Comunicador, Configuracoes, Dashboard e Pacotes;
  - `Officeimpresso/Logs/Timeline` → bloqueada: colide com `Officeimpresso/Licencas/Index`
    (`Logs/Index` passa, via override `oi-log`).
- `--dry` no lote inteiro: executáveis **77 → 32 de 139** (versão do `main` 73ba361e01 × esta,
  as duas rodadas dentro de `scripts/design/`). As 45 que saem abriam a mesma rota de outra tela
  do mesmo módulo: superadmin 6, essenciais 6, assets 5, venda-pdv 4, hrm 4, governance 4,
  projects 3, compras 3, chat 3, ponto 3, officeimpresso 2, cli-novo 2, produtos 2, vendas 2.
- `scripts/design/_lib-charter.test.mjs`: verde (36 provas).

## Limites

- A colisão de hash e o `data-page` rodam no caminho de render (playwright + espelho servido);
  o selftest cobre as funções puras que decidem, não o render.
- Só o `repair-page.jsx` tem `ROTAS` hoje. Superadmin e Officeimpresso continuam sem rota por tela
  até o Cowork declarar a tabela na âncora (o espelho é só leitura) ou alguém pôr o token em
  `governance/design/targets/roles/<slug>.json`.
- O `design-smoke-ci.yml` roda o lote inteiro: as 45 telas acima passam a sair NÃO MEDI com motivo
  em vez de um par de medidas sem valor.
