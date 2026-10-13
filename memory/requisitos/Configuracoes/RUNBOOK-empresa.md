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
3. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W] — não é desta thread.

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS. Estados: carregado · salvando · aviso de sucesso/erro (`status` da sessão) · sem permissão (403).
Trilho vira seletor abaixo de 768px. `data-contract`: `page-header`, `abas`, `aba-<id>`, `salvar`.

## 9. DoD checklist

- [x] RUNBOOK + paridade · [x] Pest baseline (tenant 98 × 99)
- [x] F3-1: ramo Inertia atrás da flag + Page (aba Negócio) + charter + casos + `EmpresaContratoTest`
- [x] F3-2: abas Impostos, Produtos e Contatos (UC-EMP-06)
- [x] F3-3a: Vendas e Pagamento (UC-EMP-08)
- [x] F3-3b: Venda no PDV + balança (UC-EMP-09)
- [ ] F3-3c: Compras · depois Sistema e Integrações
- [ ] Smoke biz=1 (F4) · cutover (F5, decisão [W])

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
- Precisão de moeda/quantidade e margem padrão mexem em VALOR mostrado e sugerido: qualquer mudança no tratamento
  desses campos cai na regra mestre de cálculo (`proibicoes.md`).
- O logo sobe por `business_logo` (multipart): o salvar da Page precisa ser `forceFormData`.
- O redirect é sempre `/business/settings` com `status` na sessão.
- **Balança grava o índice, não o número de dígitos (2026-10-13).** `settings_weighing_scale` usa `Form::select(..., [1..n])`,
  cuja chave é o índice 0-based; o `SellPosController::__parseWeighingBarcode` lê `índice + 1`. Seletor que gravar o rótulo
  muda a quantidade de toda venda por balança. UC-EMP-09.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
