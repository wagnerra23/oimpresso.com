---
sessao: "errata-indice"
titulo: "HRM — o retorno (55) perdeu o bloqueio da thread 11 que o #9377 tinha aplicado"
executor: "[CC]"
data: 2026-10-15
base: wagnerra23/oimpresso.com@main b5f2d68c6c
---
# Errata do `00-INDICE.md` do HRM (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho (§5 2026-09-24). A correção só vale depois que o
Cowork a fizer e o próximo retorno a trouxer. Este arquivo não é recibo.

## O que aconteceu

O #9377 (14/10, opt-in [W]) aplicou no espelho e enviou ao Cowork a errata de 13/10, que põe um
`bloqueio` na thread 11. O índice que veio no handoff (55) foi reformatado (JSON em várias linhas)
e trouxe todas as outras mudanças, mas **não tem esse campo** na thread 11. Conferido linha a
linha: de tudo que o #9377 acrescentou, só esta linha falta no retorno.

## A correção

No bloco json do `00-INDICE.md`, thread `"id": "11"` ("Fim do topnav Blade + limpeza O8"),
acrescentar logo depois de `"arquivo": "11-topnav-legado.md",`:

```json
"bloqueio": "até a Folha ter Page (thread 10, _DECISOES-W-2026-10-13): o nav_hrm tem 7 consumidores com rota viva, incluindo as 6 blades de payroll (_saida-11b)",
```

## Por que importa

Sem o campo, o placar mostra a 11 como executável. Ela não é: apagar o `nav_hrm` hoje quebra as
6 blades de payroll que ainda estão no ar (`_saida-11b`).
