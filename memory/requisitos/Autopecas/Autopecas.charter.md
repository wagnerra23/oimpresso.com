---
module: Autopecas
charter_type: module
status: proposto
lifecycle: feature-wish
piloto: Vargas (candidato — sinal qualificado real, contrato pendente Q4/26)
last_review: 2026-05-10
owner: wagner
parent_adr: 0125
related_adrs: [0011, 0093, 0094, 0105, 0119, 0121, 0125]
tier: A
charter_version: 1
---

# Module Charter — Autopecas (planejado — não existe)

> **Status `proposto` / lifecycle `feature-wish`:** charter **antecipatório**, escrito como *template aplicado* pra firmar contrato de produto **antes** de virar US ativa. Sem Vargas (ou substituto qualificado) assinar contrato pioneer, **nada aqui é compromisso de roadmap** — é hipótese formalizada conforme [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) e [ADR 0125](../../decisions/0125-modules-autopecas-feature-wish.md).
>
> Reusa convenções da [Vestuario.charter.md](../Vestuario/Vestuario.charter.md) (template canônico ADR 0121) e [OficinaAuto.charter.md](../OficinaAuto/OficinaAuto.charter.md) (vertical próximo). Antecipa que o catálogo de peças (SKU + aplicação veicular chassis/ano/modelo) será compartilhado com `Modules/OficinaAuto` quando ambos verticais ativarem (refactor shared infra futura).
>
> Charter de **módulo inteiro** (não de página). Diferente de `*.charter.md` ao lado de `.tsx`. Aqui o objeto governado é o módulo vertical inteiro do oimpresso conforme [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md).

---

## 1. Mission (1 frase)

Add-on vertical de comércio de autopeças sobre o núcleo oimpresso — entrega catálogo SKU por aplicação veicular (chassis/ano/modelo/montadora), venda balcão sub-segundo, NFC-e ágil + NFe-de-boleto-pago automática, devolução com motivo + impacto estoque, e garantia loja-vs-fabricante separadas pro fluxo realista de autopeças balcão brasileira (Vargas-style: B2B oficinas + B2C balcão + entrega raio 5-15km).

---

## 2. Goals — objetivos de produto mensuráveis (antecipatórios)

> **Antecipatório:** métricas-alvo só viram baseline quando Vargas (ou piloto qualificado) começar a operar. Hoje servem como hipótese a validar.

| # | Goal | Métrica (a baselinear no piloto Vargas) |
|---|---|---|
| G1 | **Lookup peça por aplicação <2s** — balconista digita "Civic 2015 freio dianteiro", sistema retorna SKUs compatíveis ranqueados em ≤2s p95 | tempo p95 `/autopecas/produtos/buscar-aplicacao` < 2000ms; 0 falsos negativos em sample 100 buscas reais |
| G2 | **Venda balcão p95<1500ms** — finalizar venda 5 itens + cliente + pagamento + NFC-e em <1500ms p95 | p95 `POST /autopecas/balcao/finalizar` <1500ms; NFC-e cstat=100 ≥99,5% das vendas |
| G3 | **Devolução registrada ≤60s** — balconista recebe peça devolvida, registra motivo + estoque retorna em ≤60s | tempo médio fluxo devolução <60s; 100% devoluções com motivo enum (não free text) |
| G4 | **Garantia lookup <500ms** — cliente apresenta NF, balconista confere em <500ms se peça está em garantia loja, garantia fabricante ou expirada | p95 `GET /autopecas/garantias/lookup` <500ms; 0 disputas "tinha garantia ou não?" no piloto |
| G5 | **NFC-e + NFe-boleto auto sem clique humano** — boleto crediário cai pago → NFe emitida automática + email/WhatsApp cliente; venda balcão → NFC-e em <500ms síncrona | 100% boletos crediário viram NFe sem ação humana; <0,5% NFC-e balcão fora janela 500ms (degradação alertada) |

---

## 3. Non-Goals — o que **NÃO** é responsabilidade do módulo

> Cada item evita escopo gourmet. Onde o trabalho realmente vive está apontado.

