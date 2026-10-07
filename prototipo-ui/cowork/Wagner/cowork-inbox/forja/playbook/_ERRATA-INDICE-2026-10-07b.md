---
sessao: "errata-indice"
titulo: "Forja — a thread 05 já estava entregue pelo #8508, e a prova dela compara dois campos que dizem coisas diferentes"
executor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main (origin/main de 2026-10-07)
---
# Errata do `00-INDICE.md` da Forja, nº 2 (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho (§5 2026-09-24), nem a errata nº 1
(`_ERRATA-INDICE-2026-10-07.md`, que trata da thread 07). Esta é uma errata nova.

## Thread 05
- **O que o índice diz:** a `nota_provas` é *"related_prototype do charter == fonte do contrato"*,
  e a §1 lista isso como divergência (D1).
- **O que está no `main`:** não há divergência. O charter aponta a âncora (`forja-page.jsx`, o hub);
  o contrato aponta o arquivo onde a copy mora (`forja-gantt.jsx`, o `FjGanttView` montado pelo hub).
  O contrato (`_nota_fonte`) e o charter (linha 181) declaram isso desde o #8508 (2026-10-02).
- **O que a troca literal faria:** medido, a anti-tautologia passa a acusar 6/6 copy do Gantt como
  "lida da tela". Detalhe em `_saida-05.md`.

Troca pedida na thread `05`:

```json
"provas": [{ "tipo": "contem", "path": "governance/design/contracts/forja-gantt.contract.json", "padrao": "mesma âncora" }],
"nota_provas": "o contrato declara (_nota_fonte) que forja-gantt.jsx é o arquivo da copy DENTRO da âncora forja-page.jsx do charter"
```

E na D1, se o Cowork quiser deixar o registro exato: a âncora é `forja-page.jsx`; a frase
"o contrato é corrigido para apontar pra ele" não se aplica, pelo motivo acima.
