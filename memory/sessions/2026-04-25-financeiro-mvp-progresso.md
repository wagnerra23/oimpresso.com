# Financeiro MVP — progresso parcial (2026-04-25)

> Sessão pausada com 95% de uso de tokens. Tudo commitado em `feat/financeiro-mvp`.
> Próxima sessão: rodar testes + ajustar fixtures dos bancos que falharem.

## O que foi feito

### Decisões (commitado)
- ADR TECH-0003: MVP via fork local de `eduardokum/laravel-boleto` v0.11.1 com mock de envio CNAB
- Tabela `fin_contas_bancarias` virou **complemento 1-1 da `accounts`** (core UltimatePOS) — opção C aprovada por Wagner

### Código (commitado)
- `lib-custom/laravel-boleto/` — fork da lib v0.11.1 com patch único: aceita Laravel 13 (composer.json constraint)
- `composer.json` raiz: path repository + `eduardokum/laravel-boleto: ^0.11.0`
- Migration `2026_04_24_140003_create_fin_contas_bancarias_table` reescrita: complemento 1-1 com FK pra `accounts.id`, campos de boleto (carteira, convênio, cedente, beneficiário, certificado A1)
- Migration `2026_04_25_140101_create_fin_boleto_remessas_table` nova
- `Modules\Financeiro\Models\ContaBancaria` reescrito com relação `belongsTo(Account::class)` + accessors `nome`/`numero_conta`
- `Modules\Financeiro\Models\BoletoRemessa` novo, com const STATUS_* e STRATEGY_*
- `Modules\Financeiro\Contracts\BoletoStrategy` interface (3 métodos: emitir, cancelar, statusAtual)
- `Modules\Financeiro\Strategies\CnabDirectStrategy` implementação com método público `gerarBoleto()` separado de `emitir()` pra facilitar teste sem DB
- `tests/Feature/Modules/Financeiro/CnabDirectStrategyContractTest.php` — itera 5 bancos prioritários (Bancoob/Sicoob, BB, Inter, C6, Itaú)

## Próximos passos (PRÓXIMA sessão)

### Imediato
1. **Rodar `./vendor/bin/pest tests/Feature/Modules/Financeiro/`** e ajustar fixtures dos bancos que falharem na validação da lib (cada banco tem regras próprias de carteira/conta/agencia/convenio)
2. Provavelmente precisa Pessoa endereço com tamanho mínimo, CEP no formato exato, etc — ler erros e calibrar

### Pendências de design (não bloqueante)
3. Definir como o título resolve `conta_bancaria_id` em produção:
   - opção A: campo nullable em `fin_titulos` (default null = pega conta default do business)
   - opção B: parâmetro explícito do service que orquestra emissão
4. Adicionar os outros 13 bancos da lib (Ailos, Banrisul, BNB, BTG, Bradesco, Caixa, Cresol, Delbank, Fibra, HSBC, Ourinvest, Pine, Rendimento, Santander, Sicredi, Unicred) ao contract test — cada um com sua fixture
5. Test de `emitir()` com persistência DB (idempotência por `(business_id, titulo_id, idempotency_key)`)
6. Test de `cancelar()` (status muda + metadata.cancelamento gravado)

### Onda 2 (depois MVP rodar verde)
7. Geração CNAB 240/400 remessa (transitar status `gerado` → `enviado`)
8. Parser CNAB retorno (transitar `enviado` → `pago`)
9. Tela `/financeiro/boletos` listando BoletoRemessa
10. Tela `/financeiro/contas-bancarias` (lista accounts + popula complemento)

## Como retomar

```bash
git checkout feat/financeiro-mvp
git pull
./vendor/bin/pest tests/Feature/Modules/Financeiro/CnabDirectStrategyContractTest.php
```

## Refs
- [memory/requisitos/Financeiro/adr/tech/0003-mvp-eduardokum-com-mock-cnab.md](../requisitos/Financeiro/adr/tech/0003-mvp-eduardokum-com-mock-cnab.md)
- [memory/requisitos/Financeiro/adr/arq/0003-strategy-pattern-boleto-cnab-vs-gateway.md](../requisitos/Financeiro/adr/arq/0003-strategy-pattern-boleto-cnab-vs-gateway.md)
- [memory/requisitos/Financeiro/ARCHITECTURE.md](../requisitos/Financeiro/ARCHITECTURE.md)
- Lib: https://github.com/eduardokum/laravel-boleto (fork local em `lib-custom/laravel-boleto/`)
