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

**Implementado em:** _[TODO — `resources/js/Pages/NfeBrasil/Configuracao/Certificado.tsx`]_

**Definition of Done:**
- [ ] FormRequest aceita `.pfx` ≤ 100KB + `senha` (não loga em audit log!)
- [ ] `CertificadoService::validar()` lê o pfx via OpenSSL, extrai CN (CNPJ), valida `not_after > now()`
- [ ] Storage criptografado: `storage/app/nfe-brasil/{business_id}/cert/{uuid}.pfx.enc` (encrypt at rest com chave do business)
- [ ] Senha nunca persiste em texto: armazenada via `encrypt()` Laravel
- [ ] Certificado próximo do vencimento (≤30d) gera badge no sidebar
- [ ] Test Feature: upload válido + cert expirado rejeitado + CNPJ ≠ business CNPJ rejeitado + isolamento

### US-NFE-002 · Emitir NFC-e a partir de venda finalizada

> **Área:** Emissao
> **Rota:** `POST /nfe-brasil/nfce/emitir`
> **Controller/ação:** `NfceController@emitir`
> **Permissão Spatie:** `nfe.nfce.emitir`

**Como** Larissa-caixa (operador POS)
**Quero** clicar "Finalizar venda" no POS e o sistema emitir NFC-e em background, retornar DANFE imprimível em até 5s
**Para** atender exigência fiscal sem fricção no balcão

**Implementado em:** _[TODO — integração `/sells/create` finalizar + `resources/js/Pages/NfeBrasil/Emissao/Sucesso.tsx`]_

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

**Implementado em:** _[TODO — `resources/js/Pages/NfeBrasil/Emissoes/Show.tsx`]_

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

**Implementado em:** _[TODO — `resources/js/Pages/NfeBrasil/Emissoes/Show.tsx` (botão Cancelar)]_

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

**Implementado em:** _[TODO — `resources/js/Pages/NfeBrasil/Emissoes/Show.tsx` (botão CCe)]_

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

**Implementado em:** _[TODO — `resources/js/Pages/NfeBrasil/Contingencia/Index.tsx`]_

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

**Implementado em:** _[TODO — `resources/js/Pages/NfeBrasil/Monitor/Index.tsx`]_

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

**Implementado em:** _[TODO — `resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx`]_

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

**Implementado em:** _[TODO — `resources/js/Pages/NfeBrasil/Sped/Index.tsx`]_

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

**Implementado em:** _[TODO — `resources/js/Pages/NfeBrasil/Tributacao/Regras/Index.tsx`, `.../Form.tsx`]_

**Definition of Done:**
- [ ] Tabela `nfe_fiscal_rules` com `UNIQUE (business_id, ncm, uf_origem, uf_destino)` composta (ARQ-0004 schema)
- [ ] FormRequest valida: `ncm` 8 dígitos, `uf_origem` em FEBRABAN UFs, `uf_destino` opcional (NULL = todas), CSOSN OU CST exclusive
- [ ] Schema flexível: campos `cbs_*` e `ibs_*` nullables (Reforma Tributária 2026-2033, ARQ-0004)
- [ ] **Cascade fallback respeitado** (ARQ-0006): regra criada participa do cascade Nível 2 ou 3 dependendo de `uf_destino` ser NULL
- [ ] **Bridge automática** (ARQ-0005): listener `SyncFiscalRuleToTaxRate` upsert linha em `tax_rates` core (compat Connector)
- [ ] **Importação CSV** em massa: upload datasets Receita Federal (NCM 8d) ou CONFAZ (CEST 7d):
  - Preview antes de aplicar (10 primeiras linhas + totais)
  - Detecta duplicados existentes (skip ou update via `--update-existing` flag)
  - Validação por linha (erros não bloqueiam linhas válidas)
  - Resultado: "150 criadas, 12 atualizadas, 3 falharam (ver log)"
