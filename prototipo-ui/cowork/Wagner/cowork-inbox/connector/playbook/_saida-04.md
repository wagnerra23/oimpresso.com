---
sessao: "_saida-04"
thread: "04 · CONN-O3 · Blade → Inertia (Api/Index) + contrato connector-api"
dono: "[CL]"
data: 2026-10-01
base_lida: wagnerra23/oimpresso.com@main 10adca648
---
# _saida-04 (PR-a)

## Entregue
- `Modules/Connector/Http/Controllers/ClientController.php`
  - `index` → `Inertia::render('Api/Index')`. Props: `clients` (id, nome, quem criou, data,
    `active_tokens_24h`) **sem `secret`**, `is_demo` (demo → lista vazia), `endpoints_count`
    (rotas `connector/api/`), `credencial` (flash da criação). Escopo por `users.business_id`
    mantido (o `leftJoin` virou `join`: com o `where u.business_id` o resultado é o mesmo).
  - `store` → o segredo sai do `status.msg` (que vira toast e some) para o flash
    `connector_credencial`. A tela mostra uma vez, num bloco copiável que fica até fechar.
    Nada muda no valor guardado nem na API do desktop.
- `Modules/Connector/Resources/js/Pages/Api/Index.tsx` — lista + busca (`/`, `n`) + KPIs +
  criar (Dialog) + bloco da credencial + excluir (AlertDialog com a consequência) + estados vazios
  (primeira vez · busca sem resultado · demonstração). Componentes do DS (`PageHeader`, `KpiGrid`,
  `KpiCard`, `EmptyState`, `Dialog`, `AlertDialog`, `DropdownMenu`). Copy PT-BR.
- `Index.charter.md` + `Index.casos.md` — trazidos do cowork-inbox (pendência da 01, IT2), com as
  erratas: rota `/connector/client`; UCs como `## UC-`; `related_prototype` com o path real;
  R2/R6 atualizados pelo que as threads 02/03 já fizeram. UC sem teste citando virou `[BACKLOG]`.
- `governance/design/contracts/connector-api.contract.json` — derivado do `connector-page.jsx`,
  recortado para a aba de clients (10 seções).
- Testes: `ApiClientsPanelTest` passa a bater em `/connector/client` (UC-01/02/03/09/16) e o UC-03
  lê a prop Inertia; `ClientControllerBaselineTest` (perdedor, mesmo PR) passa a ler o segredo no
  flash novo.

## Provas do json, medidas no branch
1. `ClientController.php` contém `Inertia::render(` — ✅
2. `Pages/Api/Index.tsx` existe — ✅
3. `connector-api.contract.json` existe — ✅
Gates locais: `contrato-de-tela --contract` limpo (10/10 + ordem) · `--map --check` limpo ·
`--anti-tautologia` 0 reprovado · `casos-coverage-guard` sem violação nova · `integrity-check`
IT2/IT2b PASS · `screen-coverage --check` catraca ok · schema do charter OK.

## Erratas para o Cowork (não editei o playbook)
1. **Contrato.** A proposta usa `verdict: "proposto"` / `"ratificado_[W]_…"`; a máquina só aceita
   `aprovado|recusado`. Os `acordos_estado` ficaram fora deste PR. A seção `novo-client-form`
   exige `password_client` à vista em "Tipo", mas as proibições da mesma proposta vetam enum cru:
   a tela mostra "client de senha (o app troca usuário e senha por token)".
2. **Contagem na confirmação de excluir.** O protótipo mostra "Os N acessos caem"; a lista só tem
   os tokens **usados em 24 h**, e o `destroy` revoga **todos** os ativos. A tela diz isso com
   essas palavras, sem prometer um número que não é o revogado.
3. **Tamanho.** O PR passa de 300 linhas por causa do charter + casos copiados do Cowork (~270);
   o código fica perto de 300.

## Pendente
- **PR-b da 04:** abas Documentação, Saúde e Módulo + seções `tabs`/`docs-*`/`saude-checks`/
  `modulo-*` e `acordos_estado` no contrato.
- `RUNBOOK-connector-index.md` §3/§10.2 e `BRIEFING.md` do Connector ainda descrevem a Blade e o
  segredo visível — fora do prefixo desta thread.
- Blade `clients/index.blade.php` órfã (o `index` não a usa mais): sai na thread 06.
- [W2] screenshot em produção → charter `live`.

## NÃO MEDI
Pest (os dois arquivos fazem skip em SQLite; MySQL é no CT 100, que esta sessão não toca) e
`tsc` (sem `node_modules` no worktree) — o CI faz.

## PR
(no corpo do PR)
