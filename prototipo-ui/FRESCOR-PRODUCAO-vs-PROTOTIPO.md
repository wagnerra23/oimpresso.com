# FRESCOR — Produção (`resources/js/Pages/**`) vs Protótipo (Cowork export)

> **Canal reverso Code → Design.** Onde a **produção passou** o protótipo, o design deve **puxar o estado vivo** (parar de tratar o export como fonte). Onde a **produção está atrás**, é catch-up real (build).
> Mantido pelo **Claude Code** ao fim de cada sync/Fase 1. Lido pelo **Cowork** no início da sessão (junto de `CODE_NOTES.md`).
> Regra-mãe: **camada de design** = Cowork manda; **memory/ADR/resources/** = repo manda, design só lê (ver ADR-proposta `2026-06-23-prototipo-ssot-unico-com-historico`).

**Legenda:** 🔵 produção À FRENTE (design puxa) · 🟠 produção ATRÁS (catch-up real) · ⚪ fundação/empate de governança · ✅ em paridade.

## Quadro (Fase 1 · 2026-06-23)

| Tela / camada | Protótipo (export) | Produção viva | Frescor | O design precisa saber |
|---|---|---|---|---|
| **Atendimento/CaixaUnificada** | `inbox-page.jsx` (15/mai, @88%) | `Atendimento/CaixaUnificada/` charter v19, Ondas 1–4 + PR-1..10 | 🔵 **À FRENTE** | A Caixa viva REALIZA tudo do protótipo + filas em DB, broadcast LGPD, IA real, SLA, Customer360. **É o OURO (LEI 18/jun): não repintar.** Único catch-up opcional: `linkifyMessage` (cross-refs `#os4821` clicáveis). |
| **Cliente (Crm)** | bundle `clientes-975.jsx` + docs US-078 | `Cliente/` Index/Show/Edit/Create/Ledger/Map · US-CRM-063..076 done | 🔵 **À FRENTE** | Cliente já passou o protótipo (drawer 760 ADR 0179, tabs multi-type, IA server-side). US-078 multi-endereços (Tela 1) **já integrado**. Não propor split/layout antigo. |
| **PageHeader (fundação)** | `pageheader-canon-v3/` (4 HTMLs conflitantes) | `Components/PageHeader/` v3.8 (3 zonas, roxo 295) | ⚪ **~85%, já é canon** | Canon vivo = roxo `oklch(0.55 0.15 295)` universal. **Descartar** `index.html` (hue-per-grupo) e `3-familias.html` (navy) — regridem. Só `b-v2-roxo-kpis.html` reflete o vivo. |
| **Sidebar/Shell (fundação)** | `sidebar-v3-unificado/visual-source.html` | `AppShellV2` + Sidebar (8 grupos, glyph) | ⚪ **empate de governança** | Vivo evoluiu além em 3 eixos (8 grupos, glyph, sem topbar) — **não regredir**. 3 itens de catch-up: tema light/dark (**trava: precisa [W] desempatar dark `cockpit.css` × light UI-0014**), Cmd+K visível, seção Fixados. |
| **Compras grade-matrix** | `compras-grade-matrix/page.jsx` | `Compras/components/GradeMatrixInput.tsx` (ÓRFÃO) | 🟠 **ATRÁS** | Único gap de produto real. Componente existe mas **não plugado** em tela; falta endpoint backend (matriz tam×cor). Valor alto p/ vestuário (Larissa/ROTA LIVRE). Gated: charter `/compras` lista inline como anti-hook; caller canônico = `Purchase/Create.tsx`. |

## Como o design usa isto
1. Antes de exportar uma tela 🔵, **leia o estado vivo no `main`** (`resources/js/Pages/<Mod>/`) — o export local é fotocópia que envelhece (PORTÃO 1 do `STATUS.md`).
2. Telas 🟠 são as que valem export novo (produção precisa do design).
3. ⚪ fundação = PR sequencial isolado, nunca em paralelo com telas (incidente #2495).

---
_Semente: Fase 1 de `aplicar-prototipo` 2026-06-23. Detalhe por parte: `memory/requisitos/{Atendimento,Crm,Compras,_DesignSystem}/*-gap.md`._
