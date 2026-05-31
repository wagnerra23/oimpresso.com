---
slug: audit-metodo-9-75-telas-financeiro
title: "Auditoria Método 9.75 — telas do Financeiro (código real)"
type: audit
authority: canonical
lifecycle: ativo
session_date: '2026-05-31'
quarter: 2026-Q2
related:
  - '0093'
  - '0190'
pii: false
---

# Auditoria Método 9.75 — telas do Financeiro (código real)

> **Origem (2026-05-31):** re-auditoria **read-only** das telas do Financeiro contra o método **KB-9.75**, lendo o **código real** (`Modules/Financeiro/Http/Controllers/*` + `resources/js/Pages/Financeiro/*` + `_components`), feita por **5 agentes em paralelo** (1 por tela). Motivada pela descoberta (PR #2040) de que a [BRIEFING.md](BRIEFING.md) estava atrás do código.
>
> **A correção que esta auditoria traz:** o doc [METODO-9.75-FINANCEIRO.md](METODO-9.75-FINANCEIRO.md) concluiu "módulo já ~9,3". **Errado.** Só o **Unificado** está em ~9,3 — as outras telas estão muito abaixo **e carregam bugs reais** (não só falta de polish). Média real do módulo ≈ **5,2/10**.

---

## 1. Placar real (por tela)

| Tela | Score | Nav · Cur · IA · Guia · Saída | Achado mais crítico |
|---|---:|---|---|
| **Unificado** | **9,3** | 9 · 9 · 5,5 · 6 · 7,5 | Flagship. aging/anexos/audit/OCR/auto-suggest/workflow já feitos (verificado em sessão anterior). |
| **Cobrança** | **7,0** | 8 · 6 · 6 · 4 · 5 | 🟠 muitos botões **NO-OP** (Cancelar/Estornar/Reemitir/Baixar PDF/Exportar/Copiar sem `onClick`). Só "Nova cobrança→Emitir" e "Cobrar cartão" gravam. |
| **DRE** | **6,0** | 5,5 · 3 · 1,5 · 1 · 8 | Export PDF/XLSX/CSV real ✅. Mas **5 botões topnav são stub** (Buscar/Resumir/Apresentar) + CSV funciona mas **sem botão na UI**. |
| **Conciliação** | **3,2** | 4 · 2,5 · 1,5 · 1 · 1 | 🔴 `match_score` é **fake `0.85` hardcoded** (não calculado); **sem audit log**; usa `DB::table` raw **sem model com BusinessScope** (rede de proteção Tier 0 ausente). |
| **Relatórios** | **3,2** | 4 · 1 · 1 · 2 · 4 | Tela sendo **esvaziada** (DRE saiu p/ `/dre`, aba Fluxo duplica `/fluxo` de forma mais pobre). Só CSV, sem PDF/XLSX. |
| **Extrato** | **2,4** | 3 · 1,5 · 0,5 · 1,5 · 1 | 🔴 **link de sidebar quebrado** (ghost → `/financeiro/extrato` sem `contaId` → 404); **sem cross-link extrato↔título**. Tela mais fraca. |

**Média ≈ 5,2.** O Unificado mascara o resto.

---

## 2. Cinco verdades módulo-wide (o que a documentação não contava)

1. 🔴 **"IA" no Financeiro NÃO é LLM.** `FinMonthDigest`, `FinMonthResume`, `AiResumoMes`, `FinTroubleshooter` são **compute/heurística pura** (os próprios arquivos declaram "sem LLM"). O **único** uso real de modelo é `BoletoOcrService` (OCR Vision no Unificado). → **RAG "perguntar" (I2) é gap genuíno e módulo-wide.** Proposta: [ADR arq/0006](adr/arq/0006-rag-perguntar-historico-financeiro.md).
2. 🟠 **Botões decorativos (NO-OP / stub).** Cobrança e DRE têm vários botões com visual estado-da-arte mas **sem handler**. Pior que faltar: dá feedback falso. Anti-padrão "botão decorativo" — mata confiança da Eliana/Larissa.
3. 🔴 **Conciliação tem confiança fake + risco Tier 0.** `match_score` constante `0.85` (controller `sugerirMatches`), `DB::table('fin_bank_statement_lines')` raw sem Eloquent+`BusinessScope` (isolamento só funciona porque cada query repete `where business_id` manual — sem rede de proteção), e `match()`/`ignorar()` **não chamam** `FinanceiroAuditLogger` (que existe) apesar do comentário "append-only audit".
4. 🟠 **Extrato e Conciliação são 2 sistemas paralelos que não se falam.** `fin_extrato_lancamentos` (sync API, sem `titulo_id`) vs `fin_bank_statement_lines` (upload OFX, com `titulo_id`/match). Decisão de produto pendente: qual é a fonte da conciliação?
5. 🟡 **Inconsistências:** `session('business.id')` (Extrato) vs `session('user.business_id')` (resto); permissão `financeiro.relatorios.view` (DRE/Relatórios) vs `financeiro.dashboard.view` (Fluxo); `Relatorios/Index.review.md` é STALE (descreve 592 linhas/DRE/paginate que não existem mais).

---

## 3. Backlog priorizado (corrigido pós-auditoria)

> Separa **correção (certo vs errado)** de **maturidade 9,75 (bom vs ótimo)**. Cada item respeita as restrições F3 (§4). Cada um ≈ 1 PR ≤300 linhas, charter + Pest + smoke biz=1.

### 🔴 P0 — Correção / bug (não é polish)
| # | Item | Tela | Plug-point real |
|---|---|---|---|
| B1 | `match_score` calculado de verdade (valor 0.7 + proximidade-data 0.3, como o docblock promete) | Conciliação | `ConciliacaoController::sugerirMatches` (hoje grava `0.85`) |
| B2 | Chamar `FinanceiroAuditLogger` em `match()`/`ignorar()` + ação **reabrir** (desfazer) | Conciliação | controller + rota `POST /conciliacao/{id}/reabrir` |
| B3 | Model Eloquent `BankStatementLine` com `BusinessScope` (rede Tier 0; hoje é `DB::table` raw) | Conciliação | novo model + refactor queries |
| B4 | Corrigir ghost de sidebar do Extrato (→ seletor de conta, não `/extrato` sem id → 404) | Extrato | `DataController` ghost + rota |
| B5 | Padronizar `session('user.business_id')` (Extrato usa `business.id`) | Extrato | `ExtratoController` |
| B6 | Ligar **ou remover** botões NO-OP/stub (Copiar/Baixar PDF/Cancelar/Estornar/Reemitir/Exportar; topnav DRE) | Cobrança, DRE | wire `onClick` ou tirar o botão |

### 🟡 P1 — Maturidade 9,75 (sem ADR, reusa o que existe)
| # | Item | Tela | Reusa |
|---|---|---|---|
| M1 | Expor botão CSV do DRE (rota existe, sem botão) + dropdown CSV/PDF/XLSX | DRE | `dre/export-csv` pronta |
| M2 | Ligar "Apresentar" (stub) ao `FinPresentationMode` | DRE, Relatórios | componente existe |
| M3 | Export PDF/XLSX em Relatórios (paridade DRE) + `Inertia::defer` por aba | Relatórios | espelhar `DreController::exportPdf/Xlsx` |
| M4 | Aging pill colorida (fresco/atrasando/stale) + empty-state melhor | Extrato | dado `saldo_atualizado_em` já chega |
| M5 | Cross-link read-only extrato↔título (chip "→ Título #N") | Extrato | lookup por valor+data |
| M6 | Auto-sugerir categoria/plano-de-contas no lançamento (I3) | Unificado, Conciliação | estende `sugerirValor` |
| M7 | Comentários (`FinCommentsThread`) + "conferido" no drawer de Cobrança/Conciliação | Cobrança, Conciliação | componente existe no Unificado |

### 🔵 P2 — Maturidade 9,75 (precisa ADR / é maior)
| # | Item | Escopo |
|---|---|---|
| A1 | **RAG "perguntar ao histórico"** (I2) — maior salto de score | [ADR arq/0006](adr/arq/0006-rag-perguntar-historico-financeiro.md) proposta neste PR |
| A2 | Trilhas de onboarding (G3) da Eliana | sem ADR, mas é feature nova |
| A3 | Mobile cards + a11y (N5) | gap recorrente |
| A4 | Decisão de produto: Relatórios vira só "Resumo" + redireciona Fluxo→`/fluxo`? Extrato vs Conciliação fonte única? | Wagner decide |

---

## 4. Restrições de execução (lições F3 — gates duros, enforcement PHPStan)

- Models reais: `Titulo`/`TituloBaixa`/`ContaBancaria`/`Categoria`/`PlanoConta`/`ExtratoLancamento`/`TituloAnexo`/`TituloComment`/`BoletoRemessa`. Cobrança usa `PaymentGateway\Cobranca` (módulo separado), **não** `Titulo`.
- `session('user.business_id')` + `can:financeiro.*` (`oimpresso.missingTenantScope`).
- Refator/extensão de tela em prod = **PR separado sobre o controller real**, nunca regenerar.
- Sem NO-OP `return back()` em mutação (`oimpresso.nopMutation`) · sem fallback silencioso (`oimpresso.silentFallback`) · idempotência + soft-delete.
- Schema/Service/componente-shared novo = **ADR primeiro**.

---

## 5. Recomendação de execução

1. **P0 primeiro** — são bugs/risco, não estética. O `match_score` fake (B1) e o `DB::table` raw sem BusinessScope (B3) são os mais sérios (engana a Eliana + risco Tier 0). B4 (link 404) e B6 (botões mortos) são baratos e de alta percepção.
2. **P1 em wave paralela** — M1..M7 são isolados por tela, reusam código existente, baixo risco → bom candidato a paralelizar (como esta auditoria).
3. **P2 com gate** — RAG (A1) só após Wagner aceitar a [ADR arq/0006](adr/arq/0006-rag-perguntar-historico-financeiro.md). A4 é decisão de produto.

**Meta realista:** fechar P0 + P1 leva o módulo de ~5,2 → ~8,5 (telas secundárias deixam de arrastar). RAG (P2) + trilhas levam a 9,75.

---

**Status:** auditoria concluída 2026-05-31 (5 agentes paralelos, código real). Backlog aguardando Wagner priorizar. Nenhum código alterado nesta auditoria.
