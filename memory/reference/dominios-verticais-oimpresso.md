---
name: Domínios canônicos verticais oimpresso (não confundir vocabulário)
description: Mapa rápido das verticais oimpresso com unidades primárias + clientes piloto + o que NÃO confundir entre elas. Wagner pediu 2026-05-13 após quase confundir m³ (caçamba) com ComVis (gráfica usa m²). Atualizado 2026-05-26 com correção domínio Martinho via ADR 0194.
type: reference
---
> Wagner pediu memory firme depois que eu quase confundi `m³` (caçamba container — volume 3D) com Comunicação Visual (gráfica usa **m²** — área 2D). Confundir vocabulário entre verticais destrói credibilidade na pré-venda.
>
> **Atualização 2026-05-26:** Wagner identificou que Martinho NÃO é locação caçamba container — é **mecânica pesada/autorizada caminhão basculante** (sub-vertical 4 nova). Sub-vertical 3 "Locação caçamba" perdeu seu piloto (vira hipótese sem cliente real). Correção formal em [ADR 0194](../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md).

## Mapa canônico

### Modules/Vestuario · CNAE 4781-4/00 · em prod

- **Cliente piloto:** ROTA LIVRE biz=4 (Larissa, LOJA DE ROUPA em Termas do Gravatal/SC — NÃO é gráfica em SP)
- **Unidades primárias:** **peça**, par (sapato), variação cor + tamanho (grade)
- **Pricing:** per peça
- **NÃO confundir:** Larissa NÃO é gráfica; ROTA LIVRE NÃO usa m³ nem m²

### Modules/ComunicacaoVisual · CNAE 1813-0/01 · em construção

- **Clientes candidatos OficeImpresso saudáveis:** Vargas (recapagem caçamba caminhão), Extreme, Gold, Zoom, Fixar, Mhundo, Produart
- **Unidades primárias:**
  - **m² (área impressa)** — banner, faixa, lona impressão digital
  - m linear — faixa adesiva, vinil em rolo
  - kg / L — tinta (cartucho ou líquida)
  - kg — bobina papel (medida por gramatura: 280g/m² etc)
  - peça — banner pronto, placa MDF
- **Pricing:** per m² OU per peça
- **NÃO confundir:**
  - **NÃO usa m³** (área 2D, não volume 3D)
  - **NÃO é mecânica** apesar de ter "Vargas" como piloto compartilhado com OficinaAuto recapagem

### Modules/OficinaAuto (4 sub-verticais)

#### Sub-vertical 1 — Mecânica geral · CNAE 4520-0/01 · aguardando sinal

- **Unidades:** peça, par, kg (graxa), L (óleo motor/freio)
- **NÃO usa m³**

#### Sub-vertical 2 — Recapagem pneus · CNAE 2212-9/00 · qualificado por Vargas

- **Cliente piloto:** Vargas (Cliente_874398, OfficeImpresso, recapagem caçamba caminhão)
- **Unidades:** **pneu** (peça), kg (borracha), L (cola), placa multi-eixo (cavalo+reboque)
- **Schema EQUIPAMENTO_VEICULO:** PLACA 80% + PLACA2 20% + CHASSI2 8% (multi-placa real)
- **NÃO usa m³** (pneu é peça contável, não volume)

#### Sub-vertical 3 — Locação caçamba container · CNAE 4581-4/00 · hipótese sem cliente real (corrigido 2026-05-26)

> **Histórico do erro:** entre 2026-05-11 e 2026-05-25, Martinho foi descrito como piloto deste sub-vertical (caçamba estacionária m³ entulho/obra). Em 2026-05-26 Wagner corrigiu — Martinho é mecânica pesada (sub-vertical 4). Este sub-vertical 3 perdeu cliente real ancorado — fica reservado caso futuro cliente desse perfil real apareça.

- **Cliente piloto:** _hipótese sem cliente pagante_
- **Unidades primárias hipotéticas:**
  - **m³ (capacidade caçamba container)** — 3m³ pequena reforma · 5m³ reforma grande · 7m³ obra média
  - **diária** (locação)
  - peça (lona proteção, parafuso, ferragem auxiliar)
- **Pricing hipotético:** per diária
- **Schema:** caçamba container estacionária **não tem placa ANTT** (diferencial dura vs sub-vertical 4 basculante que tem PLACA)
- **Decisão:** schema `service_orders.daily_rate` + `expected_return_date` + `delivery_address` (migration 2026_05_12_220002) preservado nullable caso cliente real surgir — sem schema drop até [ADR 0194 review_trigger](../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) reavaliar M6+

