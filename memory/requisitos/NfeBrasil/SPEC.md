---
module: NfeBrasil
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
na_justified:
  D7.a: "Fiscal compliance CONFAZ SINIEF 07/2005 — CPF/CNPJ em logs SEFAZ obrigatórios. PiiRedactor NÃO aplica em dados fiscais (XML original preservado). Detalhe em PII-LGPD-FISCAL.md."
---

# Especificação funcional — NfeBrasil

> Convenção do ID: `US-NFE-NNN` para user stories, `R-NFE-NNN` para regras Gherkin.
> Campo `Implementado em` linka com a página React (`resources/js/Pages/...`).

## 1. Glossário rápido

- **NFe** — Nota Fiscal Eletrônica modelo 55 (B2B)
- **NFC-e** — Nota Fiscal de Consumidor Eletrônica modelo 65 (B2C ponto-de-venda)
- **CSOSN** — Código Situação da Operação Simples Nacional
- **CST** — Código Situação Tributária (regimes não-Simples)
- **CSC** — Código de Segurança do Contribuinte (NFC-e)
- **DANFE** — Documento Auxiliar da NFe (PDF imprimível)
- **cStat** — código de status SEFAZ (100 = autorizada, 217 = NFe não consta na base, etc.)

(Vocabulário completo: [GLOSSARY.md](GLOSSARY.md))

## 2. User stories

### US-NFE-001 · Configurar certificado digital A1

> **Área:** Configuracao
> **Rota:** `POST /nfe-brasil/configuracao/certificado`
> **Controller/ação:** `CertificadoController@upload`
> **Permissão Spatie:** `nfe.configuracao.manage`

**Como** Gestor RH/Admin
**Quero** subir certificado A1 (.pfx) com senha e o sistema validar + armazenar criptografado
**Para** começar a emitir NFe sem expor cert na rede

**Implementado em:** _parcial_ · [`Modules/NfeBrasil/Http/Controllers/CertificadoController.php`](../../../Modules/NfeBrasil/Http/Controllers/CertificadoController.php) · [`Modules/NfeBrasil/Services/CertificadoService.php`](../../../Modules/NfeBrasil/Services/CertificadoService.php) · verificado@08c4a8f (2026-06-21) — backend (upload + validação OpenSSL + cripto) pronto; falta a tela React de upload em Pages/NfeBrasil/Configuracao (não migrada)

**Testado em:** `Modules/NfeBrasil/Tests/Feature/CertificadoServiceTest.php` (covers US-NFE-001) · `Modules/NfeBrasil/Tests/Feature/CertificadoControllerTest.php` (covers US-NFE-001)

**Definition of Done:**
- [x] FormRequest aceita `.pfx` ≤ 100KB + `senha` (não loga em audit log!)
- [x] `CertificadoService::validar()` lê o pfx via OpenSSL, extrai CN (CNPJ), valida `not_after > now()`
- [x] Storage criptografado: `storage/app/nfe-brasil/{business_id}/cert/{uuid}.pfx.enc` (encrypt at rest com chave do business)
- [x] Senha nunca persiste em texto: armazenada via `encrypt()` Laravel
- [x] UI Inertia com 3 estados (sem cert / OK / próximo vencimento ≤30d / vencido) — `Pages/NfeBrasil/Configuracao/Certificado.tsx`
- [x] Sidebar entry "Certificado A1" + permissão `nfe.configuracao.manage` (DataController)
- [x] Test Feature: upload válido + cert expirado rejeitado + CNPJ ≠ business CNPJ rejeitado + isolamento (`CertificadoServiceTest.php` 13 tests + `CertificadoControllerTest.php` 3 tests)
- [ ] Badge no sidebar global quando cert ≤30d (placeholder — depende de carregar status do cert no shared props do Inertia)

### US-NFE-002 · Emitir NFC-e a partir de venda finalizada

> **Área:** Emissao
> **Rota:** `POST /nfe-brasil/nfce/emitir`
> **Controller/ação:** `NfceController@emitir`
> **Permissão Spatie:** `nfe.nfce.emitir`

**Como** Larissa-caixa (operador POS)
**Quero** clicar "Finalizar venda" no POS e o sistema emitir NFC-e em background, retornar DANFE imprimível em até 5s
**Para** atender exigência fiscal sem fricção no balcão

**Implementado em:** _pendente_ — emissão NFC-e a partir de venda (job + listener) não construída; falta integração `/sells/create` + tela de sucesso em Pages/NfeBrasil/Emissao

**Definition of Done:**
- [ ] Listener escuta `App\Events\TransactionCompleted` (core) → dispatch job `EmitirNfceJob` na queue `nfe`
- [ ] Job monta XML via `eduardokum/sped-nfe` builder
- [ ] Tributação calculada por `MotorTributarioService` baseada em produto.NCM + business.regime
- [ ] Idempotência: `(business_id, transaction_id)` UNIQUE em `nfe_emissoes` (re-emitir mesma venda = no-op)
- [ ] Status retornado via broadcast `business.{id}.nfe-status` (frontend escuta com Echo)
- [ ] PDF gerado via `eduardokum/sped-da` salvo em `storage/app/nfe-brasil/{business_id}/danfe/{chave}.pdf`
- [ ] Test Feature: emissão happy path + idempotência + rejeição cStat 217 + isolamento

**Pré-voo da NFC-e automática — medido em produção em 2026-10-26 (só leitura), [W] 2026-10-26:**
nenhuma empresa passou nos quatro itens (certificado A1 válido · CSC e série · ambiente de produção · não emitir por
outro sistema), e nenhuma foi ligada.
- **biz=1 (WR2):** estava ligada (`auto_emission_enabled`), mas o certificado A1 tinha vencido em 2026-08-06 e a
  SEFAZ-SC recusava as NFC-e com 781 ("emissor não habilitado") — faltava o credenciamento NFC-e. Nenhuma venda nos
  30 dias anteriores.
- **biz=4 (ROTA LIVRE):** emitia pelo Delphi (WR Comercial/OfficeImpresso), o sistema que está sendo migrado para o
  oimpresso — [W] 2026-10-26: *"emite pelo sistema Delphi meu o que esta sendo migrado"*. Ligar aqui duplicaria a nota.
- **biz=164 (Martinho):** emitia NF-e pelo Delphi (as notas no oimpresso eram importadas, `legacy_source`).
Ligar a NFC-e nessas empresas é passo de cutover da migração (US-NFE-074), não um toggle.

### US-NFE-003 · Visualizar e reimprimir NFCe/NFe emitida

> **Área:** Emissao
> **Rota:** `GET /nfe-brasil/emissoes/{chave}`
> **Controller/ação:** `EmissaoController@show`
> **Permissão Spatie:** `nfe.emissoes.view`

**Como** Larissa-caixa / Gestor
**Quero** abrir nota emitida pela chave/número e baixar DANFE + XML
**Para** reimprimir, enviar pra cliente, anexar em e-mail

**Implementado em:** _pendente_ — tela de visualização/reimpressão (Pages/NfeBrasil/Emissoes/Show) não construída

**Definition of Done:**
- [ ] Mostra: chave acesso (44 dígitos formatado em 11 grupos), número, série, data emissão, valor, cStat, link DANFE PDF, link XML
- [ ] Histórico de eventos (autorização → cancelamento? → CCe?)
- [ ] Re-download é log auditado (Spatie activity_log)
- [ ] Test Feature: read-only, com dados; not found 404; isolamento

### US-NFE-004 · Cancelar NFe/NFC-e dentro do prazo legal

> **Área:** Cancelamento
> **Rota:** `POST /nfe-brasil/emissoes/{chave}/cancelar`
> **Controller/ação:** `CancelamentoController@cancelar`
> **Permissão Spatie:** `nfe.cancelamento.manage`

**Como** Gestor / Larissa-caixa autorizada
**Quero** cancelar uma NFC-e em até 24h ou NF-e em até 168h informando justificativa (15-255 chars)
**Para** corrigir venda errada sem deixar rastro errado pra Receita

**Implementado em:** _pendente_ — cancelamento (serviço SEFAZ + botão na tela Emissoes/Show) não construído

**Definition of Done:**
- [ ] Valida prazo legal antes de chamar SEFAZ (NFC-e: 24h; NF-e: 168h)
- [ ] FormRequest valida `justificativa` length 15-255
- [ ] `CancelamentoService::cancelar()` envia evento SEFAZ + persiste em `nfe_eventos` (tipo=110111)
- [ ] Atualiza `nfe_emissoes.status` para `cancelada` apenas se cStat=135 (cancelamento autorizado)
- [ ] Estorno automático no Financeiro: emite evento `NfeCancelada` que reverte `transaction_payment` e fecha título com motivo
- [ ] Test Feature: cancelamento dentro do prazo + após prazo (422) + dupla cancelamento (idempotente) + isolamento

### US-NFE-005 · Carta de Correção Eletrônica (CCe)

> **Área:** Correcao
> **Rota:** `POST /nfe-brasil/emissoes/{chave}/carta-correcao`
> **Controller/ação:** `CartaCorrecaoController@enviar`
> **Permissão Spatie:** `nfe.correcao.manage`

**Como** Gestor
**Quero** enviar CCe pra corrigir erro não-monetário (ex: nome do destinatário errado, transportadora) sem cancelar e re-emitir
**Para** evitar custo de re-emissão e manter histórico

**Implementado em:** _pendente_ — Carta de Correção Eletrônica (serviço + botão CCe na tela Emissoes/Show) não construída

**Definition of Done:**
- [ ] Valida limite legal: máximo 20 CCe por NFe; correção 15-1000 chars; não permite mudar valor/quantidade/CNPJ
- [ ] Sequência crescente persistida em `nfe_eventos`
- [ ] Email automático com XML CCe pra destinatário (se config habilitada + e-mail conhecido)
- [ ] Test Feature: CCe válida + tentar mudar valor (422) + 21ª CCe rejeitada + isolamento

### US-NFE-006 · Modo contingência (EPEC ou FS-DA)

> **Área:** Contingencia
> **Rota:** `POST /nfe-brasil/contingencia/ativar`
> **Controller/ação:** `ContingenciaController@ativar`
> **Permissão Spatie:** `nfe.contingencia.manage`

**Como** Gestor (quando SEFAZ está fora)
**Quero** ativar contingência (EPEC ou FS-DA pra NF-e, off-line pra NFC-e) e seguir vendendo offline
**Para** não parar caixa quando SEFAZ está com problema

**Implementado em:** _pendente_ — modo contingência (EPEC/FS-DA + tela Pages/NfeBrasil/Contingencia) não construído

**Definition of Done:**
- [ ] Detecção automática: `SefazHealthCheck` ping a cada 30s → se 3 falhas seguidas, sugerir contingência
- [ ] Ativação manual em 1 clique com motivo
- [ ] Notas em contingência ganham `tpEmis=4 (EPEC)`, `tpEmis=5 (FS-DA — NF-e mod. 55)` ou `tpEmis=9 (off-line NFC-e — mod. 65)`
- [ ] Job retentativa quando SEFAZ volta: re-envia em ordem de emissão
- [ ] Tela mostra fila pendente + status saúde SEFAZ
- [ ] Test Feature: ativar/desativar + emitir em contingência + retentativa após volta + isolamento

### US-NFE-007 · Monitor de rejeições + status SEFAZ

> **Área:** Monitor
> **Rota:** `GET /nfe-brasil/monitor`
> **Controller/ação:** `MonitorController@index`
> **Permissão Spatie:** `nfe.monitor.view`

**Como** Gestor
**Quero** dashboard com KPIs (autorizadas hoje, rejeitadas, contingência ativa, cert vencendo) + lista de rejeições com cStat + sugestão de correção
**Para** atacar rejeições antes de o cliente perceber

**Implementado em:** _pendente_ — monitor de rejeições + status SEFAZ (tela Pages/NfeBrasil/Monitor) não construído

**Definition of Done:**
- [ ] KPIs: autorizadas (hoje/semana/mês), rejeitadas, em contingência, cert dias restantes
- [ ] Tabela rejeições: cStat, motivo, sugestão de correção (lookup `cstat_correcoes` table)
- [ ] CTA "Reemitir corrigido" gera novo número (mantém audit do erro original)
- [ ] Status SEFAZ por UF (verde/amarelo/vermelho) — consulta `WS-Status-Servico` periódica
- [ ] Cache 1 min nas KPIs, invalidado em `NfeAutorizada`/`NfeRejeitada`
- [ ] Test Feature: KPIs + filtros + acesso por permissão + isolamento

### US-NFE-008 · Manifestar NF-e recebida (destinatário)

> **Área:** Manifestacao
> **Rota:** `POST /nfe-brasil/manifestacao/{chave}`
> **Controller/ação:** `ManifestacaoController@manifestar`
> **Permissão Spatie:** `nfe.manifestacao.manage`

**Como** Gestor
**Quero** manifestar NFe recebida (Confirmação / Ciência / Desconhecimento / Operação não realizada)
**Para** atender obrigação legal e gerar apropriação correta no DRE

**Implementado em:** `Modules/NfeBrasil/Http/Controllers/ManifestacaoController.php` · `Modules/NfeBrasil/Services/Manifestacao/ManifestacaoService.php` · `resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx` · `Modules/NfeBrasil/Tests/Feature/ManifestacaoControllerTest.php` · verificado@08c4a8f (2026-06-21)

**Testado em:** `Modules/NfeBrasil/Tests/Feature/ManifestacaoServiceTest.php` (covers US-NFE-008) · `Modules/NfeBrasil/Tests/Feature/ManifestacaoControllerTest.php` (covers US-NFE-008)

**Definition of Done:**
- [ ] Lista NFes destinadas (consulta `WS-NFeDistribuicaoDFe` periódica) com status manifestação
- [ ] 4 ações em modal: Confirmação / Ciência / Desconhecimento / Operação não realizada
- [ ] XML manifestação persistido em `nfe_eventos`
- [ ] Test Feature: cada ação + idempotência + isolamento

### US-NFE-009 · Gerar SPED Fiscal/EFD ICMS-IPI mensal

> **Área:** Sped
> **Rota:** `GET /nfe-brasil/sped/efd-icms-ipi?mes=YYYY-MM`
> **Controller/ação:** `SpedController@efdIcmsIpi`
> **Permissão Spatie:** `nfe.sped.gerar`

**Como** Contador (terceiro com role limitada)
**Quero** baixar arquivo SPED Fiscal pronto pra ECF do mês de competência
**Para** entregar obrigação contábil sem ligar pra Larissa

**Implementado em:** _pendente_ — geração SPED Fiscal/EFD (tela Pages/NfeBrasil/Sped) não construída

**Definition of Done:**
- [ ] Geração assíncrona via job (volume grande); progresso via broadcast
- [ ] Blocos C100/C170 (notas próprias) + C500 (notas terceiros, se manifestação habilitada)
- [ ] Validação contra layout SPED Fiscal vigente (PVA atualizado)
- [ ] Token compartilhável read-only `/sped/share/{token}` válido 14 dias (contador clica e baixa)
- [ ] Test Feature: geração mês com 100 notas + bloco C100 bate com fixtures + isolamento

### US-NFE-010 · Cadastrar regra tributária por NCM (motor)

> **Área:** Tributacao
> **Rota:** `POST /nfe-brasil/tributacao/regras`
> **Controller/ação:** `TributacaoController@store`
> **Permissão Spatie:** `nfe.tributacao.manage`
> **ADRs relacionados:** [ARQ-0004](adr/arq/0004-schema-flexivel-cbs-ibs-reforma-tributaria.md) (schema CBS/IBS), [ARQ-0005](adr/arq/0005-tax-rates-core-vs-fiscal-rules.md) (bridge), [ARQ-0006](adr/arq/0006-cascade-defaults-ncm-produto.md) (cascade), [UI-0003](adr/ui/0003-configuracao-fiscal-3-niveis.md) (3 níveis)

**Como** Gestor / Contador
**Quero** definir tributação por NCM/UF: ICMS, ICMS-ST (com MVA), IPI, PIS, COFINS, CBS, IBS
**Para** automatizar cálculo na emissão sem digitar imposto a imposto

**Implementado em:** [`Modules/NfeBrasil/Services/MotorTributarioService.php`](../../../Modules/NfeBrasil/Services/MotorTributarioService.php) (motor cascade) · [`Modules/NfeBrasil/Models/NfeFiscalRule.php`](../../../Modules/NfeBrasil/Models/NfeFiscalRule.php) · [`Modules/NfeBrasil/Models/NfeBusinessConfig.php`](../../../Modules/NfeBrasil/Models/NfeBusinessConfig.php) · [`Modules/NfeBrasil/Http/Controllers/TributacaoController.php`](../../../Modules/NfeBrasil/Http/Controllers/TributacaoController.php) · [`Modules/NfeBrasil/Http/Controllers/ConfigDefaultController.php`](../../../Modules/NfeBrasil/Http/Controllers/ConfigDefaultController.php) · [`resources/js/Pages/NfeBrasil/Tributacao/Index.tsx`](../../../resources/js/Pages/NfeBrasil/Tributacao/Index.tsx) · [`.../RegraForm.tsx`](../../../resources/js/Pages/NfeBrasil/Tributacao/RegraForm.tsx) · [`.../ConfigDefault.tsx`](../../../resources/js/Pages/NfeBrasil/Tributacao/ConfigDefault.tsx)

**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (covers US-NFE-010) · `Modules/NfeBrasil/Tests/Feature/TributacaoControllerTest.php` (covers US-NFE-010)

**Definition of Done:**
- [x] Tabela `nfe_fiscal_rules` com index `(business_id, ncm, uf_origem, uf_destino)` (ARQ-0004 schema; idempotência via service `firstOrCreate`)
- [x] FormRequest valida: `ncm` 8 dígitos, `uf_origem` em FEBRABAN UFs, `uf_destino` opcional (NULL = todas), CSOSN OU CST exclusive (`UpsertRegraTributariaRequest` + `UpsertConfigDefaultRequest`)
- [x] Schema flexível: coluna `metadata` JSON pra CBS/IBS futuros (ARQ-0004)
- [x] **Cascade fallback respeitado** (ARQ-0006): `MotorTributarioService::calcular` itera Nível 1→4 com cache em memória; testes Pest cobrem 10 cenários (níveis 1-4 + edge cases + multi-tenant)
- [x] **UI fase 2 (CRUD básico)** — Index lista regras + config default; RegraForm create/edit; ConfigDefault Nível 4 com regime + alíquotas; permissão `nfe.tributacao.manage`; sidebar entry "Tributação"; tests Pest controller (7 cenários)
- [ ] **Bridge automática** (ARQ-0005): listener `SyncFiscalRuleToTaxRate` upsert linha em `tax_rates` core (compat Connector)
- [ ] **Importação CSV** em massa: upload datasets Receita Federal (NCM 8d) ou CONFAZ (CEST 7d):
  - Preview antes de aplicar (10 primeiras linhas + totais)
  - Detecta duplicados existentes (skip ou update via `--update-existing` flag)
  - Validação por linha (erros não bloqueiam linhas válidas)
  - Resultado: "150 criadas, 12 atualizadas, 3 falharam (ver log)"
- [ ] **Buscador NCM** com autocomplete (dataset core 15k entries; lookup por digit ou nome)
- [ ] **Preview de cálculo** com produto exemplo: usuário digita valor R$ [redacted Tier 0] → mostra ICMS=18, ICMS-ST=12, IPI=5, PIS=0,65, COFINS=3 (carga efetiva calculada antes de salvar)
- [ ] **Tabela com filtros** (UI-0003 Aba 2):
  - Sort por "Uso" (count emissões 30d) DESC default
  - Filtro "Sem uso 90d" (candidatos a limpeza)
  - Filtro "Suspeitas" (heurísticas: ICMS=0 fora Simples, ICMS-ST sem MVA, etc.)
