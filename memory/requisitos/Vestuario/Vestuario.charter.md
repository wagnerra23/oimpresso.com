---
module: Vestuario
charter_type: module
status: piloto
piloto: ROTA LIVRE biz=4 (LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME, Gravatal/SC)
last_review: 2026-05-10
owner: wagner
parent_adr: 0121
related_adrs: [0011, 0093, 0094, 0105, 0121]
tier: A
charter_version: 1
---

# Module Charter — Modules/Vestuario

> **Status piloto:** ROTA LIVRE valida o vertical **hoje rodando no núcleo** (Sells/Stock/Financeiro). Módulo formal `Modules/Vestuario/` ainda **a extrair** — esta charter é o contrato de produto que guia a extração + serve como template canônico pros próximos módulos verticais (ComunicacaoVisual, OficinaAuto).
>
> Charter de **módulo inteiro** (não de página). Diferente de `*.charter.md` ao lado de `.tsx` (que é tela). Aqui o objeto governado é o módulo vertical inteiro do oimpresso conforme [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md).

---

## 1. Mission (1 frase)

Add-on vertical de moda/vestuário sobre o núcleo oimpresso — entrega estoque por SKU+tamanho+cor, devolução/troca, etiqueta de preço e fluxo de balcão otimizado pra dona-operadora de loja física brasileira.

---

## 2. Goals — objetivos de produto mensuráveis

| # | Goal | Métrica |
|---|---|---|
| G1 | **Venda balcão p95 < 1500ms** end-to-end (do scan até NFC-e autorizada) | observado em `/sells/create` biz=4, baseline pre-extração |
| G2 | **Estoque por SKU+tamanho+cor confiável** — divergência inventário < 2% / mês | conta de variants com `qty_available < 0` ou drift contábil |
| G3 | **Devolução/troca em ≤ 60s no balcão** sem precisar de admin | conta de fluxos `return_sell` ÷ `sell` no período |
| G4 | **Etiqueta de preço imprimível em 1 click** por linha de produto/variant | nº de prints por dia ÷ produtos cadastrados ativos |
| G5 | **Tela 1280px sem scroll horizontal** em todos os fluxos críticos do módulo | `CockpitPatternConformanceTest` (Pest) verde |

---

## 3. Non-Goals — o que **NÃO** é responsabilidade do módulo

> Cada item evita escopo gourmet. Onde o trabalho realmente vive está apontado.

- ❌ **Emissão fiscal NFC-e/NFe** → vive em `Modules/NfeBrasil` (módulo consome o evento `NFCeAutorizada`)
- ❌ **Visão financeira AR/AP unificada** → vive em `Modules/Financeiro`
- ❌ **Boleto/assinatura/cobrança recorrente** → vive em `Modules/RecurringBilling`
- ❌ **Multi-tenant `business_id` global scope** → infraestrutura núcleo Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- ❌ **Jana IA / memória persistente** → vive em `Modules/Jana`
- ❌ **Cofre de senhas/credenciais** → vive em `Modules/MemCofre`
- ❌ **PCP de produção** (planejamento, ordem de produção, BOM gráfico) — isso é de `Modules/ComunicacaoVisual`
- ❌ **OS de reparo/ajuste de roupa** — Modules/Repair atende, vestuário só consome se cliente quiser
- ❌ **E-commerce / marketplace** (Shopify, Mercado Livre, etc) → fora de escopo MVP; integração via Connector futuramente
- ❌ **Folha de pagamento / RH dos funcionários da loja** → núcleo UltimatePOS Essentials

---

## 4. Audiência (persona detalhada)

**Larissa-tipo: dona-operadora 8h/dia.**