#### Sub-vertical 4 — Mecânica pesada / autorizada caminhão basculante · CNAE 4520-0/01 · qualificado por Martinho (NOVO 2026-05-26)

- **Cliente piloto:** **Martinho Caçambas LTDA** (Cliente_731814, biz=164 prod, Capivari de Baixo/SC) — 91 placas de caminhões de CLIENTES + 44.709 vendas Firebird + R$ [redacted Tier 0]M+/mês estimado
- **Razão social / fantasia:** MARTINHO CAÇAMBAS LTDA / Martinho Caçambas (do filho — pai é "Martinho da Caçamba" em Tubarão, transportadora resíduo, NÃO é cliente)
- **Unidades primárias:**
  - **peça hidráulica** — PTO (tomada de força), kit hidráulico, bomba, válvula, mangueira
  - **peça mecânica pesada** — eixo, suspensão pesada, motor diesel, embreagem, freio pneumático
  - L — óleo diesel/motor/hidráulico
  - kg — graxa
  - **hora-trabalho mecânico** — OS programada (não diária)
- **Pricing:** per peça (catálogo cross-ref Scania/Volvo/MB/Ford) + per hora-trabalho serviço OS
- **Schema EQUIPAMENTO_VEICULO:** PLACA 95.6% (caminhão de cliente identificado) · sem PLACA2/CHASSI2 (basculante simples, não bitrem)
- **NÃO usa m³** — caçamba aqui é **carroceria basculante do caminhão** (que tomba), não container estacionário de entulho
- **Cadeia comercial:** [Tork Tomadas de Força (fábrica PTO Capivari)](../research/clientes-prospect/tork-tomadas-forca/01-perfil.md) → Martinho (revenda peça + instala) → frota basculante terceiro
- **Concorrentes:** Auto Manager · sistemas oficina pesada · softwares concessionária Volvo/Scania/MB (NÃO Lokoz/locadoras caçamba)

### Modules/Repair · shared infrastructure · LIVE

- **NÃO é vertical** — é infra Kanban OS reusável
- **Unidades:** device (celular/eletrônico), peça (eletrônico)
- **Charter ADR 0080 · trust L3**

### Modules/Autopecas · CNAE 4530 · feature-wish

- **Sinal:** Vargas parcial (peças avulsas além de pneu) + Tork prospect indústria PTO
- **Unidades:** peça (par freio, filtro, vela, PTO, kit hidráulico)
- **NÃO usa m³**

### Modules/Inventory · cross-vertical · proposed

- **Não é vertical** — fundação cross-vertical pra Kits/BOM + Batch + Dimensional + Movements unified
- **Unidades:** todas — ofertece capacidades genéricas pra outras verticais consumirem

## 3 erros recorrentes a NÃO repetir

1. **"ROTA LIVRE = gráfica em SP"** — é LOJA DE ROUPA em Termas do Gravatal/SC (vestuário). Razão social `LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME`. Ver cliente-rotalivre.md.

2. **"m³ pra ComunicacaoVisual"** — gráfica usa **m² (área)**, NÃO m³ (volume). Banner é 2D não 3D. Caçamba container hipotética usaria m³ se existisse cliente real. Wagner pediu memory 2026-05-13 após eu quase confundir.

3. **"Martinho = locação caçamba estacionária m³/diária"** — **ERRO CORRIGIDO 2026-05-26.** Martinho NÃO aluga caçamba container — é **loja de peça hidráulica + oficina autorizada caminhão basculante** (Capivari de Baixo/SC, CNAE 4520, sub-vertical 4). Unidades corretas: peça hidráulica + hora-trabalho mecânico, NÃO m³+diária. Wagner: *"é mecânica de caminhão caçamba de grande porte, 1 milhão+ faturamento mês, quase concessionária. Entra o caminhão pra trocar e consertar, não vejo destruído lá"*. WebSearch confirmou negócio + cidade. Correção formal [ADR 0194](../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md).

## Onde aplicar

- **Pré-venda:** ler ESTE doc antes de propor solução pra cliente novo — escolher vertical + sub-vertical + unidades correspondentes
- **Charter/SPEC novo:** referenciar este doc pra unidades — não inventar (ex: nunca dizer "Martinho aluga caçamba 5m³ R$ [redacted Tier 0]/diária" — é peça hidráulica + serviço OS hora)
- **Mockup/demo:** dados dummy precisam respeitar unidades verticais (Martinho dummy = peça hidráulica PTO/kit + OS hora; ComVis dummy = m² + tinta kg; Vestuario dummy = peça + tamanho/cor)
- **Importer Firebird:** mapeamento legacy → oimpresso preserva unidade original do cliente
