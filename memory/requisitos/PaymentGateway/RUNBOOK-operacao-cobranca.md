---
title: "RUNBOOK — Operação do PaymentGateway: flags de valor, webhook órfão, retorno CNAB e rotação de credencial"
module: PaymentGateway
owner: W
status: ativo
last_validated: "2026-11-19"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0170-bancos-nativos-top5-drivers-separados
---

# RUNBOOK · Operação do PaymentGateway

> Escrito em 2026-11-19 a partir do código em `main` @`bedeeb0`. Os lotes L0–L3 da auditoria de 2026-11-19 ainda estavam abertos e podem mudar o que está descrito aqui; antes de seguir um passo, confira o comando com `php artisan <comando> --help`. A lista de comandos que existem está em [CONTRACTS.md §5](CONTRACTS.md).

Quatro procedimentos. Os dois primeiros mexem em **valor** e seguem a REGRA MESTRE de `memory/proibicoes.md` (dupla confirmação, antes→depois apresentado ao [W], aprovação explícita). Ligar flag de valor é decisão do [W]; este runbook descreve como medir e como ligar, não decide.

Onde rodar: comandos de leitura e os `--dry-run` podem rodar no Hostinger por SSH (`memory/how-trabalhar.md` §SSH Hostinger). Testes nunca rodam no Hostinger nem na máquina local (`memory/proibicoes.md` §Ambiente).

---

## 1. Ligar uma flag que toca valor

As flags do módulo vêm do `.env` e são lidas por `config()` em `Modules/PaymentGateway/Config/config.php`. Ambas nascem `false`.

| Flag (`.env`) | Chave `config()` | O que passa a acontecer |
|---|---|---|
| `PAYMENTGATEWAY_RETRY_ORPHAN_WEBHOOKS_ENABLED` | `paymentgateway.retry_orphan_webhooks_enabled` | O agendamento `paymentgateway:retry-orphan-webhooks` (a cada 5 min, só em `live`, `app/Console/Kernel.php`) passa a executar; eventos órfãos com `cobranca_id` preenchido re-disparam `CobrancaPaga`, que quita título |
| `PAYMENTGATEWAY_LANCAR_PAGAMENTO_NA_VENDA` | `paymentgateway.lancar_pagamento_na_venda` | O listener `LancarPagamentoDaCobrancaNaVenda` passa a gravar `transaction_payments` na venda quando uma cobrança de origem `sale` é paga, e atualiza o `payment_status` da venda |

### 1.1 Antes de ligar `RETRY_ORPHAN_WEBHOOKS`

1. Medir o que o job faria, sem disparar nada:
   ```bash
   php artisan paymentgateway:retry-orphan-webhooks --dry-run
   ```
   Saída: total de órfãos na janela de 1h a 24h, quantos têm `cobranca_id` (esses quitam título) e uma linha por evento (`#id biz gateway evento cobranca_id created_at`). Lista até 50.
2. Para cada evento com `cobranca_id`, montar a tabela antes→depois: cobrança, título hoje (aberto/quitado) e o que o job faria. Evento cujo título já está quitado é duplicidade em potencial — o job depende de o listener `OnCobrancaPagaCreateFinanceiroTitulo` ser idempotente (aviso no docblock de `Jobs/RetryOrphanWebhookJob.php`).
3. Apresentar a tabela ao [W] e esperar o sim.

Pendências abertas com o [W] que afetam esta flag (auditoria 2026-11-19): B2 (valor pago diferente do valor da cobrança) e B3 (webhooks Asaas/C6/Sicoob/Pagar.me/Inter boleto hoje só gravados, não processados). Enquanto B3 estiver aberta, a tabela `gateway_webhook_events` recebe pouco e o dry-run tende a sair vazio — vazio não é prova de que é seguro ligar.

### 1.2 Antes de ligar `LANCAR_PAGAMENTO_NA_VENDA`

O listener não age para trás: só trata `CobrancaPaga` disparado depois de ligado. O risco é contar duas vezes um pagamento que alguém já registra à mão.

1. Perguntar a quem opera o caixa de cada empresa com cobrança de venda se hoje registra esses pagamentos à mão (o docblock do listener exige essa conferência).
2. Medir, por empresa, quantas cobranças de venda foram pagas nos últimos 30 dias e quantas dessas vendas já têm pagamento lançado. Só leitura:
   ```sql
   SELECT c.business_id,
          COUNT(*) AS cobrancas_pagas,
          SUM(EXISTS (SELECT 1 FROM transaction_payments tp
                      WHERE tp.transaction_id = c.origem_id
                        AND tp.business_id = c.business_id)) AS vendas_com_pagamento
   FROM cobrancas c
   WHERE c.origem_type = 'sale' AND c.status = 'paga'
     AND c.paga_em >= NOW() - INTERVAL 30 DAY
   GROUP BY c.business_id;
   ```
   Venda que já tem pagamento é o caso em que o listener recusaria por falta de saldo (`sem_saldo`) ou somaria se o lançamento manual for parcial. Não colar o resultado com valores em PR/commit (`memory/proibicoes.md`, valores BRL).
3. Apresentar o antes→depois ao [W]. A empresa dona do SaaS (`config('app.saas_owner_business_id')`) fica fora por código.

### 1.3 Ligar

1. No `.env` do Hostinger (não versionado), mudar a linha da flag para `true`.
2. Recarregar o cache de configuração, como o deploy faz:
   ```bash
   php artisan config:clear && php artisan config:cache
   ```