- ❌ **Emissão fiscal NFC-e/NFe/SAT** infraestrutura → vive em `Modules/NfeBrasil` (módulo consome eventos `NFCeAutorizada`/`NFeAutorizada`)
- ❌ **Visão financeira AR/AP unificada** → vive em `Modules/Financeiro`
- ❌ **Boleto/assinatura/cobrança recorrente** (crediário interno usa pipeline) → vive em `Modules/RecurringBilling`
- ❌ **Multi-tenant `business_id` global scope** → infraestrutura núcleo Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- ❌ **Jana IA / memória persistente** → vive em `Modules/Jana`
- ❌ **OS de aplicação física** (mecânico aplica peça em veículo) → vive em `Modules/OficinaAuto` (US-AUTO-006 multi-mecânico)
- ❌ **Diagnóstico mecânico assistido** (sintoma → hipóteses peça aplicável) → vive em `Modules/OficinaAuto` (US-AUTO-007). Autopecas só **consulta** catálogo, não diagnostica
- ❌ **Tabela tempária Sindirepa** (mão-de-obra) → vive em `Modules/OficinaAuto`. Autopecas vende peça, não tempo de aplicação
- ❌ **CT-e / MDF-e (transporte)** — fora do MVP. Adicionar SE Vargas operar frota própria entrega volumes >R$ [redacted Tier 0]k/dia
- ❌ **Marketplace integration** (Mercado Livre Auto, Mercado Pago, Magazine Luiza Marketplace) → fora do MVP; Connector futuramente
- ❌ **Folha de pagamento balconista/entregador** → núcleo UltimatePOS Essentials
- ❌ **Cadastro veículo persistente do cliente final** → opcional. Autopecas guarda aplicação (chassis/ano/modelo) **por SKU**, não veículo do cliente. Cliente B2C balcão raramente tem veículo cadastrado; B2B oficina cadastra na própria OS Modules/OficinaAuto
- ❌ **Telemetria veicular OBD-II** — fora total

---

## 4. Audiência (persona detalhada)

**Quatro personas distintas — autopeças é mais multi-papel que Vestuario/OficinaAuto.**

### 4.1 Dono autopeças (decisor + comprador)

- Homem, 38-65 anos, dono autopeças independente em cidade pequena/média ou bairro grande capital
- **Não opera balcão o dia inteiro** — entra de manhã ver caixa do dia, fim de tarde ver fechamento, e em casos críticos (cotação grande, cliente VIP, devolução polêmica)
- Decide preço/desconto/política garantia/limite crediário
- Compra estoque (lê alertas, cota fornecedores, decide marca: original Bosch vs similar Nakata)
- Quer ver: faturamento dia, caixa, alertas estoque mínimo, devolução% mês, peças em garantia ativa
- Monitor desktop balcão (1280-1920px)
- PT-BR exclusivo
- **Vargas é exatamente este perfil** — Wagner conhece direto, 26 anos relação

### 4.2 Balconista (executor principal — venda + atendimento)

- Homem ou mulher, 22-50 anos, ensino médio
- **Mãos limpas** (não graxa) — opera teclado + mouse + scanner código de barras
- Atende balcão presencial + telefone + WhatsApp simultaneamente
- Busca peça → cliente → pagamento → NFC-e em <60s pico (Vargas opera 8-18h)
- **Decora atalhos teclado** (F2/F4/F8/F12) — não mexe em mouse no pico
- Usa monitor (1280px típico — quirk Larissa-tipo aplica) E celular pessoal pra WhatsApp B2B
- Multi-tarefa extrema — atende cliente A enquanto B liga e C manda WhatsApp
- 8h direto, decora layouts ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md) aplicável: zero regressão visual)

### 4.3 Comprador (compras + cotação fornecedores)

- Homem ou mulher 30-55 anos, geralmente o dono ou cônjuge dono em SMB
- Lê alertas estoque mínimo → cota 3-5 fornecedores → escolhe melhor preço/prazo
- Negocia condições pagamento (30/60/90d) com fornecedor
- Mantém relação com 10-30 fornecedores ativos
- Monitor desktop + email + WhatsApp Business pro fornecedor
- Em SMB Vargas-size, **acumula com dono** — papel não-isolado

### 4.4 Entregador (logística raio 5-15km)

