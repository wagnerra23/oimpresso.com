---
slug: metodo-9-75-financeiro
title: "Método KB-9.75 — aplicado ao Financeiro"
type: roadmap
authority: draft-aguardando-wagner
lifecycle: proposto
session_date: '2026-05-31'
quarter: 2026-Q2
related:
  - '0093'
  - '0104'
  - '0107'
  - '0114'
  - '0190'
pii: false
---

# Método KB-9.75 — aplicado ao Financeiro

> **🟡 DRAFT pra Wagner — corrigido após pré-flight no código real.**
>
> **Origem (2026-05-31):** o `Método 9.75 Financeiro.html` do Cowork **não veio no export** (snapshot 28/mai; arquivo mais novo). Wagner pediu reconstrução ancorada no módulo real.
>
> **🔴 CORREÇÃO IMPORTANTE (pré-flight no código real, 2026-05-31):** a 1ª versão deste doc usou a [BRIEFING.md](BRIEFING.md) de **2026-05-20** + [DOC_TELAS_E_SCORE.md](DOC_TELAS_E_SCORE.md) de **2026-04-25** — **ambas obsoletas**. Ao ler `UnificadoController.php` + `Unificado/Index.tsx` reais, descobri que as **Ondas 22-29 (US-FIN-021..029, PRs E/F/G/H/I/J, 2026-05-25)** já fecharam **quase todo o Refino #1-#3** que eu ia propor. **Não implementei nada** — seria re-fazer feature existente (anti-padrão T-AP-4 das lições F3). Este doc agora reflete a **realidade do código**, não a briefing velha.

---

## 1. Ancoragem — o Financeiro JÁ está perto de 9,75

O Financeiro é o módulo **mais maduro do ERP** e avançou muito entre 20/mai e 25/mai. Evidência no código real (`Modules/Financeiro/Http/Controllers/UnificadoController.php` + `resources/js/Pages/Financeiro/Unificado/Index.tsx`):

| O que eu ia propor (R1-R3) | Estado REAL no código | Onda/US |
|---|---|---|
| Aging buckets + cor | ✅ `agingBreakdown()` + chips filtro 5 buckets (lt30/30-60/60-90/gt90/gt180) | PR E · US-FIN-022 |
| J/K + atalhos teclado | ✅ J/K/↑↓/Space/Enter/Esc + ⌘K + `/` + B + N/R/P (Index.tsx:936-1005) | PR G · US-FIN-024 |
| Anexos UI (listar/baixar) | ✅ `listarAnexos()` + `baixarAnexo()` + remove | US-FIN-026 |
| Diff "ver edições" | ✅ `auditTrail()` com `{field, from, to}` (FinAuditTrail) | Onda DB |
| Resumir IA | ✅ `FinMonthDigest` / `FinMonthResume` | Onda 7c |
| Auto-sugerir valor | ✅ `sugerirValor()` (último + média + count por contraparte) | PR I · US-FIN-025 |
| OCR de boleto (killer vs Conta Azul) | ✅ `ocrBoleto()` OpenAI Vision + `BoletoOcrService` | Onda 23 · US-FIN-029 |
| Troubleshooter | ✅ `FinTroubleshooter` dialog | Onda 7b |
| Imprimir com brand / apresentação / favoritos | ✅ `FinTranscriptPDF` · `FinPresentationMode` · `useFinFavs` (atalho B) | Onda 7b/7c |
| Workflow aprovação + Spatie gate | ✅ solicitar/aprovar/rejeitar `can:financeiro.titulo.aprovar` | Onda 21-22 · US-FIN-027/028 |
| Delta % MoM | ✅ `deltaPct()` nos KPIs | PR H · US-FIN-023 |

**Conclusão honesta:** o Unificado (tela-carro-chefe) está realisticamente em **~9,2-9,4/10**, não 8,5. O roadmap "4 refinos" da v1 estava errado — a maior parte **já foi entregue** pelo time nas Ondas 22-29.

---

## 2. As 22 features-tipo — status REAL (pós código)

✅ feito · 🟡 parcial · ❌ falta de verdade · ❓ não verificado nesta sessão

| Cat | Feature | Status real | Evidência / gap |
|---|---|---|---|
| Nav | N1 Tri/quad-pane | ✅ | Cockpit + drawer 440 (Unificado 10/10) |
| Nav | N2 ⌘K palette | ✅ | Index.tsx:936 |
| Nav | N3 J/K + atalhos | ✅ | Index.tsx:961-1005 (era 🟡 na v1 — **estava errado**) |
| Nav | N4 Subcategorias tree | ✅ | Plano de Contas BR 49 entries |
| Nav | N5 Responsive tablet | ❓→provável ❌ | não confirmei cards mobile; gap antigo do DOC |
| Cur | C1 Editor inline | ✅ | `TituloEditSheet` + guard imutabilidade |
| Cur | C2 Histórico + diff | ✅ | `auditTrail()` diff (era 🟡 — **corrigido**) |
| Cur | C3 Frescor + aging | ✅ | `FinPillFrescor` + `agingBreakdown` chips |
| Cur | C4 Re-verificar | ✅ | `FinConferidoToggle` per-user DB |
| Cur | C5 Comentários inline | ✅ | `FinCommentsThread` |
| IA | I1 Resumir | ✅ | `FinMonthDigest` |
| IA | **I2 Perguntar (RAG)** | ❌ | 🔴 **gap real.** `sugerirValor()` é heurística (média/último), NÃO RAG. Falta "esse cliente costuma atrasar?" sobre histórico via Jana. **Precisa ADR.** |
| IA | I3 Auto-sugest meta | 🟡 | `sugerirValor` + `FinAnomalyDetector` existem; falta auto-sugerir **categoria/plano-de-contas** |
| IA | I4 Empty-state IA | ❓→provável ❌ | busca vazia → "perguntar à IA" não confirmado |
| Gui | G1 Troubleshooter | ✅ | `FinTroubleshooter` (era ❌ — **corrigido**) |
| Gui | G2 Editor visual de árvore | ❓→provável ❌ | criar fluxo sem código |
| Gui | **G3 Trilhas onboarding** | ❌ | 🔴 **gap real.** Onboarding Eliana (fechar mês, conciliar). |
| Gui | G4 Cross-link auto | ✅ | `FinCrossLinkify` #V-/#PC- |
| Saí | S1 Favoritos | ✅ | `useFinFavs` (atalho B) |
| Saí | S2 Apresentação | ✅ | `FinPresentationMode` |
| Saí | S3 Imprimir brand | ✅ | `FinTranscriptPDF` + OCR boleto |
| Saí | S4 Anexos/imagens | ✅ | `listarAnexos`+`baixarAnexo` (era 🟡 — **corrigido**) |

