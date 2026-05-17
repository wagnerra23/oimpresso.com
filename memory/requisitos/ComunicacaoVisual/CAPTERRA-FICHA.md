# CAPTERRA-FICHA — ComunicacaoVisual

> Ficha canônica de benchmark do módulo vertical ComunicacaoVisual.
> **Bucket**: `vertical_client_facing` ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md))
> **Wave 23** — saturação 41.5 → ≥85 (gap maior do bucket; rubrica scoped vertical_client_facing.yaml)
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) + [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7

---

## Identidade do módulo

- **Nome interno**: `ComunicacaoVisual`
- **CNAE**: 1813-0/01 (Impressão de material publicitário)
- **Estado lifecycle** (ADR 0121): **em construção** (planejado piloto 2026-Q3 entre 6 saudáveis OfficeImpresso)
- **Candidatos piloto**: Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart (6-7 saudáveis OfficeImpresso legacy Delphi)
- **Diferencial-chave**: cálculo m² + PCP gráfico + apontamento + NFe-de-boleto-pago + IA conversacional

## Concorrentes-alvo

| Concorrente | Pricing/mês | Foco | Lacuna oimpresso preenche |
|---|---|---|---|
| **Mubisys Gráfica** | R$ [redacted Tier 0]-800 | gráficas rápidas pequenas | UI legacy, sem IA, BI raso |
| **Zênite** | R$ [redacted Tier 0]-1000 | médio porte | sem cálculo m² granular, custo apurado fraco |
| **Calcgraf** | R$ [redacted Tier 0]-600 | calculadora gráfica nicho | só cálculo, sem PCP/apontamento integrado |
| **Bling Gráfica** | R$ [redacted Tier 0]-400 | horizontal raso | sem fluxo plotter/m²/apontamento |

## Capacidades baseline (Sprint 1-2 entregue 2026-Q2)

```yaml
capacidades_baseline:
  - us: US-COMVIS-001
    nome: "Criar orçamento gráfico com cálculo m²"
    score: P0
    onde: "OrcamentoController + OrcamentoCalculator service"
    evidencia: "CustomerJourneyTest.php cobre criação + total"

  - us: US-COMVIS-002
    nome: "Aprovar orçamento → gerar OS"
    score: P0
    onde: "Orcamento.status state + Os entity"
    evidencia: "FSM canon ADR 0143 cv_ordens_producao.current_stage_id"

  - us: US-COMVIS-003
    nome: "OS com FSM canon (estágios pré-impressão→entrega)"
    score: P0
    onde: "app/Domain/Fsm/ (cv_ordens_producao.current_stage_id)"

  - us: US-COMVIS-004
    nome: "Apontamento produção (iniciar/finalizar + m² real)"
    score: P0
    onde: "ApontamentoController + ApontamentoTracker service"
    evidencia: "drift m² calculado (m2_produzido vs m2_orcado)"
```

## Top 5 gaps P0 (pra subir nota ≥85 — gap maior do bucket)

| US | Capacidade | Esforço | ROI estimado | Concorrente que tem |
|----|------------|---------|--------------|---------------------|
| US-COMVIS-005 | Custo apurado por OS (matéria + apontamento + maquinário) | 24h | alto (margem real PCP) | Zênite parcial |
| US-COMVIS-006 | Workflow pré-impressão (arte→aprovação cliente→PCP) | 32h | alto (rework -50%) | Mubisys |
| US-COMVIS-007 | Catalogo de produtos gráficos com fórmulas m²/m linear | 16h | médio (cotação +3x velocidade) | Calcgraf |
| US-COMVIS-008 | Entrega/instalação fachada (campo + foto + assinatura) | 20h | médio (paridade visual mkt) | Mubisys parcial |
| US-COMVIS-009 | BI dashboard OEE plotter/maquinas (eficiência) | 24h | alto (ROI direto) | nenhum tem |

## Diferenciais oimpresso vs concorrentes

1. **Cálculo m² granular** com fórmulas customizáveis por produto (lona, adesivo, banner, painel)
2. **Apontamento real-time** com drift m² (orçado vs produzido) — concorrente nenhum tem
3. **NFe-de-boleto-pago automática** (US-RB-044, ADR 0089) — exclusividade
4. **Jana IA conversacional** (ADR 0035) — "quanto produzi de adesivo essa semana?"
5. **FSM canon multi-stage** (ADR 0143) — auditoria append-only de cada transição
6. **Stack moderna** Laravel 13.6 + React 19 + Inertia v3 vs Mubisys Delphi/Zênite jQuery

## Score Capterra W22 → W23

| Dimensão (scoped vertical_client_facing) | W22 | W23 alvo |
|------------------------------------------|-----|----------|
| V1 Pest E2E Customer Journey | 4/15 | **12/15** |
| V2 Code Quality FormRequests | 6/10 | 9/10 |
| V3 Perf UX (Inertia::defer) | 3/10 | 6/10 |
| V4 LGPD retention canon | 7/15 | **14/15** |
| V5 Docs canon (BRIEFING/CHANGELOG/CAPTERRA) | 4/20 | **18/20** |
| V6 Capterra ROI Top 5 | 2/10 | 7/10 |
| **Total scoped** | **41.5/100** (gap maior) | **≥85/100** |

## Status lifecycle (ADR 0121)

- ✅ `em_construção` — schema multi-vertical + 4 US baseline + Sprint 1-2
- ⏳ `piloto` (meta 2026-Q3) — exige 1+ cliente saudável OfficeImpresso migrado
- ⏳ `ativo` (meta 2027-Q2+) — 3+ clientes pagantes

## Anti-padrões (Tier 0 IRREVOGÁVEIS)

- ⛔ Tabela `cv_*` sem `business_id` indexed + FK + global scope (ADR 0093)
- ⛔ Apontamento com SoftDeletes (registro legal append-only — CCom Art. 195 + Portaria 671)
- ⛔ Migrar cliente saudável sem qualificação ADR 0105 (sinal real ≠ wish)
- ⛔ Smoke test `business_id=4` (ROTA LIVRE PROD) — usar biz=1 ou biz=99

## Referências

- [SPEC.md](SPEC.md) — US-COMVIS-001..009
- [ComunicacaoVisual.charter.md](ComunicacaoVisual.charter.md) — module charter
- [MATRIZ-ROI.md](MATRIZ-ROI.md) — ROI top capacidades
- [PLANO-MIGRACAO-6-SAUDAVEIS.md](PLANO-MIGRACAO-6-SAUDAVEIS.md) — pipeline migração

---

**Próxima revisão**: 2026-08-16 (trimestre) ou quando 1º piloto fechar.
**Wave**: 23 (saturação bucket vertical_client_facing — ADR 0160).
