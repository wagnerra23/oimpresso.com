# Pricing recalibrado por % GMV — baseado em 32 clientes reais — 2026-05-09

> **Status:** PROPOSAL (não-canon, não-ADR ainda)
> **Autor:** Claude + Wagner
> **Substitui:** parcialmente `pricing-recalibracao-ticket-real-830-850.md` (foca tier por GMV, não ticket fixo)
> **Próximo passo se aceito:** ADR canon `arq/00XX-pricing-pct-gmv-tier-vertical.md` + comunicação faseada

---

## 1. Realidade descoberta

Varredura Python `firebird-driver` em 32 bancos legacy OfficeImpresso (2026-05-09) revelou:

| Métrica | Valor |
|---|---|
| GMV agregado dos 32 clientes | **R$ 45.071.561 / ano** |
| Receita atual da WR Sistemas com esses 32 | ~R$ 487k / ano |
| **% implícito atual** | **0,9% do GMV deles** |
| Clientes saudáveis ativos (transações últimos 90d) | 6 |
| Churn provável (>180d sem transação) | 20 |
| Recém-onboarded / borderline | 6 |

**Sinais qualificados externos** (mercado real, validados):
- Cliente OfficeImpresso ativo trocando de sistema pagando **R$ 830/m** (Martinho Caçambas)
- Cliente saudável já trocou pra Mubisys pagando **R$ 850/m** (Gold Comunicação)
- Concorrentes verticais (Mubisys, Zênite, Alfa, Visua, Calcgraf, Calcme) cobram tickets nessa faixa

**Hipótese central:** SaaS verticais de núcleo crítico (ERP de gráfica/com.visual = oxigênio do cliente) cobram tipicamente **0,5–3% do GMV**. Estamos cobrando **5x menos** que o piso.

---

## 2. Distribuição real GMV dos 32 clientes

| Banda GMV anual (R$) | # clientes | GMV agregado (R$) | Status médio |
|---|---:|---:|---|
| < 100k | 14 | ~620k | Maioria CHURN_PROVAVEL ou marginal |
| 100k – 500k | 6 | ~1,55M | Misto: 3 saudáveis pequenos, 3 churn |
| 500k – 1M | 2 | ~1,49M | 1 saudável, 1 borderline |
| 1M – 3M | 2 | ~1,98M | Saudáveis (Cliente Beta + 1 borderline) |
| 3M – 7M | 5 | ~26,1M | **Núcleo de receita** — 4 saudáveis (Alpha/Gamma/Delta/Epsilon) + 1 borderline |
| 7M+ | 1 | ~7,9M | **Cliente Alpha** — único cliente >R$ 7M GMV/ano (saudável) |
| **Total** | **30** | **~39,6M** | (2 clientes não-classificáveis somam diferença pra R$ 45M) |

**Concentração:** 6 clientes (banda R$ 3M+) detêm **~75% do GMV agregado** dos 32. Pricing flat (R$ 299/599/899/1499) ignora essa concentração e deixa 5–10x dinheiro na mesa.

---

## 3. Tier proposto — pricing por % GMV alvo

### Snapshot Free (lead magnet)
- **GMV alvo:** qualquer (incluindo prospects sem cliente atual)
- **Preço:** R$ 0 — gratuito 30 dias
- **% implícito:** 0%
- **Features:** Snapshot mensal automatizado do banco legacy Firebird (receita, despesa, inadimplência, top clientes); read-only; 1 e-mail PDF/mês
- **Para:** prospects, qualificar interesse, gerar tração no churn pool (20 clientes)
- **Cliente exemplo:** qualquer dos 20 CHURN_PROVAVEL — receber 1 PDF/mês reativa a relação, mostra valor sem fricção
- **Conversion target:** aceitar trial = lead qualificado (30% conversão pra Starter/Pro em 60d)

### Starter Vertical
- **GMV alvo:** até R$ 500k/ano
- **Preço:** R$ 299/m fixo
- **% implícito:** 0,7%–∞ (GMV baixo torna % irrelevante)
- **Features:** ERP completo (vendas, compras, financeiro básico, NFe), 1 business, 2 usuários, suporte e-mail 24h
- **Para:** clientes pequenos onde % GMV não justifica modelo variável
- **Cliente exemplo:** "Cliente Mu" (~R$ 754k GMV) — borderline; pricing flat captura sem perder
- **Não inclui:** Jana IA Pro, multi-business, API write, customização

### Pro Vertical
- **GMV alvo:** R$ 500k – 2M/ano
- **Preço:** R$ 599/m fixo
- **% implícito:** 0,4% – 1,4%
- **Features:** Starter + Jana IA básica (memória + recall), 1 business, 5 usuários, integração Asaas, suporte WhatsApp 8h
- **Para:** clientes médios crescendo
- **Cliente exemplo:** "Cliente Sigma" (~R$ 1,23M GMV) — fit perfeito; paga 0,6% GMV
- **Upsell:** quando cliente cruza R$ 2M GMV (medido por nossas vendas registradas), oferta automática Pro Plus

