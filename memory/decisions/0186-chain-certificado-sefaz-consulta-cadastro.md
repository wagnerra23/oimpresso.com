---
slug: 0186-chain-certificado-sefaz-consulta-cadastro
number: 186
title: "Chain de certificado A1 + SEFAZ ConsultaCadastro merge paralelo — IRREVOGÁVEL"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-23
module: NfeBrasil
quarter: 2026-Q2
tags: [multi-tenant, fiscal, cert-a1, sefaz, lookup-cnpj, tier-zero, irrevogavel, no-regression]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0090-cert-legacy-nfe-brasil-coexistence
  - 0179-cliente-drawer-760px-substitui-show-fullpage
  - 0178-restauracao-campos-fiscais-br-canon
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
review_triggers:
  - SEFAZ-RS/SP/PR mudar protocolo XML ou endpoint do WS ConsultaCadastro2
  - oimpresso ganhar 10+ businesses ativos sem cert próprio (avaliar custo SEFAZ rate-limit no fallback institucional)
  - cert oimpresso institucional próximo de vencimento (cron alerta — não-regressão de invariante #5)
  - Wagner decidir adotar provider pago FiscalAPI/CNPJa como provider adicional (NÃO substitui SEFAZ — adiciona)
---

# ADR 0186 — Chain de certificado A1 + SEFAZ ConsultaCadastro merge paralelo

> **STATUS: IRREVOGÁVEL.** Esta ADR é canon Tier 0 — invariantes listados na §Invariantes NÃO podem regredir. Pest guardas (§Guardas anti-regressão) bloqueiam merge em violações detectadas. Mudança em qualquer invariante exige nova ADR com `supersedes: [186]` + aprovação Wagner.

## Contexto

Drawer 760 Cliente ([ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md)) precisa preencher IE + dados fiscais automaticamente pra emissão de NFe. **BrasilAPI gratuita não retorna IE** (Sintegra/SEFAZ é responsabilidade estadual, 27 sistemas distintos). Larissa @ ROTA LIVRE (biz=4, vestuário RS, ~30 cadastros/dia) é cliente piloto — feedback "IE não vem" foi gatilho da auditoria.

[Auditoria fiscal 2026-05-23](../sessions/2026-05-23-arte-busca-cliente-cnpj-ie.md) avaliou **3 técnicas** com nota:

| Técnica | Estratégia | Nota | Decisão |
|---|---|---:|---|
| A | BrasilAPI sequencial + SEFAZ só pra IE | 82/100 | superseded |
| B | SEFAZ-RS first + BrasilAPI fallback | 65/100 | rejeitada (cobertura ruim cliente PJ não-RS + PF) |
| **C** | **Merge paralelo BrasilAPI + SEFAZ com autoridade por campo** | **95/100** | **aceita IRREVOGÁVEL** |

**6 das 10 rejeições NFe mais comuns** dependem de campos que **só SEFAZ retorna** (`IE`, `cSit`, `indCredNFe`). Catálogo: 207 (CNPJ inválido), **233/235/487/770 (IE inválida/inativa/sem cadastro)**, 478 (não habilitado), 656 (endereço divergente fiscal), 778 (cMun inválido), 215 (schema XML).

Cert A1 já é canon `nfe_certificados` ([Modules/NfeBrasil](../../Modules/NfeBrasil/), [ADR 0090](0090-cert-legacy-nfe-brasil-coexistence.md)). **Maioria dos clientes oimpresso JÁ emite NFe → JÁ tem cert válido** — reusa em vez de comprar provider pago. Cert oimpresso institucional (biz=1) atende clientes recém-cadastrados sem cert próprio ainda.

## Decisão canônica (Técnica C)

### 1. Chain de 3 camadas pra carregar cert A1

`Modules\NfeBrasil\Services\CertificadoService::carregarParaSefazComFallback(int $businessId)`:

```
1. Cert PRIMÁRIO business consumidor (nfe_certificados business-scoped)
   NfeCertificado::where('business_id', $businessId)->where('ativo', true)
                ->where('valido_ate', '>', now())->first()

2. Cert LEGADO `business.certificado` BLOB ([ADR 0090](0090-cert-legacy-nfe-brasil-coexistence.md) coexistência)

3. Cert INSTITUCIONAL oimpresso operacional
   config('fiscal.fallback_business_id', 1)
   NfeCertificado::withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $fallbackBusinessId)
                ->where('ativo', true)->where('valido_ate', '>', now())->first()
   + audit log mcp_audit_log event='sefaz.cert.fallback_institutional_used'
     metadata.cnpj_hash = sha256(cnpj) // LGPD Art. 6º III minimização

4. RuntimeException → frontend renderiza badge UI "configure cert"
```

**Ordem das camadas IMUTÁVEL.** Fallback institucional NUNCA precede primário (invariante #1).

### 2. SEFAZ ConsultaCadastro paralelo (Promise.all)

`SefazConsultaCadastroService::consultar($cnpj, $uf, $businessId)` retorna **contrato fixo**:

```php
[
    // Base — sempre presente.
    'ie'               => string|null,
    'situacao'         => string|null,    // cSit raw
    'situacao_label'   => string|null,    // PT-BR canônico
    'nome'             => string|null,
    'uf'               => string,
    'fonte'            => 'sefaz_' . strtolower($uf),
    'cert_source'      => 'nfe_brasil'|'business_legado'|'institutional_fallback',
    'cert_business_id' => int,
    // Técnica C — derivações fiscais.
    'ind_ie_dest'      => 1|2|9,           // NFe <dest>/<indIEDest> obrigatório
    'ind_cred_nfe'     => 0|1|2|3|4|null,
    'regime_apuracao'  => string|null,
    'endereco_sefaz'   => array,           // logradouro/numero/bairro/cmun/cep/uf
    'alertas'          => array,           // [['code', 'severity', 'msg'], ...]
    'consultado_em'    => string,          // ISO8601
]
```

`alertas[].severity`: `high` (cancelado/baixado/não habilitado), `medium` (suspenso), `low` (não credenciado emissão).

`derivarIndIeDest($ie, $cSit)` regra fixa:
- IE = `ISENTO` (case-insensitive) → `2`
- IE válida + cSit ∈ {`0`,`2`,`4`} → `1`
- sem IE OR IE=`0` OR cSit ∈ {`3`,`5`} → `9`

### 3. Matriz UFs supported em `config/fiscal.php`

6 UFs com WS SEFAZ ConsultaCadastro2 funcionando em produção: **RS, SP, PR, MG, BA, SC**. Config-driven — adicionar UF nova não exige deploy. UFs fora retornam `404 reason=uf_unsupported` → badge UI "preencha manual".

### 4. Persistência dos campos derivados (contacts schema)

Migration `2026_05_23_120000_add_sefaz_consulta_fields_to_contacts` adiciona:
- `ind_ie_dest` tinyint(1) — enum 1/2/9
- `sefaz_cad_sit` varchar(20) — enum `habilitado|nao_habilitado|suspenso|cancelado|paralisado|baixado`
- `sefaz_cad_ind_cred_nfe` tinyint(1) — enum 0-4
- `sefaz_cad_consultado_em` timestamp

`ClienteAutosaveController::identificacao` validator aceita os 4 campos. `shapeContactResponse` retorna.

### 5. Frontend `IdentificacaoTab.handleCnpjLookup` (Promise.all)

```ts
const [brasilApiR, sefazR] = await Promise.all([
  fetch(`/cliente/lookup/cnpj/${digits}`),
  ufInicial ? fetch(`/cliente/lookup/cnpj/${digits}/sefaz?uf=${ufInicial}`) : null,
]);
// Segunda chance SEFAZ se ufInicial vazia + BrasilAPI revelar state.
// Merge por autoridade:
//   - IE → SEFAZ (única fonte)
//   - razão social → SEFAZ se presente, senão BrasilAPI
//   - fantasia, QSA, telefone, email → BrasilAPI (SEFAZ não retorna)
// PATCH batch dos 4 campos derivados em /identificacao após sucesso.
// Badge UI 4 estados + append alerta SEFAZ severity high.
```

## Invariantes (NÃO podem regredir)

> **Lista numerada de invariantes Tier 0.** Cada um tem **guarda Pest** (§Guardas) bloqueando merge em violação.

1. **Ordem da chain de cert é imutável**: primário → legado → institucional. Fallback institucional NUNCA é tentado antes do primário. Qualquer commit que inverta a ordem em `CertificadoService::carregarParaSefazComFallback` é regressão.

2. **`withoutGlobalScope(ScopeByBusiness)` em `NfeCertificado` é restrito UMA chamada** — somente na camada #3 (fallback institucional). Multi-tenant Tier 0 [ADR 0093](0093-multi-tenant-isolation-tier-0.md) IRREVOGÁVEL. Qualquer outra ocorrência no codebase é bug.

3. **Audit log obrigatório no fallback institucional** — `mcp_audit_log` recebe entry com `event='sefaz.cert.fallback_institutional_used'` toda vez que camada #3 é acionada. Audit graceful (try/catch) mas tentativa é obrigatória. CNPJ no audit log SEMPRE como `sha256(cnpj)` — nunca plain (LGPD Art. 6º III).

4. **Promise.all paralelo BrasilAPI + SEFAZ NÃO pode virar sequencial**. Latência única é parte da decisão. Refactor que volte `await brasilApi; await sefaz;` é regressão. Validado por lint do código de `IdentificacaoTab.handleCnpjLookup`.

5. **Merge campo-a-campo respeita autoridade fixa**:
   - `ie` → SEFAZ sempre (BrasilAPI NÃO retorna)
   - `ind_ie_dest` → SEFAZ derivado (única fonte canônica)
   - `sefaz_cad_sit`, `sefaz_cad_ind_cred_nfe`, `regime_apuracao` → SEFAZ (única fonte)
   - `nome_fantasia`, `qsa`, `capital_social`, `telefone`, `email`, `simples_optante` → BrasilAPI (SEFAZ não retorna)
   - `razao_social`, `endereco` → SEFAZ se presente, BrasilAPI fallback
   - Inverter autoridade (ex: priorizar BrasilAPI pra IE) é regressão.

6. **Contrato de retorno `SefazConsultaCadastroService::consultar` é estável**. Os 13 campos listados na §Decisão #2 são parte do contrato público. Remover campo = breaking change que exige nova ADR. Adicionar campo opcional = OK sem ADR.

7. **Matriz UFs supported é config-driven (`config/fiscal.php`)** — hardcoded em código é regressão. Permite ligar UF nova sem deploy quando SEFAZ publicar.

8. **Migration `2026_05_23_120000` é idempotente** (Schema::hasColumn check). Não pode ser modificada — apenas append em nova migration. ADR 0093 §append-only respeitado.

9. **Validator `ind_ie_dest` aceita APENAS enum {1,2,9}** — outros valores rejeitados 422. NFe XML `<indIEDest>` admite só esses 3 valores na spec SEFAZ.

10. **Warning antecipado UI quando `cSit ≠ habilitado` é compromisso de UX**. Cliente cancelado/suspenso mostra badge severity high pra evitar perder venda + rejeição NFe. Remover warning silenciosamente é regressão.

11. **Timeout enforcement (anti-hang)** — backend SEFAZ SOAP usa `Tools::soap->timeout(4)` (4s connect + 24s total). Frontend usa `AbortController` com 8s pra cada fetch SEFAZ. Drawer NUNCA fica travado em loading state. Mensagem visual diferenciada quando timeout: "SEFAZ-XX demorou — tente de novo ou preencha IE manual". Valores são config-driven (`fiscal.sefaz_consulta_cadastro_timeout_seconds` + `fiscal.sefaz_consulta_cadastro_frontend_timeout_ms`). Remover timeout = regressão (UX trava em SEFAZ lenta).

## Guardas anti-regressão

Pest tests obrigatórios em `tests/Feature/Cliente/SefazConsultaCadastroChainTest.php` + `tests/Feature/Cliente/SefazInvariantesAntiRegressaoTest.php` (NOVO):

| # Invariante | Guarda Pest | Falha → bloqueia merge? |
|---|---|---|
| 1 (ordem chain) | `chain_cert_primario_antes_de_institucional` — mock primário disponível, asserta que institucional NÃO é chamado | ✅ |
| 2 (withoutGlobalScope único) | `apenas_carregarParaSefazComFallback_usa_withoutGlobalScope_em_nfe_certificados` — grep no codebase | ✅ |
| 3 (audit log fallback) | `fallback_institucional_grava_mcp_audit_log_com_sha256` — mock chain falhar primário+legado, asserta DB insert | ✅ |
| 4 (paralelo TS) | `frontend_usa_promise_all_no_handleCnpjLookup` — grep `Promise.all(\[brasilApiP, sefazP\])` no `IdentificacaoTab.tsx` | ✅ |
| 5 (autoridade merge) | `merge_autoridade_ie_sempre_sefaz` — mock SEFAZ ret IE='X', BrasilAPI ret IE='Y' (improvável), state final = 'X' | ✅ |
| 6 (contrato Service) | `sefaz_service_retorna_13_campos_canonicos` — chamada com mock SEFAZ válido, asserta keys do array | ✅ |
| 7 (config UFs) | `matriz_ufs_é_config_driven_não_hardcoded` — grep `'RS' \|\| 'SP' \|\|` no Service (proibido fora do config) | ✅ |
| 8 (migration idempotente) | `migration_sefaz_é_idempotente` — roda duas vezes sem erro | ✅ |
| 9 (enum ind_ie_dest) | `ind_ie_dest_rejeita_fora_enum_1_2_9` — já implementado | ✅ |
| 10 (warning cSit) | `cSit_diferente_habilitado_gera_alerta_high_severity` — mock SEFAZ ret cSit=2, asserta alertas[0].severity=='medium' (e cSit=3 → high) | ✅ |
| 11 (timeout enforcement) | `service_aplica_timeout_via_tools_soap` + `frontend_usa_abortcontroller_com_timeout` — grep `$tools->soap->timeout(` no Service e `new AbortController()` + `signal:` no TSX | ✅ |

Workflow CI `governance-gate.yml` roda `pest --filter SefazInvariantesAntiRegressao --stop-on-failure` — falha bloqueia merge.

## Justificativa

**Por que Técnica C é IRREVOGÁVEL e não as outras:**

| Caso real Larissa | A (sequential) | B (SEFAZ-RS first) | **C (paralelo)** |
|---|---|---|---|
| Cliente PJ RS contribuinte | 🟢 (latência 2x) | 🟢 | 🟢 (latência única) |
| Cliente PJ SP contribuinte | 🟢 (latência 2x) | 🟡 (SEFAZ-RS não conhece SP) | 🟢 (latência única + SEFAZ-SP) |
| Cliente PJ GO contribuinte | 🟡 (BrasilAPI sem IE) | 🟡 | 🟡 |
| Cliente PJ RS isento | parcial | 🟢 | 🟢 (`ind_ie_dest=2` auto) |
| Cliente PJ RS cancelado | sem alerta | sem alerta | 🟢 **warning evita NFe rejeitada** |
| Cliente PF | 🟢 | 🔴 (SEFAZ desperdiça consulta) | 🟢 (SEFAZ skip explicit) |

**Por que esta ADR é IRREVOGÁVEL e não só "aceita":**
- 6/10 rejeições NFe mais comuns dependem de dados aqui persistidos. Regressão = vendedor recebe rejeição que poderia ter evitado.
- Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) é IRREVOGÁVEL — o `withoutGlobalScope` autorizado neste serviço é exceção controlada que não pode vazar (invariante #2).
- Cert oimpresso institucional é compromisso operacional com clientes ativos — desligar sem aviso quebra cadastros em produção.
- A complexidade de Técnica C vs A é justificada pela auditoria das 3 técnicas (95 vs 82) — voltar pra A é regressão fiscal mensurável.

## Consequências

### Positivas

- **Custo recorrente R$ 0** — reusa cert que cada business já paga pra NFe.
- **Larissa biz=4 RS**: IE auto + warnings antecipados desde dia 1 via cert dela mesma.
- **Cliente novo sem cert**: IE auto via fallback institucional — UX first-use boa.
- **6 das 10 rejeições NFe comuns evitadas** via warning antecipado UI antes da emissão.
- **Bate Bling/Tiny/Omie em design fiscal** — nenhum exibe `cSit` ou warnings no cadastro.
- **Multi-tenant Tier 0 limpo**: `withoutGlobalScope` único, intencional, auditado.
- **Reusa canon existente** `nfe_certificados` + `CertificadoService` + `nfephp-org/sped-nfe`.

### Negativas / Trade-offs

- **Cobertura UFs limitada (6/27)**. Outras 21 UFs vêm com badge "preencha manual". Tolerável (Bling/Tiny também não cobrem).
- **Cert institucional oimpresso é ponto crítico operacional**. Mitigação: cron health-check daily com alerta ≤30 dias (fase 6 deste ADR).
- **Complexidade Técnica C > Técnica A** — mais código, mais Pest. Mitigado por guardas anti-regressão automatizadas (§Guardas).
- **Rate-limit SEFAZ por UF** pode bater em escala (10+ businesses sem cert próprio usando fallback). Mitigação: cache Redis 30d compartilhado + escalação pra provider pago como aditivo quando sinal aparecer ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)).

