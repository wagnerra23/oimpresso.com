---
slug: 2026-06-04-roteiro-aprovacao-44-telas
title: "Roteiro de aprovação das 44 telas (gate visual [W]) — priorizado por impacto×receita, em ondas independentes"
type: roteiro
status: ativo
date: "2026-06-04"
authors: [claude-cowork]
grounded_in_main: "PLANO-DESIGN-TELAS-2026-05-31.md · feat/staging-ct100"
---

# Roteiro — passar o gate visual das 44 telas (rápido, por receita, em ondas)

> As 44 já estão em **código verde** (`feat/staging-ct100`). O que falta é **você aprovar por screenshot** (ADR 0107/0114) → fecha o ratchet (0236) + libera merge. Os screenshots saem do **build de staging** ([CL]/deploy os gera; eu não renderizo .tsx daqui).
> **Pré-passo do [CL] (antes de você olhar):** confirmar que `feat/staging-ct100` **não está stale vs `main`** (forkou há ~4 dias) e subir o build de staging com as 44.

## ⏱️ Executa já ou espera tudo junto? → **JÁ, independente. Nunca "tudo junto".**

| Ação | Quando | Dependência | Por quê agora |
|---|---|---|---|
| **1. Ligar o juiz LLM** (`PR_UI_JUDGE_ENABLED=true`) | **hoje, 2 min** | nenhuma | começa a pontuar **todo PR futuro** já — drift passa a ser pego pela máquina. Esperar não ganha nada |
| **2. Aprovar as 44 em ONDAS** (A→B→C, merge a cada onda) | esta semana | build staging do [CL] | trabalho feito **apodrece** parado no branch (foi o que aconteceu com `refactor/css-fundacao-unica`). Merge em ondas = sem big-bang |
| **3. Higiene** (reconciliação memória · DS v6 ADR · lápides · branch +111k) | async, paralelo | — | **NÃO bloqueia 1 e 2.** Baixo risco, baixa urgência |

> Regra: cada onda merge sozinha (CI verde + seu ok visual). **Não juntar tudo num PR** — big-bang foi o erro do branch +111k.

## ✅ Protocolo de aprovação rápida (vale pra TODA tela — 5 checagens binárias)
1. **Cor:** só roxo 295 + neutros quentes? Zero azul/sky/zinc/âmbar cru fora de status. (P1)
2. **Shell:** está dentro do AppShellV2 (sidebar light + PageHeader)? (P2/P3)
3. **Detalhe = drawer lateral**, nunca modal full-screen. PT-BR, zero emoji.
4. **Larissa (1280px):** densidade ok, alvos ≥44px, atalho de salvar onde precisa.
5. **Faz o que promete?** (stub virou feature real? — P9)

**Rejeitar é 1 linha:** "tela X — [o quê] errado" → volta pro design, não trava as outras. Aprovar em lote por onda; só parar nas que falham.

---

## 🥇 ONDA A — RECEITA DIRETA / cliente no balcão (aprovar PRIMEIRO · ~17 telas)
> O que faz dinheiro: venda, fiscal que libera a venda, oficina do Martinho, produção, preço, financeiro.

| Tela | →Alvo | O que olhar (fix) |
|---|--:|---|
| `ComunicacaoVisual/Index` | 54→70 | **calculadora m² entrega valor?** + no shell (P2) |
| `NfeBrasil/.../NfceStatus` | 38→70 | sem azul cru; Badge/Card DS; **ação reemitir** funciona (P1) |
| `Fiscal/Sped` | 68→70 | só limpeza de cor; núcleo fiscal já roda (P1) |
| `Financeiro/Unificado/Novo` | 52→70 | stub picker virou **form unificado real** (P9) |
| `Financeiro/Extrato/Index` | 67→70 | **CPF/doc mascarado** (LGPD) + PageHeader (P8/P3) |
| `Financeiro/AssinaturaAtualizar` | 58→70 | PageHeader + **preview de impacto** (P3) |
| `Financeiro/Configuracoes/Contador` | 58→70 | nativos→@/ui + confirm→Dialog (P5/P6) |
| `Financeiro/Advisor/Login` + `/Dashboard` | 50/52→70 | portal contador: hand-roll→@/ui (P5) |
| `OficinaAuto/ServiceOrders/Create` | 66→70 | nativos→@/ui + **combobox placa** + erros (P5) |
| `OficinaAuto/Vehicles/Create`+`Edit`+`Show` | 62-68→70 | paridade de campos Create↔Edit + badge canon + KPI/FSM no topo |
| `Repair/JobSheet/Create`+`AddParts`+`Index` | 52-68→70 | **busca cliente/produto real** (não ID cru) + totais (P9/P5) |
| `Repair/Dashboard/Index` | 62→70 | defer + **gráficos reais** (não listas) + KPIs (P7) |
| `Produto/SellingPrices` | 68→70 | cor + PageHeader + **atalho salvar** (P1/P3) |
| `Produto/Unificado/Index` | 56→70 | nativos→@/ui + cor + a11y (P5/P1) |
| `Produto/StockHistory` | 47→70 | **timeline real** (hoje só linka Blade) (P9) |
| `Manufacturing/Index` | 50→70 | montar no shell + **habilitar CTA** (P2) |

## 🛡️ ONDA B — SEGURANÇA (público · ~3 telas · não-receita mas obrigatório)
| Tela | →Alvo | O que olhar |
|---|--:|---|
| `Site/BlogPost` + `Site/Page` | 55/58→70 | **HTML sanitizado** (anti-XSS server-side ✓ HTMLPurifier) renderiza ok |
| `Site/Blogs` | 68→70 | paginação + busca/tags + data pt-BR |

## 🥉 ONDA C — INTERNO / superadmin (aprovar POR ÚLTIMO · ~24 telas)
> Ferramenta interna, não tela de cliente. Baixa receita. Aqui o critério é "funciona + não feio", não pixel-perfect. **Várias nem deviam aparecer no menu do cliente** (= o fix de sidebar, Onda 4).

- **SISTEMA/governança (lote P1 cor):** `Auditoria/Index`+`/Detail` · `governance/Policies`+`/DriftAlerts` · `Admin/FeatureFlags/Index`+`/Show` · `Admin/RagQualityDashboard` · `Admin/Index` · `superadmin/Usuario360/Index`+`/Show` · `MemCofre/Modulo` · `Settings/PaymentGateways/CnabRetorno`
- **ads/* (IA interna):** `ads/Admin/Graph` · `/Learning` · `/Confidence`
- **Jana atalhos:** `Jana/Brief/Index` · `Jana/Regras/Index` · `Jana/Painel`
- **RH:** `Ponto/Relatorios/Index` · `Ponto/Welcome`

→ Aprovar essas em **bloco** ("ok, todas") salvo alguma gritante; o valor de revisar uma a uma é baixo.

## Depois das ondas (fecha o ciclo, vira automático)
1. **Re-rodar o board** (workflow 19-agentes) → média nova (era 75). Mede o ganho.
2. **Juiz LLM já ligado** (ação 1) cobra as próximas sozinho → você sai do circuito de fiscalizar.
3. 3 fixes de **sidebar** (Onda 4) = sua decisão de produto (desinchar SISTEMA, grupos órfãos, bucket Público).

## Trilha do tempo
- 2026-06-04 · [CC] · roteiro priorizado por receita (A balcão/receita · B segurança · C interno) a partir do `PLANO-DESIGN-TELAS-2026-05-31`. Recomendação de sequência: ligar juiz hoje + aprovar em ondas (não big-bang) + higiene async. [CL] confirma frescor de `feat/staging-ct100` e gera os screenshots de staging.
