---
module: ComunicacaoVisual
version: "1.0"
last_updated: "2026-06-13"
status: rascunho
piloto: Gold confirmado vertical comvis (perfil 04-gold-comvis) — Vargas REMOVIDO (autopeças confirmado 2026-05-10 → Modules/OficinaAuto)
piloto_previsao: 2026-Q3
cnae_principal: "1813-0/01"
related_adrs: [0121-oimpresso-modular-especializado-por-vertical, 0143-fsm-pipeline-live-prod-marco-2026-05-12, 0094-constituicao-v2-7-camadas-8-principios, 0093-multi-tenant-isolation-tier-0, 0035-stack-ai-canonica-wagner-2026-04-26, 0119-migration-factory-capacidade-institucional, 0117-multiplos-numeros-whatsapp-por-business, 0136-sells-grade-avancada-modo-toggle, 0105-cliente-como-sinal-guiar-sem-mandar, 0011-alinhamento-padrao-jana, 0024-instalacao-1-clique-modulos]
last_review: 2026-05-12
owner: [W]
---

<!-- schema-allowlist: US sob "## 3. Capacidades core (User Stories)"; heading numerado-com-sufixo do projeto não casa o regex literal do gate estendido (## N. User stories), mas as US-COMVIS-NNN estão todas lá. Heading preservado pra não reestruturar o corpo. -->

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

Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart — todos em produção Delphi legacy WR Sistemas com R$ [redacted Tier 0]k–R$ [redacted Tier 0]M GMV/ano. Migration Factory ([ADR 0119](../../decisions/0119-migration-factory-capacidade-institucional.md)) move cada um.

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

**Concorrência:** **Calcgraf ✅** (único com pós-cálculo formal). Mubisys/Zênite/Visua ❌. **oimpresso pode entregar via Modules/Financeiro + Jana 3 ângulos** ([ADR 0052](../../decisions/0052-contextonegocio-expor-multiplos-angulos.md)).

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
- [ ] 3 ângulos faturamento (recebido/faturado/orçado) — [ADR 0052](../../decisions/0052-contextonegocio-expor-multiplos-angulos.md)
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
> **Reusa:** [Migration Factory ADR 0119](../../decisions/0119-migration-factory-capacidade-institucional.md)

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

Frontend Inertia em `resources/js/Pages/ComunicacaoVisual/` seguindo Cockpit Pattern V2 ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)) com `.charter.md` ao lado de cada Page (S4+).

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

Base [Migration Factory ADR 0119](../../decisions/0119-migration-factory-capacidade-institucional.md). Receita por cliente:

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
14. ❌ **Esquecer `php artisan module:install` rotas obrigatórias** — RUNBOOK-criar-modulo §3 rotas Install (status, install, uninstall) senão botão fica sem ação ([ADR 0024](../../decisions/0024-instalacao-1-clique-modulos.md)).
15. ❌ **Migrar 6 pilotos em paralelo** — escala humana 5 pessoas. Migration Factory rolling: 1 piloto por mês, depois 2/mês após M6 (curva aprendizado).

---

## 11. Pipeline FSM canônico Comunicação Visual ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))

> Sessão 2026-05-12 marcou FSM Pipeline canônico LIVE em prod biz=1 (40+ PRs ~10h). ComVis **reusa fundação canon** `sale_processes` + `sale_process_stages` + `ExecuteStageActionService` + `GuardsFsmTransitions` — não duplica fundação. Stages CV-específicos cadastrados PER-business via processo seed "OS Comunicação Visual".
>
> Detalhes arquiteturais em [proposal ADR `comunicacao-visual-modulo-canonico`](../../decisions/proposals/drafts/comunicacao-visual-modulo-canonico.md) §D2.

### 11.1 Stages canônicos CV (13 ativos + 4 laterais + 2 terminais)

```
quote_draft (initial)
  → quote_sent
  → quote_approved
  → arte_em_aprovacao              ← split do "aprovado" pra ciclo designer→cliente
  → arte_aprovada
  → aguardando_maquina             ← OPCIONAL — habilitado per-business (Extreme PCP industrial); off pra Gold comvis sob demanda
  → em_impressao
  → impressao_concluida
  → aguardando_acabamento          ← 1 stage genérico; sub-itens corte/ilhós/costura/perfuração via acabamento_json
  → acabamento_concluido
  → aguardando_instalacao          ← SKIP se instalacao_tipo='cliente_busca' (jump direto pra entregue_completo)
  → em_instalacao
  → instalado_aguardando_aprovacao_final  ← cliente recebe foto pós + assina digital
  → entregue_completo (T)

Laterais (transitam pra estados não-terminais):
  → rejeitar_arte → arte_em_aprovacao (loop volta + side-effect NotificarDesigner)
  → refazer_impressao → em_impressao (side-effect ConsumirEstoqueExtra + AlertaMargemNegativa)
  → reagendar_instalacao → aguardando_instalacao (side-effect AtualizarAgendaEquipe)

Terminais laterais:
  → cancelado (T)            ← qualquer stage não-terminal; side-effect CancelarVendaCascade (libera reserva + cancela NFe se já emitida + estorna boleto)
  → garantia_acionada (T)    ← pós entregue_completo; abre OS filha tipo "garantia"
```

