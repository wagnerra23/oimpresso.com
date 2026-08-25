# Pedido zero-toque — /modulos (Gerenciador de Módulos)

> De [CC] para [CL] · 2026-08-19 · aprovação de escopo [W]
> Leitura base no `main` tree `0b125f09bd96`. **Nada aqui está commitado** — as tools de GitHub deste
> projeto são read-only. Cole este pedido ou abra Issue com o form `cowork-intake`.

## Contexto em 3 linhas

`/manage-modules` (AdminLTE) **já foi substituído** por `/modulos` em Inertia — a tela existe, funciona
e tem as 3 ações (toggle/install/uninstall) em POST. O que falta não é tradução: é **prova** (o trio da
tela está incompleto) e **verdade** (três células afirmam coisas que o backend não sustenta).

## PRs propostos (nesta ordem)

> **Regra de ordem — [W] 2026-08-19:** o legado sai **por último** (PR-8) e só com a lane verde na tela
> nova. Ordem = do mais barato e reversível para o irreversível.

### PR-1 · doc: apontar operador para /modulos (5 min, sem risco)
6 ocorrências de `/manage-modules` como caminho vivo em skills: `cockpit-runbook/TEMPLATE.md:42`,
`criar-modulo/SKILL.md:26,46,74,151,252`, `migrar-modulo/SKILL.md:144,226`, `sidebar-menu-arch/SKILL.md:222`.
Trocar por `/modulos`, preservando a menção histórica onde o texto explica a substituição.

### PR-2 · test+doc: fechar o trio da tela (destrava o gate)
Arquivos prontos em `cowork-inbox/modulos/repo/` (espelho da árvore do `main` — cada arquivo já no caminho de destino):
- `resources/js/Pages/Modules/Index.casos.md` (16 UC, Dado/Quando/Então, rastreabilidade)
- `resources/js/Pages/Modules/Index.charter.md` (substitui o `draft` v1 — v2: R1–R11, A1–A4, 4 pendências [W])
- `tests/Feature/Modules/ModuleManagementTest.php`
- `tests/Unit/Services/ModuleManagerServiceTest.php`

Notas para o PR: o UC-MOD-03 está marcado `skip` com placeholder (o contrato de auth da lane decide
entre 401 e redirect — ajuste na sua ponta); o UC-MOD-13 **falha de propósito** até o PR-3 entrar.

### PR-3 · fix: a tela para de mentir (P1+P2+P3)
Arquivos com o patch já aplicado em `cowork-inbox/modulos/repo/` (mesmo caminho do `main`):
- `app/Services/ModuleManagerService.php` — **P1** `install()` reverte a flag no `catch` (UC-MOD-13) e a mensagem
  avisa que migrations podem estar parcialmente aplicadas; **P2** `error` passa a ser preenchido para
  `module.json` malformado / sem `providers[]` / ausente (o status "Com erro" era inalcançável);
  **P4** versão instalada via `System::getProperty('<alias>_version')` — **este trecho depende de D1**,
  remova se [W] decidir outra saída.
- `resources/js/Pages/Modules/Index.tsx` — **P3** módulo ativo sem `DataController` ganha o marcador "sem menu"
  (a prop `has_datacontroller` já vinha e a tela ignorava; é o sintoma "instalei e não aparece no menu").
- `tests/Unit/Services/ModuleErroFixtureTest.php` — prova do P2 (fixture `Modules/__ErrFixture__` no padrão do
  `DetectDriftCommandTest`).

⚠️ Bug adjacente que o P4 encosta: `isModuleInstalled()` consulta `strtolower($name).'_version'`, então
alias kebab (`oficina-auto`, `comunicacao-visual`) grava chave diferente da consultada — a própria
skill `criar-modulo` documenta o "Instalar perpétuo". Vale um teste que trave a convenção nos 32 antes
de exibir versão instalada.

### PR-4 · sec: uma lei de autorização (P5 — depende de D2)
`ModuleManagementController.php` em `repo/app/Http/Controllers/` troca `session('is_admin')`/`Admin#<biz>` por
`can('manage_modules')` — a mesma chave do item de menu (`AdminSidebarMenu.php:809`) e do legado. Hoje
é possível ver o item no menu e tomar 403 na tela. UC-MOD-02/04 mudam de porta, não de intenção.

