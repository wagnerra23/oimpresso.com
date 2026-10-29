---
id: resources-js-pages-financeiro-contas-bancarias-index-casos
casos: Contas bancárias · /financeiro/contas-bancarias
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/contas-bancarias

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Tela `live` (CNAB + beneficiário). Credenciais de gateway **não** moram aqui (ADR 0170).

> ⚠️ **Âncora podre encontrada:** o charter cita `FinanceiroContasBancariasCharterTest` em "Métricas vivas", mas **não existe** arquivo com esse nome em `Modules/Financeiro/Tests/` (medido 2026-08-17). As provas reais são `ContaBancariaIndexTest` e `UpsertContaBancariaRequestTest`. Corrigir o charter no mesmo PR.

## UC-CTB-01 — Listar contas com status honesto
Status: 🧪 (`tests/Feature/Modules/Financeiro/ContaBancariaIndexTest.php`)
Quando abre a tela · Então cada conta mostra beneficiário, carteira e um StatusBadge que diz a verdade: "Faltam dados" · "Inativo" · "Ativo · Cart. X".

## UC-CTB-02 — Configurar boleto valida os dados do beneficiário
Status: 🧪 (`UpsertContaBancariaRequestTest`)
Quando salva o `ConfigurarBoletoSheet` · Então banco/agência/carteira/beneficiário são validados (CEP, UF) e o upsert grava sem emitir nada.

## [BACKLOG] Credencial de gateway nunca é persistida aqui
Status: ⬜ sem prova — nenhum teste posta campo gateway_* pra provar o descarte. Vira `UC-CTB-03` quando existir teste citando o id (G-2).
Um POST com campos de credencial ainda salva os dados bancários e **descarta** a credencial (o cofre é `payment_gateway_credentials`).

## UC-CTB-04 — Tier 0
Status: 🧪 (`MultiTenantIsolationTest`)
Conta de outro business não aparece nem é editável ([ADR 0093]).

## Cadastro da conta (tela única de contas — decisão [W] 2026-10-27)

Desde 2026-10-27 esta tela também cadastra a conta do núcleo (`accounts`), antes feita em `/account/account`
(decisão [W], opção A). Fontes dos casos: a decisão do [W], o `RUNBOOK-contas-bancarias.md`, o charter v3 e a paridade
do cadastro antigo (`memory/requisitos/Configuracoes/contas-parity.md`); o código só confirma. O saldo e o saldo de
abertura saem do mesmo serviço do núcleo (`App\Services\Contas\ContasDoNucleo`).

**Onde roda:** `tests/Feature/Configuracoes/ContasNoFinanceiroContratoTest.php`, lane `acessos-pest` (MySQL), que roda a
pasta `tests/Feature/Configuracoes/`. Tenant 98 (`seededTenant()`) × 99 (`seededSupportClientTenant()`).

---

## UC-CTB-05 · A lista traz só as contas da empresa, com o saldo do núcleo
- **Persona:** financeiro da empresa abrindo `/financeiro/contas-bancarias`.
- **Aceite:** Dado uma conta da empresa 98 com crédito de 1.000,50, débito de 200,25 e um crédito apagado de 999,00, e uma conta da empresa 99 · Quando abro a tela · Então só a conta da 98 aparece e o saldo dela é 800,25 — igual à conta feita à mão (dois caminhos) e com o texto formatado pelo servidor.
- **Teste:** `tests/Feature/Configuracoes/ContasNoFinanceiroContratoTest.php` — `UC-CTB-05 a lista traz só as contas da empresa, com o saldo do mesmo SQL do núcleo — por dois caminhos`.
- **Regressão que defende:** regra mestre de valor (o saldo não ganha segunda conta) e Tier 0 (lista de outra empresa).
- **Status: ⬜** — aguardando o CI do PR #10046.

---