- [ ] **Buscador NCM** com autocomplete (dataset core 15k entries; lookup por digit ou nome)
- [ ] **Preview de cálculo** com produto exemplo: usuário digita valor R$ 100 → mostra ICMS=18, ICMS-ST=12, IPI=5, PIS=0,65, COFINS=3 (carga efetiva calculada antes de salvar)
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
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MultiTenantIsolationTest`.

### R-NFE-002 · Permissões Spatie por área

```gherkin
Dado um usuário sem `nfe.{area}.{action}`
Quando ele acessa rota correspondente
Então recebe 403
```

**Implementação:** `Route::middleware('can:...')` no group. 14 permissões registradas no boot.
**Testado em:** `SpatiePermissionsTest`.

### R-NFE-003 · Numeração sequencial garantida (sem gap)

```gherkin
Dado o business tem 100 NFe emitidas (números 1-100)
Quando 5 emissões concorrentes disparam
Então cada uma recebe número distinto entre 101 e 105
E não há gap nem dupla numeração
```

**Implementação:** `NumberSequenceService::next()` com `lockForUpdate` em transação. Para NFC-e, sequencial por `(business_id, serie)`.
**Testado em:** `NumberSequenceConcorrenciaTest` — 50 jobs em paralelo (Linux only).

### R-NFE-004 · Cert digital expirado bloqueia emissão

```gherkin
Dado o certificado A1 do business expirou (not_after < hoje)
Quando uma venda dispara emissão
Então a emissão é bloqueada com erro `cert_expirado`
E aparece banner em todas as telas administrativas pra renovar
```

**Implementação:** `CertificadoGuard::ensureValid()` rodada antes de qualquer call SEFAZ.
**Testado em:** `CertExpiradoTest`.

### R-NFE-005 · Idempotência de emissão

```gherkin
Dado uma venda com transaction_id = 1234 e NFC-e já emitida
Quando o job EmitirNfceJob roda 2x (retry queue)
Então a 2ª execução retorna a emissão existente (sem chamar SEFAZ de novo)
```

**Implementação:** `nfe_emissoes` tem `UNIQUE (business_id, transaction_id, modelo)`. Service faz `firstOrCreate`.
**Testado em:** `EmissaoIdempotenciaTest`.

### R-NFE-006 · Storage cert criptografado at rest

```gherkin
Dado um cert .pfx é uploaded
Quando inspeciono `storage/app/nfe-brasil/{business_id}/cert/{uuid}.pfx.enc`
Então o conteúdo é diferente do .pfx original
E só é decifrável com a chave de criptografia do business
```

**Implementação:** `CertificateStorageService` usa `openssl_encrypt` com chave por business salva em `nfe_business_keys` (rotacionável).
**Testado em:** `CertStorageEncryptionTest`.

### R-NFE-007 · Senha do cert NUNCA é logada

```gherkin
Dado um upload de cert com senha
Quando inspeciono `storage/logs/laravel.log` e `activity_log`
Então a senha não aparece em nenhum log
```

**Implementação:** FormRequest tem `dontFlash = ['senha']` + audit log filtra `senha`.
**Testado em:** `CertSenhaNaoLogadaTest`.

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
**Testado em:** `CancelamentoPrazoTest`.

### R-NFE-009 · Detecção de SEFAZ down → contingência sugerida

```gherkin
Dado que `SefazHealthCheck` falhou 3 vezes seguidas
Quando Larissa tenta finalizar venda
Então o sistema sugere ativar contingência
E permite forçar emissão online (com risco) ou aceitar contingência
```

**Implementação:** Health check job a cada 30s grava `sefaz_status`; UI consulta antes de habilitar emissão online.
**Testado em:** `ContingenciaSugestaoTest`.

### R-NFE-010 · Retenção XML 5 anos imutável

```gherkin
Dado uma NFe autorizada em 2026-04
Quando passamos por 5 anos sem alterações no XML
E inspeciono o storage
Então o XML original ainda está lá, com hash SHA256 batendo com `nfe_emissoes.xml_hash`
```

**Implementação:** Storage `storage/app/nfe-brasil/{business_id}/xmls/{ano}/{mes}/{chave}.xml` (read-only após escrita); `xml_hash` na tabela; rotina diária verifica integridade.
**Testado em:** `XmlImutabilidadeTest`.

### R-NFE-011 · Schema CBS/IBS vazio em 2026, preenchido conforme legislação

```gherkin
Dado uma NFe emitida em 2026-04 com regime tradicional
Quando inspeciono `nfe_fiscal_rules.cbs_*` e `nfe_fiscal_rules.ibs_*`
Então estão NULL
E o XML emitido NÃO inclui blocos CBS/IBS
```

**Implementação:** Schema flexível; `MotorTributarioService` skip CBS/IBS se rule é NULL.
**Testado em:** `MotorTributarioCbsIbsNullTest`.

### R-NFE-012 · Não-emissão NÃO bloqueia venda (assíncrono)

```gherkin
Dado SEFAZ está fora E contingência não está ativa
Quando Larissa finaliza venda no POS
Então a venda é gravada (`transaction.payment_status = paid`)
E aparece alerta vermelho "NFe pendente — ativar contingência?"
E job retentativa fica na fila
```

**Implementação:** `EmitirNfceJob` em queue retry exponencial; UI vê fila em `/nfe-brasil/monitor`.
**Testado em:** `EmissaoAssincronaTest`.

### R-NFE-013 · Audit log Spatie em toda mutação fiscal

```gherkin
Dado qualquer emissão / cancelamento / CCe
Quando consulto activity_log
Então existe row com causer + subject + properties (chave, cStat, valor)
```

**Implementação:** Trait `LogsActivity` em `NfeEmissao`, `NfeEvento`, `Certificado`, `FiscalRule`.
**Testado em:** `AuditoriaNfeTest`.

### R-NFE-014 · Webhook SEFAZ idempotência (consulta status)

```gherkin
Dado uma consulta `WS-Consulta-Cadastro` retorna duas vezes (rede instável)
Quando processo as 2 respostas
Então `nfe_consultas.cache_key` UNIQUE bloqueia a 2ª de processar de novo
```

**Implementação:** Cache de consulta com TTL + UNIQUE em `consultas` por chave + tipo.
**Testado em:** `ConsultaIdempotenciaTest`.

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
