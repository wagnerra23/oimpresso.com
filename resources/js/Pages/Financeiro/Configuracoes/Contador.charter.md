---
page-id: financeiro-configuracoes-contador
route: /financeiro/configuracoes/contador
module: Financeiro
controller: Modules\Financeiro\Http\Controllers\AdvisorAccessController
status: draft
owner: eliana
us: US-FIN-037
created: 2026-05-31
page: /financeiro/configuracoes/contador
component: resources/js/Pages/Financeiro/Configuracoes/Contador.tsx
last_validated: "2026-05-31"
parent_module: Financeiro
tier: B
charter_version: 1
---

# Charter — Contador Parceiro (Financeiro / Configurações)

## Mission

Permitir que o **dono do negócio** conceda ao contador parceiro acesso
**somente-leitura** ao Financeiro (Visão Unificada + Relatórios DRE/Fluxo)
num portal próprio do contador — sem compartilhar credenciais — e revogue
esse acesso a qualquer momento, com trilha de auditoria LGPD.

## Goals

- Conceder acesso a um contador (busca/cria advisor por CNPJ+email).
- Definir escopo do grant (Visão Unificada e/ou Relatórios).
- Coletar **consentimento LGPD explícito** (Art. 7º, II) no ato da concessão.
- Listar acessos ativos com nome, email, CNPJ mascarado, data e escopo.
- Revogar acesso com confirmação deliberada (AlertDialog DS).

## Non-Goals

- **NÃO** é a tela de login do contador (isso é `/advisor/login`,
  `AdvisorAuthController`).
- **NÃO** edita dados cadastrais do advisor (nome/telefone) após criação.
- **NÃO** concede acesso de escrita — grant é sempre read-only.
- **NÃO** gerencia múltiplos negócios do advisor aqui (escopo é o business atual).

## UX targets

- Padrão de Tela: lista + form inline (drawer-less), header canon.
- `PageHeader` (shared) com `icon` + `title` + `description` + `FinanceiroSubNav`
  no slot `action` (paridade com Unificado/Categorias — Wave 4).
- Inputs via `@/Components/ui/input` (variant cowork default) + `Label`.
- Feedback de flash via `Alert` (`default` p/ sucesso/info, `destructive` p/ erro).
- Ações destrutivas via `AlertDialog` controlado (sem `window.confirm`).
- Cores **somente tokens** (sem hex/oklch inline, sem `bg-blue-*`).

## Anti-hooks

- Não reintroduzir `os-btn`, `os-page-h`, flash `bg-blue-*/bg-emerald-50` cru
  nem `window.confirm` (regressão DS).
- Não expor CNPJ completo no front — sempre `advisor_cnpj_mascarado` do backend.
- Não logar PII (CNPJ/email) — backend já redige em log; front não persiste.
- Não transformar o consent LGPD em opt-out — é checkbox obrigatório opt-in.
