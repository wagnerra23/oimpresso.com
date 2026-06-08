# Pricing ERPs oficina auto BR — 2026-05-09

> Pesquisa pública pra calibrar quanto oimpresso poderia cobrar se expandir do nicho gráfica de comunicação visual pro nicho oficina auto BR. Comparar contra `05-pricing-real-concorrentes-horizontais.md` (gráfica vertical) e contra Bling/Conta Azul (horizontal).
>
> **Metodologia:** sites oficiais como fonte primária; valores marcados `[inferido — validar]` quando não confirmáveis em página pública. Coletado 2026-05-09 (Wagner [W] + Claude).
>
> **Caveat:** ERPs originalmente solicitados (Mecanico/Tecnosistemas, ManagerOS, Auto Manager, Lokoz, Olho Vivo, OficinaMaster, Carros2, Garage HuntDevs, Skill Auto, Workshop SaaS) **não retornaram pricing público**. Substituídos por equivalentes verticais com pricing publicado (Oficina Integrada, Oficina Inteligente, Ultracar, Onmotor, MecânicaFlow, Manager Full, NeXT, GestãoClick, vhsys, MinhaOficina, APTO, IS2 Automotive). Marca-se claramente o que é "ERP oficina vertical real BR" vs "horizontal usado em oficina".

---

## Sumário executivo

- **ERP oficina vertical BR puro:** range R$ **70–599/m** (mais barato MinhaOficina Bronze R$ 70/m; mais caro Oficina Inteligente Fantástico R$ 599/m). **Mediana ~R$ 200–340/m.**
- **Diferença vs gráfica vertical:** ERP oficina é **30–40% mais barato** que ERP gráfica horizontal (Bling Titânio T2 ~R$ 200, Conta Azul Avançado R$ 400). Mas ticket por cliente **menor** porque oficina típica tem menos ciclos de boleto (~150–500 OS/m vs gráfica ~500–1.500 vendas/m). **Universo 30x maior** (150k oficinas BR vs 5k gráficas SP) compensa.
- **Setup fee** quase nenhum cobra explicitamente (vs gráfica/horizontal que também não cobra). Setup R$ 999–2.500 oimpresso seria **anomalia no nicho**. Recomendação: setup zero como default + cobrar só "migração documentada" como opcional.
- **Trial 7 dias** é o padrão (5 ERPs com 7d, 1 com 5d, 1 com 10d, 1 com 30d NeXT). 14d oimpresso seria competitivo sem ser exagero.
- **App mobile** é diferencial declarado em 4 ERPs (Oficina Integrada, Manager Full, MecânicaFlow, Ultracar pós-vendas). **Faltar app = atrito alto** no nicho oficina (mecânico no chão da oficina).
- **Integração DETRAN/CRLV** não é vendida como tier-1 nem por Oficina Integrada nem Ultracar — usam APIs terceiras (Infosimples, API Placas, ConsultarPlaca). É **diferencial possível pra oimpresso** se pacote nativo.

---

## Tabela completa de tiers (12 ERPs × pricing)

