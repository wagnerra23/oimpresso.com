---
thread: "07"
modulo: ancora
titulo: "Cruzar as 162 telas do application-report com trio/contrato/âncora — usando os donos que JÁ existem"
dono_pedido: "[CC]"
executor: "[CL]"
gerado: 2026-09-16T19:00Z
base_lido: "main @c1f77b029185 — application-report.json (324.506 B, por busca) · active-bundle.json (166.177 B, por busca) · prototipo-ui/design-system/HANDOFF.md (9.308 B, inteiro) · aplicar-payload.mjs (24.986 B, inteiro) · gerar-payload-partes.mjs (14.239 B, inteiro) · árvore de resources/js/Pages (330 de 805 por regex)"
alvo_medido: "n/a — é medição de cobertura, não export de layout. Nada aqui autoriza pixel."
---
# Thread 07 · o que falta pro Code EXECUTAR cada tela (e por que o report não sabe responder)

> **Leia só este arquivo.** Não precisa da conversa.
> **Peço execução e números, não código novo.** Nenhum script novo: os donos já existem no `main` (§3). Se eu pedisse um "gerador de planilha de prontidão", eu estaria recriando `design-coverage` + `casos-coverage-guard` + `screen-coverage-map` — o vício que a minha própria L-42 nomeia.

## 1 · O fato que abre a thread
O delta `6270479598680fc21409b6d6703b7dc62b0b41bf356108e2055ba2d572aba006` foi promovido em **2026-09-16T18:09:02.671Z** (`mode: delta`, base `b19625fb…`, `mirrorScope: tree`, **702 arquivos · 10.363.749 B**, `unchanged: 278`). O `application-report.json` (schema `oimpresso-design-application-report/3`) declara:

| campo | valor |
|---|---|
| `transportChanges` | **424** |
| `screens` | **162** |
| `tested` | **0** |
| `smoked` | **0** |
| `pending` | **134** |

⇒ **os bytes pousaram; nenhuma tela foi executada.** `to-create` = 162 − 134 = **28** (não li o campo; é subtração — se houver um 3º estado, o número muda e quem manda é o seu `--json`). **Não li as linhas 16–19 do `summary`** (4 campos) — não afirmo `applied`/`blocked`.

## 2 · O buraco: o report não carrega entendimento
Busquei, no indent das entradas, por `charter|casos|contract|anchor|reason|blockers|missing|needs|readiness|trio|tokens|styleFingerprint`: **0 hit**. Cada entrada tem **só** `source` → `target` → `module` → `applicationState`.

Ou seja: a máquina diz **onde pousa** e **se falta receptor**. Não diz se a tela tem charter, `.casos.md` (UC), contrato de tela, ou componente que não existe. **Quem separa "dá pra executar hoje" de "falta UC" de "falta componente" não é o report** — são os donos do §3, e ninguém cruzou os dois.

Os 28 `to-create` que a busca nomeou (a maioria com `target: "—"` e `module: null`): `boletos` · `catalogo-qr` · `cms` · `comissionados` · `comissoes` · `connector` · `crm-blade` (3×) · `crm-blade-forms` · `crm-portal` · `documentacao` · `equipe` · `funcoes` · `notificacoes` · `orc` · `os` · `planilhas` · `ponto-fechamento` (2×) · `ponto-mobile` · `prefs` · `producao` · `programa-doc` · `prototipos/payment-gateway-ui/cobranca` · `relatorios` · `usuarios` · `voz-do-cliente`.
**`ponto-fechamento` e `ponto-mobile` como `to-create` confirmam por MÁQUINA o furo F4 de 14/09** ("sem receptor") — até agora era só leitura minha de charter.

## 3 · Os donos que já existem (não peça script novo)
Colhidos dos workflows/hooks do `main` neste turno — `.claude/workflows/migracao-layout-em-ondas.js:99,133` e `validador-modulo-prototipo.js:26,96`:

