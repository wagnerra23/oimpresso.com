# `_saida-07.md` — recibo da thread 07 (`ancora/playbook/07-cruzar-report-trio-contrato.md`)

> Executor **[CL]** · base `main @c1f77b029185` + ZIP 21 aplicado nesta sessão · 2026-09-16.
> **Nenhum script novo.** Todo número abaixo veio de dono que já existia; o join das 162 linhas
> rodou em script efêmero no scratchpad da sessão (fora da árvore — §5 2026-09-15), importando
> `resolveAncora` e `ucsDeclaredInCasos` dos donos em vez de reimplementá-los.

## 0 · Recibo de execução (comando → exit code)

| # | comando | exit | o que devolveu |
|---|---|---|---|
| 0 | `receber-handoff.mjs --zip "…handoff (21).zip"` | **1** | `PASSO 0 nao liberou` — veredito `indeterminado`, nada promovido |
| 0b | idem `--conta w` | **0** | pacote CONFORME · 3 pontos: 4 fora-do-bundle, 698 iguais · dry-run VALIDADO |
| 0c | idem `--conta w --apply` | **0** | **PROMOVIDO ATOMICAMENTE** · delta `+2 ~2 -0 =700` · 704 arquivos |
| 1 | `git grep -n "ancora.mjs"` + `git hash-object` | 0 | §1 CONFIRMADO (abaixo) |
| 2 | leitura direta `scripts/qa/prototipo-readiness.mjs` | 0 | **existe** — 8.129 B |
| 3 | `node scripts/qa/prototipo-readiness.mjs` | **0** | 92 telas com protótipo real · 57 prontas · 35 em 1-ciclo |
| 4 | `node scripts/qa/design-coverage.mjs` | **0** | 220 charters · 100% com fonte declarada · 42 `.tsx` sem charter |
| 5 | `node scripts/casos-coverage-guard.mjs --report` | **0** | 220 páginas · 158 casos.md · 1035 UC · 63 sem casos · 74 violações |
| 6 | `node scripts/design/ancora.mjs --list` | 0 | 223 linhas — **por ROTA**, não por `Mod/Tela` (ver §6) |
| 7 | `npm run tokens:build` | **0** | 4 escopos emitidos · **zero arquivo versionado alterado** |
| 8 | `node scripts/design-sync/ds-push.mjs` | **0** | **VALOR: 0** (bundle == git canon) |
| 9 | `node scripts/governance/ds-mirror-drift.mjs` | **0** (advisory) | **totalDiverge: 8** > baseline 0 |

## 1 · §1 do `_PATCH-INDICE-2026-09-16` — CONFIRMADO, com um número a corrigir

`prototipo-ui/ancora.mjs` **não existe** (`git ls-files` vazio · `ls` = *No such file*). O alvo real:

```
scripts/design/ancora.mjs   sha f8a186642fda09c62d4c8f20ea3025478f75826e   115.752 B
```

⚠️ **O patch diz "os 49.089 B eram do caminho antigo" — o tamanho novo é 115.752 B, 2,36× maior.**
Quem reapontar as threads 01–03 tem de regravar **sha e tamanho**, não só o path.

Consumidores medidos (o patch listou 6; a varredura contada devolve mais):
`post-merge-ui-smoke-required.mjs:316` (import) e `:318` (degrada citando) · `.test.mjs:175` (`existsSync`) ·
`block-ancora-no-olho.mjs:84` · `charter-validate.mjs:117` · `design-coverage.yml:17` (trigger `paths:`) ·
`design-memory-gate.yml:374` (`--selftest`) · `migracao-layout-em-ondas.js:99,133` · `refutador-gt-g5.js:355` ·
`validador-modulo-prototipo.js:26,96` · skills `comparar-design-prod`, `cowork-prototype-replication`, `validador-modulo`.

**Não apliquei o patch no `00-INDICE.md`.** Duas razões independentes, cada uma suficiente:
o índice vive em `prototipo-ui/cowork/Wagner/**`, que é **espelho de leitura** (ADR 0374) — edição minha
ali some no próximo `--export-from`, e acabei de ver isso ao vivo (o ZIP 21 reescreveu essa pasta);
e o **próprio `nao_toca` da thread 07** lista `prototipo-ui/cowork/**`. O conserto durável nasce no
Cowork vivo e desce — que foi exatamente como o patch chegou aqui.

## 2 · `prototipo-readiness.mjs` — CONFIRMADO, e é o dono da pergunta

Existe, tem teste e **tem invocador**: `mv-metabolismo.yml:128` (nightly 06:30, `--json`) e
`governance-script-tests.yml:1106`. Consta do `MAQUINAS-INVENTARIO.md:716`. Classifica exatamente
o eixo dos baldes A/B: ✅ PRONTA (trio + casos-com-UC + scorecard) · 🟡 1-CICLO · ⛔ SEM-ANCORA.
**Rodado em modo leitura** (sem `--json`, que escreve em `memory/governance/` — §5 2026-09-05).

Resultado: **92** telas com protótipo real → **57 prontas** · **35 em 1-ciclo** (dessas, 31 faltam
só `scorecard` e 4 faltam `casos.md`-com-UC).

## 3 · O `summary` INTEIRO (tarefa 5) — e **não existe 3º estado**

```json
{ "transportChanges": 4, "screens": 162, "tested": 0, "smoked": 0,
  "lifecycle": { "anchored": 134, "to-create": 28 }, "pending": 134, "to-create": 28 }
```