| ERP | Tier | Preço/m (anual) | Preço/m (mensal) | Setup | Users | OS/m | NFe? NFCe? NFSe? | Trial | Suporte | App? |
|---|---|---|---|---|---|---|---|---|---|---|
| **Oficina Integrada** | 1 user (MEI) | R$ 109,65 | R$ 129 | 0 | 1 | Ilim. | NF-e + NFS-e + NFC-e ilim. | 7d | Online grátis | iOS+Android |
| **Oficina Integrada** | até 2 users | R$ 157,25 | R$ 185 | 0 | 2 | Ilim. | NF-e + NFS-e + NFC-e ilim. | 7d | Online grátis | iOS+Android |
| **Oficina Integrada** | até 10 users | R$ 288,15 | R$ 339 | 0 | 10 | Ilim. | NF-e + NFS-e + NFC-e ilim. | 7d | Online grátis | iOS+Android |
| **Oficina Integrada** | ilim. users | R$ 322,15 | R$ 379 | 0 | Ilim. | Ilim. | NF-e + NFS-e + NFC-e ilim. | 7d | Online grátis | iOS+Android |
| **Oficina Inteligente** | Inteligente | n/d | R$ 399 | n/d | Ilim. | Ilim. | NF-e + NFC-e + NFS-e | n/d | Ilimitado | Web+celular |
| **Oficina Inteligente** | Fantástico | n/d | R$ 599 | n/d | Ilim. | Ilim. | NF-e + NFC-e + NFS-e + multi-unidade | n/d | Ilimitado | Web+celular |
| **Ultracar** | Ultra Plus | n/d | R$ 324 | n/d | 5 | n/d | NF peças/serviço/consumidor + Sintegra | 7d | Chat + telefone | Não declarado |
| **Ultracar** | Ultra Master | n/d | R$ 494 | n/d | Ilim. | n/d | NF + SAT/MF-e + SEFAZ + manifestação | 7d | Chat + tel + WhatsApp | App pós-vendas |
| **Onmotor** | V1 (free) | R$ 0 | R$ 0 | 0 | n/d | 50 | ❌ | 5d (V12) | Treino remoto | Não declarado |
| **Onmotor** | V2 | R$ 47,60 | n/d | 0 | n/d | Ilim. | ❌ | 5d | Incluso | Não decl. |
| **Onmotor** | V6 | R$ 142,80 | R$ 168 | 0 | Ilim. | Ilim. | ❌ | 5d | Incluso + WhatsApp | Não decl. |
| **Onmotor** | V8 | R$ 210,80 | R$ 248 | 0 | Ilim. | Ilim. | ❌ | 5d | Incluso | Não decl. |
| **Onmotor** | V10 | R$ 337,45 | R$ 397 | 0 | Ilim. | Ilim. | ❌ | 5d | Incluso | Não decl. |
| **Onmotor** | V12 (NFe) | R$ 407,15 | R$ 479 | 0 | Ilim. | Ilim. | NF-e peças/serviço/cupom | 5d | Full | Não decl. |
| **MecânicaFlow** | Anual | R$ 210 | n/d | 0 | n/d | Ilim. | NF-e + NFC-e + NFS-e + CC-e | 7d | WhatsApp | Não decl. |
| **MecânicaFlow** | Único | n/d | R$ 300 | 0 | Ilim. clientes | Ilim. | NF-e + NFC-e + NFS-e + CC-e | 7d | WhatsApp | Não decl. |
| **Manager Full** | Completo | R$ 155 | n/d | 0 | Ilim. | Ilim. | NF-e + NFS-e + NFC-e + busca XML SEFAZ | 7d (money-back) | Premium + treino | Web mobile |
| **NeXT Software** | Professional | R$ 69 | R$ 99 | 0 | n/d | Ilim. NFe | NF-e + NFS-e + NFC-e + SAT | 30d | Chat + tel + remoto | Não decl. |
| **NeXT Software** | Enterprise | R$ 89 | R$ 149 | 0 | n/d | Ilim. | NF-e + NFS-e + NFC-e + e-commerce | 30d | Chat + tel + remoto | Não decl. |
| **GestãoClick** | Bronze | R$ 119 | R$ 183,08 | 0 | 1 | n/d | NF-e (não NFC-e/NFS-e claro) | 10d | Grátis | 100% online |
| **GestãoClick** | Prata | R$ 199 | R$ 306,15 | 0 | 3 | n/d | NF-e + boleto | 10d | Grátis | 100% online |
| **GestãoClick** | Ouro | R$ 289 | R$ 444,62 | 0 | 5 | n/d | NF-e + boleto | 10d | Customer Success | 100% online |
| **GestãoClick** | Platina | R$ 379 | R$ 583,08 | 0 | 7 | n/d | NF-e + boleto + assinatura digital | 10d | Customer Success | 100% online |
| **vhsys** | Inicial [inferido] | A partir R$ 39,90 | n/d | 0 | n/d | n/d | NF-e + NFS-e + NFC-e + CT-e + MDF-e | 7d | Chat | Web |
| **MinhaOficina** | Bronze | n/d | R$ 70 | 0 | n/d | n/d | n/d | 10d (Ouro) | Chat + ticket + remoto | Não decl. (Windows install) |
| **MinhaOficina** | Prata | n/d | R$ 73 | 0 | n/d | n/d | n/d | 10d (Ouro) | Chat + ticket + remoto | Windows install |
| **MinhaOficina** | Ouro | n/d | R$ 104 | 0 | n/d | n/d | n/d | 10d | Chat + ticket + remoto | Windows install |
| **APTO Sistemas** | Básico/Padrão/Avançado | A partir R$ 87,92 | n/d | 0 | n/d | n/d | NF-e + NFC-e + NFS-e | 7d | Remoto | Não decl. |
| **IS2 Automotive** | Lifetime base | — | R$ 112 (1×/PC) | n/a (single payment) | Por PC | n/d | NFe módulo R$ 172/CNPJ (1×); ou R$ 112/m c/ NFe | 60d demo | WhatsApp + vídeos | Windows install |
| **WSoft (mencionado)** | Único | n/d | R$ 79,90 | 0 | n/d | n/d | OS+NFe+financeiro+estoque+CRM | n/d | n/d | n/d |
| **Tempario (TMO)** | Único | n/d | R$ 79 | 0 | n/d | n/a (TMO não é ERP) | n/a (orçamentista) | n/d | n/d | n/d |
| **Syscar TEMPOCERTO** | Único | n/d | R$ 69 | n/d | n/d | n/d | n/d | n/d | n/d | n/d |

