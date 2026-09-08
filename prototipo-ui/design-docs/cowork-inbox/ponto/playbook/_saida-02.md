---
sessao: "02"
titulo: "Desamarrar UC ⛓ — saída da thread"
autor: "[CL]"
criado: 2026-09-08
base: a364bd65ede3
thread: 02-uc-desamarrar.md
constituicao: "CONSTITUICAO-COWORK.md (C1–C12) + memory/proibicoes.md"
veredito: "entregue — 0 UC desamarrado por mim, e o número é esse mesmo: os 18 ⛓ de 04/09 já estavam em ZERO nesta sha. O trabalho que sobrou era outro e foi feito: 63 Status defasados do veredito REAL da lane."
---

# _saída 02 · Desamarrar UC ⛓

## Resposta curta à prova que a thread pede

| medida | valor | como medi |
|---|---|---|
| **UC ⛓ ANTES** | **0** | `npm run casos:report` — 142 presos em 16 módulos, **nenhum é Ponto** |
| **UC ⛓ DEPOIS** | **0** | idem, pós-PR |
| **UC órfãos (Ponto)** | **0** | os 11 do repo são Forja 6 · governance 4 · kb 1 |
| **lane `ponto-pest`** | **verde** | run `34215745965` (main `dced5fd3d8`): **302 passed · 1 skipped · 1009 assertions** |

**Zero UC desamarrado por mim.** Não porque eu não fiz — porque **já estavam**. O brief dizia
18 ⛓ em 04/09 e mandava *"medir antes"*: os 44 `*ContratoTest` que nasceram entre 04/09 e esta
sha zeraram a fila. A thread encontrou o trabalho feito e mediu para provar.

## 1 · O que estava feito (e como provei que estava, em 3 caminhos)

Não bastava o relatório dizer "Ponto não aparece" — ausência de um módulo numa lista é exatamente
a falsa-ausência que o §5 cataloga. Os três caminhos:

1. **`casos:report`** — Ponto ausente da lista de presos (142 em 16 módulos).
2. **`scripts/casos-coverage-baseline.json`** — **0 entradas** do Ponto nas 86 do baseline.
3. **Medição UC-a-UC** replicando `buildTestCorpus`/`buildTestTitleCorpus` do guard:
   **69 no TÍTULO · 0 docblock · 0 órfão**.

E antes disso, a pergunta que decide se o zero é real ou artefato de escopo: **as telas do Ponto
entram no denominador?** `isPageScreenPath()` (fonte única `scripts/qa/page-path.mjs`) responde
**sim para as 21** — inclusive `Welcome.tsx`, que é flat e mesmo assim está DENTRO
(21 `casos.md`, não 20; meu primeiro pathspec `Ponto/**/*.tsx` perdeu o flat e eu corrigi).

**Controle positivo do medidor** (senão eu estaria confiando num instrumento que não discrimina):
`UC-CAT-01`, ⛓ conhecido do Financeiro, deu `corpus=true titulo=false`; `UC-FORJA-03`, órfão
conhecido, deu `corpus=false titulo=false`. O medidor separa as três classes.

## 2 · O que sobrou, e foi feito — 63 Status que não diziam a verdade

Medindo o passo 4 do brief (*"Status com o resultado real da lane"*) apareceu a divergência real:

| | antes | depois |
|---|---|---|
| ✅ verde na lane | 6 | **69** |
| 🧪 sem veredito | 61 | 0 |
| ⬜ não verificado | 2 | 0 |

O manifesto `scripts/casos-test-results.json` (derivado do JUnit, fonte
`test-results/pest-ponto-junit.xml`) tem **`verdict: pass` para 69 de 69**. As tabelas diziam
"sem veredito". Havia veredito — e é verde. Aplicada a precedência de
[proibicoes.md](../../../../../memory/proibicoes.md) §"REGRA DE PRECEDÊNCIA" — *teste verde >
casos* — e a regra de ação: **conflito detectado = corrigir o perdedor no MESMO PR**.

