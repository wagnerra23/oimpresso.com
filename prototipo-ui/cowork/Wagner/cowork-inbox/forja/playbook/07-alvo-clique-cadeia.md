---
sessao: "07"
titulo: alvo.mjs — clique em cadeia
dono: "[CL]"
base: 8d231ac7a13f
origem: _saida-A1.md §Não medido
---
# 07 · O alvo do Gantt precisa de 2 cliques; o alvo.mjs dá 1

`_saida-A1`: o Gantt é `trabvis=gantt` dentro da view `trabalho`. O `alvo.mjs` lê só a 1ª ocorrência de `--clicar` e só grava `oimpresso.route` + modo da sidebar no localStorage.

Escolha **uma** (a mais barata no código atual):
- `--clicar` repetível, executado em ordem, com espera de quietude entre cliques; ou
- `--storage chave=valor` repetível, gravado antes do load (aqui: `oimpresso.forja.view=trabalho` + `oimpresso.forja.trabvis=gantt`).

Teste com fixture: o 2º alvo só existe depois do 1º passo → mede; sem o 2º passo → `NÃO MEDI` rc=2. Os 3 alvos já gravados da A1 re-medem byte-idênticos (guarda).
