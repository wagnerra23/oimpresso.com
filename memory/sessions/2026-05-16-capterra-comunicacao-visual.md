# Session — CAPTERRA-FICHA ComunicacaoVisual (Wave 22)

**Data:** 2026-05-16
**Agente:** Claude (Wave 22, 1 de 12 agents paralelos governance-wave-21-22-mega)
**Branch:** `claude/governance-wave-21-22-mega`
**Worktree:** `D:\oimpresso.com\.claude\worktrees\jolly-hypatia-b8741c\`
**Área exclusiva:** `memory/requisitos/ComunicacaoVisual/CAPTERRA-FICHA.md` + este session log
**Pré-existente:** BRIEFING.md (Wave 18), MATRIZ-ROI.md (Wave 16), SPEC.md, charter, ROADMAP, PLANO-MIGRACAO-6-SAUDAVEIS, QUALIFICACAO-PILOTO

## Tarefa

Aplicar pattern CAPTERRA-FICHA canônico (validado em NfeBrasil, RecurringBilling, ProjectMgmt, Whatsapp, KB) ao módulo `Modules/ComunicacaoVisual`. Comparar com 5 concorrentes (4 BR + 1 benchmark global).

## Concorrentes pesquisados (3 WebSearch)

1. **Mubisys** (mubisys.com) — Mubi Sistemas / PG Consultoria — orçamento + produção + financeiro + estoque + app mobile + MubiDrive (~150TB DAM). Base SP/PR.
2. **Zênite Sistemas GE 3.0** (zsl.com.br) — 25+ anos, 2.000+ gráficas BR, versões Lite/Smart/Standard/Full, módulos completos
3. **Calcgraf / NetCalc** (calcgraf.com.br) — 40+ anos especialista orçamentação, 2 milhões orçamentos/mês, cálculo automático etiqueta/faca/cilindro
4. **Calcme** (calcme.com.br) — gráficas + marcenarias, PCP Kanban, WhatsApp Chatme, NFe, comissão, novos preços Jan/2026
5. **EFI PrintSmith Vision 5** (efi.com) — benchmark global MIS gráfica, web-to-print MarketDirect StoreFront, US$ 599-750

## Capacidades inventariadas — 20 (P0-P3)

- **9 P0** (peso 4): calc m², cadastro substrato, PCP Kanban, apontamento CMYK, orç→OS, NFe55, NFSe, multi-tenant Tier 0, importer Firebird
- **7 P1** (peso 2): pós-cálculo, NFe-boleto-pago, dual-doc, Jana IA, arte WhatsApp, NR-35, DAM
- **2 P2** (peso 1): comissão multi-papel, provador online
- **2 P3** (peso 0.5): web-to-print storefront, CT-e/MDF-e

## Nota agregada calculada

| Categoria | Pesos disponíveis | Pontos obtidos |
|---|---:|---:|
| 9 P0 × 4 | 36 | 20 (6 cobertas, 3 parciais) |
| 7 P1 × 2 | 14 | 2 (1 parcial dual-doc + 1 parcial NR-35) |
| 2 P2 × 1 | 2 | 0 |
| 2 P3 × 0.5 | 1 | 0 |
| **TOTAL** | **53** | **22** |

**Nota: 22/53 = 41.5/100** 🟡 em construção (esperado — Sprint 1 backend entregue, Sprint 2-3 pendente)

**Projeções:**
- Pós-Sprint 2 (Pages + NFe55+NFSe adapter): ~60/100 (paridade operacional)
- Pós-Sprint 3 (Jana IA + WhatsApp + pós-cálculo + Importer): ~83/100 (supera Mubisys/Zênite em diferenciais únicos)

## Top 5 GAPS (priorização Wave 23+)

| # | Gap | ROI score | Esforço |
|---|---|---:|---:|
| 1 | Pages Inertia Orçamento/PCP/Apontamento (Sprint 2 UI) | bloqueador | 30h |
| 2 | NFe55 adapter Listener OS→Job (US-COMVIS-006) | 1500 | 8h |
| 3 | NFSe driver Floripa+Goiânia (US-COMVIS-008) | 321 | 14h |
| 4 | **Importer Firebird OfficeImpresso** (US-COMVIS-017) — top ROI | **1562** | 16h |
| 5 | NFe-de-boleto-pago + Jana IA 22h + Dual-doc (3 wedges) | combinado top | 16h |

## Wedges únicos (nenhum concorrente tem)

1. NFe-de-boleto-pago automática (todos forçam clique humano)
2. Jana IA bulk update substrato via chat ("aumenta lona 440g +5%")
3. Dashboard noturno Jana 22h (Calcme tem WhatsApp não-IA)
4. Dual-doc fiscal NFe55+NFSe56 simultâneo em 1 OS
5. Wizard onboarding Jana detecta CNAE 1813
6. Importer Firebird OfficeImpresso vertical (Bling/Omie só CSV genérico)

## Observações metodológicas

- Não-goal: criar SPEC novo. Reaproveitei MATRIZ-ROI (24 features priorizadas) + BRIEFING (estado consolidado) + SPEC (US existentes). Ficha = consolidação benchmark, não plano novo
- Esforços recalibrados ADR 0106 (10x IA-pair) já refletidos em MATRIZ-ROI
- Nota 41.5/100 é HONESTA: módulo Sprint 1 entregue (10 entities, 11 Pest verdes, FSM 16 stages, Tier 0 Guard) mas SEM Pages cliente final + sem NFe/NFSe adapter + sem importer = não está utilizável em prod ainda
- Sinal-zero qualificado: 6 candidatos identificados, 0 piloto ativo (ADR 0105 gating P2/P3)

## Arquivos criados

- `memory/requisitos/ComunicacaoVisual/CAPTERRA-FICHA.md` (10 seções canônicas, 20 capacidades, 4 UX heuristics, 5 automation targets, nota 41.5/100)
- `memory/sessions/2026-05-16-capterra-comunicacao-visual.md` (este log)

## Próximos passos (sugestão Wave 23+)

1. Wagner valida nota 41.5/100 + projeções 60/83
2. Sprint 2 prioriza Pages Inertia (charter MWART F1.5 visual gate) + NFe55 adapter
3. Sprint 3 ativa wedges únicos (NFe-boleto-pago + Jana IA + Importer Firebird)
4. Re-auditar pós-Sprint 2 (~2026-08-16) — meta 60/100

## Restrições Tier 0 respeitadas

- ✅ PT-BR em tudo
- ✅ Zero git ops (sem add/commit/push)
- ✅ Não tocou outros módulos (só `memory/requisitos/ComunicacaoVisual/` e `memory/sessions/`)
- ✅ Não BOM (arquivos UTF-8 puro)
- ✅ Sem alucinação: capacidades baseadas em SPEC/MATRIZ existentes + concorrentes pesquisados em 3 WebSearch Wave 22

## WebSearch consultados

1. "Mubisys software gestão gráfica comunicação visual orçamento BR 2026"
2. "Printsmith Vision MIS gráfica web-to-print orçamento OS 2026"
3. "Zenite sistema gráfica plotagem banner orçamento gestão BR"
4. "Calcgraf cálculo gráfica orçamento metro quadrado m2 sistema BR"
5. "Calcme sistema gestão gráfica BR funcionalidades preço orçamento PCP"