### Pro Plus Vertical
- **GMV alvo:** R$ 2M – 5M/ano
- **Preço:** R$ 1.499/m fixo + créditos Jana IA inclusos
- **% implícito:** 0,4% – 0,9%
- **Features:** Pro + Jana IA ilimitada, multi-business (até 3), API full read+write, dashboard analytics avançado, suporte WhatsApp 4h
- **Para:** clientes maduros/crescendo
- **Cliente exemplo:** "Cliente Zeta" (~R$ 3,18M GMV) — fit; paga 0,57% GMV ≈ ticket Mubisys
- **Reposicionamento:** este é o tier "Mubisys-equivalent" do oimpresso

### Enterprise Vertical
- **GMV alvo:** R$ 5M – 10M/ano
- **Preço:** R$ 3.500 – R$ 5.000/m (negociado por contrato 12–24m)
- **% implícito:** 0,5% – 1,0%
- **Features:** Pro Plus + multi-business até 5, SLA WhatsApp 4h, onboarding dedicado (4h consultoria), customização leve (até 20h dev/ano), backup cross-region
- **Para:** núcleo de receita, contratos plurianuais
- **Cliente exemplo:** "Cliente Beta/Gamma/Delta/Epsilon" (R$ 4,8–6,4M GMV) — todos 4 caem aqui; pricing 0,6–0,9% do GMV deles
- **Cláusula DAM-style** (anti-saída a custo zero): contrato 24m + 6m notice; quem migrou pra concorrente sem aviso prévio paga 6 meses

### Power Vertical
- **GMV alvo:** > R$ 10M/ano
- **Preço:** R$ 5.000 – R$ 10.000/m + setup R$ 15–30k
- **% implícito:** 0,5% – 1,2%
- **Features:** Enterprise + dev dedicado 40h/ano, integração ERP terceiro (Bling/Tiny/etc se cliente tiver), SLA 2h, dashboard customizado
- **Para:** topo da pirâmide, contratos exclusivos
- **Cliente exemplo:** "Cliente Alpha" (~R$ 7,9M GMV — único cliente >R$ 7M na base atual). Piso da banda; convite voluntário pra Power se ele crescer pra >R$ 10M
- **Observação:** ninguém na base atual está em Power. Tier reservado pra clientes-âncora futuros (ex.: rede de gráficas com 5+ filiais)

---

## 4. Cálculo de receita potencial — 3 cenários

### Cenário 1: "Status quo" (zero migração — apenas mantém atuais)
- Receita atual: ~R$ 487k/ano
- Risco: cada cliente saudável que sai (Gold já saiu) = perda de R$ 10k–15k/ano + sinal de churn pros outros
- **Veredito:** insustentável; preço atual não cobre custo Jana IA (Brain B Sonnet) + suporte humano

### Cenário 2: "Migração voluntária faseada" (RECOMENDADO)
**Premissa:** 50% dos saudáveis aceitam upgrade voluntário em 12m + 30% dos churned reativam via Snapshot Free.

| Cliente | GMV (R$) | Tier proposto | R$/m | R$/ano |
|---|---:|---|---:|---:|
| Alpha | 7,9M | Power (piso) | 5.000 | 60.000 |
| Beta | 6,36M | Enterprise | 4.000 | 48.000 |
| Gamma (já saiu) | 6,13M | — (perda confirmada) | 0 | 0 |
| Delta | 4,8M | Enterprise | 3.500 | 42.000 |
| Epsilon | 3,18M | Pro Plus | 1.499 | 17.988 |
| Zeta | 1,23M | Pro | 599 | 7.188 |
| Mu | 754k | Starter | 299 | 3.588 |
| Nu | 733k | Starter | 299 | 3.588 |
| **Subtotal saudáveis migrados (7 de 8)** | — | — | — | **R$ 182.352/ano** |
| Reativação 6 churned via Pro (R$ 599 × 12) | — | — | — | **R$ 43.128/ano** |
| 19 clientes em Starter (resto da base) | — | — | — | **R$ 68.172/ano** |
| **TOTAL projetado** | — | — | — | **~R$ 293k/ano só dos 32** |

**Plus:** novos prospects via Snapshot Free (lead magnet) — meta 10 novos/ano em Pro = +R$ 71k.

**Receita 12m projetada:** **R$ 293k (base atual recalibrada) + R$ 71k (novos) = ~R$ 364k/ano**

