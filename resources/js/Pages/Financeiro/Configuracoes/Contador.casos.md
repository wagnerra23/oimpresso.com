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
Status: 🧪 (`Modules/Financeiro/Tests/Feature/Advisor/Onda31AdvisorPortalTest.php`)
Quando o dono informa CNPJ+email e o escopo (Visão Unificada e/ou Relatórios) · Então nasce o grant ativo e o contador passa a ver o business no portal dele.

## [BACKLOG] Consentimento LGPD é opt-in obrigatório
Status: ⬜ sem prova — nenhum teste tenta criar grant SEM consentimento pra provar a recusa. Vira `UC-CTD-02` quando existir teste citando o id (G-2).
Sem o consentimento explícito marcado, o grant **não** é criado (nunca opt-out por omissão).

## UC-CTD-03 — Revogar acesso é deliberado e imediato
Status: 🧪 (`Onda31AdvisorPortalTest` — grant revogado perde acesso)
Quando o dono revoga (via AlertDialog, nunca `window.confirm`) · Então `revoked_at` é gravado e o contador perde o acesso na hora.

## UC-CTD-04 — Grant é sempre read-only
Status: 🧪 (`Onda31AdvisorPortalTest` + middleware `AdvisorViewScope`)
Nenhum escopo concedido permite escrita, mesmo com `advisor_view=1`.

## UC-CTD-05 — CNPJ nunca aparece inteiro
Status: 🧪 (`test_advisor_cnpj_masked_protege_pii`)
A lista de acessos mostra o CNPJ mascarado; o front não recebe nem persiste o valor cheio.

## Backlog de casos (sem id)
- **[BACKLOG] Trilha de auditoria da concessão/revogação** visível ao dono.
- **[BACKLOG] Não edita cadastro do advisor após criação** (Non-Goal declarado).

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork (leva 4 antecipada por ser irmã do portal).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
