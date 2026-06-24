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
**Quero** ativar contingência (EPEC pra NF-e, FS-DA pra NFC-e) e seguir vendendo offline
**Para** não parar caixa quando SEFAZ está com problema

**Implementado em:** _pendente_ — modo contingência (EPEC/FS-DA + tela Pages/NfeBrasil/Contingencia) não construído

**Definition of Done:**
- [ ] Detecção automática: `SefazHealthCheck` ping a cada 30s → se 3 falhas seguidas, sugerir contingência
- [ ] Ativação manual em 1 clique com motivo
- [ ] Notas em contingência ganham `tpEmis=4 (EPEC)` ou `tpEmis=9 (FS-DA)`
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

### R-NFE-011 · Schema CBS/IBS vazio em 2026, preenchido conforme legislação

```gherkin
Dado uma NFe emitida em 2026-04 com regime tradicional
Quando inspeciono `nfe_fiscal_rules.cbs_*` e `nfe_fiscal_rules.ibs_*`
Então estão NULL
E o XML emitido NÃO inclui blocos CBS/IBS
```

**Implementação:** Schema flexível; `MotorTributarioService` skip CBS/IBS se rule é NULL.
**Testado em:** `Modules/NfeBrasil/Tests/Feature/OndaIbsCbsScaffoldTest.php` (schema flexível IBS/CBS: colunas nullable `cst_ibs`/`cst_cbs`/`aliquota_ibs`/`aliquota_cbs` em `$fillable` + casts; cascade do motor coberto por `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php`).

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

> owner: — · priority: p0 · estimate: 16h · status: todo · type: epic · origin: capterra-inventario-2026-05-06 · capacidade: #7-multi-tenant
> blocked_by: —
> bloqueia: US-NFE-041, US-NFE-001..011 (todas as US existentes do módulo dependem desta foundation)

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

> owner: — · priority: p0 · estimate: 12h · status: todo · type: story · origin: capterra-inventario-2026-05-06 · capacidade: #1
> blocked_by: US-NFE-040

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

⚠️ **Status `blocked` por orientação Wagner 2026-05-09 ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).** Escopo focado em emissão NF-e 55. Reativa apenas se discovery (US-NFE-042) confirmar que Gold também emite. A proposta da fase manifestação é tratada nas US-049..053 + apêndice no template `PROPOSTA-COMERCIAL-vs-mubsys.md`.

Construir proposta comercial pra Gold ancorada nos diferenciais oimpresso vs Mubsys ([comparativo Capterra 2026-04-25](../../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md)).

**Acceptance criteria:** ver bloco original em comentário do MCP (pricing on-prem TBD por Wagner; cláusula cert A1; SLA suporte; 1 página executiva + anexo técnico).

**Refs:** ADR 0115 · ADR 0026 (posicionamento) · README NfeBrasil pricing

### US-NFE-044 · Upgrade plataforma on-prem Gold pra versão atual com NfeBrasil · 🔒 DORMENTE

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 6h · status: blocked · type: story
> blocked_by: US-NFE-043

⚠️ **Status `blocked` ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).** Parte do upgrade é reaproveitada pela manifestação (US-NFE-049..053 dependem de upgrade base também). Mantida dormente — pode ser parcialmente reativada quando manifestação exigir.

Trazer instalação Gold da versão atual dela pra `main` do oimpresso, incluindo `Modules/NfeBrasil` e `Modules/Officeimpresso`. Risco ALTO — produção do cliente.

**Acceptance criteria:** backup completo, janela combinada, `composer install` sem `--no-dev`, `php artisan migrate`, `npm ci && npm run build`, triggers MySQL imutabilidade preservados, smoke pós-upgrade. Detalhe original em comentário MCP.

**Refs:** ADR 0115 · Runbook Fase 3 · auto-mem `reference_diff_3_7_vs_6_7_officeimpresso.md`

### US-NFE-045 · Configuração fiscal NfeBrasil Gold (cert + IE + regime + template SP) · 🔒 DORMENTE

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 3h · status: blocked · type: story
> blocked_by: US-NFE-044

⚠️ **Status `blocked` ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).** Cert A1 + IE também são pré-requisitos de manifestação (assinatura de evento). Parte reaproveitada por US-NFE-049..053.

Configurar emissão fiscal NfeBrasil pra business Gold após upgrade. Detalhe em comentário MCP.

**Refs:** ADR 0115 · Runbook Fase 4 · `Modules/NfeBrasil/Resources/templates/industria-grafica-presumido-sp.php`

### US-NFE-046 · Smoke fiscal homologação SEFAZ-SP biz=Gold (1ª NF-e 55 cstat 100) · 🔒 DORMENTE

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 2h · status: blocked · type: story
> blocked_by: US-NFE-045

⚠️ **Status `blocked` ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).** Smoke específico de emissão NF-e 55. Smoke equivalente da manifestação fica em US-NFE-053 (eventos 210/220).

Emitir 1ª NF-e modelo 55 em homologação SEFAZ-SP usando cert + IE da Gold. Detalhe em comentário MCP.

**Refs:** ADR 0115 · Runbook Fase 5 · auto-mem `runbook_smoke_sefaz_biz1.md`

### US-NFE-047 · Treinamento operadora Gold + cutover NF-e produção + canary 7d · 🔒 DORMENTE

> owner: wagner · sprint: Gold-Reativacao · priority: p1 · estimate: 3h · status: blocked · type: story
> blocked_by: US-NFE-046

⚠️ **Status `blocked` ([ADR 0116](../../decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md)).** Treinamento e cutover da fase manifestação ficam em US-NFE-052/053.

Treinar operadora Gold + cutover NF-e prod + canary 7d. Detalhe em comentário MCP.

**Refs:** ADR 0115 · Runbook Fases 5-6

### US-NFE-048 · Refinar runbook on-prem reutilizável pós-Gold (Trilha 1 dormentes) · 🔒 DORMENTE

> owner: wagner · sprint: Gold-Reativacao · priority: p2 · estimate: 2h · status: blocked · type: story
> blocked_by: US-NFE-047

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

> owner: — · sprint: cycle-04 · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: —

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
