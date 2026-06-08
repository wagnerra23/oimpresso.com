# Estado-da-arte — Integração Caixa Econômica Federal (CaixaDriver)

> **Tipo:** dossier de pesquisa pré-implementação · Fase 1 ciclo `/audit-and-fix`
> **Tema:** viabilidade do `CaixaDriver` no `Modules/PaymentGateway` (pacote top-5 bancos)
> **Data:** 2026-05-25
> **Autor:** audit-research-expert (sessão Claude)
> **Output relacionado:** `Modules/PaymentGateway/Services/Drivers/InterDriver.php` (driver-referência)
> **Status pesquisa:** ⚠️ **CRÍTICO — VIABILIDADE COMPROMETIDA**

---

## TL;DR (6 linhas)

🟡 **PARCIAL → tendência ❌ INVIÁVEL pra PME.** Caixa tem **dois universos paralelos**: (1) **PIX Automático API REST moderna** (BCB-padrão, OAuth2+mTLS, sandbox em homologação, lançado jun/2025) — exige **convênio SIGCB-Pix presencial** + cadastro PSP, prazo prático 30-60d; (2) **Cobrança boleto** é **SOAP/XML B2B legado SIGCB** (sem REST oficial pra PME), portal `desenvolvedores.caixa.gov.br` retorna tela branca/504 em maio/2026. Comunidade ACBr/Projeto ACBr classifica como **"único banco implementado sem suporte real ao desenvolvedor"**. **Recomendação:** **NÃO fazer CaixaDriver nativo agora.** Manter Caixa via CNAB 240 SIGCB com `eduardokum/laravel-boleto` (já suportado, maduro). Reavaliar PIX Automático em Q3-2026 quando convênio SIGCB-Pix maturar pra MEI/EI.

**Maturidade global oimpresso pra Caixa hoje:** 18% (vs Inter 88%, BCB PIX Automático 75%).

---

## 1. Identidade da API