- Homem 22-50 anos, motorizado moto/carro próprio
- Recebe pedido → leva peça pro cliente → cobra na entrega ou já pago
- **App mobile crítico** — vê pedidos do dia, marca entregue, sobe foto cliente recebendo (anti-fraude)
- Tablet/celular 4G + offline graceful (zonas mortas raio rural)

Validação: **Vargas é candidato piloto qualificado** mas charter antecipatório. Personas baseadas em pesquisa setor BR autopeças (research Q4/26 pendente) + cross-com Modules/Vestuario (operação balcão similar).

---

## 5. UX targets

### Heurísticas Nielsen aplicáveis (foco)

- **#1 Visibility of system status** — venda balcão sempre mostra estoque atual + status NFC-e (pendente/emitindo/autorizada)
- **#2 Match real world** — usar léxico autopeças ("aplicação", "OEM", "similar", "qualidade", "tempário NÃO" — tempário é OficinaAuto) não termos genéricos UltimatePOS
- **#3 User control & freedom** — cancelar venda em curso / re-abrir venda fechada (com auditoria) sem trap modal
- **#5 Error prevention** — confirmação dupla pra ações de impacto (estornar venda já emitida NFC-e, dar desconto >10%, aprovar devolução D+8 fora prazo)
- **#7 Flexibility** — atalhos teclado balcão decoráveis (F2/F4/F8/F12) + interface touch otimizada PWA mobile (US-AP-012)
- **#8 Aesthetic minimalist** — Cockpit V2 ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)): pills, KPIs no topo, drawer detalhe

### Targets duros

- **p95 venda balcão (US-AP-002) < 1500ms** — gate Pest browser-test obrigatório
- **p95 lookup peça por aplicação (G1) < 2000ms**
- **p95 lookup garantia (G4) < 500ms**
- p95 first-paint < 1500ms (admin) / < 800ms (Cockpit dashboard)
- 0 erros JS console em smoke biz=Vargas ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- **Monitor 1280px sem scroll horizontal** em todos os fluxos balcão/recepção
- **Mobile-first telas balconista mobile + entregador** (≥360px)
- Tipografia canon ADR 0110
- Cores semânticas Cockpit V2

---

## 6. Automation hooks (onde Jana IA atua)

> Jana = Modules/Jana. Hooks **propostos** — exigem sinal qualificado antes de virar US ativa.

- ✅ **Sugestão peça por aplicação** — balconista digita "Civic 2015 freio dianteiro", Jana sugere top 3 SKUs ranqueados (compatibilidade × estoque × margem)
- ✅ **WhatsApp consulta peça por foto** (US-AP-011) — cliente oficina manda foto peça quebrada, Jana identifica + sugere SKU compatível + reserva 2h
- ✅ **Alerta estoque mínimo proativo** — quando peça crítica (alta rotatividade) cair perto do mínimo, Jana avisa + sugere cotação automática 3 fornecedores
- ✅ **Resumo do dia 18h pro dono** — "Y vendas, R$ Z faturado, W devoluções (motivo top: cliente errou peça), V cotações pendentes resposta, U peças garantia vence 7d"
- ✅ **Detecção crediário risco** — cliente B2B começa atrasar parcela → alerta limite crediário; sugestão renegociação antes virar inadimplente
- ✅ **Sugestão cross-sell na venda** — cliente comprou pastilha freio, Jana sugere disco freio (alta correlação)
- ✅ **Análise garantia** — % devolução por marca/categoria/fabricante → ajuda dono decidir cortar marca defeituosa do estoque

---

## 7. Anti-hooks (onde Jana **NÃO** deve interferir)

> Tier 0. Onde IA não-confirmada gera dano real ao negócio do cliente.