- [ ] **Override por produto** opt-in via `products.fiscal_rule_override_id` (ARQ-0006 Nível 1)
- [ ] **Evento publicado** `FiscalRuleCreated`/`Updated`/`Deleted` (consumido pelo bridge ARQ-0005)
- [ ] **Audit log** Spatie em todas mutações (R-NFE-013)
- [ ] **Multi-tenant scope** `business_id` em todas queries (R-NFE-001)
- [ ] **Permissão Spatie** `nfe.tributacao.manage` (R-NFE-002)
- [ ] **Test Feature** cobre:
  - Criar regra simples + duplicidade rejeitada (UNIQUE constraint)
  - Importação CSV happy path + linhas inválidas (parsial sucesso)
  - Cascade fallback Nível 2 → 3 → 4 com fixtures
  - Bridge cria/atualiza/deleta `tax_rates` espelho
  - Preview de cálculo retorna shape correto
  - Multi-tenant isolation (business A não vê regras de B)

## 3. Regras de negócio (Gherkin)

### R-NFE-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo NfeBrasil
Então só vê emissões/regras/certificados com `business_id = A`
```

**Implementação:** Trait `BusinessScope` em todo Model.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest.php`.

### R-NFE-002 · Permissões Spatie por área

```gherkin
Dado um usuário sem `nfe.{area}.{action}`
Quando ele acessa rota correspondente
Então recebe 403
```

**Implementação:** `Route::middleware('can:...')` no group. 14 permissões registradas no boot.
**Testado em:** _lacuna — SpatiePermissionsTest não existe em NfeBrasil (só `Modules/Ponto/Tests/Feature/SpatiePermissionsTest.php`, que é de Ponto, não cobre permissões NFe)_.

### R-NFE-003 · Numeração sequencial garantida (sem gap)

```gherkin
Dado o business tem 100 NFe emitidas (números 1-100)
Quando 5 emissões concorrentes disparam
Então cada uma recebe número distinto entre 101 e 105
E não há gap nem dupla numeração
```

**Implementação:** `NumberSequenceService::next()` com `lockForUpdate` em transação. Para NFC-e, sequencial por `(business_id, serie)`.
**Testado em:** _lacuna — NumberSequenceConcorrenciaTest (50 jobs concorrentes / no-gap) não existe. A unicidade da numeração por tenant é coberta por `Modules/NfeBrasil/Tests/Feature/NfeDomainModelsTest.php` (UNIQUE biz, modelo, serie, numero) e `Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest.php` (numeração isolada por business), mas o cenário de concorrência/lockForUpdate em si não tem teste_.

### R-NFE-004 · Cert digital expirado bloqueia emissão

```gherkin
Dado o certificado A1 do business expirou (not_after < hoje)
Quando uma venda dispara emissão
Então a emissão é bloqueada com erro `cert_expirado`
E aparece banner em todas as telas administrativas pra renovar
```

**Implementação:** `CertificadoGuard::ensureValid()` rodada antes de qualquer call SEFAZ.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/CertificadoServiceTest.php` (cenário `rejeita cert expirado` → throw com mensagem `expirado`; complementado por `NfeCertificado::isVencido()` em `Modules/NfeBrasil/Tests/Feature/NfeDomainModelsTest.php`).

### R-NFE-005 · Idempotência de emissão

```gherkin
Dado uma venda com transaction_id = 1234 e NFC-e já emitida
Quando o job EmitirNfceJob roda 2x (retry queue)
Então a 2ª execução retorna a emissão existente (sem chamar SEFAZ de novo)
```

**Implementação:** `nfe_emissoes` tem `UNIQUE (business_id, transaction_id, modelo)`. Service faz `firstOrCreate`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeServiceIdempotenciaRetryTest.php` (idempotência SEFAZ + retry safety: status autorizada/pendente → no-op; complementado por `Modules/NfeBrasil/Tests/Feature/NfeDomainModelsTest.php`, cenário `UNIQUE(business_id, transaction_id) garante idempotência`).

### R-NFE-006 · Storage cert criptografado at rest

```gherkin
Dado um cert .pfx é uploaded
Quando inspeciono `storage/app/nfe-brasil/{business_id}/cert/{uuid}.pfx.enc`
Então o conteúdo é diferente do .pfx original
E só é decifrável com a chave de criptografia do business
```

**Implementação:** `CertificateStorageService` usa `openssl_encrypt` com chave por business salva em `nfe_business_keys` (rotacionável).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/CertificadoServiceTest.php` (cenário `salvar() persiste cert encrypted + senha encrypted`: conteúdo do storage NÃO é o binary plain — é encrypted; senha roundtrip via `Crypt`).

### R-NFE-007 · Senha do cert NUNCA é logada

```gherkin
Dado um upload de cert com senha
Quando inspeciono `storage/logs/laravel.log` e `activity_log`
Então a senha não aparece em nenhum log
```

**Implementação:** FormRequest tem `dontFlash = ['senha']` + audit log filtra `senha`.
**Testado em:** _lacuna — CertSenhaNaoLogadaTest não existe; nenhum teste asserta que a senha não aparece em `laravel.log`/`activity_log`. A senha encriptada e oculta em serialização é coberta por `Modules/NfeBrasil/Tests/Feature/NfeDomainModelsTest.php` (cenário `NfeCertificado esconde encrypted_password em toArray()`), mas a redação em log especificamente não tem teste_.

### R-NFE-008 · Cancelamento dentro do prazo legal

```gherkin
Dado uma NFC-e emitida há 23h
Quando Larissa cancela com justificativa válida
Então o cancelamento é aceito (cStat=135)

Dado uma NFC-e emitida há 25h
Quando Larissa tenta cancelar
Então recebe 422 com erro "fora do prazo legal NFC-e (24h)"
```

**Implementação:** `CancelamentoService::ensureDentroDoPrazo()` switch por modelo (55: 168h; 65: 24h).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeDomainModelsTest.php` (cenários `isCancelavel — NFC-e dentro/após 24h` e `NFe modelo 55 tem 168h`; complementado por `Modules/NfeBrasil/Tests/Feature/Wave25NfeSaturationTest.php`, `isCancelavel() respeita prazos CONFAZ 24h/168h`).

### R-NFE-009 · Detecção de SEFAZ down → contingência sugerida

```gherkin
Dado que `SefazHealthCheck` falhou 3 vezes seguidas
Quando Larissa tenta finalizar venda
Então o sistema sugere ativar contingência
E permite forçar emissão online (com risco) ou aceitar contingência
```

**Implementação:** Health check job a cada 30s grava `sefaz_status`; UI consulta antes de habilitar emissão online.
**Testado em:** _lacuna — ContingenciaSugestaoTest não existe; nenhum teste cobre `SefazHealthCheck` 3 falhas → sugestão de contingência_.

### R-NFE-010 · Retenção XML 5 anos imutável

```gherkin
Dado uma NFe autorizada em 2026-04
Quando passamos por 5 anos sem alterações no XML
E inspeciono o storage
Então o XML original ainda está lá, com hash SHA256 batendo com `nfe_emissoes.xml_hash`
```

**Implementação:** Storage `storage/app/nfe-brasil/{business_id}/xmls/{ano}/{mes}/{chave}.xml` (read-only após escrita); `xml_hash` na tabela; rotina diária verifica integridade.
**Testado em:** _lacuna — XmlImutabilidadeTest não existe; nenhum teste valida `xml_hash` SHA256 batendo nem imutabilidade read-only do XML após 5 anos_.

### R-NFE-045 · A config da SEFAZ leva o CSC da empresa com o nome que a lib lê

```gherkin
Dado uma empresa com CSC e id do CSC cadastrados (business.csc / business.csc_id)
Quando a NFC-e é assinada
Então a configuração da sped-nfe leva CSC e CSCid com esses valores (as chaves que Common\Tools::addQRCode lê)
E empresa sem CSC leva vazio — no QR Code versão 3 a lib não exige o CSC
```

