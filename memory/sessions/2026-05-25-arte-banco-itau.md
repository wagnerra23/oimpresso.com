# Estado-da-arte API Open Banking Itaú — dossier `ItauDriver`

**Data:** 2026-05-25
**Autor:** Claude Code (agente desktop, Opus 4.7) a pedido de Wagner
**Escopo:** Pesquisa estado-da-arte pra implementar `Modules/PaymentGateway/Services/Drivers/ItauDriver.php` no pacote top-5 bancos (Bradesco + Itaú + BB + Santander + Caixa)
**Driver de referência:** [`InterDriver.php`](../../Modules/PaymentGateway/Services/Drivers/InterDriver.php) (~498 LOC, OAuth2 + mTLS, boleto + PIX cob/cobv + refund parcial)
**Contract:** [`PaymentDriverContract.php`](../../Modules/PaymentGateway/Contracts/PaymentDriverContract.php)
**Decisão pendente:** Wagner aprovou top-5 — este dossier responde "Itaú entra na onda 1 ou onda 2?"

---

## TL;DR (6 linhas)

- ✅ **VIÁVEL** — API REST moderna, OAuth2 + mTLS, padrão BACEN Pix v2.41.15 + Cobrança Full IaaS
- 🟡 **Mas com fricção operacional pesada:** sandbox FECHADO (precisa onboarding com gerente PJ Itaú), credenciais via OfficerCash por email, **token temporário expira em 7 dias corridos**
- 🟢 **Maturidade técnica equivalente ao Inter** (OAuth2 + mTLS + endpoints BACEN-compliant) — quem já fez Inter sabe fazer Itaú em ~70% do tempo
- 🔴 **3 gotchas críticos:** (1) webhook único por chave PIX (CNPJ) — sobrescreve URL anterior, (2) certificado dinâmico renova 60 dias antes do vencimento (365d) → **cron mensal obrigatório**, (3) API "exclusiva Itaú" ≠ API "regulatória BACEN" — webhooks só na regulatória
- 💰 **Tarifa PIX recebido: 1,45% (mín R$ [redacted Tier 0] / máx R$ [redacted Tier 0])** — boleto registrado custa à parte (~R$ [redacted Tier 0]-2,50 por boleto liquidado, varia por contrato PJ)
- 🟢 **Recomendação: ITAÚ ENTRA NA ONDA 1 do top-5** (junto com Inter já-pronto), mas **APÓS sandbox liberado pelo cliente PJ piloto** (cliente fornece credenciais — Wagner não tem conta PJ Itaú)

---

## 1. Identidade da API

