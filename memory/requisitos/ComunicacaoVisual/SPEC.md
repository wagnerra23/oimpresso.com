---
module: ComunicacaoVisual
status: em_construcao (planejado)
piloto: 1 dos 6 saudáveis OfficeImpresso (a confirmar — Vargas/Extreme/Gold/Zoom/Fixar/Mhundo/Produart)
piloto_previsao: 2026-Q3
cnae_principal: "1813-0/01"
related_adrs: [0121, 0094, 0093, 0035, 0119, 0105, 0011, 0024]
last_review: 2026-05-10
owner: [W]
---

# Especificação funcional — Modules/ComunicacaoVisual

> Convenção do ID: `US-COMVIS-NNN` para user stories, `R-COMVIS-NNN` para regras Gherkin.
> Módulo NÃO existe em código ainda. Este SPEC é o contrato de construção, derivado de [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) (modular especializado por vertical).
> Antes de scaffoldear, ler [Modules/Jana](../../../Modules/Jana) + [Modules/Repair](../../../Modules/Repair) + [Modules/NfeBrasil](../NfeBrasil/SPEC.md) e imitar ([ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md)).

## 1. Visão

ERP vertical brasileiro pra gráfica rápida e comunicação visual (lona, fachada, plotter, ACM, brindes) que substitui Mubisys/Zênite/Calcgraf — entregando cálculo m² + PCP gráfico + apontamento de máquina + NFe-de-boleto-pago + IA conversacional, sem nenhum dos concorrentes ter os 5 juntos.

## 2. Audiência alvo

### Perfil-alvo: gráfica rápida BR de pequeno-médio porte

| Dimensão | Faixa |
|---|---|
| Funcionários | 3–25 |
| GMV anual | R$ [redacted Tier 0]k – R$ [redacted Tier 0]M |
| Tickets/mês | 80 – 1.500 |
| Máquinas | 1–6 (plotter Roland/Mimaki/Mutoh + impressora UV/HP Latex + acabamento) |
| Estado fiscal | Simples Nacional (maioria) ou Lucro Presumido |
| CNAE principal | **1813-0/01** (impressão de material para uso publicitário) — secundários 1812-1, 7319-0/03, 4761-0/03 |
| Sistema atual | OfficeImpresso Delphi legacy / Mubisys / Calcgraf / planilha+Bling |
| Cliente final | empresas locais (lojas, escritórios), prefeituras, órgãos públicos, condomínios |

### Mecânicas operacionais típicas

1. Cliente chega via WhatsApp/loja com pedido informal ("preciso de um banner 3x1,5m pra sábado")
2. Vendedor calcula em planilha: área × preço × acabamento + instalação + entrega
3. PDF do orçamento volta no WhatsApp; cliente aprova
4. Designer faz arquivo (Corel/Illustrator); valida com cliente (foto/print)
5. Plotter imprime; acabamento (corte, ilhós, costura, aplicação adesivo); embala
6. Cliente busca OU equipe instala (fachada, ACM)
7. Boleto/Pix recebido; NFC-e ou NFSe (instalação=serviço, mídia=produto)

### 6 saudáveis OfficeImpresso candidatos a migrar (piloto)

Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart — todos em produção Delphi legacy WR Sistemas com R$ [redacted Tier 0]k–R$ [redacted Tier 0]M GMV/ano. Migration Factory ([ADR 0119](../../decisions/0119-migration-factory.md)) move cada um.

## 3. Capacidades core (User Stories)

Priorização: **P0** = bloqueia 1ª piloto migrado · **P1** = competitivo vs Mubisys/Zênite · **P2** = diferencial de longo prazo · **P3** = backlog.

### US-COMVIS-001 · Cálculo automático por m² (lona, vinil, banner, fachada) — **P0**

> **Área:** Pricing
> **Rota:** `POST /comvis/orcamento/calcular`
> **Controller/ação:** `OrcamentoController@calcular`
> **Permissão Spatie:** `comvis.orcamento.create`

**Como** vendedor de gráfica
**Quero** informar largura × altura + material + acabamento + instalação e ver preço calculado
**Para** entregar orçamento ao cliente em <2min sem abrir Excel paralelo

**Definition of Done:**
- [ ] Form: largura (m) + altura (m) + qtd + material + acabamentos[] + instalação? + entrega?
- [ ] Cálculo: `area_m2 = largura × altura`; `subtotal = area_m2 × material.preco_m2 × qtd`; `extras = sum(acabamentos.preco)`; `total = subtotal + extras + instalacao + entrega`
- [ ] Mínimo cobrado configurável (`material.minimo_m2` — ex: 0,5m² mesmo se peça é menor)
- [ ] Preview PDF + envio WhatsApp 1-clique
- [ ] Test Pest: 6+ casos (banner 3x1,5 frontlight, lona 5x2 blackout, vinil adesivo recortado, ACM com instalação, brinde unitário, etc.)
- [ ] Multi-tenant scope `business_id` (skill `multi-tenant-patterns`)

**Concorrência:** Mubisys ✅, Zênite ✅, Calcgraf ✅ (2M orçam/mês), Calcme ✅, Alfa ✅. **oimpresso ❌ hoje** — gap #1 do comparativo Capterra ([oimpresso_vs_concorrentes_capterra_2026_04_25.md](../../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md)).

---

### US-COMVIS-002 · Cadastro de material com preço por gramatura — **P0**

> **Área:** Catalog
> **Rota:** `GET/POST /comvis/materiais`
> **Controller/ação:** `MaterialController`