> **Notas:** valores `[inferido]` precisam validação direta com SDR; ERPs originalmente listados (Mecanico/Tecnosistemas, ManagerOS, Auto Manager, Lokoz, Olho Vivo, OficinaMaster, Carros2, Garage HuntDevs, Skill Auto, Workshop SaaS) **não publicam pricing rastreável** em busca pública 2026-05-09. Tabela substitui por equivalentes verticais com preço público.

---

## Cálculo "stack atual" oficina típica

### Cenário A — Oficina pequena (1-3 mecânicos, ~50 OS/m, 1 CNPJ, dono opera tudo)

| Item | Valor/m |
|---|---|
| ERP vertical (Onmotor V2 ou MinhaOficina Bronze) | R$ 47,60–70 |
| Asaas: ~50 boletos × R$ 1,99 + ~10 cartão × R$ 200 × 2,99% | R$ 159,40 |
| WhatsApp Business (gratuito API básica) | R$ 0 |
| Conta Simples (gestão financeira PJ) | R$ 49,90 |
| Tempario TMO (orçamento mão-de-obra) | R$ 79 |
| **Subtotal** | **R$ 335,90–358,30** |

### Cenário B — Oficina média (5-10 mecânicos, ~200 OS/m, 1-2 CNPJ)

| Item | Valor/m |
|---|---|
| ERP vertical (Oficina Integrada até 10 users anual ou Onmotor V8) | R$ 248–288 |
| Asaas: ~250 boletos × R$ 1,99 + ~50 cartão × R$ 350 × 2,99% | R$ 1.020,50 |
| WhatsApp Business API (~R$ 80/m) | R$ 80 [inferido] |
| Conta Azul Controle (financeiro+contabilidade) | R$ 309,90 |
| API DETRAN/Placas (~R$ 0,15/consulta × ~400/m) | R$ 60 [inferido] |
| **Subtotal** | **R$ 1.718,40–1.758,40** |

### Cenário C — Oficina grande (10-20 mecânicos, ~500 OS/m, multi-loja)

| Item | Valor/m |
|---|---|
| ERP vertical (Oficina Inteligente Fantástico ou Ultracar Master) | R$ 494–599 |
| Asaas/Iugu: ~600 boletos + ~150 cartão × R$ 400 × 2,99% | R$ 2.989,40 |
| WhatsApp Business API + suporte (~R$ 250/m) | R$ 250 [inferido] |
| Conta Azul Avançado | R$ 399,90 |
| API DETRAN/Placas (~R$ 0,15 × 1.200/m) | R$ 180 [inferido] |
| Treinamento contábil/CFOP separado | R$ 200 [inferido] |
| **Subtotal** | **R$ 4.513,30–4.618,30** |

> Range realista cliente final: **R$ 250–800/m em ERP+contabilidade** (sem contar transações Asaas que são marginais ao ERP).

