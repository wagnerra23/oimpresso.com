---
name: Domínios canônicos verticais oimpresso (não confundir vocabulário)
description: Mapa rápido das verticais oimpresso com unidades primárias + clientes piloto + o que NÃO confundir entre elas. Wagner pediu 2026-05-13 após quase confundir m³ (caçamba) com ComVis (gráfica usa m²)
type: reference
---
> Wagner pediu memory firme depois que eu quase confundi `m³` (caçamba Martinho — volume 3D) com Comunicação Visual (gráfica usa **m²** — área 2D). Confundir vocabulário entre verticais destrói credibilidade na pré-venda.

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

### Modules/OficinaAuto (3 sub-verticais)

#### Sub-vertical 1 — Mecânica geral · CNAE 4520-0/01 · aguardando sinal

- **Unidades:** peça, par, kg (graxa), L (óleo motor/freio)
- **NÃO usa m³**

#### Sub-vertical 2 — Recapagem pneus · CNAE 2212-9/00 · qualificado por Vargas

- **Cliente piloto:** Vargas (Cliente_874398, OfficeImpresso, recapagem caçamba caminhão)
- **Unidades:** **pneu** (peça), kg (borracha), L (cola), placa multi-eixo (cavalo+reboque)
- **Schema EQUIPAMENTO_VEICULO:** PLACA 80% + PLACA2 20% + CHASSI2 8% (multi-placa real)
- **NÃO usa m³** (pneu é peça contável, não volume)

#### Sub-vertical 3 — Locação caçamba · CNAE 4581-4/00 · qualificado por Martinho

- **Cliente piloto:** **Martinho** (Cliente_731814, OfficeImpresso, caçamba avulsa pra entulho/obra) — 91 caçambas + 44.709 vendas Firebird
- **Unidades primárias:**
  - **m³ (capacidade caçamba)** — 3m³ pequena reforma · 5m³ reforma grande · 7m³ obra média
  - **diária** (locação)
  - peça (lona proteção, parafuso, ferragem auxiliar)
- **Pricing:** per diária (locação)
- **Schema:** PLACA 95.6% (sem cavalo+reboque — caso simples vs Vargas)
- **usa m³ — único caso onde m³ é correto no oimpresso**

### Modules/Repair · shared infrastructure · LIVE

- **NÃO é vertical** — é infra Kanban OS reusável
- **Unidades:** device (celular/eletrônico), peça (eletrônico)
- **Charter ADR 0080 · trust L3**

### Modules/Autopecas · CNAE 4530 · feature-wish

- **Sinal:** Vargas parcial (peças avulsas além de pneu)
- **Unidades:** peça (par freio, filtro, vela)
- **NÃO usa m³**

### Modules/Inventory · cross-vertical · proposed

- **Não é vertical** — fundação cross-vertical pra Kits/BOM + Batch + Dimensional + Movements unified
- **Unidades:** todas — ofertece capacidades genéricas pra outras verticais consumirem

## 3 erros recorrentes a NÃO repetir

1. **"ROTA LIVRE = gráfica em SP"** — é LOJA DE ROUPA em Termas do Gravatal/SC (vestuário). Razão social `LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME`. Ver cliente-rotalivre.md.

2. **"m³ pra ComunicacaoVisual"** — gráfica usa **m² (área)**, NÃO m³ (volume). Banner é 2D não 3D. Caçamba sim usa m³. Wagner pediu memory 2026-05-13 após eu quase confundir.

3. **"Martinho aluga só caçamba"** — Martinho TAMBÉM vende peças/material + emite boletos + gerencia estoque. Demo Martinho precisa mostrar /products + /pos/create + /sells + /recurring-billing + /financeiro além de /oficina-auto. Charter + demo-script atualizados PR #734.

## Onde aplicar

- **Pré-venda:** ler ESTE doc antes de propor solução pra cliente novo — escolher vertical + sub-vertical + unidades correspondentes
- **Charter/SPEC novo:** referenciar este doc pra unidades — não inventar (ex: nunca dizer "caçamba 5 m²" — é m³)
- **Mockup/demo:** dados dummy precisam respeitar unidades verticais (Martinho dummy = m³ + diária; ComVis dummy = m² + tinta kg; Vestuario dummy = peça + tamanho/cor)
- **Importer Firebird:** mapeamento legacy → oimpresso preserva unidade original do cliente
