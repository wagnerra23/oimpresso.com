---
date: "2026-09-03"
topic: "Forja Onda 11 — revogação parcial de /project-mgmt (7 das 8 telas)"
authors: ["C"]
prs: [6617]
related_adrs: ["0367-cockpit-unico-forja-project-mgmt-morre", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0024-instalacao-1-clique-modulos"]
outcomes:
  - "7 telas revogadas com receptor medido; Roadmap/Index fica pela ADR 0367 D7"
  - "32 rotas project-mgmt.* → 5 (4 install obrigatórias + roadmap)"
  - "achado: a navegação da área tem 3 superfícies, e o §11 listava só uma"
  - "bug vivo consertado: o 301 descartaria o ?project= dos resultados do ⌘K"
---

# Onda 11 da Forja — revogação onde havia receptor, e o que ficou declarado

**PR:** [#6617](https://github.com/wagnerra23/oimpresso.com/pull/6617) · **Branch:** `claude/forja-onda-11-revogacao-9e15b8`

## O que o pedido dizia, e o que a medição achou

O pedido era explícito: *"Faça DEPOIS das Ondas 3–10 (só se revoga o que já tem substituto no ar)"*.
Medido em `origin/main` fresco: **as Ondas 3-10 não existem** — 0 commit, 0 PR aberto. Mergearam
0 ([#6543](https://github.com/wagnerra23/oimpresso.com/pull/6543)), 1 ([#6544](https://github.com/wagnerra23/oimpresso.com/pull/6544)),
2 ([#6553](https://github.com/wagnerra23/oimpresso.com/pull/6553)), 2.1 ([#6563](https://github.com/wagnerra23/oimpresso.com/pull/6563)).

Primeiro reportei o bloqueio e **devolvi um menu de opções ao [W]** — que é exatamente a LC-28
(*escalar decisão que era minha*). O [W] respondeu "Continue". A leitura certa era a do canon:
pedido com verbo de ação traz a autorização do escopo; o que era meu era **decidir o recorte**,
e o recorte se decide medindo. Fiz isso.

## O recorte, e por que ele não é o que eu tinha reportado

O primeiro relatório dizia que Triagem, Roadmap e Inbox estavam bloqueados. **Duas dessas três
estavam erradas**, e a ADR 0367 (que eu só tinha lido pelo índice) desfez:

- **D6** separa a TELA da ABA: *"morre a tela `/project-mgmt/triage`, fica a aba"*. O receptor já
  existia. `Triage/Index` podia sair — o que **não** pode sair é o `_components/ForjaTriage`, que
  é a aba.
- **D5** diz que as US do Inbox *"morrem junto, e isso é o custo aceito, não um esquecimento"*.
  O conflito que o §9 registrava tinha sido resolvido pela ratificação da ADR.
- **D7** sobreviveu à revisão: condiciona a saída do quarter view a *"o Gantt provar que
  substitui"*, e a Onda 6 não rodou. `Roadmap/Index` **fica**.

Saíram **7 das 8**. Ficaram `Roadmap/Index` (D7) e os 3 `_components` (o `Cockpit.tsx:19-21`
importa os três; `ForjaTriage` serve a landing `/forja` e o botão "Novo issue" — destrava na Onda 3).

## Os dois achados que o §11 não previa

**1. A navegação tem TRÊS superfícies, não uma.** O §11 listava "rotas, testes e SCOPE". Faltava
`Modules/Forja/Resources/menus/topnav.php` — a **viva** (`LegacyMenuAdapter` → `shell.topnavs.Forja`),
com 8 itens **todos** apontando pras telas revogadas. Sem tocá-la, as telas morriam e o menu seguia
oferecendo-as. A 3ª é o `DataController::modifyAdminMenu()`, onde um `return` **incondicional** na
linha 116 já tornava tudo abaixo código morto — inclusive os `route('project-mgmt.*')` que eu tinha
temido que quebrassem a sidebar. Quase cometi o inverso da [§5 2026-08-10](../proibicoes.md):
tratar **código morto como dependência viva**. Ler as linhas em volta desfez.

**2. Um bug vivo.** `SearchController` devolvia `url` de resultado do ⌘K apontando pra
`/project-mgmt/board?project=X`. O 301 teria **descartado o `?project=`**. Reapontei pros filtros que
o receptor de fato aceita (`TrabalhoController:91-106`): `q` e `cycle` existem, `project` **não**
(decisão [W]: a lista abre com todas as tasks). O resultado de Projeto vai pra lista inteira — perda
declarada, não silenciosa.

## O que os gates ensinaram

Rodei cada gate em **todos** os modos que o CI usa ([§5 2026-07-28](../proibicoes.md)), não em um só.
Dois morderam, e os dois estavam certos:

- **`anchor-lint`** (required): 7 US do SPEC ficaram `anchored_dead`. Reconciliadas pro único estado
  que a gramática do ADR 0273 admite sem path (`_pendente_ — <razão>`). Cobertura 52,6% → **89,5%**.
- **`deadlink-gate`**: aqui errei e corrigi no meio. Apaguei `CHARTER-board.md` e `RUNBOOK-index.md`
  (lei/receita de tela morta), e isso **cascateou 6 links mortos** em 4 docs que os citavam.
  Restaurei os dois **com tarja de revogado no topo**: o risco que eu queria matar (sessão futura
  obedecer lei morta) morre pela tarja, sem a cascata. E o charter ainda serve pra uma coisa — é
  onde está escrito **o que se perdeu** (atalhos `E`/`A`, overlay `?`, filtros cycle/epic/owner).

Duas coisas ficaram **fora** de propósito: os baselines de CSS (`.conformance`/`.fontramp`), cuja
regeneração pegou drift de arquivos que esta onda não tocou — absorver seria adotar dívida de
terceiros; e o corpo das ADR 0110/0367, append-only (os 3 links foram pro `deadlink-baseline` pelo
mecanismo do próprio gate, com o diff conferido: 792→795, só essas duas entradas).

## Erro de método que vale registrar

Li `governance/deadlink-baseline.json` no **nível errado** — contei as chaves do topo (3: `_doc`,
`files`, `total_vivo`) e conclui "só 3 entradas no baseline", quando `files` tinha **291**. Isso me
levou a hipotetizar que o gate já estava vermelho no `main`. A medição certa (28 success / 1 failure
nas runs) desfez. É LC-08 na forma mais barata: ler a estrutura no nível errado.

## Sessões paralelas

Ao consultar as runs do CI apareceram **outras branches de Forja ativas agora**:
`claude/forja-onda9-changelog-r2` e `claude/forja-1280-prod-medida`. Não rodei `whats-active` no
início — deveria ter ([§5 2026-08-13](../proibicoes.md)). A Onda 9 estar em voo **não** conflita com
esta (ela toca o Changelog; eu toco `/project-mgmt`), mas o `SCOPE.md` e o `PARIDADE.md` são
superfície comum e podem conflitar no merge.
