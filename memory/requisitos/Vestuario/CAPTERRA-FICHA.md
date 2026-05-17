# CAPTERRA-FICHA — Vestuario

> Ficha canônica de benchmark do módulo vertical Vestuario.
> **Bucket**: `vertical_client_facing` ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md))
> **Wave 23** — saturação 67 → ≥85 (rubrica scoped vertical_client_facing.yaml)
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) + [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7

---

## Identidade do módulo

- **Nome interno**: `Vestuario`
- **CNAE**: 4781-4/00 (Comércio varejista de artigos do vestuário e acessórios)
- **Estado lifecycle** (ADR 0121): **piloto** (live prod biz=4 desde 2024-Q1)
- **Cliente piloto**: ROTA LIVRE (LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME, Termas do Gravatal/SC, biz=4)
- **Volume validado**: 17.251+ vendas / ~99% do oimpresso novo Laravel
- **Customização preservada**: `format_date` shift +3h (ADR 0066 — Larissa decorou, NÃO mexer)

## Concorrentes-alvo

| Concorrente | Pricing/mês | Foco | Lacuna oimpresso preenche |
|---|---|---|---|
| **Linx Microvix Vestuário** | R$ 800-2500 | grandes redes (>5 lojas) | preço alto, lock-in, suporte demorado |
| **ProMoz** | R$ 300-700 | médio-pequeno (1-3 lojas) | sem NFe-de-boleto-pago, BI fraco |
| **Vendizap** | R$ 50-150 | micro (catálogo WhatsApp) | sem PDV físico, fiscal raso |
| **Bling Loja** | R$ 150-400 | horizontal raso | sem profundidade matriz tam×cor |
| **F360** | R$ 400-800 | regional sul | UI legacy jQuery |

## Capacidades em produção (validadas em ROTA LIVRE)

```yaml
capacidades_em_prod:
  - us: US-VEST-001
    nome: "Variation tamanho×cor (15+ SKUs por peça)"
    score: P0
    onde: "App\\Variation + VariationTemplate (núcleo UPos)"
    evidencia: "ROTA LIVRE prod 2+ anos"

  - us: US-VEST-002
    nome: "PDV balcão com leitor barcode"
    score: P0
    onde: "SellPosController (núcleo UPos)"

  - us: US-VEST-005
    nome: "Estoque por (variation × location)"
    score: P0
    onde: "VariationLocationDetails (núcleo)"

  - us: US-VEST-007
    nome: "AR/AP + boleto Asaas"
    score: P1
    onde: "Modules/Financeiro + Modules/RecurringBilling"

  - us: US-VEST-009
    nome: "Locale pt-BR DataTables + responsivo 1280px"
    score: P1
    onde: "monitor Larissa 1280px (sazonalidade verão/inverno)"
```

## Top 5 gaps P0 (pra subir nota ≥85)

| US | Capacidade | Esforço | ROI estimado | Concorrente que tem |
|----|------------|---------|--------------|---------------------|
| US-VEST-020 | Etiqueta térmica TAM-COR-COLEÇÃO | 12h | alto (PDV +15% velocidade) | Linx Microvix, ProMoz |
| US-VEST-021 | Devolução/troca CDC + crédito | 16h | alto (paridade fiscal) | Linx Microvix |
| US-VEST-022 | Comissão vendedor escalonada | 16h | médio (rotatividade time) | Linx Microvix |
| US-VEST-023 | Liquidação categoria/marca em massa | 10h | médio (sazonal Black Friday) | Linx Microvix |
| US-VEST-029 | Atributo "estação" first-class | 6h | médio (pré-req 020/023) | Linx Microvix |

## Diferenciais oimpresso vs concorrentes

1. **Jana IA com memória persistente** (ADR 0035-0053) — Larissa pergunta "quanto vendi de Verão24?" e recebe resposta com dados reais
2. **NFe-de-boleto-pago automática** (US-RB-044, ADR 0089) — concorrente nenhum tem
3. **Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093) — isolation por design
4. **Stack moderna** Laravel 13.6 + React 19 + Inertia v3 + Pest 4 vs PHP 7.x + jQuery legacy
5. **Customizações preservadas** (shift +3h ADR 0066) — concorrente "atualiza e quebra"
6. **Sinal qualificado pra evolução** (ADR 0105) — backlog só recebe se cliente paga e reporta

## Score Capterra W22 → W23

| Dimensão (scoped vertical_client_facing) | W22 | W23 alvo |
|------------------------------------------|-----|----------|
| V1 Pest E2E Customer Journey | 8/15 | **13/15** |
| V2 Code Quality FormRequests | 10/10 | 10/10 |
| V3 Perf UX (Inertia::defer) | 6/10 | 8/10 |
| V4 LGPD retention canon | 9/15 | **14/15** |
| V5 Docs canon (BRIEFING/CHANGELOG/CAPTERRA) | 8/20 | **18/20** |
| V6 Capterra ROI Top 5 | 4/10 | 8/10 |
| **Total scoped** | **67/100** (P3 médio) | **≥85/100 (P1 alto)** |

## Status lifecycle (ADR 0121)

- ✅ `piloto` — ROTA LIVRE biz=4 pagando, código vivendo
- ⏳ `ativo` (meta Q4/26 ou Q1/27) — exige 3+ clientes pagantes + Modules/Vestuario formal extraído + Pest GUARD pra Non-Goals/Anti-hooks

## Anti-padrões (Tier 0 IRREVOGÁVEIS)

- ⛔ Smoke test com `business_id=4` (ADR 0101 — usar biz=1 ou biz=99)
- ⛔ Mexer no `format_date` shift +3h sem ADR amendment a 0066
- ⛔ Adicionar coluna default em DataTables `/sells` sem checar largura 1280px
- ⛔ Criar tabela `vest_*` sem `business_id` indexed + FK + global scope
- ⛔ Implementar US-VEST-030 (ecommerce) sem 3+ sinais qualificados (ADR 0105)

## Referências

- [BRIEFING.md](BRIEFING.md) — estado consolidado 1-pager
- [SPEC.md](SPEC.md) — US-VEST-001..030
- [Vestuario.charter.md](Vestuario.charter.md) — module charter
- [PII-LGPD.md](PII-LGPD.md) — política PII
- [OBSERVABILITY.md](OBSERVABILITY.md) — observabilidade SRE

---

**Próxima revisão**: 2026-08-16 (trimestre) ou quando entrar 2º cliente Vestuario.
**Wave**: 23 (saturação bucket vertical_client_facing — ADR 0160).
