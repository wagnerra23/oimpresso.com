---
id: requisitos-superadmin-runbook-minha-assinatura
title: "RUNBOOK — /subscription (Minha assinatura · Blade → Inertia)"
module: Superadmin
tela: superadmin/MinhaAssinatura/Index
owner: W
status: ativo
last_validated: "2026-10-05"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/subscription` (Minha assinatura, Inertia/React)

F1 do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) da thread
**Superadmin/07**. É a tela que o **negócio** (não o superadmin) abre para ver o próprio plano e
escolher um pacote. A decisão D2 de [W] (2026-10-01) mantém a tela aqui no Superadmin.

- **Fonte de design:** não há protótipo para a visão do negócio (`superadmin-page.jsx` só desenha a
  visão do superadmin). A tela segue o PT de lista e as telas irmãs (`Pacotes/Index`).
- **Page:** `Modules/Superadmin/Resources/js/Pages/superadmin/MinhaAssinatura/Index.tsx`.
- **Rotas (não mudam):** `GET /subscription` → `index()` · `GET /subscription/{id}/pay` → `pay()`
  (segue **Blade**). `GET /all-subscriptions` (DataTables) e `GET /subscription/{id}` (modal) ficam
  sem consumidor na tela nova e **não** foram removidos nesta onda.

## 1. O que mudou no backend

- `index()` manda uma prop `assinatura` em `Inertia::defer`: assinatura ativa, próximas, aguardando
  aprovação, histórico do negócio, os dois quadros do recibo e o catálogo de pacotes.
- Valores saem **já formatados** por `moedaComoBlade()`: moeda do sistema, precisão e posição do
  símbolo do negócio, arredondamento do accounting.js. É o mesmo texto que a Blade mostrava depois
  do `__currency_convert_recursively`.
- Datas saem por `dataComoBlade()`, igual à diretiva `@format_date` (sem o deslocamento do `Util`).

## 2. Tier 0 — invariantes

- Tudo que é do negócio filtra por `session('user.business_id')`: ativa, próximas, aguardando e o
  histórico. Teste cross-tenant 98×99 em `SuperadminMinhaAssinaturaContratoTest`.
- Pacote privado (`is_private`) só aparece para quem tem `superadmin`, como na Blade.
- O pagamento não mudou: valor cobrado é calculado no servidor em `confirm()` e nos gateways.

## 3. Smoke prod (R1)

Após o deploy: abrir `https://oimpresso.com/subscription` com um usuário de negócio que tenha
`superadmin.access_package_subscriptions`, conferir os cartões, o histórico e o detalhe de uma linha.
Clicar em um pacote deve abrir a página de pagamento Blade. **Não** concluir pagamento real.

## 4. O que NÃO entrou

O pagamento (`pay.blade.php` + 9 parciais de gateway) segue Blade: cada parcial carrega o checkout
de um gateway e migrá-lo é outra onda, com prova de valor própria.