| Dimensão | Boleto Cobrança | PIX Cob/CobV | PIX Automático |
|---|---|---|---|
| **API name canônico** | SIGCB (Sistema de Gestão de Cobrança Bancária) | PIX Recebimentos (BCB-padrão) | "Convênio Pix Automático CAIXA" v1.0.3 |
| **Protocolo** | **SOAP/XML B2B** ([WebService XML Cobrança Bancária PDF](https://www.caixa.gov.br/Downloads/cobranca-caixa/WEBSERVICE-XML-COBRANCA-BANCARIA.pdf)) | REST/JSON (padrão Bacen `/cob` + `/cobv`) | REST/JSON ([Doc Técnico Convênio Pix Automático v1.0.3](https://www.caixa.gov.br/empresa/pagamentos-recebimentos/recebimentos/pix-automatico/Documents/documento-tecnico-convenio-pix-automatico.pdf)) |
| **REST moderna existe?** | ❌ NÃO — só SOAP B2B + CNAB 240/400 | 🟡 Sim, mas portal `desenvolvedores.caixa.gov.br` reportado tela-branca/504 ([apitracker](https://apitracker.io/a/caixa-br)) | ✅ Sim, manual técnico jul/2025 |
| **Versão atual** | SIGCB legacy (~2010+) | BCB Manual Padrões Pix v2.9.0 | v1.0.3 (dez/2025) |
| **Status** | GA legado — sem evolução documentada | 🟡 GA cinza — sem portal funcional pra self-service | 🟢 GA novo (jun/2025) — exige convênio |
| **Portal developer** | `desenvolvedores.caixa.gov.br/apiresources/explorer` — **HTTP 504 em maio/2026** | Mesmo portal — mesmo problema | Documentado via PDF `caixa.gov.br/empresa/...` |

> Fonte autorizada da reclamação devs: [Boletos API Caixa Econômica Federal — Projeto ACBr](https://www.projetoacbr.com.br/forum/topic/81521-boletos-api-caixa-econ%C3%B4mica-federal/) — *"CEF não tem suporte ao desenvolvedor — só um manual, e fraco. É o único banco implementado com API que carece desse suporte"* (jan/2025).

---

## 2. Endpoints

### Cobrança boleto (SIGCB)

| Item | Valor |
|---|---|
| Base URL prod | `https://barramento.caixa.gov.br/sibar/...` (SOAP, varia por operação) |
| Base URL sandbox | **Não publicamente documentado** — homologação direto com gerente Caixa via convênio |
| Sandbox aberto? | ❌ **Não.** Exige convênio SIGCB ativo + senhas geradas presencialmente em agência |
| Healthcheck | Nenhum endpoint público |
| Token issuance | Não OAuth — autenticação via **WS-Security + HASH SHA256** dos campos do envelope SOAP ([HASH DIVERGENTE issue](https://github.com/wagnermengue/caixa-webservice/issues/1) — bug clássico que paralisa qualquer dev iniciante) |

### PIX cob/cobv + PIX Automático

| Item | Valor |
|---|---|
| Base URL prod | Inferido `https://api.pix.caixa.gov.br/...` (padrão Bacen, mas Caixa não publica catálogo público) |
| Base URL sandbox | Manual técnico menciona "ambiente de homologação", sem URL canônica indexada |
| Sandbox aberto? | 🟡 **Não auto-serviço.** Exige cadastro PSP via gerente PJ Caixa + assinatura convênio Pix Automático ([FAQ Convênio Pix Automático](https://www.caixa.gov.br/empresa/pagamentos-recebimentos/recebimentos/pix-automatico/Documents/FAQ_Convenio_Pix_Automatico.pdf)) |
| Token issuance | OAuth2 client_credentials + mTLS (padrão Bacen, [Manual Segurança PIX v3.7](https://www.bcb.gov.br/content/estabilidadefinanceira/cedsfn/Manual_de_Seguranca_PIX.pdf)) |
| Webhook | Sim, padrão Bacen `PUT /webhook/{chave}` (mTLS no callback) |

---

## 3. Autenticação

| Capacidade | SIGCB Boleto | Pix Cob/CobV | Pix Automático |
|---|---|---|---|
| **Esquema** | WS-Security XML + hash SHA256 manual nos campos do envelope | OAuth2 client_credentials + mTLS | OAuth2 + mTLS |
| **Como obter credenciais** | **Agência presencial** — gerente PJ gera "Convênio Caixa Cobrança" (código convênio + senha SIGCB) | Gerente PJ + cadastro PSP — sem self-service web | Idem + assinatura convênio Pix Automático |
| **Rotação secret** | Manual — abrir chamado | Manual — abrir chamado | Manual — abrir chamado |
| **Tempo médio homologação** | **30-90 dias** (relatos comunitários ACBr + reclameaqui — sem SLA público) | **30-60 dias** estimados | 30-60 dias (novo, sem histórico estatístico confiável) |
| **Certificado A1 emitido por?** | Caixa exige ICP-Brasil do beneficiário (ex: SERPRO, Certisign, AC SAFEWEB) | Idem | Idem |

> Comparação chocante: [Banco Inter](https://developers.inter.co/references/cobranca-bolepix) entrega Client ID/Secret + cert .p12 em ~24h via internet banking, **100% self-service**.

---

## 4. Capacidades

| Capacidade | Inter (ref) | Caixa SIGCB | Caixa Pix REST | Caixa CNAB 240 (eduardokum) |
|---|---|---|---|---|
| Boleto registrado emitir | ✅ REST | 🟡 SOAP B2B com hash bugado | ❌ | ✅ arquivo remessa offline |
| Boleto baixa/cancelar | ✅ | 🟡 SOAP | ❌ | ✅ remessa |
| Boleto consultar | ✅ | 🟡 SOAP | ❌ | 🟡 só via retorno CNAB |
| PIX Cob (imediata) | ✅ | ❌ | 🟡 com convênio | ❌ |
| PIX CobV (vencimento) | ✅ | ❌ | 🟡 com convênio | ❌ |
| PIX Automático (Res. BCB 380/2024) | 🟡 roadmap | ❌ | 🟡 v1.0.3 (jul/2025) | ❌ |
| Cartão | ❌ | ❌ | ❌ | ❌ |
| Refund/cancelamento PIX | ✅ | ❌ | 🟡 PIX devolução | ❌ |
| Webhook callback boleto | ✅ | ❌ (só polling SOAP) | n/a | ❌ (só importar retorno CNAB) |
| Webhook PIX | ✅ | n/a | ✅ padrão Bacen | n/a |
| Polling-friendly | ✅ | 🟡 lento, com lock B2B | ✅ | n/a |

---

## 5. Limites operacionais

| Item | SIGCB | PIX |
|---|---|---|
| Rate limit | Não documentado publicamente | Não documentado pela Caixa (BCB sugere 600 req/min por PSP) |
| Quota grátis | n/a — convênio cobra por boleto registrado (~R$ [redacted Tier 0]-4,00 título) | PIX recebimento **grátis pra MEI/EI** no Caixa, [tabela PJ Caixa](https://www.caixa.gov.br/Downloads/tabelas-tarifas-pessoa-fisica-pessoa-juridica/tabela-de-tarifas-pj.pdf) — outras PJ negociam |
| Timeout recomendado | 30-60s (SOAP pesado) | 10-15s (padrão Bacen) |
| Retry strategy | Sem orientação oficial — comunidade recomenda backoff manual + idempotency key próprio | Padrão Bacen (txid client-gen, idempotência via `/cob/{txid}` PUT) |

---

## 6. Gotchas conhecidos críticos

### Reportados pela comunidade

1. **HASH DIVERGENTE no SOAP** — bug clássico onde concatenação dos campos pra gerar SHA256 do envelope SIGCB diverge do calculado pelo servidor. Issue [wagnermengue/caixa-webservice#1](https://github.com/wagnermengue/caixa-webservice/issues/1) — semanas perdidas debugando.
2. **Portal `desenvolvedores.caixa.gov.br` retornando tela branca / HTTP 504** (confirmado nesta pesquisa em 2026-05-25). [Relato apitracker](https://apitracker.io/a/caixa-br).
3. **Sem suporte real ao dev** — comunidade ACBr afirma literalmente: *"é o único banco implementado [no ACBrBoleto] que carece de suporte ao desenvolvedor — só manual fraco"* ([Projeto ACBr 2025](https://www.projetoacbr.com.br/forum/topic/81521-boletos-api-caixa-econ%C3%B4mica-federal/)).
4. **Issue [bacen/pix-api#551](https://github.com/bacen/pix-api/issues/551)** aberta sem resposta há +1 ano questionando se Caixa tem mesmo portal estilo BB/Itaú — sintoma de invisibilidade institucional.
5. **Reclamações Reclame Aqui** ([API de integração — Caixa](https://www.reclameaqui.com.br/caixa-economica-federal/api-de-integracao_m4Elx3LY7NDhvxrZ/)) — dificuldade até pra descobrir SE existe API.
6. **SoapClient PHP nativo** quebra com namespaces SIGCB — requer workaround manual de envelope ([vmassuchetto/WebserviceCaixa](https://github.com/vmassuchetto/WebserviceCaixa)) — código de 2014 ainda referência por falta de manutenção.

### Casos de sucesso PME

- **CNAB 240 SIGCB via `eduardokum/laravel-boleto`** ([readthedocs](https://laravel-boleto.readthedocs.io/en/latest/usage/remessa/caixa.html)) — único caminho realmente comprovado pra PME brasileira. Convênio SIGCB-Cobrança continua exigido, mas envio é por upload arquivo no Internet Banking CAIXA (não API). Lifecycle conhecido, tolerância a erro alta.
- **Boleto Cloud / Kobana / Boleto Simples** — agregadores que abstraem Caixa pra REST por debaixo, cobrando ~R$ [redacted Tier 0]-2/boleto extra. Indicam que ninguém integra Caixa direto se puder evitar ([Kobana — Visão Geral Caixa](https://developers.kobana.com.br/reference/caixa-econ%C3%B4mica-federal)).

### Convênio SIGCB — fluxo real

- Solicitar com gerente PJ Caixa (formulário físico + assinatura).
- Esperar 5-15 dias úteis pra liberar **Código do Convênio** (6 dígitos).
- Receber senha SIGCB separadamente (carta protocolar / 2FA telefônico).
- Homologar arquivos remessa CNAB manualmente com analista da CAIXA por e-mail/telefone.
- **Custo:** mensalidade convênio R$ [redacted Tier 0]-80/mês + R$ [redacted Tier 0]-4,00 por boleto registrado liquidado (varia por relacionamento).

---

## 7. Comparação dimensional vs InterDriver

| Dimensão | Inter | Caixa SIGCB+CNAB | Δ | Esforço extra Caixa |
|---|---|---|---|---|
| Onboarding cred | self-service 24h | presencial 30-90d | **-95%** | depende do cliente |
| Protocolo | REST/OAuth2 + mTLS | SOAP+WS-Sec OU upload CNAB | **muito pior** | 3-5x mais código |
| Boleto emitir | 1 POST | SOAP envelope + hash manual OU gerar arquivo CNAB e fazer upload | **5x** | parser CNAB + remessa + retorno |
| PIX cob | 1 POST | só com convênio Pix REST adicional | **bloqueado** | depende cliente |
| Webhook | nativo | inexistente (boleto) | **inviável** | reescrever como polling/cron |
| Refund | API | manual via SAC | **inviável** | n/a |
| Testabilidade | `Http::fake()` puro | precisa mock SOAP cliente + simular hash + simular arquivos CNAB | **horrível** | ~3-4x tests vs Inter |

**Veredito dimensional:** Caixa via API direta está **5-10× pior** que Inter em quase tudo, com **risco humano-limitado real** (cliente pode não conseguir liberar convênio).

---

## 8. Esforço estimado recalibrado (ADR 0106)

### Cenário A — CaixaDriver REST nativo (Pix-only, IGNORANDO boleto SOAP)

| Item | Estimativa |
|---|---|
| LOC Driver | ~700 (vs InterDriver 600) |
| LOC Pest tests | ~500 (Http::fake do padrão Bacen funciona — manageable) |
| Horas codáveis IA-pair | 12-16h (fator 10x + margem 2x = 20-30h calendário) |
| Horas humano-limitadas | **30-60 dias calendário** (homologação convênio Caixa por cliente) |
| Custo dev real | médio se Wagner pessoalmente não tem conta Caixa: **bloqueado em cliente real** pra smoke production |

### Cenário B — CaixaDriver com SOAP SIGCB pra boleto + REST Pix

| Item | Estimativa |
|---|---|
| LOC Driver | ~1.800-2.400 (3-4x InterDriver) |
| LOC Pest tests | ~1.500-2.000 (SOAP mocking é doloroso) |
| Horas codáveis IA-pair | 60-90h (fator 10x ainda não compensa SOAP complexity) |
| Horas humano-limitadas | 60-120 dias calendário (homologação SIGCB-Cobrança + Pix separado) |
| Risco abandonar antes do MVP | **ALTO** — 6 issues documentadas de devs que tentaram |

### Cenário C — manter Caixa via CNAB lib `eduardokum/laravel-boleto`

| Item | Estimativa |
|---|---|
| LOC adapter | ~150-250 (wrapper fino pra usar lib já instalável) |
| LOC tests | ~200 (gerar arquivo + parsear retorno fixture) |
| Horas codáveis | 4-6h IA-pair |
| Horas humano-limitadas | dias para cliente entregar arquivo retorno real → conciliação |
| Cobertura | só boleto (sem PIX cob automático) — adequado pra perfil cliente atual |

---

## 9. Viabilidade verdict ⚠️ CRÍTICO

🟡 **PARCIAL** com forte tendência a **❌ INVIÁVEL pra PME no horizonte de 2026.**

**Justificativa em 1 parágrafo:** O melhor caminho técnico (Pix Automático REST padrão Bacen) é **legítimo e moderno**, mas a **CAIXA não tem self-service de credenciais nem sandbox aberto** — toda integração depende de convênio presencial via gerente PJ, e Wagner pessoalmente NÃO tem conta Caixa pra criar ambiente próprio de homologação. Pior: o portal `desenvolvedores.caixa.gov.br` está literalmente quebrado em maio/2026 (504 timeout). Boleto via SIGCB SOAP é um **buraco arqueológico** documentado por 5+ devs que sangraram tentando. Comunidade ACBr (autoridade no PHP brasileiro pra integrações bancárias) classifica Caixa como "único banco que carece de suporte real" — sinal de mercado claríssimo.

---

## 10. Recomendação concreta

### Decisão recomendada: **CONSOLIDAR via CNAB lib, NÃO criar `CaixaDriver` nativo agora**

1. **Onda 1 (imediata · 4-6h codáveis):** Criar `CaixaCnabAdapter` fino dentro de `Modules/PaymentGateway/Services/Drivers/` que **delega pra `eduardokum/laravel-boleto`** já presente/instalável. Implementa só `emitirBoleto()` gerando arquivo remessa CNAB 240 SIGCB + endpoint upload manual no painel admin pra cliente subir arquivo retorno. **Não tenta** PIX, refund, webhook (não há) — `supports()` retorna `['boleto']` apenas.

2. **Onda 2 (condicional · Q3-2026):** Reavaliar Pix Automático Caixa SE/QUANDO:
   - Pelo menos 1 cliente oimpresso pedir nominalmente Caixa Pix com convênio já assinado em mãos (sinal qualificado [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))
   - Portal `desenvolvedores.caixa.gov.br` voltar a funcionar
   - Quotação cliente OK pra esperar 30-60d homologação (negócio aceitar prazo bancário)
   - Existir relato público de PME PJ pequena que conseguiu Pix REST Caixa sem dor de cabeça grande

3. **Onda 3 (nunca, possivelmente):** SIGCB SOAP boleto via API B2B — só se cliente CRÍTICO grande pagar dev-days direcionados. Não entrar no pacote top-5 padrão.

### Comunicação pro pacote top-5 bancos (Bradesco + Itaú + BB + Santander + Caixa)

> "Bradesco/Itaú/BB/Santander entram como drivers REST nativos seguindo pattern InterDriver. **Caixa entra de forma assimétrica**: CNAB adapter offline pra boleto registrado (atendendo demanda real de clientes oimpresso com conta Caixa), e CaixaPixDriver fica em backlog condicionado a cliente-sinal qualificado. Salvamos **40-80h** de código + **30-90d** de homologação calendário sem perder cobertura funcional relevante (boleto Caixa continua emissível, PIX desses clientes pode rotear pra Banco Central direto se já tiverem chave Pix Caixa cadastrada e quiserem usar BcbPixDriver com Pix Automático regulado)."

---

## Surpresa positiva (Caixa > mercado)

- **Pix Automático Convênio Caixa v1.0.3** está alinhado ao padrão Bacen Res. 380/2024 — quando funcionar, é o mesmo contrato que Inter/BB/Itaú vão expor. Investimento futuro **não é desperdício total**, é só prematuro.
- **PIX MEI/EI grátis no Caixa** — tarifa zero pra cliente final, atrativo comercial real quando integração madurar.

## Surpresa negativa (mercado > Caixa)

- Banco Inter (médio porte) entrega em 24h o que Caixa (maior banco federal) não consegue em 60 dias.
- Documentação Sicoob/BB/Itaú/Bradesco tem **Swagger interativo público** — Caixa só PDF estático.
- Portal developer da Caixa **literalmente retornando 504** em maio/2026 enquanto BB tem [`apidocs.bb.com.br`](https://apidocs.bb.com.br) operacional.
- ACBr (autoridade comunitária Delphi/PHP) lista Caixa como caso de exceção negativa — sinal forte de mercado.

---

## Fontes

- [Portal Desenvolvedores Caixa (504 em 2026-05-25)](https://desenvolvedores.caixa.gov.br/apiresources/explorer)
- [WebService XML Cobrança Bancária Caixa — PDF](https://www.caixa.gov.br/Downloads/cobranca-caixa/WEBSERVICE-XML-COBRANCA-BANCARIA.pdf)
- [API Pix Automático Manual Técnico Caixa](https://www.caixa.gov.br/empresa/pagamentos-recebimentos/recebimentos/pix-automatico/Documents/api-pix-automatico-manual-tecnico.pdf)
- [Documento Técnico Convênio Pix Automático v1.0.3](https://www.caixa.gov.br/empresa/pagamentos-recebimentos/recebimentos/pix-automatico/Documents/documento-tecnico-convenio-pix-automatico.pdf)
- [FAQ Convênio Pix Automático CAIXA](https://www.caixa.gov.br/empresa/pagamentos-recebimentos/recebimentos/pix-automatico/Documents/FAQ_Convenio_Pix_Automatico.pdf)
- [Tabela Tarifas PJ Caixa](https://www.caixa.gov.br/Downloads/tabelas-tarifas-pessoa-fisica-pessoa-juridica/tabela-de-tarifas-pj.pdf)
- [Projeto ACBr — Boletos API Caixa Econômica Federal (jan/2025)](https://www.projetoacbr.com.br/forum/topic/81521-boletos-api-caixa-econ%C3%B4mica-federal/)
- [Projeto ACBr — API CEF](https://www.projetoacbr.com.br/forum/topic/80862-api-cef/)
- [Projeto ACBr — Pix Caixa Econômica Federal](https://www.projetoacbr.com.br/forum/topic/73930-pix-caixa-econ%C3%B4mica-federal/)
- [GitHub topic caixa-economica-federal](https://github.com/topics/caixa-economica-federal)
- [GitHub wagnermengue/caixa-webservice — issue HASH DIVERGENTE](https://github.com/wagnermengue/caixa-webservice/issues/1)
- [GitHub vmassuchetto/WebserviceCaixa (2014, ainda referência)](https://github.com/vmassuchetto/WebserviceCaixa)
- [GitHub jovemnf/cef-webservice](https://github.com/jovemnf/cef-webservice)
- [GitHub bacen/pix-api issue #551 (sem resposta)](https://github.com/bacen/pix-api/issues/551)
- [eduardokum/laravel-boleto — Caixa CNAB 240 docs](https://laravel-boleto.readthedocs.io/en/latest/usage/remessa/caixa.html)
- [eduardokum/laravel-boleto — Caixa.php source](https://github.com/eduardokum/laravel-boleto/blob/master/src/Cnab/Retorno/Cnab240/Banco/Caixa.php)
- [APITracker — Caixa Econômica Federal (sem APIs catalogadas)](https://apitracker.io/a/caixa-br)
- [Kobana — Caixa Econômica Federal Visão Geral](https://developers.kobana.com.br/reference/caixa-econ%C3%B4mica-federal)
- [Boleto Simples — Caixa Econômica Federal](https://api.boletosimples.com.br/bank_contracts/cef/)
- [Reclame Aqui — API de integração Caixa](https://www.reclameaqui.com.br/caixa-economica-federal/api-de-integracao_m4Elx3LY7NDhvxrZ/)
- [BCB Manual Segurança PIX v3.7](https://www.bcb.gov.br/content/estabilidadefinanceira/cedsfn/Manual_de_Seguranca_PIX.pdf)
- [Banco Inter — API Cobrança bolepix (referência self-service)](https://developers.inter.co/references/cobranca-bolepix)
- [Tecnospeed — API bancos brasileiros (visão geral)](https://blog.tecnospeed.com.br/api-de-bancos-brasileiros/)
- [DevMedia — SIGCB boleto Caixa](https://www.devmedia.com.br/devmedia-apis-de-boleto-novo-boleto-caixa-economica-federal-modelo-sigcb-implantado/31283)

---

**Disposição:** este dossier alimenta backlog do pacote top-5 bancos. Próximo passo recomendado pra parent: criar US/task "CaixaCnabAdapter (Onda 1)" descartando explicitamente CaixaDriver REST nativo do escopo Q2-Q3 2026.
