# _DECISOES-W-2026-10-07 — Forja (Cowork → Code)

> **Fonte:** [W] 2026-10-07, textual: *"Forja você decide o melhor"* — delegação explícita ao [CC]. Critério registrado abaixo para auditoria.
> **Origem:** `_saida-R1.md` §"Continuam com [W]".

| id | decisão | por quê |
|---|---|---|
| D-GANTT | **Fica a biblioteca `@svar-ui/react-gantt`**, vestida com os tokens do DS (cor de barra por status, tipografia do ramp, sem cor crua). O corpo `.fj-g-*` do protótipo vira **alvo de forma**, não código a portar. | Gantt próprio = arrasto, zoom, dependências e a11y pra manter sozinho. A moldura (#6624) já existe e o smoke (#6644) passa. |
| D-TOQUE | **Mínimo 24×24 px em todo botão** (WCAG 2.2 AA, critério 2.5.8). 44×44 só nas telas da persona Técnico (tablet/celular) — a Forja é de escritório. Ícone pequeno mantém o desenho; cresce a área clicável (padding/`::before`), não o glifo. | Corrige os 81 de 118 sem refazer layout. |
| D-SUPERF | As 8 superfícies sem receptor (`issue-drawer`, `cmdk`, `notifs`, `novo-issue`, `runbook`, `handoff`, `ia`, `rag`) **não ganham thread agora**. Ordem quando ganharem: `issue-drawer` → `cmdk` → `novo-issue`; `ia`/`rag` ficam por último (dependem de Jana). | Drawer de detalhe é o padrão PT-02 e destrava o resto. |

## Edição pedida no json
```json
[{ "id": "D-GANTT", "respondida": true, "resposta": "mantém @svar-ui/react-gantt com tokens do DS; .fj-g-* é alvo de forma" },
 { "id": "D-TOQUE", "respondida": true, "resposta": "24x24 mínimo (WCAG 2.5.8) via área clicável; 44 só persona Técnico" },
 { "id": "D-SUPERF", "respondida": true, "resposta": "sem thread agora; ordem issue-drawer > cmdk > novo-issue; ia/rag por último" }]
```
