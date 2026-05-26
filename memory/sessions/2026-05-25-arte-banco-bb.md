# ARTE — Banco do Brasil (BB) Open Banking API — estado-da-arte 2026-05-25

> **Sessão:** dossier estado-da-arte API BB Cobrança + PIX pra implementar `BBDriver` no `Modules/PaymentGateway` (pacote top-5 bancos aprovado por Wagner).
> **Driver de referência:** `Modules/PaymentGateway/Services/Drivers/InterDriver.php` (~600 LOC, Onda 4a, contract `PaymentDriverContract`).
> **Cliente alvo:** PJ do oimpresso com conta-corrente BB (Wagner não tem BB pessoal — implementação é pros clientes cadastrarem em `/settings/payment-gateways`).
> **Recalibração:** ADR 0106 (fator 10x IA-pair + margem 2x).

---

## TL;DR (6 linhas)

- **Viabilidade:** ✅ **VIÁVEL com ressalva** — API REST moderna OAuth2 + mTLS, sandbox aberto sem KYC, docs OK; mas homologação prod exige cadastro CNPJ + gerente PJ liberar convênio cobrança (2-5 dias úteis BB + ~7-15 dias úteis gerência).
- **Maturidade:** considerada **historicamente a mais sólida do mercado** pra boleto registrado (CIP), mas **DX abaixo do Inter** (suporte mais lento, fórum confuso, gw-app-key não-documentado direito).
- **Esforço total:** ~12-18h codáveis (com IA-pair) + 7-15 dias humano-limitados (homologação banco — cliente faz, não nós).
- **Recomendação:** **BB deve vir #2 do pacote**, depois de Bradesco (não primeiro) — porque ainda que clientes oimpresso tenham BB, o lead-time de convênio é igual ou pior que Bradesco, e o gw-app-key é gotcha forte que vale fazer em segundo (curva aprendida).
- **3 gotchas mais críticos:** (1) `gw-app-key` ≠ `client_id` e ninguém documenta isso direito; (2) convênio cobrança CIP é processo gerencial off-API; (3) webhook só notifica BAIXA OPERACIONAL — outros eventos precisam polling.
- **Comparado a Inter:** BB é **igual em boleto registrado** (CIP nativo), **abaixo em PIX cob/cobv DX** (Inter tem docs mais limpos), **acima em capilaridade PJ Brasil** (maioria dos clientes oimpresso já tem BB).

---

## 1. Identidade da API

