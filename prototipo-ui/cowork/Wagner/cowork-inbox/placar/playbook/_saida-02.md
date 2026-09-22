---
sessao: "02"
titulo: /onda modo thread — caminho quebrado e índice ausente vira PARAR
executor: "[CC]"
base: 701f40c6ec66
pr: "#7737"
---
# _saida 02

## Os 3 defeitos, fechados

| # | defeito | como ficou |
|---|---|---|
| 1 | `--thread $NN` usava variável que o harness não define | `$1`=módulo · `$2`=`--thread` · `$3`=NN, resolvidos em `MOD`/`NN`/`IDX` no topo do bloco |
| 2 | `$1` cru no caminho, mas os diretórios são minúsculos | `MOD=$(echo "$1" \| tr '[:upper:]' '[:lower:]')` |
| 3 | índice ausente não mandava parar | portão `test -f "$IDX" \|\| { echo "NAO MEDI…"; exit 1; }` + parágrafo `PARE` logo abaixo |

## Os dois testes exigidos

O bloco foi **extraído do próprio `onda.md`** e executado — medi o que está no arquivo, não uma cópia.

**(a) `/onda Patrimonio --thread 01` abre a thread certa** — e em MAIÚSCULA, que era o caso que falhava:
```
Patrimonio: entregue 0 de 1 · próximo 1 · em curso 0 · pendente 0 · bloqueada 0
  01 [proximo  ] Tenant na subconsulta de revoke (vazamento Tier 0) — sem _saida
PRÓXIMO: Patrimonio/01 Tenant na subconsulta de revoke (vazamento Tier 0) [CL]
prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/01-tenant-subquery-revoke.md
rc=0
```
Um módulo, uma thread. Antes, este mesmo comando varria os 13.

> Testado com `Patrimonio` e não com `placar` porque o índice do `placar` ainda não está no `main` —
> chega pelo #7736 (import do handoff 31). O defeito exercitado é o mesmo: maiúscula + módulo único.

**(b) `/onda inexistente --thread 01` para com NÃO MEDI, sem varrer outro módulo:**
```
NAO MEDI: indice 'inexistente' ausente no main - o pacote do Cowork nao foi importado. Nada executado.
rc=1
```
Uma linha e sai. **Isto mudou durante a execução:** a 1ª versão usava `|| echo` puro — imprimia o
aviso e **seguia**, emitindo mais um `NÃO MEDI` do placar e um erro de `ls`. Passou a `|| { …; exit 1; }`.
O furo só apareceu porque o teste foi rodado; lido no diff, o `|| echo` parece correto.

**(c) controle (não exigido, rodado mesmo assim):** módulo real + thread inexistente (`fiscal 99`)
não é silenciado pelo portão — quem responde é o placar, com o `NÃO MEDI` dele. O portão cobre
índice ausente, não thread ausente, e essa fronteira é deliberada.

## Provas da spec
- `onda.md` **não contém** `--thread $NN` — `grep -c` = 0
- `onda.md` **contém** `PARE` — linha 71
- 0 bytes de controle · UTF-8 sem BOM · sem CRLF

## Escopo
Um arquivo no prefixo (`.claude/commands/onda.md`) + este recibo. Zero `scripts/qa/**`, zero
`pedido.mjs`, zero playbook — o `nao_toca` foi respeitado.
