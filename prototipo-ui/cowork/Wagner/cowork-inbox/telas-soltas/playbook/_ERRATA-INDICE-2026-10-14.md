---
sessao: "errata-indice"
titulo: "Telas soltas — a thread 01 está entregue; a prova home.contract.json aponta para um arquivo que não deve existir"
executor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main 62cb42b3d7
---
# Errata do `00-INDICE.md` de Telas soltas (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho (§5 2026-09-24). A correção só vale depois que o
Cowork a fizer e o próximo retorno a trouxer. Este arquivo não é recibo, porque o placar lê só
`_saida-<id>.md`. Os recibos da 01 são `_saida-01.md`, `_saida-01b.md` e `_saida-01c.md`.

## O que o placar mostra

`node scripts/qa/placar.mjs --indice`, em `62cb42b3d7`:

```
Telas soltas (sem roteiro próprio): entregue 6 de 7 · próximo 0 · em curso 1 · pendente 0 · bloqueada 0
  01 [em curso ] Contratos das 7 (derivados do alvo) — governance/design/contracts/home.contract.json (arquivo ausente)
```

## Por quê

`Home/Index` já tem contrato: `governance/design/contracts/dashboard-visao-geral.contract.json`
declara `tela: Home/Index` e passa no gate (`node scripts/contrato-de-tela.mjs --contract …` → rc=0).
Um `home.contract.json` seria o segundo contrato da mesma tela. Isso foi dito no `_saida-01.md`
(06/10), no #8934 e no `_saida-01b.md`; o índice vivo ainda pede o arquivo.

As outras 6 provas existem e passam no gate (rc=0 em cada): `kb` (#9282), `documentacao`,
`suporte-empresas`, `cobranca` (#8934), `perfil`, `suporte-visao` (#9259).

## O que mudar no índice

1. Na thread `01`, trocar a prova:

```json
{
  "tipo": "arquivo",
  "path": "governance/design/contracts/dashboard-visao-geral.contract.json"
}
```

2. Na tabela da §1, linha "Visão geral (`dash-legacy`)": coluna contrato de `—` para `✅ dashboard-visao-geral`.

## Medição da troca

Apliquei a troca numa cópia local, rodei o placar e revertí o arquivo (`git status` limpo depois):

```
Telas soltas (sem roteiro próprio): entregue 7 de 7 · próximo 0 · em curso 0 · pendente 0 · bloqueada 0
  01 [feito    ] Contratos das 7 (derivados do alvo)
```