**Contrato:** sped-nfe `Common\Tools::addQRCode` (lança "faltam dados CSC e/ou CSCId" fora do QR v3).
**Implementação:** `Modules/NfeBrasil/Services/NfeService.php` (`montarConfigSefaz`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfceCscConfigTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** o CSC também vai vazio em `NfeCartaCorrecaoService` e `NfeInutilizacaoService` (eventos de NF-e, onde a lib não usa o CSC).

### R-NFE-011 · Schema CBS/IBS vazio em 2026, preenchido conforme legislação

```gherkin
Dado uma NFe emitida em 2026-04 com regime tradicional
Quando inspeciono `nfe_fiscal_rules.cbs_*` e `nfe_fiscal_rules.ibs_*`
Então estão NULL
E o XML emitido NÃO inclui blocos CBS/IBS
```

**Implementação:** Schema flexível; `MotorTributarioService` skip CBS/IBS se rule é NULL.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/OndaIbsCbsScaffoldTest.php` (schema flexível IBS/CBS: colunas nullable `cst_ibs`/`cst_cbs`/`aliquota_ibs`/`aliquota_cbs` em `$fillable` + casts; cascade do motor coberto por `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php`).

### R-NFE-044 · Cancelar a NF-e pergunta: só a nota ou a venda junto

```gherkin
Dado uma NF-e autorizada de uma venda da empresa, no fluxo de vendas (FSM)
Quando o fiscal cancela a nota escolhendo "venda junto"
Então a nota é cancelada na SEFAZ (evento 110111) e a venda recebe a ação FSM cancelar_venda com o mesmo motivo
E escolhendo "só a nota" (ou sem escolha, como antes) só a nota é cancelada, para reemitir
E venda de outra empresa não é achada; venda fora do fluxo não é cancelada e a mensagem manda cancelar em Vendas
E escolha diferente de nota/venda não passa da validação
```

**Contrato:** decisão [W] 2026-10-26 ("ao cancelar, o sistema pergunta"). O que a ação `cancelar_venda` faz é o `CancelarVendaCascade`: cancela as notas por job idempotente, estorna boleto pendente, libera **reserva** de estoque e avisa o cliente.
**Implementação:** `Modules/Fiscal/Http/Controllers/AcoesController.php` (`cancelarNfe`, `cancelarVendaDaNota`).
**Testado em:** `Modules/Fiscal/Tests/Feature/AcoesCancelarEscopoTest.php` (lane `nfebrasil-pest`).
**Fora deste item (até a US-SELL-077, #9911):** o cascade **não** devolvia ao estoque o que a venda já baixou nem cancelava o título do Financeiro (medido em 2026-10-26 — `LiberarReserva` "NÃO mexe em qty_available"). Com a US-SELL-077 a venda vira `cancelled`, o estoque volta e o título sem pagamento é cancelado; venda com dinheiro recebido **não** cancela.
**Conferência antes da nota (2026-10-26):** com "venda junto", o controller confere antes de cancelar a nota se a venda pode ser cancelada (`recusaDaVenda`: sem venda na empresa, fora do fluxo, ou as pendências do `CancelamentoDaVenda` — dinheiro recebido). Recusou → **nada** é cancelado, nem a nota (o cancelamento na SEFAZ é irreversível). Prova: `AcoesCancelarEscopoTest` UC-FNFE-54.
**Tela:** `resources/js/Pages/Fiscal/_components/NotaDrawer.tsx` (modal com as duas opções; padrão "só a nota") · teste `tests/js/fiscal-nfe-cancelar-escopo.test.tsx` (lane `fiscal-teclado-gate`).

### R-NFE-012 · Não-emissão NÃO bloqueia venda (assíncrono)

```gherkin
Dado SEFAZ está fora E contingência não está ativa
Quando Larissa finaliza venda no POS
Então a venda é gravada (`transaction.payment_status = paid`)
E aparece alerta vermelho "NFe pendente — ativar contingência?"
E job retentativa fica na fila
```

**Implementação:** `EmitirNfceJob` em queue retry exponencial; UI vê fila em `/nfe-brasil/monitor`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/EmitirNfceAoFinalizarVendaTest.php` (listener despacha `EmitirNfceJob` em queue ao finalizar venda `payment_status=paid` sem bloquear a venda; filtro `payment_status` e gate per-business; dispatch do job em si coberto por `Modules/NfeBrasil/Tests/Feature/EmitirNfceJobTest.php`).

### R-NFE-013 · Audit log Spatie em toda mutação fiscal

```gherkin
Dado qualquer emissão / cancelamento / CCe
Quando consulto activity_log
Então existe row com causer + subject + properties (chave, cStat, valor)
```

**Implementação:** Trait `LogsActivity` em `NfeEmissao`, `NfeEvento`, `Certificado`, `FiscalRule`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/Wave25NfeSaturationTest.php` (D7: `NfeEmissao`+`NfeEvento`+`NfeInutilizacao` usam `LogsActivity` + `getActivitylogOptions` loga apenas campos canon; complementado por `Modules/NfeBrasil/Tests/Feature/Wave23SaturationTest.php`).

### R-NFE-014 · Webhook SEFAZ idempotência (consulta status)

```gherkin
Dado uma consulta `WS-Consulta-Cadastro` retorna duas vezes (rede instável)
Quando processo as 2 respostas
Então `nfe_consultas.cache_key` UNIQUE bloqueia a 2ª de processar de novo
```

**Implementação:** Cache de consulta com TTL + UNIQUE em `consultas` por chave + tipo.
**Testado em:** _lacuna — ConsultaIdempotenciaTest não existe; nenhum teste cobre `nfe_consultas.cache_key` UNIQUE bloqueando reprocessamento da 2ª resposta_.

### R-NFE-015 · ICMS-ST calculado pela MVA

```gherkin
Dado regra com mva=0,40, IPI 0, ICMS próprio 12% e alíquota interna do destino 20% informada
Quando o motor calcula um item de valor 1.000,00 (fixture)
Então base_st = 1.000 × 1,40 = 1.400,00 e valor_st = 1.400 × 0,20 − 120,00 = 160,00
E regra sem MVA, ou sem a interna informada, devolve valor_st = 0 e os demais campos como antes
```

**Contrato:** LC 87/1996, art. 8º, II e § 5º (citados literalmente no docblock do motor) · US-NFE-010 · US-FISCAL-020.
**Implementação:** `Modules/NfeBrasil/Services/MotorTributarioService.php` (`calcularStFcpDifal`) · `Modules/NfeBrasil/Services/Tributacao/TributoCalculado.php` (`base_st`, `valor_st`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** a interna vem do caller; a tabela por UF é R-NFE-021 (thread 09) e a emissão ainda não a informa, então o XML não muda.

### R-NFE-015b · FCP calculado quando a regra tem FCP

```gherkin
Dado regra com fcp=0,02
Quando o motor calcula um item de valor 1.000,00 (fixture)
Então valor_fcp = 20,00 (em DIFAL, é o FCP da UF de destino)
E regra com fcp nulo dá valor_fcp = 0
```

**Contrato:** ADCT art. 82, § 1º · coluna `nfe_fiscal_rules.fcp`, até aqui gravada e ignorada.
**Implementação:** `Modules/NfeBrasil/Services/MotorTributarioService.php` (`calcularStFcpDifal`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (lane `nfebrasil-pest`).

### R-NFE-016 · CSOSN 500 não recalcula ST

```gherkin
Dado regra com csosn=500 (ST cobrada anteriormente) e mva preenchida
Quando o motor calcula com a interna do destino informada
Então valor_st = 0
E a mesma regra com csosn=201 calcula a ST
```

**Implementação:** `Modules/NfeBrasil/Services/MotorTributarioService.php` (`calcularStFcpDifal`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** CST 60 (regime normal, mesma situação) ainda não é tratado.

### R-NFE-041 · Busca de DF-e e manifestação usam o CNPJ que a empresa tem

```gherkin
Dado a empresa sem titular no certificado, com tax_number_1 vazio e business.cnpj preenchido (biz=1 em produção, #9833)
Quando a busca de DF-e ou a manifestação montam a configuração da SEFAZ
Então o CNPJ é o do business.cnpj; o valor padrão da coluna (só zeros) não conta e cai no tax_number_1; o titular do certificado vem primeiro
E sem CNPJ em nenhum dos três a chamada é recusada com "CNPJ da empresa … não configurado — preencha em Fiscal › Configuração"
```

**Implementação:** `Modules/NfeBrasil/Support/CnpjDoEmitente.php` · `Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeService.php` e `ManifestacaoService.php` (`buildConfig`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/DfeCnpjEAlarmeTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** o ambiente (`nfebrasil.ambiente` nulo em produção → homologação) — ligar `ambiente=1` é decisão [W] depois deste conserto; o `EmpresaFiscalLookupService` segue com a ordem dele (titular → `tax_number_1`).

### R-NFE-042 · Falha da busca de DF-e vira alarme visível

```gherkin
Dado o BuscarDfesRecebidosJob falhando nas 3 tentativas
Quando ele desiste
Então grava um alerta dfe_busca_falhou (aberto, severidade alta, com o último erro) em mcp_alertas_eventos, um por empresa e por dia
E o cockpit Fiscal da empresa mostra o alerta em crit nos 3 dias seguintes, até alguém arquivá-lo; a empresa vizinha não o vê
```

**Implementação:** `Modules/NfeBrasil/Jobs/BuscarDfesRecebidosJob.php` (`failed`) · `Modules/Fiscal/Http/Controllers/CockpitController.php` (`computeAlerts`, `ultimaFalhaBuscaDfe`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/DfeCnpjEAlarmeTest.php` (lane `nfebrasil-pest`).

### R-NFE-017 · DIFAL só para não contribuinte em outra UF

```gherkin
Dado destinatário sem IE em UF diferente da origem, ICMS da regra 12% e interna do destino 20%
Quando o motor calcula um item de valor 1.000,00 (fixture)
Então valor_difal = 1.000 × (0,20 − 0,12) = 80,00
E destinatário com IE, ou na mesma UF, dá valor_difal = 0
```

**Contrato:** CF art. 155, § 2º, VII e VIII, b (EC 87/2015), citados no docblock do motor.
**Implementação:** `Modules/NfeBrasil/Services/MotorTributarioService.php` (`calcularStFcpDifal`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** o corte do DIFAL do Simples por UF (ADI 5.464) é R-NFE-035 (thread 29).

### R-NFE-018 · A nota usa a versão de regra vigente na data da emissão

```gherkin
Dado a versão A da regra (ICMS 12%) válida até 31/12/2026 e a versão B (ICMS 18%) a partir de 01/01/2027
Quando o motor calcula um item de 1.000,00 com data 31/12/2026 e com data 01/01/2027
Então usa A (ICMS 120,00) e B (ICMS 180,00), respectivamente
E uma regra sem vigência cadastrada vale em qualquer data, como antes
```

**Contrato:** D-OPERACAO ([W] 2026-10-06) · ADR ARQ-0006.
**Implementação:** `Modules/NfeBrasil/Services/MotorTributarioService.php` (`buscarRegra`, parâmetro `dataEmissao` de `calcularComDestino`) · `Modules/NfeBrasil/Models/NfeFiscalRule.php` (`scopeVigenteEm`) · migração `2026_10_07_000001`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** a emissão chama o motor com a data de hoje; o SPED (`Modules/Fiscal`) recalcula notas antigas sem passar a data delas — ver `_saida-07.md`.

### R-NFE-019 · Editar regra cria versão nova; a antiga continua lendo igual

```gherkin
Dado uma regra de ICMS 12%
Quando o contador a edita para 18% em 07/10/2026
Então nasce uma versão nova válida de 07/10/2026, a antiga ganha valida_ate = 06/10/2026 e nenhuma outra coluna dela muda
E recalcular com data 06/10/2026 dá 120,00 e com 07/10/2026 dá 180,00 (item de 1.000,00)
E o override por produto que apontava a versão antiga passa a usar a vigente
E o tax_rate vinculado passa para a versão nova (o cadastro de impostos não ganha linha repetida)
```

**Contrato:** ADR 0093 G8 (append-only) · D-OPERACAO · ADR ARQ-0005 (vínculo com `tax_rates`).
**Implementação:** `Modules/NfeBrasil/Models/NfeFiscalRule.php` (`novaVersao`) · `Modules/NfeBrasil/Http/Controllers/TributacaoController.php` (`update`, `regraEditavel`) · `Modules/NfeBrasil/Services/MotorTributarioService.php` (Nível 1 segue a cadeia).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` · `Modules/NfeBrasil/Tests/Feature/OperacaoFiscalTenantTest.php` (lane `nfebrasil-pest`).

### R-NFE-020 · O padrão atual vira a regra geral da operação Venda sem perda

```gherkin
Dado uma empresa com tributacao_default (CFOP 5102, CSOSN 102, ICMS 1,86%, PIS 0,65%, COFINS 3%)
Quando a migração roda (duas vezes)
Então existe uma única operação "Venda" padrão, cuja regra geral é o tributacao_default da empresa
E o motor devolve o mesmo resultado de antes, campo a campo (item de 1.000,00: ICMS 18,60 · PIS 6,50 · COFINS 30,00)
```

**Contrato:** D-OPERACAO · UC-NFCD-02.
**Implementação:** migração `2026_10_07_000002_seed_operacao_venda_padrao` (regra geral NULL = usa o `tributacao_default`, que a tela ConfigDefault continua editando) · `Modules/NfeBrasil/Models/NfeOperacaoFiscal.php`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/OperacaoFiscalTenantTest.php` (lane `nfebrasil-pest`).

### R-NFE-020b · Exceção só vale para a operação dela

```gherkin
Dado uma exceção do NCM X na operação Venda (ICMS 12%) e a operação "Devolução" com regra geral CFOP 1202 / CSOSN 900
Quando o motor calcula a Devolução para o NCM X
Então usa a regra geral da Devolução (Nível 4), não a exceção de venda
E na Venda a exceção é usada (ICMS 120,00 num item de 1.000,00)
E uma operação sem regra geral recusa o cálculo, em vez de usar o padrão da venda
```

**Contrato:** D-OPERACAO.
**Implementação:** `Modules/NfeBrasil/Services/MotorTributarioService.php` (`buscarRegra`, Nível 4 por operação).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (lane `nfebrasil-pest`).

### R-NFE-020c · CFOP com "?" vira 5, 6 ou 7 pelo destino

```gherkin
Dado uma operação com CFOP ?102
Quando o destino é a UF da empresa, outra UF, ou o exterior (EX)
Então o CFOP é 5102, 6102 e 7102
E um CFOP sem "?" (3102) não é alterado
```

**Contrato:** D-OPERACAO · padrão de mercado (Tiny/Olist).
**Implementação:** `Modules/NfeBrasil/Models/NfeOperacaoFiscal.php` (`resolverCfop`) · `Modules/NfeBrasil/Services/MotorTributarioService.php`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** a emissão ainda não passa `EX` para o exterior.

### R-NFE-020d · Operações e versões de regra não atravessam empresas

```gherkin
Dado uma operação e uma regra da empresa A
Quando a empresa B calcula com a operação de A, lista operações, ou edita a regra de A
Então o cálculo é recusado, a lista não traz a de A, e a edição dá 404 sem mudar a regra
E a empresa A usa as próprias
```

**Contrato:** ADR 0093 · `HasBusinessScope`.
**Implementação:** `Modules/NfeBrasil/Models/NfeOperacaoFiscal.php` · `Modules/NfeBrasil/Services/MotorTributarioService.php` (`resolverOperacao`) · migração `2026_10_07_000001` (FK para `business`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/OperacaoFiscalTenantTest.php` (lane `nfebrasil-pest`).

### R-NFE-021 · UF sem alíquota interna cadastrada não inventa valor

```gherkin
Dado a tabela ICMS por UF da empresa semeada (interestaduais) e a interna de RJ vazia
Quando alguém pede a alíquota interna de RJ
Então recebe o erro "Alíquota interna de RJ não cadastrada", nunca 0 nem um palpite
E com a interna preenchida pelo contador (20%) devolve 0,20
E o seed traz 729 pares por empresa: 146 a 7% e 556 a 12%, conferidos contra a Resolução do Senado nº 22/1989, e 27 internos sem interestadual
E rodar o seed de novo não insere nem altera nada (append-only)
```

**Contrato:** lei 4 do módulo · D-UF ([W] 2026-10-06) · Resolução do Senado Federal nº 22/1989, art. 1º e parágrafo único, II (citada literal no seeder).
**Implementação:** migração `2026_10_07_000003_create_nfe_icms_uf_table` · `Modules/NfeBrasil/Models/NfeIcmsUf.php` (`aliquotaInterna`, `aliquotaInterestadual`) · `Modules/NfeBrasil/Database/Seeders/NfeIcmsUfSeeder.php` · `Modules/NfeBrasil/Exceptions/AliquotaInternaNaoCadastradaException.php`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/IcmsUfTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** o motor ainda não lê a tabela (fallback é outra thread); os 4% da Resolução nº 13/2012 dependem da origem do produto importado; o seed não roda sozinho no deploy.

### R-NFE-022 · Bloqueio por NCM padrão sempre oferece a saída e nunca emite sem NCM

```gherkin
Dado empresa sem NCM padrão (nem em tributacao_default.ncm_default nem em business.ncm_padrao)
Quando tenta emitir a NFC-e ou NF-e
Então a emissão não acontece e nenhuma nota é criada
E o erro traz a ação "Escolher NCM padrão" com a tela /nfe-brasil/tributacao/config-default#ncm_default
E a emissão manual responde 422 com essa ação, em vez de 500 só com texto
E a tela é a mesma que a Saúde fiscal aponta para "NCM padrão ausente"
E com NCM padrão válido o bloqueio de NCM não acontece (controle positivo)
```

**Contrato:** D-SUPORTE ([W] 2026-10-06: *"NF-e sem NCM não é saída: a saída é o NCM padrão da empresa"*) · playbook Fiscal thread 16.
**Implementação:** `Modules/NfeBrasil/Exceptions/NcmPadraoAusenteException.php` · `Modules/NfeBrasil/Services/NfeService.php` (`emitirParaInvoice`, `emitirParaTransaction`, itens) · `Modules/NfeBrasil/Http/Controllers/NfeEmissaoController.php` (`emitir`) · `resources/js/Pages/Sells/_components/FiscalSection.tsx` (mostra o link).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeBloqueioComSaidaTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** a emissão automática (job da NFC-e e listener da cobrança) segue só registrando em log: lá a saída é o card da Saúde fiscal (UC-NFTR-24). A emissão recusa NCM com tamanho ≠ 8; `00000000` passa, embora a Saúde fiscal o marque como bloqueante.

### R-NFE-026 · Devolução copia base e alíquotas da nota de origem

```gherkin
Dado uma nota autorizada com ICMS 18% (2 × 50,00 = 100,00 · ICMS 18,00) e, depois, a regra mudada para 12%
Quando emito a devolução dessa nota
Então o det usa 18% e a base da origem (ICMS 18,00), finNFe=4, tpNF=0, refNFe = chave da origem e CFOP espelho (5102 → 1202)
E uma venda nova depois da mudança usa 12% (100,00 × 12% = 12,00)
E CFOP sem espelho conhecido (ex. 5949) é recusado, em vez de inventado
```

**Contrato:** D-OPERACAO ([W] 2026-10-06) · R-NFE-019 (versão antiga intacta) · leiaute NF-e 4.00 (finNFe 4, grupo NFref).
**Implementação:** `Modules/NfeBrasil/Services/DevolucaoService.php` (lê o XML autorizado da origem; não recebe o motor) · `Modules/NfeBrasil/Services/NfeService.php` (`buildXml`: `fin_nfe`, `tp_nf`, `ref_nfe`, com default igual ao de antes).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeDevolucaoTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** tela/rota para pedir a devolução e o vínculo com o registro de devolução de venda (`SellReturn`) — ver `_saida-20.md`.

### R-NFE-027 · Devolução parcial é proporcional e não passa da quantidade vendida

```gherkin
Dado uma venda de 10 unidades (vProd 99,90 · ICMS 17,98)
Quando devolvo 4
Então vProd 39,96 e ICMS 7,19 (4/10 da linha, arredondado em centavos)
Quando tento devolver mais 7
Então é recusado (saldo 6)
E devolver as 6 restantes é aceito (vProd 59,94 · ICMS 10,79), e a soma das devoluções bate com a linha (17,98)
E devolução rejeitada, de outra nota ou de outra empresa não consome saldo
```

**Contrato:** UC-TRB-13 (protótipo, aba Devoluções) · ADR 0093 (saldo por empresa).
**Implementação:** `Modules/NfeBrasil/Services/DevolucaoService.php` (`montarDevolucao`, rateio pela quantidade acumulada · `saldoDevolvido`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeDevolucaoTest.php` (lane `nfebrasil-pest`).

### R-NFE-039 · A NF-e de devolução tem rota, com permissão e na empresa da sessão

```gherkin
Dado uma NF-e autorizada da empresa A
Quando o fiscal com a permissão fiscal.nfe.acoes pede POST /fiscal/acoes/nfe/{id}/devolucao com itens[1]=2 e itens[3]=0,5
Então o DevolucaoService recebe a empresa A, a nota e {1: 2, 3: 0,5} (R-NFE-026/027 fazem o resto)
E sem a permissão a resposta é 403; sem item, ou com quantidade zero, negativa ou não numérica, não passa da validação
E a nota de outra empresa não é achada (404) e o serviço não é chamado
```

**Contrato:** US-NFE-064 (a devolução copia a nota de origem) · ADR 0093.
**Implementação:** `Modules/Fiscal/Http/Controllers/AcoesController.php` (`devolucao`) · `Modules/Fiscal/Routes/web.php` (`fiscal.acoes.nfe.devolucao`).
**Testado em:** `Modules/Fiscal/Tests/Feature/AcoesDevolucaoTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** o botão "Emitir devolução" na tela da nota (escolher itens e quantidades) e o vínculo com a devolução de venda do estoque (`sell_return`).

### R-NFE-030 · Base reduzida e cBenef no item

```gherkin
Dado uma regra com redução de base de 33,33%, ICMS 18% e cBenef informado pelo contador
Quando vendo um item de 600,00
Então a base do ICMS é 600,00 × (1 − 0,3333) = 400,02 e o ICMS é 400,02 × 18% = 72,00
E o item da nota leva <cBenef>, <pRedBC>33.3300 e <vBC>400.02, e o total da nota soma essa base
E uma regra sem redução calcula sobre a base cheia (600,00 × 18% = 108,00), sem cBenef e com o XML de antes
```

**Contrato:** lei 4 do módulo (o ato legal da UF fica na regra, em `beneficio_fundamento`, obrigatório junto da redução ou do código) · D-MOTOR ([W] 2026-10-06).
**Implementação:** migração `2026_10_14_000001_add_beneficio_fiscal_to_nfe_fiscal_rules` · `Modules/NfeBrasil/Services/MotorTributarioService.php` (`aplicarRegra`) · `Modules/NfeBrasil/Services/Tributacao/TributoCalculado.php` · `Modules/NfeBrasil/Services/NfeService.php` (`montarItensNfe`, `adicionarItem`) · `Modules/NfeBrasil/Http/Requests/UpsertRegraTributariaRequest.php`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** ST e DIFAL seguem sobre a base cheia; a pendência na Saúde fiscal para UF que exige cBenef depende de uma tabela das UFs que o exigem, com o ato de cada uma — ver `_saida-25.md`.

### R-NFE-031 · Filial usa a própria UF de origem

```gherkin
Dado uma filial no PR cadastrada como local emissor (CNPJ, IE, endereço, CEP, código IBGE e série próprios)
Quando ela vende
Então a nota sai com a UF, o CNPJ, a IE e o endereço da filial, e o motor calcula com uf_origem = PR
E a exceção cadastrada para SP não é usada (Nível 4: 1.000,00 × 1,86% = 18,60), enquanto a matriz SP usa a dela (180,00)
E a interestadual do par é a da Resolução do Senado 22/1989: PR→SC 12% · PR→BA 7% · SP→BA 7%
E local sem cadastro de emissor, ou venda sem local, emite como o business, igual a antes
E filial incompleta, com a série da matriz, ou apontando local de outra empresa é recusada
```

**Contrato:** Resolução do Senado Federal nº 22/1989 (tabela da thread 09) · chave `uf_origem` (ADR ARQ-0006) · ADR 0093.
**Implementação:** migração `2026_10_14_000002_create_nfe_locais_emissores_table` · `Modules/NfeBrasil/Models/NfeLocalEmissor.php` · `Modules/NfeBrasil/Services/NfeService.php` (`resolverEmitente`, `emitirParaTransaction`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeFilialNumeracaoTest.php` · `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** o motor ainda não lê a interestadual da tabela (é o fallback da thread 09); o certificado continua o do business; a cobrança recorrente (`emitirParaInvoice`) segue emitindo pelo business — ver `_saida-26.md`.

### R-NFE-040 · Item do regime normal sai com base e alíquotas em percentual

```gherkin
Dado uma regra do regime normal com CST 00, ICMS 0,18, PIS 0,0165 e COFINS 0,076 (fração, como a régua guarda)
Quando um item de 1.000,00 vai à NF-e
Então o item leva vBC 1.000,00, pICMS 18, vICMS 180,00, pPIS 1,65 e pCOFINS 7,6, e o ICMSTot leva vBC 1.000,00
E vBC × pICMS / 100 = vICMS (a conta que a SEFAZ confere)
E com base reduzida (benefício) a base é a do motor; Simples, CST sem ICMS ou alíquota zero seguem com base 0
```

**Contrato:** NF-e leiaute 4.00 — `pICMS`, `pPIS` e `pCOFINS` são percentuais (TDec_0302a04); `UpsertRegraTributariaRequest` guarda fração ("0.18 = 18%").
**Implementação:** `Modules/NfeBrasil/Services/NfeService.php` (`montarItensNfe`, `emitirParaInvoice`, `baseIcmsDoItem`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeAliquotaPercentualTest.php` · `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` · `Modules/NfeBrasil/Tests/Feature/NfeDevolucaoTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** CST de PIS/COFINS do regime normal com base (hoje `07` fixo, R-NFE-025b) e o ICMS-ST.

### R-NFE-032 · Série e numeração são do local emissor (o certificado segue o do business)

```gherkin
Dado matriz (série padrão) e filial (série 7), com o contador legado da matriz em 995.999
Quando emitem alternadas, três vezes cada
Então a matriz sai 996.000, 996.001, 996.002 e a filial 1, 2, 3 — nenhuma pula nem repete
E autorizar a nota da filial não mexe no contador da matriz; autorizar a da matriz mexe
```

**Contrato:** R-NFE-003 (numeração sem gap) · UNIQUE `nfe_emissoes_biz_seq_unique` (business + modelo + série + número).
**Implementação:** `Modules/NfeBrasil/Services/NfeService.php` (`proximoNumeroLocked`, `processarRetorno`, `seriePadrao`) · série única por empresa em `nfe_locais_emissores`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeFilialNumeracaoTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** certificado por local — a emissão segue assinando com o certificado do business.

### R-NFE-034 · Natureza decidida pelo contador vale para o produto

```gherkin
Dado um banner marcado como natureza pendente
Então a NF-e emite pelo padrão da UF e a Saúde fiscal mostra "natureza a decidir" (aviso, não bloqueia)
Quando o contador decide "serviço 24.01" com a fonte
Então a decisão fica com autor, data e fonte, e a NF-e/NFC-e da venda recusa o item dizendo "emita NFS-e"
E marcar o produto como pendente de novo não desfaz a decisão (ele sai da fila)
E sem a permissão nfe.tributacao.manage, de outra empresa, sem fonte ou sem item da LC 116 a decisão é recusada — a Jana nunca grava natureza
```

**Contrato:** D-NATUREZA ([W] 2026-10-06) · Súmula 156 do STJ · SEFAZ-SP Consulta 10406/2016 (citadas no docblock do serviço).
**Implementação:** migração `2026_10_14_000003_create_nfe_naturezas_produto_e_difal_simples` · `Modules/NfeBrasil/Models/NfeNaturezaProduto.php` · `Modules/NfeBrasil/Services/Tributacao/NaturezaFiscalService.php` · `Modules/NfeBrasil/Services/NfeService.php` (`recusarItensDeServico`) · `Modules/NfeBrasil/Services/Tributacao/SaudeFiscalService.php` (`naturezaPendente`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NaturezaFiscalTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** a emissão da NFS-e a partir da venda (o playbook deixa NFS-e fora, rev.6) e a tela de decisão — ver `_saida-29.md`.

### R-NFE-035 · DIFAL do Simples só nas UFs marcadas

```gherkin
Dado empresa do Simples vendendo a consumidor no RJ (regra 12%, interna do RJ 20%, item de 1.000,00)
Quando RJ não está marcado na tabela por UF
Então sem DIFAL e um aviso citando a ADI 5.464 (bateria C17); o ICMS próprio segue 120,00
Quando o contador marca RJ
Então DIFAL = 1.000,00 × (0,20 − 0,12) = 80,00
E venda a contribuinte nunca gera DIFAL do remetente, marcada ou não
E empresa fora do Simples segue o R-NFE-017 (80,00) mesmo com RJ desmarcado
```

**Contrato:** D-DIFAL-UF ([W] 2026-10-06) · STF ADI 5.464 · STF Tema 1284 · SEFAZ-SP Consulta 32359/2025.
**Implementação:** migração `2026_10_14_000003_create_nfe_naturezas_produto_e_difal_simples` (`nfe_icms_uf.cobra_difal_simples`) · `Modules/NfeBrasil/Services/MotorTributarioService.php` (`calcularStFcpDifal`, `ehSimples`, `ufCobraDifalSimples`) · `Modules/NfeBrasil/Services/Tributacao/TributoCalculado.php` (`aviso`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** a guia (GNRE) do DIFAL; nenhum caller da emissão passa a interna do destino hoje, então o DIFAL ainda não chega à nota.

### R-NFE-038 · O XML do mês vai ao contador, uma vez por competência

```gherkin
Dado a empresa com contador cadastrado e, em 09/2026, 1 NF-e autorizada, 1 cancelada e 1 rejeitada (fixture)
Quando roda o envio da competência 2026-09
Então o contador recebe um e-mail com um ZIP de 2 XML (a autorizada e a cancelada, marcada "-cancelada")
E o envio fica registrado; rodar de novo no mesmo mês não manda outro e-mail
E nota do mês sem XML no storage é contada no e-mail, não some
E nota de outra empresa ou de outro mês não entra; empresa sem contador ou sem nota não recebe nada
E o agendamento mensal (dia 5, 07:00) só roda com a chave ligada
```

**Contrato:** CONFAZ Ajuste SINIEF 07/2005, cláusula décima (o emitente guarda o XML e o disponibiliza); a NF-e cancelada segue escriturada.
**Implementação:** `Modules/NfeBrasil/Services/PacoteXmlContadorService.php` · `Modules/NfeBrasil/Mail/PacoteXmlContadorMail.php` · `Modules/NfeBrasil/Console/Commands/EnviarXmlContadorCommand.php` · migração `2026_10_26_000001_create_nfe_envios_contador_table` · `Modules/NfeBrasil/Providers/NfeBrasilServiceProvider.php` (agenda) · `config/fiscal.php` (`envio_xml_contador_enabled`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/EnviarXmlContadorTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** o drawer "Enviar p/ contabilidade" do cockpit ainda mostra dados fictícios e o botão está desligado; NFS-e e eventos (CC-e) no pacote; SFTP.

### R-NFE-033 · Aceita CNPJ alfanumérico válido e recusa o inválido

```gherkin
Dado um CNPJ alfanumérico válido de destinatário (ex. fictício da Receita 12.ABC.345/01DE-35)
Quando cadastro o cliente e monto a NF-e
Então o cadastro aceita e o XML leva <CNPJ>12ABC34501DE35</CNPJ> no <dest>
E com dígito verificador errado o cadastro recusa (422) dizendo que o CNPJ alfanumérico não confere
E um CNPJ só numérico continua válido, com o mesmo DV de antes
```

**Contrato:** IN RFB nº 2.229/2024 — *"O CNPJ adotará o formato alfanumérico composto por quatorze posições, conforme disposto no Anexo XV, com previsão de implementação a partir de julho de 2026."*
**Implementação:** `app/Support/BR/Cnpj.php` (DV módulo 11 com valor ASCII − 48) · `app/Rules/BR/CpfCnpj.php` (cadastro) · `Modules/NfeBrasil/Services/NfeService.php` (`emitirParaInvoice` e `buildXml` deixam de arrancar as letras do destinatário).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/CnpjAlfanumericoTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** emitente com CNPJ alfanumérico, chave de acesso, QR Code, máscara no front e o XSD do sped-nfe (que ainda declara `TCnpj` como `[0-9]{14}`) — ver `_saida-27.md` da thread 27 do playbook Fiscal.

### R-NFE-036 · A NF-e da venda sai para o cliente da venda

```gherkin
Dado uma venda de SC com cliente cadastrado com CNPJ, IE e endereço no RJ
Quando emito a NF-e (modelo 55) dessa venda
Então o <dest> leva o CNPJ, a IE, o endereço e a UF RJ do cliente, e o idDest é 2 (interestadual)
E o motor recebe a UF RJ, se o cliente é contribuinte e a alíquota interna do RJ (quando cadastrada)
E o vNF continua igual ao total da venda
E venda sem cliente com CPF/CNPJ, e toda NFC-e, seguem com o consumidor anônimo da própria UF, como antes
```

**Contrato:** NF-e leiaute 4.00, grupo E (destinatário) e campo `idDest` (1 interna · 2 interestadual); `indIEDest` 1/2/9 conforme o cadastro do cliente.
**Implementação:** `Modules/NfeBrasil/Services/NfeService.php` (`emitirParaTransaction`, `destinatarioDaVenda`, `destinatarioDoContato`, `internaDoDestino`, `montarItensNfe`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeDestinatarioDaVendaTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** gravar o ICMS-ST no XML (R-NFE-037 grava só o DIFAL).

### R-NFE-037 · O DIFAL da venda a não contribuinte de outra UF vai ao XML

```gherkin
Dado uma venda de SC para não contribuinte no RJ, ICMS interestadual 12%, interna do RJ 20%, FCP 2%, item de 1.000,00 (fixture)
Quando sai a NF-e
Então o item leva o grupo ICMSUFDest com vBCUFDest 1.000,00, pICMSUFDest 20,0000, pICMSInter 12,00, partilha 100, vICMSUFDest 80,00 e vFCPUFDest 20,00
E o ICMSTot leva vICMSUFDest 80,00 e vFCPUFDest 20,00, com o vNF igual ao total da venda (1.000,00)
E contribuinte, ou mesma UF, não leva o grupo, e o XML fica como antes
E sem a interna do RJ cadastrada a nota é recusada com "Alíquota interna de RJ não cadastrada"
```

**Contrato:** CF art. 155, § 2º, VII e VIII, b (EC 87/2015) e ADCT art. 99 (partilha 100% para o destino desde 2019); NF-e leiaute 4.00, grupo NA (ICMSUFDest). A SEFAZ exige o grupo na operação interestadual com consumidor final não contribuinte.
**Implementação:** `Modules/NfeBrasil/Services/NfeService.php` (`montarItensNfe` monta o `difal` do item; `adicionarItem` emite `tagICMSUFDest`; `buildXml` soma no ICMSTot; `garantirInternaDoDifal` recusa sem a interna).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeDifalNoXmlTest.php` · `Modules/NfeBrasil/Tests/Feature/NfeDestinatarioDaVendaTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** empresa do Simples sai com o grupo e DIFAL zero quando a UF não cobra (R-NFE-035) — se a SEFAZ aceita assim só o smoke em homologação confirma; a guia GNRE do DIFAL; o `pICMSInter` é a alíquota ICMS da regra, então a regra interestadual tem de ter 4%, 7% ou 12%.

### R-NFE-043 · O ICMS-ST do motor vai ao XML e soma no vNF

```gherkin
Dado um item de 1.000,00 de SC para contribuinte no RJ, regra CST 10, ICMS próprio 12%, MVA 40%, interna do RJ 18% (fixture)
Quando sai a NF-e
Então o ICMS10 leva modBCST 4, pMVAST 40, vBCST 1.400,00, pICMSST 18 e vICMSST 132,00 (1.400 × 0,18 − 120)
E o ICMSTot leva vBCST 1.400,00 e vST 132,00, o vNF é 1.132,00 e o pagamento acompanha o vNF
E CSOSN 202 também leva o ST; regra com MVA mas CST/CSOSN sem ST (ex. CST 00) é recusada com a saída
E sem ST o vNF segue igual ao total da venda
```

**Contrato:** leiaute NF-e 4.00, regra W16 (rejeição 610 — "Total da NF difere do somatório"): vNF = vProd − vDesc − vICMSDeson + vST + vFCPST + vFrete + vSeg + vOutro + vII + vIPI + vIPIDevol. [W] 2026-10-26: "não é decisão minha, é regra do leiaute — siga".
**Implementação:** `Modules/NfeBrasil/Services/NfeService.php` (`stDoItem`, `preencherSt`, `montarItensNfe`, `buildXml`, `emitirParaTransaction`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeIcmsStNoXmlTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** FCP-ST; ST em operação interna (a interna da própria UF ainda não chega ao motor — só a do destino interestadual); a venda continua com `final_total` sem o ST, então a nota com ST vale mais que a venda no caixa e o título do Financeiro.

### R-NFE-046 · Destinatário e CFOP informados por quem emite (pedido de marketplace)

```gherkin
Dado uma venda cujo comprador veio de fora do cadastro (ex. pedido de marketplace)
Quando emito a NF-e (modelo 55) informando esse destinatário, com as chaves de `contacts`
Então o <dest> leva o documento, o endereço e a UF desse destinatário, e não o cliente da venda
E destinatário informado sem CPF/CNPJ válido recusa a emissão, sem cair no consumidor anônimo
Quando informo também um CFOP de 4 dígitos (1xxx a 7xxx)
Então todos os itens saem com esse CFOP no lugar do da regra, e CFOP fora do formato recusa
E sem destinatário nem CFOP informados a emissão é a do R-NFE-036
```

**Contrato:** NF-e leiaute 4.00, grupo E (destinatário) e campo `CFOP` do item (I08). O valor do CFOP é de quem chama: nada no `NfeService` fixa CFOP de marketplace.
**Implementação:** `Modules/NfeBrasil/Services/NfeService.php` (`emitirParaTransaction`, `destinatarioDaEmissao`, `comCfop`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeDestinatarioExternoCfopTest.php` (lane `nfebrasil-pest`).

### R-NFE-047 · Nota de bonificação

```gherkin
Dado uma venda com transactions.sub_type = 'bonificacao'
Quando emito a NF-e (modelo 55) sem CFOP informado
Então todos os itens saem com CFOP 5910 (destinatário da mesma UF) ou 6910 (outra UF)
E a natureza da operação é BONIFICACAO e o pagamento é tPag 90 (sem pagamento) com vPag 0
E a tributação do item e os totais (vProd, vNF) são os mesmos da venda
Quando o CFOP é informado por quem chama, ele vence; destino exterior recusa
Quando peço NFC-e (modelo 65) dessa venda, a emissão recusa
```

**Contrato:** NF-e leiaute 4.00 — CFOP 5910/6910 (remessa em bonificação, doação ou brinde), grupo YA tPag 90 = sem pagamento, que exige vPag 0.
**Implementação:** `Modules/NfeBrasil/Services/NfeService.php` (`emitirParaTransaction`, `ehBonificacao`, `comoBonificacao`) · `Modules/NfeBrasil/Listeners/EmitirNfceAoFinalizarVenda.php`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfeBonificacaoTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** bonificação "sem ST" do Delphi (decisão pendente); a tela que grava o `sub_type` e o Financeiro sem título.

### R-NFSE-004 · A NFS-e lê o certificado da config pelo CertificadoService

```gherkin
Dado uma empresa com config de NFS-e apontando um certificado A1 (cert_id)
Quando monto o payload da NFS-e
Então o PFX vem do disco ({business}/cert/{uuid}.pfx.enc), decriptado, em base64 uma única vez
E a senha vem de encrypted_password, decriptada
E empresa sem config monta sem certificado e não lê o de outra
E arquivo ausente no disco falha com "Arquivo do certificado ausente em disco", nunca em silêncio
```

**Contrato:** schema único de certificado do NfeBrasil (4e772bcb46, 2026-05-07), que removeu `pfxDecriptado()`/`senhaDecriptada()` do modelo do NFSe sem migrar o chamador — desde então toda emissão com certificado configurado quebrava ao montar a nota.
**Implementação:** `Modules/NfeBrasil/Services/CertificadoService.php` (`carregarCertificado`, também usado por `carregarParaSefaz`) · `Modules/NFSe/Services/NfseEmissaoService.php` (`montarPayload`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfseCertificadoPayloadTest.php` (lane `nfebrasil-pest`).

### R-NFSE-001 · Retém 4,65% de tomador PJ no regime normal; dispensa no limite do art. 31 § 3º

```gherkin
Dado serviço de 1.500 reais pra tomador PJ, prestador em lucro presumido ou real
Quando calculo as retenções federais da NFS-e
Então PIS 9,75 + COFINS 45,00 + CSLL 15,00 = 69,75 reais (bateria C21)
E com serviço de 150 reais os 4,65% dão 6,98 reais ≤ 10 reais → dispensado, com aviso
E 10,00 reais exatos dispensa; 10,01 reais retém
E tomador PF não retém; serviço fora da lista do art. 30 não retém
```

**Contrato:** Lei 10.833/2003, arts. 30, 31 e 31 § 3º (citados literal no docblock). NFS-e consolidada no NfeBrasil por D-NFSE-CONSOLIDA ([W] 2026-10-13).
**Implementação:** `Modules/NfeBrasil/Services/RetencoesNfseService.php` (`calcular`, `calcularParaBusiness`).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfseRetencoesTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** a emissão viva da NFS-e (`Modules/NFSe`) ainda não chama o cálculo; IRRF; INSS 11%; exceção do DARF via Siafi; art. 30 § 2º (regime do tomador) — ver `_saida-23.md` da thread 23 do playbook Fiscal.

### R-NFSE-002 · Prestador no Simples não retém PIS/COFINS/CSLL

```gherkin
Dado prestador optante pelo Simples (regime simples ou mei em nfe_business_configs)
Quando calculo a NFS-e pra tomador PJ
Então nenhuma retenção federal e o aviso "Confirme com o contador" (bateria C22)
E mudando o regime para lucro presumido/real a retenção aparece
E sem regime configurado não há palpite: nenhuma retenção e aviso de configuração
E o regime lido é o do próprio tenant (98 Simples não herda o normal do 99)
```

**Contrato:** Lei 10.833/2003, art. 32, III · regime em `nfe_business_configs.regime`.
**Implementação:** `Modules/NfeBrasil/Services/RetencoesNfseService.php`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfseRetencoesTest.php` (lane `nfebrasil-pest`).

### R-NFSE-003 · A retenção marcada na nota vai para a DPS e fica gravada

```gherkin
Dado prestador em lucro presumido, tomador com CNPJ e a marcação "serviço sujeito à retenção" ligada
Quando emito uma NFS-e de 1.500 reais
Então a nfse_emissoes grava PIS 9,75, COFINS 45,00 e CSLL 15,00
E a DPS leva tribFed.piscofins.tpRetPisCofins = 3 e vRetCSLL = 69,75 (a soma), sem usar vPis/vCofins
E desmarcado não há grupo federal e a chave de idempotência é a de antes
E prestador no Simples, valor dispensado ou tomador sem CNPJ não retêm nem levam o grupo
```

**Contrato:** Lei 10.833/2003 arts. 30–32 · NT SE/CGNFS-e nº 007 (grupo `tribFed`, lida em 2026-10-13) · decisão [W] 2026-10-13 ("Marcação na nota").
**Implementação:** `Modules/NFSe/Services/NfseEmissaoService.php` (`montarPayload`, `emitir`) · `Modules/NFSe/DTO/NfseEmissaoPayload.php` · `Modules/NFSe/Adapters/SnNfseAdapter.php` (`buildDps`) · migração `Modules/NfeBrasil/Database/Migrations/2026_10_13_000001_add_retencoes_federais_to_nfse_emissoes.php` · `resources/js/Pages/Nfse/Emitir.tsx` (marcação).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfseRetencoesDpsTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** o envio em XML assinado é o R-NFSE-005; o CST 01 do grupo `piscofins` é suposição a confirmar com o contador; IRRF e INSS; título no Financeiro com o valor líquido.

### R-NFSE-005 · A DPS vai em XML assinado, válido nos XSDs oficiais, gzip+base64 em POST /nfse

```gherkin
Dado uma NFS-e com prestador (CNPJ e regime configurados), tomador e certificado A1
Quando o adapter emite
Então a DPS sai em XML no namespace http://www.sped.fazenda.gov.br/nfse, versao 1.01
E o XML é válido contra os XSDs oficiais v1.01 versionados no repositório
E o infDPS é assinado (XMLDSig, referência pelo Id) e a assinatura confere
E o Id tem 45 posições: município (7) + tipo de inscrição (1) + CNPJ (14) + série (5) + nDPS (15)
E o corpo do POST /nfse é {"dpsXmlGZipB64": <DPS assinada em GZip + Base64>}, com mTLS do mesmo certificado
E o retorno grava a chaveAcesso como protocolo e o nNFSe como número
E erro da Sefin (erros[{Codigo, Descricao}]) vira NfseException com o código
E sem regime, sem CNPJ do prestador ou com nDPS fora do leiaute a emissão falha visível
```

**Contrato:** esquemas XSD oficiais v1.01 (`nfse-esquemas_xsd-v1-01-20260209`, gov.br/nfse, baixados em 2026-10-13) · Manual dos Contribuintes — API do Emissor Público Nacional (v1.2) · NT SE/CGNFS-e nº 007.
**Implementação:** `Modules/NfeBrasil/Services/Nfse/DpsXml.php` (serializar, assinar, empacotar) · `Modules/NFSe/Adapters/SnNfseAdapter.php` (`buildDps`, `emitir`) · `Modules/NFSe/DTO/NfseEmissaoPayload.php` (`serieDps`, `regimePrestador`, `comNumeroDps`) · `Modules/NFSe/Services/NfseEmissaoService.php` (nDPS = id da emissão) · `Modules/NFSe/Config/config.php` (`/SefinNacional`, `assinatura_algoritmo`) · XSDs em `Modules/NfeBrasil/Resources/schemas/nfse/v1.01/`.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/NfseDpsXmlTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** nenhuma DPS foi enviada à Sefin de verdade (falta certificado A1 em homologação); o algoritmo da assinatura (SHA-256, em `nfse.assinatura_algoritmo` no config do módulo — sem variável de ambiente) só se confirma no 1º envio; o desdobro do cTribNac passou a vir da tabela nacional (R-NFSE-006); pAliq omitido (município do Sistema Nacional parametriza); consultar() e cancelar() deste adapter seguem no formato antigo.

### R-NFSE-006 · cTribNac vem da tabela nacional; com vários desdobros, quem emite escolhe

```gherkin
Dado a lista de serviços nacional do Anexo B v1.01 (338 códigos, 201 subitens, 62 com mais de um desdobro)
Quando emito uma NFS-e do item 1.05 (um desdobro só)
Então o cTribNac é 010501 sem ninguém escolher
E no item 7.10 (071001 vias e logradouros · 071002 imóveis, chaminés, piscinas) a tela pede o desdobro
E sem escolha, ou com código de outro item, o formulário recusa e a emissão falha dizendo as opções
E item fora da lista nacional é recusado no formulário
E o cTribNac usado fica gravado na nfse_emissoes
```

**Contrato:** Anexo B oficial do Sistema Nacional NFS-e (`anexo_b-nbs2-lista_servico_nacional-snnfse-v1-01-20260122.xlsx`, aba `LISTA.SERV.NAC.`, sha256 `e74b0be8…1b1a`) · XSD v1.01 `TCCServ/cTribNac` (item 2 + subitem 2 + desdobro 2).
**Implementação:** `scripts/fiscal/lista-servico-nacional.py` (gera) · `Modules/NfeBrasil/Resources/data/nfse/lista-servico-nacional-v1.01.json` · `Modules/NfeBrasil/Services/Nfse/ListaServicoNacional.php` · `Modules/NFSe/Adapters/SnNfseAdapter.php` · `Modules/NFSe/Http/Requests/StoreNfseRequest.php` · `Modules/NFSe/Services/NfseEmissaoService.php` · migração `2026_10_13_000002_add_ctrib_nac_to_nfse_emissoes` · `resources/js/Pages/Nfse/Emitir.tsx` (seletor).
**Testado em:** `Modules/NfeBrasil/Tests/Feature/ListaServicoNacionalTest.php` (lane `nfebrasil-pest`).
**Fora deste item:** o código NBS (`cNBS`, opcional na DPS) não é enviado; notas anteriores têm `ctrib_nac` nulo.

## 4. Decisões pendentes

- [ ] Lib base: `eduardokum/sped-nfe` (recomendado) vs alternativa? — provavelmente sped-nfe (já maduro, BR)
- [ ] DANFE PDF: `eduardokum/sped-da` ou customizar? — sped-da pra MVP
- [ ] CBS/IBS: começar schema flexível agora ou esperar consolidação 2027?
- [ ] Cert A1 gerenciado: oimpresso renova/armazena ou só armazena? Implicação legal séria
- [ ] Manifestação automática (Confirmação) por padrão ou apenas manual?
- [ ] Contingência EPEC requer cert separado (homologação SEFAZ-AN)? Validar com contador

## 5. Referências cruzadas

- **Auto-memória:** `reference_ultimatepos_integracao.md`, `reference_db_schema.md`, `cliente_rotalivre.md`
- **Origem:** `_Ideias/NfeBrasil/evidencias/conversa-claude-2026-04-mobile.md`
- **Design:** `_DesignSystem/adr/ui/0006-padrao-tela-operacional.md`
- **Módulos relacionados:** [Financeiro](../Financeiro/), [RecurringBilling](../RecurringBilling/)

---

## 6. Backlog vindo do Capterra-Inventário (range 040+)

> Tasks geradas pela skill `comparativo-do-modulo` em **2026-05-06**. Range 040-049 reservado pra essa origem.
> Detalhes em [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md). Doutrina: [ADR 0089](../../decisions/0089-capterra-driven-module-evolution.md).
> **Onda 1 — tasks aprovadas por Wagner em 2026-05-06**: #1 (US-NFE-040) e #2 (US-NFE-041) abaixo. Demais Onda 1 (NfeService base, MotorTributario, DANFE) ficam em próximo lote após validação destas.

### US-NFE-040 · [Epic] Foundation domínio NFe — migrations + models + composer

> owner: — · priority: p0 · estimate: 16h · status: done · type: epic · origin: capterra-inventario-2026-05-06 · capacidade: #7-multi-tenant
> blocked_by: —
> bloqueia: US-NFE-041, US-NFE-001..011 (todas as US existentes do módulo dependem desta foundation)

**Implementado em:** `Modules/NfeBrasil/Database/Migrations/2026_05_06_002000_create_nfe_certificados_table.php` · `Modules/NfeBrasil/Database/Migrations/2026_05_06_002001_create_nfe_emissoes_table.php` · `Modules/NfeBrasil/Models/NfeEmissao.php` · verificado@85cf3e5 (2026-07-01) — foundation NFe: 4 migrations (certificados/emissoes/eventos/inutilizacoes) + models Eloquent com BusinessScope + composer sped-nfe ^5.2/sped-da ^1.1

**Contexto.** CAPTERRA-INVENTARIO classificou ❌ AUSENTE — módulo é scaffold puro sem domínio. Epic bloqueador absoluto: nada emite, cancela, inutiliza ou consulta sem essas tabelas + libs.

**Acceptance criteria:**
- [ ] `composer require nfephp-org/sped-nfe nfephp-org/sped-da` registrados em `composer.json` + lock
- [ ] Migration `nfe_certificados` — id, business_id (int unsigned, ADR tech/0008), uuid (path), cnpj_titular, valido_ate, encrypted_password, ativo, timestamps. UNIQUE parcial (só 1 ativo por business)
- [ ] Migration `nfe_emissoes` — id, business_id, transaction_id (UPos transactions, int unsigned FK), modelo enum(55,65,67), serie, numero, chave_44, status enum(pendente, autorizada, rejeitada, cancelada, denegada), cstat, motivo, xml_path, danfe_path, valor_total, emitido_em, timestamps. UNIQUE(business_id, modelo, serie, numero); UNIQUE(business_id, transaction_id) — idempotência reemissão
- [ ] Migration `nfe_eventos` — id, business_id, emissao_id (FK nfe_emissoes), tipo (110110 CCe, 110111 cancelamento, 210200 confirmação), justificativa, status, cstat_evento, payload_json, created_at (append-only)
- [ ] Migration `nfe_inutilizacoes` — id, business_id, modelo, serie, numero_de, numero_ate, justificativa, cstat, autorizada_em, timestamps
- [ ] Models Eloquent: `NfeCertificado`, `NfeEmissao`, `NfeEvento`, `NfeInutilizacao` com relacionamentos + `BusinessScope` global (multi-tenant — skill multi-tenant-patterns)
- [ ] Tipos: business_id `unsignedInteger` (ADR tech/0008 — UltimatePOS legado é int unsigned)
- [ ] Migrations idempotentes (`Schema::hasTable` guard, ADR tech/0008)
- [ ] Tests Pest mínimos: criar emissão, transição de status, evento append-only, isolamento multi-tenant
- [ ] phpunit.xml registra `Modules/NfeBrasil/Tests/Feature` (já feito 2026-05-06) + `Tests/Unit` se houver

**Bloqueia:** US-NFE-001..011 + US-NFE-041 + Onda 2 inteira do CAPTERRA-INVENTARIO.

**Referências:**
- ADR tech/0008 (FK type-mismatch UltimatePOS legado)
- Skill `multi-tenant-patterns`
- CAPTERRA-INVENTARIO #1, #7

### US-NFE-041 · CertificadoService + storage encrypted + UI admin

> owner: — · priority: p0 · estimate: 12h · status: done · type: story · origin: capterra-inventario-2026-05-06 · capacidade: #1
> blocked_by: US-NFE-040

**Implementado em:** `Modules/NfeBrasil/Services/CertificadoService.php` · `Modules/NfeBrasil/Http/Controllers/CertificadoController.php` · `Modules/NfeBrasil/Http/Requests/UploadCertificadoRequest.php` · `resources/js/Pages/Fiscal/Config.tsx` · verificado@85cf3e5 (2026-07-01) — UI unificada em Fiscal/Config.tsx (tela própria removida na unificação 2026-05-27; GET legacy → 302 /fiscal/config)

**Contexto.** CAPTERRA-INVENTARIO #1 ❌ AUSENTE — sem certificado válido, **nada** funciona (NFe/NFC-e/CCe/cancelamento todos exigem assinatura A1). Cert A1 (.pfx) é arquivo binário com senha; vazamento = catástrofe legal (terceiro pode emitir notas em nome do tenant). Storage **deve** ser encrypted-at-rest e a senha **nunca** em texto. Substitui parte da SPEC US-NFE-001 — implementação real desta capacidade.

**Acceptance criteria:**
- [ ] `Modules/NfeBrasil/Services/CertificadoService.php` com:
  - `validar(string $pfxBase64, string $senha): array` — `openssl_pkcs12_read`, extrai CN (CNPJ), valida `not_after > now()`
  - `salvar(int $businessId, string $pfxBase64, string $senha): NfeCertificado` — encrypt-at-rest do .pfx + `Crypt::encryptString` da senha + desativa cert anterior
  - `carregarParaSefaz(int $businessId): array` — descriptografa pra lib sped-nfe (in-memory)
  - `verificarVencimento(int $businessId): ?int` — dias restantes; null se sem cert
- [ ] Storage path: `storage/app/nfe-brasil/{business_id}/cert/{uuid}.pfx.enc` — encrypt via `Crypt::encrypt(file_get_contents($pfx))`
- [ ] Senha encrypted em coluna `nfe_certificados.encrypted_password` via `Crypt::encryptString` — **nunca loga em audit log**
- [ ] FormRequest `UploadCertificadoRequest` aceita `.pfx` ≤ 100KB + `senha` (required|string|max:80)
- [ ] Endpoint `POST /nfe-brasil/configuracao/certificado` chama `CertificadoController@upload` com permissão `nfe.configuracao.manage`
- [ ] Spatie Activity Log registra upload (sem incluir senha nem path do arquivo)
- [ ] Tela Inertia `resources/js/Pages/NfeBrasil/Configuracao/Certificado.tsx` com upload + status (CNPJ titular, vence em X dias) + badge sidebar amarelo ≤30d / vermelho se vencido
- [ ] Tests Pest: upload válido + cert expirado rejeitado + CNPJ ≠ business rejeitado + senha errada rejeita + isolamento multi-tenant + vencimento próximo

**Diferencial vs concorrentes:** TecnoSpeed/PlugNotas/FocusNFE armazenam cert no servidor deles (risco de vazamento centralizado). oimpresso armazena por business com chave Laravel rotacionável.

**Referências:**
- CAPTERRA-INVENTARIO #1
- SPEC US-NFE-001 (substituída — depende de US-NFE-040 pra `nfe_certificados` table)

---

## 7. Caso Gold Comunicação Visual (sprint `Gold-Reativacao`)

> Origem: sessão 2026-05-09 — recuperação cliente Gold antes da migração pra Mubsys.
> Decisões: [ADR 0115](../../decisions/0115-recuperacao-cliente-gold-via-bundle-oimpresso.md) (estratégia comercial) + [ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md) (pivot escopo técnico).
>
> **Status do bundle 2026-05-09:** discovery (042) + manifestação (049-053) ATIVOS. Pacote emissão (043-048) **dormente** aguardando US-NFE-042 confirmar se Gold também emite NF-e 55 (orientação Wagner: "guarde inativo").

### US-NFE-042 · Discovery técnico instalação Gold Comunicação Visual

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — discovery on-prem Gold não executado; documento `memory/clientes/gold/discovery-*.md` (gitignored PII) não existe. Humano-limitado (audit da instalação do cliente).

Audit técnico da instalação on-prem do cliente Gold pra identificar delta com `main` antes do upgrade. Cliente está migrando pra Mubsys; janela curta. **Determina se Gold é só destinatária (manifestação) ou também emite NF-e 55** — define ativação ou não das US-NFE-043..048 dormentes.

**Acceptance criteria:**
- Documento `memory/clientes/gold/discovery-2026-MM-DD.md` (gitignored — PII)
- Versão atual oimpresso identificada
- Banco MySQL/MariaDB versão + tamanho dump + tabelas core listadas
- Cert A1 .pfx local? Storage path?
- IE habilitada SEFAZ + regime tributário identificado
- Conectividade SEFAZ outbound testada (firewall não bloqueia)
- **Decisão dual:** Gold é só destinatária OU também emite NF-e 55 (vendas B2B)?
- Volume estimado NF-e RECEBIDAS / EMITIDAS por mês
- Decisão GO/NO-GO pro upgrade (Fase 3) + se reativa US-NFE-043..048

**Refs:** ADR 0115 + ADR 0116 · Runbook `memory/requisitos/Officeimpresso/RUNBOOK-recuperacao-on-prem.md` Fase 1

### US-NFE-043 · Proposta comercial Gold — diferenciais vs Mubsys + pricing on-prem · 🔒 DORMENTE

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 3h · status: blocked · type: story
> blocked_by: US-NFE-042

**Implementado em:** _pendente_ — 🔒 DORMENTE (blocked_by US-NFE-042, ADR 0116). Proposta comercial Gold não construída; reativa só se discovery confirmar emissão NF-e 55.

⚠️ **Status `blocked` por orientação Wagner 2026-05-09 ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).** Escopo focado em emissão NF-e 55. Reativa apenas se discovery (US-NFE-042) confirmar que Gold também emite. A proposta da fase manifestação é tratada nas US-049..053 + apêndice no template `PROPOSTA-COMERCIAL-vs-mubsys.md`.

Construir proposta comercial pra Gold ancorada nos diferenciais oimpresso vs Mubsys ([comparativo Capterra 2026-04-25](../../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md)).

**Acceptance criteria:** ver bloco original em comentário do MCP (pricing on-prem TBD por Wagner; cláusula cert A1; SLA suporte; 1 página executiva + anexo técnico).

**Refs:** ADR 0115 · ADR 0026 (posicionamento) · README NfeBrasil pricing

### US-NFE-044 · Upgrade plataforma on-prem Gold pra versão atual com NfeBrasil · 🔒 DORMENTE

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 6h · status: blocked · type: story
> blocked_by: US-NFE-043

**Implementado em:** _pendente_ — 🔒 DORMENTE (blocked_by US-NFE-043, ADR 0116). Upgrade on-prem da instalação Gold pra `main` não executado (produção do cliente, humano-limitado, risco ALTO).

⚠️ **Status `blocked` ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).** Parte do upgrade é reaproveitada pela manifestação (US-NFE-049..053 dependem de upgrade base também). Mantida dormente — pode ser parcialmente reativada quando manifestação exigir.

Trazer instalação Gold da versão atual dela pra `main` do oimpresso, incluindo `Modules/NfeBrasil` e `Modules/Officeimpresso`. Risco ALTO — produção do cliente.

**Acceptance criteria:** backup completo, janela combinada, `composer install` sem `--no-dev`, `php artisan migrate`, `npm ci && npm run build`, triggers MySQL imutabilidade preservados, smoke pós-upgrade. Detalhe original em comentário MCP.

**Refs:** ADR 0115 · Runbook Fase 3 · auto-mem `reference_diff_3_7_vs_6_7_officeimpresso.md`

### US-NFE-045 · Configuração fiscal NfeBrasil Gold (cert + IE + regime + template SP) · 🔒 DORMENTE

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 3h · status: blocked · type: story
> blocked_by: US-NFE-044

**Implementado em:** _pendente_ — 🔒 DORMENTE (blocked_by US-NFE-044, ADR 0116). Configuração fiscal (cert+IE+regime) do business Gold não realizada; os templates `industria-grafica-*-sp.php` existem mas a US é o ato de configurar o cliente pós-upgrade (humano-limitado).

⚠️ **Status `blocked` ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).** Cert A1 + IE também são pré-requisitos de manifestação (assinatura de evento). Parte reaproveitada por US-NFE-049..053.

Configurar emissão fiscal NfeBrasil pra business Gold após upgrade. Detalhe em comentário MCP.

**Refs:** ADR 0115 · Runbook Fase 4 · `Modules/NfeBrasil/Resources/templates/industria-grafica-presumido-sp.php`

### US-NFE-046 · Smoke fiscal homologação SEFAZ-SP biz=Gold (1ª NF-e 55 cstat 100) · 🔒 DORMENTE

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 2h · status: blocked · type: story
> blocked_by: US-NFE-045

**Implementado em:** _pendente_ — 🔒 DORMENTE (blocked_by US-NFE-045, ADR 0116). Smoke 1ª NF-e 55 homologação SEFAZ-SP biz=Gold não executado (round-trip SEFAZ real, humano-limitado).

⚠️ **Status `blocked` ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).** Smoke específico de emissão NF-e 55. Smoke equivalente da manifestação fica em US-NFE-053 (eventos 210/220).

Emitir 1ª NF-e modelo 55 em homologação SEFAZ-SP usando cert + IE da Gold. Detalhe em comentário MCP.

**Refs:** ADR 0115 · Runbook Fase 5 · auto-mem `runbook_smoke_sefaz_biz1.md`

### US-NFE-047 · Treinamento operadora Gold + cutover NF-e produção + canary 7d · 🔒 DORMENTE

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 3h · status: blocked · type: story
> blocked_by: US-NFE-046

**Implementado em:** _pendente_ — 🔒 DORMENTE (blocked_by US-NFE-046, ADR 0116). Treinamento operadora Gold + cutover prod + canary 7d não realizado (relógio do mundo real, humano-limitado).

⚠️ **Status `blocked` ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).** Treinamento e cutover da fase manifestação ficam em US-NFE-052/053.

