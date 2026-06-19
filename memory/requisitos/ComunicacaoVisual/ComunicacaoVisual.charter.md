---
module: ComunicacaoVisual
charter_type: module
status: proposto
piloto: a confirmar Q3/26 entre 6 saudáveis OfficeImpresso (Vargas / Extreme / Gold / Zoom / Fixar / Mhundo / Produart)
piloto_previsao: 2026-Q3
last_review: 2026-05-10
owner: wagner
parent_adr: 0121
related_adrs: [0011, 0024, 0035, 0093, 0094, 0105, 0119, 0121]
tier: A
charter_version: 1
---

# Module Charter — Modules/ComunicacaoVisual

> **Status proposto:** módulo NÃO existe em código. Esta charter é o contrato de produto que guia a construção em paralelo à [SPEC.md](SPEC.md), seguindo o template canônico estabelecido em [Vestuario.charter.md](../Vestuario/Vestuario.charter.md). Piloto previsto: 1 dos 6 saudáveis OfficeImpresso migrado em Q3/26 via [Migration Factory ADR 0119](../../decisions/0119-migration-factory.md).
>
> Charter de **módulo inteiro** (não de página). Diferente de `*.charter.md` ao lado de `.tsx` (que é tela). Aqui o objeto governado é o módulo vertical inteiro do oimpresso conforme [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md).

---

## 1. Mission (1 frase)

Add-on vertical de comunicação visual / gráfica rápida sobre o núcleo oimpresso — entrega cálculo m² + PCP gráfico multi-etapa + apontamento de plotter + NFe-de-boleto-pago + acompanhamento de OS pra dono-gráfica BR pequeno-médio (3-25 funcionários, R$ [redacted Tier 0]k-8M GMV) substituindo Mubisys/Zênite/Calcgraf/OfficeImpresso Delphi legacy.

---

## 2. Goals — objetivos de produto mensuráveis

| # | Goal | Métrica |
|---|---|---|
| G1 | **Cálculo orçamento m² p95 < 800ms** server-side (largura × altura × material × acabamentos + extras) | observado em `POST /comvis/orcamento/calcular`, baseline 1ª piloto migrada |
| G2 | **Throughput plotter mensurável** — m²/hora por máquina, custo CMYK/m² visível em dashboard | apontamento US-COMVIS-004 alimenta `comvis_apontamentos` em ≥80% das OS produtivas |
| G3 | **PCP gráfico OS multi-etapa** (Design → Prepress → Impressão → Acabamento → Instalação → Entrega) sem OS perdida | 0 OS órfãs > 7d; tempo médio etapa visível por gráfica |
| G4 | **NFe automática a partir de boleto pago** (reuso US-RB-044) — ≥90% dos boletos B2B disparam NFe sem clique humano | `payment_received → NFCeAutorizada` rate em `mcp_briefs` |
| G5 | **Pós-cálculo orçado vs realizado** visível em ≤24h após fechar OS — gráfica enxerga margem real | % OS com `pos_calculo` preenchido na piloto migrada (meta M4 ≥80%) |

---

## 3. Non-Goals — o que **NÃO** é responsabilidade do módulo

> Cada item evita escopo gourmet. Onde o trabalho realmente vive está apontado.