**Como** dono/financeiro
**Quero** cadastrar material (lona front-light 440g, blackout 510g, vinil adesivo, calandrado, perfurado, ACM 3mm, telas) com preço/m² e markup
**Para** alimentar US-COMVIS-001 sem hard-code

**DoD:**
- [ ] CRUD material: nome, categoria (lona/vinil/ACM/tela/papel), gramatura (g/m²), preco_custo_m2, preco_venda_m2, minimo_m2, fornecedor padrão
- [ ] Bulk update via Jana ("aumenta 5% em todo lona 440g") — atende **dor #3 do top 10** do research
- [ ] Histórico de preço (audit) pra permitir relatório margem
- [ ] Import via XML NFe entrada (TransactionBuilder reuso)
- [ ] Multi-tenant `business_id`

---

### US-COMVIS-003 · PCP gráfico — fluxo OS multi-etapa com responsável + prazo + custo — **P0**

> **Área:** Producao
> **Rota:** `GET /comvis/os/{id}` + Kanban
> **Controller/ação:** `OsController`
> **Reusa:** [Modules/Repair](../../../Modules/Repair) Kanban drag-drop (PR #363) — Modules/Repair é shared infrastructure ([ADR 0121 §P8](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md))

**Como** PCP/produção
**Quero** ver Kanban com colunas Design → Prepress → Impressão → Acabamento → Instalação → Entregue
**Para** saber onde cada OS está + prazo + responsável

**DoD:**
- [ ] Etapas configuráveis por business (cada gráfica tem fluxo levemente diferente)
- [ ] Cada etapa: responsável, prazo limite, custo previsto vs realizado
- [ ] Drag-drop card entre colunas (Inertia + dnd-kit, padrão Repair)
- [ ] Notificação Centrifugo quando OS muda de etapa (vendedor sabe sem olhar)
- [ ] Foto/anexo na etapa (designer sobe preview, instalador sobe foto da fachada)
- [ ] Histórico timeline + tempo em cada etapa
- [ ] Reaproveita `os_status_history` table padrão Repair

**Concorrência:** Mubisys ✅ PCP+sectorização, Zênite ✅ tempo real, Calcgraf ✅ industrial, Visua ✅ workflow instalação. **oimpresso 🟡** (Repair genérico existe, falta vocabulário gráfico).

---

### US-COMVIS-004 · Apontamento de máquina (Roland/Mimaki/Mutoh/HP Latex) — **P1**

> **Área:** Producao
> **Rota:** `POST /comvis/apontamento`
> **Controller/ação:** `ApontamentoController`

**Como** operador de plotter
**Quero** registrar início/fim do trabalho + m² impresso + consumo tinta (CMYK ml)
**Para** alimentar pós-cálculo (custo real vs orçado) e relatório produtividade máquina

**DoD:**
- [ ] Form mobile-first (operador usa celular ao lado da máquina)
- [ ] Campo: maquina_id, os_id, inicio, fim, m2_impresso, consumo_tinta_ml{c,m,y,k}, mídia_consumida_m2 (lona/vinil)
- [ ] Cálculo automático: `tempo_minutos = fim - inicio`; `m2_por_hora = m2 / (tempo/60)`
- [ ] Dashboard máquina: ocupação (%), m²/dia, custo tinta/m², custo mídia/m²
- [ ] Integração futura (P2): leitura direto do plotter via SNMP/SDK (Mubisys e Zênite têm)
- [ ] Multi-tenant + scope por máquina cadastrada no business

**Concorrência:** Zênite ✅ (coleta automática IoT — diferencial alto), Mubisys 🟡, Calcgraf 🟡. **oimpresso ❌**.

---

### US-COMVIS-005 · Pós-cálculo (orçado vs realizado) — **P1**

> **Área:** Financeiro
> **Rota:** `GET /comvis/os/{id}/pos-calculo`
> **Controller/ação:** `PosCalculoController`

**Como** dono/gestor
**Quero** ver, ao fechar OS, comparativo: custo orçado vs custo real (mídia consumida + tinta + mão-de-obra etapa + instalação)
**Para** descobrir qual produto/cliente dá margem real e ajustar tabela

**DoD:**
- [ ] Cruza `orcamento.subtotal_custo` (US-COMVIS-001) com soma de `apontamento.custo_real` (US-COMVIS-004) + folha etapa (R$/h × tempo)
- [ ] Margem % por OS, por cliente, por produto, por mês
- [ ] Alerta automático Jana: "OS-1234 fechou com margem -3% (orçado 22%) — quer revisar tabela?"
- [ ] Relatório export Excel/PDF
- [ ] Atende **dor #4 do top 10** (relatório margem por OS)

**Concorrência:** **Calcgraf ✅** (único com pós-cálculo formal). Mubisys/Zênite/Visua ❌. **oimpresso pode entregar via Modules/Financeiro + Jana 3 ângulos** ([ADR 0052](../../decisions/0052-faturamento-3-angulos.md)).

---

### US-COMVIS-006 · Tabela tributária CNAE 1813-0/01 (CFOP/CSOSN/NCM padrão) — **P0**

> **Área:** Fiscal
> **Rota:** seed migration + UI configuração
> **Reusa:** [Modules/NfeBrasil](../NfeBrasil/SPEC.md)

**Como** dono novo onboarding
**Quero** que materiais cadastrados em US-COMVIS-002 já venham com CFOP/CSOSN/NCM corretos pra impresso publicitário
**Para** emitir NFC-e/NFe sem precisar de contador configurar 80 produtos

**DoD:**
- [ ] Seed `comvis_tributacao_padrao`: NCMs 4911.10 (impressos publicitários), 4911.99, 3919 (vinil adesivo), 7610 (estruturas alumínio/ACM), 9405 (luminoso/letra-caixa)
- [ ] CFOP padrão: 5101/5102 (venda intra), 5933 (serviço gráfico), 5949 (instalação)
- [ ] CSOSN: 102 (Simples sem permissão crédito), 500 (ICMS retido anterior)
- [ ] Wizard onboarding: Jana detecta CNAE 1813-0 e pré-popula tabela
- [ ] Override por material/business_id

**Concorrência:** todos os verticais têm. **oimpresso ✅ NfeBrasil**, falta seed específica.

---

### US-COMVIS-007 · Gestão de fachada/instalação (agenda + equipe + EPI) — **P1**

> **Área:** Servicos
> **Rota:** `GET/POST /comvis/instalacao`
> **Controller/ação:** `InstalacaoController`

**Como** coordenador de instalação
**Quero** agendar equipe (instalador, ajudante, motorista) + ferramentas (escada, andaime, parafusadeira) + EPI + endereço cliente
**Para** não chegar no cliente sem furadeira ou sem 2ª pessoa pra fachada de 6m

**DoD:**
- [ ] Agenda calendário (semanal) — drag-drop instalação na slot
- [ ] Checklist ferramentas + EPI (NR-35 trabalho em altura — relevante)
- [ ] Endereço integrado Google Maps + foto da fachada (cliente sobe pré-vistoria)
- [ ] Comprovante instalação: foto + assinatura digital + GPS coords (LGPD: consent)
- [ ] Comissão instalador % por m² instalado

**Concorrência:** Visua ✅ (checklist instalação — diferencial vertical real), Zênite/Mubisys 🟡. **oimpresso ❌**.

---

### US-COMVIS-008 · NFSe automática pra serviço de instalação — **P1**

> **Área:** Fiscal
> **Reusa:** [Modules/NFSe](../NFSe/) (a criar — pendente)

**Como** financeiro
**Quero** que ao concluir US-COMVIS-007 (instalação aceita pelo cliente) o sistema emita NFSe automática (CNAE 7319-0/03)
**Para** não esquecer de emitir e dar problema fiscal

**DoD:**
- [ ] Trigger: instalacao.status='concluida' → dispatch job EmitirNfseInstalacao
- [ ] Integração com prefeitura local (cada município tem webservice próprio — começar SP/BH/Joinville/Floripa)
- [ ] Item de serviço LC 116/03 código 14.05 (composição gráfica) ou 32.01 (publicidade)
- [ ] PDF NFSe enviado WhatsApp cliente
- [ ] Multi-tenant + retry

**Concorrência:** Calcme ✅, Mubisys ✅, Zênite 🟡. **oimpresso ❌ Modules/NFSe não existe**.

---

### US-COMVIS-009 · NFe automática a partir de boleto pago — **P0** (já entregue no núcleo)

> **Área:** Fiscal
> **Reusa:** [Modules/RecurringBilling US-RB-044](../RecurringBilling/SPEC.md) — **JÁ ENTREGUE** ✅

**Como** financeiro
**Quero** que boleto/pix recebido (Asaas/Inter/Sicoob) dispare NFC-e automática
**Para** eliminar 2 cliques humanos do fluxo

**DoD:**
- [x] Webhook Asaas/Inter → Listener BoletoPago → Job EmitirNfceJob → NfeBrasil
- [x] Trigger configurável: emitir NFC-e ou NFe (B2B vs B2C)
- [x] Fallback: se SEFAZ down, retry exponencial 24h
- [ ] Adapter ComunicacaoVisual: ao receber pagamento de OS instalação, dispara NFSe (US-COMVIS-008) em vez de NFC-e

**Concorrência:** **NENHUM concorrente vertical entrega.** Diferencial #1 oimpresso.

---

### US-COMVIS-010 · Provador de orçamento online (formulário web público) — **P2**

> **Área:** Comercial
> **Rota:** pública `GET /b/{slug}/orcamento` (sem auth)
> **Controller/ação:** `OrcamentoPublicoController`

**Como** cliente final navegando
**Quero** preencher formulário no site da gráfica (largura, altura, material, foto inspiração) e receber preço estimado
**Para** decidir antes de ligar

**DoD:**
- [ ] Form público multi-step (mobile-first)
- [ ] Cálculo igual US-COMVIS-001 mas com markup configurável (gráfica pode mostrar +20% pra quem chega pelo site)
- [ ] Captcha + rate-limit (anti-spam)
- [ ] Lead vai pra CRM funil "orçamento web" (US-COMVIS-011)
- [ ] WhatsApp clique-pra-conversar
- [ ] Subdomínio whitelabel: `{slug}.oimpresso.app` ou domínio próprio

**Concorrência:** Calcme ✅ (Chatme), Alfa ✅ (loja virtual), Mubisys 🟡. **oimpresso ❌**.

---

### US-COMVIS-011 · Comissão por OS (vendedor + instalador) — **P1**

> **Área:** Financeiro
> **Reusa:** [Modules/Financeiro](../Financeiro/) HR/folha

**Como** dono
**Quero** que ao fechar OS, comissão do vendedor (% sobre venda líquida) e do instalador (% sobre instalação) sejam calculadas
**Para** pagar correto na folha sem planilha paralela

**DoD:**
- [ ] Regra config por funcionário: `comissao_venda_pct`, `comissao_instalacao_pct`, `regra` (sobre faturado / sobre recebido)
- [ ] Trigger: pagamento confirmado (US-COMVIS-009) → cria lançamento `comissao_pendente`
- [ ] Relatório mensal por vendedor/instalador
- [ ] Integra folha (provisão DRE)

**Concorrência:** Mubisys ✅, Calcgraf ✅, Zênite ✅. **oimpresso 🟡 (Crm básico, falta automatizar)**.

---

### US-COMVIS-012 · DAM básico — cliente envia arquivo print-ready — **P2**

> **Área:** Producao
> **Rota:** upload S3-compatible (Minio CT 100 ou Wasabi)

**Como** cliente
**Quero** subir PDF/AI/PSD print-ready direto no portal sem mandar por WhatsApp 80MB
**Para** designer baixar pronto + manter histórico de versões

**DoD:**
- [ ] Upload chunked (>100MB ok) — biblioteca Uppy
- [ ] Validação: PDF/X-1a, CMYK, sangria 3mm (preflight)
- [ ] Versionamento: cliente sobe v1, v2 — designer escolhe ativa
- [ ] Storage: Minio no CT 100 (não Hostinger)
- [ ] Multi-tenant: arquivos isolados por business_id

**Concorrência:** **Mubisys ✅ MubiDrive (150+ TB — diferencial forte)**. oimpresso ❌ — gap real.

---

### US-COMVIS-013 · Bulk update preço material via Jana — **P2**

> **Área:** IA
> **Reusa:** [Modules/Jana](../../../Modules/Jana) tools

**Como** dono
**Quero** dizer pra Jana "aumenta 5% em todo lona 440g a partir de amanhã"
**Para** repassar reajuste de fornecedor sem editar 40 produtos

**DoD:**
- [ ] Jana tool `comvis.materiais.bulk_update` (PolicyEngine REQUIRE_HUMAN_REVIEW antes de aplicar)
- [ ] Preview: "vou aumentar 5% em 12 materiais — confirme"
- [ ] Aplicação com data efetiva futura
- [ ] Audit log com user, motivo, escopo

**Concorrência:** **NENHUM concorrente entrega** (dor #3 top 10 documentada PrintPlanet).

---

### US-COMVIS-014 · Dashboard "Larissa pergunta no chat às 22h" — **P2**

> **Área:** IA
> **Reusa:** [Modules/Jana](../../../Modules/Jana) ContextSnapshot + 3 ângulos faturamento

**Como** dona-operadora (perfil ROTA LIVRE/Larissa)
**Quero** perguntar "quanto faturei essa semana de banner vs lona", "qual cliente mais lucrou em abril", "quais OS atrasaram entrega" no celular fora-de-hora
**Para** decidir sem abrir 4 telas de relatório

**DoD:**
- [ ] Contexto vertical comvis: faturamento por categoria material, margem por cliente, OS atrasadas no PCP
- [ ] 3 ângulos faturamento (recebido/faturado/orçado) — [ADR 0052](../../decisions/0052-faturamento-3-angulos.md)
- [ ] Resposta com query SQL auditável anexa
- [ ] Atende **dor #1 wedge** do research (transparência radical + IA)

**Concorrência:** **NENHUM concorrente vertical entrega.** Calcme tem WhatsApp (canal), oimpresso tem Jana (entendimento).

---

### US-COMVIS-015 · Cadastro de máquina com tinta/CMYK consumption tracking — **P2**

> **Área:** Inventory
> **Rota:** `GET/POST /comvis/maquinas`

**Como** PCP
**Quero** cadastrar plotter (modelo Roland VS-540, Mimaki JV-150, HP Latex 365) com cartuchos atuais (ml restante CMYK)
**Para** alertar reposição antes de plotter parar no meio do trabalho

**DoD:**
- [ ] CRUD máquina: modelo, fabricante, tipo (eco-solvente/UV/Latex), cartuchos[]
- [ ] Atualização ml restante via US-COMVIS-004 apontamento
- [ ] Alerta: "Cyan da Roland VS-540 com 15% — reposição"
- [ ] Custo CMYK/m² calculado por máquina

**Concorrência:** Zênite ✅ (coleta máquina), Mubisys 🟡, Calcgraf 🟡. **oimpresso ❌**.

---

### US-COMVIS-016 · CT-e/MDF-e pra entrega — **P3**

> **Área:** Fiscal
> **Reusa:** [Modules/NfeBrasil](../NfeBrasil/SPEC.md) (não tem hoje)

**Como** financeiro de gráfica que entrega
**Quero** emitir CT-e (transporte) e MDF-e (manifesto) automaticamente quando OS sai pra entrega
**Para** estar legal — Ajustes SINIEF abr/2026 tornaram alguns itens obrigatórios

**DoD:**
- [ ] CT-e modelo 57 + MDF-e modelo 58 via sped-nfe
- [ ] Trigger: OS.status='em_entrega' → emite CT-e
- [ ] Multi-tenant + retry
- [ ] Integração com Modules/NfeBrasil pipeline

**Concorrência:** Calcgraf ✅, Calcme ✅, Bling/Omie ✅. **oimpresso ❌** (gap #3 comparativo Capterra).

---

### US-COMVIS-017 · Importação massiva de clientes/produtos do legacy OfficeImpresso — **P0**

> **Área:** Onboarding
> **Reusa:** [Migration Factory ADR 0119](../../decisions/0119-migration-factory.md)

**Como** dono migrando do OfficeImpresso Delphi
**Quero** trazer clientes (CPF/CNPJ + endereço + histórico OS) + produtos + saldos abertos AR/AP em 1 clique
**Para** não digitar 5.000 cadastros do zero

**DoD:**
- [ ] Conector Firebird .FDB (skill `officeimpresso-financial-snapshot` reuso)
- [ ] Mapeamento: clientes_legacy → contacts; produtos_legacy → comvis_materiais; OS abertas → orcamentos pendentes
- [ ] Anonimização opcional pra demo (PIIs reais [REDACTED])
- [ ] Dry-run report antes de gravar
- [ ] Multi-tenant: importa pra business_id alvo

**Concorrência:** Bling ✅, Omie ✅ (genéricos com importer maduro). Verticais: ❌ todos. **oimpresso 🟡** — base UltimatePOS importer existe.

---

### US-COMVIS-018 · Loja whitelabel pra catálogo público — **P3**

> **Área:** Comercial
> **Rota:** `GET /b/{slug}` página pública

**Como** dono
**Quero** ter mini-site público com catálogo de produtos (banner, lona, fachada) com preço-base e foto
**Para** atrair lead orgânico SEO sem contratar agência

**DoD:**
- [ ] Tema único whitelabel (cor + logo + endereço configurável)
- [ ] Listagem produtos com SEO meta
- [ ] CTA "fazer orçamento" → US-COMVIS-010
- [ ] Subdomínio próprio ou domínio cliente

**Concorrência:** Alfa ✅, Calcme 🟡. **oimpresso ❌**.

## 4. Concorrentes verticais

### 4.1 Mubisys (Mubi Sistemas) — Barueri/SP — 13 anos

- 14k+ usuários, 1.800+ empresas (claim)
- SaaS cloud puro + apps mobile iOS/Android
- **Diferencial forte:** MubiDrive DAM (150+ TB), 4.9/5 em 300+ reviews próprios
- **Calcanhar documentado:** "sistema engessado sem possibilidade de integração ou consulta de dados" ([Reclame Aqui fev/2023](https://www.reclameaqui.com.br/mubi-sistemas/sistema-engessado-sem-possibilidade-de-integracao-ou-consulta-de-dados-bom_PlMWx0_YDQRfinlQ/))
- Stack PHP tradicional (`?app=` URL)
- Pricing opaco, trial 7 dias

### 4.2 Zênite Sistemas (GE 4.0 / GWorks Enterprise) — BH/MG — 32 anos

- 2.200+ gráficas atendidas (claim)
- **Diferencial forte:** coleta automática de dados de máquinas (IoT), Mapa RKW, Módulo Balcão
- Hybrid web+desktop em migração
- **Calcanhar:** instabilidade fim-de-semana ([Reclame Aqui](https://www.reclameaqui.com.br/zenite-sistemas/zenite-sistemas-uma-pessima-escolha-para-comunicacao-visual_EmZx7mAtts45a2LE/)), sem app mobile, base envelhecida
- Endorsement Singrafs/Assingrafs

### 4.3 Calcgraf — São Paulo/SP — 40 anos

- 1.000+ implantações, 2M orçamentos/mês
- **Diferencial forte:** **pós-cálculo** formal (único do mercado), SPED+CT-e+MDF-e completo, NetCalc gratuito até 3 vendedores
- Mid/large market industrial offset/embalagem
- **Calcanhar:** overengineering pra pequena gráfica rápida, stack legacy 40 anos
- Cases públicos (ADEgraf, Prefeitura BH)

### 4.4 Calcme — Blumenau/SC

- 1.000+ empresas (claim), Calcme3D pra marcenaria
- **Diferencial forte:** Chatme (WhatsApp), Assiname (assinatura digital), Calcpay (cobrança), 3D
- **Calcanhar:** **4 reclamações Reclame Aqui sérias** padrão "trial promete, contrato engessa" ([RA 1](https://www.reclameaqui.com.br/calcme-sistemas/calcme-sistemas-sistema-facil-para-adquirir-mas-que-nao-funciona-e-nada_DIhIlFBd5TvAWnG9/), [RA 2](https://www.reclameaqui.com.br/calcme-sistemas/nao-cumpre-o-que-promete_D48IewhtKuLCApZM/)), importação manual produto, PDV duplica valor, sem reembolso

### 4.5 Alfa Networks — Limeira/SP

- SaaS + trial 7 dias
- **Diferencial:** loja virtual integrada, cashback embutido, CNAB 240 (6 bancos)
- **Calcanhar:** treinamento ineficiente reclamado, sem CRM, sem IA, tempo médio resposta RA 20d 14h

### 4.6 Visua — Joinville/SC — 17 anos

- **Diferencial forte:** FPV (Formação de Preço de Venda), checklist de instalação (real diferencial)
- Hybrid Win7+ desktop + Visua Web parcial
- **Calcanhar:** sem mobile, sem API pública, sem IA — só CRUD bem feito

## 5. Diferenciais oimpresso

| Diferencial | oimpresso | Mubisys | Zênite | Calcgraf | Calcme | Alfa | Visua |
|---|---|---|---|---|---|---|---|
| **Jana IA conversacional + memória persistente** ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)) | ✅ em construção | ❌ | ❌ | ❌ | 🟡 (WhatsApp não-IA) | ❌ | ❌ |
| **NFe automática boleto pago** (US-RB-044 entregue) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | ✅ | 🟡 | 🟡 | ❌ | ❌ | ❌ | ❌ |
| **Stack moderna** (Laravel 13.6 + Inertia v3 + React 19 + Tailwind 4) | ✅ | ❌ PHP trad | 🟡 migrando | ❌ legacy 40a | 🟡 | 🟡 | ❌ Win7 desktop |
| **MCP server governado** ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Constituição v2 + ADRs append-only** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Demo honesta (cliente piloto público)** | ✅ ROTA LIVRE | ❌ | ❌ | 🟡 cases | ❌ | ❌ | 🟡 |

**Wedge primário:** *"O ERP de comunicação visual com IA contextual + NFe-de-boleto-pago + transparência radical (cliente piloto telefonável). Os outros têm cálculo m². Só nós fechamos o loop até o boleto pago disparar a nota — e ainda respondemos no chat às 22h."*

## 6. Arquitetura técnica

### 6.1 Estrutura de diretórios

```
Modules/ComunicacaoVisual/   ← a criar
├── Config/
│   ├── config.php
│   └── permissions.php       ← Spatie permissions: comvis.orcamento.{view,create,update}, comvis.os.*, comvis.material.*
├── Database/
│   ├── Migrations/
│   │   ├── create_comvis_materiais_table.php
│   │   ├── create_comvis_orcamentos_table.php
│   │   ├── create_comvis_orcamento_itens_table.php
│   │   ├── create_comvis_os_table.php
│   │   ├── create_comvis_os_etapas_table.php
│   │   ├── create_comvis_apontamentos_table.php
│   │   ├── create_comvis_maquinas_table.php
│   │   ├── create_comvis_instalacoes_table.php
│   │   └── seed_comvis_tributacao_padrao_cnae_1813.php
│   └── Seeders/
├── Entities/                ← Eloquent Models (com BusinessIdScope global)
│   ├── Material.php
│   ├── Orcamento.php
│   ├── OrcamentoItem.php
│   ├── Os.php
│   ├── OsEtapa.php
│   ├── Apontamento.php
│   ├── Maquina.php
│   └── Instalacao.php
├── Http/
│   ├── Controllers/
│   │   ├── DataController.php       ← UltimatePOS hooks: user_permissions, modifyAdminMenu, superadmin_package
│   │   ├── InstallController.php    ← 3 rotas obrigatórias (status, install, uninstall) — RUNBOOK-criar-modulo
│   │   ├── OrcamentoController.php
│   │   ├── OsController.php
│   │   ├── MaterialController.php
│   │   ├── MaquinaController.php
│   │   ├── ApontamentoController.php
│   │   ├── InstalacaoController.php
│   │   ├── PosCalculoController.php
│   │   └── OrcamentoPublicoController.php  ← rota pública, sem auth, com captcha
│   ├── Middleware/
│   └── Requests/
├── Listeners/
│   ├── BoletoPagoEmiteNFCe.php       ← reusa US-RB-044 + decide NFC-e ou NFSe
│   └── OsConcluidaEmiteNFSe.php
├── Jobs/
│   ├── ImportarLegacyOfficeImpresso.php  ← Firebird → MySQL
│   └── BulkUpdateMateriaisJob.php
├── Resources/
│   ├── views/  (mínimo Blade — 99% Inertia)
│   └── lang/
├── Routes/
│   ├── web.php
│   └── api.php
├── Services/
│   ├── OrcamentoCalculator.php       ← cálculo m² + extras
│   ├── PosCalculoService.php
│   ├── ApontamentoService.php
│   └── OnboardingComvisService.php   ← wizard CNAE 1813
├── Tests/
│   ├── Feature/
│   └── Unit/
├── module.json
└── composer.json
```

Frontend Inertia em `resources/js/Pages/ComunicacaoVisual/` seguindo Cockpit Pattern V2 ([ADR 0110](../../decisions/0110-cockpit-pattern-v2.md)) com `.charter.md` ao lado de cada Page (S4+).

### 6.2 Extensions UltimatePOS

- **Variation custom fields** em `variations` table: `largura_padrao_m`, `altura_padrao_m`, `gramatura_g_m2`, `categoria_comvis` (lona/vinil/ACM/tela)
- **DataController hooks** registrados em `Modules/ComunicacaoVisual/Http/Controllers/DataController.php`:
  - `user_permissions()` — Spatie roles
  - `modifyAdminMenu()` — sidebar entries
  - `superadmin_package()` — feature flags por business
- **Eloquent global scope** `BusinessIdScope` em todas Models (skill `multi-tenant-patterns` Tier A — [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- **Tabela `business.vertical_id`** apontando pra `verticals.slug='ComunicacaoVisual'` (ADR 0121 §P4)
- **Reuso Modules/Repair Kanban** pra PCP (US-COMVIS-003)
- **Reuso Modules/NfeBrasil** pra fiscal (US-COMVIS-006/008/009/016)
- **Reuso Modules/Jana** pra IA (US-COMVIS-013/014)
- **Reuso Modules/RecurringBilling** pra trigger boleto pago (US-COMVIS-009)

### 6.3 Schema essencial

```sql
-- comvis_materiais
id, business_id (FK + scope), nome, categoria, gramatura_g_m2,
preco_custo_m2, preco_venda_m2, minimo_m2, fornecedor_id,
ncm, cfop_padrao, csosn_padrao, ativo, created_at, updated_at

-- comvis_orcamentos
id, business_id, contato_id (FK contacts), vendedor_id (FK users),
status (rascunho|enviado|aprovado|reprovado|virou_os),
subtotal, extras, instalacao, entrega, total,
data_validade, observacao, created_at, updated_at

-- comvis_orcamento_itens
id, orcamento_id, material_id, largura_m, altura_m, qtd, area_m2_calc,
preco_m2_aplicado, acabamentos_json, subtotal_item

-- comvis_os
id, business_id, orcamento_id, codigo (sequencial biz),
status_etapa_atual (FK comvis_os_etapas),
data_prazo, data_conclusao, valor_total, valor_recebido

-- comvis_os_etapas
id, os_id, etapa (design|prepress|impressao|acabamento|instalacao|entrega),
responsavel_id, data_inicio, data_fim, custo_previsto, custo_real,
observacao, anexos_json

-- comvis_maquinas
id, business_id, modelo, fabricante, tipo (eco_solvente|uv|latex|sublimacao),
cartuchos_json, custo_tinta_ml_padrao, ativo

-- comvis_apontamentos
id, business_id, maquina_id, os_id, operador_id,
inicio, fim, m2_impresso, consumo_tinta_json, midia_consumida_m2

-- comvis_instalacoes
id, business_id, os_id, equipe_json, data_agenda, data_realizada,
endereco_json, lat_lng, foto_pre, foto_pos, assinatura_cliente,
nfse_id (FK nfe_documents), comissao_calculada
```

Todos com `business_id` indexado + FK + global scope (Tier 0 IRREVOGÁVEL).

## 7. Roadmap 12 meses

| Mês | Fase | Entregas | Métrica de saída |
|---|---|---|---|
| **M1** (jul/26) | Foundation | Module scaffold + DataController + InstallController + 3 migrations core (materiais, orcamentos, os) + Charter inicial | `php artisan module:install ComunicacaoVisual` funciona em dev |
| **M2** (ago/26) | Pricing core | US-COMVIS-001 + US-COMVIS-002 + US-COMVIS-006 (tributária CNAE) + Pest tests | Vendedor consegue calcular orçamento m² lona/vinil/ACM em <2min |
| **M3** (set/26) | PCP + 1ª piloto | US-COMVIS-003 (Kanban via Repair) + US-COMVIS-017 (importer OfficeImpresso) + 1ª piloto migrada (Vargas/Extreme/Gold a confirmar) | 1 cliente real pagando R$ [redacted Tier 0]-499/m módulo |
| **M4** (out/26) | Producao + Fiscal | US-COMVIS-004 (apontamento) + US-COMVIS-005 (pós-cálculo) + US-COMVIS-009 (NFe-de-boleto adapter) | Pós-cálculo mostra margem real OS na piloto |
| **M5** (nov/26) | Servicos + comissão | US-COMVIS-007 (instalação) + US-COMVIS-008 (NFSe — após Modules/NFSe) + US-COMVIS-011 (comissão) | Fluxo fachada/instalação ponta-a-ponta |
| **M6** (dez/26) | Network effect base | 2ª piloto migrada + dashboard benchmark setor (média margem comvis) + case Vargas público | 2 clientes pagantes; case demonstrável |
| **M7-M9** (jan-mar/27) | IA + Mobile | US-COMVIS-013 (Jana bulk update) + US-COMVIS-014 (Jana 22h) + US-COMVIS-010 (provador online) + US-COMVIS-015 (cartuchos máquina) + responsividade mobile-first | 3 clientes pagantes; Jana respondendo perguntas reais |
| **M10-M12** (abr-jun/27) | Escala | 3ª/4ª piloto + US-COMVIS-016 (CT-e/MDF-e) + US-COMVIS-018 (whitelabel) + US-COMVIS-012 (DAM básico) | **5 clientes pagantes; ARR R$ [redacted Tier 0]-60k; 2 cases públicos** |

## 8. Estratégia de migração — 6 saudáveis OfficeImpresso

Base [Migration Factory ADR 0119](../../decisions/0119-migration-factory.md). Receita por cliente:

| Etapa | Owner | Esforço |
|---|---|---|
| 1. Snapshot financeiro pré-venda (skill `officeimpresso-financial-snapshot`) | Wagner | 2h |
| 2. Apresentação personalizada (mostrando receita real do cliente extraída do .FDB) | Wagner | 1h |
| 3. Aceite + assinatura (Calcme tem Assiname; usar DocuSign ou doc PDF + WhatsApp) | Wagner | 1h |
| 4. Criar `business_id` novo + `vertical_id=ComunicacaoVisual` | Felipe | 1h |
| 5. Importação clientes/produtos/saldos abertos via US-COMVIS-017 (Firebird → MySQL) | Claude IA-pair + Felipe | 8h |
| 6. Onboarding wizard Jana detecta CNAE 1813 + pré-popula tributária | sistema | 0h (auto) |
| 7. Treinamento + go-live (Larissa-style — vídeo curto + WhatsApp suporte) | Maiara | 16h |
| 8. Canary 7d (cliente roda em paralelo OfficeImpresso) | cliente | 7d wallclock |
| 9. Cutover final (desliga OfficeImpresso) + monitor 30d | Felipe | 4h |

**Esforço total por piloto: ~33h time interno + 7d canary + 30d monitor.** Com IA-pair Claude ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)), etapa 5 (importação) cai de 8h pra 1h após conector reusado.

### Ordem sugerida (priorizar primeiro)

Quando snapshot financeiro de cada um estiver pronto, priorizar quem tem:
1. Maior GMV (mais ARR potencial)
2. Maior dor explícita com Delphi (drives migration urgency)
3. Geografia próxima (suporte presencial possível) — Maiara/Wagner em SC, ROTA LIVRE em SC
4. Já é B2B com NFe (pra logo provar US-COMVIS-009)

## 9. Métricas de sucesso 12m

| Métrica | Baseline (M0) | M6 | M12 | Crítica |
|---|---|---|---|---|
| Clientes pagantes Modules/ComunicacaoVisual | 0 | 2 | **3-5** | <2 = re-avaliar tese |
| ARR módulo (R$/ano) | 0 | R$ [redacted Tier 0]k | **R$ [redacted Tier 0]-60k** | <R$ [redacted Tier 0]k = pivotar |
| US entregues (de 18 totais) | 0 | 11 (P0+P1 core) | **15** | <12 = stack mal calibrado |
| Cases públicos clicáveis | 0 | 1 | **2** | (transparência radical) |
| Pós-cálculo: gráficas com margem real visível | 0 | 1 | 3 | (diferencial vs Mubisys) |
| Bug crítico em produção | n/a | <1/mês | <1/trimestre | (Pest gate ADR 0094) |
| Churn módulo | n/a | 0% | <10%/ano | (review trigger ADR 0121) |

**Meta convergente com [ADR 0022](../../decisions/0022-meta-5mi-ano-financeira.md):** Modules/ComunicacaoVisual contribui R$ [redacted Tier 0]-60k ARR de R$ [redacted Tier 0]M total (1-2% no M12). Multi-vertical é a tese, não vertical único.

## 10. Anti-padrões — o que NÃO fazer

1. ❌ **Copiar feature-set Mubisys e cobrar 30% menos** — Caminho A rejeitado em [comparativo Capterra](../../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md). Sem narrativa, sem diferencial, perde por base instalada.
2. ❌ **Construir SEM cliente piloto pagante real** — viola [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md). Antes de M3 ter 1ª piloto migrada, não escalar features além de P0.
3. ❌ **Hard-code CNAE 1813 / vocabulário gráfico no núcleo** — quebra [ADR 0121 §P1](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) (núcleo é horizontal). Tudo específico vai em `Modules/ComunicacaoVisual/`.
4. ❌ **Reutilizar Modules/Repair com "vehicle"/"placa"/"box" no UI** — [Modules/Repair §P8](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) é shared infra; ComVis precisa override de labels (OS, etapa, cliente — não placa/veículo).
5. ❌ **Esquecer `business_id` global scope em qualquer Model nova** — Tier 0 IRREVOGÁVEL ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)). Skill `multi-tenant-patterns` enforce.
6. ❌ **Daemon/job pesado no Hostinger** — [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md). Apontamento real-time, importer Firebird, Jana embeddings → CT 100. App web → Hostinger.
7. ❌ **PII real (CPF/CNPJ ROTA LIVRE/Vargas/etc) em PR/commit/log** — skill `commit-discipline` Tier A. `[REDACTED]` ou `PiiRedactor`.
8. ❌ **Vender "concorrente do Mubisys" antes de provar 1 case real** — wedge é "transparência radical com cliente piloto telefonável", não feature-parity.
9. ❌ **Construir DAM/MubiDrive own-built de cara** (US-COMVIS-012 P2) — Mubisys investiu anos. Começar com S3-compatible (Wasabi/Minio CT 100) + Uppy upload chunked; UI básica suficiente.
10. ❌ **Implementar SPED contábil completo** — gap explícito; deixar pra contador externo. Foco em SPED fiscal (já reusa NfeBrasil).
11. ❌ **Cálculo m² em frontend** sem servidor validar — rule R-COMVIS-001: server-side authoritative pra evitar manipulação preço.
12. ❌ **App mobile nativo M1-M6** — adiável 12m se Inertia/React mobile-first (Tailwind 4 responsive) + PWA cobrir o caso de uso "vendedor in-loco".
13. ❌ **Onboarding sem wizard Jana** — gráficas pequenas não pagam consultor implantação. Jana detecta CNAE 1813 e pré-popula. Caso contrário, churn alto.
14. ❌ **Esquecer `php artisan module:install` rotas obrigatórias** — RUNBOOK-criar-modulo §3 rotas Install (status, install, uninstall) senão botão fica sem ação ([ADR 0024](../../decisions/0024-module-install-routes-canonical.md)).
15. ❌ **Migrar 6 pilotos em paralelo** — escala humana 5 pessoas. Migration Factory rolling: 1 piloto por mês, depois 2/mês após M6 (curva aprendizado).

---

## Anexo — links canônicos

- [ADR 0121 — modular especializado por vertical](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- [Comparativo Capterra/G2 oimpresso vs concorrentes (2026-04-25)](../../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md)
- [Research Zênite + Mubisys (2026-05-09)](../../research/2026-05-prospeccao/02-concorrentes-zenite-mubisys.md)
- [Research Alfa+Visua+Calcgraf+Calcme + reviews setor (2026-05-09)](../../research/2026-05-prospeccao/03-concorrentes-alfa-visua-calcgraf-reviews.md)
- [RUNBOOK criar módulo](../Infra/RUNBOOK-criar-modulo.md)
- [Modules/NfeBrasil SPEC](../NfeBrasil/SPEC.md) (reuso fiscal)
- [Modules/Jana](../../../Modules/Jana) (reuso IA)
- [Modules/Repair](../../../Modules/Repair) (reuso Kanban PCP — shared infra)