**A lane rodou de verdade (LC-13).** `success` não prova execução: esta lane tem skip-as-pass por
`dorny/paths-filter`. Li **assertions**, não a conclusion — **1009**, com `coherent: true` e
`provou_algo: true` no resumo da própria run. O único `skipped` da run não é UC (o coletor trata
skip como não-pass, e os 69 vieram `pass`).

**Não carimbei sobre máquina alheia.** Verifiquei antes que o símbolo não tem dono automático:
`casos-results-publish.yml` só aterrissa o JSON; `criar-tela.mjs:576` diz que o Status *"vira
🧪/✅ quando o teste executar e passar"* e o coletor declara que o valor "é DECLARADO por humano".
Editar aqui é o caminho previsto, não uma máquina paralela.

## 3 · Descobertas — as 5 predições de vermelho que CADUCARAM

5 UCs declaravam `🧪 **vermelho ESPERADO** (predição)`: nasceram *failing-first* denunciando
defeito medido. O manifesto diz `pass`. Isso é ou bug corrigido, ou teste enfraquecido — e a
diferença importa, então **medi os 5, não 1** (§5 2026-08-03: consertar um da família e não olhar
os irmãos):

| UC | defeito que o teste denunciava | estado hoje | recibo |
|----|-------------------------------|-------------|--------|
| `UC-ESPSH-01` | espelho lia `tem_divergencia` (atributo fantasma) | **corrigido** | o próprio docblock data: `20a2757a5e` + F3 #6115 |
| `UC-ESCF-01` | controller lia `entrada`, coluna é `hora_entrada` | **corrigido** | `EscalaController.php:83-86` mapeia `entrada` a partir de `hora_entrada` |
| `UC-ESCF-02` | `update(Request)` chamava `validated()` → BadMethodCall | **corrigido** | `EscalaController.php:109` recebe `StoreEscalaRequest` |
| `UC-IMPIDX-03` | lista lia `linhas_criadas`, coluna é `linhas_sucesso` | **corrigido** | `ImportacaoController.php:44` lê `linhas_sucesso` |
| `UC-INTCRE-01` | `business_id` **nunca** atribuído na criação (Tier 0) | **corrigido** | `IntercorrenciaController.php:116` injeta via session/auth (US-PONTO-013) |

**Nenhum assert foi tocado** — a thread proíbe, e não foi preciso: os asserts seguem intactos e
foram eles que ficaram verdes quando o código foi consertado. Esses 5 receberam
`✅ verde na lane (predição de vermelho caducou)`, preservando o rastro.

⚠️ **`UC-INTCRE-01` merece registro à parte:** era gravação que não gravava por `business_id`
ausente — Tier 0. Está **corrigido em produção**, não é incidente aberto. Verifiquei porque a
thread manda parar e escalar se `CrossTenant*` revelar vazamento real; **não revelou**.

## 4 · Não feito, e por quê

- **Nenhum teste novo, nenhuma assertion nova** — a thread proíbe, e nada exigiu.
- **Nenhum UC virou `[BACKLOG · ⬜ sem teste]`**: a lista de órfãos do Ponto é **vazia**. O padrão
  existe para UC ⛓ sem teste correspondente; não havia nenhum.
- **Não rodei Pest** — nem local nem no CT 100. O veredito que uso é o da **lane no `main`**, que
  é o oráculo certo para "a lane está verde"; rodar no CT 100 mediria outra árvore (o checkout de
  lá está em 2026-07-23 com alterações não-commitadas de outra sessão).
- **Não toquei** `.tsx`, `.charter.md`, o texto dos UC, `Services/`, `Http/` nem contratos.

## 5 · Decisão minha, registrada (não é pergunta pro [W])

**1 PR com 21 arquivos**, contra a letra de **C6** (`≤ 8 arquivos`). O que C6 protege é
revisabilidade, e aqui o diff é **63 linhas de UC** (bem abaixo do teto de 300), **1 assunto**,
**um glyph por linha**, derivado de uma fonte de máquina única, com teste de identidade colado no
PR: **105 `+` / 105 `−`, troca 1:1, zero linha fora de `| UC-` e `last_run*`**. Quebrar isso em
4 PRs multiplicaria CI e merge do [W] sem reduzir risco nenhum. Declaro aqui porque exceção
silenciosa é pior que exceção — se o [W] preferir fatiado, refatio.