| Item | Valor |
|---|---|
| **Portal developer** | [devportal.itau.com.br](https://devportal.itau.com.br) |
| **API canônicas usadas** | (a) **PIX Recebimentos v2** (BACEN-compliant), (b) **Cash Management / Cobrança Full IaaS** (boleto), (c) **Bolecode** (boleto-PIX híbrido) |
| **Versão recomendada** | PIX Recebimentos v2.41.15 (BACEN spec atual) · Cobrança Full IaaS v1 |
| **Status** | GA (produção desde 2020+ Pix, Cobrança Full IaaS desde 2022) |
| **PIX Automático** | Em rollout — disponível pra clientes Itaú Empresas via API desde **junho 2025**, segue padrão BACEN `/rec`, `/solicrec`, `/cobr` |
| **Documentação principal** | [Autenticação mTLS produção](https://devportal.itau.com.br/autenticacao-documentacao) · [Manual Full IaaS Cobrança](https://devportal.itau.com.br/manual-de-integracao-full-iaas) · [Certificado dinâmico](https://devportal.itau.com.br/certificado-dinamico) · [Autosserviço credenciais](https://devportal.itau.com.br/certificado-dinamico-credenciais) |

**Particularidade Itaú:** existem **duas trilhas de API** que coexistem:

1. **API "Regulatória" (BACEN-compliant Pix v2)** — endpoints padrão `/v2/cob`, `/v2/cobv`, `/v2/webhook`, `/v2/pix` — funciona igual em qualquer PSP (Inter, BB, Caixa, Santander) — **webhook funciona aqui**
2. **API "Exclusiva Itaú"** — endpoints proprietários — **NÃO permite registrar webhook pra status payment** (gotcha documentado em [bacen/pix-api#589](https://github.com/bacen/pix-api/issues/589))

**Decisão de design pro `ItauDriver`:** usar **somente trilha regulatória** (BACEN v2) — código fica portável e webhook funciona.

---

## 2. Endpoints

| Ambiente | Token URL | API base PIX Recebimentos | API base Cobrança |
|---|---|---|---|
| **Sandbox** | `https://devportal.itau.com.br/api/jwt` | `https://devportal.itau.com.br/sandboxapi/pix_recebimentos_ext_v2/v2` | `https://devportal.itau.com.br/sandboxapi/cash_management_ext` |
| **Produção** | `https://sts.itau.com.br/api/oauth/token` (ou `https://sts.itau.com.br/as/token.oauth2` se private_key_jwt) | `https://secure.api.itau/pix_recebimentos/v2` | `https://secure.api.itau/cash_management` |

**Sandbox aberto?** ❌ **NÃO.** Diferente do BCB ou OpenAPI sandbox livre — **precisa solicitação formal** pelo gerente PJ Itaú. Token temporário chega por email em 1 dia útil, validade 7 dias corridos pra emitir credencial/certificado. Fonte: [primeiros-passos](https://devportal.itau.com.br/como-comecar) + [autosserviço credenciais](https://devportal.itau.com.br/certificado-dinamico-credenciais).

**Healthcheck endpoint:** mesmo do token issuance — `POST {token_url}` com `grant_type=client_credentials` e olhar `access_token` na resposta. Identico ao pattern do `InterDriver::healthCheck()`.

---

## 3. Autenticação

### Esquema

Itaú suporta **duas variantes OAuth2 + mTLS**:

| Variante | Quando usar | Complexidade |
|---|---|---|
| **`client_credentials` + mTLS** | Cobrança Full IaaS, Bolecode, PIX Recebimentos "exclusiva" | 🟢 Baixa — bem similar Inter |
| **`private_key_jwt` + mTLS** | PIX Regulatório (Direto BACEN), Open Finance, alguns endpoints novos | 🟡 Média — precisa assinar JWT RS256 com private key + claim `exp <= now+300s` |

**Para `ItauDriver` v1:** adotar **`client_credentials` + mTLS** (igual ao Inter, contrato `EmitirCobrancaInput` já encaixa). Migrar pra `private_key_jwt` só se cliente piloto exigir PIX Direto.

### Obtenção de credenciais (caminho do cliente PJ)

1. Cliente Itaú PJ aciona **gerente de relacionamento** → pede acesso a "API Cobrança/Bolecode/PIX Recebimentos"
2. Gerente abre solicitação no time **OfficerCash / Backoffice Itaú**
3. Cliente recebe email com **client_id + token temporário** (válido **7 dias corridos**) — credenciais PIX/Bolecode chegam **não-criptografadas**, credenciais Cobrança chegam **criptografadas com chave focal point**
4. Cliente acessa [devportal autosserviço](https://devportal.itau.com.br/certificado-dinamico-credenciais) → gera CSR → recebe **certificado dinâmico** válido **365 dias**
5. Cliente cadastra credenciais em `/settings/payment-gateways` (wizard 3 steps já existe no oimpresso) — driver `inter` já valida `config_json.client_id + client_secret + certificado_crt + certificado_key`, **Itaú reusa exatamente o mesmo shape**

### Rotação 60 dias — CONFIRMADO? **NÃO exatamente.**

Pesquisa específica indica que o número "60 dias" **não é rotação de `client_secret`**, e sim a **janela de renovação do certificado dinâmico**:

- **Certificado dinâmico:** 365 dias de validade — **pode ser renovado a partir de 60 dias antes do vencimento até 1 dia antes** ([fonte oficial](https://devportal.itau.com.br/certificado-dinamico))
- **Token temporário onboarding:** 7 dias corridos
- **Access token JWT:** 300 segundos (5 minutos) — `tokenCache` in-memory funciona, mas idealmente Redis com TTL=expires_in
- **`client_secret`:** **sem rotação documentada** — não há política mandatória de rotação como Salesforce/Microsoft impõem

**Mitigação pro oimpresso (Tier 0):**

1. **Cron diário** `payment-gateway:cert-expiry-check` → lista credentials com `cert_expires_at < now+45d` → manda WhatsApp pro responsável financeiro do cliente
2. **Health check** existing já valida token em cada uso — vai falhar com 401 quando cert expirar, mas o ideal é warning **30d antes**
3. **Wizard `/settings/payment-gateways`** ganha campo `cert_expires_at` (datepicker) + UI banner amarelo se <60d, vermelho se <15d

### Homologação — tempo médio

Documentação Itaú não cita prazo SLA. Relatos de fóruns ([Casa do Desenvolvedor](https://forum.casadodesenvolvedor.com.br/topic/44779-resolvido-api-ita%C3%BA-pix-403-authentication-failed/)) e integradores ([Vindi](https://atendimento.vindi.com.br/hc/pt-br/articles/360026491192), [TecnoSpeed](https://atendimento.tecnospeed.com.br/hc/pt-br/articles/27385139166487-341-Ita%C3%BA)) indicam:

- **3-15 dias úteis** entre solicitação ao gerente e credenciais emitidas
- **+2-5 dias** entre teste sandbox OK e liberação produção
- **Total realista: 1-3 semanas calendário** por cliente piloto

---

## 4. Capacidades

| Capacidade | Suporta? | Endpoint | Notas |
|---|---|---|---|
| **Boleto registrado** | ✅ Sim | `POST /cash_management_ext/bank-slips` (Cobrança Full IaaS) | Carteiras 109/112/121/175/180/198 — depende de contrato PJ |
| **PIX cob (imediata)** | ✅ Sim | `PUT /v2/cob/{txid}` (BACEN regulatório) | Padrão Pix v2.41.15 — payload idêntico Inter/BB/Caixa |
| **PIX cobv (com vencimento)** | ✅ Sim | `PUT /v2/cobv/{txid}` | Padrão BACEN |
| **PIX Automático (recv)** | 🟡 Parcial — em rollout 2025 | `PUT /v2/rec/{idRec}` + `PUT /v2/cobr/{txid}` | Disponível Itaú Empresas via API desde [jun/2025](https://www.itau.com.br/empresas/pix/pix-automatico) — usar `BcbPixDriver` dedicado se cliente exigir Res. BCB 380 puro |
| **Bolecode (boleto + PIX híbrido)** | ✅ Sim — diferencial Itaú | API Bolecode própria | Cliente boleta normal mas pagador pode ler QR PIX e cair no mesmo `txid` — UX superior |
| **Cartão crédito/débito** | ❌ **NÃO** | — | Itaú não oferece adquirência via API — usar Asaas/Pagar.me |
| **Refund / devolução PIX** | ✅ Sim | `PUT /v2/pix/{e2eid}/devolucao/{id}` | Padrão BACEN — espelha Inter |
| **Cancelar boleto** | ✅ Sim | endpoint Cobrança Full IaaS | Aceita motivos: BAIXA, ACERTOS, A_PEDIDO_CLIENTE |
| **Webhook callback** | ✅ Sim (regulatório) / ❌ Não (exclusiva) | `PUT /v2/webhook/{chave_pix}` | Eventos: cobrança paga, devolução, expirada. **1 webhook por chave PIX** — gotcha crítico abaixo |

---

## 5. Limites operacionais

| Item | Valor | Fonte |
|---|---|---|
| **Rate limit** | **Não documentado publicamente** — relatos comunitários sugerem ~600 req/min por credencial em produção (igual ao limite BACEN suggested) | [bacen/pix-api](https://github.com/bacen/pix-api/discussions/266) |
| **Quota grátis** | Não há tier grátis — billing por transação |
| **PIX recebido (tarifa Itaú)** | **1,45%** do valor recebido · mínimo **R$ [redacted Tier 0]** · máximo **R$ [redacted Tier 0]** por transação | [Tarifas PSPs Pix](https://github.com/bacen/pix-api/discussions/266) |
| **Boleto registrado** | ~R$ [redacted Tier 0]-2,50 por boleto liquidado (varia contrato PJ, sem tarifa por emissão) | Tabela tarifas Itaú PJ |
| **Bolecode** | Tarifa **boleto** se pago via boleto, tarifa **PIX 1,45%** se pago via QR | Manual Bolecode |
| **Token JWT expiração** | 300 segundos (5 min) | [autenticacao-documentacao](https://devportal.itau.com.br/autenticacao-documentacao) |
| **Timeout recomendado** | 30s pra Cobrança · 10s pra OAuth (mesmo do Inter) |
| **Retry strategy** | Não há doc oficial — usar **HttpClientFactory** do oimpresso já existente (handles 429 com backoff exponencial — aplicado em Onda 4e auditoria 2026-05-23) |

---

## 6. Gotchas conhecidos

### 🔴 G1 — Webhook único por chave PIX (sobrescreve)

**Sintoma:** Cliente registra webhook URL A. Depois muda pra URL B. **URL A para de receber, sem warning.** Itaú só armazena o último registro por chave PIX. Documentação indica que a chave do webhook **deve ser CNPJ** e só a última URL conta.

**Mitigação:** No oimpresso, **um único endpoint multi-tenant** `/api/webhooks/itau/{credential_id}` que recebe TUDO e routa internamente por `credential_id` extraído da URL. Já é o pattern usado em `WebhookProcessor.php` para outros drivers.

**Fonte:** [bacen/pix-api#411](https://github.com/bacen/pix-api/discussions/411) (erro 500 ao cadastrar webhook duplicado) · [bacen/pix-api#589](https://github.com/bacen/pix-api/issues/589) (webhook só na API regulatória, não na exclusiva)

### 🔴 G2 — Certificado renova 60d antes — **cron mensal obrigatório**

**Sintoma:** Cliente integra, esquece. 365 dias depois, **toda transação trava com 401/SSL handshake failed.**

**Mitigação:**
- Migration adiciona `cert_expires_at TIMESTAMPTZ NULL` em `payment_gateway_credentials` (já existe? confirmar — senão escopo da Onda 4f-Itaú)
- Cron `payment-gateway:cert-expiry-check` daily 09:00 BRT → query `WHERE cert_expires_at < NOW() + INTERVAL '45 days' AND gateway_key = 'itau'` → manda notificação multicanal (WhatsApp + email) pro `business.contact_financeiro`
- UI: banner amarelo se < 60d, vermelho se < 15d, bloqueio com modal de upload se < 1d
- Health check existing já detecta token failure → registra incidente Tier 1

### 🟡 G3 — Sandbox NÃO é self-service

**Sintoma:** Dev quer testar driver localmente, abre `devportal.itau.com.br`, **não há botão "criar credencial sandbox"** como Inter ou Stripe. Precisa gerente PJ Itaú.

**Mitigação pro dev oimpresso:**
- Implementar driver com **Http::fake() massivo nos Pest tests** (espelha pattern `InterDriverTest.php` se existe — auditar)
- Usar **fixtures de payload BACEN** (cob, cobv, pix, devolução) capturadas de outros bancos (Inter sandbox) — payloads são quase idênticos por serem padrão BACEN
- Smoke test real só roda quando cliente piloto fornece credenciais (registrar em `MULTI-TENANT-PILOT-PROGRAM.md`)

### 🟡 G4 — PHP cURL + mTLS bug "Falha ao Extrair TokenInfo filter failed"

**Sintoma:** Token OK no Postman, mas falha em cURL/Guzzle com 401 "TokenInfo filter failed". Causa: chain CA incompleta ou ordem cert/key invertida.

**Mitigação:** Driver Inter já tem `mtlsOptions()` que usa `cert` + `ssl_key` separados (não PFX), igual ao que Itaú exige. Reusar 1:1.

**Fonte:** [bacen/pix-api#531](https://github.com/bacen/pix-api/issues/531)

### 🟡 G5 — Sandbox vs Prod divergem em headers obrigatórios

**Sintoma:** Algumas APIs Itaú em sandbox **não exigem** `x-itau-correlationID` e `x-itau-flowID`, mas produção **rejeita 400** se faltarem.

**Mitigação:** Sempre enviar ambos headers (uuidv4 por request) em sandbox **E** prod. Custo zero, evita surpresa F3→F5.

**Fonte:** [autenticacao-documentacao](https://devportal.itau.com.br/autenticacao-documentacao) cita headers como "opcional" mas comunidade reporta obrigatoriedade em prod.

### 🟢 G6 — `private_key_jwt` exige `exp <= 300s`

**Sintoma:** JWT assinado com expiração > 5min → 401 invalid token.

**Mitigação:** Se/quando adotar `private_key_jwt` (Onda 4f-Itaú-v2 só se exigido), gerar JWT com `iat=now, exp=now+300, jti=uuid` — biblioteca `lcobucci/jwt` já presente no Laravel.

---

## 7. Comparação com `InterDriver`

| Dimensão | Inter | Itaú | Verdict |
|---|---|---|---|
| **Auth scheme** | OAuth2 client_credentials + mTLS | OAuth2 client_credentials + mTLS (idêntico) ou private_key_jwt | 🟰 Igual (v1) |
| **Sandbox** | Auto-serviço web, gera credencial em minutos | Solicitação formal gerente + email + 7d token temp | 🔴 Inter superior |
| **Boleto API** | REST v3 limpa, payload simples | REST Cash Management Full IaaS — mais campos obrigatórios | 🟡 Inter levemente superior |
| **PIX cob/cobv** | BACEN v2 padrão | BACEN v2 padrão (idêntico) | 🟰 Igual |
| **PIX Automático** | Em rollout | Em rollout (lançado abr/2025) | 🟰 Igual |
| **Refund** | PIX sim (parcial), boleto NÃO | PIX sim (parcial), boleto via cancelamento+novo | 🟰 Igual |
| **Webhook** | URL única por credencial — UX OK | URL única por chave PIX — sobrescreve sem aviso | 🔴 Inter superior |
| **Diferencial** | API mais developer-friendly do mercado BR | **Bolecode (boleto+PIX híbrido)** — UX pagador superior | 🟢 Itaú tem killer feature |
| **Tarifa PIX recebido** | 0,99% (até R$ [redacted Tier 0]) | 1,45% (mín R$ [redacted Tier 0] / máx R$ [redacted Tier 0]) | 🔴 Inter mais barato |
| **Base instalada Brasil** | ~30 milhões correntistas | ~70 milhões correntistas (líder PJ) | 🟢 Itaú maior alcance PJ |

**Verdict global:** Itaú está **levemente abaixo do Inter em DX (Developer Experience)** mas **acima em alcance comercial** (mais clientes Itaú entre PMEs gráficas brasileiras). Implementação reutiliza 80% do código `InterDriver`.

---

## 8. Esforço estimado recalibrado (ADR 0106 fator 10x IA-pair)

| Item | Estimate raw | Recalibrado (÷10 + margem ×2) |
|---|---|---|
| `ItauDriver.php` (boleto + PIX cob/cobv + cancelar + consultar + healthCheck + processWebhook) | ~650 LOC | **3-4h IA-pair** (vs 30-40h dev sozinho) |
| Pest tests com Http::fake | ~400 LOC, ~25 testes | **2-3h IA-pair** |
| Migration `cert_expires_at` (se não existir) | ~30 LOC | **15min** |
| Command `payment-gateway:cert-expiry-check` | ~80 LOC + 5 testes | **1h** |
| Observability (logs ALERT + métrica Prometheus opcional) | ~50 LOC | **30min** |
| **TOTAL CODÁVEL (IA-pair):** | | **~7-9h** (1 dia-pair recalibrado) |
| Homologação (humano-limitado, relógio mundo real) | 1-3 semanas calendário | **mantém — cliente piloto controla** |
| Onboarding cliente piloto (apoio Felipe/Maiara) | 2-4h suporte | **mantém** |
| **TOTAL CALENDÁRIO realista:** | | **2-3 semanas** (gargalo = cliente PJ + Itaú backoffice) |

**Custo runtime adicional (rotação cert 60d):**
- Cron 1 query/dia → ~0 custo DB
- Notificação WhatsApp por client/ano → 1× ano × N clientes × R$ [redacted Tier 0] = irrelevante
- **TOTAL: < R$ [redacted Tier 0]/mês mesmo com 100 clientes Itaú**

---

## 9. Viabilidade verdict

# ✅ VIÁVEL — RECOMENDADO ONDA 1 do pacote top-5

**Justificativa:**
- API REST moderna e BACEN-compliant
- Reuso ~80% do `InterDriver` (mesmo OAuth2 + mTLS + payloads BACEN v2)
- Bolecode é diferencial competitivo real (UX pagador superior — boleto + PIX no mesmo doc)
- Base instalada PJ Itaú dominante no segmento gráfico/PME brasileiro

**Caveats:**
- 🟡 Sandbox FECHADO — dev codifica com fakes, validação real só com cliente piloto
- 🟡 Cron renovação cert 60d é **mandatório** (não é nice-to-have)
- 🟡 PIX Automático ainda em rollout — implementar via `BcbPixDriver` regulado se cliente exigir agora; `ItauDriver::emitirPixAutomatico()` retorna `DriverNotSupportedException` v1

---

## 10. Recomendação concreta

### Ordem implementação top-5

| Onda | Banco | Justificativa |
|---|---|---|
| **Onda 4f-1** | **Itaú** (este dossier) | Maior alcance PJ + reuso Inter + Bolecode diferencial |
| **Onda 4f-2** | **Bradesco** | 2º maior PJ BR + API similar (mTLS + OAuth2 + private_key_jwt) |
| **Onda 4f-3** | **BB (Banco do Brasil)** | API muito boa (auto-serviço), porém base PJ menor que Itaú/Bradesco |
| **Onda 4f-4** | **Santander** | API mTLS funcional mas onboarding pesado (similar Itaú) |
| **Onda 4f-5** | **Caixa** | API mais "burocrática", PIX Automático maduro — última pois maior fricção |

### Subset funcional `ItauDriver` v1 (escopo mínimo)

Cobrir **somente o que cliente piloto vai usar nos 30 primeiros dias**:

- ✅ `emitirBoleto` (Cobrança Full IaaS)
- ✅ `emitirPix` tipo `cob` e `cobv` (BACEN regulatório v2)
- ✅ `cancelar` boleto
- ✅ `consultar` (boleto + PIX)
- ✅ `healthCheck` (token issuance)
- ✅ `processWebhook` (parse + map pra Cobranca)
- ❌ `emitirPixAutomatico` → throw `DriverNotSupportedException` ("Use BcbPixDriver")
- ❌ `cobrarCartao` → throw `DriverNotSupportedException` ("Itaú não emite cartão. Use Asaas.")
- ✅ `refund` PIX (devolução) — boleto throw `DriverNotSupportedException`

### Subset Bolecode v2 (Onda 4f-1-b)

Só implementar **se cliente piloto pedir explicitamente** — esquema BACEN é o mesmo, só muda endpoint específico Bolecode + flag UI.

### Pré-requisitos antes do dev começar

1. ✅ Confirmar com Wagner: cliente PJ Itaú piloto identificado (1-2 nomes)
2. ✅ Cliente aciona gerente Itaú → solicita acesso "API Cobrança + Bolecode + PIX Recebimentos"
3. ✅ Migration `cert_expires_at` revisada (provavelmente já feita em Onda 4e)
4. ✅ `HttpClientFactory` já tem retry+429 (sim — Onda 4e auditoria 2026-05-23)
5. ✅ Wizard `/settings/payment-gateways` ganha tab "Itaú" — mesma estrutura tab "Inter" + campo cert_expires_at + upload cert/key separados

### Risco residual

- 🟡 **Itaú backoffice lento:** se piloto demorar > 3 semanas, refatorar pra começar **Bradesco em paralelo** (mesmo padrão técnico)
- 🟡 **Rate limit não documentado:** colocar circuit breaker no `ItauDriver` (5 falhas consecutivas → cool-down 5min) — pattern já existe? Auditar `HttpClientFactory`
- 🟢 **Multi-tenant Tier 0:** `PaymentGatewayCredential` já tem `business_id` via global scope (ADR 0093) — sem mudança necessária

---

## Apêndice A — Fontes pesquisadas

### Documentação oficial Itaú
- [devportal.itau.com.br — Autenticação mTLS produção](https://devportal.itau.com.br/autenticacao-documentacao)
- [devportal.itau.com.br — Manual Integração Full IaaS Cobrança](https://devportal.itau.com.br/manual-de-integracao-full-iaas)
- [devportal.itau.com.br — Certificado dinâmico](https://devportal.itau.com.br/certificado-dinamico)
- [devportal.itau.com.br — Autosserviço de credenciais e certificado](https://devportal.itau.com.br/certificado-dinamico-credenciais)
- [devportal.itau.com.br — Primeiros passos](https://devportal.itau.com.br/como-comecar)
- [devportal.itau.com.br — Como obter credenciais e gerar certificado](https://devportal.itau.com.br/como-obter-as-credenciais-e-gerar-o-certificado-dinamico)
- [devportal.itau.com.br — Guia criação credencial+certificado](https://devportal.itau.com.br/guia-de-criacao-de-credencial-e-certificado)
- [itau.com.br/empresas/pix/pix-automatico — Itaú Empresas](https://www.itau.com.br/empresas/pix/pix-automatico)
- [itau.com.br/itaubba-pt/pix/pix-automatico — Itaú BBA](https://www.itau.com.br/itaubba-pt/pix/pix-automatico)

### Implementações de referência (GitHub)
- [tiagoskabrazil/pixrecebimentos-itau](https://github.com/tiagoskabrazil/pixrecebimentos-itau) — Cliente PHP PIX Recebimentos v2.41.15
- [leandroferreirama/api-itau](https://github.com/leandroferreirama/api-itau) — Wrapper API Itaú
- [eduardokum/laravel-boleto](https://github.com/eduardokum/laravel-boleto) — Pacote Laravel boleto (CNAB legacy, não API REST — útil pra estruturas)
- [matheushack/itauboleto](https://github.com/matheushack/itauboleto) — Boleto registrado Itaú

### Issues comunidade BACEN (gotchas)
- [bacen/pix-api#589 — Webhooks API Exclusiva Itaú](https://github.com/bacen/pix-api/issues/589)
- [bacen/pix-api#411 — Cadastrar webhook erro 500](https://github.com/bacen/pix-api/discussions/411)
- [bacen/pix-api#531 — cURL PHP mTLS](https://github.com/bacen/pix-api/issues/531)
- [bacen/pix-api#399 — Connect to URL filter failed sandbox](https://github.com/bacen/pix-api/issues/399)
- [bacen/pix-api#537 — Participante Direto/Indireto OAuth2 JWT](https://github.com/bacen/pix-api/issues/537)
- [bacen/pix-api#119 — Dúvida Client Credential Flow JWT](https://github.com/bacen/pix-api/issues/119)
- [bacen/pix-api#266 — Tarifas Pix PSPs](https://github.com/bacen/pix-api/discussions/266)
- [bacen/pix-api#187 — Lista PSPs recebedores](https://github.com/bacen/pix-api/discussions/187)

### Integradores comerciais (DX comparativo)
- [OpenPix — Integração Itaú](https://developers.openpix.com.br/en/docs/bank-integrations/integration-itau-bank)
- [Sotreq Developers — API PIX Itaú](https://dev.sotreq.com.br/api-portal/content/api-guide/pix-itau)
- [Omie — Configurando Bolecode Itaú](https://ajuda.omie.com.br/pt-BR/articles/7225464-configurando-a-integracao-com-o-itau-bolecode-via-api)
- [Omie — Configurando PIX Itaú](https://ajuda.omie.com.br/pt-BR/articles/6817569-configurando-a-integracao-com-o-itau-pix-via-api)
- [HubSoft Wiki — Integração PIX Itaú](https://wiki.hubsoft.com.br/pt-br/modulos/configuracao/integracao/pix/itau)
- [Vindi — Integração Boleto Itaú V2](https://atendimento.vindi.com.br/hc/pt-br/articles/360026491192-Integra%C3%A7%C3%A3o-Boleto-Ita%C3%BA-V2)
- [TecnoSpeed — 341 Itaú](https://atendimento.tecnospeed.com.br/hc/pt-br/articles/27385139166487-341-Ita%C3%BA)
- [Loja5 — Solicitar dados PJ ao Itaú](https://loja5.zendesk.com/hc/pt-br/articles/38055426585869)

### Fóruns/troubleshooting
- [Casa do Desenvolvedor — API Itaú PIX 403 Authentication](https://forum.casadodesenvolvedor.com.br/topic/44779-resolvido-api-ita%C3%BA-pix-403-authentication-failed/)
- [openbankingtracker.com — Itaú for Developers](https://www.openbankingtracker.com/provider/itau)

### Documentação PIX Automático (cross-reference)
- [Banco Central — Guia Pix Automático](https://liftchallenge.bcb.gov.br/content/estabilidadefinanceira/pix/automatico/guia_pix_automatico.pdf)
- [Efí Pay — API Pix Automático](https://dev.efipay.com.br/en/docs/api-pix/pix-automatico/)
- [Inter — API Pix Automático](https://developers.inter.co/references/pix-automatico)
- [Caixa — Manual Técnico Pix Automático](https://www.caixa.gov.br/empresa/pagamentos-recebimentos/recebimentos/pix-automatico/Documents/api-pix-automatico-manual-tecnico.pdf)
- [Itaú Unibanco lança pagamento recorrente Pix (TI Inside abr/2025)](https://tiinside.com.br/14/04/2025/itau-unibanco-lanca-pagamento-recorrente-com-pix/)

---

**FIM DO DOSSIER.** Dúvidas/aprovação → Wagner para próximo passo de criar ADR de onda (Onda 4f-1 Itaú) e/ou cycle no MCP (`tasks-create module:PaymentGateway parent:Onda4f`).