São **8 campos**, não 4. A subtração `162 − 134 = 28` está certa — e é **desnecessária**: o campo
`to-create: 28` está explícito, e a distribuição contada de `applicationState` nas 162 entradas é
`pending: 134` + `to-create: 28`, sem terceiro valor.

⚠️ **`transportChanges` hoje é `4`, não `424`.** O 424 era do delta anterior; este número é **datado
por construção** — ele descreve o último transporte, não um acumulado.

## 4 · ⚠️ A §2 da thread está REFUTADA por medição

A thread afirma: *"Cada entrada tem **só** `source` → `target` → `module` → `applicationState`"* e
*"busca por `charter|casos|contract|anchor|reason|…` no indent das entradas = **0 hit**"*.

Controle positivo: `grep -o "charter" application-report.json | wc -l` = **199**.

Cada entrada tem **13 chaves**: `source · bundleChange · target · module · mapping · comparison ·
lifecycleState · applicationState · compared · tested · smoked · applicationEvidence · nextAction`.

| campo | distribuição |
|---|---|
| `mapping` | `charter.component` **145** · `registro a-criar` 16 · `path-espelhado` 1 |
| `comparison` | `SEMANTICO` 133 · `A-CRIAR` 16 · `ALVO-PENDENTE` 12 · `ALTERADO` 1 |
| `nextAction` | `gerar/registrar map.json antes de aplicar semanticamente` 134 · `criar a Page pelo fluxo MWART antes de aplicar` 28 |
| `applicationEvidence` | `null` 161 · 1 com `targetSha256` |

Ou seja: o report **sabe** que 145 dos 162 alvos vieram do **charter** (`charter.component`) e **diz
o próximo passo de cada um**. O que ele de fato **não** carrega é `casos`/UC, contrato de tela,
nível de âncora e componente-faltante — que é o buraco real, e é o que a tabela do §6 preenche.

## 5 · Classificação das 162 (tarefa 4)

⚠️ **Primeiro, a correção que a thread me obriga a fazer contra mim mesmo.** Minha primeira
passada classificou sem aplicar a condição *"âncora ≥E1"* que a própria definição de (A) exige —
e o resultado saiu inflado: `A=29`. Com a condição aplicada, `A=26`. Fica registrado, não apagado.

⚠️ **Segundo: 162 são ENTRADAS, não telas.** Há **139 alvos distintos** — 23 entradas são um
2º/3º protótipo apontando para o **mesmo** `.tsx` (ex.: `Fiscal/Cockpit.tsx` recebe 2 fontes).
Quem ler "162 telas" superestima o denominador em 17%.

| balde | entradas | alvos distintos | o que significa |
|---|---|---|---|
| **A — executável hoje** | **26** | **20** | trio + contrato + âncora ≥E1 + zero componente faltante |
| **B — falta UC** | **20** | 19 | **charter existe em 20/20**; o que falta é `casos.md` (19) ou UC>0 (1) |
| **C — falta contrato** | **66** | 66 | trio ok, âncora ok, **sem `*.contract.json`** — é o balde majoritário |
| **D — falta componente + ADR** | **13** | 13 | o protótipo usa componente sem par no `main` |
| **E — sem receptor** | **28** | 12 | `to-create`; 16 sem `target` algum, 12 com alvo previsto |
| **F — âncora declarada que NÃO abre** | **9** | 9 | ⚠️ ver abaixo |

⚠️ **O balde F é um achado, não um desvio da taxonomia.** A thread pede *"exatamente um de 5
baldes"* — mas 9 entradas têm trio completo, contrato resolvível e **`related_prototype` apontando
para arquivo que não existe no repo**. Não cabem em nenhum dos 5, e enfiá-las em (C) ou (D) as
esconderia. São elas, com a âncora que o charter declara e o disco não tem:

`Purchase/Index.tsx` e `Purchase/Show.tsx` ← `compras-page.jsx` · `Home/Index.tsx` ← `dash-legacy-page.jsx` ·
`Essentials/Metas.tsx` ← `hrm-extras.jsx` · `Essentials/Tipos.tsx` ← `hrm-page.jsx` ·
`OficinaAuto/ServiceOrders/Create.tsx` e `.../Edit.tsx` ← `oficina-forms.jsx` ·
`Sells/Create.tsx` ← `vendas-create-page.jsx` · `Sells/Index.tsx` ← `vendas-page.jsx`

O próprio `ancora.mjs` documenta a regra que produz isso (`:528`): *"Só empurra se ABRIR… âncora que
aponta pro vazio é pior que âncora ausente"* — a perna do lugar fixo se recusa a resolver, e a perna
1 (`related_prototype`) resolve mesmo assim. **Nas 8 primeiras a decisão é de [W]** (re-emitir a fonte
pelo Cowork, ou corrigir o `related_prototype`); a 9ª é diferente e está no §7.

Distribuição do eixo âncora nas 162: `E1` **95** · `sem-charter` 27 · `n/a-declarado` 22 ·
`E0(não-abre)` 17 · `nulo(ambígua)` 1. (`n/a-declarado` **não é defeito** — é decisão consciente que
a máquina reconhece, §5 2026-08-28. Os 17 `E0` viram só 9 no balde F porque 8 deles já caem antes,
em B, por falta de `casos.md`.)

