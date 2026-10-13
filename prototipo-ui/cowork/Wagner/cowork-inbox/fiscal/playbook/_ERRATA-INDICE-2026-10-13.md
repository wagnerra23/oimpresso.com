---
sessao: "errata-indice"
titulo: "Fiscal — a 07 foi entregue (#8941); a prova do índice procura valida_de no arquivo errado"
executor: "[CL]"
data: 2026-10-13
base: wagnerra23/oimpresso.com@main 94c005aec5
---
# Errata do `00-INDICE.md` do Fiscal (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho (§5 2026-09-24). A correção só vale depois que o
Cowork a fizer e o próximo retorno a trouxer. Este arquivo não é recibo, porque o placar lê só
`_saida-<id>.md`. O recibo da 07 é o `_saida-07.md`, de 07/10.

## O que o placar mostra

`node scripts/qa/placar.mjs --indice`, em `94c005aec5`:

```
Fiscal: entregue 18 de 30 · próximo 0 · em curso 5 · pendente 7 · bloqueada 0
  07 [em curso ] Operacao + vigencia nas regras — Modules/NfeBrasil/Services/MotorTributarioService.php (não contém "valida_de")
  08 [em curso ] Simulador read-only — depende de 07 (não feita)
  09 [em curso ] Tabela ICMS/FCP por UF curada — depende de 07 (não feita)
  10 [em curso ] Sugestoes da IA — depende de 07 (não feita)
  15 [em curso ] Aceite do contador — depende de 07 (não feita)
```

Hoje 12 threads aparecem travadas pela 07: 08, 09, 10, 14, 15, 20, 25, 26 e 29 diretamente, e 16
e 28 por cadeia.

## Por quê

A 07 entrou pelo #8941 (`3cbc1b22c6`). O filtro de vigência ficou no Model, não no motor:
`NfeFiscalRule::scopeVigenteEm` (`NfeFiscalRule.php:96`) usa `valida_de`/`valida_ate`. O motor
chama esse escopo com `->vigenteEm($data)` (`MotorTributarioService.php:144`) e passa a data para
`buscarRegra`. Medido em `94c005aec5` com `grep -o <termo> <arquivo> | wc -l`:

| arquivo | `valida_de` | `vigenteEm` |
|---|---|---|
| `Modules/NfeBrasil/Services/MotorTributarioService.php` | **0** | 2 |
| `Modules/NfeBrasil/Models/NfeFiscalRule.php` | 9 | 0 (o método se chama `scopeVigenteEm`) |

O comportamento tem prova por teste. O run 38425543480 da lane `nfebrasil-pest` no `main` (head
`df33edac1d`) saiu verde, e o log mostra cada um destes casos com ✓:
`R-NFE-018 · versão vigente na data`,
`R-NFE-019 · editar gera versão, append-only`, `R-NFE-020b · exceção restrita à operação`,
`R-NFE-020c · CFOP ? por destino` (MotorTributarioServiceTest) e
`R-NFE-020 · default vira regra geral da Venda`, `R-NFE-020d · operação isolada por business`
(OperacaoFiscalTenantTest).

Ninguém vai escrever a string no motor só para a prova passar.

## O que mudar no índice

Na thread `07`, trocar a prova:

```json
{
  "tipo": "contem",
  "path": "Modules/NfeBrasil/Models/NfeFiscalRule.php",
  "padrao": "valida_de",
  "nota": "#8941: a vigência é o escopo scopeVigenteEm do Model; o motor o chama com ->vigenteEm($data)"
}
```

## Medição da troca

Apliquei a troca numa cópia local, rodei o placar e revertí o arquivo (`git status` limpo depois):

```
Fiscal: entregue 23 de 30 · próximo 6 · em curso 0 · pendente 1 · bloqueada 0
  07 [feito    ] Operacao + vigencia nas regras
  08 [feito    ] Simulador read-only
  09 [feito    ] Tabela ICMS/FCP por UF curada
  10 [feito    ] Sugestoes da IA
  15 [feito    ] Aceite do contador
```

Com a troca, 08, 09, 10 e 15 também saem como `feito`, porque cada uma já tem `_saida` e as provas
dela passam. Passam a `próximo` a 14 e as threads 20, 25, 26 e 29.