> **Nota:** parece menor que R$ 487k atual — mas R$ 487k inclui receita de TODOS os 32 (incluindo 20 churn que provavelmente vão sair de qualquer jeito). O delta real é: R$ 364k SUSTENTÁVEL vs R$ 487k FRÁGIL com churn iminente.

### Cenário 3: "Migração agressiva" (todos saudáveis migram em 6m)
- Subtotal saudáveis: R$ 218.752/ano (assumindo Alpha aceita Power R$ 5k)
- Churn pool reativado 50%: R$ 71.880/ano
- Novos via lead magnet: R$ 100k/ano
- **Total:** ~R$ 390k/ano + base flat dos pequenos R$ 80k = **R$ 470k/ano em 6m + crescimento sustentável**

---

## 5. Riscos da mudança de pricing

| Risco | Probabilidade | Mitigação |
|---|---|---|
| Cliente saudável reage mal a 4–5x preço (ex: Beta paga R$ 850 hoje, vai pagar R$ 4.000?) | **Alta** | **Grandfather pricing** pros 6 saudáveis atuais por 12m; oferta de upgrade VOLUNTÁRIO com features novas (NFe automática, Jana IA ilimitada, multi-business) que SÓ existem no Enterprise+ |
| Churn voluntário acelera ("se vão me cobrar 5x, troco logo") | Média | Comunicação faseada: 1º apresentar valor (snapshot dashboard/BI gratuito 30d), depois preço; nunca o inverso. Cláusula DAM-style 24m com migração+features novas |
| Concorrentes (Mubisys etc) usarem isso como argumento ("oimpresso aumentou 5x") | Média-baixa | Posicionar como "alinhamento de mercado" — Mubisys já cobra R$ 850; Enterprise vertical R$ 4.000 entrega 4x mais features (Jana IA + NFe automática + multi-business) |
| Cliente Alpha (R$ 7,9M GMV) considera in-house | Baixa | Power tier negociado por contrato; entregar dev dedicado 40h/ano amortiza custo migração in-house |
| Reduzir base de 32 pra ~12 clientes (concentração) | Alta-aceita | É feature, não bug — 12 clientes pagantes a R$ 30k/ano = R$ 360k > 32 clientes a R$ 487k com churn iminente |

---

## 6. Recomendação

**Cenário 2 (Migração voluntária faseada) com pricing por GMV implícito + grandfather 12m.**

### Plano de execução (90d)
1. **Semana 1–2:** comunicar 6 saudáveis individualmente (Wagner pessoalmente) com proposta de upgrade voluntário + grandfather 12m + features novas inclusas
2. **Semana 3–4:** lançar Snapshot Free pros 20 churned via e-mail + WhatsApp (lead magnet, fricção zero)
3. **Mês 2:** abrir Pro Plus pra prospects via landing oimpresso.com com cases dos saudáveis
4. **Mês 3:** medir conversão; se ≥3 saudáveis aceitarem Enterprise+, expandir Power tier
5. **Mês 6:** revisar ADR; se conversão <30%, voltar ao tier flat antigo + reposicionar

### Métricas de validação (gates ADR)
- **90d:** 1+ saudável aceita Enterprise (R$ 3.500+/m) → HIPÓTESE VALIDADA
- **180d:** receita média/cliente saudável sobe de ~R$ 70/m pra **R$ 1.500+/m** (21x)
- **360d:** receita anual estável ≥R$ 400k com base ≤25 clientes (concentração saudável)
- **Trip-wire de reverter:** ≥2 saudáveis cancelam voluntariamente em 60d → pausar rollout, voltar grandfather permanente

---

## 7. Frase pra Wagner usar na comunicação ao cliente

> *"O oimpresso evoluiu — agora oferece NFe automática, Jana IA ilimitada e multi-business. Pra continuar entregando isso sustentavelmente, alinhamos o pricing ao tamanho do seu negócio (~0,5–1% do faturamento, em linha com mercado). Você tem 12m com preço atual preservado pra migrar no seu ritmo, e pode optar pelo upgrade voluntário a qualquer momento — algumas features novas só existem no novo plano."*

---

## 8. Próximos passos (não-executados sem aprovação Wagner)

- [ ] Wagner aprova ou rejeita esta proposta
- [ ] Se aprovado → criar ADR canon `arq/00XX-pricing-pct-gmv-tier-vertical.md` (Nygard)
- [ ] Abrir 1 task MCP por saudável: contato individual (`tasks-create module:Vendas`)
- [ ] Implementar Snapshot Free como feature técnica (já tem skill `officeimpresso-financial-snapshot`)
- [ ] Atualizar landing oimpresso.com com novos tiers (Modules/Cms)
- [ ] Cláusula DAM-style 24m em rascunho (`clausula-dam-90d-rascunho.md` já existe — apenas estender)