### Os 20 alvos do balde A — o que dá pra fazer amanhã

`Arquivos/Index` · `Backup/Index` · `Purchase/Create` · `Compras/Index` · `Modules/Index` ·
`Fiscal/{Cockpit,Config,Dfe,Eventos,Nfe,Nfse,Sped}` · `Jana/{Index,Acoes,Alertas,Plataforma}` ·
`Manufacturing/Recipes` · `Ponto/Dashboard/Index` · `Ponto/Espelho/{Index,Show}`

### Os 13 do balde D — componente sem par no `main` (PISTA, não fato)

| alvo | componentes que o protótipo usa e o `main` não tem |
|---|---|
| `RecurringBilling/Index` · `Financeiro/Unificado/Index` | `PeriodBar` |
| `Patrimonio/{Index,Bens,Alocacoes,Manutencoes,Configuracoes}` | `Progress` `PeriodBar` `Breadcrumb` `Pagination` `Chart` |
| `superadmin/{Dashboard,Negocios,Pacotes,Assinaturas}/Index` | `Progress` `Pagination` `RegistrationMark` |
| `Officeimpresso/Logs/{Index,Timeline}` | `Progress` `PeriodBar` `Pagination` `RegistrationMark` |

⚠️ Derivado por **grep dos nomes no `-page.jsx` da âncora**, contra a lista *"sem par no `main`"* do
`design-system/HANDOFF.md §4`. O próprio HANDOFF §5 avisa que aquele mapa é **pista**: dos 40+ pares
herdados, 3 foram remedidos e **2 dos 3 estavam errados**. Antes de virar ADR, cada par se reconfere.

## 6 · Eixo token (tarefa 6) — **VALOR:0, drift:8**

| medida | valor | leitura |
|---|---|---|
| `tokens:build` | 4 escopos emitidos, **0 arquivo versionado alterado** | o `_generated-*.css` do git já estava em dia |
| `ds-push.mjs` → **VALOR** | **0** | `bundle == git canon` · 7 blocos · 8 valores reconciliados |
| `ds-mirror-drift.mjs` → **totalDiverge** | **8** (baseline 0) | `light 2 · dark 2 · cockpit-light 0 · cockpit-dark 4` |

