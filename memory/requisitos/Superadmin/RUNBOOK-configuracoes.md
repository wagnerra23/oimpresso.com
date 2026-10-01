---
id: requisitos-superadmin-runbook-configuracoes
title: "RUNBOOK — /superadmin/settings (Configurações · Blade → Inertia)"
module: Superadmin
tela: superadmin/Configuracoes/Index
owner: W
status: ativo
last_validated: "2026-10-01"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/superadmin/settings` (Configurações, Inertia/React)

F1 do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) da
thread **Superadmin/05**, 2º dos 2 PRs (o 1º foi o Comunicador, #8342). A tela era
`superadmin::superadmin_settings.edit` (Blade + 8 abas AdminLTE + TinyMCE) e passa a `Inertia::render`.

- **Fonte de design:** [`prototipo-ui/cowork/Wagner/superadmin-page.jsx`](../../../prototipo-ui/cowork/Wagner/superadmin-page.jsx) → `ViewConfig()` (L1369).
- **Page:** `Modules/Superadmin/Resources/js/Pages/superadmin/Configuracoes/Index.tsx`.
- **Rotas (não mudam):** `GET /superadmin/settings` → `edit()` · `PUT /superadmin/settings` → `update()`.

## 1. O que mudou no backend

- `edit()` manda uma prop `Inertia::defer` (`config`): `valores` (chaves da lista fechada da tabela `system` + chaves NÃO secretas do `.env`), `segredos` (chave → definido sim/não), `opcoes` (moedas, idiomas, drivers de e-mail, discos de backup), `cron` e `versao`.
- **Segredos** (`SuperadminSettingsController::SEGREDOS`: senhas, chaves secretas, tokens) nunca saem do servidor. O Blade os imprimia em `<input type=text>` com o valor.
- `update()` ignora segredo **vazio** (= manter o gravado) e só regrava quando preenchido. Antes, campo vazio gravava `KEY=""`.
- `update()` tira `\r`, `\n` e `"` dos valores antes de escrever o `.env`: uma quebra de linha abriria uma linha nova com qualquer chave.
- `envPath()` isolado (protegido) para o teste gravar num arquivo temporário.

## 2. Tier 0 — invariantes

- Configuração global, sem `business_id` **de propósito** (ADR 0093 §exceções Superadmin). A trava é `can('superadmin')`.
- Chave nova de senha/secret/token entra em `SEGREDOS`, nunca em `ENV_VISIVEIS`.

## 3. Smoke prod (R1)

Após o deploy: `curl -sv https://oimpresso.com/superadmin/settings` sem login → 302 para o login, sem 5xx. Logado como superadmin: abrir a tela, ver as 7 seções e os segredos como "definido". **Não** clicar em Salvar em prod para testar: grava o `.env` da plataforma.

## 4. O que NÃO entrou

Teste de conexão SMTP/gateway, editor rico, "última execução do cron", liga/desliga por gateway (protótipo). Ver charter §Divergências.
