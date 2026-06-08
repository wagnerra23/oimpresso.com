---
module: ComunicacaoVisual
doc_type: matriz_roi
status: draft
last_review: 2026-05-12
owner: [W]
metodologia: "Impacto R$ baseado em Gold (R$ 6,1M GMV/ano, 4.170 lançamentos 12m, ticket médio R$ 1.466) e extrapolação 6 saudáveis. Esforço em horas IA-pair fator 10x recalibrado (ADR 0106). ROI score = (impacto_mensal_R$ / esforço_horas)."
related_adrs: [0121, 0143, 0093, 0094, 0105, 0106, 0117, 0136]
---

# Matriz feature × ROI — Modules/ComunicacaoVisual

> Sinal-zero hoje (0 clientes pagantes CV) — ROI estimado em piloto Gold (R$ 6,1M GMV, 356 vendas/mês — perfil [04-gold-comvis](../../research/clientes-legacy-officeimpresso/04-gold-comvis/01-perfil.md)). Extrapolar pros 6 saudáveis multiplica impacto.
>
> **Metodologia rigorosa:**
> - **Impacto R$ mensal:** estimativa baseada em GMV Gold (R$ 509k/m) × % impacto que feature provoca (margem direta, redução de erro humano, recuperação de receita perdida)
> - **Esforço horas IA-pair:** recalibrado ADR 0106 fator 10x; tarefas humanas (treinamento cliente, smoke prod canary 7d) mantém relógio real
> - **Risco:** baixo (reuso de fundação canon) / médio (lógica nova mas isolada) / alto (depende externalidade — webservice prefeitura, certificado A1)
> - **ROI score:** impacto_mensal_R$ / esforço_horas — normalizado pra comparar diretamente
> - **Concorrente que tem/não tem:** baseado em [SPEC.md §4 concorrentes verticais](SPEC.md) + comparativo Capterra 2026-04-25
> - **Prioridade sugerida:** P0 fundação (bloqueia 1ª piloto) / P1 diferencial competitivo / P2 nice-to-have / P3 futuro

## Matriz 24 features avaliadas