## 6 · Prefixo tocado

- `resources/js/Pages/Ponto/**/*.casos.md` — **só** as colunas `Status`, `last_run`, `last_run_ci` (21 arquivos)
- `prototipo-ui/design-docs/cowork-inbox/ponto/playbook/_saida-02.md` — este arquivo

`Modules/Ponto/Tests/Feature/**` faz parte do prefixo e **não precisou de uma linha**: já estava
convertido para `it('UC-…')`.

## 7 · Duas falhas de CI que NÃO são desta thread (medidas, não deduzidas)

O PR abriu com 2 checks vermelhos, nenhum dos dois no meu raio. Não deduzi causa por
proximidade — rodei os dois guards contra `origin/main` **puro**, num worktree limpo:

| check | veredito | prova |
|---|---|---|
| `SUPERFICIE.md == árvore` | **era base defasada — RESOLVIDO** | meu branch `exit 1` × main `exit 0`. O fix estava no `2052c46ae3` (#7051), que entrou **depois** da minha base. `git merge origin/main` resolveu; agora `exit 0`. |
| `PageHeader · ratchet` | **herdado do main — NÃO consertei** | `origin/main` em `b7c1581e21e3` dá `exit 1` com a mesma tela, sem um byte meu. |

**Por que não consertei o PageHeader.** A causa é `resources/js/Pages/Patrimonio/Index.tsx`,
que entrou pelo **#7040** (playbook Patrimônio, hoje) importando `@/Components/shared/PageHeader`
— e é a **única das 4 telas do módulo** ainda no header antigo (`Alocacoes`, `Bens` e
`Configuracoes` já usam o canon). Consertar aqui seria: tocar `.tsx` (a thread proíbe
literalmente), migrar UI de **outro módulo** dentro de um PR de docs do Ponto (viola *1 PR = 1
intent*), sem charter, sem gate visual e sem smoke — e trocar o import não basta, porque a API do
canon v3.8 é outra (3 zonas + SubNav, ADR 0189/0190).

O gate é **advisory** — li a união `classic_protection ∪ rulesets` do
`governance/required-checks-baseline.json` (45 contextos): `PageHeader · ratchet` não está lá.
Reprova é visível, não bloqueia o merge do [W].

⚠️ **Não leia o verde deste PR como conserto.** Depois do push do merge o check **sumiu da lista**
(122 → 82 checks) — e não foi porque passou: [`pageheader-gate.yml`](../../../../../.github/workflows/pageheader-gate.yml)
declara `pull_request: types: [opened, reopened, ready_for_review]`, **sem `synchronize`**. Ele roda
na abertura do PR e não re-executa a cada commit. A dívida segue no `main`, intacta e medida acima;
o PR só deixou de perguntar.

⚠️ **Erro meu, corrigido aqui:** este `_saida` nasceu com `base: 2052c46ae302`. Errado — li o
`origin/main` **depois** de já ter criado a branch. A base real é `a364bd65ede3`, e foi
exatamente essa defasagem que produziu o vermelho do `SUPERFICIE`.

## 8 · Achados para quem vier depois

1. **O `00-INDICE.md` §1 diz `casos.md 21/21` e o §2-bis fotografa 18 ⛓** — o segundo número
   caducou em 4 dias. Não editei o índice (a thread proíbe); fica registrado aqui.
2. **A thread 02 nasceu para um trabalho que outra onda fez antes.** O sinal honesto para o
   playbook é que threads de conversão devem **medir na abertura**, como esta mediu.
3. **O eixo que ainda tem folga não é o do Ponto:** 142 UC ⛓ vivos em 16 módulos, liderados por
   Financeiro (30), Cliente (21) e Purchase (17). O Ponto está limpo.
4. **Nenhum UC do Ponto está fora de lane** (`uc-lane-coverage`: 69 ⛔ no repo, zero do Ponto) —
   os ✅ que este PR carimba não são "verde impossível".
