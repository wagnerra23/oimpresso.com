---
id: requisitos-nfse-briefing
module: NFSe
status: piloto
status_nota: "homologação SN-NFSe federal + cutover fiscal por-business habilitado (biz=164 Martinho — #2147); cert A1 pendente Wagner"
piloto: oimpresso biz=1 (Wagner, Tubarão-SC) homologação + biz=164 (Martinho OficinaAuto) cutover por-business
last_review: 2026-11-17
updated_at: "2026-11-17"
owner: E
parent_adr: ARQ-0001
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-sistema-charter-capterra-governanca-escopo
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0153-module-grade-rubrica-v1
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
  - 0156-module-grade-v3-errata-otel-helper-na-justified
nota_stale: "número de module-grade abaixo é HISTÓRICO (2026-05-16) e assim FICA: a rubrica foi aposentada em 2026-09-15 (ADR 0399) e não há fonte viva que o substitua. Não restatear à mão (LC-08)"
na_justified: [D5]
---

# BRIEFING — `Modules/NFSe`

> **Tipo:** BRIEFING canônico do módulo — 1 página executiva
> **Refs:** [proibicoes.md §Sempre fazer](../../proibicoes.md) — Tier 0 "BRIEFING.md atualizado em todo PR mergeado"
> **Skill auto-trigger:** `brief-update` (Tier B)
> **Owner:** Eliana[E] · **Paralelo a:** Cycle 01 (não bloqueia)
> Última atualização: 2026-11-16 (ver §13 — é a data que o `briefing-code-staleness` lê)

---

## 1. O que é

**URL principal:** `https://oimpresso.com/nfse`
**Backend:** `Modules/NFSe/`
**Frontend:** `resources/js/Pages/Nfse/` (Index, Emitir, Show)