---

## Análise: oimpresso seria competitivo?

### Auto Starter R$ 199/m (1 oficina, 1-3 mecânicos, 100 OS/m)
- **Concorrentes diretos:** Onmotor V2 R$ 47,60 (anual), MinhaOficina Bronze R$ 70, NeXT Professional R$ 69 (anual), APTO Básico R$ 87,92
- **Mediana mercado entry tier:** ~R$ 70–130
- **Veredicto:** **CARO em comparação direta** — 2-3x os entry tiers verticais. Mesmo problema do Starter R$ 299 oimpresso na gráfica (5x Bling)
- **Justificativa pra manter R$ 199:** posicionamento ERP vertical-vertical (oficina é vertical já no mercado; oimpresso seria "vertical-com-Jana-IA-embutida" — diferencial precisa ser claro). NFCe automática + API DETRAN nativa + IA Jana
- **Risco:** baixíssima conversão se cliente compara só preço

### Auto Pro R$ 399/m + setup R$ 999 (1 oficina, 4-10 mecânicos, 500 OS/m)
- **Concorrentes diretos:** Oficina Integrada até 10 users R$ 288 (anual), Onmotor V10 R$ 337 (anual), Oficina Inteligente Inteligente R$ 399, GestãoClick Ouro R$ 289 (anual), Ultracar Plus R$ 324
- **Mediana mercado mid tier:** ~R$ 290–399
- **Veredicto:** **PRECIFICADO NO TETO do mercado mid.** R$ 399 = igual Oficina Inteligente entry. **Setup R$ 999 é anomalia** — todos os concorrentes cobram zero setup
- **Risco:** "por que pagar R$ 399 + R$ 999 setup se Oficina Integrada cobra R$ 288 + 0?" → conversão sofre. **Recomendação: setup zero default** (cobrar só "migração documentada de Delphi/concorrente" como opcional R$ 999)

### Auto Premium R$ 999/m + setup R$ 2.500 (multi-loja, 11-30 mecânicos)
- **Concorrentes diretos:** Oficina Inteligente Fantástico R$ 599, Ultracar Master R$ 494, GestãoClick Platina R$ 379 (anual)
- **Mediana mercado top tier:** ~R$ 494–599
- **Veredicto:** **MUITO CARO** — 1,7-2x acima do top vertical. Só faria sentido se oimpresso entregar 2x o valor (multi-loja real + IA Jana faturando + API DETRAN nativa + integração CRLV nativa + SLA telefônico)
- **Risco:** setup R$ 2.500 amortizado em 12 meses só vale se cliente economizar R$ 200+/m vs alternativa (improvável quando alternativa custa metade)

---

## Pricing proposto (3 tiers calibrados pelo nicho)

### Auto Starter R$ 149/m (revisado — vs original R$ 199)
- **Inclui:** 1 oficina, 1-3 mecânicos, 100 OS/m, 1 user dono, NF-e + NFC-e + NFS-e ilim., WhatsApp básico, app mecânico read-only
- **Não inclui:** API DETRAN, multi-loja, Jana IA, app mecânico full
- **Trial 14 dias** sem cartão de crédito
- **Setup R$ 0** (default)
- **Posicionamento:** "ERP vertical oficina com NFe automática + Jana IA básica — entry tier"

### Auto Pro R$ 349/m + setup R$ 0 default (revisado — vs original R$ 399 + R$ 999)
- **Inclui:** 1 oficina, 4-10 mecânicos, 500 OS/m, 5 users, app mecânico básico, NF-e/NFC-e/NFS-e ilim., API consulta placa (incluso 200 consultas/m), Jana IA Sprint 1 (resposta sobre OS/cliente)
- **Setup R$ 999 opcional** quando: migração de Delphi/Excel/outro ERP documentada (treino 4h + ativação custom)
- **Anual:** "12 paga 10" — R$ 3.490/ano
- **Trial 14 dias** sem cartão
- **Posicionamento:** "tier mainstream, igual Oficina Integrada/Onmotor mas com Jana IA + API placa"

