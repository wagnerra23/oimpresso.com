---
sessao: "errata-indice"
titulo: "Connector — o placar mostra 07, 08, 09 e 10 em curso porque a prova é `execucao`, não por trabalho pendente"
executor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main ea4b4f0985
---
# Errata do `00-INDICE.md` do Connector (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho: ele está registrado como verificado no ledger de
frescor, e editá-lo à mão derruba o check required do espelho (§5 2026-09-24). A correção vale
quando o Cowork a fizer e o próximo retorno a trouxer. Este arquivo diz o que mudar e por quê.

## Placar medido

`node scripts/qa/placar.mjs --indice` em `ea4b4f0985`:

```
Connector: entregue 6 de 10 · próximo 0 · em curso 4 · pendente 0 · bloqueada 0
  07 [em curso ] (indecidível) CONN-O7 · quem está usando cada credencial
  08 [em curso ] (indecidível) CONN-O8 · saúde com histórico de 14 dias
  09 [em curso ] (indecidível) Aba Documentação (PR-b da 04)
  10 [em curso ] (indecidível) Rodar UC-CONN-12 na lane MySQL (pulado em SQLite)
```

As quatro têm prova do tipo `execucao`, que o placar não decide: o avaliador de recibo ficou fora
do repo (ADR 0397). O trabalho está no `main`:

| thread | PR | recibo |
|---|---|---|
| 07 | [#8657](https://github.com/wagnerra23/oimpresso.com/pull/8657) | `_saida-07.md` — UC-CONN-21 verde, run 37313923243 |
| 08 | [#8667](https://github.com/wagnerra23/oimpresso.com/pull/8667) | `_saida-08.md` — UC-CONN-26/27 verdes, run 37314770035 |
| 09 | [#8379](https://github.com/wagnerra23/oimpresso.com/pull/8379) | `_saida-09.md` — UC-CONN-19 verde, run 36868265003 |
| 10 | [#8382](https://github.com/wagnerra23/oimpresso.com/pull/8382) | `_saida-10.md` — UC-CONN-12 verde, run 36868265003 |

## Pedido: trocar o array `provas` inteiro dessas 4 threads

```json
[
  {
    "id": "07",
    "provas": [
      {
        "tipo": "contem",
        "path": "${MOD}/Http/Controllers/ClientController.php",
        "padrao": "tokens_resto"
      },
      {
        "tipo": "arquivo",
        "path": "${MPAGES}/Api/_components/QuemUsa.tsx"
      }
    ]
  },
  {
    "id": "08",
    "provas": [
      {
        "tipo": "contem",
        "path": "${MOD}/Console/Commands/ConnectorHealthCommand.php",
        "padrao": "health-history.json"
      }
    ]
  },
  {
    "id": "09",
    "provas": [
      {
        "tipo": "contem",
        "path": "${MOD}/Http/Controllers/DataController.php",
        "padrao": "/connector/client?aba=docs"
      }
    ]
  },
  {
    "id": "10",
    "provas": [
      {
        "tipo": "contem",
        "path": ".github/workflows/connector-pest.yml",
        "padrao": "Modules/Connector/Tests/Feature/ApiClientsPanelTest.php"
      }
    ]
  }
]
```

## Medição das provas novas

- **Positivo:** com as 4 trocas aplicadas numa cópia fora do repo, o placar `--indice` deu
  `Connector: entregue 10 de 10` (medido em `c8777ae00a`).
- **Negativo:** na base do índice (`2fc50fa6dcb8`) nenhuma prova passa — os três `contem` em
  arquivos do Connector dão rc=1 em `git grep -F`, o `QuemUsa.tsx` não existe e o
  `connector-pest.yml` não existe.

**Ressalva:** a prova nova diz que o código está no `main`, não que o teste rodou. O run verde de
cada thread continua citado no seu `_saida-NN.md`.

## Fora desta errata (decisão [W], já listada nos recibos)

- Âncoras `quem-usa`, `confirm-quem-perde` (07) e `saude-ultima`, `saude-desvios` (08) fora do
  `connector-api.contract.json`.
- Na cópia do Cowork do `Index.casos.md`, o id UC-CONN-21 descreve outro caso (ver `_saida-07.md`).

## Depois do retorno

Com a troca, o placar esperado do Connector é `entregue 10 de 10`.