Emissão de Nota Fiscal de Serviço eletrônica (NFSe) via **SN-NFSe federal** (LC 214/2025), direto SEFIN nacional sem provider terceiro. Município: Tubarão-SC (IBGE 4218707). Bucket de governança = `functional_horizontal` ([module.json](../../../Modules/NFSe/module.json), 2026-05-17 [W]) — **infra fiscal cross-vertical, não cliente único**. Consumidores hoje: **biz=1** (Wagner, homologação) + **biz=164** (Martinho/OficinaAuto — cutover fiscal por-business habilitado, #2147). Pareado com `Modules/NfeBrasil` (cert A1 + schema `nfe_certificados` unificados). A tabela `nfse_emissoes` é compartilhada com o NfeBrasil, e a decisão [W] de 2026-10-13 de consolidar a NFS-e lá (D-NFSE-CONSOLIDA) ainda não tem ADR — ver [SCOPE.md](SCOPE.md).

## 2. Estado consolidado

Este BRIEFING não guarda porcentagem. A tabela de % que estava aqui era de 2026-05-16 e não tinha fonte que a recalculasse (removida em 2026-11-16). O estado se lê nas portas vivas:

- âncora e cobertura das US: `node scripts/governance/anchor-lint.mjs memory/requisitos/NFSe/SPEC.md`
- casos e testes por tela: `npm run casos:report`
- produção: nenhuma NFS-e real emitida até 2026-11-16 (US-NFSE-013 aberta; o certificado A1 de homologação ainda não foi carregado)

### Score module-grade

> ⚠️ **Números abaixo são HISTÓRICO datado (2026-05-16), não o estado atual.** Não há mais fonte viva: a rubrica `module-grade` foi APOSENTADA em 2026-09-15 ([ADR 0399](../../decisions/0399-aposentar-rubrica-module-grade-gate-e-baseline.md)) — **não existe mais nota de módulo, nem comando que a calcule**, e o baseline `governance/module-grades-baseline.json` foi deletado. O número fica como história datada; para o estado atual do módulo, leia este BRIEFING e `node scripts/governance/requisitos-status.mjs NFSe`.

| Versão (datada) | Score | Observação |
|---|---|---|
| v2 (2026-05-16) | ~56/100 | Penalizava D5 (cliente externo) — então biz=1 interno único |
| v3 esperado (2026-05-16) | ~65-75/100 | `na_justified` D5 → rubrica v3 redistribui peso (ADR 0156) |

**`na_justified` D5 declarado no SPEC:** módulo fiscal foundation-horizontal; sem cliente externo pagante isolado enquanto biz=1 (homologação) e biz=164 (cutover Martinho) são os consumidores. **NUNCA biz=4 ROTA LIVRE** (vestuário CNAE 4781-4/00 só emite NFCe).

## 3. Capacidades hoje

- **Provider**: SN-NFSe federal direto (LC 214/2025) — sandbox + prod endpoints configurados
- **Tela emissão**: `Nfse/Emitir.tsx` — form completo com pré-fill via `transaction_id` (vinculação venda → NFSe)
- **Tela listagem**: `Nfse/Index.tsx` — filtros status/competência/tomador + paginate 25
- **Tela detalhe**: `Nfse/Show.tsx` — status, cancelamento com motivo e reemissão de nota em `erro`. O botão "Baixar DANFSE" só aparece com `pdf_url`, e **hoje nenhuma nota nova tem DANFSe**: o `SnNfseAdapter` grava `pdfUrl: null` em toda emissão (medido em `origin/main` 2026-11-16; ver §5)
- **Service `NfseEmissaoService`**: idempotência via `idempotency_key` (sha256 de empresa + tomador + valor + descrição + competência), log estruturado canal `nfse`. Falha de conexão tem duas camadas de retentativa: 3 tentativas no serviço com espera de 1s, 2s e 4s, e depois o `EmitirNfseJob` (3 tentativas, 60s entre elas), sempre com o mesmo nº de DPS. _Errata 2026-11-17: em 2026-11-16 esta linha dizia que o retry era só do job, com 60s fixo. Estava errado: o serviço já esperava 1s/2s/4s (`sleep(2 ** …)` no `NfseEmissaoService`, commit `e2b0fa0451`); só o job tinha sido lido._
- **Ambiente POR-BUSINESS (cutover fiscal)**: emissão resolve `homologacao`/`producao` de `nfse_provider_configs.ambiente` do tenant (`montarPayload` L76-80 + `SnNfseAdapter::resolveBaseUrl`), NÃO do bind global — ligar produção pra biz=164 não afeta os demais (#2147, teste `AmbientePorBusinessTest`)
- **Job assíncrono**: `EmitirNfseJob` na fila `nfse`. Desde o lote 2.3 (#11032) o job vai **sem** o certificado; o serviço lê o certificado da empresa na hora de enviar. _Até 2026-11-16 o payload do job levava o PFX em base64 e a senha decifrada._ **Em produção nenhum `queue:work` agendado lê a fila `nfse`** (medido 2026-11-17, ver §5)
- **Emissão a partir da venda**: `POST /nfse/transactions/{tx}/emitir` (`NfseController@emitirParaTransaction`, botão da tela de venda); venda separada emite só a parte serviço (US-NFE-069)
- **Cert A1**: storage encriptado, delegado ao `CertificadoService` do NfeBrasil (schema unificado migration 2026_05_07_210000). Import via `php artisan nfse:importar-cert --pfx=<arquivo.pfx> --senha=<senha> --business=<id>` (`ImportarCertificadoCommand`)
- **Health check**: `php artisan nfse:health` — 6 sinais (tabelas, providers ativos, **cert vencendo ≤30d**, rejeitadas 24h) — `NfseHealthCommand`, Wave 23 D6
- **Cancelamento**: motivo obrigatório, bloqueia dupla cancelação (`NfseJaCanceladaException`), span OTel `nfse.cancelar` (Wave 28, #2678), log canal nfse
- **Retenção federal PIS/COFINS/CSLL** (2026-10-13, R-NFSE-003): marcação "serviço sujeito à retenção (art. 30)" na tela Emitir, desligada por padrão; o cálculo é do `RetencoesNfseService` do NfeBrasil (regime do tenant), grava em `nfse_emissoes.valor_ret_*` e leva o grupo `tribFed` à DPS (NT 007). O certificado da config é lido pelo `CertificadoService::carregarCertificado` (R-NFSE-004, 2026-10-13); antes o `montarPayload` chamava métodos que não existiam desde 2026-05-07.
- **DPS em XML assinado** (2026-10-13, R-NFSE-005): o `SnNfseAdapter` gera a DPS no leiaute dos XSDs oficiais v1.01, assina o `infDPS` com o A1 e envia gzip+base64 em `POST /SefinNacional/nfse` com mTLS — validado contra os XSDs no teste. Até aqui mandava JSON, que a Sefin não reconhece. Nenhuma DPS foi enviada de verdade ainda: falta o certificado A1 em homologação.
- **Código de tributação nacional** (2026-10-13, R-NFSE-006): `cTribNac` vem da lista de serviços nacional do Anexo B oficial (338 códigos, versionada e regerável por `scripts/fiscal/lista-servico-nacional.py`); nos 62 subitens com mais de um desdobro a tela Emitir pede a escolha. Antes ia "01" fixo, errado nesses 62.
- **Observabilidade**: spans OTel `nfse.emissao` + `nfse.cancelar` com atributo `businessId` (correlação prod cross-tenant)

## 4. Diferenciais únicos (não-replicáveis BSPs)

1. **SN-NFSe federal direto sem provider terceiro** — custo zero per-emissão (concorrentes pagam Focus NFe / NFE.io / PlugNotas R$ [redacted Tier 0]-1,00/emissão)
2. **Vinculação venda → NFSe nativa** — `transaction_id` no payload + pre-fill form (Bling/Tiny obrigam re-digitação)
3. **Idempotência por `idempotency_key`** — duplo-submit não cria dupla nota
4. **Multi-tenant Tier 0** — cada `business_id` com config + cert isolados (ADR 0093)
5. **Retentativa sem nota em dobro** — falha de conexão retenta no serviço (1s, 2s, 4s) e depois no job (3×, 60s), sempre com o mesmo nº de DPS (lote 2.2, #11025). _Em 2026-11-16 este item dizia que o backoff 1s/2s/4s não existia; existia (errata em §3)._

## 5. Gaps remanescentes (próxima onda)

| # | PR alvo | Esforço IA-pair | Nota |
|---|---|---|---|
| 0a | DANFSe (US-NFSE-016) — o sistema emissor precisa gerar o PDF desde a NT 008/2026; hoje `pdf_url` fica nulo e o `/nfse/{id}/pdf` dá 404 | — | **bloqueia a 1ª emissão real** |
| 0b | Rascunho da recorrência sem saída (US-NFSE-007) — o observer cria `rascunho`, mas nada o emite | — | aberto |
| 0c | `consultar()` usa o ambiente global (US-NFSE-015). O cancelamento já usa o ambiente e o certificado da empresa da nota desde o lote 0 (#10987) | — | parcial |
| 0e | Fila `nfse` sem consumidor em produção: nenhum `queue:work` agendado a lê (`schedule:list` em 2026-11-17). A primeira emissão ficaria parada na tabela `jobs` | — | **bloqueia a 1ª emissão real** |
| 0f | Certificado da empresa 1 vencido em produção (`nfse:health` FAIL em 2026-11-17) | — | **bloqueia a 1ª emissão real** |
| 0d | Emissão real em produção-restrita (US-NFSE-017, aceite 3) — a DPS já sai em XML v1.01 validado contra o XSD | — | aberto |
| 1 | Pest emissão end-to-end com hit DB real (Waves 25/27/28 são reflection+source-grep, ZERO hit DB — CHANGELOG). Desde 2026-11-16 os testes do módulo rodam na lane `nfse-pest` (MySQL, #10964), com testes de comportamento com banco (lote 1) | 4h | **parcial** |
| 2 | Bulk emissão (recurring invoices mensais) | 6h | aberto |
| 3 | Dashboard métricas NFSe (volume/erro/ISS pago) | 4h | **parcial** — `nfse:health` já dá 6 sinais CLI; falta tela |
| 4 | UI configuração cert A1 + provider | 8h | **parcial** — `nfse:importar-cert` substituiu Tinker; falta UI web |
| 5 | Webhook callback SEFIN async (em vez de polling) | 6h | aberto |
| 6 | Non-Goals/Anti-hooks dos 3 charters (status `draft`, pendem aprovação [W] pra virar `live`) | — | #4113 criou os charters; falta [W] |

## 6. Bloqueadores manuais Wagner

- **Certificado A1 (.pfx)** válido oimpresso — assinar com contador
- **CNPJ + IE + IM** registrados prefeitura Tubarão (Wagner confirma)
- **Regime tributário** (Simples Nacional / Real / Presumido) — Wagner + contador
- Decisão custo: usar SN-NFSe direto vs Focus NFe / NFE.io / PlugNotas (US-NFSE-001 ✅ decidiu SN-NFSe)

## 7. ROI defendido vs concorrentes

| Concorrente | Como ganhamos | Como perdemos |
|---|---|---|
| Focus NFe / NFE.io / PlugNotas | Zero custo per-emissão (SN-NFSe direto) | Setup mais complexo (cert A1 self-managed) |
| Bling/Tiny/Omie NFSe | ERP nativo + vinculação venda automática | UX polida, suporte 24/7 |
| eNotas | Multi-tenant Tier 0 isolation real | Provider já integrado com prefs municipais antigas (legacy ABRASF) |

## 8. Risks ativos

- 🟡 **Cert A1 expira** — `nfse:health` já sinaliza `cert_vencimento_alarme` ≤30d (era 🔴 no BRIEFING de 05-16); falta cron que dispare o alerta proativamente (D-30/D-7/D-1)
- 🟡 **SN-NFSe LC 214/2025 ainda em adoção** — algumas prefeituras retardatárias podem voltar a exigir ABRASF municipal (cobertura SEFIN evolui em 2026)
- 🟡 **Idempotência só cobre store()** — race em update concorrente status precisa lock pessimista futuro
- 🟢 **Sem cliente real em prod ainda** — risco financeiro contido a homologação

## 9. Métricas-chave (last 7d)

- Volume: 0 (módulo ainda em homologação)
- Custo: R$ [redacted Tier 0]/dia (SN-NFSe federal gratuito)
- Erros emissão: N/A
- Tempo médio emissão: ~2-4s SEFIN (esperado)

## 10. Cliente piloto / canary

- **biz=1** (Wagner/oimpresso, Tubarão-SC) — homologação SEFIN
- **biz=164** (Martinho / OficinaAuto) — **cutover fiscal por-business habilitado no código** (#2147, 2026-06-03). O ambiente do tenant é lido de `nfse_provider_configs.ambiente`; se/qual business está de fato flipado pra `producao` em prod é fato de runtime (CT 100/prod DB) — não afirmar "LIVE emitindo" sem medir
- **Medido em produção, 2026-11-17:** só a empresa 1 tem config de NFS-e (homologação, certificado vencido); a 164 não tem config; `nfse_emissoes` vazia
- **Próximo canary:** ComunicacaoVisual (Vargas/Extreme/Gold/Zoom/Fixar/Mhundo) quando `Modules/ComunicacaoVisual` for a produção
- **NUNCA biz=4 ROTA LIVRE** — Larissa é vestuário CNAE 4781-4/00, não emite NFSe (só NFCe)

## 11. ADRs centrais do módulo

- [ADR ARQ-0001](adr/arq/0001-cliente-oimpresso-modulo-standalone.md) — cliente oimpresso (NÃO ROTA LIVRE)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) — doutrina de teste (tenant 98; superseded a antiga 0101-tests, que este BRIEFING citava)
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — modular especializado por vertical (pareado NfeBrasil)
- [ADR 0155](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md) e [ADR 0156](../../decisions/0156-module-grade-v3-errata-otel-helper-na-justified.md) — rubrica module-grade v3, **aposentada** em 2026-09-15 ([ADR 0399](../../decisions/0399-aposentar-rubrica-module-grade-gate-e-baseline.md)); só história

## 12. Sessões e handoffs relevantes (últimos 30d)

- Sprint A concluída 2026-05-01 — US-001/002/003 ✅ scaffold + migrations + pesquisa Tubarão
- 2026-10-13 — R-NFSE-003…006 (retenção federal, certificado pelo `CertificadoService`, DPS em XML assinado, cTribNac do Anexo B)
- 2026-11 — auditoria do módulo; lotes de correção em andamento (código e documentação)
- 2026-11-17 — [SDD do fluxo de emissão](SDD-emissao-nfse-v1.0.md) e [runbook de operação](RUNBOOK-operacao.md) (lote 5.3)
- _Em 2026-05 esta seção dizia "Sprint B em curso — US-004 parcial"; já não vale._

---

## 13. Último update

**Atualizado:** 2026-11-17 BRT — auditoria NFSe, lote 5.3: errata do backoff (§3/§4), job sem certificado (§3), fila `nfse` sem consumidor e certificado vencido em §5, estado de produção medido em §10, links para o SDD e o runbook de operação (§12). _(Antes: 2026-11-16 BRT — lote 5.4: datas internas alinhadas, tabela de % sem fonte removida (§2), DANFSe e backoff "corrigidos pelo código" (§3/§4) — o do backoff estava errado, ver a errata de 2026-11-17 —, gaps P0/P1 da auditoria em §5, links de ADR apontados para os donos atuais; 2026-10-13 BRT — retenção federal na emissão (R-NFSE-003) e achado do certificado (§3); 2026-08-01 BRT — catch-up 76d (#5153): cutover fiscal por-business biz=164 Martinho, nota de module-grade vira ponteiro; e 2026-05-16, Wave 18 saturation — D1 NfseCertificado cross-tenant Pest.)_

> **Carimbo único — não duplicar.** Este rodapé é a data que o `briefing-code-staleness` lê
> (junto de `updated_at`/`distilled_at`/`reviewed_at`, pegando a MAIOR). O frontmatter
> `last_review:` **não é lido por ele**. Se as duas discordarem, o detector acredita nesta —
> foi o que produziu um alarme falso de **88 dias** entre 08-01 e 08-16, com o módulo já
> revisado. Ao revisar, mova as duas juntas ou mantenha só uma.
**Próximo update esperado:** quando o DANFSe (US-NFSE-016) existir ou o certificado A1 for carregado
**Mantenedor:** Claude (auto) + Eliana (owner) + Wagner (review)

### Wave 18 deltas (2026-05-16)
- D1: novo Pest `NfseCertificadoMultiTenantIsolationTest.php` (3 testes — scope herdado de NfeCertificado, isExpirado() alias, contrato business_id NOT NULL)
- CHANGELOG.md criado pelo módulo
- Cobertura cross-tenant explícita pra credenciais fiscais A1/A3 (CNPJ titular + encrypted_password)