- ❌ **Emissão fiscal NFC-e/NFe/NFSe/CT-e** → vive em `Modules/NfeBrasil` + `Modules/NFSe` (a criar) — ComunicacaoVisual consome eventos
- ❌ **Visão financeira AR/AP unificada / DRE** → vive em `Modules/Financeiro`
- ❌ **Boleto/assinatura/cobrança recorrente** → vive em `Modules/RecurringBilling`
- ❌ **Multi-tenant `business_id` global scope** → infraestrutura núcleo Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- ❌ **Jana IA / memória persistente** → vive em `Modules/Jana`
- ❌ **Cofre de senhas/credenciais (cert digital A1)** → vive em `Modules/MemCofre`
- ❌ **Estoque por SKU+tamanho+cor / etiqueta de preço de balcão** — isso é de `Modules/Vestuario`
- ❌ **Kanban genérico drag-drop** — `Modules/Repair` é shared infra ([ADR 0121 §P8](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)); ComunicacaoVisual consome com vocabulário gráfico (etapa/OS, não placa/veículo)
- ❌ **DAM/MubiDrive own-built** — UI de catálogo de arquivos é P2 (US-COMVIS-012); storage S3-compatible (Wasabi/Minio CT 100), não own-built de cara
- ❌ **App mobile nativo** — adiável 12m via Inertia/React responsive + PWA (anti-padrão #12 da SPEC)
- ❌ **SPED contábil completo** — fora de escopo; contador externo. SPED fiscal já reusa NfeBrasil
- ❌ **Folha de pagamento / RH** → núcleo UltimatePOS Essentials
- ❌ **E-commerce / marketplace integrações (Mercado Livre etc)** → fora de escopo MVP

---

## 4. Audiência (persona detalhada)

**Trio operacional gráfica rápida BR — não Larissa-tipo.**

### 4.1 Dono-gráfica (decisor + comercial)
- Homem ou mulher 35-55, dono ou sócio operador, gráfica 3-25 funcionários
- Cidade média/grande (capital ou interior estruturado, ≥100k habitantes)
- Vê WhatsApp o dia inteiro; usa sistema pra orçar e fechar venda
- Quer **enxergar margem real por OS / cliente** (dor #4 top 10 PrintPlanet) — tabela de preço cega = sangria silenciosa
- GMV anual R$ [redacted Tier 0]k-8M; Simples Nacional maioria, alguns Lucro Presumido
- Já usa OfficeImpresso Delphi legacy (ou Mubisys/Calcgraf) há 5-15 anos — decora atalhos, vai migrar contrariado se sistema novo "perder velocidade"

### 4.2 Designer / atendente comercial (operador-orçamento)
- 22-40 anos, formação técnica (Senac/IFB/comunicação visual)
- Manuseia Corel/Illustrator + sistema ERP em paralelo
- **Recebe pedido informal por WhatsApp** ("preciso banner 3x1,5 pra sábado") e precisa virar PDF orçamento em < 2min
- Conhecimento de m² + materiais (lona 440g, blackout 510g, vinil, ACM) é dado — UX deve respeitar (não explicar o óbvio)
- Mexe em arquivo print-ready (CMYK, sangria 3mm) — **valoriza preflight automático**

### 4.3 Operador-plotter / instalador (operador-produção)
- 25-50, técnico de máquina (Roland VS-540 / Mimaki JV-150 / HP Latex 365 / Mutoh)
- **Mobile-first** — celular ao lado da plotter pra apontar início/fim/m² impresso/CMYK consumido
- Instalador de fachada vai a campo — precisa de checklist EPI + GPS + foto pré/pós (NR-35 trabalho em altura)
- Não fica olhando dashboard — recebe push (Centrifugo) quando OS dele muda de etapa
- PT-BR exclusivo, vocabulário gráfico (não "ordem de serviço genérica" — é "OS gráfica" com etapa nominal)

### Validação
6 saudáveis OfficeImpresso (Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart) — todos em produção Delphi legacy WR Sistemas com R$ [redacted Tier 0]k-7,9M GMV/ano. Snapshot financeiro pré-venda via skill `officeimpresso-financial-snapshot` antes de abordar.

---

## 5. UX targets

### Heurísticas Nielsen aplicáveis (foco)
- **#1 Visibility of system status** — OS sempre mostra etapa atual + responsável + prazo; orçamento mostra (rascunho/enviado/aprovado/virou OS)
- **#2 Match real world** — vocabulário gráfico (m², gramatura g/m², lona front-light, blackout, ACM, prepress, plotter) — nunca "produto genérico"
- **#3 User control & freedom** — cancelar orçamento/OS em ≤ 2 cliques antes de virar fiscal; depois exige confirmação
- **#5 Error prevention** — confirmação humana **obrigatória** em (a) emissão NFe/NFSe, (b) disparo de plotter, (c) aplicação reajuste bulk material > 5%
- **#7 Flexibility** — atalhos teclado em `/comvis/orcamento/calcular` (F2 buscar material, F4 calcular, F8 enviar WhatsApp)
- **#8 Aesthetic minimalist** — Cockpit V2 ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)): pills `rounded-full`, KPIs no topo (margem real, OS atrasadas, plotter ocioso %), drawer lateral pra detalhe OS

### Targets duros
- **p95 cálculo orçamento m² < 800ms** server-side (G1 desta charter — 47% mais rígido que Vestuario p95 1500ms porque vendedor recalcula 5-10x na call)
- p95 first-paint Cockpit produção < 1500ms
- 0 erros JS console em smoke biz=1 (Wagner WR2 SC) — nunca em piloto real ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- **Mobile-first em apontamento** (US-COMVIS-004) — operador celular ao lado plotter
- Desktop monitor 1280px+ sem scroll horizontal pro Kanban PCP (designer com 2 abas Corel + ERP)
- Tipografia canon ADR 0110 — h1 22-24px / pill 12px / badge 11px
- Cores semânticas Cockpit V2 (rose/emerald/amber/blue), nunca cor crua
- Server-side authoritative em todo cálculo m² (R-COMVIS-001) — nunca confiar em frontend pra preço

---

## 6. Automation hooks (onde Jana IA atua)

> Jana = Modules/Jana. Hooks abaixo são **propostos** — exigem sinal qualificado ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) antes de virar US ativa.

- ✅ **Wizard onboarding CNAE 1813** (US-COMVIS-006) — Jana detecta CNAE da empresa e pré-popula NCM/CFOP/CSOSN sem precisar contador
- ✅ **Bulk update material via chat** (US-COMVIS-013) — "aumenta 5% em todo lona 440g" → preview + confirmação humana + audit log
- ✅ **Pergunta às 22h** (US-COMVIS-014) — dono pergunta "quanto faturei essa semana de banner vs lona" / "qual cliente mais lucrou em abril" / "quais OS atrasaram" — 3 ângulos faturamento ([ADR 0052](../../decisions/0052-faturamento-3-angulos.md))
- ✅ **Alerta margem negativa** — pós-cálculo (US-COMVIS-005) detecta OS fechada com margem < orçado em >5pp → notificação passiva no brief
- ✅ **Alerta cartucho CMYK baixo** (US-COMVIS-015) — Cyan da Roland VS-540 com 15% restante → reposição sugerida
- ✅ **Resumo do dia 18h** — dono recebe no brief: "faturou R$ X, top 3 materiais, 2 OS atrasaram em Acabamento, plotter Roland 78% ocupação"

---

## 7. Anti-hooks (onde Jana **NÃO** deve interferir)

> Tier 0. Onde IA não-confirmada gera dano real ao negócio do cliente.

- ❌ **NUNCA disparar plotter / job de impressão automaticamente** sem confirmação humana — m² de lona desperdiçada custa R$ [redacted Tier 0]-150 por erro, e mídia consumida não volta
- ❌ **NUNCA recalcular m² de orçamento depois que NFe foi emitida** — fiscal congela base de cálculo; recalcular = inconsistência fiscal
- ❌ **NUNCA emitir NFC-e/NFe/NFSe automaticamente** sem confirmação humana — fiscal é irreversível, erro custa multa (mesmo princípio Vestuario)
- ❌ **NUNCA aplicar reajuste bulk material > 5%** automaticamente — virar sugestão preview + humano confirma (US-COMVIS-013 PolicyEngine REQUIRE_HUMAN_REVIEW)
- ❌ **NUNCA marcar OS como "concluída" / mover etapa final** sem ação do operador — disparar NFSe da instalação automática gera nota fiscal de serviço não realizado
- ❌ **NUNCA aceitar orçamento público (US-COMVIS-010) sem rate-limit + captcha** — bot enche CRM
- ❌ **NUNCA enviar WhatsApp/email pro cliente final** sem opt-in explícito por OS (LGPD Art. 7º)
- ❌ **NUNCA alterar `cartuchos_json` (ml restante CMYK)** por algoritmo sem apontamento humano — divergência exige inventário físico (não auto-corrige)
- ❌ **NUNCA cancelar OS com NFe já autorizada** sem fluxo formal de cancelamento fiscal (carta correção / cancelamento dentro do prazo legal)
- ❌ **NUNCA classificar cliente como "inadimplente"** sem fluxo formal (impacto cadastral)
- ❌ **NUNCA escrever em outro `business_id`** (multi-tenant Tier 0 IRREVOGÁVEL)
- ❌ **NUNCA hard-code CNAE 1813 / vocabulário gráfico no núcleo UltimatePOS** — quebra [ADR 0121 §P1](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)

---

## 8. Integrações (módulos do núcleo que este consome)

| Módulo núcleo | Como ComunicacaoVisual consome | Direção |
|---|---|---|
| `Modules/NfeBrasil` | Listener `BoletoPago` → `EmitirNfceJob` (US-COMVIS-009 reuso US-RB-044); CFOP/CSOSN/NCM seed CNAE 1813 (US-COMVIS-006) | consome |
| `Modules/NFSe` (a criar) | NFSe automática pra serviço de instalação (US-COMVIS-008) | consome (dependência futura) |
| `Modules/Financeiro` | AR/AP boletos, DRE simplificado, comissão folha (US-COMVIS-011) | consome |
| `Modules/Jana` (Jana) | Chat contextual + 3 ângulos faturamento + bulk update materiais + brief diário | consome |
| `Modules/RecurringBilling` | Trigger boleto pago → NFe automática (US-COMVIS-009) | consome |
| `Modules/Repair` | Kanban drag-drop multi-etapa (US-COMVIS-003) — Repair é shared infra com override de labels gráfico | consome shared infra |
| `Modules/MemCofre` | Cofre cert digital A1, login fornecedor (Roland/Mimaki SDK futuro), webservice prefeitura NFSe | consome opcional |
| Núcleo UltimatePOS | `business_id`, users, roles, locations, `transactions`, `contacts`, `variations` (custom fields gramatura/categoria) | base |

**Inverso:** Modules/ComunicacaoVisual **não é consumido** por outros módulos verticais — cada vertical é independente (princípio P2 ADR 0121). `Modules/Vestuario` e `Modules/OficinaAuto` (futuro) seguem mesmo formato sem dependência cruzada.

---

## 9. Métricas de sucesso

### Adoção
- **DAU / MAU** ≥ 0.5 (gráfica rápida tem operação 5-6 dias/semana — baseline meta)
- **Retention 12m** ≥ 85% (vertical com network effect: pós-cálculo + IA = sticky)
- **NPS específico comvis** ≥ 45 (medir só clientes ativos no módulo)

### Saúde do módulo
- **Tickets de suporte / cliente ativo / mês** ≤ 4 (curva aprendizado mais íngreme que Vestuario nos primeiros 60d)
- **Bugs críticos abertos > 7d** = 0
- **Cobertura Pest do módulo** ≥ 70% (núcleo) + 100% das regras Non-Goal/Anti-hook (GUARD)
- **% OS com pós-cálculo preenchido** ≥ 80% até M4 (G5)
- **% boletos B2B → NFe automática** ≥ 90% (G4)

### Comercial (review_triggers ADR 0121)
- **2 clientes pagantes em 6m após launch (M3-M9)** — confirma piloto → ativo
- **3-5 clientes pagantes em 12m após launch** — promove pra `ativo`
- **10+ clientes pagantes em 24m** — promove pra `maduro` (network effect benchmark setor — média margem comvis BR visível via Jana)
- **<2 clientes ativos em 12m após launch formal** — candidato a aposentar (`historical`)

### Performance
- **p95 cálculo orçamento m² < 800ms** (G1)
- **p95 Kanban PCP first-paint < 1500ms**
- **p95 apontamento mobile < 2000ms** (rede 4G in-loco)

---

## 10. Lifecycle

Segue lifecycle canon de módulo vertical ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §Lifecycle):

