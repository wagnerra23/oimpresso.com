# ADR TECH-0004 (Financeiro) · Inter API v3 — InterApiStrategy + Webhook

- **Status**: proposed
- **Data**: 2026-06-03
- **Decisores**: Wagner (review), Eliana (autor)
- **Categoria**: tech
- **Relacionado**: ARQ-0003 (Strategy Pattern Boleto), TECH-0003 (MVP eduardokum mock)

## Contexto

ADR TECH-0003 entregou um `CnabDirectStrategy` em modo MOCK: gera linha digitável + PDF offline com `status='gerado_mock'`, sem nenhuma chamada bancária. Larissa (ROTA LIVRE) usa Sicoob, mas o cliente do Wagner (Office Impresso CNPJ 80281414) usa **Banco Inter (077)** e já tem **integração ativa configurada no portal** (nome "Oimpresso"/"Office Impresso", conta 219541078).

Pra Eliana ter boleto Inter funcionando ponta-a-ponta — registro no banco + baixa automática quando o cliente paga — faltam 3 capacidades que o MVP TECH-0003 não tem:

1. **Registro real do boleto na API Inter** (POST cobrança v3 + OAuth2 mTLS)
2. **Webhook receptor** pra `situacao=PAGO` → `TituloBaixa` automática
3. **Configuração persistida** das credenciais (client_id/secret/certificado) por conta bancária

## Decisão

**Construir `InterApiStrategy` reusando `Eduardokum\LaravelBoleto\Api\Banco\Inter` (versão 3) já presente no fork local.**

3 frentes mínimas:

### 1. Strategy real + Resolver

- `Modules\Financeiro\Strategies\InterApiStrategy` implementa `BoletoStrategy`. Delega HTTP/OAuth/mTLS pra lib eduardokum (`Api\Banco\Inter` v3, base URL `https://cdpj.partners.bancointer.com.br`).
- `BoletoStrategyResolver` substitui o binding direto no `FinanceiroServiceProvider`. Regra: `banco_codigo=077` + `inter_client_id_encrypted` + `certificado_path` + `certificado_chave_path` preenchidos → `InterApiStrategy`; senão → `CnabDirectStrategy` (mantém comportamento legado).
- Reusa `CnabDirectStrategy::gerarBoleto()` pra construir o objeto `BoletoInter` (mesmo formato de Pessoa/pagador/beneficiário/numero), então só muda quem persiste no Inter vs. quem gera só o PDF.

### 2. Webhook receptor com idempotência

- Rota pública sem auth: `POST /webhook/inter/{token}` (path `/webhook/*` já está no `$except` do `VerifyCsrfToken`).
- O `{token}` é o segredo. Armazenado em `fin_contas_bancarias.webhook_token` (CHAR 64 UNIQUE, gerado via `Str::random(64)`). Lookup O(1) identifica a conta e o business. Rotacionável via `--rotate` no comando.
- Throttling: `throttle:60,1` (60 req/min/IP — Inter manda em batch, é folga).
- Idempotência: `fin_inter_webhook_events` com `UNIQUE(business_id, event_hash)` onde `event_hash = sha256(json_encode(item))`. Inter v3 não expõe event_id estável; hash do item é o melhor proxy.
- Append-only (`delete()` lança `DomainException`).
- Mapeamento `situacao` → ação:
  - `PAGO|RECEBIDO|MARCADO_RECEBIDO` → cria `TituloBaixa`, atualiza `BoletoRemessa.status=pago`, recalcula `Titulo.valor_aberto` e `Titulo.status` (parcial/quitado).
  - `CANCELADO` → `BoletoRemessa.status=cancelado`.
  - `EXPIRADO` → `BoletoRemessa.status=vencido`.
  - `A_RECEBER` → só registra evento, sem mudança de estado.

### 3. Persistência das credenciais

- Novas colunas em `fin_contas_bancarias`:
  - `certificado_chave_path` (path do `.key` — par com `certificado_path` que é o `.crt`)
  - `inter_client_id_encrypted` (text, cast `encrypted`)
  - `inter_client_secret_encrypted` (text, cast `encrypted`)
  - `webhook_token` (char 64 UNIQUE nullable)
  - `webhook_registered_at` (timestamp nullable, marca quando `financeiro:inter:registrar-webhook` rodou com sucesso)
- 2 comandos artisan pra setup CLI (Sheet UI fica como follow-up):
  - `financeiro:inter:configurar-conta --conta=<id>` — wizard interativo que recebe client_id/secret/paths e grava encriptado
  - `financeiro:inter:registrar-webhook --conta=<id> [--rotate] [--dry-run]` — gera token, monta URL pública (`{APP_URL}/webhook/inter/{token}`), chama Inter via `PUT /cobranca/v3/cobrancas/webhook`

## Por que não criar `HybridStrategy` agora

ADR ARQ-0003 prevê `HybridStrategy` pra decidir banco-por-cliente. Não cabe ainda: Resolver simples por `ContaBancaria.banco_codigo` resolve nosso único cliente API real (Wagner). Quando aparecer 2° banco API (C6/Cora) o resolver vira `BancoStrategyRegistry` com map `banco_codigo → strategy class`. YAGNI no MVP.

## Por que não signature HMAC no webhook

A lib `Webhook\Banco\Inter` não lê nenhum header de assinatura — confirmado em `lib-custom/laravel-boleto/src/Webhook/Banco/Inter.php`. Inter v3 protege webhook por **secret no path** (modelo "URL-as-secret"). É segurança mais fraca que HMAC, mas:
- O token tem 64 chars random (entropia ~382 bits)
- HTTPS obrigatório protege em trânsito
- Rotação trivial via `--rotate`
- Vazamento detectável: logs com `token_prefix` (primeiros 8 chars) permitem auditoria

Se Inter publicar HMAC no futuro, adicionar é local: middleware antes do controller, sem mudar o resto.

## Riscos & open issues

- **Sheet UI não atualizada**: hoje só CLI. Eliana tem CLI no Hostinger via SSH. Wagner aprova; UI vira US-FIN-NN no próximo cycle.
- **Sem teste end-to-end automatizado**: a lib eduardokum não tem mocks pro `Api\Banco\Inter`. Smoke test manual obrigatório no deploy (ver checklist do PR).
- **Certificado A1 expira em 1 ano**: precisa monitor + comando `financeiro:inter:check-cert-expiry` no scheduler (TODO follow-up).
- **Hostinger não suporta workers persistentes pro Horizon** (INFRA.md §4). Baixa de boleto roda inline no webhook controller, sem fila — se ficar lento, mover pra job em CT 100 + adicionar `dispatch()`.

## Alternativas descartadas

- **Escrever client HTTP do zero**: rejeitada. Lib eduardokum já implementa OAuth2 + mTLS + payload v3 + retry. Reimplementar viola "não reinventar" do CLAUDE.md §5.
- **Usar pacote oficial Inter (Java/C#)**: rejeitada. PHP não tem SDK oficial. Lib eduardokum é o padrão de fato no ecossistema PHP brasileiro.
- **Gateway externo (Asaas/Iugu)**: descartada pra esse cenário. Cliente já tem conta Inter PJ ativa + integração registrada — passar por gateway agrega taxa sem valor.

## Referências

- Lib base: `lib-custom/laravel-boleto/src/Api/Banco/Inter.php` (v3 endpoints + scopes)
- Lib webhook parser: `lib-custom/laravel-boleto/src/Webhook/Banco/Inter.php`
- Portal Inter: `https://contadigital.inter.co/open-banking/gerenciamento-vans-apis`
- Docs Inter: `https://developers.inter.co/references/cobranca-bolepix`
