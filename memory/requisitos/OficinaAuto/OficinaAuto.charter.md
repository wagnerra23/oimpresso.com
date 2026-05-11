---
module: OficinaAuto
charter_type: module
status: ativo
lifecycle: em-construcao
piloto: Vargas + Martinho (sinal qualificado em ADR 0137 — 2 de 4 candidatos OfficeImpresso saudáveis)
last_review: 2026-05-11
owner: wagner
parent_adr: 0137
related_adrs: [0011, 0093, 0094, 0105, 0121, 0129, 0137]
tier: A
charter_version: 2
---

# Module Charter — Modules/OficinaAuto

> **Status atualizado 2026-05-11** ([ADR 0137](../../decisions/0137-modules-oficinaauto-qualificada.md)): charter migrou de `proposto/feature-wish` (v1) pra `ativo/em-construcao` (v2). Sinal qualificado materializado: Vargas (recapagem caçamba, 1.064 veículos, multi-placa 20%) + Martinho (caçambas avulsas, 91 veículos, placa única 96%). V0 scaffold em curso — Vehicle + ServiceOrder CRUD multi-tenant Tier 0.
>
> Reusa convenções da [Vestuario.charter.md](../Vestuario/Vestuario.charter.md) (template canônico ADR 0121) e antecipa que a infra de OS será compartilhada com `Modules/Repair` após o refactor Caminho A do audit F29 (extração de `RepairCore` reutilizável).
>
> Charter de **módulo inteiro** (não de página). Diferente de `*.charter.md` ao lado de `.tsx`. Aqui o objeto governado é o módulo vertical inteiro do oimpresso conforme [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md).

---

## 1. Mission (1 frase)

Add-on vertical de oficina mecânica automotiva sobre o núcleo oimpresso — entrega Ordem de Serviço por placa+km, tabela tempária Sindirepa, checklist de diagnóstico padronizado, integração CRLV/DETRAN e comissão por OS pro fluxo realista de oficina independente brasileira.

---

## 2. Goals — objetivos de produto mensuráveis (antecipatórios)

> **Antecipatório:** métricas-alvo só viram baseline quando o piloto pagante começar a operar. Hoje servem como hipótese a validar.

| # | Goal | Métrica (a baselinear no piloto) |
|---|---|---|
| G1 | **OS por placa+km com histórico veicular** — abrir OS rastreia veículo (placa) + km atual + histórico anterior visível em ≤ 1 clique | tempo p95 abertura OS < 90s; 100% OSs com placa + km registrados |
| G2 | **Tabela tempária Sindirepa por serviço** — tempo padrão de mão-de-obra populado da tabela do sindicato local; mecânico não digita hora a mão | % de itens de OS com `tempo_sindirepa` aplicado ≥ 90% |
| G3 | **Checklist diagnóstico digital** ao receber veículo — fotos antes/depois + itens conferidos antes do orçamento | OSs com checklist completo / total OSs ≥ 95% |
| G4 | **Integração CRLV/DETRAN** — consulta multas/situação do veículo no momento de abrir OS (ou batch noturno) | tempo médio de consulta < 3s; cache 24h pra evitar custo API recorrente |
| G5 | **Comissão automática por OS** — mecânico/auxiliar recebe % automática sobre mão-de-obra fechada; relatório mensal pronto | divergência relatório vs holerite ≤ 1% / mês |

---

## 3. Non-Goals — o que **NÃO** é responsabilidade do módulo

> Cada item evita escopo gourmet. Onde o trabalho realmente vive está apontado.

