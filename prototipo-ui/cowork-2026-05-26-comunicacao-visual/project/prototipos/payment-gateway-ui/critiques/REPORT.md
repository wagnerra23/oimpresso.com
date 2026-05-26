# F1.5 Critique — PaymentGateway UI batch (3 telas)

**Disparado por:** ADR 0144 PaymentGateway · F0 brief 2026-05-19
**Avaliador:** Claude Cowork (este turn, [CD] role)
**Método:** mwart-comparative V4 + KB-9.75 alignment + 15 dimensões CLAUDE_DESIGN_BRIEFING §6
**Data:** 2026-05-19

---

## Veredito

| Tela | Persona | Gate | Score | Verdict | Maior gap |
|---|---|---:|---:|---|---|
| 1 · Cobrança | Eliana[E] + Larissa | ≥85 | **82** | 🟡 needs_refator (1 round) | Filtros estoura 1280px |
| 2 · Settings/Gateways | Wagner | ≥80 | **78** | 🟡 needs_refator (margem 2pts) | Tabela + cards drivers redundantes |
| 3 · Drawer Vendas · chip + modal | Larissa | ≥90 | **84** | 🔴 needs_refator (gap 6pts ALTO) | Modal hardcoded fora do canon |

**Nenhuma das 3 telas passa o gate na F1.** É esperado — F1.5 existe pra isso. Telas precisam de 1 round de refator pra ir pra F2 (Wagner screenshot approval).

## Tradução pra ação

### 🔴 P0 (bloqueia gate)

1. **Tela 1** — barra de filtros (chips TIPO + dropdowns + chips ORIGEM) estoura 1280px. Refator: 2ª linha pra dropdowns OU popover "Mais filtros". **+3pts esperados.**
2. **Tela 1** — KPI #4 contextual aparece/some, pula layout. Refator: slot fixo com default rotativo. **+2pts esperados.**
3. **Tela 1** — Status badges sem ícone (viola charter Boletos canon). Refator: ícone lucide por status. **+1pt esperado.**
4. **Tela 2** — Tabela superior + cards inferiores duplicam info de drivers. Refator: cards viram "drivers NÃO configurados". **+3pts esperados.**
5. **Tela 2** — Toggle ativo na tabela sem confirm. Trust L3 fail-fast. Refator: modal "Desativar X afeta Y cobranças". **+2pts esperados.**
6. **Tela 3** — Modal usa inline styles em vez de Tailwind+canon. Refator: reescrever com shadcn-grade. **+3pts esperados.**
7. **Tela 3** — Modal centralizado diverge do drawer-lateral canon Sells/Index. Decisão Wagner: drawer lateral OU aceita exceção como ADR per-tela. **+3pts esperados se virar drawer.**

### 🟡 P1 (sobe score além do gate)

8. Tela 1 — funil Lembrete/Cobrança ativa hardcoded; marcar como derivado ou remover até Onda 2
9. Tela 1 — drawer 520px linha digitável quebra; reorganizar row
10. Tela 1 — "Próx. janela remessa 18:30" hardcoded; condicional a gateway=c6 ativo
11. Tela 2 — KPI "Cobranças hoje" sem onClick; linkar pra Cobrança
12. Tela 2 — Health histórico 7d mock visual; trocar por tabela últimos 5 checks reais
13. Tela 2 — Alerta mTLS BCB precisa P0 vermelho se <30d
14. Tela 3 — Modal sem ESC/focus trap/scroll lock (WCAG 2.1)
15. Tela 3 — Chips usam oklch inline; trocar por classes Tailwind warm semantic
16. Tela 3 — Mock por hash uniforme; ajustar pesos pra realismo (paga 50% / pending 30% / etc)

### 🟢 P2 (polish · pode ficar pra F3)

17. Atalhos teclado (J/K/Esc/⌘K) ausentes nas 3 telas — gap consistente vs Vendas/Index A+ 9,75
18. Persistência localStorage filtros (Tela 1)
19. Sort clicável nas tabelas (Tela 1 + 2)
20. Filtro/busca em Settings (5 hoje, escala pra 50+)
21. Loading skeleton em tabelas/cards

---

## KB-9.75 alignment

Vendas/Financeiro PR #1064 chegou a 9.75 com 7 elementos: tabular-nums + densidade + chip composto + atalhos J/K + cheat-sheet ? + comentários inline + AI panel.

PaymentGateway batch tem **3 de 7**: tabular-nums ✅, densidade ✅, chip composto ✅. Faltam **4**: atalhos teclado, cheat-sheet, comentários, AI panel.

Decisão pendente Wagner: atingir 9.75 em F3 é meta? Se sim, F1.5 não precisa fechar isso (entra na F3). Se não, fica 8.4 médio depois do refator e segue pra F2.

---

## Próxima ação

[CC] aplica refatores P0 (1-7) na ordem listada. Re-roda critique. Score esperado pós-refator:

| Tela | Antes | Após P0 | Status |
|---|---:|---:|---|
| 1 | 82 | **88** | ✅ passa ≥85 |
| 2 | 78 | **83** | ✅ passa ≥80 |
| 3 | 84 | **93** | ✅ passa ≥90 |

Se Wagner quer ALTO score (9.5+) → F3 inclui KB-9.75 elements (atalhos, cheat, comentários, AI panel).

Após refator P0 + re-critique passar gate → **F2 screenshot approval** (Wagner aprova em sequência Tela 1 → 2 → 3 em SYNC_LOG.md) → **F3 backend** (Onda 4 ADR 0144 — drivers + UI Inertia).