- ❌ **NUNCA emitir NFC-e/NFe automaticamente sem fechar venda humana** — fiscal é irreversível, erro custa multa SEFAZ. Auto NFe-de-boleto-pago (US-AP-007) tem trigger explícito (boleto cai pago no banco) — não é "Jana decidiu emitir" **(Tier 0 auto-específico — fiscal autopeças tem CFOPs/CSOSNs específicos: 5102 peça nova, 5949 acessório, 5409/6409 transferência intra/interestadual)**
- ❌ **NUNCA aprovar devolução fora prazo (D+7 default) automaticamente** — disputa cliente vs loja é decisão humana **(Tier 0 auto-específico — sem aprovação humana = vira política de auto-devolução que sangra margem)**
- ❌ **NUNCA ajustar estoque sem rastreio** — todo ajuste estoque (devolução, transferência, baixa, perda) gera audit log obrigatório com user_id + motivo + delta. Jana sugere ajuste, humano confirma **(Tier 0 auto-específico — auditoria fiscal SEFAZ exige rastreio movimentação estoque)**
- ❌ **NUNCA aprovar crediário acima limite cliente** — virar sugestão "cliente solicitou +R$ [redacted Tier 0]k, score atual 80, recomendo aprovar com aval"; dono aprova
- ❌ **NUNCA aplicar desconto > 5% sem aprovação humana** — virar sugestão, dono aprova
- ❌ **NUNCA mexer em data retroativa de venda digitada manualmente** — workflow legítimo (venda fechada ontem, balconista lança hoje cedo)
- ❌ **NUNCA reordenar/esconder colunas decoradas balconista** (decora layout, atalhos)
- ❌ **NUNCA enviar SMS/WhatsApp pro cliente final sem opt-in explícito** (LGPD Art. 7º). US-AP-011 exige opt-in B2B fornecedor + B2C cliente
- ❌ **NUNCA classificar cliente como "inadimplente"** sem fluxo formal (impacto cadastral SPC/Serasa)
- ❌ **NUNCA cobrar peça antes de venda finalizada** — Código Defesa Consumidor Art. 39 III
- ❌ **NUNCA escrever em outro `business_id`** (multi-tenant Tier 0 IRREVOGÁVEL — ADR 0093)
- ❌ **NUNCA emitir NFe transferência multi-depósito com CFOP errado** — multa SEFAZ. CFOP 5409 same UF / 6409 outra UF; sem CFOP correto = bloqueio dura

---

## 8. Integrações (módulos do núcleo que este consome)

| Módulo núcleo | Como Autopecas consome | Direção |
|---|---|---|
| `Modules/NfeBrasil` | Listener `NFCeAutorizada` (venda balcão) + `NFeAutorizada` (transferência multi-depósito + crediário); pipeline TransactionBuilder | consome |
| `Modules/RecurringBilling` | US-RB-044 boleto pago→NFe automática (crediário + B2B 30/60/90d) | consome |
| `Modules/Financeiro` | Visão unificada AR/AP de vendas; DRE simplificado | consome |
| `Modules/Jana` (Jana) | Chat contextual + alertas + brief diário + WhatsApp consulta peça (US-AP-011) | consome |
| `Modules/Whatsapp` | Webhook Meta Cloud API pra US-AP-011 + opt-in cliente | consome |
| `Modules/OficinaAuto` | **Reuso futuro shared infra** — catálogo `pecas` + `aplicacoes` (chassis/ano/modelo) extraível como pacote shared quando OficinaAuto ativar | consome (futuro) |
| `Modules/Vestuario` | **Reuso UI/Controller patterns** — venda balcão padrão, variação SKU multi-atributo (~30-40% reuso) | imita (não consome direto) |
| `Modules/MemCofre` | Cofre senhas (cert digital, login fornecedor, API DETRAN se ativar) | consome opcional |
| Núcleo UltimatePOS | `business_id`, users, roles, locations, `transactions`, `products`, `contacts` | base |
| API Bosch / Nakata / Fras-le (catálogo OEM) | Connector — sync periódico catálogo OEM open data ou parceria | externa (opcional fase 2) |

**Inverso:** Autopecas (planejado — não existe) **não é consumido** por outros módulos verticais (princípio P2 ADR 0121). Excepão: catálogo peças shared (P2 ADR 0125) **será** consumido por Modules/OficinaAuto quando ambos verticais coexistirem.

---

## 9. Métricas de sucesso

### Adoção (a baselinear no piloto Vargas)

- **DAU / MAU** ≥ 0.7 (autopeças balcão = uso diário intenso, mais que oficina)
- **Retention 12m** ≥ 90% (custo switch ERP autopeças é alto + Migration Factory garante 30d parallel run)
- **NPS específico Autopecas** ≥ 50

### Saúde do módulo

