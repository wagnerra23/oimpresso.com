---
name: feedback-auditar-components-antes-de-onda
description: ANTES de planejar roadmap multi-onda de implementação, auditar diretório `Pages/<Modulo>/_components/` do módulo alvo. Componentes podem já estar mounted no drawer/sheet do módulo, economizando ondas inteiras.
type: feedback
---

# Auditar `_components/` antes de planejar ondas — economiza ondas inteiras

**Regra:** Antes de propor um roadmap N-ondas pra portar features Cowork pra Inertia/React, **rodar grep de uso** dos prováveis componentes em `resources/js/Pages/<Modulo>/_components/`. Se já existem e estão `mount`ados (geralmente no `Sheet.tsx` ou `Drawer.tsx`), aquela onda já está done.

**Why:** Sessão 2026-05-18 ciclo KB-9.75 — Wagner pediu "continuar as ondas" depois de aprovar sync prototipo-ui. Plano original previa **7 ondas implementação** (R1-R4 Vendas + R1-R3 Financeiro + cross-link). Audit revelou que `Sells/_components/` já tinha:

| Componente | LOC | Onda original | Mount em SaleSheet |
|---|---|---|---|
| SaleAiPanel.tsx | 273 | 2 — IA copiloto | ✅ |
| SaleAuditTrail.tsx | 278 | 3 — Curadoria | ✅ |
| SaleItemComments.tsx | 217 | 3 — Curadoria | ✅ |
| SaleLinkifier.tsx | 115 | 7 — Cross-link | ✅ |
| SaleMessagePreview.tsx | 167 | 4 — Distribuição | ✅ |
| SalePresentationMode.tsx | 155 | 4 — Distribuição | ✅ |
| SaleTranscriptPDF.tsx | 272 | 4 — Distribuição | ✅ |

→ **4 ondas Vendas já estavam done.** Resultado: 7 ondas planejadas viraram **3 ondas reais** (Financeiro R1+R2+R3). Economia: ~30-40h de trabalho que seria duplicação.

**How to apply:**

1. **Antes de mandar roadmap pro Wagner**, rodar:
   ```bash
   ls -la resources/js/Pages/<Modulo>/_components/
   ```
   E pra cada componente candidato:
   ```bash
   for c in CompA CompB CompC; do
     echo "=== $c ==="
     echo "Lines: $(wc -l < resources/js/Pages/<Modulo>/_components/$c.tsx 2>/dev/null || echo N/A)"
     echo "Used in Index: $(grep -c "$c" resources/js/Pages/<Modulo>/Index.tsx)"
     echo "Used in Sheet: $(grep -c "$c" resources/js/Pages/<Modulo>/_components/<Modulo>Sheet.tsx 2>/dev/null || echo 0)"
   done
   ```

2. **Marcar no roadmap** o que JÁ está done com PR de origem ("done pré-auditoria · PR #NNNN").

3. **Recalcular total de ondas** apenas com gaps reais — NÃO duplicar trabalho.

4. **Reportar achado pro Wagner** ANTES de implementar (transparência) — ele aprova o novo plano enxuto.

**Triggers de uso da regra:**

- User pede "continue as ondas", "implemente o KB-X.X em <modulo>", "porte protótipo Cowork pra real"
- Multi-feature implementation planning
- Após merge de sync visual (PR de prototipo-ui)

**Anti-pattern (a evitar):**

- Confiar no charter como ground-truth do que está implementado — charter pode estar desatualizado vs `_components/` filesystem real
- Implementar do zero sem checar se já existe — duplica trabalho + cria divergência canon

**Histórico:**

- 2026-05-18 — instalado após sessão ciclo KB-9.75: auditoria revelou 7/7 components Vendas já mounted, economizou 4 ondas (PR #1068/#1069/#1070).
