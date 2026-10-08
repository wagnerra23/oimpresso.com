---
sessao: "_saida-09"
thread: "09 · Tier 0: decompose de project sem business_id"
dono: "[CL]"
data: 2026-10-08
tipo: recibo
entregue_em: "#8985"
base_lida: wagnerra23/oimpresso.com@main 265b507860
---
# _saida-09

## Entregue
A thread já estava feita quando esta sessão abriu. O #8985 (mergeado em 2026-10-07 20:56 UTC,
aberto a partir do achado da `_saida-02`) cobre os três passos da ficha. Esta sessão não mudou
código. Ela conferiu o que está no `main` e escreve o recibo.

- **Passo 1, teste antes do conserto:** `Modules/Forja/Tests/Feature/ProjectDecomposeTenantTest.php`,
  tenants 98 × 99. Rodado no CT 100 contra o código anterior, o caso de serviço deu
  `1 failed (2 assertions)`: como empresa 98, o `decompose` do project da 99 respondia
  `already_decomposed` em vez de `project_not_found`, ou seja, lia o project e as parts de outra
  empresa. Sem part gravada, seguiria para a IA e gravaria parts no project alheio.
- **Passo 2, conserto:** `ProjectDecomposerService::decompose($id, $businessId)` lê e atualiza
  `mcp_projects` com `where('id')->where('business_id')`. O `ProjectsController@decompose` passa a
  empresa da sessão, sem o fallback para 1. Project de outra empresa responde `project_not_found`,
  igual ao inexistente.
- **Passo 3, verde:** o mesmo caso deu `1 passed (3 assertions)` no CT 100 com o conserto.

## Provas lidas nesta sessão
- Lane `forja-pest.yml`: o teste está na allowlist, uma entrada por linha.
- Run `37806337124` (push no `main`, head `914bacbd7b`, 2026-10-08 16:07 UTC, success): os dois
  casos aparecem executados pelo nome, `✓ UC-ADPS-03 · POST /ads/admin/projects/{id}/decompose de
  project…` e `✓ UC-ADPS-03 · decompose no serviço: project de OUTRA empresa…`. Total da lane
  `135 passed (646 assertions)`, `skipped: 0`.
- `ProjectShow.casos.md`: UC-ADPS-03 `[must]` `[T0]`, citado pelos dois testes.

## O que não está provado
- O vermelho foi medido só no **caso de serviço**. O caso HTTP não rodou no CT 100, porque o
  helper `usuarioComPermissoes` não existe no checkout de lá, que está atrás do `main`. Ele roda
  e passa na lane, mas ninguém o viu vermelho. O filtro que ele defende é o mesmo do serviço, e o
  caso de serviço já viu esse filtro morder.
- Não refiz a mutação. O "antes" do #8985 é o código sem o filtro, que é a mutação que a ficha
  pede. Repetir exigiria mexer no checkout compartilhado do CT 100, que outras sessões usam.

## Fora desta thread (da `_saida-02`, não consertados aqui)
- `TeamScopes` lendo `user_businesses`.
- `grant` / `revoke` / `execute` de Ferramentas exigindo só login (thread 11).
- `triggered_by = 'wagner'` fixo.
