# tools/design-mcp — Oimpresso Designer (prancheta F1 · proposta)

> **Status:** 📋 **PROPOSTA · zero ondas executadas.** Adoção = **tooling → Tier 0 (Wagner) + ADR**.
> Estes arquivos são **referência/handoff** pra continuar a construção em qualquer máquina (git = SSOT, ADR 0239). **Nada aqui está plugado no `.mcp.json` nem altera o pipeline.**

## O que é

Uma ferramenta de design estilo Figma, **canon-locked**, pra criar/padronizar telas do ERP em **co-design humano + IA**, plugável na IA dev via **MCP**. Vira a **prancheta F1 (design)** do loop MWART/PROTOCOL, emitindo artefatos que `ui:lint` / `ui:judge-pr` / MWART F2..F4 já consomem.

## Arquivos

| Arquivo | É |
|---|---|
| [`EVOLUCAO-DESIGNER-ENGINE.md`](EVOLUCAO-DESIGNER-ENGINE.md) | **Documento-alvo.** Plano de evolução em 6 ondas: 1 tela → sistema inteiro, fonte única (lê o canon do repo, não copia), auditor = espelho do `ui:lint`, export-na-stack (`page.tsx`), superfície MCP. Decisões abertas no §12. |
| [`HANDOFF.md`](HANDOFF.md) | Handoff de contexto: visão, canon real (roxo 295 v4 / DS v6), governança, plano faseado. |
| [`canvas-prototype.html`](canvas-prototype.html) | **Protótipo funcional** (standalone, autocontido, runtime-verificado). Ponto de partida da onda 1. ⚠️ Embute tokens do **canon v3 antigo** (azul 220) — re-baseiar no canon vivo é a onda 2. |

## Próxima ação

Decisões §12 do `EVOLUCAO-DESIGNER-ENGINE.md` (escalar pro Wagner) → abrir ADR de adoção → onda 1 (servidor MCP + canvas compartilhando `project.json`).

> Para continuar em outra máquina: `git fetch && git checkout feat/design-engine-prancheta` (ou a branch que mergear esta proposta).