### 11.2 Actions críticas (🔒 — RBAC obrigatório + audit + side-effects)

| Action | Roles permitidas | Side-effects | Anti-hook charter |
|---|---|---|---|
| `enviar_para_aprovacao_arte` | designer, gerente | `NotificarClienteAprovacaoArteJob` (WhatsApp ADR 0117) | Respeita `whatsapp_consent` LGPD |
| `aprovar_arte` 🔒 | sistema (via link público token) ou gerente | freeze arte_url (imutável daqui) | Bloqueia recálculo m² (#2) |
| `iniciar_impressao` 🔒 | operador, gerente | — | NUNCA dispara plotter auto (#1) |
| `concluir_impressao` 🔒 | operador, gerente | `ConsumirEstoque` substrato (m² lona da reservation) | — |
| `concluir_acabamento` 🔒 | operador, gerente | — | — |
| `concluir_instalacao` 🔒 | instalador, gerente | unlock `faturar`; gera assinatura cliente + GPS (LGPD-consent) | NUNCA marca auto "concluído" (#5) |
| `emitir_nfe_e_nfse` 🔒 | gerente, financeiro | dispatch `EmitirNfeJob` + `EmitirNfseJob` PARALELO (ver §13) | NUNCA emite fiscal auto (#3) |
| `cancelar_os` 🔒 | gerente | `CancelarVendaCascade` (libera reserva + cancela docs + estorna boleto) | NUNCA cancela NFe autorizada sem fluxo formal (#9) |
| `aplicar_garantia` 🔒 | gerente | abre OS filha tipo garantia | — |

### 11.3 Override per-business (stages opcionais)

Cada gráfica habilita/desabilita stages via `sale_process_stages.business_id` FK + flag `is_active`:

| Stage | Gold (comvis sob demanda) | Extreme (industrial PCP) | Razão |
|---|:-:|:-:|---|
| `aguardando_maquina` | OFF | **ON** | Gold zero PCP industrial; Extreme 52k linhas centro_trabalho |
| `arte_em_aprovacao` | ON | ON | universal |
| `aguardando_acabamento` | ON | ON | universal |
| `aguardando_instalacao` | ON (50% das OS) | ON (30% das OS) | both atendem fachada |

---

## 12. Schema proposto `Modules/ComunicacaoVisual/Entities`

> Detalhes em [proposal ADR §D1+§schema](../../decisions/proposals/drafts/comunicacao-visual-modulo-canonico.md). Todas tabelas com `business_id` indexado + FK + global scope (Tier 0 [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

### 12.1 Tabelas (6 core + 2 opcionais)

| Tabela | Tipo | Campos críticos | FK chave |
|---|---|---|---|
| `cv_substratos` | catálogo | nome, categoria (lona/vinil/adesivo/acm/tela/mdf/neon/letra_caixa), gramatura_g_m2, preco_custo_m2, preco_venda_m2, minimo_m2, ncm, cfop_padrao, csosn_padrao | business_id, fornecedor_id |
| `cv_acabamentos` | catálogo | nome, tipo ENUM(m_linear/unitario/m2/fixo), preco DECIMAL(8,2) | business_id |
| `cv_instalacoes_catalogo` | catálogo | nome, preco_base, preco_m2, preco_km, exige_nr35, ferramentas_necessarias_json | business_id |
| **`cv_ordens_producao`** | transacional | codigo, contato_id, current_stage_id (FK sale_process_stages), substrato_id, largura_m, altura_m, qtd, area_m2 GENERATED, acabamento_json, instalacao_tipo ENUM, endereco_instalacao_json, equipamentos_necessarios_json, arte_url, arte_aprovada_em, estimated_completion, prazo_prometido (mapeia PROJETO_DT_FIM Delphi — `_LICOES-CRITICAS.md` §3), commission_distribution_json (§14), subtotal, extras, total | business_id, transaction_id (FK opcional pro fiscal), orcamento_id |
| `cv_instalacoes` | execução | equipe_user_ids_json, data_agendada, data_realizada, foto_pre_url, foto_pos_url, assinatura_cliente_url, lat_lng_inicio POINT, lat_lng_fim POINT | business_id, ordem_id, nfse_emissao_id (FK nfe_documents NULLABLE) |
| `cv_orcamentos` | transacional | status (rascunho/enviado/aprovado/reprovado/virou_os), subtotal, extras, instalacao, entrega, total, data_validade, observacao | business_id, contato_id, vendedor_id |

**Opcionais (Extreme/PCP industrial):**

| Tabela | Tipo | Razão | Ativação |
|---|---|---|---|
| `cv_maquinas` | catálogo plotters | Roland/Mimaki/HP Latex + cartuchos CMYK json | per-business flag |
| `cv_apontamentos` | execução | inicio, fim, m2_impresso, consumo_tinta_json | per-business flag |

### 12.2 Decisão "campos em cv_ordens_producao direto vs sub-tabelas"

| Campo | Decisão | Razão |
|---|---|---|
| `acabamento_json` | JSON inline | Catalog estável (5-10 opções); busca analytics secundária via JSON_EXTRACT MySQL 8+ |
| `commission_distribution_json` | JSON inline | Multi-papel flexível; promover pra `cv_commission_lines` quando gráfica >100 OS/m |
| `equipamentos_necessarios_json` | JSON inline | Pequeno (3-8 items), não FK |
| `endereco_instalacao_json` | JSON inline | Snapshot momento agendamento; histórico cliente em `contacts.address` |
| `substrato_id` | FK direta | Reutilização alta + busca por substrato comum |
| `current_stage_id` | FK direta (FSM canon) | Gateway obrigatório `ExecuteStageActionService` |

---

## 13. Vinculação NFe55 + NFSe56 simultânea ([CASO-PRATICO](../Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md))

### 13.1 Caso prático canônico

> Wagner referência sessão 2026-05-10. Banner R$ [redacted Tier 0] (mercadoria — NFe55) + Instalação R$ [redacted Tier 0] (serviço — NFSe56) = 1 OS = 2 documents.

```
cv_ordens_producao.id = 12345
  └── transaction_id = 99999 (cria 1 Transaction Sells)
       ├── transaction_documents poly:
       │     ├── doc_type=nfe55  · doc_id=789 · value=350.00 · status=authorized (banner)
       │     ├── doc_type=nfse56 · doc_id=44  · value=200.00 · status=authorized (instalação LC 17.06)
       │     └── (opcional) doc_type=mdfe58 · doc_id=12 · value=550.00 (transporte >R$ [redacted Tier 0])
       └── total Transaction = R$ [redacted Tier 0] = total documentado ✓
```

### 13.2 Action FSM `emitir_nfe_e_nfse` 🔒 — side-effect dispatch PARALELO

```php
class EmitirNfeENfseSideEffect implements StageActionSideEffect
{
    public function execute(OrdemProducaoCv $os): void
    {
        DB::transaction(function() use ($os) {
            // Item 1: banner = NFe55
            if ($os->valor_substrato > 0) {
                EmitirNfeJob::dispatch(
                    business_id: $os->business_id,
                    ordem_id: $os->id,
                    item: 'substrato',
                    value: $os->valor_substrato,
                    ncm: $os->substrato->ncm,
                    cfop: $os->substrato->cfop_padrao,
                    csosn: $os->substrato->csosn_padrao,
                )->onQueue('fiscal');
            }
            // Item 2: instalação = NFSe56 (modelo nacional NT 2024-001)
            if ($os->valor_instalacao > 0) {
                EmitirNfseJob::dispatch(
                    business_id: $os->business_id,
                    ordem_id: $os->id,
                    value: $os->valor_instalacao,
                    item_lc: '17.06',           // Publicidade
                    iss_municipio: $os->endereco_instalacao->municipio_ibge,
                )->onQueue('fiscal');
            }
        });
    }
}
```

**Falha 1 NÃO bloqueia o outro** — retry exponencial independente 24h.

### 13.3 UI tela `/comvis/ordens/{id}` card "Documentos Fiscais"

```
┌─ Documentos Fiscais (2) ────────────────────────────┐
│ ✅ NFe 55  nº 789      R$ [redacted Tier 0]   Banner          │
│ ✅ NFSe 56 nº 44       R$ [redacted Tier 0]   Instalação      │
│                                                     │
│ Total documentado: R$ [redacted Tier 0] = total OS ✓          │
└─────────────────────────────────────────────────────┘
```

### 13.4 Wedge competitivo

| Concorrente | Comportamento | Problema |
|---|---|---|
| **Mubisys/Zênite/Calcgraf** | 2 vendas SEPARADAS | Cadastro duplo + financeiro duplo + estoque descasado |
| **Bling/Omie horizontal** | Suporte NFSe parcial (emissor municipal direto) | Vai parar com adesão obrigatória NT 2024-001 |
| **oimpresso** | 1 OS → 1 Transaction → 2 documents | Cadastro único, financeiro unificado, FSM canon |

---

## 14. Comissão multi-vendedor/instalador via `commission_distribution_json`

### 14.1 Cenário Gold/Extreme típico

- **Vendedor calcula+aprova:** 5% sobre total OS
- **Designer faz arte:** R$ [redacted Tier 0] fixo por OS
- **Instalador externo:** 30% sobre `valor_instalacao` apenas

### 14.2 Schema JSON `cv_ordens_producao.commission_distribution_json`

```json
[
  {"user_id": 12, "papel": "vendedor",   "tipo": "pct_total",         "valor": 5.0,  "calculado_brl": 27.50},
  {"user_id": 19, "papel": "designer",   "tipo": "fixo",              "valor": 50.0, "calculado_brl": 50.00},
  {"user_id": 7,  "papel": "instalador", "tipo": "pct_instalacao",    "valor": 30.0, "calculado_brl": 60.00}
]
```

Tipos suportados: `pct_total`, `pct_subtotal`, `pct_instalacao`, `pct_acabamento`, `fixo`, `por_m2`.

### 14.3 Trigger comissão

Action FSM `concluir_instalacao` (default) ou `marcar_pago` (override per-business `business.comvis_settings.comissao_sobre = 'recebido'`) dispatcha:

```php
CalcularComissaoOsJob::dispatch(
    business_id: $os->business_id,
    ordem_id: $os->id,
);
```

Job lê `commission_distribution_json`, calcula valores, cria lançamentos `comissao_pendente` em `Modules/Financeiro`. Audit log preservado.

### 14.4 Limitações conhecidas (V1)

- ❌ Sem FK validation no JSON — `user_id` inválido detectado só no Job. Mitigação: Pest test guard.
- ❌ Sem analytics agregadas DB-side ("top vendedores trimestre") — Service lê via JSON_EXTRACT (MySQL 8+ ok). Promover pra `cv_commission_lines` quando gráfica >100 OS/m.

---

## 15. User stories adicionais — US-COMVIS-NEW-NNN

> Complementam as 18 US base (§3). Recalibradas ADR 0106 (fator 10x IA-pair).

### US-COMVIS-NEW-001 · Cadastrar processo FSM "OS Comunicação Visual" per-business — **P0**

> **Owner:** — (aguarda atribuição) · **Estimate:** 4h IA-pair · **Status:** todo · **Blocked_by:** ADR proposal accepted + scaffold módulo (Fase 1)

**Como** dono de gráfica novo onboarding
**Quero** que ao instalar Modules/ComunicacaoVisual no meu business, o processo FSM "OS Comunicação Visual" (13 stages + 6 actions críticas + 10 roles) seja cadastrado automaticamente
**Para** começar a usar o pipeline sem configuração manual

**Acceptance:**
- [ ] Seeder `FsmProcessoOsComvisSeeder` cadastra processo per-business no install do módulo
- [ ] Roles Spatie suffix `#{biz}`: `comvis.designer#{biz}`, `comvis.operador#{biz}`, `comvis.instalador#{biz}`, `comvis.gerente#{biz}`, `comvis.financeiro#{biz}`
- [ ] Stages opcionais (`aguardando_maquina`) cadastrados mas `is_active=false` default (gráfica industrial liga via admin)
- [ ] Pest test: instala módulo em biz=99 (cross-tenant test conforme `feedback_test_biz_99_cross_tenant_convention.md`) → 13 stages + 6 actions cadastrados + roles per-business

### US-COMVIS-NEW-002 · Sub-feature PCP gráfico industrial (Extreme `aguardando_maquina`) — **P1**

> **Owner:** — · **Estimate:** 8h · **Status:** todo · **Blocked_by:** US-COMVIS-NEW-001 + sinal qualificado Extreme piloto

**Como** PCP de gráfica industrial (Extreme)
**Quero** habilitar stage `aguardando_maquina` no fluxo FSM
**Para** rastrear OS aguardando máquina específica (Roland/Mimaki) ocupada

**Acceptance:**
- [ ] UI admin permite gráfica habilitar/desabilitar stages opcionais via toggle
- [ ] `business.comvis_settings.stages_opcionais_ativos = ['aguardando_maquina']` JSON config
- [ ] Quando ativo: action `iniciar_impressao` exige `maquina_id` no payload; sem máquina disponível → stage `aguardando_maquina`
- [ ] Liberação máquina dispatcha event → tenta avançar stage seguinte automaticamente (com confirmação humana)
- [ ] Pest: smoke biz Extreme piloto end-to-end

### US-COMVIS-NEW-003 · Action FSM `emitir_nfe_e_nfse` paralelo dual-doc — **P0**

> **Owner:** — · **Estimate:** 6h · **Status:** todo · **Blocked_by:** US-COMVIS-NEW-001 + Modules/NfeBrasil já entregue + US-SELL-014 transaction_documents poly entregue

**Como** financeiro de gráfica
**Quero** que ao concluir instalação + clicar "Emitir fiscal", AMBOS NFe55 (banner) E NFSe56 (instalação) sejam emitidos em paralelo
**Para** não emitir manualmente cada documento + 1 cadastro de OS

**Acceptance:**
- [ ] Action FSM `emitir_nfe_e_nfse` (🔒 gerente+financeiro role) dispatch `EmitirNfeJob` + `EmitirNfseJob` paralelo
- [ ] Falha 1 não bloqueia outro (retry exponencial 24h independente)
- [ ] Card UI "Documentos Fiscais" mostra status independente de cada doc
- [ ] Pest: caso prático banner R$ [redacted Tier 0] + instalação R$ [redacted Tier 0] → 2 documents criados em transaction_documents poly
- [ ] Smoke biz=gold real (após cutover) — emissão real SEFAZ + prefeitura

### US-COMVIS-NEW-004 · Workflow arte aprovação via WhatsApp link token — **P1**

> **Owner:** — · **Estimate:** 8h · **Status:** todo · **Blocked_by:** US-COMVIS-NEW-001 + ADR 0117 multi-números entregue + LGPD consent contacts

**Como** designer de gráfica
**Quero** enviar arte (preview imagem) pelo WhatsApp pro cliente final, ele clicar link → ver preview → aprovar/rejeitar em 1 clique
**Para** reduzir ciclo aprovação de 2 dias pra 4h

**Acceptance:**
- [ ] Action `enviar_para_aprovacao_arte` dispatcha `NotificarClienteAprovacaoArteJob` (WhatsApp via número arte_id business)
- [ ] Mensagem contém link assinado curta-validade `/b/{slug}/arte-aprovacao/{token}` (7d, Laravel signed URL)
- [ ] Página pública (sem auth) renderiza preview + 2 botões (Aprovar / Solicitar alteração)
- [ ] Aprovar → dispatcha action FSM `aprovar_arte` em nome de `system_user` + log audit
- [ ] Solicitar alteração → action `rejeitar_arte` + campo motivo livre (notifica designer)
- [ ] LGPD: `contact.whatsapp_consent === true` antes de dispatch; fallback email se consent
- [ ] Sem nenhum canal → log warning + UI alerta "Cliente sem canal — contate manualmente"
- [ ] Pest: token expirado → 410; token válido após aprovação → 410 (one-time use)

### US-COMVIS-NEW-005 · Wizard onboarding CNAE 1813 via Jana detecta — **P2**

> **Owner:** — · **Estimate:** 3h · **Status:** todo · **Blocked_by:** Modules/Jana + Modules/NfeBrasil seed tributária

**Como** dono novo onboarding (criando business novo)
**Quero** que Jana detecte CNAE 1813-0/01 no cadastro do business e pré-popule NCMs/CFOPs/CSOSN
**Para** não precisar contador configurar 80 produtos

**Acceptance:**
- [ ] Hook em `BusinessCreated` listener: se `cnae_principal` começa com `1813`, ativa wizard ComVis
- [ ] Wizard cria 12 substratos padrão (lona 440g, lona 510g blackout, vinil adesivo, vinil perfurado, ACM 3mm, tela mesh, etc.) + 6 acabamentos (corte reto, corte vinco, ilhós, costura, perfuração, aplicação adesivo) + 3 instalações (fachada simples, fachada com escadote, fachada com andaime NR-35)
- [ ] Preço sugestão baseado em média mercado SC/SP (configurável)
- [ ] Wagner aprovou cada item via 1 clique
- [ ] Pest: business novo CNAE 1813 → wizard ativado + 21 itens cadastrados

### US-COMVIS-NEW-006 · Mapeamento Delphi `VENDA.SITUACAO` Gold → stage FSM — **P0**

> **Owner:** — · **Estimate:** 6h · **Status:** todo · **Blocked_by:** US-COMVIS-NEW-001 + US-COMVIS-017 importer

**Como** engenheiro de migração
**Quero** mapear os 7 estados Gold textuais (`VENDA.SITUACAO`) pros stages CV-específicos
**Para** importar 29k vendas EM PRODUÇÃO + 7k FINALIZADA sem perder contexto

**Acceptance:**
- [ ] Map table dry-run: cada distinct value de `Cliente_09FEB1.VENDA.SITUACAO` mapeia pra 1 stage CV
- [ ] Validação Wagner cada mapeamento (manual approval gate)
- [ ] Bridge `cv_ordens_producao_legacy_map` com `business_id` global scope (Pattern 02)
- [ ] Importer preserva `created_at` legacy + adiciona `imported_at`
- [ ] Pest: dry-run em copy local Firebird Gold → 29k+7k stages atribuídos sem erro

### US-COMVIS-NEW-007 · Pós-cálculo ConsumirEstoque + AlertaMargem — **P1**

> **Owner:** — · **Estimate:** 10h · **Status:** todo · **Blocked_by:** US-COMVIS-005 + US-COMVIS-NEW-001 + apontamento US-COMVIS-004

**Como** dono de gráfica
**Quero** que ao concluir impressão, sistema calcule margem real (orçado vs realizado m² consumido + tinta + tempo etapa) e alerte se negativa
**Para** descobrir OS sangrando + ajustar tabela

**Acceptance:**
- [ ] Side-effect `ConsumirEstoque` registra `cv_apontamentos_consumo` snapshot
- [ ] Service `PosCalculoService::calcular($ordem)` retorna DTO com {orçado_brl, realizado_brl, margem_pct}
- [ ] Margem < orçado em >5pp → Event `MargemNegativaDetectada` → notificação passiva no Jana brief
- [ ] Action FSM `concluir_impressao` 🔒 chama `PosCalculoService::calcular`
- [ ] UI tela OS mostra card "Pós-cálculo" com breakdown auditável
- [ ] Pest: caso banner orçado 22% margem, realizado 15% → alerta gerado

### US-COMVIS-NEW-008 · Driver NFSe Floripa SC (ABRASF v2.04 SOAP) — **P1**

> **Owner:** — · **Estimate:** 14h · **Status:** todo · **Blocked_by:** Modules/NfeBrasil NFSe framework (PR #653 ADR 0143) + cert A1 sandbox Floripa + cliente piloto SC

**Como** financeiro de gráfica SC
**Quero** emitir NFSe modelo 56 automática pra Florianópolis
**Para** cumprir LC 116/2003 + adesão NT 2024-001

**Acceptance:**
- [ ] Implementa interface `NfseDriver` em `Modules/NfeBrasil/Services/NfseDrivers/NfseDriverFloripa.php`
- [ ] SOAP request ABRASF v2.04 → endpoint sandbox Floripa
- [ ] Retry exponencial 24h se SEFAZ/prefeitura down
- [ ] Cert A1 lido de `Modules/SRS`
- [ ] Pest: mock SOAP success path + 3 error paths (cert vencido, payload inválido, rejeitada)
- [ ] Smoke biz=gold real (se SC) ou biz=99 sandbox

### US-COMVIS-NEW-009 · UI Inertia Cockpit Pattern V2 — **P0**

> **Owner:** — · **Estimate:** 12h · **Status:** todo · **Blocked_by:** US-COMVIS-NEW-001 + ADR 0110 Cockpit V2 + Repair KanbanBoard extraível

**Como** designer/atendente
**Quero** UI Inertia/React Cockpit Pattern V2 pra orçamento + listagem OS + drawer detail
**Para** consistente com Sells/Repair/Vestuario

**Acceptance:**
- [ ] `/comvis/orcamento/calcular` — form mobile-first + preview tempo real
- [ ] `/comvis/ordens` — Listagem com filtros (stage, cliente, vendedor) + DataTables ou React Table
- [ ] `/comvis/ordens/{id}` — drawer SaleSheet-style com pipeline FSM panel + timeline histórico + documents fiscais card
- [ ] Kanban PCP em `/comvis/pcp` — reusa componente `<KanbanBoard>` extraído pra `Components/shared/`
- [ ] Charter `.charter.md` ao lado de cada Page
- [ ] Tipografia + cores semânticas ADR 0110 (rose/emerald/amber/blue)
- [ ] Mobile-first apontamento (US-COMVIS-004)
- [ ] Pest browser MCP smoke biz=gold

### US-COMVIS-NEW-010 · Charter de cada Page Inertia — **P1** (governance gate)

> **Owner:** — · **Estimate:** 4h · **Status:** todo · **Blocked_by:** US-COMVIS-NEW-009

**Como** governance (S4+ charter-first Tier A)
**Quero** charter `.charter.md` ao lado de cada Page Inertia
**Para** garantir mission + non-goals + ux targets explícitos antes de Edit/Write

**Acceptance:**
- [ ] `OrcamentoCalculator.charter.md` ao lado de `OrcamentoCalculator.tsx`
- [ ] `OrdensProducaoIndex.charter.md` ao lado de `OrdensProducaoIndex.tsx`
- [ ] `OrdemProducaoShow.charter.md` (drawer) ao lado
- [ ] `PcpKanban.charter.md` ao lado
- [ ] Cada charter: mission 1 frase + goals 3 itens + non-goals 5+ itens + anti-hooks 5+ itens
- [ ] Validado via skill `charter-write` se disponível

### US-COMVIS-NEW-011 · Permission UI Spatie granular per-action FSM — **P0** (governance)

> **Owner:** — · **Estimate:** 4h · **Status:** todo · **Blocked_by:** US-COMVIS-NEW-001

**Como** gerente RBAC
**Quero** atribuir roles Spatie (`comvis.designer#{biz}`, etc.) per-user via UI
**Para** controlar quem executa cada action FSM (designer aprova arte, financeiro emite fiscal, instalador conclui instalação)

**Acceptance:**
- [ ] UI admin `/admin/roles` lista roles ComVis per-business
- [ ] Atribuição user → role visível em `/admin/users/{id}/edit`
- [ ] Action FSM sem role compatível → 403 com mensagem clara
- [ ] Pest cross-tenant: user com role `comvis.designer#1` NÃO consegue aprovar arte em biz=2 (403)

### US-COMVIS-NEW-012 · Pest GUARD Tier 0 anti-hooks charter — **P0** (governance)

> **Owner:** — · **Estimate:** 6h · **Status:** todo · **Blocked_by:** US-COMVIS-NEW-001

**Como** governance Tier 0
**Quero** Pest test guard pra cada anti-hook listado em `ComunicacaoVisual.charter.md` §7
**Para** detectar regressão de Tier 0 imediatamente

**Acceptance:**
- [ ] Test: NUNCA disparar plotter auto (test `iniciar_impressao` sem role → falha)
- [ ] Test: NUNCA recalcular m² pós-NFe (try update `area_m2` com NFe emitida → exception)
- [ ] Test: NUNCA emitir fiscal auto (test `emitir_nfe_e_nfse` sem role gerente → 403)
- [ ] Test: NUNCA cancelar OS com NFe autorizada sem fluxo (test ordem com NFe authorized + `cancelar_os` simples → exige fluxo)
- [ ] Test: NUNCA escrever em outro business_id (test biz=1 user tentando criar ordem biz=2 → 404)
- [ ] Test: NUNCA aplicar reajuste bulk >5% sem confirmação humana (test `bulk_update_substratos` 10% → REQUIRE_HUMAN_REVIEW)

### US-COMVIS-NEW-013 · Comissão multi-papel JSON Job — **P1**

> **Owner:** — · **Estimate:** 5h · **Status:** todo · **Blocked_by:** US-COMVIS-NEW-001 + Modules/Financeiro lançamento comissão

**Como** financeiro
**Quero** que ao concluir instalação (ou marcar pago), comissões dos papéis (vendedor + designer + instalador) sejam calculadas e lançadas
**Para** pagar correto na folha sem planilha paralela

**Acceptance:**
- [ ] `CalcularComissaoOsJob` lê `commission_distribution_json`
- [ ] Cria lançamentos `comissao_pendente` em `Modules/Financeiro` por papel
- [ ] Audit log com user, role, valor calculado, base, fórmula
- [ ] Override per-business: `business.comvis_settings.comissao_sobre = 'faturado'|'recebido'`
- [ ] Pest: caso 3 papéis (vendedor 5% + designer R$ [redacted Tier 0] + instalador 30% instalação) → 3 lançamentos R$ [redacted Tier 0] + R$ [redacted Tier 0] + R$ [redacted Tier 0]

### US-COMVIS-NEW-014 · Snapshot financeiro pré-venda 6 saudáveis batch — **P0** (sales)

> **Owner:** Wagner [W] · **Estimate:** 6h wallclock (1h × 6 clientes via skill) · **Status:** todo · **Blocked_by:** skill `officeimpresso-financial-snapshot` (Tier B Bash)

**Como** vendedor (Wagner)
**Quero** rodar snapshot financeiro nos 5 candidatos (Extreme/Zoom/Fixar/Mhundo/Produart — Vargas removido)
**Para** apresentar receita real do cliente extraída do .FDB na call de venda + decidir ordem prioridade

**Acceptance:**
- [ ] Snapshot batch via skill `officeimpresso-financial-snapshot`
- [ ] Cada cliente: receita 12m, despesa 12m, MRR, ticket médio, top 30 clientes, inadimplência
- [ ] Arquivo em `memory/research/clientes-legacy-officeimpresso/NN-<slug>/03-financeiro-<data>.md`
- [ ] Anonimização sha1 PIIs (LGPD)
- [ ] Wagner valida identidade Gold (registry vs Mubisys post-mortem)

### US-COMVIS-NEW-015 · Componente `<KanbanBoard>` extraído pra Components/shared — **P0** (reuso)

> **Owner:** — · **Estimate:** 6h · **Status:** todo · **Blocked_by:** investigar acoplamento atual Modules/Repair

**Como** dev frontend
**Quero** extrair componente Kanban drag-drop de Modules/Repair pra `Components/shared/KanbanBoard.tsx`
**Para** reuso em CV (US-COMVIS-003 PCP gráfico) sem duplicar código

**Acceptance:**
- [ ] Componente puro `<KanbanBoard items={...} columns={...} onMove={...} renderCard={...} />`
- [ ] Modules/Repair migra import sem regressão
- [ ] Modules/ComunicacaoVisual usa mesmo componente com renderCard CV-específico
- [ ] Pest browser MCP smoke ambos módulos
- [ ] Storybook entry pra componente (opcional)

---

## 16. Total US recalibrado (base 18 + novas 15 = 33 US)

| Prioridade | US | Total esforço (h IA-pair) |
|---|---|--:|
| **P0** | COMVIS-001, 002, 003, 006, 009, 017 + NEW-001, NEW-003, NEW-006, NEW-009, NEW-011, NEW-012, NEW-014, NEW-015 | ~110h |
| **P1** | COMVIS-004, 005, 007, 008, 011 + NEW-002, NEW-004, NEW-007, NEW-008, NEW-010, NEW-013 | ~95h |
| **P2** | COMVIS-010, 012, 013, 014, 015 + NEW-005 | ~50h |
| **P3** | COMVIS-016, 018 | ~22h |
| **Total** | **33 US** | **~277h IA-pair** |

Recalibrado ADR 0106 fator 10x — tarefas codáveis. Tarefas humano-limitadas (treinamento, canary, monitor) mantém wallclock (ver ROADMAP.md).

---

## Anexo — links canônicos

- [MATRIZ-ROI.md](MATRIZ-ROI.md) — 24 features × ROI score + esforço + concorrentes
- [ROADMAP.md](ROADMAP.md) — 5 fases com gate de sinal qualificado
- [ComunicacaoVisual.charter.md](ComunicacaoVisual.charter.md) — charter módulo
- [PLANO-MIGRACAO-6-SAUDAVEIS.md](PLANO-MIGRACAO-6-SAUDAVEIS.md) — plano migração (Vargas removido)
- [proposal ADR `comunicacao-visual-modulo-canonico`](../../decisions/proposals/drafts/comunicacao-visual-modulo-canonico.md) — 7 decisões arquiteturais
- [CASO-PRATICO-OS-COMUNICACAO-VISUAL](../Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md) — dual-doc fiscal NFe55 + NFSe56
- [04-gold-comvis/01-perfil.md](../../research/clientes-legacy-officeimpresso/04-gold-comvis/01-perfil.md) — piloto Gold qualificado
- [_ANALISE-CROSS-CLIENTE.md](../../research/clientes-legacy-officeimpresso/_ANALISE-CROSS-CLIENTE.md) — Gold/Extreme padrões cross-cliente
- [_LICOES-CRITICAS.md](../../research/clientes-legacy-officeimpresso/_LICOES-CRITICAS.md) — anti-bugs Delphi→Laravel
- [ADR 0143 FSM Pipeline canônico LIVE](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0121 — modular especializado por vertical](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- [Comparativo Capterra/G2 oimpresso vs concorrentes (2026-04-25)](../../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md)
- [Research Zênite + Mubisys (2026-05-09)](../../research/2026-05-prospeccao/02-concorrentes-zenite-mubisys.md)
- [Research Alfa+Visua+Calcgraf+Calcme + reviews setor (2026-05-09)](../../research/2026-05-prospeccao/03-concorrentes-alfa-visua-calcgraf-reviews.md)
- [RUNBOOK criar módulo](../Infra/RUNBOOK-criar-modulo.md)
- [Modules/NfeBrasil SPEC](../NfeBrasil/SPEC.md) (reuso fiscal)
- [Modules/Jana](../../../Modules/Jana) (reuso IA)
- [Modules/Repair](../../../Modules/Repair) (reuso Kanban PCP — shared infra)
