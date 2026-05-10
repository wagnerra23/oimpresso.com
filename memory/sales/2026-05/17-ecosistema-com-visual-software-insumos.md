# Ecossistema com.visual — softwares verticais + insumos — 2026-05-09

> **Autor:** BD + integration architect (análise estratégica) · **Status:** Draft pra Wagner aprovar
> **Tese central:** Dono de gráfica BR opera diariamente um stack composto de (1) software de design (CorelDraw + Illustrator), (2) RIP large format (ONYX/Caldera/VersaWorks), (3) cutting plotter software (SAi Flexi), (4) fornecedores de insumos (3M/Avery/Heytex). Hoje **nenhum ERP brasileiro do setor (Mubisys, Zênite, Calcgraf) integra com nenhum desses sistemas** — janela aberta de 18-36m pro oimpresso se posicionar como **hub central** que conversa com toda a stack. Concorrentes legacy (Mubisys 30+ anos) provavelmente NUNCA vão integrar — são desktop Windows pesado, sem API moderna.
> **Restrição realista:** gráfica não troca CorelDraw fácil; integração export-only (oimpresso lê arquivos `.cdr`/`.ai`/`.pdf` exportados pelo designer e anexa à OS) já é "bom o bastante" pra MVP — não precisa virar plugin.

## Sumário executivo

- **20 softwares + 18 fornecedores mapeados** em 4 categorias (design, RIP, cutting, insumos)
- **Top 5 partnerships viáveis em 12m:** CorelDraw (programa Tech Partner), ONYX RIP (ISV), 3M/Avery (portal B2B vinis), SAi Flexi (cutting universal), Heytex/Endutex (lonas BR)
- **Investimento ano 1 estimado:** R$ 25-40k caixa (taxa 2 programas ISV + 1 viagem ExpoPrint + dev integração export-only) + 120-180h Wagner+dev
- **KPI realista 12m:** 2 partnerships oficiais (1 software design + 1 RIP) + 1 portal B2B fornecedor de vinil; 8-12 leads via canal; 2-3 clientes fechados via referência
- **Risco-mor:** softwares legacy (CorelDraw, Mubisys-style) sem API moderna — integração só via filesystem (watcher de pasta), portanto integração rasa; mitigação = posicionar oimpresso como "ERP que aceita o stack que você já tem", não exigir migração

---

## A. Software de design vertical

### Tabela comparativa

| # | Software | % gráficas BR usam | Tipo arquivo | API/Plugin | Programa parceiro | Integração viável |
|---|---|---|---|---|---|---|
| 1 | **CorelDraw Graphics Suite** | [validar — ~80%] | `.cdr` | Plugin VBA + GMS macros + COM API | **Corel Tech Partner** existe (não público — via contato) | Plugin "enviar OS pro oimpresso" + export `.pdf` |
| 2 | **Adobe Illustrator + Photoshop** | [validar — ~50% agências, ~30% gráficas] | `.ai` `.psd` | UXP plugins (JS moderno) + ExtendScript legacy | **Adobe Tech Partner** (formal, taxa anual) | Plugin moderno via UXP |
| 3 | **AutoCAD** | [validar — ~30% sinalização/fachada] | `.dwg` `.dxf` | LISP + .NET API + ObjectARX | Autodesk ADN (Developer Network) | Importar `.dwg` no orçamento (cálculo de m²) |
| 4 | **Affinity Designer** | [validar — ~10% crescente] | `.afdesign` | Sem API pública (Serif fechado) | Não tem programa ISV | Export-only via `.pdf` |
| 5 | **Inkscape** | [validar — ~15% gráfica pequena] | `.svg` | CLI + extensões Python | Open source — sem programa | Watcher de pasta + import `.svg` |
| 6 | **Esko ArtPro / DeskPack** | [validar — ~5% embalagem nicho] | `.ai` (com Esko XMP) | Esko Automation Engine API | Esko Solution Partner | Nicho — postergar |
| 7 | **Scribus** | [validar — <5%] | `.sla` | Python scripting | Open source | Postergar |