| # | Feature | Impacto R$/mês (Gold) | Esforço (h IA-pair) | Risco | ROI score | Concorrente tem | Concorrente NÃO tem | Prioridade |
|---|---|--:|--:|---|--:|---|---|---|
| 1 | **Cálculo m² automático server-side** (US-COMVIS-001) | R$ 8.000 (5% margem recuperada de erros planilha) | 8h | baixo | 1000 | Mubisys/Zênite/Calcgraf/Calcme/Alfa | — | **P0** |
| 2 | **Cadastro substrato + preço m²** (US-COMVIS-002) | R$ 4.500 (margem por ajuste preciso) | 6h | baixo | 750 | Mubisys/Calcgraf/Visua | — | **P0** |
| 3 | **NFe-de-boleto-pago automática** (US-COMVIS-009) | R$ 6.000 (elimina 80 min/dia financeiro × R$ 30/h × 22 dias) | 4h (adapter — núcleo entregue) | baixo | 1500 | **NENHUM** | Mubisys/Zênite/Calcgraf/Calcme/Alfa/Visua | **P0** |
| 4 | **FSM Pipeline CV (13 stages + 6 actions críticas)** | R$ 12.000 (transparência operacional — Gold tem 29k vendas EM PRODUÇÃO sem histórico estruturado) | 12h | baixo (reuso ADR 0143) | 1000 | Calcgraf (PCP), Zênite (parcial) | Calcme/Alfa/Visua (status genérico) | **P0** |
| 5 | **Tabela tributária CNAE 1813 seed** (US-COMVIS-006) | R$ 1.500 (evita contador R$ 800-2k setup por gráfica) | 3h | médio (cada UF variação) | 500 | Mubisys/Zênite/Calcgraf | Calcme básico | **P0** |
| 6 | **Importer Firebird OfficeImpresso** (US-COMVIS-017) | R$ 25.000 setup one-shot por piloto (vs digitação 80h × R$ 30) | 16h (1ª piloto) + 1h cada subseq | médio (drift Delphi) | 1562 | Bling/Omie genérico | Mubisys/Zênite/Calcgraf vertical | **P0** |
| 7 | **PCP Kanban gráfico CV-vocabulário** (US-COMVIS-003) | R$ 9.000 (reduz 6h/semana operador buscando OS) | 6h (reuso componente Repair) | baixo | 1500 | Mubisys/Zênite/Calcgraf/Visua | Calcme/Alfa | **P0** |
| 8 | **Pós-cálculo orçado vs realizado** (US-COMVIS-005) | R$ 15.000 (descobre OS margem negativa que sangra hoje invisível) | 10h | médio | 1500 | **Calcgraf único** | Mubisys/Zênite/Calcme/Alfa/Visua | **P1** |
| 9 | **Apontamento plotter mobile + CMYK tracking** (US-COMVIS-004) | R$ 7.000 (custo real CMYK/m² visível + plotter ocioso < 15%) | 8h | médio (mobile UX) | 875 | Zênite (IoT diferencial), Mubisys parcial | Calcme/Alfa/Visua | **P1** |
| 10 | **NFSe automática instalação** (US-COMVIS-008) | R$ 4.500 (elimina esquecimento → evita multa fiscal R$ 500-2k/ocorrência) | 14h (driver 3 prefeituras: Floripa/Gravatal/Goiânia) | alto (webservice prefeitura) | 321 | Calcme/Mubisys | Alfa/Visua/Calcgraf parcial | **P1** |
| 11 | **Workflow arte WhatsApp aprovação cliente** | R$ 8.500 (reduz ciclo de aprovação 2 dias → 4h; mais OS/semana) | 8h (reuso ADR 0117 multi-números) | médio (LGPD consent) | 1062 | Calcme (Chatme) | Mubisys/Zênite/Calcgraf/Alfa/Visua | **P1** |
| 12 | **Comissão multi-papel JSON** (vendedor + designer + instalador) | R$ 3.500 (precisão folha — Gold paga 0,5% errado hoje em ~80 comissões/m) | 5h | baixo | 700 | Mubisys/Calcgraf/Zênite (parcial) | Calcme/Alfa/Visua | **P1** |
| 13 | **Gestão fachada/instalação NR-35 + agenda** (US-COMVIS-007) | R$ 5.000 (evita re-trabalho ferramenta esquecida 2 OS/mês × R$ 2,5k) | 12h | médio | 416 | **Visua único (checklist)** | Mubisys/Zênite parcial, Calcme/Alfa/Calcgraf | **P1** |
| 14 | **Dual-doc fiscal NFe55 + NFSe56 simultâneo** | R$ 6.500 (1 OS = 1 cadastro vs 2 vendas separadas em Mubisys/Zênite) | 6h (reuso US-SELL-014 já entregue) | baixo | 1083 | **NENHUM vertical** (Bling/Omie horizontal sem) | Mubisys/Zênite/Calcgraf/Calcme/Alfa/Visua | **P1** |
| 15 | **Bulk update material via Jana IA** (US-COMVIS-013) | R$ 2.500 (1h dono economizada × 12 reajustes/ano) | 4h (reuso PolicyEngine) | baixo | 625 | **NENHUM concorrente entrega** | todos | **P2** |
| 16 | **Dashboard "Larissa-style 22h"** Jana IA conversacional (US-COMVIS-014) | R$ 4.000 (decisão noturna sem abrir relatório — converte em ação) | 6h (reuso Jana 3 ângulos) | médio | 666 | **NENHUM** (Calcme tem WhatsApp não-IA) | todos | **P2** |
| 17 | **Cadastro máquina + cartucho CMYK alerta reposição** (US-COMVIS-015) | R$ 3.000 (evita plotter parar meio job — 4 paradas/m × R$ 750 perda) | 8h | médio | 375 | Zênite (IoT), Mubisys parcial | Calcme/Alfa/Visua/Calcgraf | **P2** |
| 18 | **Provador orçamento online público** (US-COMVIS-010) | R$ 5.500 (8 lead/m × 20% conv × ticket R$ 350) | 10h | baixo | 550 | Calcme/Alfa | Mubisys/Zênite/Calcgraf/Visua | **P2** |
| 19 | **DAM básico Wasabi/Minio S3 + Uppy chunked** (US-COMVIS-012) | R$ 2.500 (vs WhatsApp 80MB caos — 4h/semana designer reduzido) | 12h (UI + storage) | médio | 208 | **Mubisys único (MubiDrive 150TB)** | Zênite/Calcgraf/Calcme/Alfa/Visua | **P2** |
| 20 | **Loja whitelabel pública catálogo** (US-COMVIS-018) | R$ 3.500 (SEO orgânico 6 lead/m × 15% conv × ticket R$ 380) | 12h | baixo | 291 | Alfa/Calcme parcial | Mubisys/Zênite/Calcgraf/Visua | **P3** |
| 21 | **CT-e/MDF-e entrega gráfica** (US-COMVIS-016) | R$ 2.000 (evita multa SINIEF 2026 + ajusta DF-e) | 10h | alto (SEFAZ + sped) | 200 | Calcgraf/Calcme/Bling | Mubisys/Zênite/Alfa/Visua | **P3** |
| 22 | **Reforma Tributária IBS/CBS destacar informativo** | R$ 800 (compliance 2027 prep — sem efeito caixa hoje) | 4h | médio (especulação fiscal) | 200 | nenhum entrega ainda | todos | **P3** |
| 23 | **Mobile-first PWA operador plotter** | R$ 2.500 (1 operador 5 min/dia × 22 dias × R$ 30) | 8h (responsive existente) | baixo | 312 | Mubisys/Zênite mobile apps | Calcgraf/Calcme/Alfa/Visua | **P2** |
| 24 | **Wizard onboarding Jana CNAE 1813 detecta** | R$ 1.500 (elimina consultor R$ 1,5k implantação) | 3h | baixo | 500 | **NENHUM** | todos | **P2** |