### Auto Premium R$ 699/m + setup R$ 1.500 (revisado — vs original R$ 999 + R$ 2.500)
- **Inclui:** multi-loja (até 5 unidades), 11-30 mecânicos, ilim. OS, ilim. users, app mecânico full, NF tudo + CT-e/MDF-e, API DETRAN + CRLV nativa, Jana IA full, SLA telefônico, customer success dedicado
- **Setup R$ 1.500** (treino presencial 8h ou online 16h + onboarding multi-loja + customização CFOP/CSOSN)
- **Anual:** "12 paga 10" — R$ 6.990/ano
- **Trial 14 dias**
- **Posicionamento:** "tier top, comparável Oficina Inteligente Fantástico R$ 599 com diferencial CRLV+Jana"

> **Anual padrão "12 paga 10":** mantém gancho competitivo vs Bling/Tiny/concorrentes verticais que também oferecem 2 meses grátis no anual.

---

## ROI estimado se entrar no nicho oficina auto

### Premissas
- Universo BR: ~150k oficinas (vs ~5k gráficas SP) — **30x maior**
- Tier mais provável de conversão: **Auto Pro R$ 349/m** (mainstream)
- CAC esperado: alto inicial (sem awareness no nicho), R$ 800–1.500 por cliente
- Churn esperado: 4-5%/m inicial (ERP vertical tem churn baixo após onboarding)

### Cenário 12 meses

| Marco | Clientes | MRR | ARR |
|---|---|---|---|
| Mês 3 | 5 | R$ 1.745 | R$ 20.940 |
| Mês 6 | 12 | R$ 4.188 | R$ 50.256 |
| Mês 9 | 20 | R$ 6.980 | R$ 83.760 |
| Mês 12 | **30** | **R$ 10.470** | **R$ 125.640** |

> 30 oficinas em 12m × R$ 349 = **R$ 125.640 ARR** (não R$ 143.640 do briefing original, que assumia R$ 399). Realista mas modesto.

### Cenário 24 meses

| Marco | Clientes | MRR | ARR |
|---|---|---|---|
| Mês 18 | 60 | R$ 20.940 | R$ 251.280 |
| Mês 24 | **100** | **R$ 34.900** | **R$ 418.800** |

> 100 oficinas em 24m × R$ 349 = **R$ 418.800 ARR** (vs R$ 478.800 do briefing). Plausível mas exige investimento marketing R$ 80–150k/ano.

### Comparação com gráfica
- **Gráfica universo 5k SP** + ticket alto (Pro R$ 599): 30 clientes × R$ 599 = R$ 215.640 ARR
- **Oficina universo 150k BR** + ticket médio (Pro R$ 349): 30 clientes × R$ 349 = R$ 125.640 ARR
- **Conclusão:** ticket marginal **42% menor** em oficina, mas universo **30x maior**. Decisão depende de:
  - Se oimpresso consegue manter Auto Pro R$ 349 (não competitivo abaixo) → mercado é grande mas saturado
  - Se Jana IA + API CRLV nativa entregam diferencial percebido → oimpresso pode subir pra R$ 449 e justificar
  - Se CAC oficina (com 12 concorrentes verticais consolidados) for >R$ 1.500 → payback >4 meses, drena caixa
- **Veredicto pra meta R$ 5M ARR (ADR 0022):** oficina sozinha não chega. **Combinado** gráfica + oficina chega mais rápido → entrar como diversificação, não substituição

---

## Riscos identificados