| Estado | Critério | Hoje |
|---|---|---|
| `proposto` | ADR feature-wish, sem código | ✅ **hoje** (esta charter + SPEC.md, Modules/ComunicacaoVisual ainda não existe) |
| `em_construcao` | scaffold criado, P0 USs em desenvolvimento, sem cliente real ainda | meta M1 (jul/26) — `php artisan module:install ComunicacaoVisual` funciona em dev |
| `piloto` | 1 cliente real pagando, código vivendo | meta M3 (set/26) — 1ª piloto migrada (Vargas/Extreme/Gold a confirmar) |
| `ativo` | 3+ clientes pagantes | meta M9-M12 (mar-jun/27) |
| `maduro` | 10+ clientes, benchmark setorial habilitado via Jana | aberto (24m+) |
| `historical` | <2 clientes ativos por 12m → para aceitar novos, mantém legacy | revisar trigger |

**Promoção proposto → em_construcao** exige:
- Module scaffold via `Infra/RUNBOOK-criar-modulo.md` (8 peças + 3 rotas Install)
- DataController com hooks UltimatePOS (`user_permissions`, `modifyAdminMenu`, `superadmin_package`)
- 3 migrations core: `comvis_materiais`, `comvis_orcamentos`, `comvis_os` + scope `business_id`
- Pest GUARD inicial pra Anti-hooks Tier 0 (multi-tenant + recálculo m² pós-NFe + plotter manual)

