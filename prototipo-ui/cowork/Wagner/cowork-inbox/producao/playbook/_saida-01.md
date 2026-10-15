---
sessao: "_saida-01"
thread: "01 · ADR: PCP sai do estado de desejo, ADR-PROPOSAL → accepted, Modules/Producao nasce, IProduction sai"
dono: "[CL]"
data: 2026-10-15
tipo: recibo
entregue_em: "PR desta thread (claude/producao-thread-01-adr)"
base_lida: wagnerra23/oimpresso.com@main 66c04c1191
---
# _saida-01

## Entregue
- **ADR 0431** (`memory/decisions/0431-pcp-sai-do-desejo-modules-producao.md`), `status: aceito`,
  `decided_by: [W]`, `decided_at: 2026-10-15`, `supersedes_partially: [0152]`. Ela tira o PCP do
  feature-wish da 0152 e registra D1 = `Modules/Producao` e as respostas de D2–D5 e da 2ª rodada.
  O corpo da 0152 **não** foi tocado: supersede parcial não edita a antiga (precedente 0036/0053),
  e assim o PR não precisa de label de exceção do append-only. O merge do PR é a ratificação.
- **ADR-PROPOSAL → accepted**, com número 431, tabela das respostas de [W] e os links relativos
  consertados (a dívida de links mortos dele caiu de 10 para 0).
- **`Modules/Producao`** pela receita de criar módulo: `module.json`, `composer.json`, config,
  `ProducaoServiceProvider` + `RouteServiceProvider`, `DataController` (pacote `producao_module`
  com `default => false`; permissões `producao.access`, `producao.etapa.mover`,
  `producao.desempenho.ver`, todas desligadas por padrão; sem item de sidebar enquanto não há tela),
  `InstallController` (chave `producao`), as rotas Install em `/producao`, lang `pt`, e
  `modules_statuses.json`. Sem tabela, sem tela, sem rota de negócio.
- **Teste**: `Modules/Producao/Tests/Feature/ProducaoModuloAtivacaoTest.php`, na lane sqlite do
  `ci.yml` (`.github/ci-sqlite-pest.list`). Prova as permissões de D-PERM, o pacote desligado, as 3
  rotas Install pelo registry vivo (`Route::getRoutes()`, não `class_exists`) e a ausência do
  `IProduction`.

## IProduction: nada a apagar
`Modules/IProduction` **já não existia**: saiu no PR #842 (`chore: remove Modules/Grow +
Modules/IProduction`). A prova `ausente` passava antes desta thread. O que sobra de `iproduction`
no código é o rótulo `'iproduction' => 'Operações'` em `app/Services/ModuleManagerService.php:47` e
a lista de áreas do `ModuleRequirementsGenerator` — fora do prefixo da thread e inertes sem módulo.
Ficaram.

## Desvio do prefixo, e por quê
A pasta de domínio mudou: **`memory/requisitos/Pcp/` → `memory/requisitos/Producao/`** (`git mv`).
A máquina de ativação (`documentation-loop.mjs --enforce-activation`) exige
`memory/requisitos/<Modulo>/{SCOPE,BRIEFING,SPEC,SUPERFICIE}.md` com o nome do módulo. Ficar em
`Pcp/` e criar só os arquivos exigidos em `Producao/` daria dois SPECs para o mesmo domínio. Os IDs
`US-PCP-*` ficaram. Para não quebrar quem cita o caminho antigo:
- `Pcp → Producao` entrou como rename **classe A** em `governance/ghost-rename-map.json` (sai de
  `excluded`). O deadlink-gate resolve por ele os links da ADR 0152, que é imutável.
- Nos docs da pasta, `Modules/Pcp` virou `Modules/Producao` (o que o `ghost-fix` faria, só nesta
  pasta) e `Modules/IProduction` virou `IProduction` (não afirmar caminho vivo).
  `governance/knowledge-ghosts-baseline/Pcp.json` saiu (o módulo citante não existe mais).

Também fora do prefixo, porque a receita de módulo exige: `modules_statuses.json`,
`.github/ci-sqlite-pest.list` e as projeções regeneradas pelos donos (`SUPERFICIE.md`,
`memory/governance/catalog.json`, `PAINEL-SISTEMA.md`, `ONBOARDING-AGENTE-GERADO.md`,
`_INDEX-GENERATED.md`, `governance/doc-id-index.json`).

## Pedidos ao Cowork (não editei o espelho)
1. O índice, o `_SESSAO-FRIA.md` e o `Producao.casos.md` citam `memory/requisitos/Pcp/`. O caminho
   agora é `memory/requisitos/Producao/`.
2. A prova `ausente Modules/IProduction/module.json` da thread 01 nunca pôde falhar (o módulo saiu
   no #842). Não muda nada; é só para o índice não sugerir que esta thread apagou algo.

## Provas
- `node scripts/qa/placar.mjs --indice …/producao/playbook/00-INDICE.md --thread 01` → 01 entregue.
- `documentation-loop.mjs --impact-ref 66c04c1191 --head-ref HEAD --enforce-activation` → rc 0.
- `knowledge-drift.mjs --check` → 0 NOVOS · `deadlink-gate.mjs --check` → OK ·
  `module-surface --all --check`, `catalog-graph --check`, `system-map --check`,
  `adr-index-generate --check`, `anchor-lint` → rc 0 · `memory-schemas/validate.mjs` da ADR → conforme.
- PHP não roda nesta máquina (ADR 0062): o Pest e o `php -l` ficam com o CI.

## Erro meu no caminho
O par de barras do `module.json`/`composer.json` colapsou no transporte do heredoc (LC-26) e o
JSON nasceu inválido. Pegou o `documentation-loop` (`module-json-invalido`); reescrevi pelo molde
do `VozDoCliente` via script.

## Próxima
Thread 02 (fundo). Nada pendente de [W] nesta thread além do merge.