- **Tickets de suporte / cliente ativo / mês** ≤ 3 (mais simples que OficinaAuto, menos que Vestuario)
- **Bugs críticos abertos > 7d** = 0
- **Cobertura Pest do módulo** ≥ 75% + 100% dos Non-Goals + Anti-hooks (GUARD)

### Comercial (review_triggers ADR 0121)

- **3 clientes pagantes em 12m após launch** — `piloto` → `ativo`
- **10+ clientes pagantes em 24m** — promove `maduro`
- **<2 clientes ativos em 12m após launch formal** — candidato `historical`

### Vargas-específico (piloto inicial)

- **Migration Factory Vargas: zero perda dado** — count NF emitidas legacy = count migradas; sum faturamento ano = ±0,1%
- **Vargas churn 90d pós-cutover** = 0% (review_trigger ADR 0119 #4)
- **Vargas case público publicado** D+90 (sim/não autorizado pelo dono)

---

## 10. Lifecycle

Segue lifecycle canon de módulo vertical ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §Lifecycle):

| Estado | Critério | Hoje |
|---|---|---|
| `proposto` (`feature-wish`) | ADR feature-wish, sem código | ✅ **AQUI** (sem Vargas assinatura) |
| `em_construcao` | Vargas (ou substituto qualificado) assinou contrato + 6 features mínimas em desenvolvimento ativo | aguardando gatilho |
| `piloto` | Vargas em prod pagando, MVP rodando | - |
| `ativo` | 3+ clientes pagantes, módulo formal `Autopecas` (planejado — não existe) | - |
| `maduro` | 10+ clientes, benchmark setorial via Jana | - |
| `historical` | <2 clientes ativos / 12m | - |

### Pré-requisitos pra `proposto` → `em_construcao` (gatilho explícito)

**TODOS obrigatórios — sem exceção:**

1. **Vargas assina contrato pioneer real** Enterprise R$ [redacted Tier 0]/m grandfathered 24m + 50% off 6m + setup R$ [redacted Tier 0] (não promessa, não MOU). [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
2. **Snapshot financeiro Vargas confirmado** via skill `officeimpresso-financial-snapshot` (ticket pago real, recência, sinais churn)
3. **Modules/ComunicacaoVisual com 1ª piloto comvisual em prod estabilizado** — não ativar 4º vertical sem 3º vertical comprovado
4. **6 features mínimas escopadas** em SPEC.md como US-AP-001..006:
   - US-AP-001 — Catálogo SKU + tabela aplicação
   - US-AP-002 — Venda balcão p95<1500ms
   - US-AP-003 — Tabela preço por categoria/montadora
   - US-AP-004 — Controle estoque mínimo + alertas
   - US-AP-005 — Devolução com motivo + impacto estoque
   - US-AP-006 — Garantia loja vs fabricante
5. **Cycle alocado** com goals outcome-oriented + WIP atribuído (não fica em backlog vago)
6. **Wagner aprova ADR de promoção** (`charter_version: 2`, `status: em_construcao`, registra Vargas + cycle + escopo)

### Pré-requisitos pra `em_construcao` → `piloto`

- 6 features US-AP-001..006 entregues + Pest verde
- Migration Factory Vargas: dry-run + count match + totals match (Pattern 07)
- Smoke biz=1 (Wagner WR2 SC) zerado, depois canary 7d biz Vargas (ADR 0101)
- SPEC.md + CAPTERRA-FICHA.md + CAPTERRA-INVENTARIO.md no módulo
- Pest GUARD pra Non-Goals + Anti-hooks desta charter

### Aposentar

ADR amendment + comunicação 90d + read-only legacy.

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-10 | Opus + Wagner | Charter inicial **antecipatória** — quarto módulo vertical formalizado pós-Vestuario / ComunicacaoVisual / OficinaAuto template canônico ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md), [ADR 0125](../../decisions/0125-modules-autopecas-feature-wish.md)). Status `proposto` / lifecycle `feature-wish` — **Vargas é candidato piloto qualificado mas contrato pendente**, sem código. Documenta hipótese de produto + gatilho explícito de promoção. Reuso planejado de catálogo peças shared infra com Modules/OficinaAuto (extraível quando ambos verticais ativarem). |