`module-surface.mjs --migracao` · `blade-migration-census.mjs --json` · **`screen-coverage-map.mjs`** (e `--screen`) · **`casos-coverage-guard.mjs --report`** · **`design-coverage.mjs`** · `reuse-index` · `scripts/design/gerar-contrato.mjs` + `gerar-map.mjs` · `scripts/design/ancora.mjs` (com **os dois stagings**: `prototipo-ui/cowork/Wagner` **e** `oimpresso-erp-conunica-o-visual/project`).
**`scripts/qa/prototipo-readiness.mjs`: não confirmei neste turno** — a busca saiu **bounded** (314 de 400 arquivos, budget 10 s, 83 arquivos acima de 512 KB não varridos) e **busca truncada não prova ausência** (é a minha regra de 09/09, e hoje eu quase repeti a inversa: o listador de árvore **não indexa `.mjs`**, deu 0 de 17.005 para `^scripts/.*\.mjs$`, e os arquivos **leem normal** por caminho direto). Se ele existe, é ele o primeiro da lista.

## 4 · ⚠️ Defeito de caminho no PRÓPRIO playbook da âncora (conserte antes das threads 01–03)
O `00-INDICE.md` desta pasta declara `ALVO: prototipo-ui/ancora.mjs` (**49.089 B**) e as 3 threads têm `prefixo: ["prototipo-ui/ancora.mjs"]` com todas as provas nesse caminho. **O leitor real não é esse arquivo.** Medido agora:
- `.claude/hooks/post-merge-ui-smoke-required.mjs:316` → `import(join(REPO,'scripts','design','ancora.mjs'))`, e `:318` degrada com *"import de scripts/design/ancora.mjs falhou"*.
- `post-merge-ui-smoke-required.test.mjs:175` → `existsSync(join(REPO_REAL,'scripts','design','ancora.mjs'))`.
- `block-ancora-no-olho.mjs:84`, `charter-validate.mjs:117`, os 3 workflows e a skill `refutador-gt-g5` **todos** mandam `node scripts/design/ancora.mjs`.

⇒ **as threads 01–03 iam editar um arquivo que nenhum consumidor carrega.** Eu já registrei em 14/09 que o `ancora.mjs` mudou de casa e **não corrigi este índice** — defeito meu, aqui declarado. **Antes de executar 01/02/03: remedir o caminho e reapontar prefixo e provas para `scripts/design/ancora.mjs`.**

## 5 · O que EU medi por fora, e que já dá pra agir
- **Trio:** `resources/js/Pages/**` tem **330** arquivos `*.charter.md`+`*.casos.md` (330 de 805 por regex). Assimetrias que **reprovam o trio** (charter sem UC, ou UC sem charter): `Estoque/Movimentacao` **só `.casos.md`** · `Auditoria` 2/0 · `Nfse` 3/0 · `ConsultaOs` 1/0 · `governance` 9/2 · `Essentials/{Documents,Holidays,Knowledge,Messages,Reminders,Settings,Todo}` ≈14/0 · `kb/_components` 3/0 · `Sells/{Drafts,Quotations,Subscriptions,Caixa}` 4/0 · `Site` 2/0 · `StockAdjustment` 2/0 · `StockTransfer` 2/0 · `TransactionPayment` 3/0 · `Whatsapp/{Settings,Templates}` 2/0 · `Repair/JobSheet/Index` 1/0. **Charter sem `.casos.md` = sem UC ⇒ prontidão não fecha.**
- **Contrato:** **36** `*.contract.json` em `governance/design/contracts/` para **162** telas ⇒ ≈78% sem comportamento travado no CI (mesma ordem do 2-para-21 do Ponto).
- **Componente sem par no `main`** (HANDOFF §4/§5, lido inteiro): `PageHeader.context`/`freshness` · `Progress` · `DatePicker` · `PeriodBar` · `Breadcrumb` · `Pagination` · `FsmStepper` · `Chart` · `TaskCard`/`BoardColumn` · `Logo` · print-craft (`ProofFrame`, `Dimension`, `ProofStrip`, `RegistrationMark`, `PresenterMode`). E o §5 avisa: dos 40+ pares herdados, **3 foram remedidos e 2 dos 3 estavam errados** ⇒ o mapa é **pista**. Tela que usa esses componentes **não é "pending de aplicar": é pendente de componente + ADR**.
- **Token e comportamento, como o Code os recebe:** token pelo loop **VALOR:0** (DTCG `resources/css/tokens/*.tokens.json` → Style Dictionary → `_generated-*.css`; `ds-push.mjs` sai ≠0 se VALOR>0; `ds-mirror-drift.mjs` de sentinela, baseline `totalDiverge: 0` nos 4 escopos). Regra dura #1: **`#hex`/`oklch()` literal ou CSS cru do espelho em arquivo de módulo é regressão**. Comportamento **não vem do meu CSS**: vem de `.casos.md` + `*.contract.json` + gates do §7. **Meu `page.css` é alvo visual, não contrato.**