### PR-5 · perf: install fora do request (P6 — depende de D4)
`InstalarModuloJob.php` já em `repo/app/Jobs/InstalarModuloJob.php`: lock por módulo, `tries=1` (migration não é
idempotente), estado no cache (`instalando|ok|erro`) para a tela desabilitar o botão e mostrar o
resultado. **Não verifiquei** se há fila com worker em produção — confirmar antes de trocar o caminho
síncrono, senão a tela fica "instalando" para sempre.

### PR-6 · governança: contrato + CI
`modulos.contract.json` já em `repo/prototipo-ui/contrato/modulos.contract.json` (7 âncoras, copy literal,
estados, proibições) + `data-contract` nos 8 pontos + `contrato:check` no PR. Somar o smoke visual
1280/1440 que o charter pede.

### PR-7 · DS vivo
Trocar peças caseiras por DS (`KpiCard`, `StatusBadge` com kind novo `modulo`, `EmptyState`,
`Toast`, `Drawer` se D3=sim). Tabela, `FilterDropdown` e kebab **ficam** — são padrão do shell inteiro;
trocar só aqui criaria dois padrões.

### PR-8 · limpeza do legado — **ÚLTIMO, com portão de entrada**
Detalhe em `PR-8-REMOVER-LEGADO.md`. `repo/routes/web.php` (tira as 3 rotas de `manage-modules`,
mantém `upload-module`) e `repo/app/Http/Middleware/AdminSidebarMenu.php` (menu aponta
`route('modules.index')`, `active` casa `modulos`) já vêm patchados; a view
`resources/views/install/modules/index.blade.php` e os métodos órfãos do `Install\ModulesController`
são exclusão manual. O redirect do `LegacyMenuAdapter` **fica**.

**Não entra antes de:** PR-2 a PR-6 mergeados **e** lane verde nos 3 arquivos de teste
(`ModuleManagementTest`, `ModuleManagerServiceTest`, `ModuleErroFixtureTest`) **e** smoke visual
1280/1440 aprovado por [W2]. Enquanto o legado existe, ele é a rota de fuga se a tela nova falhar em
produção — apagar antes da prova troca um caminho feio por nenhum caminho.

## Decisões [W] que bloqueiam PR-3(parte)/PR-4/PR-5

| # | Decisão | Recomendação [CC] |
|---|---|---|
| D1 | versão sempre `v0.0` (nenhum `module.json` declara `version`) | ler `system.<alias>_version` (versão instalada) |
| D2 | RBAC: `is_admin`/`Admin#biz` vs permissão `manage_modules` | unificar em `manage_modules` |
| D3 | drawer de detalhe PT-02 entra na produção? | sim — resolve a descrição truncada |
| D4 | `install` roda migration no request web | job em fila + lock, se houver worker |
| D5 | chaves órfãs do `modules_statuses.json` (Accounting, CustomDashboard, Ecommerce, FieldForce, Hms, InboxReport) | remover do arquivo (ruído de merge); UC-MOD-06 já protege |

## Checklist pós-merge

- [ ] `npm run screen:files -- Modules/Index` → trio ✅
- [ ] lane Pest com `ModuleManagementTest` + `ModuleManagerServiceTest` + `ModuleErroFixtureTest` — **verde** (é o portão do PR-8)
- [ ] smoke `/modulos` a 1280px com sidebar aberta: sem scroll horizontal
- [ ] smoke: nenhum badge com enum cru; "Com erro" acende com fixture
- [ ] `contrato:check` verde (após PR-6)
- [ ] **só então** PR-8: `/manage-modules` 404, link antigo caindo em `/modulos` pelo adapter, upload `.zip` intacto, nenhuma skill citando `/manage-modules` como caminho vivo

## Como aplicar

`cowork-inbox/modulos/repo/` é espelho da árvore do `main`: copiar `repo/` sobre a raiz do repositório
coloca cada arquivo no lugar. Nenhum rename, nenhuma decisão de path.

## Referência

Trio F1: `cowork-inbox/MODULOS-F1-2026-08-19.md` · Ondas: `cowork-inbox/MODULOS-F3-ONDAS-PARA-CODE.md` ·
Patches comentados: `cowork-inbox/modulos/PATCHES.md` · Build F1: `prototipo-ui/cowork/modulos/`.