Treinar operadora Gold + cutover NF-e prod + canary 7d. Detalhe em comentário MCP.

**Refs:** ADR 0115 · Runbook Fases 5-6

### US-NFE-048 · Refinar runbook on-prem reutilizável pós-Gold (Trilha 1 dormentes) · 🔒 DORMENTE

> owner: wagner · sprint: Gold-Reativacao · priority: p2 · estimate: 2h · status: blocked · type: story
> blocked_by: US-NFE-047

**Implementado em:** _pendente_ — 🔒 DORMENTE (blocked_by US-NFE-047, ADR 0116). Refino do runbook on-prem pós-Gold não feito; depende de concluir o caso real primeiro.

⚠️ **Status `blocked` ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).** Mantida dormente até concluir caminho real (manifestação 049..053). Refinamento pós-Gold inclui aprendizados das duas faces (emissão se reativada + manifestação).

Após o caso Gold concluir, refinar runbook on-prem com aprendizados reais. Detalhe em comentário MCP.

**Refs:** ADR 0115 · Runbook stub · `_Roadmap_Faturamento.md` Trilha 1

---

### US-NFE-049 · Migrar models/service legados Manifesto/ItemDfe/DFeService pra `Modules/NfeBrasil/`

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 4h · status: done · type: story
> blocked_by: US-NFE-042
> code-complete: 2026-05-09 (PR pendente) — 4 migrations + 4 models + legado removido