---

## 3. O gap REAL pra 9,75 (o que sobra de verdade)

Só **3-4 frentes**, todas concentradas em **Inteligência profunda + Guia + Reach** — e a maior precisa de ADR:

1. 🔴 **I2 — Perguntar ao histórico (RAG)** — "esse cliente costuma atrasar?", "quanto recebi desse canal em 90d?". Toca Jana + corpus financeiro. **Precisa ADR própria** (schema/integração). Maior salto de score.
2. 🔴 **G3 — Trilhas de onboarding** financeiro (Eliana treina substituto: fechar mês → conciliar → emitir boleto).
3. 🟡 **I3.b — Auto-sugerir categoria/plano-de-contas** no lançamento (estende o `sugerirValor` que já existe).
4. 🟡 **N5/a11y — Cards mobile + aria-labels/tab-order** (gap recorrente do DOC; reach pra tablet).

**Antes de propor PRs disto, falta uma coisa:** uma **re-auditoria das OUTRAS telas** (Conciliação, DRE, Cobrança, Relatórios, Extrato) contra o **código real** — porque a v1 deste doc provou que a briefing está atrás do código. Não vou confiar em score documentado de novo.

---

## 4. Pré-requisitos arquiteturais

| | Pré-requisito | Estado real |
|---|---|---|
| **A1** | Integração cruzada | 🟡 `#V-`/`#PC-` (Venda/Compra) ✅ + `FinPartyHistory` ✅; falta ligação completa Título↔NFe. Pré-req do RAG (I2). |
| **A2** | Multi-tenant + grupo econômico | **Tier 0 ✅** (`BusinessScope`, D1 30/30). Tier 1 (filiais) / Tier 2 (holding + DRE consolidada) = futuro, não bloqueia o gap acima. |

---

## 5. Restrições de execução (lições F3 — gates duros, já com enforcement PHPStan)

- Models reais: `Titulo`(`tipo: receber|pagar`)/`TituloBaixa`/`ContaBancaria`/`Categoria`/`PlanoConta`/`ExtratoLancamento`/`TituloAnexo`/`TituloComment`. NUNCA inventar.
- `session('user.business_id')` + `can:financeiro.*` (`oimpresso.missingTenantScope`).
- **Tela em prod = refator/extensão em PR separado sobre o controller REAL. JAMAIS regenerar/sobrescrever.** ← foi exatamente o que o pré-flight evitou hoje.
- Idempotência (`idempotency_key` UUID) · soft-delete + trava histórico · sem NO-OP `return back()` (`oimpresso.nopMutation`) · sem fallback silencioso sem `Log::warning` (`oimpresso.silentFallback`).
- Schema/Service/componente-shared novo = **ADR primeiro**. O RAG (I2) cai aqui.
- Cada item = PR ≤300 linhas, charter + Pest ao lado, gate visual, smoke biz=1.

---

## 6. O que revisar (Wagner) + recomendação

1. **A correção bate?** Confirma que as Ondas 22-29 já entregaram aging/anexos/audit-diff/OCR/troubleshooter/auto-suggest? (eu li no código, mas você conhece o histórico melhor)
2. **A BRIEFING.md (05-20) está desatualizada** — não reflete US-FIN-021..029. Quer que eu **atualize a briefing** pra parar de enganar quem ler?
3. Dos 4 gaps reais (RAG · trilhas onboarding · auto-categoria · mobile/a11y), **qual primeiro?**
4. **RAG (I2)** precisa de ADR (Jana + corpus financeiro). Proponho a ADR, ou começamos pelos itens sem-ADR (auto-categoria, mobile/a11y, trilhas)?

**Recomendação:** o Financeiro **já é ~9,3**. Em vez de "4 refinos", o caminho honesto pra 9,75 é:
- **(a)** atualizar a BRIEFING (parar o drift de documentação), e
- **(b)** fazer uma **re-auditoria das outras telas contra o código real** pra achar onde o gap de verdade está hoje (a v1 provou que a doc mente),
- **(c)** depois atacar 1-2 dos 4 gaps reais — começando pelos sem-ADR (auto-categoria de lançamento + mobile/a11y), deixando o RAG pra uma ADR dedicada.

Não vou escrever código redundante. O valor desta sessão foi **descobrir que o módulo já está quase lá** e **mapear o que falta de verdade** — não inflar PRs.

---

**Status:** DRAFT 2026-05-31 · corrigido pós pré-flight no código real.
**Reconstruído por:** Claude Code (Opus 4.8). O `.html` original do Cowork não estava no export; o diagnóstico foi ancorado no código de produção (`UnificadoController` + `Unificado/Index.tsx`), não na briefing.