### Análise

- **CorelDraw é o rei absoluto da gráfica BR.** Toda gráfica que arte interna usa Corel — herança histórica desde anos 90. Trocar Corel por Illustrator quebra 30 anos de biblioteca de templates. Dono de gráfica NÃO troca por nada.
- **Adobe é mais comum em agências** que mandam arte fechada pra gráfica imprimir. Gráfica pequena sem agência interna raramente paga assinatura Adobe.
- **AutoCAD entra no nicho fachada/sinalização** — quem faz placa metálica, totem, ACM exige planta arquitetônica `.dwg`. Importar `.dwg` no orçamento pra calcular m² seria diferencial enorme (nenhum concorrente faz).
- **Affinity tem zero programa ISV** — Serif (criadora) é empresa fechada, sem API. Postergar.
- **Inkscape**: open source, sem programa formal, mas integração via filesystem watcher é trivial.

### Realidade da integração com CorelDraw

- **Plugin nativo (GMS macro):** dev em VBA dentro do Corel; chama HTTP API do oimpresso pra criar OS. Esforço: ~3-4 sprints. Bloqueador: GMS macro é Windows-only, não roda em Mac (raro em gráfica BR — Mac é coisa de agência).
- **Watcher de pasta:** designer salva `.cdr` em `\\servidor\OS-pendentes\`, oimpresso detecta arquivo novo e pergunta "vincular a qual OS?". Esforço: ~1 sprint. Funciona em qualquer software (Corel, AI, Inkscape, AutoCAD). **Recomendado pra MVP.**
- **Export-only via PDF:** designer faz "Arquivo → Publicar pro oimpresso" (gera PDF + sobe via API). Esforço: ~1 sprint dentro Corel + endpoint oimpresso.

---

## B. RIP software

### Tabela comparativa

| # | RIP | Market share BR estimado | Faixa máquina | API / Hot folder | Programa ISV | Integração viável |
|---|---|---|---|---|---|---|
| 1 | **ONYX RIP** | [validar — ~40% large format] | Premium grande porte | Hot folder + API HTTP (Thrive) | **ONYX Connect** (parceiros) | Envio OS → fila RIP + status produção |
| 2 | **Caldera** | [validar — ~20% premium] | Premium grande porte | Hot folder + API REST (PrimeCenter) | **Caldera Partner Program** | Similar ONYX |
| 3 | **Wasatch SoftRIP** | [validar — ~15% médio porte] | Médio | Hot folder | Sem ISV formal | Hot folder watcher |
| 4 | **EFI Fiery** | [validar — ~10% small format/digital] | Enterprise + small format | Fiery API (FieryXF) | **EFI Developer Program** | API existe, complexa |
| 5 | **ColorGate** | [validar — ~5% premium nicho] | Premium nicho industrial | Hot folder + API | ColorGate Partner | Nicho |
| 6 | **PhotoPRINT (SAi)** | [validar — ~30% gráfica pequena] | Entry-level | Hot folder | SAi Partner Program | Hot folder watcher |
| 7 | **Roland VersaWorks** | [validar — vem com toda Roland] | Bundle Roland | Hot folder limitado | Via partnership Roland (R27) | Casado com R27 Roland DG |
| 8 | **Mimaki RasterLink** | [validar — vem com toda Mimaki] | Bundle Mimaki | Hot folder limitado | Via partnership Mimaki (R27) | Casado com R27 Mimaki |

### Análise

- **ONYX é o líder de fato em large format premium.** Toda gráfica acima de R$ 200k/m fatura tem ONYX rodando. Integração "envia OS → entra na fila do RIP" + "RIP avisa oimpresso quando arrancou impressão" é diferencial brutal — operador não precisa duplicar dado.
- **Caldera é o segundo lugar premium**, com base instalada menor mas tickets maiores (gráficas de R$ 500k+/m).
- **PhotoPRINT (SAi) cobre entrada** — gráfica pequena recém-aberta começa com SAi. Mesmo grupo do Flexi (cutting) → partnership única SAi cobre 2 categorias.
- **VersaWorks e RasterLink vêm casados com a máquina** — não compete com ONYX/Caldera; gráfica grande migra de VersaWorks pra ONYX quando cresce. Cobertura via R27 (partnerships fabricantes Roland/Mimaki).

### Realidade da integração com RIP

- **Hot folder pattern:** oimpresso gera PDF + arquivo XML (job ticket) e drop em pasta vigiada pelo RIP. RIP processa, gera arquivo de status (`.log` ou similar) que oimpresso lê. Funciona em ONYX, Caldera, Wasatch, PhotoPRINT, VersaWorks, RasterLink — universal.
- **API HTTP (ONYX Thrive, Caldera PrimeCenter):** moderna, REST, status em tempo real. Esforço: 2-3 sprints por RIP.
- **Estratégia:** começar com hot folder (cobre 100% dos RIPs) + evoluir pra API só nos top 2 (ONYX + Caldera).

---

## C. Cutting plotter / acabamento software

| # | Software | Market share BR estimado | Hardware típico | Integração viável |
|---|---|---|---|---|
| 1 | **SAi Flexi** | [validar — ~50% cutting plotter universal] | Roland GR/CAMM, Graphtec, Summa | Hot folder + SAi Partner Program (mesmo SAi do PhotoPRINT) |
| 2 | **CADlink SignLab** | [validar — ~15%] | Graphtec, Mutoh, Summa | Hot folder |
| 3 | **EasyCut** | [validar — ~20% chinês entrada] | Plotters chineses | Hot folder limitado, sem programa |
| 4 | **Esko Studio** | [validar — embalagem nicho] | Kongsberg | Esko Solution Partner — nicho |
| 5 | **Plot Manager** | [validar — ~10% gerência fila] | Multi-vendor | Sem API — postergar |

### Análise

- **SAi Flexi é universal** em cutting plotter — independente de marca de máquina. Integração via hot folder cobre maioria.
- **Mesma SAi de PhotoPRINT (RIP entrada)** — partnership única SAi resolve **categoria B + C** simultaneamente. Maior alavanca de ROI do mapa.
- **EasyCut chinês**: cresce em gráfica entrada, sem API formal — postergar até cliente reportar.

---

## D. Fornecedores de insumos (BR ICP)

### D.1 Lonas frontlight/backlight

| # | Fornecedor | Origem | Base BR | Programa ERP / API | Modelo viável |
|---|---|---|---|---|---|
| 1 | **Heytex** | DE → BR via distribuidor | [validar — top 3 BR premium] | Sem programa ERP declarado | Portal B2B oimpresso + cadastro produtos pré-feito |
| 2 | **Endutex** | PT → BR | [validar — top 3 BR] | Sem programa | Similar Heytex |
| 3 | **Multi** | BR fabricante | [validar — popular médio] | Sem programa | Cadastro produto |
| 4 | **Suntex** | CN → BR | [validar — entrada] | Sem programa | Cadastro produto |
| 5 | **Saemar** | BR | [validar — regional SP] | Sem programa | Cadastro produto |
| 6 | **Junta MMA** | BR | [validar — pequeno] | Sem programa | Postergar |

### D.2 Vinis adesivos

| # | Fornecedor | Origem | Base BR | Programa B2B / API | Modelo viável |
|---|---|---|---|---|---|
| 1 | **3M Commercial Graphics** | US → BR | [validar — top 1 premium] | **3M Commercial Solutions Portal** (B2B, login revendedor) | API existe via portal — pedido programático |
| 2 | **Avery Dennison** | US → BR | [validar — top 2 premium] | **Avery Dennison MyAvery portal** (B2B) | API existe — pedido programático |
| 3 | **Mactac** | US → BR | [validar — médio premium] | Sem portal público | Cadastro produto |
| 4 | **ORAFOL (Oracal)** | DE → BR | [validar — top 3 premium] | Sem portal BR | Cadastro produto |
| 5 | **IMPRIDIA** | BR | [validar — médio nacional] | Sem programa | Cadastro produto |
| 6 | **KPMF** | UK → BR nicho | [validar — wrap automotivo] | Sem programa | Postergar |

### D.3 Tintas large format

| # | Fornecedor | Tipo | Base BR | Modelo viável |
|---|---|---|---|---|
| 1 | **HP / Epson originais** | OEM | [validar — top via R27 fabricantes] | Casado R27 (já mapeado) |
| 2 | **INKMAX** | Compatível BR | [validar — popular custo-benefício] | Cadastro produto |
| 3 | **OEM diversos** | Genérico | [validar — long tail] | Cadastro produto |

### D.4 Outros materiais (perfis, LED, fixadores, especiais)

| # | Categoria | Fornecedores top 3 | Modelo viável |
|---|---|---|---|
| 1 | **Perfis alumínio** | Alcoa, Hydro, Aluvidro | Cadastro produto |
| 2 | **PVC/ACM** | Alucobond, Alubond, Multilam | Cadastro produto |
| 3 | **LED iluminação** | G-Light, Brilia, Stella | Cadastro produto |
| 4 | **Fixadores/colas** | Tigre, Tekbond, Henkel | Cadastro produto |
| 5 | **Materiais especiais** | (lonas blockout, vinil micropérfurado, telas tipográficas) | Long tail — cadastro |

### Análise insumos

- **3M e Avery são os únicos com portal B2B com API** declarada. Restante = cadastro produto + posicionamento "fornecedor parceiro recomendado oimpresso" (sem integração técnica).
- **Lonas BR (Heytex, Endutex, Multi)** = oportunidade pra **portal B2B oimpresso**: gráfica clica "comprar lona Heytex 440g" e oimpresso encaminha pedido pro distribuidor → Heytex paga oimpresso comissão (modelo similar marketplace).
- **Tintas casadas com R27** — já mapeado em fornecedores de máquinas.
- **Perfis/LED/fixadores** = long tail; postergar até cliente reportar (ADR 0105 — sem sinal não vira US).

---

## Top 5 partnerships viáveis 12 meses (priorizadas)

| # | Partnership | Cobertura ICP | Modelo | Esforço dev | Investimento | ROI 12m |
|---|---|---|---|---|---|---|
| 1 | **CorelDraw — Tech Partner + plugin GMS** | ~80% gráficas BR | Plugin "enviar OS pro oimpresso" + export PDF | 4-6 sprints | R$ 8-12k | 5-10 leads via marketing co-branded |
| 2 | **ONYX RIP — ISV Connect program** | ~40% large format BR | Hot folder + API status produção | 3-4 sprints | R$ 5-8k (taxa) | 3-5 leads gráfica média/grande |
| 3 | **3M Commercial Graphics + Avery Dennison** | Vinil premium | Portal B2B (pedido via API) | 4-6 sprints | R$ 5-10k | Co-marketing case + comissão pedido |
| 4 | **SAi (Flexi + PhotoPRINT)** | Cutting universal + entry RIP | Hot folder | 2-3 sprints | R$ 3-5k | Cobre 2 categorias com 1 partnership |
| 5 | **Heytex / Endutex (lonas BR)** | Lonas premium BR | Portal B2B + cadastro produto + comissão | 3-4 sprints | R$ 3-5k | Marketplace BR pioneer |

---

## Detalhe top 5 (caminho pra entrar)

### 1. CorelDraw — Corel Tech Partner

- **Site / contato:** corel.com → "Partners" → Technology Partners (programa não público; entrar via [partner@corel.com](mailto:partner@corel.com) ou LinkedIn gerente Brasil)
- **Programa:** Corel Technology Partner (formal, sem taxa anual conhecida — programa sob demanda)
- **Modelo viável:** plugin GMS (VBA macro) "enviar OS pro oimpresso"; selo "compatible with CorelDraw 2024+"
- **Esforço dev:** 4-6 sprints (plugin VBA Windows-only + endpoint API oimpresso + watcher fallback Mac)
- **Investimento:** R$ 8-12k (dev + 1 demo no Corel UserMeet BR)
- **ROI:** 5-10 leads/ano via marketing Corel BR + selo de compatibilidade no portal Corel
- **KPI:** 1 case study com gráfica usuária Corel + 50 downloads do plugin em 12m
- **Risco:** Corel não responder (programa pouco ativo BR) — mitigação: começar pelo watcher de pasta (universal, sem precisar Corel aprovar)

### 2. ONYX RIP — ISV Connect

- **Site / contato:** onyxgfx.com → "Partners" → "Develop with ONYX" (programa Connect existe formal)
- **Programa:** ONYX Connect ISV (taxa anual estimada US$ 500-2k; SDK + sandbox)
- **Modelo viável:** "envia OS oimpresso → entra fila ONYX" + "ONYX avisa oimpresso quando job arranca/termina" via API Thrive ou hot folder
- **Esforço dev:** 3-4 sprints
- **Investimento:** R$ 5-8k (taxa programa + dev + 1 demo ExpoPrint)
- **ROI:** 3-5 leads/ano gráfica média/grande (R$ 200k+/m fatura — ticket alto pro oimpresso)
- **KPI:** 1 partnership oficial assinada + 1 case study gráfica com ONYX
- **Risco:** ONYX programa US-centric, suporte BR fraco — mitigação: contato direto com integradores BR (não direto ONYX matriz)

### 3. 3M / Avery Dennison — portais B2B vinil

- **Site / contato:** 3m.com.br/3M/pt_BR/graphics-signage-br/ + averydennison.com/pt-br
- **Programa:** B2B portal com login revendedor (não programa ISV declarado, mas API de pedido existe pra distribuidores)
- **Modelo viável:** oimpresso vira "revendedor digital" (CNPJ Wagner) + grafica clica "comprar vinil 3M IJ180" no oimpresso → pedido entra direto na 3M; comissão de revenda fica com oimpresso
- **Esforço dev:** 4-6 sprints (integração API + cadastro inicial CNPJ revendedor)
- **Investimento:** R$ 5-10k (dev + abertura conta revendedor + capital giro pequeno pra estoque mínimo se exigido)
- **ROI:** comissão 5-15% sobre pedido + take rate oimpresso
- **KPI:** R$ 50-100k/ano em pedidos via plataforma → R$ 5-15k receita oimpresso
- **Risco:** 3M/Avery exigir CNPJ atacadista com mínimo de pedido alto — mitigação: começar como **referência** (oimpresso recomenda, gráfica compra direto da 3M com cupom oimpresso) sem virar revendedor formal

### 4. SAi (Flexi + PhotoPRINT) — Partner Program

- **Site / contato:** thinksai.com → "Partners" → "Solution Partners"
- **Programa:** SAi Solution Partner (programa formal, taxa modesta)
- **Modelo viável:** hot folder bidirecional (oimpresso → SAi job; SAi → oimpresso status); selo SAi Compatible
- **Esforço dev:** 2-3 sprints (hot folder genérico cobre 2 produtos SAi)
- **Investimento:** R$ 3-5k (taxa + dev)
- **ROI:** cobre **categorias B (RIP entry) + C (cutting)** com 1 partnership — maior alavanca custo/benefício
- **KPI:** 1 partnership assinada + 30% das gráficas pequenas (que usam SAi) acessíveis via canal
- **Risco:** SAi BR canal fraco — mitigação: partnership assinada matriz US, usar selo em marketing BR

### 5. Heytex / Endutex — portal B2B lonas BR

- **Site / contato:** heytex.com (DE matriz) → distribuidor BR + endutex.com (PT matriz)
- **Programa:** não há programa ERP declarado; oportunidade greenfield
- **Modelo viável:** oimpresso convida fornecedor pra publicar catálogo + preço wholesale no portal → gráfica compra via oimpresso → fornecedor entrega → oimpresso ganha comissão (similar marketplace)
- **Esforço dev:** 3-4 sprints (módulo `Modules/Marketplace` greenfield + cadastro fornecedor + integração pedido por email/EDI)
- **Investimento:** R$ 3-5k (dev + reunião comercial fornecedor)
- **ROI:** marketplace BR pioneer — sem concorrente Mubisys/Zênite/Calcgraf nesse espaço
- **KPI:** 3 fornecedores cadastrados em 12m (Heytex + Endutex + Multi); R$ 30-80k/ano GMV → R$ 3-8k receita comissão
- **Risco:** fornecedor BR conservador, exige reunião presencial + relacionamento — mitigação: começar com Heytex BR (quem distribui) ao invés de matriz DE

---

## Backlog ADR-feature-wish (sem sinal — ADR 0105)

> Itens abaixo ficam dormentes até cliente pedir explicitamente OU métrica detectar drift.

- **Adobe Tech Partner** — agência → gráfica não é cliente direto oimpresso; postergar até gráfica pedir export AI nativo
- **AutoCAD ADN program** — postergar até cliente fachada/sinalização pedir importar `.dwg` pra orçamento
- **Caldera Partner Program** — postergar; ONYX cobre 80% do que precisamos em RIP premium
- **EFI Developer Program** — postergar; nicho enterprise
- **ColorGate / Esko** — nicho industrial/embalagem, fora ICP atual
- **CADlink SignLab** — postergar; SAi Flexi domina universo cutting
- **EasyCut chinês** — long tail sem API
- **Affinity Designer / Inkscape / Scribus** — sem programa formal; só watcher genérico
- **Mactac, ORAFOL, IMPRIDIA, KPMF** (vinis tier 2) — cadastro produto only
- **Perfis alumínio + PVC/ACM + LED + fixadores + especiais** — cadastro produto + cliente reporta primeiro

---

## Conclusão

- **Wedge prioritário "stack ideal de gráfica BR":** **CorelDraw** (universal design) + **ONYX RIP** (premium large format) ou **SAi PhotoPRINT** (entry RIP) + **SAi Flexi** (cutting universal) + **3M/Avery** (vinil premium) + **Heytex/Endutex** (lonas BR). Combo cobre **toda a operação diária** da gráfica BR — oimpresso vira **hub central** que conversa com tudo.
- **Posicionamento de marketing:** "ERP que aceita o stack que você já tem — não pede pra você trocar nada". Direto contra concorrentes legacy (Mubisys/Zênite/Calcgraf) que ignoram esse ecossistema completamente.
- **Risco principal:** software gráfico legacy (CorelDraw em parte, Mubisys-style ERPs) sem API moderna — integração só via filesystem (watcher de pasta) ou plugins Windows-only. Mitigação: começar pelo padrão universal **hot folder / pasta vigiada** que funciona em 100% dos casos, evoluir pra API nativa só onde ROI claro (CorelDraw + ONYX + 3M/Avery).
- **Sequência sugerida 12m:**
  1. **Q3/2026:** watcher de pasta universal (cobre todos casos) + plugin VBA Corel beta + hot folder ONYX
  2. **Q4/2026:** assinar Corel Tech Partner + ONYX Connect + SAi Solution Partner
  3. **Q1/2027:** abrir portal B2B 3M/Avery + Heytex/Endutex (módulo Marketplace greenfield)

> **Próximo passo recomendado:** Wagner aprovar top 5 priorizado → BD agenda 1ª reunião com gerente Corel BR + ONYX BR + Heytex BR em paralelo (3 contatos, ~6 semanas).