**A resposta à sua pergunta — o seu build NÃO é o suspeito.** `VALOR:0` diz que o bundle bate com o
canon do git. O drift de 8 está do **outro lado**: o snapshot `prototipo-ui/design-system/colors_and_type.css`,
cujo último commit é **`4f51a9ec781` de 2026-09-11** (#7224) e que **esta sessão não tocou**
(`git status` do diretório: vazio). É dívida herdada de 5 dias, advisory, não introduzida aqui.

⚠️ **Mas a regra que você escreveu ("drift>0 ⇒ nenhuma tela do lote vira PR") continua mordendo** —
ela não pergunta de quem é a culpa. Pela sua própria régua, o lote está **barrado até o snapshot ser
refrescado**. Isso é decisão sua/[W]: ou refresca o snapshot, ou afrouxa a regra para `VALOR` apenas.

## 7 · A tabela (tarefa 3) — 162 linhas, ordenadas por balde

Colunas: `source` (protótipo) · `target` · módulo · `applicationState` · charter? · casos?(nº UC) ·
contrato? · âncora · componente-faltante · balde.

| source | target | módulo | state | charter | casos | contrato | âncora | comp-faltante | balde |
|---|---|---|---|---|---|---|---|---|---|
| `arquivos-page.jsx` | `Arquivos/Index.tsx` | Arquivos | pending | sim | sim(6UC) | `arquivos-index.contract.json` | E1 | - | **A** |
| `backup-page.jsx` | `Backup/Index.tsx` | Backup | pending | sim | sim(11UC) | `backup.contract.json` | E1 | - | **A** |
| `compras-page.jsx` | `Compras/Index.tsx` | Compras | pending | sim | sim(9UC) | `compras-cockpit.contract.json` | E1 | - | **A** |
| `fiscal-actions.jsx` | `Fiscal/Cockpit.tsx` | Fiscal | pending | sim | sim(13UC) | `fiscal-cockpit.contract.json` | E1 | - | **A** |
| `fiscal-page.jsx` | `Fiscal/Cockpit.tsx` | Fiscal | pending | sim | sim(13UC) | `fiscal-cockpit.contract.json` | E1 | - | **A** |
| `fiscal-page.jsx` | `Fiscal/Config.tsx` | Fiscal | pending | sim | sim(7UC) | `fiscal-config.contract.json` | E1 | - | **A** |
| `fiscal-subpages.jsx` | `Fiscal/Config.tsx` | Fiscal | pending | sim | sim(7UC) | `fiscal-config.contract.json` | E1 | - | **A** |
| `fiscal-page.jsx` | `Fiscal/Dfe.tsx` | Fiscal | pending | sim | sim(10UC) | `fiscal-dfe.contract.json` | E1 | - | **A** |
| `fiscal-subpages.jsx` | `Fiscal/Dfe.tsx` | Fiscal | pending | sim | sim(10UC) | `fiscal-dfe.contract.json` | E1 | - | **A** |
| `fiscal-page.jsx` | `Fiscal/Eventos.tsx` | Fiscal | pending | sim | sim(8UC) | `fiscal-eventos.contract.json` | E1 | - | **A** |
| `fiscal-subpages.jsx` | `Fiscal/Eventos.tsx` | Fiscal | pending | sim | sim(8UC) | `fiscal-eventos.contract.json` | E1 | - | **A** |
| `fiscal-page.jsx` | `Fiscal/Nfe.tsx` | Fiscal | pending | sim | sim(13UC) | `fiscal-nfe.contract.json` | E1 | - | **A** |
| `fiscal-page.jsx` | `Fiscal/Nfse.tsx` | Fiscal | pending | sim | sim(5UC) | `fiscal-nfse.contract.json` | E1 | - | **A** |
| `fiscal-page.jsx` | `Fiscal/Sped.tsx` | Fiscal | pending | sim | sim(17UC) | `fiscal-sped.contract.json` | E1 | - | **A** |
| `fiscal-subpages.jsx` | `Fiscal/Sped.tsx` | Fiscal | pending | sim | sim(17UC) | `fiscal-sped.contract.json` | E1 | - | **A** |
| `jana-telas-novas.jsx` | `Jana/Acoes.tsx` | Jana | pending | sim | sim(4UC) | `jana-acoes.contract.json` | E1 | - | **A** |
| `jana-telas-novas.jsx` | `Jana/Alertas.tsx` | Jana | pending | sim | sim(5UC) | `jana-alertas.contract.json` | E1 | - | **A** |
| `jana-merge.jsx` | `Jana/Index.tsx` | Jana | pending | sim | sim(22UC) | `jana-painel.contract.json` | E1 | - | **A** |
| `jana-telas-novas.jsx` | `Jana/Plataforma.tsx` | Jana | pending | sim | sim(5UC) | `jana-plataforma.contract.json` | E1 | - | **A** |
| `manufacturing-page.jsx` | `Manufacturing/Recipes.tsx` | Manufacturing | pending | sim | sim(8UC) | `manufacturing-recipes.contract.json` | E1 | - | **A** |
| `cowork-inbox/modulos/repo/resources/js/Pages/Modules/Index.tsx` | `Modules/Index.tsx` | Modules | pending | sim | sim(18UC) | `modulos.contract.json` | E1 | - | **A** |
| `modulos-page.jsx` | `Modules/Index.tsx` | Modules | pending | sim | sim(18UC) | `modulos.contract.json` | E1 | - | **A** |
| `ponto-page.jsx` | `Ponto/Dashboard/Index.tsx` | Ponto | pending | sim | sim(8UC) | `ponto-painel.contract.json` | E1 | - | **A** |
| `ponto-page.jsx` | `Ponto/Espelho/Index.tsx` | Ponto | pending | sim | sim(3UC) | `ponto-espelho.contract.json` | E1 | - | **A** |
| `ponto-page.jsx` | `Ponto/Espelho/Show.tsx` | Ponto | pending | sim | sim(5UC) | `ponto-espelho.contract.json` | E1 | - | **A** |
| `compras-grade-matrix.jsx` | `Purchase/Create.tsx` | Purchase | pending | sim | sim(7UC) | `purchase-create.contract.json` | E1 | - | **A** |
| `pg-payment-gateways-page.jsx` | `PaymentGateway» Settings/PaymentGateways/Index.tsx` | PaymentGateway | pending | sim | sim(0UC) | **NAO** | E0(nao-abre) | - | **B** |
| `inbox-page.jsx` | `Whatsapp» Atendimento/Channels/Index.tsx` | Whatsapp | pending | sim | NAO | **NAO** | n/a-declarado | - | **B** |
| `inbox-page.jsx` | `Whatsapp» Atendimento/Macros/Index.tsx` | Whatsapp | pending | sim | NAO | **NAO** | n/a-declarado | - | **B** |
| `essenciais-page.jsx` | `Essentials/Documents/Index.tsx` | Essentials | pending | sim | NAO | **NAO** | E0(nao-abre) | - | **B** |
| `hrm-page.jsx` | `Essentials/Holidays/Index.tsx` | Essentials | pending | sim | NAO | **NAO** | E1 | - | **B** |
| `essenciais-extras.jsx` | `Essentials/Knowledge/Index.tsx` | Essentials | pending | sim | NAO | **NAO** | E0(nao-abre) | - | **B** |
| `essenciais-page.jsx` | `Essentials/Knowledge/Index.tsx` | Essentials | pending | sim | NAO | **NAO** | E0(nao-abre) | - | **B** |
| `essenciais-page.jsx` | `Essentials/Messages/Index.tsx` | Essentials | pending | sim | NAO | **NAO** | E0(nao-abre) | - | **B** |
| `essenciais-page.jsx` | `Essentials/Reminders/Index.tsx` | Essentials | pending | sim | NAO | **NAO** | E0(nao-abre) | - | **B** |
| `hrm-page.jsx` | `Essentials/Settings/Index.tsx` | Essentials | pending | sim | NAO | **NAO** | n/a-declarado | - | **B** |
| `essenciais-page.jsx` | `Essentials/Todo/Index.tsx` | Essentials | pending | sim | NAO | **NAO** | E0(nao-abre) | - | **B** |
| `governance-page.jsx` | `governance/Audit.tsx` | governance | pending | sim | NAO | **NAO** | E1 | - | **B** |
| `governance-page.jsx` | `governance/Dashboard.tsx` | governance | pending | sim | NAO | **NAO** | E1 | - | **B** |
| `governance-page.jsx` | `governance/DriftAlerts.tsx` | governance | pending | sim | NAO | **NAO** | E1 | - | **B** |
| `repair-page.jsx` | `Repair/JobSheet/Index.tsx` | Repair | pending | sim | NAO | **NAO** | E1 | - | **B** |
| `vendas-extras.jsx` | `Sells/Caixa/Index.tsx` | Sells | pending | sim | NAO | **NAO** | E0(nao-abre) | - | **B** |
| `estoque-page.jsx` | `StockAdjustment/Create.tsx` | StockAdjustment | pending | sim | NAO | **NAO** | n/a-declarado | - | **B** |
| `estoque-page.jsx` | `StockAdjustment/Index.tsx` | StockAdjustment | pending | sim | NAO | **NAO** | n/a-declarado | - | **B** |
| `estoque-page.jsx` | `StockTransfer/Create.tsx` | StockTransfer | pending | sim | NAO | **NAO** | n/a-declarado | - | **B** |
| `estoque-page.jsx` | `StockTransfer/Index.tsx` | StockTransfer | pending | sim | NAO | **NAO** | n/a-declarado | - | **B** |
| `forja-aprova.jsx` | `Forja» Forja/Aprovacoes/Index.tsx` | Forja | pending | sim | sim(8UC) | **NAO** | E1 | - | **C** |
| `forja-page.jsx` | `Forja» Forja/Roadmap/Gantt.tsx` | Forja | pending | sim | sim(10UC) | **NAO** | E1 | - | **C** |
| `forja-page.jsx` | `Forja» Forja/Trabalho/Index.tsx` | Forja | pending | sim | sim(20UC) | **NAO** | E1 | - | **C** |
| `forja-page.jsx` | `Forja» team-mcp/Forja/Cockpit.tsx` | Forja | pending | sim | sim(16UC) | **NAO** | E1 | - | **C** |
| `inbox-page.jsx` | `Whatsapp» Atendimento/CaixaUnificada/Index.tsx` | Whatsapp | pending | sim | sim(16UC) | **NAO** | E1 | - | **C** |
| `cliente-form.jsx` | `Cliente/Create.tsx` | Cliente | pending | sim | sim(3UC) | **NAO** | E1 | - | **C** |
| `cliente-form.jsx` | `Cliente/Edit.tsx` | Cliente | pending | sim | sim(4UC) | **NAO** | E1 | - | **C** |
| `cliente-import.jsx` | `Cliente/Import.tsx` | Cliente | pending | sim | sim(1UC) | **NAO** | E1 | - | **C** |
| `clientes-page.jsx` | `Cliente/Index.tsx` | Cliente | pending | sim | sim(4UC) | **NAO** | E1 | - | **C** |
| `cliente-extrato.jsx` | `Cliente/Ledger.tsx` | Cliente | pending | sim | sim(5UC) | **NAO** | E1 | - | **C** |
| `cliente-mapa.jsx` | `Cliente/Map.tsx` | Cliente | pending | sim | sim(2UC) | **NAO** | E1 | - | **C** |
| `comunicacao-visual-page.jsx` | `ComunicacaoVisual/Index.tsx` | ComunicacaoVisual | pending | sim | sim(12UC) | **NAO** | n/a-declarado | - | **C** |
| `pg-cobranca-page.jsx` | `Financeiro/Cobranca/Index.tsx` | Financeiro | pending | sim | sim(7UC) | **NAO** | E1 | - | **C** |
| `financeiro-telas-extras.jsx` | `Financeiro/Conciliacao/Index.tsx` | Financeiro | pending | sim | sim(13UC) | **NAO** | E1 | - | **C** |
| `configuracoes-page.jsx` | `Financeiro/Configuracoes/Contador.tsx` | Financeiro | pending | sim | sim(4UC) | **NAO** | n/a-declarado | - | **C** |
| `financeiro-telas-extras.jsx` | `Financeiro/Dre/Index.tsx` | Financeiro | pending | sim | sim(6UC) | **NAO** | E1 | - | **C** |
| `financeiro-telas-extras.jsx` | `Financeiro/Fluxo/Index.tsx` | Financeiro | pending | sim | sim(6UC) | **NAO** | E1 | - | **C** |
| `financeiro-telas-extras.jsx` | `Financeiro/Impostos/Index.tsx` | Financeiro | pending | sim | sim(11UC) | **NAO** | E1 | - | **C** |
| `governance-page.jsx` | `governance/Policies.tsx` | governance | pending | sim | sim(9UC) | **NAO** | E1 | - | **C** |
| `jana-merge.jsx` | `Jana/Chat.tsx` | Jana | pending | sim | sim(19UC) | **NAO** | E1 | - | **C** |
| `jana-merge.jsx` | `Jana/Memoria.tsx` | Jana | pending | sim | sim(10UC) | **NAO** | E1 | - | **C** |
| `jana-pro.jsx` | `Jana/Pro.tsx` | Jana | pending | sim | sim(8UC) | **NAO** | n/a-declarado | - | **C** |
| `kb-page.jsx` | `kb/Index.tsx` | kb | pending | sim | sim(6UC) | **NAO** | E1 | - | **C** |
| `kb-page.jsx` | `kb/Index.v2.tsx` | kb | pending | sim | sim(10UC) | **NAO** | E1 | - | **C** |
| `manufacturing-page.jsx` | `Manufacturing/Index.tsx` | Manufacturing | pending | sim | sim(5UC) | **NAO** | n/a-declarado | - | **C** |
| `manufacturing-insumos.jsx` | `Manufacturing/Insumos.tsx` | Manufacturing | pending | sim | sim(5UC) | **NAO** | E1 | - | **C** |
| `manufacturing-producao.jsx` | `Manufacturing/Report.tsx` | Manufacturing | pending | sim | sim(5UC) | **NAO** | E1 | - | **C** |
| `manufacturing-producao.jsx` | `Manufacturing/Settings.tsx` | Manufacturing | pending | sim | sim(4UC) | **NAO** | E1 | - | **C** |
| `oficina-forms.jsx` | `OficinaAuto/AprovacaoPublica.tsx` | OficinaAuto | pending | sim | sim(7UC) | **NAO** | n/a-declarado | - | **C** |
| `oficina-page.jsx` | `OficinaAuto/ServiceOrders/Board.tsx` | OficinaAuto | pending | sim | sim(9UC) | **NAO** | E1 | - | **C** |
| `oficina-os-page.jsx` | `OficinaAuto/ServiceOrders/Show.tsx` | OficinaAuto | pending | sim | sim(11UC) | **NAO** | E1 | - | **C** |
| `oficina-forms.jsx` | `OficinaAuto/Vehicles/Create.tsx` | OficinaAuto | pending | sim | sim(3UC) | **NAO** | n/a-declarado | - | **C** |
| `oficina-forms.jsx` | `OficinaAuto/Vehicles/Edit.tsx` | OficinaAuto | pending | sim | sim(2UC) | **NAO** | n/a-declarado | - | **C** |
| `ponto-telas.jsx` | `Ponto/Aprovacoes/Index.tsx` | Ponto | pending | sim | sim(4UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/BancoHoras/Index.tsx` | Ponto | pending | sim | sim(4UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/BancoHoras/Show.tsx` | Ponto | pending | sim | sim(3UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Colaboradores/Edit.tsx` | Ponto | pending | sim | sim(2UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Colaboradores/Index.tsx` | Ponto | pending | sim | sim(3UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Configuracoes/Index.tsx` | Ponto | pending | sim | sim(1UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Configuracoes/Reps.tsx` | Ponto | pending | sim | sim(5UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Escalas/Form.tsx` | Ponto | pending | sim | sim(3UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Escalas/Index.tsx` | Ponto | pending | sim | sim(3UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Importacoes/Create.tsx` | Ponto | pending | sim | sim(2UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Importacoes/Index.tsx` | Ponto | pending | sim | sim(3UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Importacoes/Show.tsx` | Ponto | pending | sim | sim(5UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Intercorrencias/Create.tsx` | Ponto | pending | sim | sim(2UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Intercorrencias/Edit.tsx` | Ponto | pending | sim | sim(3UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Intercorrencias/Index.tsx` | Ponto | pending | sim | sim(3UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Intercorrencias/Show.tsx` | Ponto | pending | sim | sim(3UC) | **NAO** | E1 | - | **C** |
| `ponto-telas.jsx` | `Ponto/Relatorios/Index.tsx` | Ponto | pending | sim | sim(5UC) | **NAO** | E1 | - | **C** |
| `produtos-page.jsx` | `Produto/Index.tsx` | Produto | pending | sim | sim(6UC) | **NAO** | n/a-declarado | - | **C** |
| `produtos-page.jsx` | `Produto/Unificado/Index.tsx` | Produto | pending | sim | sim(21UC) | **NAO** | n/a-declarado | - | **C** |
| `cobranca-recorrente-page.jsx` | `RecurringBilling/Configuracoes/Index.tsx` | RecurringBilling | pending | sim | sim(8UC) | **NAO** | E1 | - | **C** |
| `cobranca-recorrente-page.jsx` | `RecurringBilling/Faturas/Index.tsx` | RecurringBilling | pending | sim | sim(13UC) | **NAO** | E1 | - | **C** |
| `cobranca-recorrente-page.jsx` | `RecurringBilling/Planos/Create.tsx` | RecurringBilling | pending | sim | sim(2UC) | **NAO** | n/a-declarado | - | **C** |
| `cobranca-recorrente-page.jsx` | `RecurringBilling/Planos/Index.tsx` | RecurringBilling | pending | sim | sim(3UC) | **NAO** | E1 | - | **C** |
| `repair-page.jsx` | `Repair/Dashboard/Index.tsx` | Repair | pending | sim | sim(5UC) | **NAO** | E1 | - | **C** |
| `repair-page.jsx` | `Repair/DeviceModels/Index.tsx` | Repair | pending | sim | sim(8UC) | **NAO** | n/a-declarado | - | **C** |
| `repair-page.jsx` | `Repair/Index.tsx` | Repair | pending | sim | sim(4UC) | **NAO** | n/a-declarado | - | **C** |
| `repair-page.jsx` | `Repair/ProducaoOficina/Index.tsx` | Repair | pending | sim | sim(6UC) | **NAO** | n/a-declarado | - | **C** |
| `repair-page.jsx` | `Repair/Settings/Index.tsx` | Repair | pending | sim | sim(8UC) | **NAO** | n/a-declarado | - | **C** |
| `repair-page.jsx` | `Repair/Status/Index.tsx` | Repair | pending | sim | sim(8UC) | **NAO** | E1 | - | **C** |
| `venda-v3/sells-create.jsx` | `Sells/CreateV3.tsx` | Sells | pending | sim | sim(41UC) | **NAO** | E1 | - | **C** |
| `suporte-page.jsx` | `Suporte/Visao.tsx` | Suporte | pending | sim | sim(4UC) | **NAO** | E1 | - | **C** |
| `perfil-page.jsx` | `User/Perfil.tsx` | User | pending | sim | sim(2UC) | **NAO** | E1 | - | **C** |
| `vestuario-page.jsx` | `Vestuario/Etiquetas/Index.tsx` | Vestuario | pending | sim | sim(9UC) | **NAO** | n/a-declarado | - | **C** |
| `officeimpresso-page.jsx` | `Officeimpresso» Officeimpresso/Logs/Index.tsx` | Officeimpresso | pending | sim | sim(13UC) | **NAO** | E1 | Progress+PeriodBar+Pagination+RegistrationMark | **D** |
| `officeimpresso-page.jsx` | `Officeimpresso» Officeimpresso/Logs/Timeline.tsx` | Officeimpresso | pending | sim | sim(7UC) | **NAO** | E1 | Progress+PeriodBar+Pagination+RegistrationMark | **D** |
| `superadmin-page.jsx` | `Superadmin» superadmin/Assinaturas/Index.tsx` | Superadmin | pending | sim | sim(17UC) | `superadmin-assinaturas.contract.json` | E1 | Progress+Pagination+RegistrationMark | **D** |
| `superadmin-page.jsx` | `Superadmin» superadmin/Dashboard/Index.tsx` | Superadmin | pending | sim | sim(7UC) | `superadmin-dashboard.contract.json` | E1 | Progress+Pagination+RegistrationMark | **D** |
| `superadmin-page.jsx` | `Superadmin» superadmin/Negocios/Index.tsx` | Superadmin | pending | sim | sim(8UC) | `superadmin-negocios.contract.json` | E1 | Progress+Pagination+RegistrationMark | **D** |
| `superadmin-page.jsx` | `Superadmin» superadmin/Pacotes/Index.tsx` | Superadmin | pending | sim | sim(8UC) | `superadmin-pacotes.contract.json` | E1 | Progress+Pagination+RegistrationMark | **D** |
| `financeiro-page.jsx` | `Financeiro/Unificado/Index.tsx` | Financeiro | pending | sim | sim(9UC) | **NAO** | E1 | PeriodBar | **D** |
| `patrimonio-page.jsx` | `Patrimonio/Alocacoes.tsx` | Patrimonio | pending | sim | sim(4UC) | `patrimonio-alocacoes.contract.json` | E1 | Progress+PeriodBar+Breadcrumb+Pagination+Chart | **D** |
| `patrimonio-page.jsx` | `Patrimonio/Bens.tsx` | Patrimonio | pending | sim | sim(3UC) | `patrimonio-bens.contract.json` | E1 | Progress+PeriodBar+Breadcrumb+Pagination+Chart | **D** |
| `patrimonio-page.jsx` | `Patrimonio/Configuracoes.tsx` | Patrimonio | pending | sim | sim(4UC) | `patrimonio-configuracoes.contract.json` | E1 | Progress+PeriodBar+Breadcrumb+Pagination+Chart | **D** |
| `patrimonio-page.jsx` | `Patrimonio/Index.tsx` | Patrimonio | pending | sim | sim(8UC) | `patrimonio-index.contract.json` | E1 | Progress+PeriodBar+Breadcrumb+Pagination+Chart | **D** |
| `patrimonio-page.jsx` | `Patrimonio/Manutencoes.tsx` | Patrimonio | pending | sim | sim(3UC) | `patrimonio-manutencoes.contract.json` | E1 | Progress+PeriodBar+Breadcrumb+Pagination+Chart | **D** |
| `cobranca-recorrente-page.jsx` | `RecurringBilling/Index.tsx` | RecurringBilling | pending | sim | sim(8UC) | **NAO** | E1 | PeriodBar | **D** |
| `boletos-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `cms-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `comissionados-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `comissoes-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `connector-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `documentacao-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `equipe-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `funcoes-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `notificacoes-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `orc-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `os-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `planilhas-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `prefs-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `producao-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `prototipos/payment-gateway-ui/cobranca-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `usuarios-page.jsx` | `—` | — | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `crm-blade-forms.jsx` | `Crm/Acompanhamentos.tsx` | Crm | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `crm-blade.jsx` | `Crm/Acompanhamentos.tsx` | Crm | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `crm-blade.jsx` | `Crm/Leads.tsx` | Crm | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `crm-blade.jsx` | `Crm/Painel.tsx` | Crm | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `crm-portal.jsx` | `Crm/Portal.tsx` | Crm | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `programa-doc-page.jsx` | `Documentacao/Programa.tsx` | Documentacao | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `ponto-fechamento.jsx` | `Ponto/Conformidade.tsx` | Ponto | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `ponto-fechamento.jsx` | `Ponto/Fechamento.tsx` | Ponto | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `ponto-mobile.jsx` | `Ponto/RepP.tsx` | Ponto | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `catalogo-qr-page.jsx` | `ProductCatalogue/CatalogueQr.tsx` | ProductCatalogue | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `relatorios-page.jsx` | `Relatorios/Index.tsx` | Relatorios | to-create | NAO | NAO | **NAO** | nulo(ambigua) | - | **E** |
| `voz-do-cliente-page.jsx` | `VozDoCliente/Caixa.tsx` | VozDoCliente | to-create | NAO | NAO | **NAO** | sem-charter | - | **E** |
| `hrm-extras.jsx` | `Essentials/Metas.tsx` | Essentials | pending | sim | sim(7UC) | `essentials-metas.contract.json` | E0(nao-abre) | - | **F** |
| `hrm-page.jsx` | `Essentials/Tipos.tsx` | Essentials | pending | sim | sim(9UC) | `essentials-tipos.contract.json` | E0(nao-abre) | - | **F** |
| `dash-legacy-page.jsx` | `Home/Index.tsx` | Home | pending | sim | sim(19UC) | `dashboard-visao-geral.contract.json` | E0(nao-abre) | - | **F** |
| `oficina-forms.jsx` | `OficinaAuto/ServiceOrders/Create.tsx` | OficinaAuto | pending | sim | sim(4UC) | **NAO** | E0(nao-abre) | - | **F** |
| `oficina-forms.jsx` | `OficinaAuto/ServiceOrders/Edit.tsx` | OficinaAuto | pending | sim | sim(7UC) | **NAO** | E0(nao-abre) | - | **F** |
| `compras-page.jsx` | `Purchase/Index.tsx` | Purchase | pending | sim | sim(6UC) | **NAO** | E0(nao-abre) | - | **F** |
| `compras-page.jsx` | `Purchase/Show.tsx` | Purchase | pending | sim | sim(6UC) | **NAO** | E0(nao-abre) | - | **F** |
| `vendas-create-page.jsx` | `Sells/Create.tsx` | Sells | pending | sim | sim(2UC) | **NAO** | E0(nao-abre) | - | **F** |
| `vendas-page.jsx` | `Sells/Index.tsx` | Sells | pending | sim | sim(5UC) | **NAO** | E0(nao-abre) | - | **F** |

## 8 · `screen-coverage-map` (o 5º dono) — exit 0

```
220 telas · CHARTER 220/220 (100%) · SCORECARD 184/220 (83.6%)
E2E (Browser ∪ VRT) 63/220 (28.6%) · A11Y (axe) 20/220 (9.1%)
VISREG pixel 52/220 (23.6%) [advisory] · VISREG L2 estados 8/220 (3.6%) [advisory]
```

Cruzando com o §5: os **26** do balde A têm contrato de comportamento, mas **E2E e A11Y não
acompanham** — 28,6% e 9,1% na frota. "Executável hoje" aqui significa *o contrato trava o
comportamento no CI*, **não** *existe teste de browser para a tela*.

## 9 · O que este recibo NÃO mediu (bloco 7 aplicado a mim)

1. **Não rodei o "2º staging"**, e a instrução da thread está superada pelo código. O
   `oimpresso-erp-conunica-o-visual/project` só existe em `C:\Users\wagne\Downloads\_cowork-handoff-staging`
   — lugar que o §5 2026-08-13 proíbe ler como fonte de design, e que o próprio `ancora.mjs:1300`
   chama de *"LUGAR PROIBIDO pro ancora-guard"*. Não é perda: a perna 3 do `resolveAncora` (`:516`)
   resolve o `-page.jsx` do bundle **no lugar fixo do repo, sem `--staging`** — e foi o que rodou.
   Usei `--staging prototipo-ui/cowork` (in-repo).
2. **Não passei de E1.** `E2` (símbolo), `E3` (`ds-anchor-check --check`) e `E4` (pixel) ficaram de
   fora: a R1 do `ds-anchor-check` está medida em **73,7% de falso-positivo** (§5 2026-08-25) e o
   *alcance* dela nunca foi resolvido — rodá-la em 162 telas produziria ruído com cara de veredito.
   Logo, `E1` aqui significa **"a âncora abre"**, nunca "o conteúdo confere".
3. **Rodei o `--list`, mas não o usei como veredito.** Ele é chaveado por **rota** e implementa
   metade da regra (§5 2026-08-28). Todo `E` da tabela veio da porta **per-tela**.
4. **`componente-faltante` é PISTA** (grep de nome no `-page.jsx`), não par reconferido — o
   `HANDOFF §5` declara que o mapa herdado errou em 2 de 3 quando remedido.
5. **Não olhei o CONTEÚDO de nenhum contrato** — só existência e vínculo (`tela` / `alvo`).
   Um `*.contract.json` presente não prova que ele descreve a tela certa.
6. **Nenhum pixel, nenhum runtime, nenhuma tela medida em produção.** Isto é medição de cobertura;
   nada aqui autoriza export de layout.
7. **Não apliquei o `_PATCH-INDICE` no `00-INDICE.md`** (razão no §1) e **não executei as threads
   01–03** — elas seguem barradas até o caminho ser corrigido na fonte.
8. **Não medi se `Relatorios/Index` é o mesmo caso dos outros 8 do balde F.** Ele é o único
   `nulo(ambígua)` das 162 — o `resolveAncora` devolve ambiguidade, e pelo contrato de evidência
   *"query ambígua = veredito nulo, não E1"*. Precisa de desempate antes de entrar em qualquer lote.
9. **`transportChanges: 4` é o transporte DESTA sessão.** Quem reler o report amanhã vai ver outro
   número; ele não acumula.