## Top 5 features por ROI score

| Rank | Feature | ROI score | Impacto R$/m | Esforço h | Prioridade |
|---|---|---:|---:|---:|---|
| 🥇 | **Importer Firebird OfficeImpresso** (#6) | 1562 | R$ 25k setup + R$ 1k recorrente | 16h | P0 |
| 🥈 | **NFe-de-boleto-pago automática** (#3) | 1500 | R$ 6k | 4h | P0 |
| 🥈 | **PCP Kanban gráfico vocabulário** (#7) | 1500 | R$ 9k | 6h | P0 |
| 🥈 | **Pós-cálculo orçado vs realizado** (#8) | 1500 | R$ 15k | 10h | P1 |
| 🥉 | **Dual-doc fiscal NFe55 + NFSe56** (#14) | 1083 | R$ 6,5k | 6h | P1 |

## Top 5 diferenciais (concorrente NÃO tem)

| # | Feature | Concorrentes que NÃO têm | Wedge competitivo |
|---|---|---|---|
| 1 | NFe-de-boleto-pago automática | **TODOS** (Mubisys/Zênite/Calcgraf/Calcme/Alfa/Visua) | "Boleto cai, NFe sai. Zero clique humano." |
| 2 | Bulk update material via Jana IA chat | **TODOS** | "Aumenta 5% em todo lona 440g" no chat às 22h |
| 3 | Dashboard Jana 22h (3 ângulos faturamento) | **TODOS** (Calcme WhatsApp não-IA) | "Quanto faturei hoje?" com SQL auditável |
| 4 | Dual-doc fiscal NFe55 + NFSe56 simultâneo em 1 OS | **TODOS verticais** (Bling/Omie horizontal não dual) | "1 OS, 2 notas, 1 cadastro" |
| 5 | Wizard onboarding Jana detecta CNAE 1813 | **TODOS** | Cliente paga consultor R$ 1,5k → conosco R$ 0 |

## ROI agregado por fase roadmap

| Fase | Features incluídas | Esforço total (h) | Impacto/m (Gold piloto) | Impacto/m × 6 clientes |
|---|---|---:|---:|---:|
| **Fase 1 V0** (scaffold sem sinal) | módulo nWidart + migrations + Models | 24h | 0 | 0 |
| **Fase 2 piloto Gold** | #1, #2, #3, #4, #5, #6, #7 (P0) | 55h | R$ 65,5k | R$ 65,5k (1 cliente) |
| **Fase 3 rollout 6 saudáveis** | P0 + reusos | +6h/cliente | R$ 65,5k/cliente | R$ 393k |
| **Fase 4 diferencial** | #8, #9, #10, #11, #12, #13, #14 (P1) | 63h | +R$ 44k/cliente | +R$ 264k |
| **Fase 5 expansão** | #15-#24 (P2/P3) | 75h | +R$ 25,3k/cliente | +R$ 151,8k |

**Total esforço M1-M12 IA-pair recalibrado:** ~218h = ~27,3 dias úteis humano + 80% IA-pair.

**Impacto agregado M12 6 saudáveis migrados pagantes:**
- P0 only: R$ 393k/mês potencial × 12 = **R$ 4,71M/ano impacto cliente** (sangria recuperada + eficiência)
- P0 + P1: R$ 657k/mês × 12 = **R$ 7,88M/ano**
- ARR oimpresso (≠ impacto cliente): R$ 30-60k M12 conforme SPEC §7, escalando R$ 100-200k M24

## Decisões pendentes pra Wagner

1. **P0 strict 7 features ou stretch P0+P1?** SPEC SPEC.md §7 sugere P0 + alguns P1 em M2-M4. Recomendo P0 strict pra V0/Fase 2, P1 incremental Fase 3-4.
2. **Sinal qualificado ativa feature P2/P3?** ADR 0105 — só implementar P2 (#15-#18) e P3 (#19-#22) se cliente pagante pedir explicitamente OU métrica detectar drift. Caso contrário, registrar como `feature_wish` em ADR.
3. **Driver NFSe ordem geográfica** — Floripa first (ABRASF v2.04, Extreme/Vargas potencial SC) ou Goiânia (Gold confirmado GO)? Wagner valida após snapshot financeiro Gold concluído.

## Notas metodológicas

- **Impacto R$ estimado** — não medido; baseline = Gold (R$ 509k/m GMV × % impacto razoável). Refinar quando 1ª piloto rodar 3 meses.
- **Esforço IA-pair** — assume Wagner aprovou ADR 0106 (fator 10x em tarefas codáveis). Realocação humana: treinar cliente, smoke canary 7d, monitor 30d mantém wallclock real.
- **Risco médio/alto** — depende de externalidade (webservice prefeitura, plotter SDK, drift Delphi). Mitigação: stub graceful + cliente reporta + adapter incremental.
- **ROI score** = (impacto_mensal_R$ / esforço_horas) — não é VPN/payback. Usar pra rankear features SIMILARES em prioridade, não decidir P0 vs P3 isoladamente.

## Refs

- [SPEC.md](SPEC.md) — backlog completo US-COMVIS-001..018
- [ComunicacaoVisual.charter.md](ComunicacaoVisual.charter.md) — charter módulo
- [proposal ADR ComunicacaoVisual canônico](../../decisions/proposals/drafts/comunicacao-visual-modulo-canonico.md) — decisões arquiteturais
- [Gold piloto perfil](../../research/clientes-legacy-officeimpresso/04-gold-comvis/01-perfil.md) — baseline numérico
- [Gold financeiro 12m](../../research/clientes-legacy-officeimpresso/04-gold-comvis/03-financeiro-2026-05-11.md) — R$ 6,1M receita / 4.170 lanç
- [_ANALISE-CROSS-CLIENTE.md](../../research/clientes-legacy-officeimpresso/_ANALISE-CROSS-CLIENTE.md) — cross-cliente padrões
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — sinal qualificado (gating P2/P3)
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — esforço recalibrado 10x