**Implementado em:** `Modules/NfeBrasil/Models/NfeDfeRecebido.php` · `Modules/NfeBrasil/Models/NfeDfeItem.php` · verificado@3b425d8 (2026-06-24) — models legados Manifesto/ItemDfe migrados pro padrão Modules/ multi-tenant

Resgatar arquivos legados UltimatePOS órfãos e migrar pro padrão `Modules/NfeBrasil/`:
- `app/Manifesto.php` → `Modules/NfeBrasil/Models/NfeDfeRecebido.php`
- `app/ItemDfe.php` → `Modules/NfeBrasil/Models/NfeDfeItem.php`
- `app/ManifestoLimite.php` → review (pode ser deletado se não fizer sentido no novo modelo)
- `app/Services/DFeService.php` → `Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeServiceLegacy.php` (referência)
- `app/Http/Controllers/ManifestoController.php` → review pra reaproveitar lógica

**Acceptance criteria:**
- Models novos com `BusinessScope` global multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- Migration `nfe_dfe_recebidos` (id, business_id, chave_44, cnpj_emitente, valor_total, data_emissao, nsu, xml_path, status_manifestacao, manifestado_em, prazo_confirmacao_em, timestamps) — UNIQUE(business_id, chave_44)
- Migration `nfe_dfe_itens` (id, business_id, dfe_recebido_id, produto_id_match nullable, ncm, descricao, quantidade, valor)
- Models antigos `App\Manifesto`/`App\ItemDfe`/`App\ManifestoLimite` removidos do `app/` (com referência em CHANGELOG)
- ManifestoController legado `app/Http/Controllers/ManifestoController.php` removido
- Tests Pest: criar dfe_recebido + isolamento multi-tenant + UNIQUE chave por business

**Refs:** ADR 0116 · models legados em [`app/Manifesto.php`](../../../app/Manifesto.php) etc

### US-NFE-050 · ManifestacaoService — eventos 210/220/230/240 via sped-nfe

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 4h · status: done · type: story
> blocked_by: US-NFE-049
> code-complete: 2026-05-09 (PR pendente) — service + 4 testes Pest (idempotência, just ≥15, 4 eventos)

**Implementado em:** `Modules/NfeBrasil/Services/Manifestacao/ManifestacaoService.php` · verificado@3b425d8 (2026-06-24) — eventos 210/220/230/240 via sped-nfe (cienciar/confirmar/desconhecer/naoRealizada)

**Testado em:** `Modules/NfeBrasil/Tests/Feature/ManifestacaoServiceTest.php` (covers US-NFE-050)

> Os 4 eventos, a justificativa ≥15 chars (NT 2014.002) e a idempotência já eram exercitados por
> esse arquivo desde 2026-05; faltava a declaração `@covers-us US-NFE-050` — adicionada em
> 2026-07-28 pela corrida do [SDD](SDD-emissao-fiscal-v1.0.md). Nenhum caso novo foi escrito: a
> US-NFE-008 é a narrativa do operador e a US-NFE-050 é o service que a entrega, e o mesmo teste
> cobre as duas.

Implementar `Modules/NfeBrasil/Services/Manifestacao/ManifestacaoService.php` envolvendo `eduardokum/sped-nfe::Tools::sefazManifesta($chave, $tpEvento, $xJust='')`.

**Acceptance criteria:**
- Métodos: `cienciar(NfeDfeRecebido $dfe)`, `confirmar(NfeDfeRecebido $dfe)`, `desconhecer(NfeDfeRecebido $dfe)`, `naoRealizada(NfeDfeRecebido $dfe, string $justificativa)`
- 4 eventos: tpEvento 210210 (Ciência), 210200 (Confirmação), 210220 (Desconhecimento), 210240 (Não Realizada)
- Cada manifestação cria registro em `nfe_eventos` (já existe — `NfeEvento.php` Module/NfeBrasil/Models)
- Atualiza `nfe_dfe_recebidos.status_manifestacao` + `manifestado_em`
- Audit log Spatie Activity (causer + chave + tpEvento + cstat)
- Idempotência: 2ª chamada do mesmo tpEvento na mesma chave → no-op com warning log
- Justificativa obrigatória pra Não Realizada (≥15 chars NT 2014.002)
- Carrega cert via `Modules/NfeBrasil/Services/CertificadoService.php` (já existente)
- Tests Pest: 4 eventos + idempotência + justificativa obrigatória + isolamento multi-tenant

**Refs:** ADR 0116 · `Modules/NfeBrasil/Services/CertificadoService.php` · `eduardokum/sped-nfe::Tools` Make.php

### US-NFE-051 · DistribuicaoDfeService + Job agendado puxa XMLs por NSU

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 5h · status: done · type: story
> blocked_by: US-NFE-049
> code-complete: 2026-05-09 (PR pendente) — service + Job + Command artisan + Kernel schedule 06:15 + 4 testes Pest

**Implementado em:** `Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeService.php` · `Modules/NfeBrasil/Jobs/BuscarDfesRecebidosJob.php` · verificado@3b425d8 (2026-06-24) — sefazDistDFe por NSU + Job agendado diário

Implementar `Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeService.php` envolvendo `Tools::sefazDistDFe($lastNSU)` + Job agendado.