## 6 · LISTA DE TAREFAS — [CL], nesta ordem
1. **Conserte o caminho da âncora neste playbook** (§4): reaponte `ALVO`/`prefixo`/`provas` de 01–03 para `scripts/design/ancora.mjs`, remedindo o sha. **Não execute 01–03 antes disso.**
2. **Confirme ou derrube `scripts/qa/prototipo-readiness.mjs`** por leitura direta do caminho + controle positivo (busca bounded não vale). Diga qual é o dono real da prontidão.
3. **Rode os donos do §3** sobre o universo do report e devolva **UMA tabela** (no `_saida-07.md`, não em arquivo novo de retrato — mapa é comando): `source · target · module · applicationState · charter? · casos? · contrato? · âncora (E0–E4) · componente-faltante`. Use os **dois** stagings do `ancora.mjs`.
4. **Classifique cada uma das 162 em exatamente um balde** e me dê as contagens: **(A) executável hoje** (trio completo + contrato + âncora ≥E1 + zero componente faltante) · **(B) falta UC/charter** · **(C) falta contrato** · **(D) falta componente + ADR** · **(E) sem receptor — decisão de [W]**. É esta classificação, não o `pending: 134`, que diz o que dá pra fazer amanhã.
5. **Cheque o `summary` inteiro** e me diga os 4 campos das linhas 16–19 e se existe 3º estado além de `pending`/`to-create` (a minha subtração 162−134=28 depende disso).
6. **Rode `tokens:build` + `ds-push.mjs` + `ds-mirror-drift.mjs`** e devolva **VALOR** e `totalDiverge`. Se VALOR>0 ou drift>0, **nenhuma tela do lote deveria virar PR** — e eu preciso saber, porque o meu build é o suspeito natural.
7. **Devolva o recibo em `_saida-07.md`**: exit code de cada dono, a tabela, as 5 contagens, VALOR/drift, e a linha "o que NÃO mediu".

## 7 · O que esta thread NÃO resolve (bloco 7)
- **Não rodei `node`** — nenhum veredito de `--list`/`--report`/`--selftest` é afirmado. Todo número meu é leitura de código, busca ou árvore, com a fonte citada.
- **Não cruzei tela→trio→contrato linha por linha** (162 linhas): só agregados e assimetrias **por diretório**. A tabela do passo 3 é justamente o que falta, e é sua.
- **Não decide reancoragem.** `D-SIMBOLO` e `D-COMPONENT` seguem de [W] — sem `D-SIMBOLO`, módulo muitos-para-um tem **teto E1** e o contrato de tela não é decidível pela âncora.
- **Não autoriza pixel.** Zero tela medida neste ciclo; sem os 10 blocos, não é pacote de export.
- **Não toca `resources/js/**`, `memory/**` nem `governance/**`.**