- Mulher, 30-50 anos, dona de loja física de roupa em cidade pequena/média (≤200k habitantes)
- **Opera ela mesma o sistema** durante o dia inteiro — não tem TI nem analista
- Monitor pequeno (1280px típico) — fluxos que exigem scroll horizontal viram fricção real
- Lida com cliente PF presencial; ticket médio R$ [redacted Tier 0]-500
- **Decora** comportamentos do sistema — qualquer mudança visual em data/hora/coluna existente é regressão percebida ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md))
- Digita `transaction_date` retroativo é **fluxo normal** (vendas de balcão registradas em lote no fim do dia)
- Pico operacional 14h-17h horário SP
- Tem ≥2 operadores secundários (caixa, vendas) com permissões granulares por role
- PT-BR exclusivo — termos em inglês confundem

Validação: ROTA LIVRE biz=4, 17.251+ vendas, 99% volume sistema, em prod desde 2021-05.

---

## 5. UX targets

### Heurísticas Nielsen aplicáveis (foco)
- **#1 Visibility of system status** — venda/devolução sempre mostra estado atual (rascunho/finalizada/cancelada)
- **#3 User control & freedom** — cancelar venda/devolução em ≤ 2 cliques, sem trap modal
- **#5 Error prevention** — confirmação só em ação destrutiva irreversível (cancelar NFC-e autorizada)
- **#7 Flexibility** — atalhos teclado em `/sells/create` (F2 buscar produto, F4 finalizar)
- **#8 Aesthetic minimalist** — Cockpit V2 ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)): pills `rounded-full`, KPIs no topo, drawer lateral pra detalhe

### Targets duros
- p95 first-paint < 1500ms (admin) / < 800ms (Cockpit dashboard)
- 0 erros JS console em smoke biz=1 (Wagner WR2 SC) — nunca biz=4 em smoke ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- **Monitor 1280px sem scroll horizontal** em todas telas — quirk crítico ROTA LIVRE
- Mobile responsivo (≥768px) pra conferência de estoque no celular caminhando na loja
- Tipografia canon ADR 0110 — h1 22-24px / pill 12px / badge 11px
- Cores semânticas Cockpit V2 (rose/emerald/amber/blue), nunca cor crua

---

## 6. Automation hooks (onde Jana IA atua)

> Jana = Modules/Jana. Hooks abaixo são **propostos** — exigem sinal qualificado ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) antes de virar US ativa.

- ✅ **Alerta estoque baixo por variant** — quando `qty_available < min_stock` por SKU+tamanho+cor, Jana sugere reposição no chat contextual
- ✅ **Comparativo semanal de vendas** — Larissa pergunta "como foi essa semana vs anterior?" → ContextoNegocio responde com 3 ângulos faturamento ([ADR 0052](../../decisions/0052-contexto-negocio-3-angulos-faturamento.md))
- ✅ **Sugestão de preço sazonal** — fim de coleção, Jana sugere desconto baseado em curva histórica de venda do SKU
- ✅ **Detecção de divergência inventário** — drift > 2% / mês dispara alerta passivo no brief diário
- ✅ **Resumo do dia 18h** — Larissa recebe no brief: "vendeu R$ X, ticket médio Y, top 3 SKUs, alertas Z"

---

## 7. Anti-hooks (onde Jana **NÃO** deve interferir)

> Tier 0. Onde IA não-confirmada gera dano real ao negócio do cliente.

- ❌ **NUNCA emitir NFC-e/NFe automaticamente** sem confirmação humana — fiscal é irreversível, erro custa multa
- ❌ **NUNCA cancelar venda autorizada** sem confirmação humana
- ❌ **NUNCA aplicar desconto > 5%** automaticamente — virar sugestão, Larissa aprova
- ❌ **NUNCA mexer em `transaction_date` retroativo digitado** pela Larissa — ela usa intencionalmente, não é bug
- ❌ **NUNCA reordenar/esconder colunas decoradas** (cliente decora layout — mudança visual = regressão percebida)
- ❌ **NUNCA enviar SMS/email/WhatsApp pro cliente final** sem opt-in explícito por venda (LGPD Art. 7º)
- ❌ **NUNCA ajustar estoque negativo** por algoritmo — divergência exige inventário humano
- ❌ **NUNCA classificar cliente como "inadimplente"** sem fluxo formal (impacto cadastral)
- ❌ **NUNCA escrever em outro `business_id`** (multi-tenant Tier 0 IRREVOGÁVEL)