**2026-10-26 · R-NFE-041/042:** o job falhava todo dia desde 2026-07-03 (69 falhas, medido no #9833) porque o CNPJ vinha só do `tax_number_1`, vazio no biz=1. Agora o CNPJ segue titular → `business.cnpj` → `tax_number_1` e a falha definitiva vira alerta no cockpit Fiscal.

**Acceptance criteria:**
- Tabela `nfe_dfe_nsu_state` (business_id, last_nsu unsignedBigInteger, ultimo_check_em) — guarda cursor por business
- Service `puxarLote(int $businessId)`:
  - Lê last_nsu
  - Chama `Tools::sefazDistDFe($lastNSU)` com cert do business
  - Parseia retorno XML (lote pode ter até 50 docs)
  - Pra cada NF-e nova, persiste `nfe_dfe_recebidos` + items + storage XML
  - Atualiza last_nsu
  - Throttle: 1 chamada SEFAZ a cada 5min por business (cooldown SEFAZ NT)
- Job `BuscarDfesRecebidosJob` em `Modules/NfeBrasil/Jobs/`:
  - Roda diário 06:00 BRT em `app/Console/Kernel.php` (igual `jana:health-check`)
  - Itera businesses ativos com cert válido
  - Dispara `puxarLote` em queue retry exponencial
- Audit log: NSUs processados + count XMLs novos
- Tests Pest: lote vazio (no-op), lote com 3 docs, throttle respeitado, cert ausente skipa, isolamento multi-tenant

**Refs:** ADR 0116 · `eduardokum/sped-nfe::Tools::sefazDistDFe`

### US-NFE-052 · UI listar XMLs recebidos + 4 botões manifestar + alerta prazo 180d

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 4h · status: done · type: story
> blocked_by: US-NFE-050, US-NFE-051
> code-complete: 2026-05-09 (PR pendente) — RUNBOOK + visual-comparison approved + ManifestacaoController + Page Inertia + 3 LinkedApps + 7 testes Pest

**Implementado em:** `Modules/NfeBrasil/Http/Controllers/ManifestacaoController.php` · `resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx` · `Modules/NfeBrasil/Tests/Feature/ManifestacaoControllerTest.php` · verificado@3b425d8 (2026-06-24) — UI listagem XMLs recebidos + 4 botões manifestar + alerta prazo 180d

**Testado em:** `Modules/NfeBrasil/Tests/Feature/ManifestacaoContratoTest.php` (covers US-NFE-052)

> O `ManifestacaoControllerTest.php` também exercita esta superfície, mas declara `@covers-us`
> apenas da US-NFE-008 — **não** foi reivindicado aqui: citar teste que não declara a cobertura
> deixa o `anchor-lint` acusando *"teste existe mas não declara @covers-us"*, e reivindicar
> cobertura que o arquivo não assume é o falso-verde que este processo existe pra matar.
>
> Contrato da tela em [`Index.casos.md`](../../../resources/js/Pages/NfeBrasil/Manifestacao/Index.casos.md)
> (`UC-NFMA-01..06`). ⚠️ O `ManifestacaoContratoTest` **não** está na allowlist da lane required
> `PHP / Pest (NfeBrasil · MySQL)` — ela é catraca por prova verde e a corrida que o escreveu não
> roda teste ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)). Ratchet-up
> proposto em [SDD §8.3](SDD-emissao-fiscal-v1.0.md).

Página Inertia `resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx` listando NFes recebidas com ações de manifestação.

**Acceptance criteria:**
- Tabela com colunas: data emissão, CNPJ emitente, valor, NSU, status manifestação, **prazo confirmação** (countdown 180d), ações
- Filtros: status (todos / pendente Ciência / pendente Confirmação / manifestados / próximo do prazo)
- Linhas com badge:
  - Verde: manifestado
  - Amarelo: ≤ 30 dias do prazo
  - Vermelho: ≤ 7 dias do prazo OU já vencido
- 4 botões por linha conforme estado: [Ciência] [Confirmação] [Desconhecer] [Não Realizada]
- Modal "Não Realizada" exige justificativa (textarea ≥15 chars)
- Botão "Baixar XML" + "Visualizar DANFE PDF" (gera via `eduardokum/sped-da`)
- Permissão `nfe.manifestacao.view` + `nfe.manifestacao.manage`
- Bulk action: "Confirmar selecionadas" (pra operadora bater 50 notas em 1 clique)
- Tests Pest Browser: listagem renderiza, click confirma, prazo countdown correto

**Refs:** ADR 0116 · padrão Cockpit V2 [ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md) · skill `ui-component-creator`

### US-NFE-053 · Smoke homologação SEFAZ-SP eventos 210/220 biz=Gold

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: US-NFE-052

**Implementado em:** _pendente_ — smoke real eventos 210/220 homologação SEFAZ-SP biz=Gold não executado (round-trip SEFAZ + cert Gold, humano-limitado; evidência seria session log inexistente).

Smoke real em ambiente homologação SEFAZ-SP usando cert + CNPJ Gold. Análogo a US-NFE-046 mas pra eventos de manifestação.

**Acceptance criteria:**
- `NFE_AMBIENTE=2` (homologação)
- Job `BuscarDfesRecebidosJob` rodado manualmente → puxa lote NSU SEFAZ
- Pelo menos 1 NF-e teste do ambiente nacional homol aparece na UI
- Manifestar Ciência (210) → cstat 135 retornado
- Manifestar Confirmação (220) → cstat 135 retornado
- Eventos persistidos em `nfe_eventos` com payload completo
- XMLs de evento salvos em storage
- Sem erros em `storage/logs/laravel.log`
- Tempo de manifestação registrado (baseline)

**Refs:** ADR 0116 · runbook biz=1 SEFAZ-SC análogo (auto-mem `runbook_smoke_sefaz_biz1.md`)

### US-NFE-054 · Smoke real homologação SEFAZ-SC biz=1 — 1ª NFC-e cstat 100 (Goal #1 CYCLE-03)

> owner: wagner · priority: p1 · estimate: 0.5h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — smoke real SEFAZ-SC biz=1 (1ª NFC-e cstat 100) não executado; passos são "Wagner faz" (POS → finalizar → cstat 100). Humano-limitado, evidência seria row em `nfe_emissoes` + Goal CYCLE-03 track, ausente.

Bate o **Goal #1 do CYCLE-03** ("smoke fiscal SEFAZ-SC homologação biz=1, 1ª NFC-e real cstat 100"). Pipeline US-NFE-002 server-side já fechado em main; biz=1 (WR2 Sistemas, Tubarão/SC) já está armada — confirmado via SSH 2026-05-09:

**Estado pré-validado (não tocar):**
- `business.cnpj`=36.613.150/0001-18, `ncm_padrao`=49111090, `ambiente`=2, `ultimo_numero_nfce`=0 <!-- pii-allowlist: fixture biz=1 WR2 Sistemas (própria empresa Wagner, ADR 0101 biz=1 nunca cliente) — homologação SEFAZ-SC -->
- `nfe_certificados.ativo`=1, `valido_ate`=2026-08-06
- `nfe_business_configs`: `regime`=simples, `cfop`=5102, `csosn`=102, `auto_emission_enabled`=1
- `.env` Hostinger: `NFEBRASIL_AUTO_EMISSION_NFCE=true`
- ⚠️ `nfe_certificados.cnpj_titular` vazio mas NÃO bloqueante (NfeService:601/693 lê do `business->cnpj`)

**Passos (Wagner faz):**
1. Login `oimpresso.com` em **biz=1 (WR2 Sistemas)** — NÃO ROTA LIVRE biz=4
2. `/sells/create` (POS) → 1 produto qualquer R$ [redacted Tier 0]
3. Cliente: "Consumidor final" (sem CPF, NFC-e B2C aceita anônimo)
4. Pagamento: dinheiro
5. Finalizar (status=final, payment_status=paid)
6. Anotar `transaction_id` (recibo ou listagem `/sells`)
7. Passar `transaction_id` pro Claude OU navegar `/nfe-brasil/transactions/{tx}/status` (Page Inertia polla 2s × 30)

**DoD:**
- [ ] 1 row em `nfe_emissoes WHERE business_id=1 AND modelo=65` com:
  - `cstat=100` ("Autorizado o uso da NF-e")
  - `status='autorizada'`
  - `chave_44` preenchida (44 dígitos)
  - `numero=1` (primeira da série)
- [ ] `business.ultimo_numero_nfce` virou 1
- [ ] XML autorizado em `storage/app/nfe-brasil/1/notas/{serie}-1.xml`
- [ ] DANFE PDF em `storage/app/nfe-brasil/1/danfe/{chave_44}.pdf`
- [ ] Sem erros em `storage/logs/laravel.log` (filtrar `NFC-e` ou `NfeService`)
- [ ] Goal #1 CYCLE-03 marcado como atingido via `cycle-goals-track`

**Rollback se der ruim:** `sed -i "s/NFEBRASIL_AUTO_EMISSION_NFCE=true/=false/" .env` + `php artisan config:clear`. NFC-e cstat 100 emitida NUNCA deletar (fiscal append-only).

**Refs:**
- Runbook: [memory/requisitos/NfeBrasil/RUNBOOK-smoke-sefaz-biz1.md](../RUNBOOK-smoke-sefaz-biz1.md)
- ADR 0101 (biz=1 nunca cliente)
- Auto-mem: `project_nfebrasil_estado_2026_05_07.md`


### US-NFE-055 · Estabilizar 107 tests broken Modules/NfeBrasil aplicando dual-mode SQLite/MySQL

> owner: — · priority: p2 · estimate: 8h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — estabilização da suite (>=95% verde MySQL local) não concluída; DoD exige rodar Pest no CT 100 (não verificável só por inspeção). US-irmãs do mesmo batch (056/057) comprovadamente não aplicadas.

Aplicar o pattern dual-mode SQLite/MySQL validado em PR #486 (`fix(test): ImportRegrasCsvServiceTest`) nos demais tests Modules/NfeBrasil que falham em Pest local MySQL `oimpresso`.

## Contexto

PR #486 fixou `ImportRegrasCsvServiceTest` (3 tests `aplicar()`) — validado verde MySQL+SQLite. Ao rodar suite completa `vendor/bin/pest Modules/NfeBrasil/Tests --no-coverage` em Pest local MySQL, sessão 2026-05-10 catalogou: **107 failed, 63 skipped, 50 passed**.

Causa-raiz dominante: `Schema::dropIfExists(<tabela>)` em beforeEach colide com FKs de tabelas dependentes em prod schema MySQL.

## Tabelas-alvo (FK conflicts catalogados)

| Tabela | FK conflict | Ocorrências |
|---|---|---|
| `business` | `assets.business_id_foreign` | 10 |
| `nfe_certificados` | `nfse_provider_configs.cert_id_foreign` | 43 |
| `tax_rates` | `business.default_sales_tax_foreign` | 8 |
| `nfe_fiscal_rules` | `nfe_fr_tr_links_fiscal_rule_fk` | já fixado PR #486 |

## Test files afetados (group by failures count)

- `DanfeServiceTest.php` (21)
- `CertificadoControllerTest.php` (16)
- `NfeDomainModelsTest.php` (14)
- `CertificadoServiceTest.php` (13)
- `SyncFiscalRuleToTaxRateTest.php` (8)
- `MotorTributarioServiceTest.php` (8)
- `CertificadoFallbackLegadoTest.php` (6)
- `TributacaoControllerTest.php` (5)
- `EmitirNfceAoFinalizarVendaTest.php` (5) — pode ter outras causas (FK contacts)
- `EmitirNFeAoReceberPagamentoTest.php` (4) — FK rb_invoices.contact_id
- `NfeEmissaoControllerSerializeUrlsTest.php` (3) — toHaveKey misuse residual?
- `DistribuicaoDfeServiceTest.php` (3) — UniqueConstraintViolation em nfe_dfe_recebidos
- `HasArquivosTraitTest.php` (3)
- `DanfeServicePrefersArquivosTest.php` (3)
- `ImportRegrasCsvServiceTest.php` ✅ fixado PR #486

## Pattern de fix (PR #486 ref)

```php
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('<tabela>');
        Schema::create('<tabela>', function ($t) { /* schema mínimo */ });
    } elseif (Schema::hasTable('<tabela>')) {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        // Limpa rows whereIn business_id [1, 99] — cascateia em links
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
    Event::fake([...listeners bridge]);
});
```

## Acceptance criteria

- [ ] `vendor/bin/pest Modules/NfeBrasil/Tests --no-coverage` em MySQL local sai >=95% verde
- [ ] Cada test tocado também verde em SQLite `:memory:` (CI parity)
- [ ] CI Modules Pest job `Pest — Modules/NfeBrasil` verde após merge

## Constraints

