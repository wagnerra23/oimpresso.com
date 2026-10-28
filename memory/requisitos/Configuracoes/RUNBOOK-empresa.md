---
slug: configuracoes-runbook-empresa
title: "Configurações — Runbook da tela Configuração da empresa"
type: runbook
module: Configuracoes
tela: Configuracoes/Empresa/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Configuração da empresa (`/business/settings`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `sistema/playbook/08`
> **Fonte de design:** `prototipo-ui/cowork/Wagner/configuracoes-page.jsx` → `Empresa()` (rota `cfg-empresa`), abas em `ABAS` agrupadas em Empresa · Operação · Sistema · Integrações.
> **Decisão D1 ([W] 2026-10-06):** Configurações com abas; cada aba mantém URL e permissão próprias.

## Estado final esperado

`GET /business/settings` (`BusinessController::getBusinessSettings`) responde Inertia `Configuracoes/Empresa/Index`
**quando a flag `useV2ConfiguracoesEmpresa` está ligada** para o negócio; desligada, segue a Blade `business/settings`.
O salvar continua sendo `POST /business/update` (`postBusinessSettings`), sem mudança.

## 1. Objetivo

Trocar a Blade de ~16 abas pela tela React com trilho de abas do protótipo, sem mudar o que é gravado nem a permissão.

## 2. Pré-condições

- Permissão: `business_settings.access` (tela e salvar).
- Tudo vem do negócio da sessão (`user.business_id`); não há id na URL, então não existe acesso a outro negócio por parâmetro.

## 3. Passo-a-passo

1. **F1/F2 (PR 1):** este RUNBOOK + [`empresa-parity.md`](./empresa-parity.md) + `EmpresaBaselineTest`.
2. **F3 (PRs seguintes, ≤300 linhas cada):** ramo Inertia atrás da flag + Page com o trilho de abas e o formulário
   **completo** (ver §10). Fatiar por grupo de abas do protótipo: Empresa (Negócio, Impostos, Produtos, Contatos) →
   Operação (Vendas, PDV, Compras, Pagamento) → Sistema (Painel, Sistema, Prefixos) → Integrações (E-mail, SMS,
   Fidelidade, Módulos, Rótulos). Enquanto um grupo não estiver na Page, os valores dele viajam como campos ocultos.
3. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W]. Em 2026-10-27 [W] decidiu ligar para
   todas as empresas de uma vez ("Pode iniciar todas as ondas e coloque em produção"); sem GrowthBook por empresa, o
   F4 isolado não existia, e o smoke biz=1 foi feito já com a flag ligada para todos.

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS. Estados: carregado · salvando · aviso de sucesso/erro (`status` da sessão) · sem permissão (403).
Trilho vira seletor abaixo de 768px. `data-contract`: `page-header`, `abas`, `aba-<id>`, `salvar`.

## 9. DoD checklist

- [x] RUNBOOK + paridade · [x] Pest baseline (tenant 98 × 99)
- [x] F3-1: ramo Inertia atrás da flag + Page (aba Negócio) + charter + casos + `EmpresaContratoTest`
- [x] F3-2: abas Impostos, Produtos e Contatos (UC-EMP-06)
- [x] F3-3a: Vendas e Pagamento (UC-EMP-08)
- [x] F3-3b: Venda no PDV + balança (UC-EMP-09)
- [x] F3-3c: Compras (UC-EMP-10) — grupo Operação completo
- [x] F3-4: Sistema — Painel, Sistema, Prefixos (UC-EMP-11)
- [x] F3-5a: E-mail e SMS (UC-EMP-12)
- [x] F3-5b: Pontos de fidelidade + Módulos (UC-EMP-13)
- [x] F3-5c: Rótulos personalizados (UC-EMP-14) — **as 16 abas estão na tela nova**
- [x] Financeiro: régua de cobrança (UC-EMP-15)
- [x] App das lojas (UC-EMP-17) — **18 de 18 abas da Blade** na tela nova (medido 2026-10-26: 19 parciais `business/partials/settings_*.blade.php`; a balança é seção da aba Venda no PDV)
- [x] Cutover (F5, [W] 2026-10-27): `useV2ConfiguracoesEmpresa => true` no `$fallbackDefaults` do `FeatureFlagService`;
  rollback = remover a linha (ou flag OFF no GrowthBook) · `?classico=1` abre a Blade · `#pos` abre a aba Venda no PDV (UC-EMP-18)
