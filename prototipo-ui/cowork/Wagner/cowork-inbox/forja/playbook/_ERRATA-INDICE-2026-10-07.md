---
sessao: "errata-indice"
titulo: "Forja — a prova da thread 07 procura `--storage`, e a flag entregue é `--clicar` repetível"
executor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main d9034eb990
---
# Errata do `00-INDICE.md` da Forja (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho (§5 2026-09-24). A correção vale quando o Cowork a
fizer e o próximo retorno a trouxer.

## Thread 07

A ficha deixava a escolha da flag ao PR ("o nome da flag sai do PR; se não for --storage, o
recibo corrige a prova"). O #8948 entregou o `--clicar` repetível, então a prova
`contem scripts/design-sync/alvo.mjs "--storage"` nunca casa e o placar mostra a 07 como
`próximo` com o trabalho feito.

Troca pedida no bloco json, na thread `07`:

```json
"provas": [{ "tipo": "contem", "path": "scripts/design-sync/alvo.mjs", "padrao": "passosDeClique" }]
```

`passosDeClique` é a função que normaliza `clicar` (string ou array) para a lista de passos. Ela
só existe com a cadeia: em `d9034eb990` (antes do #8948) o arquivo tem 0 ocorrências.

## Thread A1

O placar mostra a A1 `feito`, mas o `forja--roadmap-gantt.alvo.json` que ela listava como prova
só chega pela A1b. Se o placar aceita a A1 como feita, conferir se a prova do Gantt ainda está
nela ou se já passou para a A1b; não mexi nisso.
