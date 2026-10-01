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

---

# _saida-04 (PR-b) · 2026-10-01 · base lida `origin/main` 2b24b2de5

## Entregue
- `Pages/Api/Index.tsx` — abas **API clients · Documentação · Saúde · Módulo** com o `PageHeaderTabs`
  do DS (grupo `sistema`). A aba vem de `?aba=docs|saude|modulo` e a troca atualiza a URL sem
  recarregar. O título do cabeçalho segue a aba (copy do protótipo). O atalho `n` só abre o
  formulário na aba de clients.
- `Pages/Api/_components/ConnectorAbas.tsx` (novo) — as três abas:
  - **Documentação:** "Como um app externo entra", "Regras que valem em todo endpoint", catálogo
    com busca e os 3 formatos de corpo. O catálogo **não é a lista do protótipo**: vem da prop
    `endpoints`, lida das rotas `connector/api/` registradas. Os grupos são os do protótipo; rota
    que não casa com nenhum vai para "Outras rotas" e nunca some. A coluna Observação só tem texto
    onde o contrato com o desktop está escrito no `Routes/api.php` (`processa-dados-cliente`,
    `oimpresso/registrar`, `check-update`, e o 410 da presença cedida ao Ponto); o resto mostra "—".
  - **Saúde:** sem dado inventado. Mostra **rotas registradas** (selo Dentro/Abaixo do limiar ≥ 20)
    e **tokens ativos em 24 h dos clients deste negócio** (sem selo: o limiar ≥ 1 da rotina é sobre
    todos os negócios juntos). **Licenças com acesso em 24 h = "não medido aqui"**: a tela não lê
    `licenca_computador` e o `connector:health` só grava em log — o histórico de 14 dias é a thread
    08. A tela declara que não executa o comando.
  - **Módulo:** estado medido (instalado, versão de `config('connector.module_version')`,
    migrações contadas em `Database/Migrations`, endpoints) e Instalar/Atualizar/Desinstalar como
    **links para as confirmações do `InstallController`** (GET sem efeito, ação no POST — thread 02),
    com o aviso do `passport:install --force` antes.
- `ClientController::index` — props novas `endpoints` e `modulo`; `endpoints_count` passou a ser a
  contagem de `endpoints` (mesmo predicado de antes, `connector/api/`). Nenhuma query nova em dado
  de negócio; nada da API do desktop mudou.
- `DataController::modifyAdminMenu` — o menu volta a ter link de documentação: ghost
  **Documentação → `/connector/client?aba=docs`** (pedido da sessão-mãe; pendência do `_saida-05`).
  ⚠️ Fora do prefixo da 04 (o arquivo é do prefixo da 05): 3 linhas, declarado aqui e no PR. O menu
  continua só de superadmin — a guarda não mudou.
- `governance/design/contracts/connector-api.contract.json` — + `tabs`, `docs-como-entra`,
  `docs-catalogo`, `docs-formatos`, `saude-checks`, `modulo-estado`; `ordem` com `tabs`; e o acordo
  `contrato-delphi` (`aprovado`, vocabulário `array_tabelas|json_flat|pipe`, backend
  `DelphiSyncService`, frontend `ConnectorAbas.tsx`).
- `Index.casos.md` — UC-CONN-19 cobre o catálogo; **UC-CONN-25** (novo) aba Módulo com estado
  medido; backlog atualizado (Saúde, aviso do Passport, link do menu). `Index.charter.md` — status
  e props do PR-b.
- `ApiClientsPanelTest` — `test_catalogo_da_documentacao_sao_as_rotas_registradas` (UC-CONN-19) e
  `test_aba_modulo_mostra_versao_e_migracoes_medidas` (UC-CONN-25).

## Erratas ao contrato proposto (não editei o playbook)
1. **`acordos_estado`.** A máquina só aceita `aprovado|recusado` e exige que cada valor apareça como
   literal **emitido** pelo backend e tratado pelo frontend. Dos 5 acordos da proposta, só
   `contrato-delphi` tem esse vocabulário (o `DelphiSyncService` devolve `'array_tabelas'`,
   `'json_flat'`, `'pipe'`); `congelado` virou `aprovado` (o congelamento é do ADR 0021). Os outros 4
   (`tipo-de-client`, `visibilidade-do-segredo`, `autorizacao-do-painel`, `revogacao-em-cadeia`)
   descrevem **decisões** [W], não state-strings: os valores (`guardado_nao_exibivel`,
   `apenas_client`…) não existem no código, e `password_client` só aparece como chave. Pôr literais
   no código só para o gate passar seria teatro; essas decisões já são provadas por teste
   (UC-CONN-02, 09, 11, 12). Ficaram fora.
2. **`modulo-achados` ficou fora.** Os 7 achados do protótipo eram o trabalho das threads 02–05 e
   estão resolvidos; exibi-los como estado do módulo seria falso. O card "Onde o módulo aparece"
   também: dizia que o pacote `connector_module` mostra o menu, e desde a 05 o menu é só de
   superadmin.
3. **`fonte` é um caminho só** (o gate rejeita `a + b`): `connector-page.jsx`; o `connector-api.jsx`
   fica citado na `_nota`.
4. **Migrações:** o protótipo diz 2; o módulo tem 1 arquivo em `Database/Migrations`. A tela mostra
   a contagem medida.

## Provas do json, medidas no branch
1. `ClientController.php` contém `Inertia::render(` — ✅
2. `Pages/Api/Index.tsx` existe — ✅
3. `connector-api.contract.json` existe — ✅

Gates locais: `contrato-de-tela --contract` limpo (16 seções + ordem + acordo `contrato-delphi`
coerente) · `--map --check` limpo · `--anti-tautologia` limpo · `casos-coverage-guard` sem violação
nova · `integrity-check` IT2/IT2b PASS · `screen-coverage --check` catraca ok · schema do charter OK
· `uc-id-lint` limpo · `tsc --noEmit` sem erro nos arquivos do Connector (com o `node_modules` do
checkout principal).

## Pendente
- **Saúde completa** (licenças em 24 h e histórico) — thread 08, que grava o registro do
  `connector:health`. A aba já tem o lugar.
- Pest dos dois testes novos: NÃO MEDI (skip em SQLite; MySQL é no CT 100). O CI faz.
- [W2] screenshot em produção → charter `live`.
- Errata do charter R9 (menu só de superadmin) e UC-CONN-09/13/14/15 "thread 05" — seguem como no
  `_saida-05`, fora deste PR.

## PR (PR-b)
(no corpo do PR)