## UC-CTB-06 · Nova conta com saldo de abertura
- **Persona:** financeiro cadastrando uma conta nova.
- **Aceite:** Dado o formulário com nome, número, saldo de abertura "1.234,56", um detalhe preenchido e um vazio, e `business_id` de outra empresa no pedido · Quando salvo · Então a conta nasce na empresa da sessão, o detalhe vazio é descartado e é gravado um crédito `opening_balance` de 1.234,56 — o mesmo valor que o `num_uf` do núcleo dá para o texto (dois caminhos).
- **Teste:** `ContasNoFinanceiroContratoTest.php` — `UC-CTB-06 nova conta grava em accounts da empresa da sessão, com o saldo de abertura pelo num_uf — por dois caminhos`.
- **Regressão que defende:** paridade com o cadastro antigo (`AccountController::store` + `num_uf`) e Tier 0 (`business_id` só da sessão).
- **Status: ⬜** — aguardando o CI do PR #10046.

---

## UC-CTB-07 · Sem saldo de abertura, sem lançamento
- **Persona:** financeiro cadastrando conta sem saldo inicial.
- **Aceite:** Dado o formulário só com o nome · Quando salvo · Então a conta não ganha nenhum lançamento.
- **Teste:** `ContasNoFinanceiroContratoTest.php` — `UC-CTB-07 sem saldo de abertura não nasce lançamento`.
- **Regressão que defende:** lançamento de valor zero ou fantasma no extrato da conta.
- **Status: ⬜** — aguardando o CI do PR #10046.

---

## UC-CTB-08 · Tipo de conta de outra empresa é recusado
- **Persona:** usuário mal-intencionado enviando o id de um tipo de outra empresa.
- **Aceite:** Dado um tipo de conta da empresa 99 · Quando a empresa 98 cria conta com esse tipo · Então dá erro no campo do tipo e nenhuma conta é gravada.
- **Teste:** `ContasNoFinanceiroContratoTest.php` — `UC-CTB-08 tipo de conta de outra empresa é recusado`.
- **Regressão que defende:** Tier 0 (ADR 0093) — referência cruzada entre empresas.
- **Status: ⬜** — aguardando o CI do PR #10046.

---

## UC-CTB-09 · Editar muda o cadastro e não toca no saldo
- **Persona:** financeiro corrigindo nome e observação de uma conta.
- **Aceite:** Dado uma conta da 98 com crédito de 50 e uma conta da 99 · Quando edito a da 98 · Então nome e observação mudam e o saldo continua 50; Quando tento editar a da 99 · Então 404 e ela fica intacta.
- **Teste:** `ContasNoFinanceiroContratoTest.php` — `UC-CTB-09 editar muda o cadastro e não toca no saldo; conta de outra empresa dá 404`.
- **Regressão que defende:** regra mestre de valor (editar não mexe em saldo) e isolamento 98×99 na escrita.
- **Status: ⬜** — aguardando o CI do PR #10046.

---

## UC-CTB-10 · Encerrar e reativar
- **Persona:** financeiro encerrando uma conta que não usa mais.
- **Aceite:** Dado uma conta ativa da 98 · Quando encerro · Então ela sai das ativas e aparece em "encerrada"; Quando reativo · Então volta às ativas; Quando tento encerrar uma conta da 99 · Então 404 e ela continua ativa.
- **Teste:** `ContasNoFinanceiroContratoTest.php` — `UC-CTB-10 encerrar tira da lista de ativas, reativar devolve; outra empresa dá 404`.
- **Regressão que defende:** paridade com encerrar/reativar do cadastro antigo (conta não se apaga, o movimento fica) e isolamento 98×99.
- **Status: ⬜** — aguardando o CI do PR #10046.

---

## UC-CTB-11 · Sem permissão, não cria
- **Persona:** usuário sem `financeiro.contas_bancarias.manage`.
- **Aceite:** Dado um usuário da 98 sem a permissão · Quando tenta criar conta · Então 403 e nada é gravado.
- **Teste:** `ContasNoFinanceiroContratoTest.php` — `UC-CTB-11 sem a permissão do Financeiro, criar dá 403`.
- **Regressão que defende:** gate do controller (Camada 3 Spatie, permissão do Financeiro).
- **Status: ⬜** — aguardando o CI do PR #10046.