**Promoção em_construcao → piloto** exige:
- 1 cliente real pagando (não só biz=1 Wagner/SC)
- USs P0 entregues: COMVIS-001, 002, 003, 006, 009 (boleto→NFe adapter), 017 (importer)
- Snapshot financeiro pré-venda do piloto via skill `officeimpresso-financial-snapshot`
- Canary 7d em paralelo OfficeImpresso Delphi legacy

**Promoção piloto → ativo** exige:
- 3+ clientes pagantes (não só primeiro)
- SPEC.md + CAPTERRA-FICHA.md + CAPTERRA-INVENTARIO.md no módulo
- Pest GUARD pra todos Non-Goals + Anti-hooks desta charter
- ARR módulo ≥ R$ [redacted Tier 0]k/ano (baseline ADR 0022)

**Aposentar (ativo → historical)** exige:
- ADR amendment registrando razão
- Comunicação prévia 90d aos clientes ativos (≥99% volume cliente sente impacto — princípio ROTA LIVRE)
- Manter código read-only pros legacy, parar onboarding novo

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-10 | Opus + Wagner | Charter inicial — segunda charter formal de módulo vertical no projeto, derivada do template canônico [Vestuario.charter.md](../Vestuario/Vestuario.charter.md) e da [SPEC.md](SPEC.md) recém-criada. Status `proposto` (módulo não existe em código; piloto previsto Q3/26 via [Migration Factory ADR 0119](../../decisions/0119-migration-factory.md)). Diferenças vs Vestuario: persona trio (dono+designer+operador-plotter, não Larissa-tipo), p95 cálculo m² < 800ms (vs venda balcão 1500ms), anti-hooks específicos (não disparar plotter / não recalcular m² pós-NFe / não auto-mover etapa final), lifecycle `proposto` (vs `piloto` Vestuario que valida no núcleo hoje). Templating limpo pra reuso futuro em `Modules/OficinaAuto.charter.md`. |
