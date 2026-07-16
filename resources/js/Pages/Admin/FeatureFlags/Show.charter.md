---
page: /admin/feature-flags/{key}
status: draft
owner: wagner
updated: 2026-05-31
component: resources/js/Pages/Admin/FeatureFlags/Show.tsx
related_prototype: n/a (herda PT-03 Detalhe; segue o Padrão de Tela)
last_validated: "2026-05-31"
parent_module: Admin
related_us: [US-INFRA-008]
tier: B
charter_version: 1
---

# Charter — Feature Flags (Show)

## Missão
Detalhar uma feature flag (LaunchDarkly-style) e operá-la com segurança: alternar o mata-switch por environment, gerenciar rules de targeting por business_id e auditar o histórico de mudanças.

## Goals
- Mostrar tipo/defaultValue da flag e seletor de environment (production/staging).
- Mata-switch por environment (liga/desliga global, afeta todos os bizs) com confirmação.
- CRUD de rule biz-{N} via useForm (`bizRuleForm`): adicionar/atualizar value true|false e remover.
- Histórico de auditoria desta flag (quem, quando, ação, env, resumo).
- Sinalizar fetch_error quando o provider está indisponível.

## Non-Goals
- Criar/excluir a flag em si (gerenciado no provider externo).
- Editar `valueType`/`defaultValue` por aqui (somente leitura).
- Targeting por outros atributos além de business_id.

## UX Targets
- Carregamento < 200ms (defer se provider lento).
- Estado visual por token: Badge success=ON, secondary=OFF (sem emoji).
- Toda ação destrutiva (mata-switch, remover rule) atrás de AlertDialog — nunca `window.confirm`.
- Erro de formulário inline via `bizRuleForm.errors.{biz_id|value}` (campos reais do useForm).

## Anti-Hooks
- NÃO usar `<select>` nativo, cores cruas (bg/text-*-NNN, #hex, oklch inline) ou emoji como ícone — somente DS + tokens + lucide.
- NÃO inventar flags/rules client-side — fonte da verdade é o provider.
- NÃO acionar mata-switch nem remover rule sem confirmação explícita.