- ❌ **Emissão fiscal NFC-e/NFSe** → vive em `Modules/NfeBrasil` (módulo consome o evento `NFCeAutorizada`/`NFSeAutorizada`)
- ❌ **Visão financeira AR/AP unificada** → vive em `Modules/Financeiro`
- ❌ **Boleto/assinatura/cobrança recorrente** (planos de revisão preventiva) → vive em `Modules/RecurringBilling`
- ❌ **Multi-tenant `business_id` global scope** → infraestrutura núcleo Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- ❌ **Jana IA / memória persistente** → vive em `Modules/Copiloto`
- ❌ **OS de reparo genérico** (eletrônico, costureira, vestuário, etc) → vive em `Modules/Repair`. OficinaAuto **consome `RepairCore`** quando o refactor Caminho A do audit F29 entregar. Hoje, sem refactor, isto é bloqueador
- ❌ **PCP de produção gráfica/têxtil** — fora do escopo automotivo
- ❌ **E-commerce de peças / marketplace integration** (Mercado Livre Auto, etc) → fora do MVP
- ❌ **Folha de pagamento** dos mecânicos → núcleo UltimatePOS Essentials (comissão é cálculo, não folha)
- ❌ **Telemetria veicular real-time** (OBD-II, IoT) — fora do MVP; integração via Connector futuramente
- ❌ **Agendamento online externo** (cliente final agenda pela web) — feature pós-MVP

---

## 4. Audiência (persona detalhada)

**Três personas distintas — diferente do Vestuario que tem 1 (Larissa).**

### 4.1 Dono da oficina (decisor)
- Homem, 35-60 anos, dono de oficina mecânica independente em cidade pequena/média
- **Não opera o sistema o dia inteiro** — entra de manhã, fim de tarde, e em casos críticos (orçamento alto, cliente VIP)
- Decide preço/desconto/política de garantia
- Aprova orçamentos acima de teto configurável
- Quer ver: faturamento do dia, OSs em aberto, contas a receber, comissão a pagar
- Monitor desktop balcão (1280-1920px)
- PT-BR exclusivo

### 4.2 Mecânico chão de oficina (executor)
- Homem, 25-55 anos, ensino médio (variável)
- **Mãos sujas de graxa** — usa tablet rugged ou celular Android
- Abre OS, registra checklist, sobe foto antes/depois, fecha OS quando terminar
- **Não digita textão** — prefere selecionar de lista, fotografar, gravar áudio
- Comissão é motivador direto — quer ver o que vai receber em tempo real
- Touch-first; 1 mão livre frequentemente

### 4.3 Recepcionista (atendimento + caixa)
- Mulher 22-45 anos comum em oficinas estabelecidas; em pequenas, papel acumulado pelo dono ou cônjuge
- Recebe cliente, registra placa+km, comunica orçamento, recebe pagamento
- **Multi-tarefa** — atende telefone enquanto digita; precisa atalhos
- Monitor balcão (1280px típico — quirk Larissa-tipo aplica)
- Trabalha 8h direto, decora layouts ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md) aplicável: zero regressão visual percebida)

Validação: **NENHUM CLIENTE PILOTO** (charter antecipatório). Personas baseadas em pesquisa setorial Capterra + cross-com Modules/Repair (que atende oficinas mistas hoje, sem otimização auto-específica).

---

## 5. UX targets

### Heurísticas Nielsen aplicáveis (foco)
- **#1 Visibility of system status** — OS sempre mostra estado (aguardando aprovação / em execução / aguardando peça / fechada)
- **#2 Match real world** — usar léxico de oficina ("revisão", "alinhamento", "balanceamento", "freio dianteiro") não termos técnicos genéricos
- **#3 User control & freedom** — cancelar OS / re-abrir OS fechada (com auditoria) sem trap modal
- **#5 Error prevention** — confirmação dupla pra ações de impacto cliente (cobrar antes de aprovação, fechar OS sem teste-drive)
- **#7 Flexibility** — atalhos teclado balcão + interface touch otimizada chão de oficina
- **#8 Aesthetic minimalist** — Cockpit V2 ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)): pills, KPIs no topo, drawer detalhe

### Targets duros
- p95 first-paint < 1500ms (admin) / < 800ms (Cockpit dashboard)
- 0 erros JS console em smoke biz=1 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- **Monitor 1280px sem scroll horizontal** em todos os fluxos balcão/recepção
- **Mobile-first nas telas chão de oficina** (≥360px) — mecânico no tablet/celular
- Tipografia canon ADR 0110
- Cores semânticas Cockpit V2

---

## 6. Automation hooks (onde Jana IA atua)