### Riscos mitigados via invariantes + guardas

- ❌ ~~Cert business expira → drawer quebra~~ → ✅ fallback institucional + cron alerta (invariante #5 review_trigger)
- ❌ ~~`withoutGlobalScope` vaza pra outro lugar~~ → ✅ guarda Pest invariante #2 bloqueia merge
- ❌ ~~Audit log esquecido no fallback~~ → ✅ guarda Pest invariante #3
- ❌ ~~Refactor volta sequential mata UX~~ → ✅ guarda lint invariante #4
- ❌ ~~Alguém prioriza BrasilAPI pra IE~~ → ✅ guarda Pest invariante #5
- ❌ ~~UF hardcoded fora do config~~ → ✅ guarda grep invariante #7
- ❌ ~~Warning UI removido silenciosamente~~ → ✅ guarda Pest invariante #10
- ❌ ~~LGPD: CNPJ plain em audit~~ → ✅ invariante #3 + guarda Pest sha256 obrigatório

## Implementação faseada (status)

| Fase | Escopo | Status |
|---|---|---|
| 1 | `CertificadoService::carregarParaSefazComFallback` + audit log | ✅ commit `c46790922` |
| 2 | `SefazConsultaCadastroService` + cache Redis 30d + retorno expandido + `derivarIndIeDest` + alertas | ✅ commits `c46790922` + `db0240304` |
| 3 | `ClienteLookupController::cnpjSefaz` endpoint | ✅ commit `c46790922` |
| 4 | UI badges 4 estados + warnings severity | ✅ commit `db0240304` |
| 5 | Pest cobertura básica + invariantes guardas | ⏳ parcial — guardas anti-regressão pendentes |
| 5b | **Guardas anti-regressão completas** (§Guardas table) | ⏳ a implementar nesta consolidação |
| 6 | Cron `php artisan fiscal:cert-health-check` daily — alerta ≤30d | ⏳ PR seguinte |
| 7 | Deprecate `NfeController::consultaCadastro` legacy | ⏳ PR seguinte |

## Referências

- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL (justifica `withoutGlobalScope` auditado)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio duro #6 multi-tenant)
- [ADR 0090](0090-cert-legacy-nfe-brasil-coexistence.md) — Cert legacy coexistence
- [ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) — Drawer 760 Cliente
- [ADR 0178](0178-restauracao-campos-fiscais-br-canon.md) — Restauração campos fiscais BR
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal
- [Sessão estado-da-arte 2026-05-23](../sessions/2026-05-23-arte-busca-cliente-cnpj-ie.md) — Comparativo concorrentes BR + providers + SEFAZ + 3 técnicas
- [feedback-lookup-cnpj-sobrescreve-dados.md](../reference/feedback-lookup-cnpj-sobrescreve-dados.md) — Política sobrescrita dados oficiais
- [PR #1431](https://github.com/wagnerra23/oimpresso.com/pull/1431) — Implementação canônica
- `nfephp-org/sped-nfe::sefazCadastro` — biblioteca WS SEFAZ ConsultaCadastro2
- `Modules/NfeBrasil/Services/CertificadoService::carregarParaSefazComFallback` — chain
- `Modules/NfeBrasil/Services/SefazConsultaCadastroService::consultar` — merge service
- `Modules/Crm/Http/Controllers/ClienteLookupController::cnpjSefaz` — endpoint
- `resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx::handleCnpjLookup` — frontend paralelo

---

**Histórico de evolução** (mantido pra rastreio, não modifica decisão canônica acima):

- **2026-05-23 v1** — Técnica A (BrasilAPI + SEFAZ sequencial só pra IE). Nota 82/100.
- **2026-05-23 v2 (esta versão CANON)** — Auditoria fiscal 3 técnicas → Técnica C (merge paralelo + warnings antecipados + derivação `ind_ie_dest` + persistência 4 colunas). Nota 95/100. **IRREVOGÁVEL.**
