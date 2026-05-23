---
slug: 0186-chain-certificado-sefaz-consulta-cadastro
number: 186
title: "Chain de certificado A1 pra SEFAZ ConsultaCadastro — cert do business primário + institucional fallback"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-23
module: NfeBrasil
quarter: 2026-Q2
tags: [multi-tenant, fiscal, cert-a1, sefaz, lookup-cnpj]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0090-cert-legacy-nfe-brasil-coexistence
  - 0179-cliente-drawer-760px-substitui-show-fullpage
  - 0178-restauracao-campos-fiscais-br-canon
  - 0105-cliente-como-sinal-guiar-sem-mandar
pii: false
review_triggers:
  - oimpresso ganhar 10+ businesses ativos com cert (avaliar custo SEFAZ rate-limit no fallback institucional)
  - SEFAZ-RS/SP/PR mudar protocolo ou endpoint
  - cert oimpresso institucional próximo de vencimento (cron alerta)
---

# ADR 0186 — Chain de certificado A1 pra SEFAZ ConsultaCadastro

## Contexto

Drawer 760 Cliente ([ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md)) faz lookup CNPJ ([PR #1419](https://github.com/wagnerra23/oimpresso.com/pull/1419)) via BrasilAPI gratuita. Limitação inerente: BrasilAPI **não retorna IE (Inscrição Estadual)** — Sintegra/SEFAZ é responsabilidade estadual (27 sistemas distintos). Larissa @ ROTA LIVRE (biz=4, vestuário RS, ~30 cadastros/dia) precisa IE pra emitir NFe.

**Auditoria de mercado 2026-05-23** ([sessions/2026-05-23-arte-busca-cliente-cnpj-ie.md](../sessions/2026-05-23-arte-busca-cliente-cnpj-ie.md)) descobriu:

1. **Nenhum ERP BR grande (Bling/Tiny/Omie/Conta Azul) traz IE automática no flow padrão.** Mercado tolera IE manual. oimpresso está em paridade.
2. **Providers pagos** (FiscalAPI R$130/mês 2000 req, CNPJa, SintegraWS) unificam IE em 27 UFs — opção custo recorrente.
3. **SEFAZ ConsultaCadastro direto** via `nfephp-org/sped-nfe::sefazCadastro` (NfeService::consultaCadastro linha 580 já existe legacy) — zero custo, mas só ~6 UFs funcionam (RS/SP/PR/MG/BA/SC) e requer certificado A1 do **consumidor**.

**Insight Wagner (reframe arquitetural):**

> "se o cliente já tira nota ele vai ter o certificado válido dentro do sistema também. então dá pra ver qual usar. o meu como fallback?"

Verdade — oimpresso é ERP fiscal. **Maioria dos businesses já emite NFe → já tem certificado A1 ativo em `nfe_certificados`** (canon `Modules/NfeBrasil`, multi-tenant business_id, encrypted-at-rest). Não precisa "comprar SEFAZ" pra ninguém — reusa o cert que o business já paga (~R$ 80-300/ano) pra emitir nota.

Cert do oimpresso operacional (biz=1) serve como **fallback institucional** quando o business consumidor não tem cert próprio ativo (cliente recém-cadastrado, cert em renovação, etc).

## Decisão

### Chain de 3 camadas pra carregar cert A1 quando precisar consultar SEFAZ ConsultaCadastro

Novo método `Modules\NfeBrasil\Services\CertificadoService::carregarParaSefazComFallback(int $businessId)` estende `carregarParaSefaz` ([ADR 0090](0090-cert-legacy-nfe-brasil-coexistence.md) já cobre primário + legado):

1. **Primário** — cert do business consultando (canon `nfe_certificados` business-scoped):
   ```php
   NfeCertificado::where('business_id', $businessId)
                ->where('ativo', true)
                ->where('valido_ate', '>', now())
                ->first()
   ```

2. **Fallback legado** — `business.certificado` BLOB ([ADR 0090](0090-cert-legacy-nfe-brasil-coexistence.md)) durante coexistência migration:
   ```php
   CertificadoService::lerCertLegado($businessId)
   ```

3. **Fallback institucional** (NOVO) — cert do oimpresso operacional via `config('fiscal.fallback_business_id')` (default 1):
   ```php
   NfeCertificado::withoutGlobalScope(HasBusinessScope::class)
                ->where('business_id', config('fiscal.fallback_business_id', 1))
                ->where('ativo', true)
                ->where('valido_ate', '>', now())
                ->first()
   ```

   **Tier 0 compliance:** `withoutGlobalScope` aqui é INTENCIONAL e AUDITADO. Cada uso loga em `mcp_audit_log`:
   ```php
   AuditLog::create([
       'business_id' => $businessId, // business CONSUMIDOR
       'event' => 'sefaz.cert.fallback_institutional_used',
       'metadata' => [
           'cert_business_id' => 1,
           'cnpj_consulted_hash' => sha256($cnpj),
           'uf' => $uf,
           'reason' => 'business_sem_cert_ativo',
       ],
   ]);
   ```

4. **Sem cert nenhum** — lança `RuntimeException` graceful que vira badge UI "Configure certificado em Modules/Fiscal".

### Matriz UFs suportadas — config canônica

`config/fiscal.php`:
```php
return [
    'fallback_business_id' => env('FISCAL_FALLBACK_BUSINESS_ID', 1),
    'sefaz_consulta_cadastro_ufs_supported' => [
        'RS' => ['endpoint' => 'svrs', 'status' => 'production'],
        'SP' => ['endpoint' => 'sp', 'status' => 'production'],
        'PR' => ['endpoint' => 'pr', 'status' => 'production'],
        'MG' => ['endpoint' => 'mg', 'status' => 'production'],
        'BA' => ['endpoint' => 'ba', 'status' => 'production'],
        'SC' => ['endpoint' => 'svrs', 'status' => 'production'],
        // demais 21 UFs: skip — BrasilAPI baseline + IE manual + badge UI
    ],
];
```

### Endpoint canônico novo — `Modules/Crm/Http/Controllers/ClienteLookupController::cnpjSefaz`

Separado do `::cnpj` (BrasilAPI puro) pra desacoplar:
- `GET /cliente/lookup/cnpj/{cnpj}` — BrasilAPI (atual, sem mudança)
- `GET /cliente/lookup/cnpj/{cnpj}/sefaz?uf=RS` — chama `SefazConsultaCadastroService` → IE oficial

Frontend (`IdentificacaoTab.handleCnpjLookup`):
1. Chama BrasilAPI (já existente) — preenche tudo MENOS IE
2. SE `uf_alvo` ∈ supported UFs E business tem qualquer cert na chain:
   chama endpoint SEFAZ → preenche IE + badge fonte
3. SENÃO:
   IE em branco + badge contextual

### Badges UI (4 estados)

| Estado | Cor | Mensagem |
|---|---|---|
| IE preenchida via cert primário | 🟢 verde | "IE via SEFAZ-RS (certificado da empresa)" |
| IE preenchida via cert institucional | 🟠 laranja | "IE via SEFAZ-RS (certificado oimpresso — renove o seu em /fiscal/config)" |
| UF não suportada | ⚪ cinza | "SEFAZ-GO não disponível — preencha IE manualmente" |
| Sem cert nenhum | 🔴 vermelho | "Configure certificado em /fiscal/config pra preencher IE automaticamente" |

### Refactor obrigatório legacy

`app/Http/Controllers/NfeController::consultaCadastro` (linha 686) atual é endpoint legacy não-Inertia (`echo $json` direto) usando `Business::find->certificado` (não-canon). Marcar como `@deprecated` apontando pro novo endpoint canon `ClienteLookupController::cnpjSefaz`. Remoção em ADR futura quando consumidores legacy migrarem.

## Justificativa

**Por que chain em vez de só primário ou só institucional:**

- **Só primário:** business novo (recém cadastrado, sem cert ainda) não tem IE auto. UX ruim no first-use — Larissa hoje teria IE auto, mas cliente novo de Wagner amanhã não.
- **Só institucional:** todos passam pelo cert oimpresso → rate limit SEFAZ por UF (1-3 req/s típico) vira gargalo em escala; multi-tenant fica sketchy (1 cert pra todos viola intent ADR 0093).
- **Chain primário→legado→institucional:** business com cert paga zero overhead pro oimpresso (escala linear); business sem cert tem fallback graceful (UX boa) até configurar próprio cert; audit log mantém Tier 0 limpo.

**Por que matriz UFs explícita em config (não hardcoded):**

Comunidade nfephp-org + SAP confirmam que cobertura SEFAZ ConsultaCadastro varia ao longo do tempo. UF que funciona hoje pode quebrar; UF que não funciona pode entrar em produção. Config-driven permite ligar/desligar UFs sem deploy.

**Por que NÃO partir pra provider pago FiscalAPI/CNPJa agora:**

- Larissa biz=4 está em RS — SEFAZ-RS funciona excelente (SVRS é a referência).
- Cliente atual = 1 ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal).
- Custo recorrente R$130/mês sem necessidade comprovada é over-engineering.
- Provider pago fica como opção futura **quando** o sinal aparecer (cliente piloto fora de RS/SP/PR/MG/BA/SC pedindo IE auto).

**Por que reabrir essa ADR:** ver `review_triggers` no frontmatter.

## Consequências

### Positivas

- **Larissa biz=4 RS:** IE auto desde dia 1 via cert dela mesma. Zero custo.
- **Cliente novo sem cert ainda:** IE auto via fallback institucional (cert oimpresso) — UX first-use boa.
- **Multi-tenant Tier 0 limpo:** `withoutGlobalScope` apenas no fallback institucional, intencional + auditado em `mcp_audit_log`. Felipe/Maíra entendem via `decisions-search`.
- **Reusa canon existente:** `nfe_certificados` table + `CertificadoService` ([ADR 0090](0090-cert-legacy-nfe-brasil-coexistence.md)) + `nfephp-org/sped-nfe::sefazCadastro` (`NFeService::consultaCadastro` linha 580). Zero deps novas.
- **Custo recorrente: R$0.** Cert que cada business já paga pra NFe é reusado pra ConsultaCadastro (mesma assinatura WS SEFAZ).
- **Refactor entrega tira dívida técnica** do `NfeController::consultaCadastro` legacy `echo $json`.
- **Bate mercado em design** — Bling/Tiny/Omie deixam IE manual. oimpresso resolve auto pras 6 UFs principais.

### Negativas / Trade-offs

- **Cobertura limitada a ~6 UFs.** Outras 21 UFs vêm com badge "preencha manual". Tolerável (mesmo Bling/Tiny não resolvem).
- **Cert institucional oimpresso vira ponto crítico operacional.** Wagner precisa renovar antes de vencer. Mitigação: cron health-check daily com alerta ≤30 dias.
- **Rate limit SEFAZ por UF.** Se oimpresso escalar pra 10+ businesses sem cert próprio todos consultando via fallback, rate-limit SEFAZ-RS pode bater. Mitigação: cache Redis 30d por `(cnpj, uf)` no `SefazConsultaCadastroService`. Provider pago vira opção quando sinal de saturação aparecer.
- **`withoutGlobalScope` é code smell** se mal usado em outro lugar. Mitigação: comment explícito + Pest test garantindo que essa é a única query no codebase com `withoutGlobalScope(HasBusinessScope::class)` apontando pra `nfe_certificados`.
- **UI badge laranja "cert institucional usado"** pode confundir cliente novo ("por que estão usando cert de outra empresa?"). Mitigação: copy claro "preenchimento automático cortesia oimpresso enquanto você configura o seu — clique aqui pra configurar".

### Riscos mitigados

- ❌ ~~Cert business expira → drawer quebra silencioso~~ → ✅ fallback institucional graceful + cron alerta vencimento ≤30 dias.
- ❌ ~~Multi-tenant viola ADR 0093~~ → ✅ `withoutGlobalScope` apenas no fallback institucional, intencional, log auditoria por uso.
- ❌ ~~SEFAZ-XX cai temporariamente~~ → ✅ try/catch + IE em branco + badge "SEFAZ indisponível, tente novamente" + cache Redis serve resposta anterior se houver.
- ❌ ~~LGPD: oimpresso ver CNPJ consultado pelo cliente~~ → ✅ audit log armazena `sha256(cnpj)` (não plain), proporcional ao princípio de minimização LGPD Art. 6º III.

## Implementação faseada

| Fase | Escopo | Esforço (IA-pair ADR 0106 10x) |
|---|---|---|
| 1 | `CertificadoService::carregarParaSefazComFallback` + audit log | 1-2h |
| 2 | `Modules/NfeBrasil/Services/SefazConsultaCadastroService` + cache Redis 30d | 2-3h |
| 3 | `ClienteLookupController::cnpjSefaz` endpoint + refactor `IdentificacaoTab.handleCnpjLookup` cross-tab | 1-2h |
| 4 | UI badges 4 estados em `IdentificacaoTab` + tooltip explicativo | 1h |
| 5 | Pest cobertura: 4 estados de cert + cache hit/miss + UFs supported/not + cross-tenant Tier 0 | 1-2h |
| 6 | Cron `php artisan fiscal:cert-health-check` daily 06:00 BRT alerta ≤30d | 1h |
| 7 | Deprecate `NfeController::consultaCadastro` legacy + redirect docs | 30min |

**Total: 7.5-11.5h IA-pair.** Reversível por fase (feature-flag `fiscal.sefaz_consulta_cadastro_enabled`).

## Referências

- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL (justifica `withoutGlobalScope` auditado)
- [ADR 0090](0090-cert-legacy-nfe-brasil-coexistence.md) — Cert legacy `business.certificado` BLOB → `nfe_certificados` migration coexistence
- [ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) — Drawer 760 Cliente
- [ADR 0178](0178-restauracao-campos-fiscais-br-canon.md) — Campos fiscais BR (precedente reframe regressão UPOS 6.7)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal (justifica não comprar provider pago agora)
- [Sessão estado-da-arte 2026-05-23](../sessions/2026-05-23-arte-busca-cliente-cnpj-ie.md) — Comparativo concorrentes BR + providers + SEFAZ
- [feedback-lookup-cnpj-sobrescreve-dados.md](../reference/feedback-lookup-cnpj-sobrescreve-dados.md) — Política Wagner sobrescrita dados oficiais
- [PR #1419](https://github.com/wagnerra23/oimpresso.com/pull/1419) — Implementação parcial CNPJ lookup expandido
- [PR #1422](https://github.com/wagnerra23/oimpresso.com/pull/1422) — Naming canon EnderecoTab + coluna `numero` BR
- `nfephp-org/sped-nfe::sefazCadastro` — biblioteca WS SEFAZ ConsultaCadastro2
- `app/Services/NFeService.php:580` — `consultaCadastro` legacy a refatorar
- `Modules/NfeBrasil/Services/CertificadoService::carregarParaSefaz` — interface base a estender
