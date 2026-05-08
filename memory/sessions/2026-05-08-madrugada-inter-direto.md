# Sessão 2026-05-08 madrugada — Inter direto (4 PRs Open Finance)

**Modelo:** Opus 4.7
**Duração:** ~4h
**Branch base:** `main`
**PRs mergeados:** [#206](https://github.com/wagnerra23/oimpresso.com/pull/206) · [#210](https://github.com/wagnerra23/oimpresso.com/pull/210) · [#213](https://github.com/wagnerra23/oimpresso.com/pull/213) · [#221](https://github.com/wagnerra23/oimpresso.com/pull/221)
**US fechadas:** US-RB-045 · US-RB-046 · US-RB-047

## Origem

Wagner mandou link `https://github.com/FinAegis` perguntando relevância pra Open Finance no oimpresso. Análise rápida descartou a org (Open Banking europeu PSD2, não Open Finance Brasil). Wagner ajustou pedido pra "ter acesso a extrato, boleto, PIX direto" — i.e., conectar Inter PJ direto via API própria, sem virar AISP/PISP regulado.

Discovery encontrou que `Modules/RecurringBilling` já tinha **~70% do esqueleto Inter** pronto:
- `InterDriver` boleto via `eduardokum/laravel-boleto` (mTLS funcional)
- `BoletoCredential` model + `rb_boleto_credentials` (multi-tenant, config_json criptografado)
- `SyncBankBalancesJob` (com `'inter' => null` TODO desde sempre)
- `AsaasWebhookController` com pattern de webhook idempotente

Plano em 3 fases proposto e aprovado.

## Entregas

### Fase 1 — Saldo Inter ([#206](https://github.com/wagnerra23/oimpresso.com/pull/206)) · ~2h

Quick win pra validar cert mTLS + OAuth ponta-a-ponta.

- `Modules/RecurringBilling/Services/Banking/InterBankingClient`:
  - `oauthToken(scope)`: OAuth client_credentials + cache 50min por `(business_id, scope)`
  - `getSaldo()`: `GET /banking/v2/saldo` com Bearer + mTLS, retorna `{disponivel, bloqueado, limite}`
- Wire `SyncBankBalancesJob::fetchInterSaldo()` substituindo `'inter' => null`
- Pest 7 cenários: saldo · cache hit · isolamento multi-tenant por business · isolamento por scope · 401 propaga · cert temp 0600 idempotente
- SPEC.md registra US-RB-045/046/047 com blocked_by encadeado

**SoC ADR 0094 §5:** `InterBankingClient` separado do `InterDriver` porque Banking API v2 usa Http nativo Laravel (testável via `Http::fake`), enquanto `eduardokum/laravel-boleto` usa cURL próprio (impossível mockar).

### Fase 2 backend — Extrato Inter ([#210](https://github.com/wagnerra23/oimpresso.com/pull/210)) · ~3h

- DTO `StatementLineDto` (Carbon data, valor, tipo C/D, descricao, contraparte, idempotency_key, raw)
- Contract `BankStatementDriverContract::fetchStatement(from, to): Collection<StatementLineDto>`
- `InterStatementDriver` adapta extrato Inter v2 → DTO com fallback hash quando `idTransacao`/`endToEndId` ausentes
- `InterBankingClient::getExtrato(from, to)` com paginação 100/pg cap 10pg
- Migration `fin_extrato_lancamentos` em `Modules/Financeiro` com UNIQUE `(conta_bancaria_id, idempotency_key)` — re-sync seguro
- Model `ExtratoLancamento` com `BusinessScope` global (multi-tenant Tier 0)
- `SyncBankStatementsJob(?contaBancariaId, diasRetro=7)` upsert idempotente, agendado daily 07:00 BRT (live env) em `app/Console/Kernel.php`
- Pest backend: parse PIX crédito · parse débito boleto · fallback hash idempotency · paginação 2 páginas · sync novos · idempotência (2× = mesma row count) · multi-tenant · skip non-Inter · on-demand contaId

### Fase 2 frontend — Tela /financeiro/extrato ([#213](https://github.com/wagnerra23/oimpresso.com/pull/213)) · ~2h

- `ExtratoController::index($contaBancariaId)` busca lançamentos (default últimos 30d, query `?from&to`), monta totais
- Rota nova `/financeiro/extrato/{contaBancariaId}` no web group existente
- Permissão `financeiro.extrato.view` em DataController + lang string PT-BR
- Page Inertia/React `Financeiro/Extrato/Index.tsx`: 4 cards (saldo · créditos · débitos · count) + filtro de período + tabela com cor por tipo C/D + empty state
- Pest controller test: 200 com auth · 404 cross-tenant · filtro from/to
- **Bonus**: phpunit.xml registra `Modules/Financeiro/Tests/Feature` que estava como falsa cobertura
- **Hotfix**: drift pré-existente `ProjectMgmt/SearchController` adicionado a SCOPE.md pra desbloquear scope-guard CI

### Fase 3 — PIX cob imediata + webhook ([#221](https://github.com/wagnerra23/oimpresso.com/pull/221)) · ~2h

Risco mais alto da feature: endpoint público + dinheiro real.

- DTO `PixCobResult` (txid, status, valor, pixCopiaECola, qrcodeBase64, expiracao, raw)
- `InterBankingClient::criarCobImediata(txid, body)`: `PUT /cobranca/v3/cob/{txid}` com escopo `cob.write`
- `InterBankingClient::getQrCodeBase64(txid)`: `GET .../qrcode` com `cob.read`
- `InterPixCobDriver` adapta com defaults (chave PIX recebedor + expiração 1h + truncate `solicitacaoPagador` 140 chars BR Code)
- Rota pública `POST /webhooks/inter/pix/{businessId}` (matches `/webhook/*` no `VerifyCsrfToken::$except`)
- `InterWebhookController` valida `X-Inter-Webhook-Secret` (timing-safe `hash_equals`) contra `BoletoCredential.config_json.webhook_secret` antes de processar
- 404 sem credencial Inter ativa, 401 secret diverge, multi-tenant Tier 0 (secret de biz 1 NÃO funciona em biz 2)
- Idempotência via UNIQUE `pg_webhook_events.(provider='inter', event_id=endToEndId)` — pattern reusado do Asaas
- `ProcessInterWebhookJob`: atualiza `rb_invoices` por txid (status → paid) · grava `account_transactions` (credit) na conta Inter via `insertOrIgnore` · increment `saldo_cached` · dispara `InvoicePaid` event pra NfeBrasil emitir NFe55 automaticamente
- Pest 9 cenários adversariais: 401 sem secret · 401 errado · 401 cross-tenant · 404 sem cred · 404 cred inativa · 200 dispatch · idempotência · múltiplos PIX (3 endToEndId distintos = 3 dispatches) · sem endToEndId skipa · queue correta `rb_webhooks`

## Incidentes

### Conflito com Cursor (sessão paralela)

Wagner estava usando Cursor numa branch ProjectMgmt fase 2. Cada vez que eu salvava arquivos no repo origem `D:\oimpresso.com`, em poucos segundos o Cursor fazia `git checkout` em outra branch e descartava meu trabalho não-commitado. Detectei via 3 system-reminders consecutivos avisando "intentional revert".

**Solução:** `git worktree add .claude/worktrees/inter-pix-fase3 -b claude/inter-pix-fase3-v2 origin/main`. Worktree isolado tem seu próprio working tree, imune ao checkout do Cursor. Padrão a adotar sempre que Cursor estiver visivelmente trabalhando em paralelo.

### Quota Anthropic crash mid-flight

Trabalho de Fase 3 inicial perdido quando quota acabou. Recovery rápido: re-aplicar tudo via worktree dedicado, commit early/often.

### check-scope CI fail

`Modules/Financeiro/SCOPE.md` não tinha `ExtratoController`. Constituição v2 ADR 0094 §7 (Module Charter) exige todo Controller declarado. Fix em hotfix dentro do PR #213. Drift adicional `ProjectMgmt/SearchController` (não meu trabalho) também consertado pra desbloquear o gate.

## Pendências pra ativar em prod

1. Wagner libera 4 escopos no portal Inter: `extrato.read` · `cob.read` · `cob.write` · `webhooks.write`
2. Onboarding gera `webhook_secret` aleatório, salva em `BoletoCredential.config_json`
3. Configurar webhook no Inter via `PUT /webhooks/pix-recebidos` apontando pra `https://oimpresso.com/webhooks/inter/pix/{businessId}` com header `X-Inter-Webhook-Secret`
4. Smoke: tinker → criar cob → mandar PIX → confirmar `InvoicePaid` dispara e NfeBrasil emite NFe55

## Refs

- ADR 0094 §5 (SoC brutal: banking ≠ boleto) · §6 (multi-tenant Tier 0 IRREVOGÁVEL) · §7 (Module Charter)
- ADR 0093 (multi-tenant Tier 0)
- Pattern webhook: `Modules/RecurringBilling/Http/Controllers/AsaasWebhookController.php`
- Tabela idempotência cross-provider: `pg_webhook_events`
- Lib boleto+PIX charging (não Banking): `eduardokum/laravel-boleto`
