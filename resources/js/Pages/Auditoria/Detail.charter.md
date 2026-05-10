---
charter_id: auditoria-detail
status: live
last_review: 2026-05-10
owner: Wagner
---

# Charter — `/auditoria/{id}` (Detail)

## Mission

Explicar UMA alteração com diff inequívoco e permitir reverter quando seguro.

## Goals

1. **Operador entende o que mudou em < 10s** — diff side-by-side (Antes / Depois) por campo, sem precisar interpretar JSON.
2. **Reverter exige confirmação consciente** — modal com `revert_reason` mín 10 chars; sem clique inadvertido.

## Non-goals

- Editar valores diretamente (não é form de edição) — reverter é o único caminho de mudança aqui.
- Redo (refazer revert) — caso de uso raro, usuário cria entry manual se precisar.
- Diff em campos `text`/`longtext` muito grandes (>10KB) — truncar com link "Baixar full".

## Anti-hooks (NÃO mexer sem justificativa)

- Reason mín 10 chars + textarea (não input) — anti-clique-acidental. Fundamental pra UX segura.
- Modal de confirmação obrigatório antes de POST `/auditoria/{id}/revert` — ADR 0127 §princípio 6.
- Bloqueio visual quando `activity.reverted_at` setado — não pode revert duplicado.
- Botão Reverter SEMPRE mostra "Cancelar" ao lado — operador deve poder desistir até último clique.

## Métricas de saúde

- % de revert canceladas pelo usuário no modal: > 10% (sinal de modal efetivo prevenindo erros)
- Tamanho médio de `revert_reason`: > 30 chars (sinal de razões de qualidade)
- % de revert que retornam erro 422 (whitelist UNREVERTIBLE): tracking de tentativas bloqueadas

## Dependências

- `RevertService` (US-AUDIT-008) — backend valida whitelist + janela permissão
- Coluna `reverted_at` (US-AUDIT-005) — guard contra duplicate revert
