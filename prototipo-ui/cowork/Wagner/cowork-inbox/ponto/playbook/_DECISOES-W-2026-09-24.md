# _DECISOES-W-2026-09-24 — Ponto: mudanças no 00-INDICE (Code → Cowork)

> **Estatuto:** pedido de edição do `00-INDICE.md` DO COWORK. O Code não edita o espelho
> (derruba o check required). Canon no repo: **ADR 0413** (PR #7913), que consolida D0–D4
> (14/09) + W1/W3/W7 (24/09).

## Respostas de [W]

| id | resposta | fonte |
|---|---|---|
| W1 | tabela nova `ponto_competencias`, gravada uma vez (sem UPDATE/DELETE) | [W] 2026-09-24 |
| W2 | `ponto.fechar` própria | D1 · 2026-09-14 |
| W3 | bloqueios aceitos ficam na linha da competência; **não bloqueiam AFD** | [W] 2026-09-24 |
| W4 | reabrir **não existe** na v1; correção por anulação com trilha | D1 · 2026-09-14 |
| W7 | **AFD → AEJ**; AFDT sai da exportação (Portaria 1510/2009); importação de AFDT legado fica | [W] 2026-09-24 |

## Edição pedida no §7 (playbook.json)

```json
{ "id": "W1", "respondida": true, "resposta": "tabela ponto_competencias gravada uma vez — ADR 0413" }
{ "id": "W2", "respondida": true, "resposta": "ponto.fechar própria — D1 / ADR 0413" }
{ "id": "W3", "respondida": true, "resposta": "bloqueios na linha da competência; não bloqueiam AFD — ADR 0413" }
{ "id": "W4", "respondida": true, "resposta": "sem Reabrir na v1 — D1 / ADR 0413" }
{ "id": "W7", "respondida": true, "resposta": "AFD → AEJ; AFDT sai da exportação — ADR 0413" }
```

- **thread 05**: `depende_threads: []` e `depende_decisoes: []` — D0 aprovou a Conformidade **read-only e independente** do fechamento. O "depende de 04 + W1" ficou superado.
- **thread 04**: ordem do "Quando destravar" = PR 1 migration `ponto_competencias` + guard append-only (sem UI) → PR 2 tela + contrato `ponto-fechamento`.
- **thread 12**: 1 relatório por PR, **AFD primeiro**; a chave `afdt` sai do catálogo no PR do AFD.
- §6 RESÍDUO: riscar W1–W4 e W7. Seguem abertas **W8, W9, W10**.

Nenhum `_saida-04/05/12` foi escrito: as três têm `provas: []`, e o `_saida` sozinho as contaria como entregues.