> Jana = Modules/Copiloto. Hooks **propostos** — exigem sinal qualificado antes de virar US ativa.

- ✅ **Sugestão de tempária Sindirepa** — ao adicionar serviço na OS, Jana propõe tempo do sindicato; mecânico aceita ou ajusta
- ✅ **Alerta peça em estoque baixo** — quando mecânico vai usar peça e estoque já acabou, Jana avisa antes de fechar OS
- ✅ **Resumo do dia 18h pro dono** — "X OSs abertas, Y fechadas, R$ Z faturado, T comissão a pagar, U aguardando peça >7d"
- ✅ **Detecção de OS estagnada** — OS aberta há >5 dias sem progresso → alerta passivo brief diário
- ✅ **Sugestão revisão preventiva** — cliente com km próximo do limite (10k, 20k, etc) → recomenda contato (cliente humano decide)
- ✅ **Reconhecimento OCR de CRLV** — foto do documento extrai placa/chassi/RENAVAM/proprietário (consulta DETRAN só se cliente autorizar)

---

## 7. Anti-hooks (onde Jana **NÃO** deve interferir)

> Tier 0. Onde IA não-confirmada gera dano real ao negócio do cliente.

- ❌ **NUNCA emitir NFe/NFSe automaticamente sem aprovação do dono** — fiscal é irreversível, erro custa multa **(Tier 0 auto-específico — fiscal automotivo tem ICMS-ST/CFOPs específicos que erram fácil)**
- ❌ **NUNCA fechar OS sem registro de teste-drive (ou justificativa explícita)** — entregar veículo com defeito remanescente é responsabilidade civil; teste-drive é proteção legal **(Tier 0 auto-específico — risco de acidente pós-entrega)**
- ❌ **NUNCA cobrar cliente antes de aprovação formal do orçamento** — Código de Defesa do Consumidor Art. 39 III veda serviço não solicitado; cobrar peça/serviço sem OK escrito é processo certo **(Tier 0 auto-específico — CDC Art. 39 III)**
- ❌ **NUNCA aplicar desconto > 5% sem aprovação humana** — virar sugestão, dono aprova
- ❌ **NUNCA mexer em data retroativa de OS digitada manualmente** — workflow legítimo (recepção registra OS antiga em lote)
- ❌ **NUNCA reordenar/esconder colunas decoradas** (recepcionista decora layout)
- ❌ **NUNCA enviar SMS/WhatsApp pro cliente final sem opt-in explícito por OS** (LGPD Art. 7º)
- ❌ **NUNCA classificar cliente como "inadimplente"** sem fluxo formal (impacto cadastral)
- ❌ **NUNCA acessar API DETRAN/CRLV sem opt-in do cliente** — dado pessoal sensível (LGPD Art. 5º II)
- ❌ **NUNCA escrever em outro `business_id`** (multi-tenant Tier 0 IRREVOGÁVEL)

---

## 8. Integrações (módulos do núcleo que este consome)

| Módulo núcleo | Como OficinaAuto consome | Direção |
|---|---|---|
| `Modules/Repair` (via `RepairCore` extraído) | Reusa OS base, status workflow, attachments, eventos. **Bloqueador:** depende do refactor Caminho A audit F29 (extração `RepairCore` shared). Pré-refactor, não inicia código. | consome (após refactor) |
| `Modules/NfeBrasil` | Listener `NFSeAutorizada` (serviço) + `NFCeAutorizada` (venda de peça); pipeline TransactionBuilder | consome |
| `Modules/Financeiro` | Visão unificada AR/AP de OSs; DRE simplificado | consome |
| `Modules/Copiloto` (Jana) | Chat contextual + alertas + brief diário | consome |
| `Modules/RecurringBilling` | Plano mensal da oficina (assinatura oimpresso) — não OSs finais | consome |
| `Modules/MemCofre` | Cofre senhas (cert digital, login fornecedor de peças, API DETRAN) | consome opcional |
| Núcleo UltimatePOS | `business_id`, users, roles, locations, `transactions`, products, contacts | base |
| API DETRAN/CRLV (externa) | Connector — consulta situação veicular sob opt-in cliente | externa |
| Tabela Sindirepa (dataset) | Connector — tempária por serviço por região; sync periódico | externa |