| Item | Valor | Fonte |
|---|---|---|
| Portal developer | `developers.bb.com.br` (site institucional) + `app.developers.bb.com.br` (cadastro app) + `apoio.developers.bb.com.br` (sandbox/docs) | [Portal Developers BB](https://www.bb.com.br/site/developers/) |
| API name canônico | **API Cobrança** (boleto + PIX híbrido) + **API Pix** (cob/cobv standalone) — APIs separadas | [API de Cobrança](https://www.bb.com.br/site/developers/bb-como-servico/api-cobranca/) + [API Pix](https://www.bb.com.br/site/developers/bb-como-servico/api-pix/) |
| Versão atual | API Cobrança **v2** (REST + JSON) · API Pix **v2** (alinhada manual BCB) | [DBSeller SDK](https://github.com/DBSeller/sdk-api-pix-bb) |
| Status | **GA** (produção estável desde 2018; v2 estável desde 2020) | [API Reference BB OAuth](https://api-developers.bb.com.br/docs/oauth/pt-BR/auth-code.html) |
| Reputação | "API Cobrança BB é referência do mercado pra boleto CIP nativo", mas DX (docs/suporte) considerado abaixo de Inter; ACBr forum tem threads recorrentes de 401/403 sem resposta clara | [Maycon Braga — 3 APIs PIX mais fáceis](https://mayconbraga.com.br/blog/conteudo/as-3-apis-de-pix-mais-faceis-de-integrar) + [Reclame Aqui — Timeout API BB](https://www.reclameaqui.com.br/banco-do-brasil/timeout-durante-uso-de-api_4V8NS9mDo5iKNfhR/) |

---

## 2. Endpoints

| Ambiente | Base URL Cobrança | Base URL PIX | Token URL |
|---|---|---|---|
| **Produção** | `https://api.bb.com.br/cobrancas/v2` | `https://api.bb.com.br/pix-bb/v1` (PIX cob) ou `https://api.bb.com.br/pix/v2` (BCB padrão) | `https://oauth.bb.com.br/oauth/token` |
| **Sandbox/Homologação** | `https://api.sandbox.bb.com.br/cobrancas/v2` | `https://api.sandbox.bb.com.br/pix-bb/v1` | `https://oauth.sandbox.bb.com.br/oauth/token` |
| **Homologação alt (HM)** | `https://api.hm.bb.com.br/cobrancas/v2` | `https://api.hm.bb.com.br/pix-bb/v1` | `https://oauth.hm.bb.com.br/oauth/token` |

- **Sandbox aberto sem KYC?** ✅ **SIM** — basta cadastro grátis em `app.developers.bb.com.br` com e-mail; CNPJ obrigatório só na fase "enviar pra produção" — confirmado em [Cigam Wiki — Open Banking BB](https://www.cigam.com.br/wiki/index.php/GF_-_Como_Fazer_-_Open_Banking_para_Banco_do_Brasil) + [Apoio Developers BB Sandbox](https://apoio.developers.bb.com.br/sandbox/spec/post/5fe9e7f13b02bd0012eca9f1).
- **Healthcheck:** não há endpoint dedicado — convenção é POST no token endpoint com timeout 10s (idêntico ao InterDriver).
- **Dados fictícios pra sandbox:** BB exige CPFs/CNPJs específicos pré-cadastrados (publicados em "Dados Fictícios para Teste" no portal) — usar CPF/CNPJ aleatório em sandbox **resulta em 400 com mensagem genérica** (gotcha #4).

---

## 3. Autenticação

| Item | Detalhe | Fonte |
|---|---|---|
| Esquema | **OAuth2 client_credentials** (grant_type) + **gw-app-key** como header obrigatório separado + **mTLS opcional** em prod | [HubSoft — Integração PIX BB](https://wiki.hubsoft.com.br/pt-br/modulos/configuracao/integracao/pix/banco-do-brasil) |
| Headers obrigatórios | `Authorization: Bearer <token>` + `X-Application-Key: <gw-app-key>` (prod) ou `gw-dev-app-key` (sandbox) | [DBSeller SDK README](https://github.com/DBSeller/sdk-api-pix-bb) |
| Como obter credenciais | Cadastro auto-serviço em `app.developers.bb.com.br` (grátis, instant). Pra prod: **gerente PJ presencial libera convênio cobrança CIP** (off-API, 7-15 dias úteis) | [Cigam Wiki — BB Open Banking](https://www.cigam.com.br/wiki/index.php/GF_-_Como_Fazer_-_Open_Banking_para_Banco_do_Brasil) + [Linx Pay Hub — Credenciais BB PDF](https://static.linxpayhub.com.br/qrlinx/pdf/Credenciais_BB.pdf) |
| Rotação de secret | **Manual** via portal — sem expiração automática (gotcha potencial de segurança) | [Portal Developers BB](https://www.bb.com.br/site/developers/conheca-nossas-solucoes/) |
| Tempo médio homologação | **2-5 dias úteis (BB)** pra aprovar app + **~3 dias úteis** pra deploy certificado mTLS + **7-15 dias úteis (gerência PJ)** pra liberar convênio. **Total realista: 10-25 dias úteis** | [Apoio Developers BB](https://apoio.developers.bb.com.br/) |
| Scopes Cobrança | `cobrancas.boletos-info` (leitura) + `cobrancas.boletos-requisicao` (escrita) + `cobrancas.boletos-baixa` (cancelamento) | [Kobana — Como criar conexão BB](https://ajuda.kobana.com.br/pt-BR/articles/9820688-como-criar-uma-conexao-com-a-api-do-banco-do-brasil) |
| Scopes PIX | `pix.arrecadacao-requisicao` + `pix.arrecadacao-info` + `pix.read` + `pix.write` (varia por versão) | [DBSeller SDK](https://github.com/DBSeller/sdk-api-pix-bb) |
| mTLS | **Opcional em sandbox / Obrigatório em prod** — certificado em `.PEM` enviado pelo portal | [Vigo Wiki — Envio certificado PFX BB](https://wiki.vigo.com.br/doku.php?id=enviar_bb) |

---

## 4. Capacidades

| Capacidade | Suporta? | Notas | Fonte |
|---|---|---|---|
| Boleto registrado (CIP) | ✅ **Nativo de referência** | numeroConvenio + numeroCarteira (17 padrão) + numeroVariacaoCarteira (35 padrão); BB tem o CIP mais antigo do Brasil | [gustavohmelo/integracao-bb-cobranca](https://github.com/gustavohmelo/integracao-bb-cobranca) + [openboleto](https://github.com/openboleto/openboleto) |
| PIX cobrança imediata (cob) | ✅ | `POST /cob/{txid}` ou via API Pix BB customizada (`/pix-bb/v1`) — devs preferem padrão BCB | [insign/integracao-bb](https://github.com/insign/integracao-bb) |
| PIX com vencimento (cobv) | ✅ | `PUT /cobv/{txid}` (padrão BCB Manual Pix v2.9.0) | [BCB Manual Padrões Pix v2.9.0](https://www.bcb.gov.br/content/estabilidadefinanceira/pix/Regulamento_Pix/II_ManualdePadroesparaIniciacaodoPix.pdf) |
| Boleto híbrido (boleto + PIX) | ✅ **Diferencial BB** | Mesma API Cobrança v2 retorna `linhaDigitavel` + `pixCopiaECola` no mesmo POST se convênio configurado | [IXCSoft Wiki — API BB Webhook](https://wiki-erp.ixcsoft.com.br/documentacao/guias-tutoriais/carteira-de-cobranca/integracoes-carteira-de-cobranca/integracoes-bancarias---api/integracoes/api-banco-do-brasil.html) |
| PIX Automático recv (Res. BCB 380/2024 + 402/2024) | ✅ **GA desde 16/06/2025** | BB foi 1ª instituição a ampliar pra todos clientes em maio/2025 — agora obrigatório por norma | [Agência Brasil — BB amplia PIX Automático](https://agenciabrasil.ebc.com.br/economia/noticia/2025-05/banco-do-brasil-amplia-pix-automatico-para-todos-os-clientes) + [Mattos Filho — Novas normas BCB Pix](https://www.mattosfilho.com.br/unico/bcb-novas-normas-pix/) |
| Cartão crédito/débito | ❌ Não pela API Cobrança | BB tem Cielo (joint venture) — integração via SDK Cielo separada, fora do escopo PaymentGateway atual |
| Refund / cancelamento | ✅ Cancelamento sim · ⚠️ Refund PIX via `/cob/{txid}/devolucao/{idDev}` (padrão BCB), refund boleto inexistente (igual Inter) | [BCB Manual Pix](https://www.bcb.gov.br/content/estabilidadefinanceira/pix/Regulamento_Pix/II_ManualdePadroesparaIniciacaodoPix.pdf) |
| Webhook callback | ⚠️ **Parcial** | Apenas evento "BAIXA OPERACIONAL" notificado por webhook; outros estados (REGISTRADO, VENCIDO, BAIXADO) exigem polling via `consultar` | [IXCWiki — Configuração Webhook BB](https://wiki.ixcsoft.com.br/pt-br/Cadastros/Financeiro/Carteira_de_cobran%C3%A7a/apis_banc%C3%A1rias/api_banco_do_brasil/config_webhook_bb) |
| Webhook PIX | ✅ Padrão BCB completo (PUT `/webhook/{chave}` pra registrar) | [BCB Pix API GitHub Discussion #548](https://github.com/bacen/pix-api/discussions/548) |

---

## 5. Limites operacionais

| Item | Valor | Fonte |
|---|---|---|
| Rate limit | **Não publicado oficialmente.** Devs reportam ~60 req/min em sandbox e ~120 req/min em prod por app; throttle silencioso com 429 raro mas existe | [Reclame Aqui — Timeout API BB](https://www.reclameaqui.com.br/banco-do-brasil/timeout-durante-uso-de-api_4V8NS9mDo5iKNfhR/) |
| Quota grátis | **API gratuita** — custo é o boleto/PIX em si (tarifa BB padrão por convênio CIP) — varia ~R$ 1,50-3,50 por boleto, PIX zero recebimento até 2024 (mudou em 2025 com Pix Automático tarifa BCB) | [bacen/pix-api discussion #266](https://github.com/bacen/pix-api/discussions/266) |
| Timeout recomendado | **30s** (BB tem latência reconhecidamente alta — reclame-aqui registra timeouts frequentes; usar mesmo padrão que `HttpClientFactory::make(timeoutSec: 30)`) | [Reclame Aqui — Timeout BB](https://www.reclameaqui.com.br/banco-do-brasil/timeout-durante-uso-de-api_4V8NS9mDo5iKNfhR/) |
| Retry strategy oficial | **Não publicada.** Aplicar exponential backoff padrão (1s, 2s, 4s) pra 5xx + 429; **nunca retry pra 4xx** (401/403) — corrigir requisição | [Forum ACBr — Erro 403 BB](https://www.projetoacbr.com.br/forum/topic/73668-acbrboleto-api-banco-do-brasil-erro-403/) |

---

## 6. Gotchas conhecidos (críticos)

### 🔴 Gotcha #1 — `gw-app-key` ≠ `client_id` (sub-documentado)
- BB exige **DOIS valores separados:** OAuth `client_id`/`client_secret` (Authorization Bearer) + header `X-Application-Key` (ou `gw-app-key` em algumas versões). Ambos vêm do mesmo app, mas devs novatos confundem.
- Sandbox usa `gw-dev-app-key`, produção usa `gw-app-key` — trocar gera 401 silencioso.
- Fonte: [HubSoft Wiki](https://wiki.hubsoft.com.br/pt-br/modulos/configuracao/integracao/pix/banco-do-brasil) + [DBSeller SDK](https://github.com/DBSeller/sdk-api-pix-bb).

### 🔴 Gotcha #2 — Convênio cobrança é processo off-API
- API funciona, mas registro de boleto retorna **400/403 sem convênio CIP ativo** vinculado à conta BB do cliente.
- Convênio é liberado por **gerente PJ presencial** — não há auto-serviço. Tempo: 7-15 dias úteis. Cliente precisa saber `numeroConvenio` (6-7 dígitos), `numeroCarteira` (17 padrão), `numeroVariacaoCarteira` (35 padrão).
- Fonte: [openboleto carteira docs](https://github.com/openboleto/openboleto) + [Memocash FAQ BB PIX](https://faq.memocashsolucoes.com.br/knowledgebase/integracao-pix-banco-do-brasil).

### 🔴 Gotcha #3 — Webhook só notifica BAIXA OPERACIONAL
- Diferente do Inter (notifica múltiplos eventos), BB Cobrança v2 só envia webhook quando boleto é PAGO (evento "BAIXA OPERACIONAL"). Estados intermediários (REGISTRADO, ATRASADO, BAIXADO_MANUAL) exigem **polling via `consultar`**.
- Implicação técnica: `BBDriver::processWebhook()` é simples (1 evento), mas precisa job recorrente complementar pra detectar vencimento/cancelamento.
- Fonte: [IXC Wiki Webhook BB](https://wiki.ixcsoft.com.br/pt-br/Cadastros/Financeiro/Carteira_de_cobran%C3%A7a/apis_banc%C3%A1rias/api_banco_do_brasil/config_webhook_bb).

### 🟡 Gotcha #4 — Sandbox exige CPFs/CNPJs fictícios pré-cadastrados
- Não aceita CPF/CNPJ aleatório válido (algoritmo correto) — retorna 400 genérico.
- BB publica lista de "Dados Fictícios para Teste" — usar **apenas esses CPFs/CNPJs** em sandbox.
- Fonte: [Apoio Developers BB Sandbox spec](https://apoio.developers.bb.com.br/sandbox/spec/post/5fe9e7f13b02bd0012eca9f1).

### 🟡 Gotcha #5 — Erro 403 frequente em produção pós-deploy
- Múltiplos relatos no ACBr forum + Reclame Aqui de 403 em produção após app aprovado, causado por **certificado mTLS não associado corretamente** ao app via portal (passo manual fácil de esquecer).
- Solução: re-upload certificado `.PEM` em `app.developers.bb.com.br` > Certificados > "Para consumir APIs" + aguardar 3 dias úteis processamento.
- Fonte: [Projeto ACBr — Erro 403](https://www.projetoacbr.com.br/forum/topic/73668-acbrboleto-api-banco-do-brasil-erro-403/) + [Vigo Wiki — Envio certificado PFX BB](https://wiki.vigo.com.br/doku.php?id=enviar_bb).

### 🟡 Gotcha #6 — Diferença entre `api.bb.com.br/pix-bb/v1` (proprietária) vs `/pix/v2` (BCB padrão)
- BB tem dois sabores de API PIX: proprietária (`pix-bb`) com payload custom (`numeroConvenio`, `codigoGuiaRecebimento`) e padrão BCB (`/pix/v2/cob/{txid}`). **Recomendação:** usar BCB padrão pra reaproveitar lógica do `InterDriver` (mesmo shape `txid`/`pixCopiaECola`/`devolucao`).
- Fonte: [DBSeller SDK README](https://github.com/DBSeller/sdk-api-pix-bb).

---

## 7. Comparação com InterDriver (dimensão a dimensão)

| Dimensão | Inter (referência) | BB | Verdict |
|---|---|---|---|
| **Auth** | OAuth2 + mTLS prod, 1 scope par boleto-cobranca.read/write | OAuth2 + gw-app-key + mTLS prod, scopes mais granulares | **Inter mais simples** (DX) · BB mais granular (segurança) — empate prático |
| **Boleto registrado** | API v3 estável, payload limpo | API v2 estável, payload exige convênio/carteira explícitos | **Empate** — BB tem CIP mais antigo; Inter tem DX melhor |
| **PIX cob (imediata)** | Padrão BCB `/pix/v2/cob/{txid}` (PUT) | Padrão BCB OU proprietário `/pix-bb/v1` | **Empate** se usar BCB padrão; **Inter melhor DX** |
| **PIX cobv (vencimento)** | Padrão BCB suportado | Padrão BCB suportado | Empate |
| **PIX Automático recv** | ✅ Não implementado no driver atual (delegado pra `BcbPixDriver`) | ✅ BB foi 1ª a abrir pra todos clientes (maio/2025) | **BB ligeiramente acima** — pode ser case-leader nesta feature |
| **Refund PIX** | `/pix/v2/cob/{txid}/devolucao/{idDev}` (BCB padrão) | Mesmo endpoint BCB padrão | Empate |
| **Cancelamento boleto** | Endpoint dedicado `/cancelar` com motivos enum | Endpoint baixa com motivos enum diferentes | Empate |
| **Webhook** | Eventos múltiplos (registro, pagamento, vencimento) | Apenas BAIXA OPERACIONAL — polling pra resto | **Inter acima** |
| **Sandbox** | Aberto, sem CNPJ obrigatório, dados livres | Aberto sem CNPJ, mas exige CPFs/CNPJs fictícios pré-cadastrados | **Inter mais conveniente** |
| **Homologação prod** | 2-5 dias úteis Inter + sem convênio off-API necessário | 2-5 dias úteis BB + **7-15 dias gerente PJ convênio** | **Inter MUITO mais rápido** |
| **DX (docs/suporte)** | Considerado o melhor do mercado | Considerado abaixo de Inter; fórum BB tem threads sem resposta | **Inter acima** |
| **Capilaridade PJ Brasil** | Crescente, mas menor base PJ | Maior banco PJ Brasil — maioria oimpresso já tem conta BB | **BB acima** (sinal de cliente) |

**Veredito comparativo:** BB **NÃO é universalmente superior ao Inter** — pelo contrário, em DX/velocidade Inter ganha. BB vence em **base instalada PJ** (a maioria dos clientes oimpresso já tem BB, sinal qualificado [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) e em **PIX Automático recv** (BB foi case-leader). Mito de "API BB é a mais madura" vem do CIP nativo, não da DX.

---

## 8. Esforço estimado recalibrado (ADR 0106 — fator 10x IA-pair + margem 2x)

| Item | LOC / horas | Justificativa |
|---|---|---|
| **`BBDriver.php` (código)** | ~700 LOC (vs 600 Inter — +100 por gw-app-key dual-header + convênio mapping + webhook polling helper) | Reaproveita 80% do InterDriver pattern; lógica nova é só payload boleto BB (numeroConvenio/Carteira) + dois headers OAuth + sabor PIX BCB |
| **`BBDriverTest.php` (Pest)** | ~400 LOC (vs ~300 Inter) | Testar: 8 métodos × cenários (success/4xx/5xx/timeout) + sandbox vs prod URLs + gw-dev-app-key vs gw-app-key + convênio missing |
| **Horas codáveis (IA-pair)** | **12-18h** (fator 10x → equivalente a 5-8 dias humano-sem-IA) | Driver + tests + docs charter; usa template do InterDriver. Margem 2x já embutida. |
| **Horas humano-limitadas** | **~10-25 dias úteis (do CLIENTE)** | Homologação BB (2-5d) + certificado mTLS deploy (3d) + convênio CIP gerente PJ (7-15d). **Esse tempo é do cliente, não nosso.** Nós entregamos driver pronto + checklist setup. |
| **Charter UI wizard** | ~150 LOC (extensão `/settings/payment-gateways` step BB) | 3 campos extras vs Inter: `numero_convenio`, `numero_carteira` (default 17), `numero_variacao_carteira` (default 35); resto idêntico |

**Total dev:** ~12-18h codáveis nossas + ~10-25 dias humano cliente (paralelo, não bloqueia outras integrações).

---

## 9. Viabilidade verdict

✅ **VIÁVEL** — API REST moderna, sandbox aberto sem KYC, padrão BCB suportado (reutiliza pattern Inter), docs aceitáveis, comunidade dev brasileira ativa (DBSeller, openboleto, insign/integracao-bb).

**Ressalvas:**
- DX abaixo de Inter (vale documentar em charter os gotchas pra cliente saber)
- Convênio CIP é off-API (não nosso problema, mas precisa estar claro no wizard `/settings`)
- Rate limit não publicado — usar mesmo `HttpClientFactory` com retry exponencial padrão

**NÃO é INVIÁVEL** nem PARCIAL — é VIÁVEL com lead-time humano alto (gerencial).

---

## 10. Recomendação concreta

### BB deve vir **#2 ou #3** do pacote top-5, **não primeiro**

**Por quê não primeiro:**
1. **Lead-time humano alto (10-25 dias úteis cliente)** — começar por BB significa primeiros clientes esperam quase 1 mês pra ativar. Bradesco/Itaú têm convênios mais ágeis (Itaú especialmente, com API mais nova).
2. **DX abaixo do Inter** — implementar Bradesco primeiro (que tem padrão similar ao Inter mas com base PJ maior) gera curva de aprendizado mais limpa.
3. **Gotcha #1 (gw-app-key)** vale ser enfrentado em segundo — primeira integração nova deve consolidar pattern, não testar duplo-header novo.

**Por quê vale fazer (em segundo):**
1. **Base PJ instalada** — clientes oimpresso já têm BB majoritariamente (sinal qualificado [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).
2. **PIX Automático recv** — BB é case-leader; quando essa feature subir no roadmap, BB é referência implementacional.
3. **Reaproveita 80% do código InterDriver** — incremento marginal baixo.

### Ordem sugerida do pacote top-5

1. **Bradesco** (primeiro — base PJ + API moderna similar Inter, sem dual-header)
2. **BB** (segundo — base PJ máxima + reaproveita pattern Bradesco)
3. **Itaú** (terceiro — API mais nova, sandbox mais limpo)
4. **Santander** (quarto — base menor)
5. **Caixa** (quinto — API mais antiga, último por DX)

> **Alternativa Wagner-explícita:** se cliente-âncora paga e exige BB primeiro (sinal forte), inverter ordem → BB #1. Mas sem esse sinal, ordem acima minimiza retrabalho.

### Checklist pré-implementação BB

- [ ] Wagner valida ordem do pacote (este dossier propõe Bradesco antes)
- [ ] Confirmar com cliente-piloto se aceita lead-time 10-25 dias humano pra convênio
- [ ] Adicionar 3 campos extras no wizard `/settings/payment-gateways` step BB: `numero_convenio`, `numero_carteira`, `numero_variacao_carteira`
- [ ] Decidir API PIX: proprietária `/pix-bb/v1` (DX pior, mais features BB) OU BCB padrão `/pix/v2` (reaproveita InterDriver) — **recomendação: BCB padrão**
- [ ] Implementar polling job complementar pro `consultar` (porque webhook só notifica BAIXA OPERACIONAL)
- [ ] Documentar gotcha #1 (dual-header gw-app-key) em comment no driver + charter
- [ ] CPFs/CNPJs fictícios BB pra Pest tests — buscar lista oficial no portal

---

## Fontes consolidadas

### Oficiais BB
- [Portal Developers BB — site institucional](https://www.bb.com.br/site/developers/)
- [API Cobrança BB](https://www.bb.com.br/site/developers/bb-como-servico/api-cobranca/)
- [API Pix BB](https://www.bb.com.br/site/developers/bb-como-servico/api-pix/)
- [API Reference OAuth BB](https://api-developers.bb.com.br/docs/oauth/pt-BR/auth-code.html)
- [Apoio Developers BB — Sandbox spec](https://apoio.developers.bb.com.br/sandbox/spec/post/5fe9e7f13b02bd0012eca9f1)
- [Testes via Sandbox — Apoio Developers BB](https://apoio.developers.bb.com.br/guias-e-tutoriais/webhook/testes-via-sandbox)

### Comunidade / SDKs
- [DBSeller/sdk-api-pix-bb (GitHub)](https://github.com/DBSeller/sdk-api-pix-bb)
- [gustavohmelo/integracao-bb-cobranca (GitHub)](https://github.com/gustavohmelo/integracao-bb-cobranca)
- [insign/integracao-bb (GitHub)](https://github.com/insign/integracao-bb)
- [recovieira/bbboletowebservice (GitHub)](https://github.com/recovieira/bbboletowebservice)
- [Ewersonfc/BBboleto (GitHub)](https://github.com/Ewersonfc/BBboleto)
- [divulgueregional/api-bb-php (GitHub)](https://github.com/divulgueregional/api-bb-php)
- [openboleto/openboleto (GitHub)](https://github.com/openboleto/openboleto)

### BCB (regulador)
- [BCB Manual de Padrões para Iniciação do Pix v2.9.0](https://www.bcb.gov.br/content/estabilidadefinanceira/pix/Regulamento_Pix/II_ManualdePadroesparaIniciacaodoPix.pdf)
- [BCB Guia de implementação Pix Automático](https://liftchallenge.bcb.gov.br/content/estabilidadefinanceira/pix/automatico/guia_pix_automatico.pdf)
- [bacen/pix-api GitHub Discussions](https://github.com/bacen/pix-api/discussions/548)

### Integrações de terceiros (operacional)
- [HubSoft — Integração PIX BB](https://wiki.hubsoft.com.br/pt-br/modulos/configuracao/integracao/pix/banco-do-brasil)
- [IXCSoft Wiki — API BB Webhook](https://wiki-erp.ixcsoft.com.br/documentacao/guias-tutoriais/carteira-de-cobranca/integracoes-carteira-de-cobranca/integracoes-bancarias---api/integracoes/api-banco-do-brasil.html)
- [IXC Wiki — Configuração Webhook BB](https://wiki.ixcsoft.com.br/pt-br/Cadastros/Financeiro/Carteira_de_cobran%C3%A7a/apis_banc%C3%A1rias/api_banco_do_brasil/config_webhook_bb)
- [Kobana — Como criar conexão BB](https://ajuda.kobana.com.br/pt-BR/articles/9820688-como-criar-uma-conexao-com-a-api-do-banco-do-brasil)
- [Cigam Wiki — Open Banking BB](https://www.cigam.com.br/wiki/index.php/GF_-_Como_Fazer_-_Open_Banking_para_Banco_do_Brasil)
- [Memocash — Integração PIX BB](https://faq.memocashsolucoes.com.br/knowledgebase/integracao-pix-banco-do-brasil)
- [Movere — Integração PIX BB](https://meajuda.moveresoftware.com/support/solutions/articles/27000077763-integrac%C3%A3o-pix-banco-do-brasil)
- [Vigo Wiki — Envio certificado PFX BB](https://wiki.vigo.com.br/doku.php?id=enviar_bb)
- [Linx Pay Hub — Credenciais BB PDF](https://static.linxpayhub.com.br/qrlinx/pdf/Credenciais_BB.pdf)

### Comunidade / Forum / Reclamações
- [Maycon Braga — 3 APIs PIX mais fáceis de integrar](https://mayconbraga.com.br/blog/conteudo/as-3-apis-de-pix-mais-faceis-de-integrar)
- [Reclame Aqui — Timeout API BB](https://www.reclameaqui.com.br/banco-do-brasil/timeout-durante-uso-de-api_4V8NS9mDo5iKNfhR/)
- [Projeto ACBr — Erro 401 BB](https://www.projetoacbr.com.br/forum/topic/69419-envio-boleto-api-banco-do-brasil-erro-http-401/)
- [Projeto ACBr — Erro 403 BB](https://www.projetoacbr.com.br/forum/topic/73668-acbrboleto-api-banco-do-brasil-erro-403/)
- [Casa do Desenvolvedor — Webhook PIX BB](https://forum.casadodesenvolvedor.com.br/topic/48268-como-configurar-webhook-banc%C3%A1rio-do-banco-do-brasil-na-api-pix/)

### Notícias / Regulação 2025
- [Agência Brasil — BB amplia PIX Automático](https://agenciabrasil.ebc.com.br/economia/noticia/2025-05/banco-do-brasil-amplia-pix-automatico-para-todos-os-clientes)
- [Agência Gov — BC define lançamento PIX Automático julho 2025](https://agenciagov.ebc.com.br/noticias/202407/banco-central-estabelece-lancamento-do-pix-automatico-para-julho-de-2025)
- [Mattos Filho — Novas normas BCB sobre Pix](https://www.mattosfilho.com.br/unico/bcb-novas-normas-pix/)

---

**Dossier produzido em:** 2026-05-25 · sessão Claude Code
**Fase:** 1 (estado-da-arte) — Fase 2 (BBDriver implementation) ainda não iniciada
**WebSearches usadas:** 7 · **WebFetchs:** 2 · **Fontes citadas:** 30+
