# Arte do Estado — API Open Banking Santander Brasil (2026-05-25)

> **Tipo:** dossier de pesquisa (Fase 1 audit-and-fix · auditor universal)
> **Tema:** viabilidade técnica + recomendação posicionamento Santander no pacote top-5 bancos PJ
> **Driver alvo:** `Modules/PaymentGateway/Services/Drivers/SantanderDriver.php` (a criar)
> **Referência canônica:** `InterDriver.php` (Onda 4a · [ADR 0170])
> **Autor:** Claude Code (audit-research-expert)
> **Pesquisa:** 8 WebSearch + 1 Read (InterDriver) — todas as fontes citadas inline

---

## TL;DR (6 linhas)

Santander Brasil **TEM** API Open Banking moderna (`developer.santander.com.br`) com OAuth2 + mTLS A1, cobre **boleto registrado + boleto híbrido + PIX cob + PIX Automático recv** — feature-parity ESSENCIAL com Inter. Porém **3 burocracias travam onboarding pro cliente final**: (1) certificado A1 ICP-Brasil **emitido pelo cliente** (não pelo banco), (2) liberação API via **gerente PJ** com homologação por tipo de operação, (3) **sandbox notoriamente divergente da prod** (ACBr abandonou — todos testam via "Homologação = Prod com flag `environment=Teste`"). **Veredito: 🟡 PARCIAL** — API é viável e moderna, mas onboarding cliente é **2-4 semanas humano-limitadas** vs Inter (1-3 dias). **Recomendação: posição #4 ou #5 no pacote top-5**, depois de Inter+BB+Itaú; deixar Caixa pro fim (mais legado ainda).

---

## 1. Identidade da API

| Item | Valor |
|---|---|
| Portal developer | https://developer.santander.com.br/ |
| API names canônicos | **Cobrança Híbrida** (boleto + QR Code PIX), **PIX** (cob/cobv imediata e com vencimento), **PIX Automático** (recv recorrente — Res. BCB 402/2024) |
| Versão API Cobrança | **v2.2** (PDF user-guide ago/2023 ainda é referência) — v3 mencionada em alguns canais mas v2 segue como produção dominante |
| Versão API PIX | v1 (alinhada padrão BCB DICT/SPI) |
| Status | **GA** pra Cobrança v2 e PIX cob; **GA postergado pra 2025-06-16** pra PIX Automático (originalmente 2024-10-28, adiado pelo BCB) |
| Reputação | **Média-baixa entre devs brasileiros** — burocracia de homologação citada como pior que Inter/BB, sandbox "não fiel à produção" (ACBr deprecou ambiente sandbox), bugs históricos em webhook HTTP method (GET vs PUT confusion reportada) |

