---
id: resources-js-pages-financeiro-configuracoes-contador-casos
casos: Contador parceiro · /financeiro/configuracoes/contador
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/configuracoes/contador

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Charter em **draft** (US-FIN-037). Dono concede acesso **somente-leitura** ao contador, com trilha LGPD.

## UC-CTD-01 — Conceder acesso ao contador por CNPJ+email
Status: 🧪 (`Advisor/AdvisorRotasContratoTest`)
Quando o dono informa CNPJ+email e o escopo (Visão Unificada e/ou Relatórios) · Então nasce o grant ativo e o contador passa a ver o business no portal dele.

## UC-CTD-02 — Consentimento LGPD é opt-in obrigatório
Status: 🧪 (`Advisor/AdvisorRotasContratoTest`)
Sem o consentimento explícito marcado, o grant **não** é criado (nunca opt-out por omissão).

## UC-CTD-03 — Revogar acesso é deliberado e imediato
Status: 🧪 (`Advisor/AdvisorRotasContratoTest`)
Quando o dono revoga (via AlertDialog, nunca `window.confirm`) · Então `revoked_at` é gravado e o contador perde o acesso na hora. Acesso de outra empresa devolve 404.

## UC-CTD-04 — Grant é sempre read-only
Status: 🧪 (`Advisor/AdvisorRotasContratoTest`)
Nenhum escopo concedido permite escrita, mesmo com `advisor_view=1`. Hoje a barreira é o login do ERP, não o `AdvisorViewScope`, que não está montado em rota nenhuma (ver UC-ADVD-03).

## UC-CTD-05 — CNPJ nunca aparece inteiro
Status: 🧪 (`Advisor/AdvisorRotasContratoTest`)
A lista de acessos mostra o CNPJ mascarado; o front não recebe nem persiste o valor cheio.

## UC-CTD-06 — Só quem tem a permissão gerencia o contador
Status: 🧪 (`Advisor/AdvisorRotasContratoTest`)
Sem `financeiro.advisor.grant`, tela, concessão e revogação devolvem 403.

## Backlog de casos (sem id)
- **[BACKLOG] Trilha de auditoria da concessão/revogação** visível ao dono.
- **[BACKLOG] Não edita cadastro do advisor após criação** (Non-Goal declarado).

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork (leva 4 antecipada por ser irmã do portal).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
