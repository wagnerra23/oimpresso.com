# ROI Calculator — oimpresso vs colcha de retalhos

> Modelo Excel-style. Copia/cola pra Google Sheets ou Excel — fórmulas em coluna **Fórmula**.
> Premissa: gráfica média (5-15 funcionários, R$ [redacted Tier 0]k-300k/mês faturamento).
> Todos os números marcados `[XX — validar]` precisam de Wagner aprovar antes de uso comercial.

---

## Inputs (preencher pelo prospect na call)

| Célula | Variável | Exemplo | Fórmula |
|---|---|---|---|
| B2 | Faturamento mensal (R$) | 150.000 | input |
| B3 | # vendas/mês | 220 | input |
| B4 | # NFes/mês | 180 | input |
| B5 | Custo Asaas atual (R$/mês) | 350 | input |
| B6 | Custo Conta Azul / Bling (R$/mês) | 280 | input |
| B7 | Custo planilha-tempo equipe (h/sem) | 8 | input |
| B8 | Custo-hora médio equipe operacional (R$/h) | 35 | input |
| B9 | NFes atrasadas/mês (estimativa cliente) | 12 | input |
| B10 | Multa fiscal média por atraso (R$) `[80 — validar]` | 80 | input |

---

## Cálculos

| Célula | Variável | Fórmula | Descrição |
|---|---|---|---|
| B12 | Custo SaaS atual mensal | `=B5+B6` | Asaas + Bling/CA |
| B13 | Custo planilha-tempo mensal | `=B7*4.33*B8` | h/sem × 4.33 sem/mês × custo-hora |
| B14 | Custo NFe atrasada mensal | `=B9*B10` | multas evitáveis |
| B15 | **Custo total status quo / mês** | `=B12+B13+B14` | soma das dores |
| B16 | **Custo total status quo / ano** | `=B15*12` | projeção 12m |
| | | | |
| B18 | oimpresso Pro (mensalidade) `[draft — Wagner valida]` | 599 | tier Pro proposto |
| B19 | Setup fee one-shot `[draft]` | 2.500 | migração + treinamento |
| B20 | Setup fee amortizado 24m | `=B19/24` | 104.17 |
| B21 | **Custo oimpresso / mês (steady state)** | `=B18+B20` | |
| B22 | **Custo oimpresso / ano** | `=B21*12` | |
| | | | |
| B24 | **Economia / mês** | `=B15-B21` | delta |
| B25 | **Economia / ano** | `=B24*12` | |
| B26 | **Economia / 3 anos** | `=B25*3-B19` | descontando setup só 1× |
| B27 | **Payback (meses)** | `=B19/B24` | quando setup paga sozinho |

---

## Cenário base — gráfica típica

| Variável | Valor |
|---|---|
| Faturamento mensal | R$ [redacted Tier 0] |
| # vendas/mês | 220 |
| # NFes/mês | 180 |
| Asaas | R$ [redacted Tier 0] |
| Conta Azul | R$ [redacted Tier 0] |
| Planilha-tempo | 8 h/sem × R$ [redacted Tier 0]/h |
| NFes atrasadas | 12 × R$ [redacted Tier 0] |
| **Custo status quo/mês** | **R$ [redacted Tier 0] + R$ [redacted Tier 0] + R$ [redacted Tier 0] + R$ [redacted Tier 0] = R$ [redacted Tier 0]** |
| **oimpresso Pro / mês** | **R$ [redacted Tier 0] (599 + 104 setup amortizado)** |
| **Economia / mês** | **R$ [redacted Tier 0]** |
| **Economia / ano** | **R$ [redacted Tier 0]** |
| **Economia / 3 anos** | **R$ [redacted Tier 0]** |
| **Payback** | **~1,2 meses** |

---

## Worst case — gráfica pequena, pouco retrabalho

| Variável | Valor |
|---|---|
| Faturamento mensal | R$ [redacted Tier 0] |
| Asaas | R$ [redacted Tier 0] |
| Conta Azul | R$ [redacted Tier 0] |
| Planilha-tempo | 3 h/sem × R$ [redacted Tier 0]/h |
| NFes atrasadas | 3 × R$ [redacted Tier 0] |
| **Custo status quo/mês** | **R$ [redacted Tier 0] + R$ [redacted Tier 0] + R$ [redacted Tier 0] + R$ [redacted Tier 0] = R$ [redacted Tier 0]** |
| **oimpresso Starter / mês** | **R$ [redacted Tier 0] (Starter, sem setup) `[draft]`** |
| **Economia / mês** | **R$ [redacted Tier 0]** |
| **Economia / ano** | **R$ [redacted Tier 0]** |
| **Payback** | **imediato (sem setup no Starter)** |

---

## Best case — gráfica média-grande, muito retrabalho manual

| Variável | Valor |
|---|---|
| Faturamento mensal | R$ [redacted Tier 0] |
| # vendas/mês | 480 |
| Asaas | R$ [redacted Tier 0] |
| Bling Pro + Conta Azul | R$ [redacted Tier 0] |
| Planilha-tempo | 20 h/sem × R$ [redacted Tier 0]/h |
| NFes atrasadas | 30 × R$ [redacted Tier 0] |
| **Custo status quo/mês** | **R$ [redacted Tier 0] + R$ [redacted Tier 0] + R$ [redacted Tier 0] + R$ [redacted Tier 0] = R$ [redacted Tier 0]** |
| **oimpresso Enterprise / mês `[draft]`** | **R$ [redacted Tier 0] + setup R$ [redacted Tier 0]k amortizado = R$ [redacted Tier 0]** |
| **Economia / mês** | **R$ [redacted Tier 0]** |
| **Economia / ano** | **R$ [redacted Tier 0]** |
| **Economia / 3 anos** | **R$ [redacted Tier 0]** |
| **Payback** | **~0,9 meses** |

---

## Notas de uso comercial

- **NUNCA promete o cenário best-case** — usa o **base** como número-âncora, e mostra worst-case pra vacinar contra "e se der errado?".
- **Multas fiscais (B10)** — pedi `[80 — validar]` mas valor real depende de UF + porte. Wagner precisa fechar antes de mandar pra cliente.
- **Custo-hora equipe (B8)** — perguntar pro cliente, não chutar. R$ [redacted Tier 0]/h é proxy MEI/CLT bruto.
- **Tier Starter sem setup** é decisão de marketing pra abaixar barreira de entrada — Wagner valida em `06-pricing-tiers.md`.
- **Não usar o cálculo de "horas economizadas"** isoladamente — soa abstrato. Sempre **converte em R$**.

---

**Refs internas:** `06-pricing-tiers.md` (tiers), `onepager-financeiro.md` (visão unificada justifica horas economizadas).