**Inverso:** Modules/OficinaAuto **não é consumido** por outros módulos verticais (princípio P2 ADR 0121).

---

## 9. Métricas de sucesso

### Adoção (a baselinear no 1º piloto)
- **DAU / MAU** ≥ 0.6 (oficina com operação diária)
- **Retention 12m** ≥ 85% (auto tem trocas mas custo de switch ERP é alto)
- **NPS específico OficinaAuto** ≥ 50

### Saúde do módulo
- **Tickets de suporte / cliente ativo / mês** ≤ 4 (inicialmente maior que Vestuario, dado complexidade)
- **Bugs críticos abertos > 7d** = 0
- **Cobertura Pest do módulo** ≥ 70% + 100% dos Non-Goals + Anti-hooks (GUARD)

### Comercial (review_triggers ADR 0121)
- **3 clientes pagantes em 12m após launch** — `piloto` → `ativo`
- **10+ clientes pagantes em 24m** — promove `maduro`
- **<2 clientes ativos em 12m após launch formal** — candidato `historical`

---

## 10. Lifecycle

Segue lifecycle canon de módulo vertical ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §Lifecycle):

| Estado | Critério | Hoje |
|---|---|---|
| `proposto` (`feature-wish`) | ADR feature-wish, sem código | ✅ **AQUI** (sem piloto) |
| `em_construcao` | 1 cliente piloto pagante + 6 features mínimas em desenvolvimento ativo | aguardando gatilho |
| `piloto` | 1 cliente real pagando, MVP rodando em prod | - |
| `ativo` | 3+ clientes pagantes, módulo formal `Modules/OficinaAuto/` | - |
| `maduro` | 10+ clientes, benchmark setorial via Jana | - |
| `historical` | <2 clientes ativos / 12m | - |

### Pré-requisitos pra `proposto` → `em_construcao` (gatilho explícito)

**TODOS obrigatórios — sem exceção:**

1. **1 cliente piloto pagante real** com contrato assinado (não promessa, não interesse, não MOU). [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md): "backlog só recebe item se cliente paga + reporta OU métrica detecta drift"
2. **Caminho A audit F29 entregue** — `RepairCore` extraído como pacote/módulo shared, com Pest verde + ADR de extração merged
3. **6 features mínimas escopadas** em SPEC.md como US-AUTO-001..006:
   - **US-AUTO-001** — OS por placa+km com histórico veicular
   - **US-AUTO-002** — Tabela tempária Sindirepa aplicada por serviço
   - **US-AUTO-003** — Checklist diagnóstico digital com fotos antes/depois
   - **US-AUTO-004** — Integração CRLV/DETRAN sob opt-in cliente
   - **US-AUTO-005** — Comissão automática por OS (mecânico + auxiliar)
   - **US-AUTO-006** — Aprovação orçamento (digital + LGPD opt-in WhatsApp)
4. **Cycle alocado** com goals outcome-oriented + WIP atribuído (não fica em backlog vago)
5. **Wagner aprova ADR de promoção** (`charter_version: 2`, `status: em_construcao`, registra cliente + cycle + escopo)

### Pré-requisitos pra `em_construcao` → `piloto`
- 6 features US-AUTO-001..006 entregues + Pest verde
- Smoke biz=1 (Wagner WR2 SC) zerado, depois canary 7d biz piloto
- SPEC.md + CAPTERRA-FICHA.md + CAPTERRA-INVENTARIO.md no módulo
- Pest GUARD pra Non-Goals + Anti-hooks desta charter

### Aposentar
ADR amendment + comunicação 90d + read-only legacy.

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-10 | Opus + Wagner | Charter inicial **antecipatória** — segundo módulo vertical formalizado pós-Vestuario template canônico ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)). Status `proposto` / lifecycle `feature-wish` — **sem piloto pagante**, sem código. Documenta hipótese de produto + gatilho explícito de promoção. Reuso planejado de `RepairCore` (audit F29 Caminho A bloqueador). |