1. **12 concorrentes verticais consolidados** (vs 6 em gráfica). Cada um com 5-15 anos de mercado, base de clientes pesada (Oficina Integrada se diz "milhares de oficinas"). Saturação 4x maior que gráfica
2. **Pricing entry tier abaixo de R$ 100** (Onmotor V2, NeXT Professional, MinhaOficina Bronze, IS2 Automotive Lifetime). oimpresso não pode descer aí sem destruir LTV gráfica → segmento "MEI/oficina-fundo-de-quintal" é proibitivo
3. **Setup zero é norma** (10/12 ERPs). Cobrar setup R$ 999–1.500 = atrito. Justificável só com migração documentada
4. **Tempario TMO** (R$ 79/m) é integração concorrente — clientes oficina já pagam orçamentista TMO separado. oimpresso teria que **incluir TMO nativo** ou parceria explícita pra não duplicar custo
5. **API DETRAN não é tier-1** em nenhum vertical pesquisado — provavelmente porque é cara (R$ 0,15/consulta × volume baixo de oficina = R$ 60–180/m custo). oimpresso embutir consultas grátis = sangrar margem. Modelo correto: cobrar add-on R$ 49/m por 500 consultas
6. **Mecânico-no-chão precisa app mobile** — só 4/12 ERPs oferecem (Oficina Integrada, Manager Full web, MecânicaFlow web, Ultracar pós-vendas). É **gap real do mercado** — oimpresso entrar com app full nativo seria diferencial significativo. Mas custo de desenvolver = +R$ 150k inicial
7. **WSoft R$ 79,90 "tudo em um plano sem módulos"** — modelo simples agressivo. Se virar tendência, todos os tiers acima de R$ 200 ficam vulneráveis

---

## Recomendação final

**Entrar no nicho oficina auto SÓ se:**
1. ✅ Validar **3 oficinas piloto** com pricing Auto Pro R$ 349/m antes de escalar (similar Modules/Repair S2 pra ROTA LIVRE)
2. ✅ Ter **app mobile mecânico nativo** pronto antes do GTM (gap claro do mercado)
3. ✅ **Não cobrar setup default** — adicionar só "migração documentada" R$ 999 opcional
4. ✅ Oferecer **API DETRAN/CRLV como add-on** (R$ 49/m / 500 consultas) — não embutir
5. ✅ Aceitar que **ARR oficina <50% ARR gráfica por cliente** — universo compensa mas só com escala

**Não entrar se:**
- ❌ ARR meta R$ 5M precisa ser atingida com 1 vertical (gráfica precisa virar oimpresso prioritária)
- ❌ Equipe atual não suporta 2 verticais paralelos (S5 já tem ADS + Repair em backlog)
- ❌ Diferencial vs Oficina Integrada/Onmotor não está claro em 1 frase ("Jana IA + CRLV nativo" precisa virar prova)

---

## Fontes consultadas (2026-05-09)

- Oficina Integrada planos — https://www.oficinaintegrada.com.br/software-gerencimento-oficina-mecanica/programa-gestao-oficina-mecanica-integrada/planos.asp
- Ultracar preços — https://ultracar.com.br/precos/
- Oficina Inteligente planos — https://oficinainteligente.com.br/planos
- MinhaOficina preços — https://minhaoficina.net/precos/
- Onmotor planos — https://onmotor.com.br/planos/
- MecânicaFlow — https://mecanicaflow.sistemasaas.com.br/
- Manager Full — https://managerfull.com/
- NeXT Software (oficina) — https://www.nextsoftware.com.br/segmentos/sistema-para-oficina-mecanica.aspx
- IS2 Automotive — https://www.is2.inf.br/is2automotive/software-oficina-mecanica.html
- GestãoClick (oficina) — https://gestaoclick.com.br/programa-para-oficina-mecanica-e-auto-pecas/
- vhsys (oficina) — https://www.vhsys.com.br/segmentos/sistema-para-oficina-mecanica/
- APTO Sistemas — https://aptosis.com.br/sistema-para-oficina-mecanica
- WSoft (comparativo + preço) — https://wsoft.dev.br/melhor-sistema-para-oficina-mecanica
- Tempario TMO — https://www.tempario.com.br/
- Syscar — https://www.syscar.com.br/
- Top 10 elevadoresrw — https://elevadoresrw.com.br/blog/sistema-para-oficina-mecanica/
- ComparaSoftware oficina — https://www.compararsoftware.com.br/oficina-mecanica
- API DETRAN/Placas — https://infosimples.com/consultas/detran-restricoes/ + https://apiplacas.com.br/
- Capterra Auto Repair — https://www.capterra.com/auto-repair-software/

Coleta: Wagner [W] + Claude. Próxima validação sugerida: piloto 3 oficinas com Auto Pro R$ 349 antes de escalar GTM nacional. Decisão go/no-go nicho oficina deve ser ADR formal com base nesse documento + comparativo `01-pricing-erps-graficas-auto-vertical.md` (a fazer).
