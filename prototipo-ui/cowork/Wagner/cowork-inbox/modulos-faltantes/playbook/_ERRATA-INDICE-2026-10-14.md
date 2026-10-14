---
sessao: "errata-indice"
titulo: "Módulos faltantes — a thread 01 está entregue; a prova suporte.contract.json deve ser suporte-log.contract.json"
executor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main 62cb42b3d7
---
# Errata do `00-INDICE.md` de Módulos faltantes (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho (§5 2026-09-24). A correção só vale depois que o
Cowork a fizer e o próximo retorno a trouxer. Este arquivo não é recibo, porque o placar lê só
`_saida-<id>.md`. Os recibos da 01 são `_saida-01.md`, `_saida-01b.md` e `_saida-01c.md`.

## O que o placar mostra

`node scripts/qa/placar.mjs --indice`, em `62cb42b3d7`:

```
ModulosFaltantes: entregue 3 de 4 · próximo 0 · em curso 1 · pendente 0 · bloqueada 0
  01 [em curso ] Contratos de tela das 3 telas que já existem (CV, Suporte, Vestuário) — governance/design/contracts/suporte.contract.json (arquivo ausente)
```

## Por quê

Suporte/Empresas e Suporte/Visao têm contrato próprio (`suporte-empresas`, `suporte-visao`), pela
thread 01 de telas-soltas. Um `suporte.contract.json` sobre as 3 telas seria segundo contrato das
mesmas telas; o Log ganhou o seu, `suporte-log.contract.json` (#9269, slug da tela, ADR 0286). O
pedido já está no `_saida-01b.md`; o índice vivo ainda pede o arquivo antigo.

Os 3 contratos passam no gate (rc=0 em cada): `comunicacao-visual` e `suporte-log` (#9269),
`vestuario-etiquetas` (#9283).

## O que mudar no índice

Na thread `01`, trocar a prova:

```json
{
  "tipo": "arquivo",
  "path": "governance/design/contracts/suporte-log.contract.json"
}
```

## Medição da troca

Apliquei a troca numa cópia local, rodei o placar e revertí o arquivo (`git status` limpo depois):

```
ModulosFaltantes: entregue 4 de 4 · próximo 0 · em curso 0 · pendente 0 · bloqueada 0
  01 [feito    ] Contratos de tela das 3 telas que já existem (CV, Suporte, Vestuário)
```