3. Conferir que o valor entrou:
   ```bash
   php artisan tinker --execute="dump(config('paymentgateway.retry_orphan_webhooks_enabled'), config('paymentgateway.lancar_pagamento_na_venda'));"
   ```
4. Registrar a mudança (data, flag, quem aprovou) no handoff da sessão — mudança de `.env` não aparece no git.

### 1.4 Acompanhar e desligar

- Retry: cada execução loga `paymentgateway.webhook.orphan_scan` (resumo) e `paymentgateway.webhook.orphan_replayed` / `paymentgateway.webhook.still_orphan` por evento; falha do agendamento loga `Schedule paymentgateway:retry-orphan-webhooks FALHOU` no canal `single`.
- Venda: lançamento recusado por saldo loga `paymentgateway.venda: cobrança paga maior que o saldo da venda — não lançada, conferir à mão`; erro loga `paymentgateway.venda: falha ao lançar o pagamento da cobrança na venda`. Pagamentos lançados têm `transaction_no` começando com `PG-COB-`.
- Desligar: voltar a flag para `false` no `.env` e repetir o passo 1.3.2. Pagamentos já lançados não são desfeitos.

---

## 2. Reprocessar webhook órfão à mão

Órfão é linha de `gateway_webhook_events` com `processed_at` nulo — o webhook chegou e não foi tratado (normalmente porque chegou antes da cobrança existir). Não existe comando `paymentgateway:replay-webhook`; o caminho é o mesmo comando do agendamento.

1. Listar: `php artisan paymentgateway:retry-orphan-webhooks --dry-run`. A janela é fixa: criados há mais de 1h e menos de 24h. Fora dela o job não olha.
2. Se houver eventos com `cobranca_id` e o [W] aprovar (é quitação de título — seção 1.1 passos 2 e 3), rodar sem `--dry-run`. O comando só despacha o job na fila `paymentgateway`; o resultado aparece no log quando o worker processa.
3. Evento sem `cobranca_id` continua órfão até sair da janela de 24h. Depois disso é conciliação manual: conferir a cobrança no banco e, se paga, usar o caminho da seção 3 (retorno) ou o polling PIX (`paymentgateway:inter-reconcile-pix --dry-run`, que lista o que marcaria pago).

A quitação PIX do Inter não passa por aqui: ela usa `inter_webhook_log` + `ProcessarWebhookPixInterJob`, e o polling `inter-reconcile-pix` a cada 10 min é o fallback.

---

## 3. Retorno CNAB

O arquivo de retorno do banco entra pela tela `/settings/payment-gateways/{credentialId}/cnab-retorno` (`Settings/PaymentGatewaysCnabRetornoController@store`), que valida o upload, grava em `cnab_retorno_uploads` e despacha `Jobs/CnabRetornoProcessor` na fila. A tela mostra o histórico de uploads com as contagens.

O que o processador faz por linha do arquivo (docblock de `Jobs/CnabRetornoProcessor.php`):

- acha a cobrança pelo nosso número dentro da empresa da credencial;
- liquidada → `CobrancaPaga`; baixada sem pagamento → `CobrancaCancelada`; vencida → `CobrancaVencida`; demais ocorrências só atualizam status;
- cobrança que já tem `paga_em` é pulada, então reenviar o mesmo arquivo não duplica;
- grava no upload as contagens (`paga`, `cancelada`, `vencida`, `registrada`, `ignorada`, `sem_match`) e os erros, e um registro em `gateway_webhook_events` com origem `cnab_retorno_upload`.

Quando algo dá errado:

| Sintoma | O que olhar |
|---|---|
| Upload aceito e contagens não aparecem | worker da fila parado; o job tenta 3 vezes com 60 s de intervalo |
| `sem_match` alto | nosso número do arquivo não bate com `cobrancas.nosso_numero` daquela empresa — arquivo de outra conta/convênio ou cobrança emitida fora do sistema |
| Cobrança paga no banco e aberta aqui | conferir se ela está no arquivo enviado; se estiver em `sem_match`, a cobrança não existe nesta empresa |

Reprocessar = enviar o mesmo arquivo de novo pela tela. Não há comando de reprocessamento.

---

## 4. Rotação de credencial / troca de `APP_KEY`

`payment_gateway_credentials.config_json` é cifrado com a `APP_KEY` (cast `encrypted:array`, US-PG-001). Trocar a `APP_KEY` sem preparo torna as credenciais ilegíveis.

`paymentgateway:rewrap-credentials` cifra as linhas que ainda estão em JSON puro; linha já cifrada é pulada. Ele **não** decifra com uma chave antiga e recifra com a nova — para isso não existe comando.

1. Ver o estado: `php artisan paymentgateway:rewrap-credentials` (dry-run é o padrão; `--business=N` restringe). Tabela `id | business_id | gateway | ambiente | status`, com `encrypted`, `would_wrap` ou `error`.
2. Se houver `would_wrap`, aplicar: `php artisan paymentgateway:rewrap-credentials --apply`. Cada execução grava uma linha em `mcp_audit_log`.
3. Trocar a credencial de um banco (segredo vazado ou expirado): editar pela tela `/settings/payment-gateways`, que regrava cifrado, e usar o teste de conexão da mesma tela.
4. Se a `APP_KEY` mudou e as credenciais deram `error`, recadastrar os segredos pela tela (o `RUNBOOK-sicoob-api.md` registra o mesmo sintoma para a senha do certificado).

Segredos não vão para PR, commit nem chat; consultar `memory/_INDEX-SECRETS.md` antes de procurar um.