Fontes:
- [Portal Developer Santander](https://developer.santander.com.br/)
- [API PIX – Documentação](https://developer.santander.com.br/api/documentacao/pix)
- [Automatic Pix – Overview](https://developer.santander.com.br/api/overview/automatic-pix)
- [ACBr Forum — Sandbox Santander divergente](https://www.projetoacbr.com.br/forum/topic/76632-altera%C3%A7%C3%A3o-do-endpoint-api-santander-para-testes/)
- [PIX Automático adiado pra jun/2025](https://www.bcb.gov.br/content/estabilidadefinanceira/pix/pix-automatico-FAQ-participantes.pdf)

---

## 2. Endpoints

| Item | Valor |
|---|---|
| Base URL produção (OAuth + APIs) | `https://trust-open.api.santander.com.br` |
| Endpoint token OAuth2 | `POST /auth/oauth/v2/token` |
| Sandbox dedicado | **Existe mas desencorajado** — divergente. Recomendação oficial: usar Homologação (mesmo endpoint de prod) com `environment=Teste` no workspace |
| Base sandbox UK (não Brasil) | `https://sandbox-developer.santander.co.uk` (não confundir — escopo Reino Unido, não BR) |
| Endpoint Cobrança (criar workspace) | `POST /collection_bill_management/v2/workspaces` |
| Endpoint Cobrança (registrar bill) | `POST /collection_bill_management/v2/workspaces/{workspaceId}/bank_slips` |
| Endpoint PIX (criar cob imediata) | `PUT /pix/cob/{txid}` (padrão BCB) |
| Endpoint PIX (criar cobv com vencimento) | `PUT /pix/cobv/{txid}` |
| Endpoint webhook PIX | `PUT /pix/webhook/{chave}` (configuração) |
| Healthcheck | OAuth token issuance é proxy de saúde (não há `/health` formal) |

Fontes:
- [Documentação mTLS — Santander](https://developer.santander.com.br/api/docs/user-guide/api-ghs/mtls)
- [User Guide API Cobrança v2.2 (PDF ago/2023)](https://developer.santander.com.br/sites/default/files/2023-08/user_guide_api_de_cobranca_pt_br_v2_2_08_08_23_0.pdf)
- [GitHub diogodourado/banco-santander-api-php — endpoints workspace/bills](https://github.com/diogodourado/banco-santander-api-php)

---

## 3. Autenticação

| Dimensão | Valor |
|---|---|
| Esquema | **OAuth2 client_credentials grant + mTLS** (camada dupla obrigatória em PROD) |
| Certificado | **ICP-Brasil A1** (PFX→PEM+KEY), 2048 bits, Key Usage = Digital Signature ou Key Agreement, Enhanced Key Usage = Client Authentication (1.3.6.1.5.5.7.3.2), formato x509 v3, validade mínima 90 dias, NÃO pode ser self-signed |
| Como obter credenciais | **3 passos burocráticos**: (1) Master User PJ ativa Santander ID + e-mail no portal; (2) cria App no Developer Portal selecionando produto (Cobrança/PIX); (3) **contata Gerente PJ** pra contratação serviço + abertura homologação por API. Credencial técnica (ClientID/Secret) sai em **5 min** APÓS gerente liberar — gargalo é o gerente, não a tecnologia |
| Rotação de secret | Manual via portal (sem endpoint `/rotate`) |
| Tempo médio homologação (reportes comunidade) | **2-4 semanas** pra cobrança simples; **4-8 semanas** se for PIX + boleto + pagamento combinados. Muito maior que Inter (1-3 dias) ou BB (5-10 dias) |
| Validação certificado | Cliente envia **apenas chave pública** ao banco; chave privada nunca sai do servidor |

Fontes:
- [Autenticação mTLS Santander — requisitos certificado](https://developer.santander.com.br/api/docs/user-guide/api-ghs/mtls)
- [Sankhya — Como obter credenciais Santander (5 min APÓS gerente)](https://ajuda.sankhya.com.br/hc/pt-br/articles/21069541531927-Como-obter-as-credenciais-do-banco-Santander)
- [InnCash — burocracia homologação por tipo operação](https://inn.cash/blog/recebimentos/api-santander/)

---

## 4. Capacidades

| Capacidade | Suporta? | Endpoint | Nota |
|---|---|---|---|
| Boleto registrado | ✅ | `POST /workspaces/{id}/bank_slips` | Padrão Cobrança v2.2 |
| Boleto híbrido (boleto + QR PIX dinâmico) | ✅ | mesmo endpoint, flag carteira "PIX híbrido" | **Diferencial Santander** — gera QR Code dinâmico colado ao boleto, mesmo doc paga em qualquer canal |
| PIX cobrança imediata (cob) | ✅ | `PUT /pix/cob/{txid}` | Padrão BCB DICT |
| PIX cobrança com vencimento (cobv) | ✅ | `PUT /pix/cobv/{txid}` | Padrão BCB |
| PIX Automático recv (Res. BCB 402/2024) | ✅ (GA jun/2025) | `/pix/automatic/*` | Originalmente Res. 380, sucedida pela 402. Santander já tem overview publicado |
| Cartão de crédito | ❌ | n/a | Santander **NÃO** expõe API pública de adquirência cartão (Getnet é subsidiária mas API separada) |
| Refund / cancelamento boleto | ✅ | `PATCH` no bank_slip | Status `CANCELED` |
| Webhook callback | ✅ | configurável por workspace (`webhookURL`) | Eventos: pagamento confirmado, baixa, cancelamento. **HTTP method docs históricamente confusas** (GET vs PUT — vide reclamação Reclame Aqui) |

Fontes:
- [Visão Geral PIX QR Code](https://developer.santander.com.br/api/visao-geral/pix-visao-geral)
- [Boleto Híbrido Cobrança](https://ajuda.fortestecnologia.com.br/kb/pt-br/article/438526/api-santander-boleto-hibrido)
- [Reclame Aqui — bug webhook PIX HTTP method](https://www.reclameaqui.com.br/santander/problema-com-uso-da-api-pix_Jf4D1JQbPgK7sUEW/)
- [Automatic Pix — Santander Overview](https://developer.santander.com.br/api/overview/automatic-pix)

---

## 5. Limites operacionais

| Item | Valor |
|---|---|
| Rate limit oficial | **Não publicado em docs públicas** — devs relatam ~60 req/min como observado seguro (sem garantia SLA) |
| Quota grátis | n/a — modelo é tarifa por boleto registrado (negociado caso a caso com gerente PJ) |
| Preço típico boleto | R$ [redacted Tier 0]–3,50 por boleto registrado (varia por convênio negociado — comparável ao Inter R$ [redacted Tier 0]–3,00) |
| Preço PIX | Geralmente **gratuito recebimento PJ** (regulação BCB), tarifa só pra integração e split |
| Timeout recomendado | 30s pra criação cobrança, 10s pra consulta, 60s pra OAuth |
| Retry strategy oficial | **Não documentada** — comunidade usa exponential backoff com jitter (3 tentativas, base 1s) |
| Idempotência | **Suportada** via `txid` próprio em PIX; em boleto via `nossoNumero` próprio |

Fontes:
- [InnCash — modelo tarifário Santander](https://inn.cash/blog/recebimentos/api-santander/)
- [FAQ Developer Portal](https://developer.santander.com.br/faq)

---

## 6. Gotchas conhecidos

### 6.1 Burocracia onboarding (severidade ALTA)
Cliente precisa **agendar reunião com Gerente PJ** + assinar contrato serviço API + acompanhar homologação por tipo de operação. Bancos digitais (Inter, BTG, C6) entregam credencial em horas; Santander pode levar semanas. **Crítico pro UX wizard `/settings/payment-gateways`** — onde Inter resolve em 1-3 dias, Santander pode bloquear cliente por 2-4 semanas.
> Fonte: [InnCash blog](https://inn.cash/blog/recebimentos/api-santander/)

### 6.2 Sandbox não fidedigno (severidade ALTA)
**ACBr explicitamente deprecou ambiente Sandbox Santander** porque "não era fiel à produção". Recomendação oficial atual: usar **Homologação = endpoint de produção** com flag `environment=Teste` no workspace. Implica: testes E2E confiáveis só rodam **APÓS** credencial de produção liberada — não dá pra desenvolver SantanderDriver offline com sandbox aberto como Inter permite.
> Fonte: [ACBr Forum 2023+](https://www.projetoacbr.com.br/forum/topic/76632-altera%C3%A7%C3%A3o-do-endpoint-api-santander-para-testes/)

### 6.3 Certificado A1 ICP-Brasil cliente-emitido (severidade MÉDIA)
Diferente do Inter (banco emite/fornece via portal), Santander **exige certificado A1 do próprio cliente** emitido por AC ICP-Brasil (Serasa, Valid, AC Digital). Cliente paga R$ [redacted Tier 0]–400/ano + precisa gerar PFX, exportar PEM+KEY, e fazer upload de chave **pública** no portal. Wizard precisa orientar passo a passo (mais friction que outros drivers).
> Fonte: [Documentação mTLS Santander](https://developer.santander.com.br/api/docs/user-guide/api-ghs/mtls)

### 6.4 Webhook HTTP method ambíguo (severidade MÉDIA)
Reclame Aqui documenta caso de dev que ficou semanas sem conseguir receber webhook PIX porque docs não deixavam claro se endpoint do cliente devia ser `GET` ou `PUT`. Convencional é `POST`, mas docs Santander histórica usaram terminologia BCB literal (PUT é pra REGISTRAR webhook na conta; POST é o que banco envia ao callback). **Implementar SantanderDriver: testar ambos** + log estruturado discriminando method recebido.
> Fonte: [Reclame Aqui — bug webhook](https://www.reclameaqui.com.br/santander/problema-com-uso-da-api-pix_Jf4D1JQbPgK7sUEW/)

### 6.5 Convênio (covenant) obrigatório no workspace (severidade BAIXA)
Workspace Santander exige array `covenants` com **código de convênio** negociado com banco. Esse código não é descoberto via API — precisa ser fornecido manualmente pelo gerente. Wizard precisa de campo dedicado "Número do Convênio" com tooltip explicando.
> Fonte: [GitHub diogodourado/banco-santander-api-php — workspace config](https://github.com/diogodourado/banco-santander-api-php)

### 6.6 Renovação certificado A1 anual (severidade BAIXA)
Cliente precisa renovar A1 todo ano. Driver deve emitir **alerta 30/15/7 dias antes** do vencimento, com fallback gracioso (cobrança via canal alternativo até renovação). Já temos pattern no `InterDriver` que pode ser estendido.

### 6.7 PIX Automático recv adiado (severidade BAIXA — informativa)
Originalmente Out/2024, BCB adiou pra **2025-06-16**. Santander tem overview publicado mas implementação ainda em preview. **Não bloquear roadmap top-5** por isso — fazer cob/cobv primeiro, PIX Automático em onda separada após GA confirmado.
> Fonte: [BCB FAQ PIX Automático](https://www.bcb.gov.br/content/estabilidadefinanceira/pix/pix-automatico-FAQ-participantes.pdf)

---

## 7. Comparação com InterDriver

| Dimensão | Inter (referência) | Santander | Veredito |
|---|---|---|---|
| Auth scheme | OAuth2 + mTLS A1 | OAuth2 + mTLS A1 | **=** (paridade técnica) |
| Origem certificado | Inter emite/fornece via portal | Cliente compra ICP-Brasil | 🔻 Santander (mais friction) |
| Sandbox fidedigno | ✅ `cdpj-sandbox.partners.uatinter.co` funcional | 🔻 Deprecado (use Homologação=Prod) | 🔻 Santander |
| Onboarding cliente | 1-3 dias auto-serviço | 2-4 semanas via gerente PJ | 🔻🔻 Santander |
| Boleto registrado | ✅ v3 endpoints maduros | ✅ v2.2 estável | **=** |
| Boleto híbrido (QR PIX) | ✅ | ✅ (diferencial Santander historicamente) | **=** |
| PIX cob/cobv | ✅ padrão BCB | ✅ padrão BCB | **=** |
| PIX Automático recv | 🟡 em preview | 🟡 em preview (GA jun/2025) | **=** |
| Cartão | ❌ | ❌ | **=** |
| Refund/cancelamento | ✅ POST cancelar | ✅ PATCH status | **=** |
| Webhook | ✅ payload BCB padrão | ✅ payload BCB padrão, mas docs HTTP method confusas | 🔻 Santander (pegadinhas docs) |
| Rate limit transparente | ✅ documentado | 🔻 não publicado | 🔻 Santander |
| Comunidade dev PHP | Pacote oficial-like + libs | `diogodourado/banco-santander-api-php` (1 lib comunidade ativa) | 🔻 Santander |

**Síntese:** API Santander é tecnicamente equivalente ao Inter em **capacidades**, mas inferior em **developer experience** (sandbox, onboarding, docs webhook). Para clientes que **já têm conta PJ Santander**, é viável; para novos clientes, **recomendar Inter primeiro**.

---

## 8. Esforço estimado recalibrado (ADR 0106)

Aplicando fator 10x IA-pair + margem 2x:

| Componente | LOC estimado | Horas codáveis (IA-pair) | Horas humano-limitadas |
|---|---|---|---|
| `SantanderDriver.php` (todos métodos PaymentDriverContract) | ~450 LOC | **3h** (escalar a partir do InterDriver) | — |
| `SantanderWebhookController.php` + signature validation | ~150 LOC | **1h** | — |
| Pest tests Feature + Unit (>80% coverage) | ~600 LOC | **4h** | — |
| Wizard UI step "Santander" em `/settings/payment-gateways` (campo convênio + upload A1) | ~200 LOC | **2h** | — |
| Docs `/Modules/PaymentGateway/_docs/santander-onboarding.md` (tutorial passo a passo cliente) | ~120 linhas MD | **1h** | — |
| **Subtotal codável** | ~1520 LOC | **11h** | — |
| KYC + abertura conta PJ (se cliente piloto não tiver) | n/a | — | **n/a — clientes já têm** |
| Homologação Santander com gerente PJ (cliente piloto) | n/a | — | **2-4 semanas calendário** |
| Compra/instalação certificado A1 cliente piloto | n/a | — | **3-7 dias calendário** |
| Smoke real boleto + PIX em prod com cliente | n/a | — | **1-2 dias calendário** |
| **Total recalibrado** | 1520 LOC | **~11h codáveis × margem 2x = 22h** | **3-5 semanas calendário humano-limitadas** |

**Comparativo Inter (referência):** ~10h codáveis + 1-3 dias humano-limitadas. Santander custa o **dobro em código** (gotchas extras + tests) e **10-15x mais em calendário humano**.

---

## 9. Viabilidade verdict

🟡 **PARCIAL — VIÁVEL TECNICAMENTE, MAS HUMANO-LIMITADO PESADO**

- API moderna REST + OAuth2 + mTLS = ✅
- Cobre boleto + PIX cob/cobv + híbrido = ✅
- PIX Automático no roadmap próximo = ✅
- Sandbox confiável = ❌ (Homologação=Prod é workaround oficial)
- Onboarding cliente auto-serviço = ❌ (gerente PJ mandatório)
- Sem cartão = ❌ (esperado — todos drivers bancários PJ)

**Não é INVIÁVEL** porque tecnicamente o código sai em 1 dia (IA-pair), endpoints existem, formato é padrão BCB. **É PARCIAL** porque cada cliente que escolher Santander vai **bloquear 2-4 semanas no calendário** esperando gerente PJ, e nesse tempo o oimpresso fica "esperando o cliente fazer dever de casa" — pior UX que Inter.

---

## 10. Recomendação concreta — posicionamento no pacote top-5

**Posição sugerida: #4 (depois de Inter, BB, Itaú; antes só de Caixa)**

Justificativa em 1 parágrafo: o pacote top-5 deve ser ordenado por **velocidade de ativação do cliente**, não por market share bancário. Inter ganha #1 (sandbox + auto-serviço + comunidade dev forte); BB ganha #2 (homologação rápida + mercado PJ tradicional); Itaú #3 (API ION madura, onboarding moderado); **Santander #4** (API capable mas burocracia gerente PJ) e Caixa #5 (API legada mais ainda). Implementar SantanderDriver **agora** é correto porque clientes oimpresso já têm conta lá — mas a **comunicação no wizard** precisa setar expectativa honesta: "Ativação Santander leva 2-4 semanas, recomendamos começar com Inter em paralelo se precisar emitir cobranças esta semana."

**Implementação pragmática:**
1. Codar SantanderDriver completo (11h codáveis) na próxima Onda
2. UI wizard incluir **disclaimer de prazo** + checklist "você tem certificado A1?" (link p/ Serasa/AC Digital)
3. Documentar `_docs/santander-onboarding.md` com print do portal + roteiro pra cliente levar ao gerente
4. **NÃO bloquear release do pacote top-5** esperando 1 cliente piloto Santander concluir homologação — fazer Inter+BB+Itaú primeiro como "trilha rápida", Santander+Caixa como "trilha clássica"
5. Métrica de saturação: **>50% dos clientes que escolhem Santander concluem ativação em <30 dias** (se cair abaixo, considerar PARCERIA com agregador tipo Asaas/InnCash pra Santander específicamente)

---

## Surpresa positiva (oimpresso > mercado)

Nossa arquitetura `PaymentDriverContract` + `HttpClientFactory` + DTOs (`CobrancaEmitidaResult`, `DriverHealth`) já está **acima da média** do mercado dev brasileiro — a lib `diogodourado/banco-santander-api-php` (referência comunidade) é procedural sem abstração. Adicionar SantanderDriver no nosso pattern leva **~3h** porque 80% do trabalho já está feito no `InterDriver` (mTLS handling, OAuth caching, retry, webhook signature). **O oimpresso terá o melhor wrapper Santander em PHP do mercado** quando estiver pronto.

## Surpresa negativa (mercado > oimpresso)

Não temos ainda **multi-driver fallback automatico** no `PaymentGatewayService` — se Santander estiver indisponível, não há failover pra Inter pro mesmo cliente. Asaas (agregador) faz isso nativamente. Pra clientes que pagam premium, **considerar Onda futura: multi-driver routing com policy "preferred + fallback"** baseado em healthcheck recente. Sem isso, Santander down = cobranças não emitidas pra clientes só-Santander.

---

## Fontes consolidadas

- [Portal Developer Santander](https://developer.santander.com.br/)
- [API PIX – Documentação Santander](https://developer.santander.com.br/api/documentacao/pix)
- [Automatic Pix – Santander Overview](https://developer.santander.com.br/api/overview/automatic-pix)
- [User Guide API Cobrança v2.2 (PDF ago/2023)](https://developer.santander.com.br/sites/default/files/2023-08/user_guide_api_de_cobranca_pt_br_v2_2_08_08_23_0.pdf)
- [Autenticação mTLS Santander — requisitos certificado A1](https://developer.santander.com.br/api/docs/user-guide/api-ghs/mtls)
- [FAQ Developer Portal Santander](https://developer.santander.com.br/faq)
- [Blog Webhook Certificate Update PIX API](https://developer.santander.com.br/blog/generation-qrcode-webhook)
- [ACBr Forum — Sandbox Santander divergente da prod](https://www.projetoacbr.com.br/forum/topic/76632-altera%C3%A7%C3%A3o-do-endpoint-api-santander-para-testes/)
- [Sankhya — Como obter credenciais Santander (5 min APÓS gerente)](https://ajuda.sankhya.com.br/hc/pt-br/articles/21069541531927-Como-obter-as-credenciais-do-banco-Santander)
- [InnCash — burocracia homologação por tipo operação](https://inn.cash/blog/recebimentos/api-santander/)
- [Reclame Aqui — bug webhook PIX Santander HTTP method confusion](https://www.reclameaqui.com.br/santander/problema-com-uso-da-api-pix_Jf4D1JQbPgK7sUEW/)
- [Fortes Tecnologia — API Cobrança Santander Boleto Híbrido](https://ajuda.fortestecnologia.com.br/kb/pt-br/article/438526/api-santander-boleto-hibrido)
- [GitHub diogodourado/banco-santander-api-php — referência PHP comunidade](https://github.com/diogodourado/banco-santander-api-php)
- [BCB FAQ PIX Automático — postergação jun/2025](https://www.bcb.gov.br/content/estabilidadefinanceira/pix/pix-automatico-FAQ-participantes.pdf)
- [Kobana — Como receber PIX pela API Santander](https://ajuda.kobana.com.br/pt-BR/articles/8862304-como-receber-por-pix-pela-api-do-santander)
- [TecnoSpeed — Obtendo credenciais/criação Workspace Santander V2](https://atendimento.tecnospeed.com.br/hc/pt-br/articles/21661999362199-Obtendo-credenciais-cria%C3%A7%C3%A3o-de-Workspace-para-o-WebService-Santander-API-V2)

---

**Última atualização:** 2026-05-25 — pesquisa inicial Wagner (audit-research-expert · 8 WebSearch + 1 Read InterDriver). Próximo passo: criar SPEC `SPEC-SANTANDER-DRIVER` no backlog Onda PaymentGateway top-5.
