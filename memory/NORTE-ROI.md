# NORTE-ROI — o que fazer primeiro, sempre, pelo ROI

> **Norte único do oimpresso.** Toda decisão (módulo, onda, regra, skill, refactor) passa por aqui antes de virar trabalho. Se não move o ROI rumo à meta, **espera**.

## A meta (fixa)

**R$ [redacted Tier 0] milhões/ano** ([ADR 0022](decisions/0022-meta-5mi-ano-financeira.md)). (R$ [redacted Tier 0]M/24m da meta-skill = a mesma coisa: R$ [redacted Tier 0]M ÷ 2 anos = R$ [redacted Tier 0]M/ano.)

Como se chega (revenue-thesis): ~50 enterprise + 120 médios + 200 pequenos = **R$ [redacted Tier 0]k/mês**. Caminho: **vender o que já está validado pra mais clientes** > construir o que não tem cliente.

## A fórmula de ROI (uma linha)

```
ROI = (receita_anual_realista × sinal_de_cliente) ÷ esforço
```
- **sinal_de_cliente** ([ADR 0105], o filtro nº1): paga+reporta = **1.0** · candidato qualificado = **0.5** · hipótese sem sinal = **0.2**
- receita: do `reference/revenue-thesis-modulos.md` · esforço: P / M / G

## Ranking — onde investir (o norte operacional)

### 🟢 TIER 1 — ROI alto, fazer AGORA (vender o validado)
| # | Iniciativa | Por quê (ROI) |
|---|---|---|
| 1 | **Financeiro** | foundational — todo cliente paga; ROTA LIVRE já usa (sinal 1.0); receita base R$ [redacted Tier 0]-599 |
| 2 | **Vestuario — aprofundar + replicar** | ROTA LIVRE paga+reporta (sinal 1.0), validado 2+ anos; replicável pra +clientes vestuário |
| 3 | **RecurringBilling** | receita recorrente + take rate 0,8% sobre volume; alavanca direta de R$/mês |
| 4 | **NfeBrasil** | compliance obrigatório — todo cliente BR precisa; R$ [redacted Tier 0]-599 |

### 🟡 TIER 2 — ROI médio, quando Tier 1 anda
| # | Iniciativa | Por quê |
|---|---|---|
| 5 | **Copiloto / LaravelAI** | add-on premium (multiplier de ticket); diferencial IA |
| 6 | **WhatsApp** | diferencial de atendimento — sobe retenção/conversão |
| 7 | **ComunicacaoVisual** | 6 candidatos (Vargas/Extreme/Gold/Zoom/Fixar/Mhundo) — **converter 1 pagante sobe pra Tier 1** |

### 🔴 TIER 3 — ROI baixo até ter sinal, ESPERAR
| # | Iniciativa | Por quê espera |
|---|---|---|
| 8 | **OficinaAuto** | sem cliente pagando — ADR 0105: feature-wish até sinal qualificado (Martinho a confirmar) |
| 9 | **Governança abstrata** (grade/hooks/ADR além do mínimo) | ROI **indireto** — só vale o que evita erro que custa cliente/receita. Governança é MEIO, não FIM |

## Onde você ganha dos concorrentes (justifica o preço)

- vs Bling/Tiny/Conta Azul/Omie (horizontal raso): multi-tenant Tier 0 + Jana IA com memória + governança formal
- vs Mubisys/Zênite/Calcgraf (ComVis): stack moderna + NFe-de-boleto-pago automática + IA
- Vestuário: ROTA LIVRE valida; Oficina: pendente sinal

## Regra de ouro do norte

> **Antes de qualquer trabalho: "isso aproxima R$ [redacted Tier 0]M/ano via um cliente que paga+reporta? Qual o ROI?"** Se a resposta é fraca → Tier 3, espera. A governança desta sessão (grade/método) é Tier 3: só sobe se evita um erro que custaria cliente.
