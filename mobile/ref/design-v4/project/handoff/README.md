# Handoff — app mobile em 5 ondas

Pacotes gerados no projeto de design a partir de `wagnerra23/oimpresso.com@main`. **Proposta** — cada onda vira um PR, revisão Wagner + Luiz. Nada foi gravado no repositório.

| Onda | Pasta | O que é | Risco |
| --- | --- | --- | --- |
| 1 | `onda-1/` | Tokens do DS + IBM Plex + mapa único de status da OS | Baixo |
| 2 | `onda-2/` | OS (lista e detalhe) + Veículos reescritos em Oi*; OiForm, notify, OiStatus | Baixo |
| 3 | `onda-3/` | erp-ui e screen-container viram adaptadores no DS; status-tones; patches de hex/emoji | Baixo |
| 4 | `onda-4/` | Offline visível (a fila já existe), aviso Fiscal/Pagamentos; spec de notificações | Médio |
| 5b | `onda-5b/` | **Recomendada.** Ponto por link: abre o app de ponto (Capacitor) ou o REP-P web | Baixo |
| 5 | `onda-5/` | Ponto (REP-P) nativo: GPS, bater, justificar | Médio — 4 bloqueios no README |

Ordem: 1 → 2 → 3 → 4. A 5 depende só da 1 e pode subir antes se o ponto de campo for urgente.

Pastas `-tabs-` = `mobile/app/(tabs)/`; `-id-` = `[id]` (o pacote não aceita parênteses/colchetes no nome).

Não testado em aparelho nem com `tsc` — conferir tipos de `erp-queries` (ex.: `os.vehicle`, `h.dataEntrada`) no PR.

## Itens 01–04 da avaliação
- **01 Cores** → `onda-1/` (oi-theme.ts gerado do DS).
- **02 erp-ui / ScreenContainer** → `onda-3/` (adaptadores) + `onda-2/` (3 telas reescritas).
- **03 Toque ≥ 44** → `TOQUE.md` (patches em onda-2, onda-3, onda-4).
- **04 Ponto** → `onda-5b/` (link, recomendado) ou `onda-5/` (nativo). Decisão do Wagner.
