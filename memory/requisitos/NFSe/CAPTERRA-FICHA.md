# CAPTERRA-FICHA — NFSe (capacidade)

> Ficha canônica de benchmark de **capacidade** do módulo `Modules/NFSe` — emissão de **Nota Fiscal de Serviço eletrônica (ISS municipal)** via **SN-NFSe federal** (LC 214/2025), distinto de `Modules/NfeBrasil` (NF-e/NFC-e produto, ICMS SEFAZ estadual).
> **Gerada:** 2026-07-03 · agente `capterra-senior` · Passo 1 do template de onda (`_Governanca/programa-ondas`).
> **Persona primária:** empresa **oimpresso** biz=1 (Wagner, Tubarão-SC) — prestador de serviço que emite NFSe da própria atividade. Sinal emergente: **Martinho biz=164** (OficinaAuto, mecânica — cutover fiscal PR #2147). **NUNCA biz=4 ROTA LIVRE** (Larissa vestuário CNAE 4781-4/00 só emite NFC-e, não presta serviço com ISS).
> **Alvo de código:** `Modules/NFSe/Services/NfseEmissaoService.php` (~280 LOC — idempotência SHA256 + retry 3× backoff + 9 exceções tipadas) · `Adapters/SnNfseAdapter.php` (~170 LOC — HTTP direto ao webservice REST do SN-NFSe, **DPS hand-rolled, lib oficial não integrada**) · 3 Pages Inertia (`Nfse/Index|Emitir|Show.tsx`) · schema `nfse_emissoes`/`nfse_certificados`/`nfse_provider_configs`.
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) (Capterra-driven) + [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) (tests biz=1) + [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (cliente como sinal) + [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant Tier 0) + [ADR ARQ-0001](adr/arq/0001-cliente-oimpresso-modulo-standalone.md) (cliente oimpresso, standalone).

> ⚠️ **Complementar, não substituto.** Já existe [`BRIEFING.md`](BRIEFING.md) (module-grade v3 ~56→65, foco governança/higiene) + [`SPEC.md`](SPEC.md) (US-NFSE-001..015) + [`PESQUISA_TUBARAO.md`](PESQUISA_TUBARAO.md). Esta ficha mede **CAPACIDADE fiscal** (emissão ISS / multi-prefeitura / RPS / cancelamento / config-municipal / DANFSe) vs os líderes de API de NFSe — eixo que o module-grade não mede. Ver §8 "O que a nota esconde".
> ⚠️ **Sinal fraco (ADR 0105):** esta ficha é decisão-suporte — ajuda a decidir se vale uma **onda completa** ou se o módulo fica em manutenção mínima até sinal qualificado firme. Não abre onda por si só.

---

## 1. Identidade do módulo

- **Nome interno:** `Modules/NFSe` (nWidart) — greenfield, standalone.
- **Domínio:** emissão de **NFS-e (serviço)** com apuração de **ISSQN municipal** — DPS/RPS → webservice do Sistema Nacional NFS-e (SEFIN nacional, `sefin.nfse.gov.br`). **Não** é NF-e mod. 55/65 (isso é `Modules/NfeBrasil`, ICMS estadual). A linha divisória é o imposto: NFSe = ISS (município); NF-e = ICMS (estado).
- **Função:** tela própria de emissão (`Nfse/Emitir`) + listagem (`Nfse/Index`) + detalhe/cancelamento (`Nfse/Show`), com Service de domínio (idempotência + retry) e Job assíncrono na fila `nfse`. Cálculo de ISS = `valor_servicos × alíquota` por config municipal.
- **Estado lifecycle:** Sprint A–C landadas (scaffold + migrations + Service + Adapter + 3 Pages + 13 Pest de Service + isolamento multi-tenant). Sprint D (**deploy sandbox/prod + 1 NFSe real**) **pendente** — `cert A1 (.pfx)` bloqueado em Wagner+contador. **0 NFSe emitida em produção.** Cutover de ambiente para biz=164 (Martinho) merged (PR #2147) mas sem volume confirmado.
- **Clientes diretos:** **nenhum com emissão real.** biz=1 oimpresso (homologação SEFIN); biz=164 Martinho (config prod, volume 0). Larissa/ROTA LIVRE está intencionalmente **OFF** (não presta serviço).
- **Diferencial-chave (potencial, parcialmente realizado):** **SN-NFSe federal direto sem provider terceiro** → custo zero per-emissão (todos os concorrentes cobram assinatura + por-nota) + **vinculação venda→NFSe nativa** (`transaction_id`) + **multi-tenant Tier 0** (cert isolado por `business_id`). **Honestidade:** o diferencial de custo/integração é real e estrutural, mas o motor ainda não emitiu uma nota de verdade nem gera o DANFSe — hoje é um Service correto sem prova de campo.

## 2. Concorrentes-alvo

Pricing qualitativo (Tier 0: não commitar valores BRL — [proibicoes](../../proibicoes.md)); faixa relativa + link à fonte pública.

| # | Concorrente | Tipo | Cobertura municípios | Padrão | Lacuna que o oimpresso pode preencher / onde perde | Fonte |
|---|---|---|---|---|---|---|
| 1 | **Focus NFe** | API fiscal dev-first | **3.000+ prefeituras** (+município novo por taxa fixa/15d) | Nacional **e** ABRASF/próprio (2 APIs) | Ganha volume/cobertura + webhook + 2 APIs; **oimpresso perde feio em cobertura e prova de campo** | [focusnfe.com.br/nfse](https://focusnfe.com.br/nota-fiscal-servico-nfse/) |
| 2 | **PlugNotas** (TecnoSpeed) | API fiscal (hub) | "todo o Brasil numa API" | Nacional + ABRASF + próprio (layout-agnóstico) | Toggle "NFS-e Nacional" no cadastro; layout-agnóstico maduro | [plugnotas.com.br/nfse](https://plugnotas.com.br/nfse/) |
| 3 | **eNotas** | Automação NFSe p/ e-commerce/infoproduto | **500+ prefeituras** (padrão unificado nacional) | Nacional unificado | **Automação-first** (emite na cobrança/pagamento, cancela no reembolso) + ecossistema Hotmart/Kiwify/split; oimpresso não tem trigger automático de emissão | [enotass.com.br](https://enotass.com.br/) |
| 4 | **Nuvem Fiscal** | API REST fiscal low-cost | diversos municípios | Nacional + municipal | **Será desativada 31/07/2026** — mercado em migração (oportunidade de captura, não referência de topo) | [dev.nuvemfiscal.com.br/docs/nfse](https://dev.nuvemfiscal.com.br/docs/nfse/) |
| 5 | **TecnoSpeed** (PlugNotas core) | Middleware fiscal ERP | amplo | Nacional + ABRASF | Referência-topo de robustez fiscal BR (mesma casa do PlugNotas) | [blog.tecnospeed.com.br/api-nfse](https://blog.tecnospeed.com.br/api-nfse/) |
| 6 | **NFE.io** | API fiscal + bundle | amplo | Nacional + municipal | Bundle financeiro/contábil; DX boa | [nfe.io](https://nfe.io/) |
| 7 | **Notaas** | API fiscal freemium | nacional (inclui municípios pequenos) | Nacional | **Freemium** (até 50 notas/mês grátis) + webhook no plano free + white-label — modelo de captura PME | [notaas.com.br](https://www.notaas.com.br/) |
| 8 | **Bling / Omie / Conta Azul** | ERP PME BR c/ NFSe embutida | via provider (Nacional 2026) | Nacional | ERP nativo + NFSe embutida (concorrência do "ERP faz tudo"); UI Bootstrap legado | [ajuda.omie.com.br (Nacional 2026)](https://ajuda.omie.com.br/pt-BR/articles/12270528) |
| 9 | **SN-NFSe / Emissor Nacional** (gov.br) | Portal/API oficial gratuito | ~2.000 municípios conveniados (01/01/2026) | Nacional (é o padrão) | **É a fonte** — oimpresso emite direto nele (custo zero); mas o Emissor Nacional web é o "faça você mesmo" que o ERP substitui com UX + vínculo à venda | [gov.br/nfse](https://www.gov.br/nfse) |

**Outlier interessante:** **Nuvem Fiscal desligando (31/07/2026)** + **ADN descontinuando a API de geração do DANFSe (15/07/2026, NT 008/2026)** = o mercado de NFSe está em **choque técnico simultâneo** neste exato mês. Quem tiver geração de DANFSe própria conforme o novo padrão nacional sai na frente; quem depende da API antiga (incluindo o oimpresso hoje) **quebra**.

## 3. Capacidades em produção (validadas)

> ⚠️ "Em produção" aqui = **existe no código e roda em dev/CI**, NÃO "emitiu NFSe real" (0 em prod). Rigor anti-falso-positivo (grep no Controller/Adapter/Service + Pages + migrations).

```yaml
capacidades_reais_no_codigo:
  - us: US-NFSE-004
    nome: "Emitir NFSe: DPS/RPS → HTTP POST SN-NFSe + cálculo ISS (valor × alíquota) + 9 exceções tipadas PT-BR"
    score: P0
    onde: "NfseEmissaoService::emitir + SnNfseAdapter::emitir/buildDps (Http::post {baseUrl}/nfse, tpAmb por-business)"
    evidencia: "SnNfseAdapter.php:51-79 + NfseEmissaoService.php:101-196; 13 Pest de Service (golden/idempotência/cert/ISS/timeout)"
    em_uso_prod: NAO   # homologação, cert A1 pendente, DPS hand-rolled não validado contra SEFIN real

  - us: US-NFSE-005
    nome: "Idempotência SHA256 (business_id + tomador + valor + descrição + competência) — duplo-submit não gera dupla nota"
    score: P1
    onde: "NfseEmissaoService::emitirInterno:113-121 (busca por idempotency_key + status in [emitida,processando])"
    evidencia: "verificado@0bb65dd; Pest cobre"

  - us: US-NFSE-005
    nome: "Job assíncrono fila 'nfse' + retry 3× backoff exponencial (1s/2s/4s) só em ProviderTimeout"
    score: P1
    onde: "EmitirNfseJob (tries=3) + NfseEmissaoService::emitirInterno:151-195"

  - us: US-NFSE-003
    nome: "Certificado A1 (.pfx) storage encrypted + senha encrypted + validação isExpirado() bloqueia emissão"
    score: P0
    onde: "NfseCertificado (cert_pfx_encrypted/senha_encrypted, pfxDecriptado/senhaDecriptada) + validarCertificado():245-258 + ImportarCertificadoCommand (artisan)"

  - us: US-NFSE-003
    nome: "Config municipal por business: IBGE + IM + CNAE + LC116 + alíquota ISS + série + ambiente"
    score: P0
    onde: "nfse_provider_configs (migration 000002 + 000004 prestador_cnpj) + NfseSeeder (Tubarão IBGE 4218707)"
    alerta: "SEM UI (US-NFSE-014 todo) — config só via seeder/DB/tinker"

  - us: US-NFSE-006
    nome: "Ambiente per-business na EMISSÃO (produção de 1 tenant não vaza pra outro)"
    score: P0
    onde: "NfseEmissaoService::montarPayload:80 (ambiente ← config.ambiente ?? 'homologacao') + SnNfseAdapter::resolveBaseUrl"
    evidencia: "AmbientePorBusinessTest (Http::fake homolog vs prod)"

  - us: US-NFSE-006
    nome: "Cancelamento NFSe (motivo ≥15 chars SEFIN, HTTP DELETE, status cancelada) + guard já-cancelada"
    score: P0
    onde: "NfseEmissaoService::cancelarInterno:211-226 + SnNfseAdapter::cancelar:98-104"
    alerta: "consultar()/cancelar() usam ambiente do BIND GLOBAL, não per-business (US-NFSE-015 todo) — bug latente quando 2º business em prod"

  - us: US-NFSE-004
    nome: "Multi-tenant Tier 0: business_id scope + cert/emissões isolados + cross-tenant Pest"
    score: P0
    onde: "NfseBusinessScope + NfseCertificadoMultiTenantIsolationTest + MultiTenantIsolationTest (withoutGlobalScopes com business_id explícito)"

  - us: US-NFSE-007
    nome: "Vinculação venda → NFSe (transaction_id no payload/emissão + Observer cria rascunho no recurring)"
    score: P1
    onde: "NfseEmissaoPayload.transactionId + TransactionNfseObserver + migration 2026_05_03 add_transaction_id"
    alerta: "PARCIAL — falta mapeamento item→LC116 por produto + botão 'Emitir NFSe' no recurring (legacy Blade)"

  - us: US-NFSE-008/009/010
    nome: "3 Pages Inertia: Index (filtros status/competência + paginate) + Emitir (form tomador/serviço) + Show (status + DANFSE proxy + cancelar)"
    score: P0
    onde: "resources/js/Pages/Nfse/{Index,Emitir,Show}.tsx + NfseController@index/create/store/show/cancelar/pdf"

  - us: US-NFSE-Wave14/25/28
    nome: "LGPD: retention.php (5y CONFAZ / 1y webhook+erro) + PiiRedactor em erro_mensagem/log + OTel spans nfse.emissao/nfse.cancelar"
    score: P2
    onde: "Config/retention.php + NfseEmissaoService::marcarErro:260-278 (PiiRedactor) + OtelHelper::spanBiz"

  - us: US-NFSE-Health
    nome: "NfseHealthCommand: 5 checks (incl. cert_vencimento_alarme 30d WARN)"
    score: P2
    onde: "Console/Commands/NfseHealthCommand.php:144-174"
    alerta: "é comando de health-check CLI — NÃO alerta proativo cron D-30/D-7/D-1 pro usuário"

  # ─── O QUE NÃO É CAPACIDADE REAL (ausente/pendente/risco) ───
  - us: US-NFSE-DANFSE
    nome: "Geração própria do DANFSe (PDF) conforme NT 008/2026"
    score: P0
    onde: "NÃO EXISTE — depende 100% de data['urlDanfse'] do provider; NfseController:261 só faz proxy"
    alerta: "🔴 RISCO LIVE: ADN descontinua a API de geração de DANFSe em 15/07/2026 (NT 008/2026). A responsabilidade passa ao emissor. Hoje o oimpresso não gera — quebra o link do PDF."

  - us: US-NFSE-012/013
    nome: "1 NFSe emitida em produção (marco de sucesso da SPEC inteira)"
    score: P0
    onde: "NÃO OCORREU — Sprint D todo; cert A1 (.pfx) bloqueado em Wagner+contador"

  - us: US-NFSE-SUBST
    nome: "Substituição de NFSe (evento, mantém vínculo original↔nova, janela ≤730d)"
    score: P1
    onde: "NÃO EXISTE (grep 0 matches)"

  - us: US-NFSE-WEBHOOK
    nome: "Webhook/callback assíncrono de eventos SEFIN (autorizada/rejeitada/cancelada)"
    score: P1
    onde: "NÃO EXISTE — só polling via consultar(); retention.php prevê log webhook_municipal mas não há dispatcher"

  - us: US-NFSE-BULK
    nome: "Emissão em lote (recurring mensal, N notas num disparo)"
    score: P1
    onde: "NÃO EXISTE (não-objetivo do MVP, BRIEFING gap #2)"

  - us: US-NFSE-CONFIG-UI
    nome: "Tela /nfse/config (cert + provider + dados fiscais por business, self-service)"
    score: P2
    onde: "NÃO EXISTE (US-NFSE-014 todo) — config via seeder/DB, cert via artisan"
```

## 4. Dimensões de capacidade P0–P3 — comparativa

Legenda: ✅ pareia/supera líder · 🟡 parcial · ❌ ausente. Nota /10 por **mecanismo concreto** (não pelo nome do concorrente). Foco nos eixos do pedido: emissão ISS / multi-prefeitura / RPS / cancelamento / config-municipal.

| ID | Capacidade | Peso | Líder do eixo (mecanismo SOTA) | oimpresso NFSe hoje | Nota /10 |
|---|---|:-:|---|---|:-:|
| **C01 (P0)** | **Emitir NFSe + apuração ISS** (DPS/RPS → SEFIN, valor×alíquota, LC116) | 4 | Focus/PlugNotas (JSON único → valida → prefeitura → PDF; 3000+ munis) | 🟡 **Service+Adapter reais** (HTTP POST DPS, ISS calculado, RPS gerado, 9 exceções, 13 Pest) — MAS **DPS hand-rolled** (lib `nfse-nacional/nfse-php` não integrada, TODO US-004), **0 emissão real**, não validado contra schema SEFIN | **5** |
| **C02 (P0)** | **Cancelamento dentro do prazo legal** (evento) | 4 | Focus/PlugNotas (evento cancelamento + substituição registrados) | 🟡 `cancelar()` c/ motivo ≥15 chars + status + guard já-cancelada — MAS **ambiente global** (não per-business, US-015) + HTTP DELETE pode não casar o evento real do ADN + 0 prod | **4** |
| **C03 (P0)** | **Config municipal por business** (IBGE/IM/alíquota/LC116/regime, self-service) | 4 | Focus/PlugNotas (cadastro empresa + toggle "NFS-e Nacional") | 🟡 **schema completo** (`nfse_provider_configs` todos os campos, seed Tubarão+Martinho) — MAS **sem UI** (US-014 todo); config só via seeder/DB/tinker | **5** |
| **C04 (P0)** | **Multi-prefeitura / cobertura** (Nacional + fallback ABRASF municipal) | 4 | Focus (**2 APIs**: Nacional + tradicional/ABRASF, 3000+ munis); PlugNotas (layout-agnóstico) | 🟡 **arquitetura via SN-NFSe nacional** cobre munis conveniados (~2000) com 1 endpoint; adapter pattern preserva ABRASF — MAS **só `SnNfseAdapter`** (zero ABRASF concreto), 1–2 munis configurados, cobertura não provada | **4** |
| **C05 (P0)** | **DANFSe (PDF) conforme padrão nacional** | 4 | Focus/PlugNotas (geram DANFSe; se adequam à NT 008/2026) | ❌ **depende de `urlDanfse` do provider** — NÃO gera. 🔴 **NT 008/2026 descontinua a API ADN de DANFSe em 15/07/2026** → vira gap P0 LIVE (12 dias) | **1** |
| **C06 (P0)** | **Certificado A1 encrypted + validação + isolamento** | 4 | Focus/PlugNotas (upload cert + validação + storage seguro) | ✅ `cert_pfx_encrypted`/`senha_encrypted` + `pfxDecriptado`/`senhaDecriptada` + `isExpirado()` bloqueia emissão + Pest cross-tenant do cert | **8** |
| **C07 (P0)** | **Multi-tenant Tier 0** (cert+emissões isolados por business_id) | 4 | — (provedores são multi-CNPJ, sem Tier 0 rígido) | ✅ `NfseBusinessScope` + `withoutGlobalScopes` c/ business_id explícito + ambiente per-business na emissão + Pest isolamento | **9** |
| **C08 (P1)** | **RPS / consulta status assíncrona** | 2 | Padrão nacional (RPS→NFSe, data fato gerador = RPS) + provedores consultam por protocolo/webhook | 🟡 **RPS gerado sempre** (YmdHis+4dig, série 'RPS') + `consultar()` por protocolo — MAS consultar usa **ambiente global** (US-015) + sem contingência offline verdadeira | **4** |
| **C09 (P1)** | **Substituição de NFSe** (mantém vínculo, janela legal) | 2 | Focus/PlugNotas/SP (evento cancelamento-por-substituição) | ❌ **ausente** | **0** |
| **C10 (P1)** | **Webhook/callback de eventos** | 2 | Focus/eNotas/Notaas (webhook incl. em planos, até no free) | ❌ **ausente** (só polling); retention prevê log mas não há dispatcher | **1** |
| **C11 (P1)** | **Idempotência** (duplo-submit seguro) | 2 | — (higiene; poucos anunciam) | ✅ `idempotency_key` SHA256 + guard antes de create + retorna existente | **9** |
| **C12 (P1)** | **Emissão automática por evento** (na venda/cobrança/pagamento) | 2 | **eNotas** (emite na cobrança/pagamento, cancela no reembolso — automação-first) | 🟡 `TransactionNfseObserver` cria **rascunho** no recurring — MAS não emite sozinho, falta item→LC116 + gatilho | **3** |
| **C13 (P1)** | **Vinculação venda → NFSe nativa** (`transaction_id`) | 2 | — (provedores são "API client", não ERP nativo) | ✅ `transaction_id` no payload/emissão + pré-fill (DIFERENCIAL vs Bling/Tiny que re-digitam) | **7** |
| **C14 (P2)** | **Carta de correção** (campo discriminação) | 1 | Focus/SP (CCe p/ discriminação de serviços) | ❌ **ausente** | **0** |
| **C15 (P2)** | **UI configuração cert + fiscal** (self-service, sem tinker) | 1 | Focus/PlugNotas (cadastro web completo) | ❌ **ausente** (US-014 todo); cert via artisan, config via DB | **1** |
| **C16 (P2)** | **Dashboard métricas** (volume/erro/ISS pago) | 1 | Provedores têm painel + relatórios | ❌ dashboard ausente (BRIEFING gap #3); `NfseHealthCommand` dá health CLI, não painel | **2** |
| **C17 (P2)** | **LGPD retention + PiiRedactor fiscal** | 1 | — (dever regulatório BR 2026) | ✅ `retention.php` (5y CONFAZ/1y erro+webhook) + `PiiRedactor` em erro/log + OTel spans | **8** |
| **C18 (P2)** | **Alerta proativo cert vencendo** (cron D-30/D-7/D-1) | 1 | Provedores avisam vencimento | 🟡 `NfseHealthCommand::checkCertVencimento` (30d WARN) existe — MAS é health-check CLI, sem cron de notificação ao usuário | **4** |
| **C19 (P3)** | **API REST pública** (terceiros emitem via Sanctum) | 0.5 | Focus/PlugNotas/NFE.io (é o produto deles) | 🟡 `Routes/api.php` existe — sem API pública documentada/Sanctum/rate-limit | **2** |
| **C20 (P3)** | **Readiness Reforma Tributária** (CBS/IBS, split fiscal 2026+) | 0.5 | Provedores adequando ao RTC v2.00 | ❌ `cTribNac` hardcoded '010100'; sem CBS/IBS | **1** |

## 5. Cálculo da nota ponderada

Pesos canônicos: **P0=4 · P1=2 · P2=1 · P3=0.5**.

```
P0 (peso 4): (C01 5 + C02 4 + C03 5 + C04 4 + C05 1 + C06 8 + C07 9) = 36 × 4 = 144
P1 (peso 2): (C08 4 + C09 0 + C10 1 + C11 9 + C12 3 + C13 7)         = 24 × 2 = 48
P2 (peso 1): (C14 0 + C15 1 + C16 2 + C17 8 + C18 4)                 = 15 × 1 = 15
P3 (peso 0.5):(C19 2 + C20 1)                                        =  3 × 0.5=  1.5

Σ ponderado = 144 + 48 + 15 + 1.5 = 208.5

Máximo possível:
  P0: 7×10×4 = 280 · P1: 6×10×2 = 120 · P2: 5×10×1 = 50 · P3: 2×10×0.5 = 10  → 460

nota_capacidade = 208.5 / 460 × 100 = 45.3 → 45/100
```

```
NOTA CAPACIDADE oimpresso NFSe:        45/100
Referência-topo BR (Focus/PlugNotas):  ~83/100  — 3000+ munis, 2 APIs (Nacional+ABRASF), webhook, DANFSe próprio, cancel+substituição, cert web
Referência automação (eNotas):         ~72/100  — emissão automática por evento + cancel no reembolso + 500+ munis (menos cobertura, mais automação)
Fonte oficial gratuita (SN-NFSe gov):   n/a      — é o padrão que o oimpresso consome direto (custo zero, mas "faça você mesmo" sem UX/vínculo)

Gap pro topo BR (Focus): -38 pts. Causa: (1) 0 emissão real em prod (o marco da SPEC inteira nunca ocorreu — cert A1 bloqueado); (2) NÃO gera DANFSe e a API ADN que ele consome morre em 15/07/2026 (NT 008/2026); (3) cobertura de 1 município provada vs 3000; (4) faltam substituição, webhook, config-UI, dashboard.
Onde NFSe já ganha: multi-tenant Tier 0 real (C07=9), idempotência (C11=9), cert encrypted (C06=8), LGPD (C17=8), vínculo venda→NFSe nativo (C13=7) e — o maior — CUSTO ZERO per-emissão (SN-NFSe direto) que nenhum provedor pago replica.
```

**Leitura honesta:** a capacidade (45) fica **abaixo** do topo BR (~83) porque o que define uma solução de NFSe vendável — **emitir de verdade, gerar o DANFSe, cobrir municípios, cancelar/substituir com evento oficial** — ou está em homologação sem prova (C01/C02), ou não existe (C05/C09), ou cobre 1 cidade (C04). O que o oimpresso tem de sólido é a **engenharia ao redor da emissão** (Tier 0, idempotência, cert, LGPD, vínculo à venda) e o **modelo econômico** (SN-NFSe direto = zero por-nota). É um módulo bem-arquitetado que ainda **não passou pela alfândega da SEFIN**.

## 6. Top gaps P0/P1 (pra subir a nota)

| # | Gap | Cap | Esforço (IA-pair, ADR 0106) | ROI / sinal (ADR 0105) | Concorrente que tem |
|---|---|---|---|---|---|
| **G-01** | **🔴 Geração própria do DANFSe conforme NT 008/2026** — a API ADN de DANFSe morre **15/07/2026**. Sem isso o link do PDF quebra pra qualquer nota emitida. Layout nacional único (QR Code, campos, tributário) | C05 | **M–L (~10-14h)** — precisa template DANFSe + QR + validação leiaute | **P0 crítico — prazo em 12 dias.** Vira bloqueador de qualquer emissão real | Focus, PlugNotas, TecnoSpeed |
| **G-02** | **Emitir 1 NFSe real (marco da SPEC)** — desbloquear cert A1 (.pfx) + validar DPS hand-rolled contra SEFIN produção-restrita; se o DPS não casar o schema RTC v2.00, integrar `nfse-nacional/nfse-php` | C01 | **M (~6-8h código)** + humano-limitado (cert Wagner+contador) | **P0** — sem 1 nota autorizada, toda nota da ficha é teórica | todos |
| **G-03** | **Ambiente per-business em `consultar()`/`cancelar()`** (US-NFSE-015) — hoje caem no bind global; quando biz=164 (Martinho) emitir em prod, cancelar cai em homolog e falha | C02/C08 | **S (~2-3h)** | **P0 latente** — vira bug no 1º cancelamento de 2º tenant em prod | dever de correção |
| **G-04** | **Tela `/nfse/config` self-service** (cert upload + dados fiscais por business) — substituir seeder/tinker/artisan | C03/C15 | **M (~8h)** | **P1** — sem isso não escala pra clientes ERP (cada onboarding é manual) | Focus, PlugNotas |
| **G-05** | **Webhook/callback assíncrono** de eventos SEFIN (em vez de polling) + **substituição** (evento) | C09/C10 | **M–L (~10h)** | P1 — cobre ciclo de vida completo da nota; substituição é uso real (corrigir sem cancelar) | Focus, eNotas |
| **G-06** | **Alerta proativo cert vencendo** (cron D-30/D-7/D-1 → notifica) reaproveitando `NfseHealthCommand::checkCertVencimento` | C18 | **S (~2h)** | dever operacional — cert A1 vence anual, sem alerta = emissão para sem aviso | dever |

## 7. Diferenciais oimpresso vs concorrentes

1. **SN-NFSe federal direto, sem provider terceiro → custo zero per-emissão.** Todos os concorrentes cobram assinatura + por-nota (ou por-município novo). O oimpresso fala direto com o SEFIN nacional (`sefin.nfse.gov.br`) — economia de 100% do per-nota. **Vantagem estrutural real** ([PESQUISA_TUBARAO.md](PESQUISA_TUBARAO.md) decidiu isso). Custo: o oimpresso assume a responsabilidade técnica (cert self-managed, DPS, DANFSe) que o provedor abstrai.
2. **Vinculação venda → NFSe nativa** (`transaction_id`) — concorrentes são "API client" externos; o oimpresso emite do mesmo banco da venda, com pré-fill. Bling/Tiny obrigam re-digitação.
3. **Multi-tenant Tier 0 real** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — cert + emissões isolados por `business_id`, ambiente per-business na emissão, Pest cross-tenant. Provedores são multi-CNPJ mas sem isolamento auditável desse nível.
4. **Idempotência SHA256** — duplo-submit não gera nota fiscal duplicada (evita passivo fiscal), com guard antes do INSERT.
5. **LGPD fiscal by-design** — `retention.php` (5y CONFAZ / 1y erro+webhook) + `PiiRedactor` no payload SOAP de erro (CPF/CNPJ tomador) + OTel spans na chamada ao webservice.
6. **Stack moderna** Laravel 13.6 + React 19 + Inertia v3 — vs UI Bootstrap/jQuery dos ERPs BR com NFSe embutida.

## 8. O que a nota esconde (leitura adversarial)

Seis achados, todos com evidência:

1. **O module-grade ~56→65 mede governança, a nota de capacidade (45) mede se emite NFSe.** As 9 dimensões `module-grade-v3` (Tier 0, Pest, doc, arquitetura, LGPD, sec, obs) dão nota boa porque o **entorno** da emissão é bem-feito. Nenhuma pergunta "já autorizou 1 NFSe na SEFIN? gera o DANFSe? cobre mais de 1 município?" — que é o que NFSe **é**. O `na_justified D5` (cliente único interno) infla a percepção: o módulo é "justo" na rubrica, mas **não emitiu nada**.

2. **0 NFSe emitida em produção — o marco da SPEC inteira nunca ocorreu.** US-NFSE-013 (emitir 1 NFSe real) é o "marco de sucesso da SPEC" e está `todo`. Todo o resto (Service, Adapter, Pages, Pest) é infra **a montante** de um evento que não aconteceu. É feature theater fiscal: bonito, testado com `Http::fake`, e nunca falou com a prefeitura de verdade.

3. **O DPS é hand-rolled e pode não casar o schema oficial.** O `SnNfseAdapter::buildDps` monta um `infDps` **simplificado à mão** (o próprio comentário diz "TODO US-NFSE-004: integrar lib `nfse-nacional/nfse-php`"). O layout RTC v2.00 (NT 004/SE-CGNFSe) é extenso; um DPS incompleto é rejeitado pela SEFIN. Os 13 Pest passam porque mockam a resposta — **não validam o XML/JSON contra o XSD real**. A primeira emissão real é onde isso se prova (ou quebra).

4. **🔴 Risco regulatório LIVE em 12 dias.** A NT 008/2026 descontinua a **API de geração do DANFSe** do ADN em **15/07/2026** (prorrogada de 01/07); a responsabilidade de gerar o DANFSe passa ao **sistema emissor**. O oimpresso **não gera** — depende de `data['urlDanfse']` do provider (C05=1). Ou seja: mesmo que emita, o **PDF quebra** a partir de 15/07 se não implementar a geração própria (G-01). Isso não é gap de roadmap distante — é esta quinzena.

5. **"Multi-prefeitura" é 1 prefeitura.** A arquitetura via SN-NFSe nacional *poderia* cobrir os ~2000 municípios conveniados, mas só Tubarão (+ config Martinho biz=164) está seeded, o adapter ABRASF pra municípios **não-conveniados** não existe (só `SnNfseAdapter`), e nada foi provado além de homologação. Focus cobre 3000+ com 2 APIs. O oimpresso tem o *desenho* de cobertura nacional, não a *cobertura*.

6. **Cancelar/consultar têm bug latente de ambiente.** `SnNfseAdapter::consultar/cancelar` usam o **bind global** `config('nfse.ambiente')`, não o ambiente do tenant (US-NFSE-015 catalogado no próprio SPEC). Hoje é inócuo (tudo homolog); vira bug real no **primeiro cancelamento do biz=164 em produção** — cai no endpoint de homologação e falha. A emissão já resolve per-business; cancelar/consultar ficaram pra trás no cutover.

**Síntese adversarial:** o module-grade diz "seguro, documentado, LGPD-ok"; a capacidade diz "tem um Service de emissão correto e bem-isolado que **nunca emitiu**, não gera o DANFSe (que a lei passa a exigir do emissor em 12 dias), cobre 1 cidade, e não cancela/substitui com evento oficial provado". O caminho pra virar produto real passa por **G-01 (DANFSe NT 008) → G-02 (1 nota real) → G-03 (ambiente per-business no cancelar)** — não por polir a UI. **Mas** (sinal fraco, ADR 0105): sem cliente pagante emitindo, isso é investimento especulativo — a onda só se justifica se biz=164 Martinho (ou um candidato ComunicacaoVisual) **de fato** começar a emitir e reportar.

## 9. Anti-padrões / pegadinhas Tier 0 (NFSe)

- ⛔ **Mexer em cálculo de ISS** (`valor_iss = valor_servicos × aliquota_iss`, `pAliq`, retenção) sem **dupla confirmação** (2 caminhos com números) + tabela antes→depois + aprovação humana — regra-mestre Tier 0 valor/estoque ([proibicoes](../../proibicoes.md)). Nota fiscal com imposto errado é passivo legal.
- ⛔ **Declarar "emite NFSe" / "funcionando" sem 1 nota autorizada real** na SEFIN (cole do retorno com `nfseId`/protocolo) — R1 smoke real ([proibicoes §Claim sem evidência](../../proibicoes.md)). `Http::fake` verde ≠ prefeitura aceitou.
- ⛔ **Smoke/Pest em `business_id=4`** (ROTA LIVRE) — biz=4 NÃO emite NFSe (vestuário, só NFC-e). Usar biz=1 (oimpresso) ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).
- ⛔ **Job async sem `$businessId`/payload explícito** — `EmitirNfseJob` recebe DTO com `businessId`; `session()` não vive na fila ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- ⛔ **Ligar produção de 1 business afetando outro** — ambiente é per-business via `nfse_provider_configs.ambiente` (fail-safe → 'homologacao'); nunca voltar pro bind global na emissão. **Estender esse fix a `consultar()`/`cancelar()` (US-015) antes do 2º tenant em prod.**
- ⛔ **`forceDelete()` em `nfse_emissoes`** — número/RPS permanece usado oficialmente; cancelamento marca status, não apaga registro (paridade com regra NfeBrasil / CONFAZ).
- ⛔ **PII do tomador (CPF/CNPJ) em log/erro raw** — passar por `PiiRedactor` (LGPD Art. 6º IX minimização); já aplicado em `marcarErro`, manter em qualquer novo log.
- ⛔ **Assumir que o DPS hand-rolled está correto** — validar contra o XSD/leiaute RTC v2.00 antes de prod; se divergir, integrar `nfse-nacional/nfse-php` (não reinventar o schema).

## 10. Decisão / Nota / Recomendação

### Nota de capacidade
**45/100** — abaixo do topo BR (Focus/PlugNotas ~83, eNotas ~72). Honesto: NFSe é **melhor que o mercado em isolamento Tier 0 (C07=9), idempotência (C11=9), cert encrypted (C06=8), LGPD (C17=8) e no modelo de custo zero per-emissão**, e tem **vínculo venda→NFSe nativo (C13=7)** que provedor externo não replica — mas é **fraco ou vazio no que define emitir NFSe**: DANFSe (C05=1, risco live), cobertura (C04=4, 1 cidade), cancelamento/substituição provados (C02=4, C09=0), e **0 emissão real**. O module-grade e o `na_justified` escondem que a nota fiscal nunca saiu.

### Causa principal do gap (1 frase)
**O módulo construiu toda a engenharia ao redor da emissão (Service, cert, Tier 0, idempotência, LGPD, vínculo à venda) mas nunca autorizou 1 NFSe na SEFIN, não gera o DANFSe que a NT 008/2026 passa a exigir do emissor em 15/07/2026, e cobre 1 município — então a capacidade fiscal real está em homologação, não em produção.**

### Top 3 P0 pra fechar (executável)
1. **G-01 — Geração própria do DANFSe (NT 008/2026)**: prazo LIVE em 12 dias (15/07). Sem isso, qualquer NFSe emitida fica sem PDF válido. **Comece por aqui se a decisão for evoluir o módulo.** Esforço M–L.
2. **G-02 — Emitir 1 NFSe real (marco da SPEC)**: desbloquear cert A1 (Wagner+contador) + validar o DPS hand-rolled contra a produção-restrita; integrar `nfse-nacional/nfse-php` se o schema não casar. Esforço M + humano-limitado.
3. **G-03 — Ambiente per-business em `consultar()`/`cancelar()`** (US-NFSE-015): fecha o bug latente antes do 1º cancelamento do biz=164 em prod. Esforço S.

### Recomendação (sinal fraco, ADR 0105)
**Não abrir onda completa agora.** O módulo é bem-arquitetado mas especulativo (0 cliente emitindo). **Ação mínima de sobrevivência:** avaliar G-01 (DANFSe) como **hotfix defensivo** só se houver intenção real de emitir antes de 15/07 — senão o módulo "quebra em silêncio" (mas como não emite, o dano é zero hoje). **Gatilho pra onda cheia (G-01..G-06):** biz=164 Martinho **de fato** começar a emitir NFSe e reportar, OU ativação de `Modules/ComunicacaoVisual` com candidato prestador de serviço. Até lá: manutenção mínima + esta ficha como decisão-suporte arquivada.

### Referências
- Internas: [BRIEFING.md](BRIEFING.md) · [SPEC.md](SPEC.md) (US-NFSE-001..015) · [PESQUISA_TUBARAO.md](PESQUISA_TUBARAO.md) · [RUNBOOK.md](RUNBOOK.md) · [ADR ARQ-0001](adr/arq/0001-cliente-oimpresso-modulo-standalone.md)
- Código: `Modules/NFSe/Services/NfseEmissaoService.php` · `Adapters/SnNfseAdapter.php` · `Models/{NfseEmissao,NfseCertificado,NfseProviderConfig}.php`
- Session log: [2026-07-03-capterra-nfse.md](../../sessions/2026-07-03-capterra-nfse.md)
- Regulatório 2026: [NT 008/2026 DANFSe (gov.br)](https://www.gov.br/nfse/pt-br/noticias/se-cgnfs-e-publica-nota-tecnica-no-008-2026-com-regras-para-emissao-do-danfse) · [prorrogação 15/07 DANFSe](https://www.gov.br/nfse/pt-br/noticias/se-cgnfs-e-prorroga-o-prazo-para-adequacao-ao-novo-leiaute-do-danfse) · [API ADN descontinuada jul/2026 (FENACON)](https://fenacon.org.br/reforma-tributaria/danfse-tera-novo-padrao-nacional-e-api-oficial-sera-descontinuada-em-julho/) · [adesão municípios NFSe Nacional (CNM)](https://cnm.org.br/comunicacao/noticias/municipios-devem-fazer-adesao-obrigatoria-a-nfs-e-nacional-ou-perderao-recursos-em-2026) · [LC 214/2025 obrigatoriedade](https://www.gov.br/fazenda/pt-br/assuntos/noticias/2025/agosto/a-partir-de-janeiro-de-2026-a-nota-fiscal-de-servico-eletronica-nfs-e-sera-obrigatoria-a-fim-de-simplificar-cotidiano-das-empresas)
- Concorrentes: [Focus NFe](https://focusnfe.com.br/nota-fiscal-servico-nfse/) · [PlugNotas](https://plugnotas.com.br/nfse/) · [eNotas](https://enotass.com.br/) · [Nuvem Fiscal (desativa 31/07/2026)](https://dev.nuvemfiscal.com.br/docs/nfse/) · [comparativo APIs NFSe 2026 (Notaas)](https://www.notaas.com.br/blog/post/api-nfse-nacional-melhor-provedor-emissao-nota-fiscal-de-servico-eletronica-nacional)

---

**Próxima revisão:** 2026-10-03 (trimestre) OU quando biz=164 Martinho emitir 1ª NFSe real OU se G-01 (DANFSe NT 008) for priorizado antes de 15/07.
**Onda:** Passo 1 (adversário concorrente NFSe — programa de ondas). Sinal fraco → decisão-suporte, não abre onda.
