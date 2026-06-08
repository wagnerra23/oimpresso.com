# Arte — API Banco Bradesco (BradescoDriver pra `Modules/PaymentGateway`)

> **Data:** 2026-05-25
> **Autor:** Claude Code · audit-research-expert
> **Contexto:** Wagner aprovou pacote top-5 bancos (Bradesco + Itaú + BB + Santander + Caixa). Bradesco NÃO foi implementado ainda. Clientes do oimpresso têm conta PJ Bradesco; este dossier define viabilidade + esforço pra implementar `BradescoDriver` seguindo pattern canônico (referência `InterDriver.php`).
> **Status:** Pesquisa estado-da-arte concluída, ZERO código editado. Próximo passo é decisão Wagner sobre ordem de implementação no pacote top-5.

---

## TL;DR (4 linhas)

API Bradesco é **🟡 PARCIAL** — existe e é usada em produção por ERPs grandes (Omie, Kobana, HubSoft, Fortes), mas tem **3 fricções pesadas**: (1) sandbox NÃO é auto-serviço — exige cert A1 do CNPJ titular real + gerente PJ envolvido por email pra liberar; (2) homologação leva ~10 dias úteis até prod, não os 2-5 dias do Inter; (3) endpoints PIX são **proprietários e divergem do padrão BCB** (denúncia em [bacen/pix-api#532](https://github.com/bacen/pix-api/issues/532)), aumentando custo de manutenção. **Viável implementar**, mas Bradesco vai pro **3º-4º slot** do top-5 (depois de Inter já feito + BB + Itaú), não 1º.

**Esforço total recalibrado (ADR 0106):** ~12-16h codáveis (driver + tests) + **~10 dias úteis humano-limitados** (homologação que NÃO comprime — relógio do mundo real).

---

## 1. Identidade da API

| Item | Valor |
|---|---|
| **Portal developer** | https://developers.bradesco.com.br/ (plataforma nova) + https://api.bradesco/ (portfólio comercial) |
| **API name canônico** | "API Cobrança" (boleto registrado) · "API PIX" (cob/cobv) · "Boleto Híbrido" (boleto + PIX QR Code juntos) |
| **Versão atual recomendada** | API PIX **v2.6.1** (manual versão 07, outubro/2022 — última pública conhecida [PDF oficial](https://wspf.banco.bradesco/wsValidadorUniversal/Content/Pdf/Layout_API_PIX.pdf)). API Cobrança versão atual via portal (sem semver público claro) |
| **Status** | GA (em produção real, milhares de empresas usam via ERPs) |
| **Última atualização docs visível** | Outubro/2022 no PDF público — fricção: Bradesco não publica changelog versionado |

**Fonte primária:** [Plataforma Bradesco Developers](https://developers.bradesco.com.br/) · [Portal api.bradesco](https://api.bradesco/) · [Open APIs catalog](https://api.bradesco/content/openapis.html)

---

## 2. Endpoints

| Item | URL |
|---|---|
| **Base URL produção (PIX)** | `https://qrpix.bradesco.com.br` (segundo manual PIX 2.6.1) |
| **Base URL sandbox (homologação)** | `https://qrpix-h.bradesco.com.br` (mesma estrutura, sufixo `-h`) |
| **Base URL Boleto Híbrido** | `https://openapi.bradesco.com.br/v1/boleto/...` (variável conforme serviço) |
| **Token issuance (OAuth2)** | `POST /oauth/access-token` — JWT assertion assinada com cert A1 |
| **Healthcheck oficial** | NÃO há endpoint dedicado — convenção é fazer `GET` no recurso `cob/{txid}` com txid fake e validar 404 vs 401 (idêntico ao InterDriver) |
| **Sandbox aberto?** | **❌ NÃO** — exige (a) ser cliente PJ Bradesco com contrato cobrança ativo, (b) cert A1 do CNPJ titular, (c) email pra suporte.api@bradesco.com.br com gerente PJ em cópia, (d) indicadores 175 e 182 habilitados na conta |

**Fonte:** [Manual API PIX 2.6.1](https://wspf.banco.bradesco/wsValidadorUniversal/Content/Pdf/Layout_API_PIX.pdf) · [Processo homologação Openness](https://opennesstechnologysupport.zendesk.com/hc/pt-br/articles/6295712858253-Processo-de-Homologa%C3%A7%C3%A3o-Bradesco) · [Homologação Simdata](https://ajuda.simdata.com.br/homologacao-api-banco-bradesco/) · [Kobana ativar PIX Bradesco](https://ajuda.kobana.com.br/pt-BR/articles/8861684-como-ativar-os-recebimentos-por-pix-pela-api-do-bradesco)

---

## 3. Autenticação

| Item | Valor |
|---|---|
| **Esquema** | OAuth2 `client_credentials` **+ JWT assertion assinada** (`X-Brad-Signature`) **+ mTLS** (cert A1 enviado em toda request, similar Inter) |
| **Header mágico** | `X-Brad-Signature` — PKCS#7 / RSA-SHA256 do payload, gerada com chave privada do cert A1 (similar conceito ao "fingerprint" PIX BCB mas formato proprietário) |
| **Cert A1 requisitos** | ICP-Brasil A1, PKCS#12 (.pfx), **>4Kb obrigatório**, 2048 bits mínimo, validade 4 meses–3 anos, e-CNPJ do titular da conta |
| **Como obter credenciais** | (1) Cliente cadastra app no portal developers.bradesco.com.br, (2) envia email pra `suporte.api@bradesco.com.br` com gerente PJ em CC, (3) gerente valida CNPJ tem contrato cobrança ativo (carteiras 09/21/22/25/26 indicadas), (4) Bradesco devolve `client_id` + `client_secret` em até 2 dias úteis após assinatura validada |
| **Rotação de secret** | Manual via portal — não há rotação automática documentada. **Credenciais ficam visíveis só por 3 dias após geração** (gotcha crítico) |
| **Tempo homologação até prod** | **~10 dias úteis end-to-end** (cadastro + email + cert + sandbox + email novo solicitando prod) — fonte: [Openness Technology](https://opennesstechnologysupport.zendesk.com/hc/pt-br/articles/6295712858253) recomenda "preencher data desejada de ativação em produção ~10 dias após enviar o email" |

**Comparação com Inter:** Inter usa OAuth2 puro `client_credentials` + mTLS, sem assinatura adicional do payload. Bradesco adiciona camada `X-Brad-Signature` PKCS#7 — **+~80 LOC no driver** vs Inter.

**Fonte:** [BoletoHibridoBradesco GitHub (referência X-Brad-Signature)](https://github.com/desenvolvedores-net/BoletoHibridoBradesco) · [Erro SSL client auth ACBr](https://www.projetoacbr.com.br/forum/topic/80515-erro-pix-bradesco-ssl-with-client-authentication-is-required/) · [Erro 403 forbidden bacen/pix-api#344](https://github.com/bacen/pix-api/discussions/344) · [Certificados PIX Citel](https://documentacao.citelsoftware.com.br/gerar-certificados-pix-banco-bradesco-erp-autcom-doc-9/)

---

## 4. Capacidades

| Capacidade | Suportado? | Notas |
|---|---|---|
| **Boleto registrado** | ✅ Sim | API Cobrança JSON · carteiras 02/09/06/21/22/25/26 · CNAB equivalente |
| **Boleto Híbrido (boleto + PIX QR Code)** | ✅ Sim | Endpoint dedicado · PIX é registrado no banco junto com boleto · pagamento auto-baixa |
| **PIX cobrança imediata (cob)** | ✅ Sim | Manual PIX 2.6.1 cobre |
| **PIX cobrança com vencimento (cobv)** | ✅ Sim | Idem manual |
| **PIX Automático recv (Res. BCB 380/2024)** | 🟡 Indeterminado | NÃO encontrei docs públicos Bradesco confirmando suporte ao novo padrão recv lançado em 2025. Manual público é de 2022. Provável GA até 2026 mas exige confirmação direta com banco |
| **PIX Saque e Troco** | ✅ Sim | Adicionado na versão 06 do manual |
| **Cartão de crédito** | ❌ Não | Bradesco NÃO oferece API cartão direta — usar gateway terceiro (Cielo/Stone/Pagarme) |
| **Refund / cancelamento** | ✅ Parcial | Cancela boleto antes do pagamento OK · refund PIX via DICT MED (segue padrão BCB) |
| **Webhook callback** | ✅ Sim | Permissões `webhook.write` (configurar) + `webhook.read` (consultar). Eventos: PIX recebido, boleto pago, boleto cancelado. Configurado **por chave PIX**, não por app |

**Fonte:** [Manual API PIX 2.6.1](https://wspf.banco.bradesco/wsValidadorUniversal/Content/Pdf/Layout_API_PIX.pdf) · [Webhook config bacen/pix-api#548](https://github.com/bacen/pix-api/discussions/548) · [API Cobrança Bradesco Fortes](https://ajuda.fortestecnologia.com.br/kb/pt-br/article/485872/api-bradesco-boleto-hibrido) · [Implementação ACBr boleto](https://www.projetoacbr.com.br/forum/topic/84245-implementa%C3%A7%C3%A3o-do-boleto-com-qr-code-bradesco-via-api-do-site-bradesco-developers/) · [PSPs PIX API bacen #187](https://github.com/bacen/pix-api/discussions/187)

---

## 5. Limites operacionais

| Item | Valor |
|---|---|
| **Rate limit oficial** | **Não publicado** — gotcha de transparência. ERPs reportam ~60-120 req/min seguros |
| **Quota grátis** | Cliente PJ Bradesco com contrato cobrança paga tarifa por boleto registrado (tabela varia por relacionamento, ~R$ [redacted Tier 0]-5 por boleto pago) — API não tem custo adicional |
| **Preço PIX recebido** | Boleto Híbrido: tarifa fixa por boleto (~R$ [redacted Tier 0]-5) · PIX cob puro: **1.4% até max R$ [redacted Tier 0] min R$ [redacted Tier 0]** (gotcha: MEI/EI tem isenção por resolução BCB) |
| **Timeout recomendado** | 30s (idêntico Inter) — Bradesco tem janelas de instabilidade noturnas (~3-5h da manhã pra rotina) |
| **Retry strategy oficial** | NÃO documentada — convenção: exponential backoff 1s/2s/4s, max 3 retries. Idempotência via `txid` (PIX) ou `nossoNumero` (boleto) |
| **Atraso conhecido status update** | **~1h reportado em alguns casos** ([bacen/pix-api#321](https://github.com/bacen/pix-api/issues/321)) — webhook mitiga mas polling tem lag |

**Fonte:** [Celcoin tarifas PIX PJ](https://www.celcoin.com.br/news/pix-pj-conheca-as-taxas-cobradas-pelos-principais-bancos-no-brasil/) · [PIX PJ Bradesco oficial](https://banco.bradesco/pix/pix-para-sua-empresa.shtm) · [Tarifas PSPs bacen #266](https://github.com/bacen/pix-api/discussions/266)

---

## 6. Gotchas conhecidos (top 8)

1. **`X-Brad-Signature` PKCS#7 proprietário** — não é JWT padrão, exige openssl_pkcs7_sign + base64. Bouncy Castle no .NET, openssl no PHP. +80 LOC no driver. ([BoletoHibridoBradesco GitHub](https://github.com/desenvolvedores-net/BoletoHibridoBradesco))
2. **Credenciais visíveis só 3 dias após geração** — se cliente perder, refazer todo email-suporte. Cadastrar imediatamente no `payment_gateway_credentials` ([Openness](https://opennesstechnologysupport.zendesk.com/hc/pt-br/articles/6295712858253))
3. **Sandbox NÃO é sandbox real** — usa dados reais do CNPJ (CNPJ titular, agência, número-controle, número-negociação reais). Diferença vs Inter (sandbox fake) ([Simdata](https://ajuda.simdata.com.br/homologacao-api-banco-bradesco/))
4. **Endpoints PIX divergem do padrão BCB** ([bacen/pix-api#532](https://github.com/bacen/pix-api/issues/532)) — manual BCB proíbe divergência, Bradesco diverge mesmo assim. Driver precisa de mapper específico, não dá reusar contract padrão pix-api
5. **Erro "SSL with client authentication is required"** muito comum — significa cert A1 não foi anexado corretamente OU cert expirou OU cert é A3 (não suporta) ([ACBr forum](https://www.projetoacbr.com.br/forum/topic/80515-erro-pix-bradesco-ssl-with-client-authentication-is-required/))
6. **Erro 403 forbidden em autenticação** — quase sempre é mismatch entre cert A1 enviado e CNPJ do `client_id`. Testar com Postman primeiro (Bradesco oficialmente recomenda Postman como ferramenta de suporte) ([bacen/pix-api#344](https://github.com/bacen/pix-api/discussions/344))
7. **Webhook configurado por chave PIX, não por app** — se cliente tem 2 chaves PIX (CNPJ + aleatória), precisa configurar webhook 2x. Bug comum: configurar só uma, perder eventos da outra
8. **Erros HTTP 422 com campos não enviados** — Bradesco rejeita silenciosamente campos opcionais que outros bancos aceitam. Validar payload EXATO do exemplo do manual ([ACBr 422](https://www.projetoacbr.com.br/forum/topic/90972-dificuldades-na-homologa%C3%A7%C3%A3o-com-bradesco-api-cobran%C3%A7a-com-qr-code-%E2%80%93-erros-http-422-e-campos-n%C3%A3o-enviados))

---

## 7. Comparação com `InterDriver`

| Dimensão | Inter | Bradesco | Veredito |
|---|---|---|---|
| **Auth** | OAuth2 + mTLS (2 secrets) | OAuth2 + mTLS + X-Brad-Signature PKCS#7 (3 layers) | Inter > Bradesco (Bradesco +80 LOC sig) |
| **Sandbox UX** | Auto-serviço, dados fake aceitos, prod em <15 dias | Exige cert A1 real + gerente PJ + 10 dias úteis | **Inter >> Bradesco** |
| **Boleto registrado** | API JSON moderna v3 | API JSON moderna (recente) | **Empate** |
| **PIX cob** | Conforme BCB padrão | **Divergente do BCB** (proprietário) | **Inter > Bradesco** (Bradesco custo manutenção) |
| **PIX cobv** | Suportado | Suportado | Empate |
| **PIX recv (Res. 380/2024)** | Não documentado ainda | Não documentado ainda | Empate (ambos pendentes) |
| **Refund** | Parcial | Cancela + DICT MED | **Bradesco > Inter** (escopo maior) |
| **Webhook** | Conforme BCB | Proprietário, por-chave-PIX | Inter > Bradesco (UX) |
| **Docs públicas** | Portal moderno, changelog versionado | PDF 2022, sem semver, suporte por email | **Inter >> Bradesco** |
| **Tarifa por transação** | PIX PJ: gratuito | PIX cob: 1.4% (max R$ [redacted Tier 0]) | **Inter >> Bradesco** ($ matters) |
| **Comunidade** | SDKs Java/C# oficiais + comunidade ativa | SDKs comunidade só (vitorccs, rlucasfm, eduardokum) | Inter > Bradesco |

**Síntese:** Inter é estado-da-arte. Bradesco é **legado-modernizado-parcial** — funciona, é usado por todo grande ERP, mas tem rugosidades de banco tradicional. Implementação **viável**, custo ~30-50% maior que Inter por causa de (auth +1 layer, sandbox fechado, divergência BCB).

**Fonte:** [Inter Empresas dev portal](https://developers.inter.co/) · [Tecnospeed comparação APIs bancárias](https://blog.tecnospeed.com.br/api-de-bancos-brasileiros/)

---

## 8. Esforço estimado recalibrado (ADR 0106)

| Item | Estimativa |
|---|---|
| **LOC `BradescoDriver.php`** | ~700 LOC (Inter tem ~600, Bradesco +80 sig +20 X-Brad headers) |
| **LOC `BradescoDriverTest.php` (Pest)** | ~350 LOC (Inter tem ~300, Bradesco +sig tests) |
| **Helper `BradescoSignatureFactory.php`** | ~120 LOC (PKCS#7 signing isolado, reusável) |
| **Horas codáveis IA-pair (driver + tests + helper + integration ao wizard)** | **~10-14h** (fator 10x sobre ~100-140h "humano puro") |
| **Horas humano-limitadas IRREDUTÍVEIS** | **~10 dias úteis** (KYC cliente PJ + cert A1 + gerente PJ email + homologação + email prod + ativação ~10d após). NÃO comprime com IA |
| **Margem 2x (ADR 0106)** | **~24h codáveis + 14 dias úteis humano** |

**Total efetivo:** 1 sprint de 2 semanas, sendo ~3-4 dias Claude codando e o restante esperando relógio Bradesco.

---

## 9. Viabilidade verdict

# 🟡 PARCIAL

**Justificativa:** API existe, é moderna o suficiente (REST/JSON, OAuth2, webhook), e tem milhares de empresas em prod via ERPs (Omie, Kobana, HubSoft, Fortes, IXC). NÃO é SOAP/XML legado nem fechada a grandes contas. Porém, tem 3 fricções pesadas: (1) sandbox exige cliente PJ real com cert A1 — Wagner NÃO consegue auto-testar; (2) homologação leva ~10 dias úteis irredutíveis; (3) endpoints PIX divergem do padrão BCB, aumentando manutenção. **Implementar é viável e recomendado** dado que clientes do oimpresso têm conta Bradesco, mas Bradesco vai pro **slot 3-4 do top-5**, não slot 1.

**Caminho oficial pra cliente piloto:** Wagner pede a 1 cliente PJ Bradesco (a) confirmar contrato cobrança ativo com indicadores 175+182, (b) gerar cert A1 e-CNPJ titular >4Kb, (c) abrir ticket com `suporte.api@bradesco.com.br` com gerente PJ em CC pedindo "Client ID API Cobrança + PIX para integração com ERP oimpresso". Resposta em ~2 dias úteis. Wagner faz wizard `/settings/payment-gateways/bradesco` aceitar cred + cert A1 upload.

---

## 10. Recomendação concreta

**Ordem recomendada do pacote top-5:**

1. ✅ **Inter** (feito — referência)
2. **Banco do Brasil** (próximo) — API mais antiga mas estável, sandbox auto-serviço, comunidade grande
3. **Itaú** (3º) — Itaú Shop ou API openbanking, sandbox bom
4. **Bradesco** (4º) — este dossier
5. **Caixa** (5º — último) — pior API do top-5 brasileiro, exige mais paciência

**Subset funcional Bradesco (MVP):** implementar **só `boleto` + `pix_cob` + `pix_cobv` + `cancelar` + `consultar` + `processWebhook`** (paridade Inter). NÃO implementar PIX Automático recv até Bradesco publicar manual oficial 2025+ (atual é 2022). NÃO implementar cartão (Bradesco não tem API direta). Refund: implementar `cancelar` antes pagamento na v1, refund pós-pagamento na v2 quando houver demanda real (cliente como sinal — ADR 0105).

---

## Referências (todas pesquisadas 2026-05-25)

**Oficiais Bradesco:**
- [Portal Bradesco Developers (novo)](https://developers.bradesco.com.br/)
- [Portfólio comercial api.bradesco](https://api.bradesco/)
- [Catálogo Open APIs](https://api.bradesco/content/openapis.html)
- [Manual API PIX 2.6.1 (PDF)](https://wspf.banco.bradesco/wsValidadorUniversal/Content/Pdf/Layout_API_PIX.pdf)
- [Manual API PIX 2.0.0 (PDF)](https://assets.bradesco/content/dam/portal-bradesco/pix/assets/docs/api_pix_200.pdf)
- [PIX PJ Bradesco tarifas](https://banco.bradesco/pix/pix-para-sua-empresa.shtm)

**Implementações comunidade:**
- [vitorccs/bradesco-api-php (SDK PHP)](https://github.com/vitorccs/bradesco-api-php)
- [desenvolvedores-net/BoletoHibridoBradesco (X-Brad-Signature)](https://github.com/desenvolvedores-net/BoletoHibridoBradesco)
- [FlyCorp/bradesco (carteira 26 ecommerce)](https://github.com/FlyCorp/bradesco)
- [rlucasfm/bradesco-online](https://github.com/rlucasfm/bradesco-online)
- [eduardokum/laravel-boleto (Bradesco)](https://github.com/eduardokum/laravel-boleto/blob/master/src/Boleto/Banco/Bradesco.php)
- [rafael-desouza/Api_Pix_Bradesco](https://github.com/rafael-desouza/Api_Pix_Bradesco)
- [hgmauri/registro-boleto-online-bradesco (.NET)](https://github.com/hgmauri/registro-boleto-online-bradesco)

**Tutoriais ERPs (entendem na prática):**
- [Openness — processo homologação](https://opennesstechnologysupport.zendesk.com/hc/pt-br/articles/6295712858253-Processo-de-Homologa%C3%A7%C3%A3o-Bradesco)
- [Simdata — homologação Bradesco](https://ajuda.simdata.com.br/homologacao-api-banco-bradesco/)
- [Kobana — ativar PIX Bradesco](https://ajuda.kobana.com.br/pt-BR/articles/8861684-como-ativar-os-recebimentos-por-pix-pela-api-do-bradesco)
- [Omie — config PIX Bradesco](https://ajuda.omie.com.br/pt-BR/articles/10127532-configurando-a-integracao-com-o-bradesco-pix-via-api)
- [Fortes — API Cobrança boleto híbrido](https://ajuda.fortestecnologia.com.br/kb/pt-br/article/485872/api-bradesco-boleto-hibrido)
- [HubSoft — Integração PIX Bradesco](https://wiki.hubsoft.com.br/pt-br/modulos/configuracao/integracao/pix/bradesco)
- [IXC — webhook Bradesco](https://wiki-erp.ixcsoft.com.br/documentacao/guias-tutoriais/carteira-de-cobranca/integracoes-carteira-de-cobranca/integracoes-bancarias---api/integracoes/api-banco-bradesco.html)
- [OpenPix dev docs Bradesco](https://developers.openpix.com.br/en/docs/bank-integrations/integration-bradesco-bank)
- [L2MAKER PIX Bradesco 237](https://www.l2maker.com.br/documentacao/2022/02/trabalhando-com-o-pix-no-bradesco-bra-237/)
- [Citel certificados PIX Bradesco](https://documentacao.citelsoftware.com.br/gerar-certificados-pix-banco-bradesco-erp-autcom-doc-9/)

**Issues/discussões reais:**
- [bacen/pix-api#532 — Bradesco diverge do padrão BCB](https://github.com/bacen/pix-api/issues/532)
- [bacen/pix-api#344 — erro 403 Bradesco](https://github.com/bacen/pix-api/discussions/344)
- [bacen/pix-api#321 — atrasos consulta status](https://github.com/bacen/pix-api/issues/321)
- [bacen/pix-api#187 — lista PSPs PIX API](https://github.com/bacen/pix-api/discussions/187)
- [bacen/pix-api#548 — config webhook](https://github.com/bacen/pix-api/discussions/548)
- [bacen/pix-api#266 — tarifas PSPs](https://github.com/bacen/pix-api/discussions/266)
- [ACBr — SSL client auth required](https://www.projetoacbr.com.br/forum/topic/80515-erro-pix-bradesco-ssl-with-client-authentication-is-required/)
- [ACBr — Dificuldades homologação 422](https://www.projetoacbr.com.br/forum/topic/90972-dificuldades-na-homologa%C3%A7%C3%A3o-com-bradesco-api-cobran%C3%A7a-com-qr-code-%E2%80%93-erros-http-422-e-campos-n%C3%A3o-enviados)
- [ACBr — Boleto Híbrido implementação](https://www.projetoacbr.com.br/forum/topic/73557-implementar-boleto-hibrido-bradesco/)
- [ACBr — Recebimento recorrente PIX automático](https://www.projetoacbr.com.br/forum/topic/83763-recebimento-recorrente-com-pix-autom%C3%A1tico/)

**Comparativo:**
- [Tecnospeed — APIs bancos brasileiros](https://blog.tecnospeed.com.br/api-de-bancos-brasileiros/)
- [Celcoin — tarifas PIX PJ Brasil](https://www.celcoin.com.br/news/pix-pj-conheca-as-taxas-cobradas-pelos-principais-bancos-no-brasil/)
- [Inter Empresas developers (referência)](https://developers.inter.co/)
- [OpenBanking Tracker — Bradesco](https://www.openbankingtracker.com/provider/banco-bradesco)

---

**Conclusão Wagner:** decisão é (a) confirmar ordem do pacote top-5 (Inter ✅ → BB → Itaú → Bradesco → Caixa), (b) ao chegar em Bradesco, abrir thread com 1 cliente piloto PJ Bradesco pedindo cert A1 + email gerente PJ. NÃO bloquear sprint atual com Bradesco — ele depende de relógio externo de ~10 dias úteis irredutível.
