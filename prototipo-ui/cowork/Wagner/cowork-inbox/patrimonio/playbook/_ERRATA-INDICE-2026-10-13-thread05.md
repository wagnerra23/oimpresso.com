---
sessao: "errata-indice"
titulo: "Patrimônio — a thread 05 (retenção LGPD) foi descartada por [W] em 13/10; a ADR 0430 foi recusada"
executor: "[CL]"
data: 2026-10-13
base: wagnerra23/oimpresso.com@main 94c005aec5
---
# Errata do `00-INDICE.md` do Patrimônio — thread 05 (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho (§5 2026-09-24). A correção vale quando o Cowork a
fizer e o próximo retorno a trouxer. Este arquivo não é recibo: o placar lê só `_saida-<id>.md`.

## A decisão

[W] em 2026-10-13, no chat, ao ver pronto o executor da thread 05:

> *"não pode ter o LGPD no patrimonio isso é errado"*
> *"descarta o branch e reverte a ADR 0430"*

Isso revoga a D-LGPD-REABRE (07/10). Feito no lado do Code:

- o executor `assetmanagement:retention-purge` foi descartado sem push e sem PR;
- a ADR 0430 passou de `proposto` para `recusado`, e uma lápide nova no §5
  (`memory/licoes-rejeitadas.md`) revoga a emenda de 13/10. A lápide de 2026-07-27 volta a valer
  também no Patrimônio: num ERP o dado tem dever de guarda e o controle é por permissão de acesso.
  Está no PR #9268.

Fica a correção das chaves `am_*` do `retention.php` feita na 05a: é fato de schema, não decisão de
retenção. O arquivo segue `enabled=false` e sem executor.

## O que o placar mostra hoje

```
05a [feito    ] ADR sucessora da lápide 27/07 + corrigir as 4 chaves mortas do retention.php
05 [proximo  ] Job de retenção LGPD (nasce desligado, anonimiza, nunca apaga) — sem _saida; …LgpdComplianceTest.php (não contém "retention-purge")
```

A 05 aparece como próxima e a sua prova pede o texto `retention-purge` no teste. Uma sessão que
pegar a fila vai construir de novo o que [W] mandou descartar.

## O que mudar no índice

**1. Decisões** — trocar a resposta da `D-LGPD-REABRE`:

```json
{
  "id": "D-LGPD-REABRE",
  "dono": "W",
  "respondida": true,
  "resposta": "[W] 2026-10-07 reabriu a 05. REVOGADA por [W] em 2026-10-13: 'não pode ter o LGPD no patrimonio isso é errado'. ADR 0430 recusada (PR #9268); vale a lápide de 2026-07-27."
}
```

**2. Thread 05** — acrescentar `bloqueio` (o placar passa a mostrar `bloqueada` em vez de `proximo`)
e esvaziar a prova, que mede um arquivo que não vai existir:

```json
{
  "id": "05",
  "bloqueio": "descartada por [W] 2026-10-13 — ADR 0430 recusada (PR #9268). Não há retenção LGPD no Patrimônio.",
  "provas": []
}
```

**3. Tabela da seção de threads** — a linha da 05:

```
| 05 | **Retenção LGPD — DESCARTADA por [W] 13/10** (ADR 0430 recusada) | — | 0 | 0 | — | 0 | não executável |
```

**4. Ficha `05-retencao-lgpd.md`** — o aviso do topo diz *"REABERTA por [W] em 2026-10-07"*. Trocar
por: *"DESCARTADA por [W] em 2026-10-13 (ADR 0430 recusada, PR #9268). Não executar."*

Com isso o placar deixa de oferecer a 05 e nenhuma thread fica dependendo dela.
