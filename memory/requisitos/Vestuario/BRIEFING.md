---
module: Vestuario
status: piloto (live em produção via ROTA LIVRE biz=4 desde 2024-Q1)
piloto: ROTA LIVRE — LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME
piloto_inicio: 2024-Q1
cnae_principal: "4781-4/00"
last_review: 2026-05-16
owner: wagner
parent_adr: 0121
related_adrs: [0011, 0066, 0093, 0094, 0101, 0105, 0121]
nota_atual: 71/100
gaps_top: [D2(12/20), D3(5/15 — sem BRIEFING/sem Charter), D4(9/20)]
---

# BRIEFING — Modules/Vestuario

> **1-pager executivo** do módulo vertical lojas de vestuário/moda BR (CNAE 4781-4/00).
> Estado consolidado mantido por skill `brief-update` (Tier B) após PR mergeado que toque o módulo. Wagner enxerga estado real sem precisar pedir.

## TL;DR

Vertical **em produção há 2+ anos** via cliente piloto ROTA LIVRE (biz=4, Larissa, Termas do Gravatal/SC) que concentra **~99% do volume de vendas** do oimpresso novo (Laravel). Customizações ROTA LIVRE preservadas como first-class (ADR 0066 `format_date` shift +3h). Sprint 1 entregou scaffold formal (módulo + settings JSON per-business + Resolver + Pest). Sprint 2+ migra capacidades vestuário-específicas progressivamente conforme sinal qualificado (ADR 0105).

## Cliente piloto — ROTA LIVRE biz=4 (PRODUÇÃO)

> ⚠️ **biz=4 é PROD REAL.** Nunca usar `business_id=4` em testes/seeders/smoke — ADR 0101 manda biz=1 ou biz=99. Skill `multi-tenant-patterns` (Tier A) + hook bloqueiam.

| Campo | Valor |
|-------|-------|
| Razão social | LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME |
| CNPJ | [REDACTED-CNPJ] |
| Endereço | BL0001 "ROTA LIVRE" — Termas do Gravatal, Gravatal/SC, CEP 88735-0 |
| `business_id` | **4** (PRODUÇÃO — NÃO MEXER) |
| Operadora | Larissa Fernandes (`larissa-04`, role `Admin#4`) |
| Volume | 17.251+ vendas / ~99% do sistema novo Laravel |
| Monitor | **1280px** — telas com 21+ colunas inutilizam; sempre `columnDefs` |
| Customização preservada | `format_date` shift +3h (ADR 0066 — Larissa decorou, NÃO mexer) |
| `transaction_date` retroativo | Fluxo normal (registra balcão em lote no fim do dia) |
| Default role precisa | `location.4` explícita (incidente 2026-04-24) |
| Sazonalidade | Verão (Out-Mar), Inverno (Abr-Set) |
| Pico operacional | 14h-17h horário SP |

## Capacidades em produção (validadas em ROTA LIVRE)

| US | Capacidade | Onde mora |
|----|-----------|-----------|
| US-VEST-001 | Variation (tamanho×cor) com 15+ SKUs por peça | núcleo `App\Variation` + `VariationTemplate` |
| US-VEST-002 | Venda balcão (POS) com leitor barcode | núcleo `SellPosController` |
| US-VEST-003 | NFC-e modelo 65 PDV | `Modules/NfeBrasil` (parcial — Larissa não emite hoje) |
| US-VEST-004 | Histórico vendas com filtros (cliente, período, vendedor) | núcleo `SellController` |
| US-VEST-005 | Estoque por (variation × location) | núcleo `VariationLocationDetails` |
| US-VEST-006 | Compra fornecedor + recebimento | núcleo `PurchaseController` |
| US-VEST-007 | AR/AP + boleto Asaas | `Modules/Financeiro` + `Modules/RecurringBilling` |
| US-VEST-008 | Múltiplos invoice_schemes em paralelo (`2026/NNNN` + `17NNN`) | núcleo `InvoiceLayoutController` |
| US-VEST-009 | Locale pt-BR DataTables + monitor 1280px responsivo | DataController (a criar — hoje genérico) |

## Capacidades faltantes (backlog priorizado)

P0 (paridade Linx Microvix imediata):
- **US-VEST-020** Etiqueta térmica TAM-COR-COLEÇÃO (12h)
- **US-VEST-021** Devolução/troca CDC + crédito em conta-cliente (16h)

P1 (Q3/Q4 2026):
- **US-VEST-022** Comissão vendedor escalonada (16h)
- **US-VEST-023** Liquidação categoria/marca/estação em massa (10h)
- **US-VEST-024** Fidelidade R$ [redacted Tier 0] = 1 ponto com resgate (18h)
- **US-VEST-029** Atributo "estação" first-class (6h — pré-req de 022/023)

P2/P3 (2027+ ou sob sinal qualificado):
- US-VEST-025 Gift card · US-VEST-026 Crediário · US-VEST-027 Provador · US-VEST-028 Sacoleira · US-VEST-030 Ecommerce (ADR feature-wish)

## Concorrentes diretos

| Concorrente | Foco | Pricing/m | Lacuna oimpresso preenche |
|---|---|---|---|
| Linx Microvix Vestuário | grandes redes (>5 lojas) | R$ [redacted Tier 0]-2500 | preço alto, lock-in, suporte demorado |
| ProMoz | médio-pequeno (1-3 lojas) | R$ [redacted Tier 0]-700 | falta NFe-de-boleto-pago, BI fraco |
| Vendizap | micro (catálogo WhatsApp) | R$ [redacted Tier 0]-150 | sem PDV físico, sem fiscal robusto |
| Bling Loja | horizontal raso | R$ [redacted Tier 0]-400 | sem profundidade matriz tam×cor |
| F360 | regional sul | R$ [redacted Tier 0]-800 | UI legacy |