- [x] Smoke em produção biz=1 com a flag ligada para todos (18 abas + `?classico=1` + salvar sem mudar nada = linha `business` igual)
  - **Medido em 2026-10-27, autorizado por [W].** "Salvar sem mudar nada" não é possível: o botão Salvar fica desabilitado enquanto nenhum campo muda. A prova foi uma ida e volta em `transaction_edit_days` (150 → 151 → 150), com o md5 de cada uma das 132 colunas da linha `business` id=1 (sem `updated_at`) tirado antes, no meio e no fim.
  - **128 colunas idênticas** no fim, incluindo `transaction_edit_days` de volta a 150.
  - **`email_settings` e `sms_settings`**: só a ordem das chaves mudou. O salvar recoloca as chaves secretas no fim do JSON. Prova: o JSON atual, com cada segredo de volta logo após o seu par (`mail_password` após `mail_username` etc.), reproduz o md5 de antes byte a byte.
  - **`pos_settings`**: mesmas chaves e mesmos valores. As flags de liga/desliga passaram de número (`0`/`1`) a texto (`"0"`/`"1"`), porque a tela nova envia todas as chaves e a Blade só enviava as marcadas (o controller completava as outras com o `0` numérico de `defaultPosSettings`). Prova: o JSON atual com essas flags convertidas para inteiro e os 2 segredos reposicionados reproduz o md5 de antes. Sem efeito em comportamento: todo leitor PHP compara essas flags com `== 0`, `!= 0` ou `empty()`, em que `"0"` e `0` dão o mesmo resultado, e nenhum código de front as lê (varredura em `origin/main` de `*.php`, `resources/js/**` e `public/js/*.js`).
  - As 3 colunas JSON saíram iguais no 2º salvar e no 1º, ou seja, a gravação é estável.

## 10. Pegadinhas

- **O salvar é substituição total.** `postBusinessSettings` zera o que não veio: os 12 checkboxes de `$checkboxes`,
  `enable_rp`, `enable_tooltip`, `enable_product_expiry`, `purchase_in_diff_currency`; `common_settings` vira `[]`,
  `enabled_modules` vira `null`, `keyboard_shortcuts` vira `"null"` e `pos_settings` volta ao padrão nas chaves ausentes.
  A Page **tem de mandar o formulário inteiro**, inclusive as abas que ainda não desenhou. Preso por
  `EmpresaBaselineTest` (teste da substituição).
- `on_product_expiry` e `custom_labels` são lidos sem checagem: corpo sem eles cai no `catch` (aviso genérico de erro).
- `email_settings` e `sms_settings` guardam senha e segredo (SMTP, Nexmo/Twilio). Props do Inertia ficam no HTML da
  página: a F3 não pode mandar esses segredos em claro — mandar vazio e o salvar preservar quando vier vazio exige
  mudar o controller, decisão a registrar no PR da aba Integrações.
  **Atualização 2026-10-13 (F3-1):** não exige. O `$request->only()` do salvar omite chave ausente, então o corpo da
  Page simplesmente não leva `email_settings`/`sms_settings` e eles ficam como estão (UC-EMP-01/04). A aba Integrações
  só precisa mandá-los quando o usuário editar ali.
- **Segredo de pagamento em `pos_settings` (2026-10-13):** `stripe_secret_key` e `razor_pay_key_secret` moram em
  `pos_settings`, que o salvar sobrescreve inteiro. O corpo não as leva e o `postBusinessSettings` mantém o gravado quando
  a chave **não vem** (a Blade sempre manda as duas, então limpar por ela continua igual). UC-EMP-07.
- **Segredos de e-mail e SMS (F3-5a, 2026-10-13):** mesma regra para `mail_password`, `nexmo_secret`, `twilio_token` e os
  valores de cabeçalho/parâmetro do SMS próprio (`header_val_*`, `param_val_*`, onde costuma ir o token da API). A Blade
  sempre manda todos (os `@if` de `settings_sms` só escondem por classe). Testar envio continua pela tela antiga, porque o
  teste usa a senha do formulário. UC-EMP-12.
- **Valor por ponto 0 divide por zero (achado 2026-10-13, não corrigido).** `TransactionUtil.php:4694` faz
  `floor($total / $amount_for_unit_rp)`. O salvar troca vazio por 1, mas aceita 0 — na Blade (`"0,00"`) e na tela nova.
  Barrar 0 muda valor aceito: decisão [W].
- Precisão de moeda/quantidade e margem padrão mexem em VALOR mostrado e sugerido: qualquer mudança no tratamento
  desses campos cai na regra mestre de cálculo (`proibicoes.md`).
- O logo sobe por `business_logo` (multipart): o salvar da Page precisa ser `forceFormData`.
- O redirect é sempre `/business/settings` com `status` na sessão.
- **Balança grava o índice, não o número de dígitos (2026-10-13).** `settings_weighing_scale` usa `Form::select(..., [1..n])`,
  cuja chave é o índice 0-based; o `SellPosController::__parseWeighingBarcode` lê `índice + 1`. Seletor que gravar o rótulo
  muda a quantidade de toda venda por balança. UC-EMP-09.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
