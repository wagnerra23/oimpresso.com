<!-- HANDOFF · Cowork [CC] → Claude Code [CL] · cole 1× -->
# Financeiro · Onda 1 do "Adversário" (3 casos críticos) — re-baselinado contra @main

> **Origem:** crítica adversarial de design do Financeiro (`Financeiro - O Adversário (crítica de design).html`, Cowork). [W] aprovou levar os 3 **críticos** pra produção.
> **⚠ LEITURA OBRIGATÓRIA ANTES:** eu (CC) **li o `@main` (commit `f8cf7cd`) neste turno** e a maior parte do que o screenshot de [W] mostrava **já está consertada no main**. O print de produção é de um **build atrás**. Abaixo, o que é `✓lido @main` vs `⚠confirmar`.

---

## TL;DR — a maior alavanca é DEPLOYAR o @main

1. **Deploy do `@main`** já resolve: o diff de valor da auditoria com vírgula (Caso 02) e a sparkline/KpiCard vermelhos no saldo negativo (Caso 03 parcial). **Faça isso primeiro** e re-tire o screenshot — pode matar metade da lista.
2. Depois, **2 patches pequenos** (resíduos reais no main) + **1 verificação** (Caso 01).

---

## CASO 02 · locale de número na auditoria — **quase tudo já no main**

**✓lido @main** `resources/js/Pages/Financeiro/Unificado/_components/FinAuditTrail.tsx`:
- O diff de valor **já usa `brl()`** (`toLocaleString('pt-BR', {style:'currency'})`) → renderiza `R$ 357,20 → R$ 380,00`. O `357.20` do print é **build velho**, não o main.
- **Resíduo real:** o **percentual** ainda usa ponto:
  ```tsx
  // hoje (@main):
  {e.diff.pct.toFixed(1)}%            // → "+6.4%"  (ponto en-US)
  // patch:
  {e.diff.pct.toFixed(1).replace('.', ',')}%   // → "+6,4%"
  ```
  Uma linha. Mesmo arquivo, no bloco `.fin-audit-diff .diff-pct`.

---

## CASO 03 · saldo previsto negativo sem alarme — **parcial no main**

**✓lido @main** `resources/js/Pages/Financeiro/Unificado/Index.tsx` (componente do KPI strip):
- A **sparkline já reage**: `<FinSparkline tone={kpis.saldo_previsto >= 0 ? 'pos' : 'neg'} …/>` (vira vermelha).
- O **`KpiCard` alternativo já reage**: `tone={kpis.saldo_previsto >= 0 ? 'success' : 'danger'}`.
- **Gap real:** o **número grande** do hero `.fin-stat-hero` **não** recebe cor de alarme:
  ```tsx
  // hoje (@main, ~L669):
  <b>{brl(kpis.saldo_previsto)}<DeltaBadge pct={kpis.delta_pct?.saldo_previsto} /></b>
  // patch — cor neg + chip quando negativo:
  <b className={kpis.saldo_previsto < 0 ? 'fin-num-neg' : undefined}>
    {brl(kpis.saldo_previsto)}<DeltaBadge pct={kpis.delta_pct?.saldo_previsto} />
  </b>
  // e no <small> do hero, quando saldo_previsto < 0, anexar um chip:
  //   <span className="fin-hero-alarm">projeção negativa</span>
  ```
- **CSS** (no bundle do Financeiro — `cowork-canon-financeiro-bundle.css` ou onde mora `.fin-stat-hero`):
  ```css
  .fin-stat-hero b.fin-num-neg{ color: var(--neg, oklch(0.55 0.18 25)) !important; }
  .fin-stat-hero .fin-hero-alarm{
    display:inline-flex; align-items:center; margin-left:7px; padding:1px 7px;
    border-radius:99px; font-size:11px; font-weight:600; text-transform:none;
    background: color-mix(in oklab, var(--neg) 14%, var(--surface));
    color: var(--neg); box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--neg) 30%, transparent);
  }
  ```
  > ⚠ **Confirmar no @main antes de aplicar:** li o trecho do hero na **cópia local** do Cowork (pode estar stale); confirme o JSX exato do `.fin-stat-hero` em `Index.tsx@main` e o arquivo CSS que define `.fin-stat-hero`. O conserto é aditivo (só pinta o negativo).
  > **Gate de cor:** `var(--neg)` é token semântico do tema (não cor crua) → passa no `ui:lint`/`conformance-gate`. Não introduza hex.

---

## CASO 01 · status contraditório ("Resolver" em item liquidado) — **VERIFICAR, provável não-issue no main**

**✓lido @main** `FinTroubleshooter.tsx` + uso em `Index.tsx`:
- O `<FinTroubleButton onClick={…} />` do **rodapé da LISTA** abre `<FinTroubleshooterDialog open={…} />` **sem `suggestedId`** → é o helper **genérico** ("? Resolver · 4 fluxos"), não um alarme por-título.
- O print de [W] mostra **"? Resolver: Saldo do extrato…" no rodapé do DRAWER** (com título sugerido). Esse padrão **não aparece** no `FinTroubleButton@main` (que não recebe label/sugestão). **Hipótese forte: build antigo** (o protótipo Cowork tinha esse "Resolver: <título>" por estado; o main parece já ter migrado pro genérico).

**Ação do Code (não assumir — verificar):**
1. No `Index.tsx@main`, procure o rodapé do **Sheet/Drawer** do título (não o da lista). Existe ali um botão de troubleshooter que computa `suggestedId`/label por estado do título?
   - **Se NÃO existir** → Caso 01 já está resolvido no main; **só faltava deploy**. Marca como resolvido.
   - **Se existir** e ele sugere divergência mesmo com `paid_at != null` → aplique o guard: título liquidado/conciliado **não** sugere divergência; o botão vira guia neutra (sem `suggestedId`, label genérico "? Resolver problemas comuns").
2. Padrão de referência no protótipo Cowork (`financeiro-output.jsx`, `FinTroubleButton`): `if (row.paid_at) return null;` no cálculo do `suggested` → botão neutro (`.fin-trouble-btn--guide`).

---

## Ordem sugerida
0. **Deploy `@main`** (resolve Caso 02-valores + Caso 03-sparkline/KpiCard; provavelmente Caso 01).
1. Patch **Caso 02-pct** (1 linha, `FinAuditTrail.tsx`).
2. Patch **Caso 03-hero** (`Index.tsx` + CSS do `.fin-stat-hero`) — confirmar JSX no main antes.
3. **Verificar Caso 01** no rodapé do drawer; aplicar guard `paid_at` só se o "Resolver: <título>" existir no main.

## O que NÃO fazer
- ❌ Não reescrever `FinAuditTrail` "pra consertar o locale" — o diff de valor **já está certo no main**; só o `%` precisa.
- ❌ Não introduzir cor crua/hex (gates `ui:lint` R1 / `conformance-gate`); use `var(--neg)`.
- ❌ Não tratar o screenshot de [W] como verdade do código — é build deployado, atrás do main.

---
_CC não commita (read-only no git). Esta é a proposta; o Code valida contra o `main` e aplica. Onda 2 (sérios + refinos do Adversário) vem depois, no protótipo primeiro._