## Diferenciais oimpresso vs concorrentes

1. **Jana IA com memória persistente** (ADR 0035-0053) — Larissa pergunta "quanto vendi de Verão24 essa semana?" e recebe resposta com dados reais
2. **NFe-de-boleto-pago automática** (US-RB-044, ADR 0089) — concorrente nenhum tem
3. **Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093) — isolation por design
4. **Stack moderna** Laravel 13.6 + React 19 + Inertia v3 + Pest 4 vs concorrentes PHP 7.x + jQuery legacy
5. **Customizações preservadas** (shift +3h ADR 0066) — concorrente "atualiza e quebra"
6. **Sinal qualificado pra evolução** (ADR 0105) — backlog só recebe se cliente paga e reporta

## Anti-padrões do módulo (Tier 0)

- ⛔ **Smoke test com `business_id=4`** — ADR 0101 IRREVOGÁVEL, usar biz=1 ou biz=99
- ⛔ Mexer no `format_date` shift +3h sem ADR amendment a 0066
- ⛔ Adicionar coluna default em DataTables `/sells` sem checar largura 1280px
- ⛔ Criar role custom sem `location.4` explícita (trava /sells/create)
- ⛔ Hard-deletar `Variation` com sell_lines históricos (quebra contábil)
- ⛔ Criar tabela `vest_*` sem `business_id` indexed + FK + global scope
- ⛔ Subir feature de fidelidade sem opt-in LGPD (Art. 7º)
- ⛔ Implementar US-VEST-030 (ecommerce) sem 3+ sinais qualificados

## Nota atual e gaps (rubrica module-grade-v1 — ADR 0153)

**71/100** (Bom) — piloto live em prod mas sem governança completa.

| Dimensão | Nota | Top gap |
|----------|------|---------|
| D1 Capacidades em prod | 16/20 | NFC-e regular pendente discovery regime tributário ROTA LIVRE |
| D2 Capacidades vs mercado | **12/20** | US-VEST-020 (etiqueta) + US-VEST-021 (devolução) P0 abertas |
| D3 Governança canônica | **5/15** | sem BRIEFING (preenchido neste PR) + Charter de página ainda não escrito |
| D4 Cobertura testes | **9/20** | sem Pest cross-tenant Grade Avançada (preenchido neste PR) + sem smoke routes |
| D5 UX/Tier-0 conformity | 12/15 | OK — monitor 1280px + multi-tenant + format_date preservado |
| D6 Sinal qualificado | 17/10 | bônus — ROTA LIVRE valida há 2+ anos, 99% volume |

## Status lifecycle (ADR 0121)

- ✅ `piloto` — ROTA LIVRE biz=4 pagando, código vivendo
- ⏳ `ativo` (meta Q4/26 ou Q1/27) — exige 3+ clientes pagantes + módulo formal extraído + SPEC + CAPTERRA-FICHA + CAPTERRA-INVENTARIO + Pest GUARD pra Non-Goals/Anti-hooks

## Roadmap próximos 12 meses

| Quarter | US prioridade | Marco |
|---------|---------------|-------|
| 2026-Q2 (atual) | US-VEST-029 estação + US-VEST-020 etiqueta | fundação liquidação/comissão |
| 2026-Q3 | US-VEST-021 devolução + US-VEST-023 liquidação + US-VEST-022 comissão | paridade Linx Microvix ROTA LIVRE |
| 2026-Q4 | US-VEST-024 fidelidade + US-VEST-025 gift card | sazonalidade Black Friday + Natal |
| 2027-Q1 | US-VEST-026 crediário + US-VEST-027 provador | revenda 2º cliente Vestuario |
| 2027-Q2 | US-VEST-028 sacoleira + revisão US-VEST-030 ecommerce (com sinal) | network effect |

## Decisões pendentes

- [ ] Regime tributário ROTA LIVRE (MEI/Simples?) — destrava US-VEST-003 NFC-e regular
- [ ] Modules/Vestuario formal full vs virtual — depende 2º cliente Vestuario (ADR 0105)
- [ ] Comissão escalonada simples vs discovery 1 loja real antes de codar
- [ ] Etiqueta térmica padrão Argox/Zebra universal SC ou variação regional
- [ ] Fidelidade pontos transferíveis (Linx tem) vs não-transferíveis (LGPD-easy)
- [ ] Crediário com Asaas (boleto/parcela) ou 100% offline (carnê PDF)

## Referências

- [SPEC.md](SPEC.md) — especificação funcional completa US-VEST-*
- [Vestuario.charter.md](Vestuario.charter.md) — module charter (Mission/Goals/Non-Goals/Anti-hooks)
- ADR 0121 — Modular especializado por vertical (mãe)
- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0066 — `format_date` shift +3h preservado
- ADR 0101 — Tests biz=1 nunca cliente real
- ADR 0105 — Cliente como sinal qualificado

---

**Última atualização:** 2026-05-16 — Wave Massive criou BRIEFING inicial + 3 Pest tests (Grade Avançada cross-tenant, Smoke routes Install, Scaffold) fechando gaps D3/D4 da nota module-grade-v1.