---

## Juros e multa da carteira no boleto (Sabor Brasil, item 4 do plano de migração)

Os percentuais moram no `config_json` da credencial do banco (a carteira): `juros_mes_pct`
(% ao mês) e `multa_pct` (% do valor). No Delphi vinham de `CONTAS.JUROS` e `CONTAS.MULTA`.
Teste de todos: `Modules/PaymentGateway/Tests/Feature/JurosMultaCarteiraBoletoTest.php`.

## UC-CTB-12 · A carteira diz juros ao mês e multa em %
- **Aceite:** Dado `juros_mes_pct = 1` e `multa_pct = "2,00"` · Então o boleto leva 1,00% a.m. e 2,00%; Quando o valor é 0, negativo, acima de 100 ou não é número · Então aquele item sai isento.
- **Status: ⬜** — aguardando o CI do PR.

## UC-CTB-13 · BB: juros no segmento P, multa no R, desconto mantido
- **Aceite:** Dado a carteira BB com 1% a.m. e 2% e o cliente com 5% de pontualidade · Quando emite boleto de 1.234,56 · Então a remessa CNAB 240 tem P118 = 2, P127-141 = 1,00, R66 = 2, R75-89 = 2,00 e o desconto de 61,73 em P151-165.
- **Status: ⬜** — aguardando o CI do PR.

## UC-CTB-14 · BB: carteira sem juros e multa sai isenta
- **Aceite:** Dado a carteira sem as chaves · Então P118 = 3 e R66 = 0, valores zerados — o boleto de hoje.
- **Status: ⬜** — aguardando o CI do PR.

## UC-CTB-15 · Inter: mora ao mês e multa em % na API
- **Aceite:** Dado a carteira Inter com 1% a.m. e 2% · Quando emite · Então a API recebe `mora` TAXAMENSAL 1,00 e `multa` PERCENTUAL 2,00, junto do desconto de pontualidade.
- **Status: ⬜** — aguardando o CI do PR.

## Backlog de casos (sem id)
- **[BACKLOG] Sem side-effect ao abrir** — não testa conectividade de gateway, não emite boleto, não chama driver Inter/Asaas/C6.
- **[BACKLOG] Pill "Conta indefinida" (US-FIN-038 PR2/3)** — herdar da Unificada quando a baixa não tem conta vinculada.

## Pedidos de clientes

> Pedidos de clientes registrados com `node scripts/casos-backlog/novo.mjs`. Cada um aparece na
> página "Casos de uso por tela"; quando ganhar teste, vira caso de uso com id e a linha `Pedido de origem`.

- `[BACKLOG]` **Boleto do Banco do Brasil gerado pelo sistema** — Dado a conta do Banco do Brasil cadastrada
  com convênio e carteira, Quando o título a receber pede boleto, Então o boleto é registrado no banco, sai em
  PDF para o cliente e a baixa volta sozinha pelo retorno. O Sabor Brasil passou a emitir boleto pelo BB em
  2026; antes era pelo Inter (desde 2023). Pedido de Sabor Brasil.
  - Pedido: P-157
  - Âncora: Modules/PaymentGateway · BBCnabDriver
  - Fazer: Conferir o boleto BB de ponta a ponta (remessa, PDF e retorno) na conta do cliente; decidir se basta a remessa por arquivo ou se precisa de API.
  - Hoje: part | Em parte | O BB existe por arquivo de remessa (CNAB); o Inter tem API
  - Clientes: Sabor Brasil
  - Etiquetas: part

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork; achada a âncora podre do charter.
- 2026-10-27 · [CL] UC-CTB-05..11: cadastro da conta do núcleo nesta tela (decisão [W], opção A).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
- 2026-10-29 · [W+C] Pedido P-157 "Boleto do Banco do Brasil gerado pelo sistema" (Sabor Brasil), registrado com casos-backlog/novo.mjs. Sem id de UC.
- 2026-10-29 · [W+C] UC-CTB-12..15: juros e multa da carteira no boleto, Inter e BB (Sabor Brasil, item 4).