- ADR 0101 — biz=1 (Wagner WR2), biz=99 (cross-tenant), nunca biz=4 (cliente ROTA LIVRE)
- Skill `commit-discipline` Tier A — ≤300 linhas por PR. Estimativa: dividir em 2-3 PRs por agrupamento (PR#1 Certificado*, PR#2 Danfe*, PR#3 outros)
- Não tocar lógica de produção — só test setup
- Pest local com MySQL real é gate verdadeiro (Wagner regra 2026-05-09)

## Artefatos referência

- PR #486: https://github.com/wagnerra23/oimpresso.com/pull/486
- Auto-mem `reference_pattern_dual_mode_sqlite_mysql_tests.md`
- Auto-mem `reference_listener_bridge_event_fake_pattern.md`
- Workflow CI: `.github/workflows/modules-pest.yml`
- ADR ARQ-0005 — bridge listener nfe_fiscal_rules → tax_rates

### US-NFE-056 · Continuação PR #453 — migrar configBusiness(4) → configBusiness(1) em MotorTributarioServiceTest

> owner: — · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — fix não aplicado: `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` ainda tem 8 calls `configBusiness(4)` (só 1 usa biz=1); a migração para biz=1 pedida na US não ocorreu.

PR #453 migrou business_id=4 → 1 em fixtures de tests, mas deixou pra trás `configBusiness(4)` em `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` linhas 150/174/189/203/228/259/273/286/307. Os calls correspondentes `(new MotorTributarioService)->calcular(...businessId: 1, ...)` já usam biz=1, criando mismatch — config inserido em biz=4 mas calcular busca em biz=1 → throws `TributacaoNaoConfiguradaException`.

**Sintoma:** test "Nível 4: defaults business aplicam quando NCM não tem regra" falha tanto em SQLite quanto MySQL com `TributacaoNaoConfiguradaException`. Confirmado em sessão 2026-05-10 (PR #489).

**Acceptance:**
- [ ] Migrar 9 calls `configBusiness(4, ...)` → `configBusiness(1, ...)` (preservando o test multi-tenant que LEGITIMAMENTE usa biz=4 vs biz=5 — linha 246)
- [ ] Validar `vendor/bin/pest Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php --no-coverage` em MySQL e SQLite
- [ ] Conformidade ADR 0101 (biz=1 default Wagner WR2)

**Refs:** PR #453 (migração biz=4→1 em fixtures), PR #489 (`claude/nfe-test-dual-mode-pr3`), ADR 0101

### US-NFE-057 · Schema drift — atualizar fixtures DanfeServicePrefersArquivosTest pós-remoção `arquivos.filename`

> owner: — · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — fix não aplicado: `Modules/NfeBrasil/Tests/Feature/DanfeServicePrefersArquivosTest.php` ainda referencia a coluna `filename` (2 ocorrências) que a US pede pra corrigir pós-remoção do schema.

Test `Modules/NfeBrasil/Tests/Feature/DanfeServicePrefersArquivosTest.php` insere row em `arquivos` com coluna `filename` que não existe mais no schema atual:

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'filename' in 'field list'
```

Coluna foi removida em alguma migração de Modules/Arquivos (provável Sprint 1 ADR 0123). 2 dos 3 tests do arquivo quebram em Pest local MySQL — confirmado em sessão 2026-05-10 (PR #488).

**Acceptance:**
- [ ] Identificar migration que removeu `filename` (ou se foi renomeada pra `original_name`)
- [ ] Atualizar fixtures linhas ~99 e ~165 do test
- [ ] Validar Pest local MySQL `oimpresso` verde
- [ ] Validar SQLite parity preservada

**Refs:** PR #488 (`claude/nfe-test-dual-mode-pr2`), ADR 0123 (Modules/Arquivos backbone), schema atual via `DESCRIBE arquivos`

### US-NFE-058 · Aplicar pattern dual-mode em batch restante — Emitir* + NfeEmissaoController + DistribuicaoDfe (4 files, ~15 fails)

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — batch dual-mode dos 4 test files não concluído/verificável; DoD exige Pest verde MySQL+SQLite no CT 100 (não provável só por inspeção de arquivo).

Continuação dos PRs #487-490 (sessão 2026-05-10). Pattern dual-mode SQLite/MySQL canônico (PR #486) ainda não aplicado em 4 test files do catálogo NfeBrasil:

| File | Falhas (catálogo) | Causa principal |
|---|---|---|
| `EmitirNfceAoFinalizarVendaTest.php` | 5 | FK contacts + outras (inclui PR #478 skip parcial) |
| `EmitirNFeAoReceberPagamentoTest.php` | 4 | FK `rb_invoices.contact_id` |
| `NfeEmissaoControllerSerializeUrlsTest.php` | 3 | dropIfExists `arquivos`+`nfe_emissoes` |
| `DistribuicaoDfeServiceTest.php` | 3 | UniqueConstraintViolation em `nfe_dfe_recebidos` |

**Acceptance:**
- [ ] Aplicar pattern §4 do `reference_tests_pest_canon.md` em cada arquivo
- [ ] Mapear FK reverses em INFORMATION_SCHEMA antes (decisão preserve+cleanup vs skip-em-MySQL)
- [ ] Validar Pest local MySQL + SQLite parity
- [ ] Cleanup conforme ADR 0101 (biz=1/99 only) — adaptar pra biz IDs específicos do test
- [ ] 1 PR por agrupamento (≤300 linhas) — provável PR #491 (Emitir*) + PR #492 (NfeEmissao + DistribuicaoDfe)

**Refs:** PRs #486-490 (pattern + 8 files já cobertos), ADR 0101, reference_tests_pest_canon.md §4 + §6 (FK map + dual-mode receita)

### US-NFE-059 · Smoke prod end-to-end auto-emissão NFe55 com cliente real que opt-in

> owner: wagner · priority: p2 · estimate: 4h · status: todo · type: story
> blocked_by: US-SELL-012

**Implementado em:** _pendente_ — smoke prod end-to-end não executado (checklist `CHECKLIST-US-NFE-059-smoke-prod.md` é preparo, boxes `[ ]`); humano-limitado (cliente real opt-in + SEFAZ-prod). Registra evidência só quando 1º cliente adotar.

**Contexto:** prod-evidence que estava no DoD original do US-RB-044 mas foi removida por ser premissa errada (cliente sem nota é caminho feliz). Após US-SELL-012 entregar gate por venda, ativar fluxo end-to-end com 1 cliente real que opt-in pra "Venda Com Nota Automática".

**Candidatos naturais:**
1. **Gold** (Comunicação Visual) — pós Manifestação Destinatário entregue (US-NFE-049/050/051/052 já done). Gold é gráfica grande, emite 100%.
2. **Vargas** (Autopecas) — sinal qualificado real (R$ [redacted Tier 0]M GMV), mas o módulo Autopecas (planejado — não existe) ainda não foi construído.
3. **Modules/ComunicacaoVisual cliente novo** — qualquer um das 5 cartas warming (Extreme/Zoom/Fixar/Mhundo/Produart) que adotar oimpresso pós outreach.

**Acceptance criteria (smoke fim-a-fim):**
- [ ] 1 cliente real configurado: cert A1 instalado + ncm_default + processo "Venda Com Nota Automática" como default
- [ ] Criar venda real → faturar boleto → cliente paga → InvoicePaid event dispara → FSM consulta stage atual → action `emitir_nfe` auto_trigger=true → NfeService.emitir → SEFAZ-prod retorna cstat 100
- [ ] DANFE PDF gerado + email enviado pro destinatário com XML+PDF anexados
- [ ] Wagner valida visualmente: NFe na lista do cliente em /nfebrasil + email recebido + PDF abre
- [ ] Session log da operação em `memory/sessions/YYYY-MM-DD-smoke-auto-emissao-{cliente}.md`

**NÃO bloqueia release do gate (US-SELL-012)** — feature pode ir pra prod sem este smoke. Esta US apenas registra a evidência real quando primeiro cliente adotar.

**Refs:** pivot conceitual sessão 2026-05-10 (US-RB-044 done sem prod-evidence). US-SELL-012 (gate por venda).

**Caso prático referência:** [Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](../Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md) — smoke real cobre 2 documentos atrelados à OS (NFe55 banner + NFSe56 nacional instalação). Depende US-NFE-060 (EmitirNFSeJob).

### US-NFE-060 · EmitirNFSeJob modelo 56 nacional (NT 2024-001) — paralelo ao EmitirNfceJob

> owner: wagner · priority: p1 · estimate: 12h · status: done · type: story
> blocked_by: US-SELL-014
> done: 2026-05-10 · PR: #509 · Pest: 5/5 ✅ · STUB (sem chamada API real — pacote sped-nfse pendente ADR)

**Implementado em:** _parcial_ · `Modules/NfeBrasil/Jobs/EmitirNFSeJob.php` · `Modules/NfeBrasil/Models/NfseEmissao.php` · verificado@3b425d8 (2026-06-24) — STUB modelo 56 (Job + Model existem; sem chamada API real — pacote sped-nfse pendente ADR)

**Contexto:** caso prático OS Comunicação Visual exige emissão de NFSe (instalação fachada R$ [redacted Tier 0]) em paralelo à NFe55 (banner R$ [redacted Tier 0]). Hoje `Modules/NfeBrasil` só emite modelos 55/65 (NFe/NFC-e via `nfephp-org/sped-nfe`). Falta NFSe modelo 56 nacional — padrão único `nfse.gov.br/sefin` que substituiu emissores municipais Tinus/Issnet/Ginfes (obrigatório MEI desde 09/2023, demais regimes em fases 2025-2026).

**Avaliar pacote PHP:**
- `nfephp-org/sped-nfse` (mantido pelo mesmo time que sped-nfe — primeira escolha)
- `gust-bzz/php-nfse-nacional` (alternativa)
- `harlleynunes/nfsenacional-php` (nicho)

**Job arquitetura (paralelo a `EmitirNfceJob` existente):**
- `Modules/NfeBrasil/Jobs/EmitirNFSeJob` — recebe `transaction_id` + `value_servico` + `item_lc116` (ex: 17.06 publicidade)
- Resolve config tributária via `MotorTributarioService::resolverParaNFSe(...)` (extender US-NFE-043 cascade)
- Gera RPS local (Recibo Provisório de Serviços) → envia pra Sefin nacional → autoriza
- Registra em `transaction_documents` (poly via US-SELL-014) com `doc_type=nfse56`
- Eventos: `NFSeAutorizada`, `NFSeRejeitada`, `NFSeCancelada` (espelho do que existe pra NFe55)
- PDF NFSe gerado via `Modules/NfeBrasil/Services/NfsePdfService` (adaptar `DanfeService`)
- Listener `EnviarNFSePorEmail` — anexa PDF + XML pro tomador

**Mudanças correlatas:**
- Migration `nfse_emissoes` (espelho `nfe_emissoes`: id, business_id, item_lc116, valor_servico, valor_iss, aliquota_iss, municipio_prestacao_ibge, chave_acesso, xml_path, status, etc)
- Model `NfseEmissao` em `Modules\NfeBrasil\Models`
- UI tela cliente `/contacts/{id}` ganha aba "NFSe emitidas" (espelho NFe)
- `nfe_business_configs` ganha `inscricao_municipal` + `regime_iss` (LC 116) per-business

**Acceptance criteria:**
- [ ] ADR `proposed` escolhendo pacote PHP (3 opções acima — comparativo)
- [ ] Migration `nfse_emissoes` com `business_id` global scope
- [ ] `EmitirNFSeJob` autoriza RPS na sandbox Sefin nacional → recebe número NFSe + chave acesso
- [ ] Idempotência via `transaction_documents` (US-SELL-014)
- [ ] PDF NFSe gerado (template ABRASF) + email enviado
- [ ] Eventos disparados (`NFSeAutorizada` etc)
- [ ] Pest 8+ testes (autorizar OK sandbox, rejeição RPS inválido, idempotência, isolation, item LC116 obrigatório, IM ausente bloqueia, alíquota ISS por município, PDF gerado)
- [ ] RUNBOOK em `memory/requisitos/NfeBrasil/RUNBOOK-emitir-nfse-56.md` (homologação Sefin nacional + cert A1 + IM)

**Caso prático referência:** [Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](../Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md) — NFSe instalação R$ [redacted Tier 0] ISS 5% Floripa/SC, item 17.06.

**Refs:** US-SELL-014 (transaction_documents poly). Pré-requisito pra US-NFE-059 (smoke prod end-to-end).

---

## Auditoria de completude — 2026-05-10

Disparada por: `/module-completeness-audit` (skill `module-completeness-audit` v0.1.0, sessão Wagner 2026-05-10).

**Resultado: 5 ✅ / 2 🟡 / 1 ❌ (de 8 dimensões)**

| Dim | Nome | Status | Evidência |
|---|---|---|---|
| 1 | Multi-instance scope | ✅ APROVADO | `Modules/NfeBrasil/Models/NfeEmissao.php:17,37` (FK + business_id fillable; Controller filtra ownership) |
| 2 | Permissions middleware + UI | ✅ APROVADO | `Modules/NfeBrasil/Http/Requests/UploadCertificadoRequest.php:11-13` (FormRequest authorize) + `DataController.php:57-139` (user_permissions) |
| 3 | Charter | ❌ AUSENTE | nenhum `*.charter.md` em `resources/js/Pages/NfeBrasil/**` |
| 4 | RUNBOOK | ✅ APROVADO | `RUNBOOK-smoke-sefaz.md` + `RUNBOOK-manifestacao.md` ambos status:live |
| 5 | Pest golden + cross-tenant biz=99 | ✅ APROVADO | `EmitirNfceJobTest.php:42-58` + `DanfeServiceTest.php:227` + `CertificadoControllerTest.php:44,46` (26 feature tests) |
| 6 | AuditLog em mutações | 🟡 PARCIAL | nenhum `AuditLog::log` em Controllers; mutações fiscais sem trail centralizado |
| 7 | business_id global scope | ✅ APROVADO | 20/20 Models usam `HasBusinessScope` (TIER 0 IRREVOGÁVEL conformado) |
| 8 | Browser MCP smoke | 🟡 PARCIAL | RUNBOOK fresh mas sem screenshot/console capture via Browser MCP |

### Gaps virando US-fix

- **US-NFE-061** (P0): Dim 3 Charter — bloqueia merge dos 4 PRs Manifestação NFe enquanto charter ausente

### Gaps deferred (P1/P2 — não aprovados nesta auditoria)

- 🟡 Dim 6 AuditLog (P1) — adicionar `activity('nfe.X')->log()` em store/update/destroy de TributacaoController, CertificadoController, ManifestacaoController. Razão deferred: Wagner aprovou só P0; reauditar próximo cycle.
- 🟡 Dim 8 Smoke MCP (P2) — capturar screenshot Browser MCP em `memory/requisitos/NfeBrasil/smoke-2026-05-10.md`. Razão deferred: P2.


### Atualização da auditoria 2026-05-10 — re-aprovação batch completo

Wagner re-aprovou (mesma data, turno seguinte) o batch completo: P1 e P2 também viraram US-fix. **Lista "Gaps deferred" acima zerada.**

US-fix adicionais criadas:
- **US-NFE-062** (P1): Dim 6 AuditLog em mutações fiscais
- **US-NFE-063** (P2): Dim 8 Smoke Browser MCP fresh

Total de gaps NfeBrasil convertidos em US-fix: **3 de 3 detectados.**


### US-NFE-061 · Charters NFe (Certificado, Tributacao, Manifestacao) antes dos 4 PRs Manifestação

> owner: wagner · sprint: cycle-04 · priority: p0 · estimate: 1.5h · status: done · type: story
> blocked_by: —

**Implementado em:** `resources/js/Pages/NfeBrasil/Tributacao/Index.charter.md` · `resources/js/Pages/NfeBrasil/Manifestacao/Index.charter.md` · verificado@3b425d8 (2026-06-24) — charters Tributacao + Manifestacao criados (Certificado pendente: tela Configuracao não migrada)

Gap detectado por skill `module-completeness-audit` em 2026-05-10 (Dim 3 Charter — ❌ AUSENTE).

**Evidência:** Glob `resources/js/Pages/NfeBrasil/**/*.charter.md` retornou vazio. Telas Certificado, Tributacao e Manifestacao são P0 e Wagner abriu 4 PRs Manifestação hoje — risco de mergear sem charter.

**Fix executado:** rodada skill `charter-write` 3× → criados 3 `.charter.md` ao lado dos `.tsx` → Wagner aprovou Mission/Goals/Non-Goals/Anti-hooks → marcou `status: live`.

**Acceptance criteria:**
- [x] `resources/js/Pages/NfeBrasil/Configuracao/Certificado.charter.md` criado, status:live
- [x] `resources/js/Pages/NfeBrasil/Tributacao/Index.charter.md` criado, status:live
- [x] `resources/js/Pages/NfeBrasil/Manifestacao/Index.charter.md` criado, status:live
- [x] 11 seções obrigatórias preenchidas (Mission / Goals / Non-Goals / Anti-hooks / UX targets / Automation hooks / Métricas / Comparáveis / Refs / Histórico)
- [x] Wagner aprovou Non-Goals + Anti-hooks de cada um (mesmo dia)

**Disparo:** Auditoria de completude 2026-05-10 (skill `module-completeness-audit` v0.1.0). Bloqueava merge dos 4 PRs Manifestação NFe — desbloqueado em PR #499.

**Tags:** completeness-gap, from-skill, audit-2026-05-10

### US-NFE-062 · AuditLog em mutações fiscais NFe (Tributacao, Certificado, Manifestacao)

> owner: — · sprint: cycle-04 · priority: p1 · estimate: 3h · type: story
> blocked_by: —

**Implementado em:** _parcial_ · `Modules/NfeBrasil/Http/Controllers/TributacaoController.php` · `Modules/NfeBrasil/Http/Controllers/CertificadoController.php` · verificado@85cf3e5 (2026-07-01) — falta AuditLog em `ManifestacaoController` (cienciar/confirmar/desconhecer/naoRealizada sem `activity()`; nem controller nem `ManifestacaoService` logam)

Gap detectado por skill `module-completeness-audit` em 2026-05-10 (Dim 6 AuditLog — 🟡 PARCIAL).

**Evidência:** Grep negativo em `Modules/NfeBrasil/Http/Controllers/*.php` — nenhuma chamada a `AuditLog::log`, `->logActivity()` ou `audit()`. Mutações fiscais (toggleAutoEmission, upload certificado, cienciar/confirmar/desconhecer/naoRealizada) sem trail centralizado. Compliance fraca + debugging difícil.

**Fix sugerido:** integrar Spatie Activitylog (mesmo pattern de `Modules/RecurringBilling/Http/Controllers/InvoiceController.php:97-121`):
```php
activity('nfe.tributacao')
    ->causedBy(auth()->user())
    ->withProperties(['business_id' => $businessId, 'before' => $before, 'after' => $after])
    ->log('toggle_auto_emission');
```

**Acceptance criteria:**
- [ ] `TributacaoController::toggleAutoEmission` loga (já implementado linha 105-112; manter)
- [ ] `CertificadoController::upload/destroy` logam
- [ ] `ManifestacaoController::cienciar/confirmar/desconhecer/naoRealizada` logam
- [ ] Toda mensagem inclui `business_id` em properties (filter por tenant)
- [ ] Pest test valida que `activity_log` recebe entry após mutation

**Disparo:** Auditoria de completude 2026-05-10.
**Tags:** completeness-gap, from-skill, audit-2026-05-10

### US-NFE-063 · Smoke Browser MCP fresh (screenshot+console) para fluxos Certificado/Auto-emission/Manifestacao

> owner: — · sprint: cycle-04 · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — smoke Browser MCP não salvo; o artefato de DoD `memory/requisitos/NfeBrasil/smoke-2026-05-10.md` (3 screenshots + console clean) não existe.

Gap detectado por skill `module-completeness-audit` em 2026-05-10 (Dim 8 Browser MCP smoke — 🟡 PARCIAL).

**Evidência:** RUNBOOK-smoke-sefaz.md fresh (2026-05-10) mas **sem screenshot/console capture** via Browser MCP automatizado. Apenas docs operacionais; não tem evidência visual reproduzível.

**Fix sugerido:** rodar `mcp__Claude_in_Chrome__*` em sequência pra cada fluxo principal:
1. `/nfe-brasil/configuracao/certificado` (upload + valida vencimento)
2. `/nfe-brasil/tributacao` (toggle auto_emission_nfce)
3. `/nfe-brasil/manifestacao` (lista DFes recebidas + cienciar)

Salvar em `memory/requisitos/NfeBrasil/smoke-2026-05-10.md`:
- Screenshots binary inline ou link (Wagner aprova exposição)
- `read_console_messages` filtrado por `error|Error|exception|TypeError|ReferenceError` (deve ser vazio)
- Data + biz=1 (não cliente real, ADR 0101)

**Acceptance criteria:**
- [ ] 3 screenshots dos fluxos
- [ ] Console clean (zero exceptions) em cada um
- [ ] Salvo em memory/requisitos/NfeBrasil/smoke-2026-05-10.md
- [ ] Push antes do go-live `NFEBRASIL_AUTO_EMISSION_NFCE=true`

**Disparo:** Auditoria de completude 2026-05-10.
**Tags:** completeness-gap, from-skill, audit-2026-05-10, smoke-mcp

### US-NFE-064 · Devolução de venda copia base e alíquotas da nota de origem

> owner: — · sprint: — · priority: p1 · estimate: 3h · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/NfeBrasil/Services/DevolucaoService.php` · `Modules/NfeBrasil/Services/NfeService.php` · `Modules/NfeBrasil/Tests/Feature/NfeDevolucaoTest.php` · `Modules/Fiscal/Http/Controllers/AcoesController.php` (rota, R-NFE-039) · `Modules/Fiscal/Tests/Feature/AcoesDevolucaoTest.php`

Como contador, quero que a NF-e de devolução use a base e as alíquotas da nota de origem — não a regra de hoje — para estornar exatamente o crédito ou débito que a venda gerou.

**Critérios de aceite:** R-NFE-026 · R-NFE-027 · R-NFE-039 (rota com permissão e empresa da sessão, 2026-10-26; o botão na tela da nota segue pendente).

**Disparo:** playbook Fiscal thread 20 (`prototipo-ui/cowork/Wagner/cowork-inbox/fiscal/playbook/20-devolucao.md`) · UC-TRB-13.

### US-NFE-066 · Benefício fiscal na regra (redução de base + cBenef)

> owner: — · sprint: — · priority: p1 · estimate: 3h · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/NfeBrasil/Services/MotorTributarioService.php` · `Modules/NfeBrasil/Services/NfeService.php` · `Modules/NfeBrasil/Database/Migrations/2026_10_14_000001_add_beneficio_fiscal_to_nfe_fiscal_rules.php` · `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php`

Como contador, quero cadastrar na regra a redução de base e o código do benefício da UF, com o ato legal que o concede, para a nota sair com o ICMS reduzido e o cBenef que a SEFAZ exige.

**Critérios de aceite:** R-NFE-030.

**Disparo:** playbook Fiscal thread 25 (`prototipo-ui/cowork/Wagner/cowork-inbox/fiscal/playbook/25-beneficio-cbenef.md`) · UC-TRB-26.

### US-NFE-067 · Filial em outro estado emite com a própria UF, emitente e série

> owner: — · sprint: — · priority: p1 · estimate: 4h · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/NfeBrasil/Services/NfeService.php` · `Modules/NfeBrasil/Models/NfeLocalEmissor.php` · `Modules/NfeBrasil/Database/Migrations/2026_10_14_000002_create_nfe_locais_emissores_table.php` · `Modules/NfeBrasil/Tests/Feature/NfeFilialNumeracaoTest.php`

Como empresa com filial em outro estado, quero que a nota da filial saia com a UF, o CNPJ, o endereço e a série dela, para não emitir como se estivesse na matriz nem repetir número entre estabelecimentos.

**Critérios de aceite:** R-NFE-031 · R-NFE-032.

**Disparo:** playbook Fiscal thread 26 (`prototipo-ui/cowork/Wagner/cowork-inbox/fiscal/playbook/26-filial-origem.md`) · UC-TRB-27.

### US-NFE-068 · Natureza ICMS × ISS por produto e DIFAL do Simples por UF

> owner: — · sprint: — · priority: p1 · estimate: 4h · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/NfeBrasil/Services/Tributacao/NaturezaFiscalService.php` · `Modules/NfeBrasil/Services/MotorTributarioService.php` · `Modules/NfeBrasil/Database/Migrations/2026_10_14_000003_create_nfe_naturezas_produto_e_difal_simples.php` · `Modules/NfeBrasil/Tests/Feature/NaturezaFiscalTest.php` · `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php`

Como contador, quero decidir por produto se ele é mercadoria (ICMS) ou serviço (ISS), com a fonte registrada, e marcar as UFs que cobram DIFAL do Simples, para o sistema não escolher sozinho imposto em caso controverso.

**Critérios de aceite:** R-NFE-034 · R-NFE-035.

**Disparo:** playbook Fiscal thread 29 (`prototipo-ui/cowork/Wagner/cowork-inbox/fiscal/playbook/29-icms-iss-e-difal-por-uf.md`) · D-NATUREZA · D-DIFAL-UF.

### US-NFE-069 · A natureza fiscal do item escolhe a nota: NF-e para mercadoria, NFS-e para serviço e instalação (UC-FLX-31) — **P1**

> owner: — · sprint: — · priority: p1 · estimate: 6h · type: story
> blocked_by: US-NFE-060 (NFS-e ainda é STUB, sem chamada real à Sefin nacional)

**Implementado em:** _parcial_ — `Modules/NfeBrasil/Services/SeparacaoNotasDaVenda.php` · `Modules/NfeBrasil/Services/NfeService.php` · `Modules/NFSe/Http/Controllers/NfseController.php` · `Modules/NfeBrasil/Http/Controllers/NfeEmissaoController.php` · `resources/js/Pages/Sells/_components/FiscalSection.tsx` · `Modules/NfeBrasil/Tests/Feature/EmissaoSeparadaContratoTest.php` (emissão das duas notas pelo botão do drawer; smoke em homologação e instalação em OS em aberto)

**Testado em:** `Modules/NfeBrasil/Tests/Feature/SeparacaoNotasDaVendaContratoTest.php` · `Modules/NfeBrasil/Tests/Feature/EmissaoSeparadaContratoTest.php` (lane `nfebrasil-pest.yml`)

Como faturista, quero que a venda ou OS com mercadoria e serviço saia em duas notas — NF-e com as mercadorias e NFS-e com os serviços —, decididas pela natureza fiscal de cada produto, para não ter a NF-e recusada inteira nem a NFS-e com código de serviço errado.

**Origem:** UC-FLX-31 "Natureza fiscal do item decide a nota" da tela Fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:83`, etapa Faturado; Súmula 156 do STJ). Medido 2026-10-20, **parcial**:
- existe: a natureza por produto (`nfe_naturezas_produto`, `Modules/NfeBrasil/Database/Migrations/2026_10_14_000003_create_nfe_naturezas_produto_e_difal_simples.php:25-43`), decidida só pelo contador (`Modules/NfeBrasil/Services/Tributacao/NaturezaFiscalService.php:58` `decidir`, `:40` `marcarPendente`) — US-NFE-068; testes em `Modules/NfeBrasil/Tests/Feature/NaturezaFiscalTest.php`;
- existe: a trava — `Modules/NfeBrasil/Services/NfeService.php:321` chama `recusarItensDeServico` (`:375-389`), que rejeita a NF-e **inteira** quando há um item de serviço;
- falta: dividir a venda/OS automaticamente em NF-e (mercadorias) + NFS-e (serviços);
- falta: a NFS-e ler o `codigo_servico` da natureza do produto — `EmitirNFSeJob`/`NfseEmissaoService` não a consultam (0 ocorrências) e `Modules/NFSe/Observers/TransactionNfseObserver.php:51` usa o default do business (`'1.05'`);
- falta: instalação sair como NFS-e.

US-NFE-060 cobre só o job de emissão da NFS-e (modelo 56, STUB); esta US cobre a divisão por natureza e o código de serviço por item. UC-FLX-27 ("Uma OS, duas notas") é o caso de OS da mesma divisão.

**Aceite:**
- [x] **Separar as linhas** (2026-10-20, PR-1): `SeparacaoNotasDaVenda::separar` divide a venda em parte mercadoria e parte serviço pela natureza do produto, com o `codigo_servico` de cada linha de serviço; a soma das duas partes é o `final_total`, centavo a centavo (UC-FNFSE-06..09 em `resources/js/Pages/Fiscal/Nfse.casos.md`). Regra de rateio de desconto, frete e acréscimos em `SeparacaoNotasDaVenda::REGRA_RATEIO` — **decidida por [W] em 2026-10-21**: proporcional ao bruto, a parte serviço arredonda e a mercadoria absorve o centavo, frete inteiro na NF-e. [W] 2026-10-21, textual: *"Aprovo o rateio proporcional ao bruto com o frete na NF-e. Pode seguir no UC-27/31."*
- [x] Disparo pela tela (2026-10-21, PR-2): o botão "Emitir NF-e + NFS-e" da seção Fiscal do drawer mostra as duas partes e, confirmado, emite a NF-e e depois a NFS-e (UC-FNFSE-10..12). A ação FSM "faturar" **não** dispara nota — emissão sempre por clique (charter Sells/Index). Sem a separação, `recusarItensDeServico` segue recusando a NF-e com serviço
- [x] Emissão (2026-10-21, PR-2): a NF-e sai só com a parte mercadoria (`emitirParaTransaction(..., separada: true)`) e a NFS-e só com a parte serviço; o valor do corpo da requisição é ignorado
- [x] A NFS-e da venda separada usa o `codigo_servico` gravado na natureza do produto (2026-10-21, PR-2); serviços com códigos diferentes recusam (uma NFS-e leva um código). A NFS-e avulsa e o `TransactionNfseObserver` seguem com o default do business
- [ ] Item de instalação (natureza `servico`) vai para a NFS-e, mesmo numa OS com material
- [x] Produto com natureza `pendente` bloqueia a emissão separada com mensagem que manda o contador decidir (2026-10-21, PR-2); não escolhe imposto sozinho (D-NATUREZA)
- [ ] Multi-tenant: natureza de biz=1 nunca decide nota de biz=2 (Pest com os dois tenants); só quem tem permissão de emitir nota dispara a divisão (403 sem ela)
- [ ] Smoke fiscal manual contra SEFAZ/Sefin só em biz=1, homologação

### US-NFE-070 · Contingência: dhCont/xJust no XML e retransmissão agendada (UC-FLX-32) — **P1**

> owner: — · sprint: — · priority: p1 · estimate: 4h · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/NfeBrasil/Services/NfeService.php` · `Modules/NfeBrasil/Console/Commands/RetentarContingenciaCommand.php` · `Modules/NfeBrasil/Providers/NfeBrasilServiceProvider.php` · `Modules/NfeBrasil/Jobs/RetentarContingenciaJob.php` · `Modules/NfeBrasil/Tests/Feature/ContingenciaAgendamentoTest.php`

**Testado em:** `Modules/NfeBrasil/Tests/Feature/ContingenciaAgendamentoTest.php` (covers US-NFE-070 · lane `nfebrasil-pest`)

Como gestor, quero que a nota emitida em contingência seja transmitida sozinha quando a SEFAZ voltar e saia com os campos que a SEFAZ exige, para não depender de alguém lembrar de reenviar nem ter a nota rejeitada depois.

**Origem:** UC-FLX-32 "NF-e em contingência quando a SEFAZ cai" da tela Fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:84`, etapa Validada → Transmitida). Medido 2026-10-20, **parcial** — é o resto de US-NFE-006 (cuja âncora segue `_pendente_` e não foi alterada aqui):
- existia: ativação manual (`Modules/NfeBrasil/Services/ContingenciaService.php`); `NfeService::resolverTpEmis` (modelo 55 → 4 EPEC, 65 → 9); `NfeService::transmitirContingencia`; `Modules/NfeBrasil/Jobs/RetentarContingenciaJob.php`; ~35 testes `Contingencia*Test`;
- entregue nesta US (2026-10-20): `dhCont`/`xJust` no XML de toda nota com tpEmis ≠ 1, vindos da ativação (`contingencia_ativada_em` + `contingencia_motivo`; motivo com menos de 15 caracteres ganha o prefixo "Contingencia: "); o command `nfe:retentar-contingencia`, agendado a cada 5 min no `NfeBrasilServiceProvider`, que para cada business com nota em `contingencia` faz o ping da SEFAZ com o cert dele e só despacha `RetentarContingenciaJob($businessId)` com cStat 107 (grava `verde` em `nfe_sefaz_status`);
- fica para **US-NFE-071**: SVC-AN/SVC-RS (`tpEmis` 6/7), o evento EPEC 110140 e a marcação de contingência no DANFE.

Casos: `resources/js/Pages/Fiscal/Nfe.casos.md` UC-FNFE-15..18.

**Aceite:**
- [x] Um agendamento dispara `RetentarContingenciaJob` por business; ao voltar a SEFAZ, as notas em contingência são transmitidas em ordem de emissão (o teste de `tests/Feature/Sells/FsmCatalogoProcessosContratoTest.php` passa a exigir o chamador) — UC-FNFE-17
- [x] Toda nota em contingência sai com `dhCont` e `xJust` preenchidos a partir do motivo da ativação; nota normal não muda — UC-FNFE-15/16
- [x] Multi-tenant: o job de um business nunca transmite nota de outro (Pest com os tenants 98 e 2, UC-FNFE-18); ativar/desativar exige `nfe.contingencia.manage` (403 sem ela, `ContingenciaAtivacaoTest` UC-CONT-15)
- [ ] Smoke fiscal manual contra SEFAZ só em biz=1, homologação

### US-NFE-073 · XML do mês enviado ao contador sem ninguém lembrar — **P1**

> owner: — · sprint: — · priority: p1 · estimate: 6h · type: story
> blocked_by: —

**Implementado em:** _parcial_ — `Modules/NfeBrasil/Services/PacoteXmlContadorService.php` · `Modules/NfeBrasil/Console/Commands/EnviarXmlContadorCommand.php` · `Modules/NfeBrasil/Tests/Feature/EnviarXmlContadorTest.php` (backend + agenda desligada; falta o drawer do cockpit com dado real)

Como dono da empresa, quero que o XML das notas do mês chegue ao meu contador sozinho, no começo do mês seguinte, para não ter de baixar e mandar à mão nem esquecer.

**Origem:** levantamento de lacunas de 2026-10-25 ("Envio automático dos XMLs ao contador", P1). Medido em 2026-10-26: o drawer `resources/js/Pages/Fiscal/_components/SendToContabilDrawer.tsx` tinha o botão desligado e o `CockpitController::mockContabilData` devolvia números fictícios; o contador já é cadastrado em `nfe_contadores` (thread 15b) e o XML já é lido pelo `DanfeService::obterXmlContents`.

**Aceite:**
- [x] O comando monta o ZIP das autorizadas e canceladas da competência, manda ao contador e registra, uma vez por empresa × mês — R-NFE-038, UC-FNFE-37/38
- [x] Sem contador, sem nota ou em dry-run nada sai; o agendamento mensal fica atrás de `NFEBRASIL_ENVIO_XML_CONTADOR` (desligada) — UC-FNFE-39
- [x] Multi-tenant: nota e contador de outra empresa nunca entram (tenants 98 e 99)
- [ ] O drawer do cockpit mostra o histórico real (`nfe_envios_contador`) e o botão envia agora, com permissão
- [ ] Ligar o envio automático em produção — **decisão [W]** (manda e-mail para fora)

### US-NFE-071 · Contingência: SVC-AN/SVC-RS, evento EPEC 110140 e marcação no DANFE (UC-FLX-32) — **P1**

> owner: — · sprint: — · priority: p1 · estimate: 6h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_

Como gestor, quero que a NF-e em contingência use o modo certo para a UF e tenha o evento EPEC registrado antes de imprimir, para o DANFE valer e a nota não ser rejeitada.

**Origem:** o resto da US-NFE-070 (2026-10-20). Não há chamada `sefazEPEC` nem `tpEmis` 6/7 no código.

**Entregue em 2026-10-21 (parcial — a âncora segue `_pendente_` até a marcação no DANFE):** a escolha
"Modo: SVC-AN · SVC-RS · EPEC" na seção Contingência SEFAZ de `/fiscal/config`, gravada em
`nfe_business_configs.contingencia_modo` (`Modules/NfeBrasil/Services/ModoContingencia.php`); a ativação recusa a
SEFAZ Virtual que não atende a UF do emitente (`ContingenciaService::ativar`); em SVC a NF-e sai com tpEmis 6/7 e
é transmitida na hora com `Tools::$contingency` armado em SVCAN/SVCRS; em EPEC a nota fica guardada e o evento
110140 vai ao AN (`NfeService::registrarEpec`), com falha registrada como evento `rejeitado` sem perder a nota.
Casos: `resources/js/Pages/Fiscal/Nfe.casos.md` UC-FNFE-19, 20, 30, 31 · prova `ContingenciaModoTest` (lane
`nfebrasil-pest`). Fica: a marcação de contingência no DANFE e o smoke em homologação.

**Aceite:**
- [x] NF-e modelo 55 pode usar SVC-AN (`tpEmis=6`) ou SVC-RS (`tpEmis=7`) conforme a UF do emitente — UC-FNFE-19
- [ ] Em EPEC (`tpEmis=4`) o evento 110140 é enviado e registrado antes da impressão do DANFE (feito — UC-FNFE-20/30); o DANFE mostra a marcação de contingência (pendente)
- [x] Multi-tenant: o evento de um tenant nunca grava em outro (Pest com os tenants 98 e 2) — UC-FNFE-31
- [ ] Smoke fiscal manual contra SEFAZ só em biz=1, homologação


### US-NFE-072 · ICMS-ST e DIFAL chegam à NF-e da venda interestadual — **Legal**

> owner: — · sprint: — · priority: p0 · estimate: 8h · type: story
> blocked_by: —

**Implementado em:** _parcial_ — `Modules/NfeBrasil/Services/NfeService.php` (destinatário real, destino no motor, DIFAL e ICMS-ST no XML, R-NFE-036/037/043) · `Modules/NfeBrasil/Tests/Feature/NfeDestinatarioDaVendaTest.php` · `Modules/NfeBrasil/Tests/Feature/NfeDifalNoXmlTest.php` · `Modules/NfeBrasil/Tests/Feature/NfeIcmsStNoXmlTest.php` (falta o smoke SEFAZ)

Como empresa que vende para outro estado, quero que a NF-e saia para o meu cliente, com a UF dele, e leve o ICMS-ST e o DIFAL que o motor já calcula, para não emitir nota com imposto zerado numa operação interestadual.

**Origem:** levantamento de lacunas de 2026-10-25 (item "ICMS-ST e DIFAL no XML da NF-e", obrigação legal). Medido em 2026-10-26 no `main`: o motor calcula ST, FCP e DIFAL (R-NFE-015..017, thread 06), mas (1) a NF-e da venda saía sempre para "CONSUMIDOR FINAL" na própria UF, então nenhuma venda chegava ao motor como interestadual, e (2) o `buildXml` grava `vBCST`, `vST`, `vICMSUFDest` e `vFCPUFDest` como zero e não emite os grupos de ST nem o `ICMSUFDest`.

**Aceite:**
- [x] A NF-e (55) da venda com cliente identificado leva o destinatário dele, e o motor recebe UF, contribuinte e interna do destino — R-NFE-036, UC-FNFE-32/33
- [x] O XML grava o DIFAL no grupo `ICMSUFDest` e nos totais, sem mudar o vNF — R-NFE-037, UC-FNFE-34/35
- [x] O XML grava o ICMS-ST no grupo do CST/CSOSN com ST e soma o vST ao vNF (regra W16 do leiaute; [W] 2026-10-26) — R-NFE-043, UC-FNFE-49/50
- [x] Interestadual a não contribuinte sem interna cadastrada recusa a nota com a saída (cadastrar a interna), em vez de emitir DIFAL zero — UC-FNFE-36
- [ ] Multi-tenant: o contato e a tabela por UF são lidos só do tenant da venda (Pest com os tenants 98 e 99)
- [ ] Smoke fiscal manual contra SEFAZ só em biz=1, homologação (depende do certificado A1 de biz=1; em 2026-05-09 ele valia até 2026-08-06 — conferir antes)

### US-NFE-077 · NF-e de bonificação (CFOP 5910/6910, sem pagamento) — **P1**

> owner: — · sprint: — · priority: p1 · estimate: 2h · type: story
> blocked_by: —

**Implementado em:** `Modules/NfeBrasil/Services/NfeService.php` (`emitirParaTransaction`, `ehBonificacao`, `comoBonificacao`) · `Modules/NfeBrasil/Listeners/EmitirNfceAoFinalizarVenda.php` · `Modules/NfeBrasil/Tests/Feature/NfeBonificacaoTest.php`

Como empresa que bonifica cliente (ex. supermercado), quero que a venda marcada como bonificação saia em NF-e de bonificação, para que a nota leve o CFOP certo e nenhum pagamento.

**Origem:** pedido P-154 (Sabor Brasil, migração do Delphi, VENDA_TIPO BONIFICACAO). A venda é marcada por `transactions.sub_type = 'bonificacao'` (gravado pela tela de venda); o Financeiro não gera título para ela (outras partes do mesmo pedido).

**Aceite:**
- [x] CFOP 5910 dentro do estado e 6910 fora; natOp BONIFICACAO; tPag 90 com vPag 0 — R-NFE-047, UC-FNFE-57
- [x] Tributação do item (CST/CSOSN, ICMS, ST) e totais seguem os do motor; vProd e vNF iguais aos da venda — UC-FNFE-57
- [x] NFC-e de bonificação recusa; a NFC-e automática do PDV não dispara — UC-FNFE-58
- [ ] "Bonificação sem ST" do Delphi (VENDA_TIPO "BONIFICAÇÃO SEM ST"): decisão pendente — hoje o ST vem da regra do motor, como na venda

### US-NFE-075 · NF-e com destinatário e CFOP vindos de fora da venda (pedido de marketplace) — **P1**

> owner: — · sprint: — · priority: p1 · estimate: 3h · type: story
> blocked_by: —

**Implementado em:** `Modules/NfeBrasil/Services/NfeService.php` (`emitirParaTransaction`, `destinatarioDaEmissao`, `comCfop`) · `Modules/NfeBrasil/Tests/Feature/NfeDestinatarioExternoCfopTest.php`

Como integração de marketplace, quero emitir a NF-e do pedido pago informando quem compra e o CFOP da operação, para que a nota saia para o comprador do marketplace e com o CFOP que a empresa configurou.

**Origem:** pedido da frente Plataforma de 2026-10-26: o PR #9908 deixa pronta, atrás da flag desligada `marketplaces.nfe.automatica`, a emissão da NF-e do pedido pago no Mercado Livre, e faltavam duas entradas no `NfeService`. O CFOP definitivo é decisão do contador; por isso o valor vem de quem chama e nada aqui fixa CFOP.

**Aceite:**
- [x] O destinatário externo (chaves de `contacts`) vence o cliente da venda; sem CPF/CNPJ a emissão recusa — R-NFE-046, UC-FNFE-55
- [x] O CFOP informado vence o da regra em todos os itens; fora do formato recusa — R-NFE-046, UC-FNFE-56
- [x] Sem os dois parâmetros a emissão é a de antes (R-NFE-036)
- [ ] O de-para do comprador do marketplace para as chaves de `contacts` e o CFOP configurável ficam na integração do marketplace (PR #9908, frente Plataforma)

### US-NFE-074 · Cutover da emissão Delphi → oimpresso, por empresa — **Backlog**

> owner: — · sprint: — · priority: p1 · estimate: 4h · status: todo · type: story
> blocked_by: decisão [W] por empresa (data do cutover)

**Implementado em:** _pendente_ — registro de backlog, nada executado.

Como empresa que ainda emite pelo Delphi (WR Comercial/OfficeImpresso), quero passar a emitir pelo oimpresso num dia combinado, sem duplicar nota nem repetir número, para terminar a migração.

**Origem:** pré-voo da NFC-e automática de 2026-10-26 (US-NFE-002) — biz=4 e biz=164 emitiam pelo Delphi; [W] 2026-10-26.

**Antes de ligar, por empresa (checklist):**
- [ ] Certificado A1 da empresa cadastrado no oimpresso e dentro da validade
- [ ] Ambiente de produção configurado para a empresa
- [ ] Credenciamento NFC-e na SEFAZ da UF (SEFAZ-SC para as empresas de hoje)
- [ ] Série e numeração que **não** colidam com as que o Delphi já usou — medir a última numeração do Delphi por série antes de definir
- [ ] Desligar a emissão no Delphi e ligar aqui no **mesmo dia**, com aviso prévio ao cliente e canary, conforme a F5 do MWART (ADR 0104)

### US-NFE-076 · Ler o XML da NF-e de compra, por NSU ou por upload — **P0**

> owner: — · sprint: — · priority: p0 · estimate: 3h · type: story
> blocked_by: —

**Implementado em:** `Modules/NfeBrasil/Services/Entrada/LeitorNfeEntrada.php` · `Modules/NfeBrasil/Services/Entrada/ImportarXmlEntradaService.php` · `Modules/NfeBrasil/Models/NfeDfeItem.php` (`gravarDaNota`) · `Modules/NfeBrasil/Tests/Feature/EntradaXmlUploadContratoTest.php`

**Testado em:** `Modules/NfeBrasil/Tests/Feature/EntradaXmlUploadContratoTest.php` (covers US-NFE-076 · lane `nfebrasil-pest`)

Como empresa que compra de fornecedor, quero que a NF-e que chega pela SEFAZ e a que eu envio em arquivo sejam lidas do mesmo jeito, com o código do item no fornecedor, EAN, unidade, CST/CSOSN e ICMS destacado, para depois virar compra sem digitar.

**Origem:** fase 2 da ADR `proposals/2026-10-06-entrada-xml-compra.md` (D2, D3), ratificada em #9827; schema da fase 1 em #9836. A compra é a US-COM-003 (Compras).

**Aceite:**
- [x] Um leitor só para a DF-e por NSU e para o upload; aceita `nfeProc` e `NFe` sem protocolo; recusa o que não é NF-e — UC-NFENT-01
- [x] Por item: `cProd`, `cEAN` (sem "SEM GTIN"), `uCom`, CST com origem (060) ou CSOSN, `vICMS` — UC-NFENT-01
- [x] Upload grava com `origem=upload`, sem NSU — UC-NFENT-02
- [x] Nota emitida para outro CNPJ é recusada e nada é gravado (Tier 0, ADR 0093) — UC-NFENT-03
- [x] A mesma chave não entra duas vezes na empresa — UC-NFENT-04
- [x] Atrás da flag `nfebrasil.entrada_xml_enabled`, desligada por padrão — UC-NFENT-05
- [ ] Rota e tela de envio do arquivo (fase 4, tela de conferência)

### US-NFE-078 · Empresas diferentes (CNPJs de raízes diferentes) emitindo no mesmo business — **P1**

> owner: F · sprint: — · priority: p1 · estimate: 5d · status: todo · type: story
> blocked_by: aprovação [W] da ADR ARQ-0007

**Implementado em:** _pendente_ — fase 1 (ADR); código nas fases 2–7 da ADR ARQ-0007.

Como grupo de empresas que trabalha num banco só (cadastros compartilhados, cada CNPJ com seu certificado e seu regime), quero que cada unidade emita a própria nota no mesmo business, para migrar do Delphi sem duplicar clientes e produtos.

**Origem:** migração do cliente Sabor Brasil (Firebird v1525, 6 CNPJs, um no Simples e cinco no Regime Normal), 2026-10-30. Hoje o local emissor (R-NFE-031) troca CNPJ, IE e série, mas o certificado e o regime continuam os do business.

**Aceite:**
- [ ] Local emissor com certificado próprio assina com ele; sem certificado próprio usa o do business (como hoje)
- [ ] Certificado cuja raiz de CNPJ não é a do local é recusado
- [ ] Local com regime próprio sai com o CRT dele e o motor usa CST ou CSOSN conforme esse regime
- [ ] Dois CNPJs diferentes podem usar a mesma série; cada CNPJ numera sozinho, sem pular nem repetir, e começa do número informado no cutover
- [ ] Cancelamento, CC-e, inutilização, manifestação e DF-e usam o certificado do local da nota
- [ ] Business sem local emissor emite exatamente como antes
- [ ] Homologação SEFAZ com 2 CNPJs de raízes e regimes diferentes no mesmo business