---

## 8. Integrações (módulos do núcleo que este consome)

| Módulo núcleo | Como Vestuario consome | Direção |
|---|---|---|
| `Modules/NfeBrasil` | Listener `NFCeAutorizada` ao finalizar venda; pipeline TransactionBuilder | consome |
| `Modules/Financeiro` | Visão unificada AR/AP de boletos da loja, DRE simplificado | consome |
| `Modules/Jana` (Jana) | Chat contextual + alertas + brief diário | consome |
| `Modules/RecurringBilling` | Plano mensal da loja (assinatura oimpresso) — não vendas finais | consome |
| `Modules/Repair` | OS de ajuste/conserto de roupa (opcional, se cliente ativar) | consome opcional |
| `Modules/MemCofre` | Cofre senhas (cert digital, login fornecedor) | consome opcional |
| Núcleo UltimatePOS | `business_id`, users, roles, locations, `transactions`, products | base |

**Inverso:** Modules/Vestuario **não é consumido** por outros módulos verticais — cada vertical é independente (princípio P2 ADR 0121).

---

## 9. Métricas de sucesso

### Adoção
- **DAU / MAU** ≥ 0.6 (loja com operação diária — Larissa baseline 1.0)
- **Retention 12m** ≥ 90% (vestuário tem baixa rotatividade quando ERP funciona)
- **NPS específico vestuário** ≥ 50 (medir só clientes ativos no módulo)

### Saúde do módulo
- **Tickets de suporte / cliente ativo / mês** ≤ 3 (baseline ROTA LIVRE 2026 a medir)
- **Bugs críticos abertos > 7d** = 0
- **Cobertura Pest do módulo** ≥ 70% (núcleo) + 100% das regras Non-Goal/Anti-hook (GUARD)

### Comercial (review_triggers ADR 0121)
- **3 clientes pagantes em 12m** — mantém status `piloto` → `ativo`
- **10+ clientes pagantes em 24m** — promove pra `maduro` (network effect benchmark setorial)
- **<2 clientes ativos em 12m após launch formal** — candidato a aposentar (`historical`)

---

## 10. Lifecycle

Segue lifecycle canon de módulo vertical ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §Lifecycle):

| Estado | Critério | Hoje |
|---|---|---|
| `proposto` | ADR feature-wish, sem código | - |
| `piloto` | 1 cliente real pagando, código vivendo (mesmo no núcleo) | ✅ **ROTA LIVRE biz=4** |
| `ativo` | 3+ clientes pagantes, módulo formal extraído pra `Modules/Vestuario/` | meta Q4/26 ou Q1/27 |
| `maduro` | 10+ clientes, benchmark setorial habilitado via Jana | aberto |
| `historical` | <2 clientes ativos por 12m → para aceitar novos, mantém legacy | revisar trigger |

**Promoção piloto → ativo** exige:
- Extração formal `Modules/Vestuario/` seguindo `Infra/RUNBOOK-criar-modulo.md`
- 3+ clientes pagantes (não só ROTA LIVRE)
- SPEC.md + CAPTERRA-FICHA.md + CAPTERRA-INVENTARIO.md no módulo
- Pest GUARD pra todos Non-Goals + Anti-hooks desta charter

**Aposentar (ativo → historical)** exige:
- ADR amendment registrando razão
- Comunicação prévia 90d aos clientes ativos
- Manter código read-only pros legacy, parar onboarding novo

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-10 | Opus + Wagner | Charter inicial — primeira charter formal de módulo vertical no projeto. Vira template pros próximos (ComunicacaoVisual, OficinaAuto) conforme [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md). Status `piloto` (ROTA LIVRE valida hoje no núcleo, extração `Modules/Vestuario/` formal a planejar). |
