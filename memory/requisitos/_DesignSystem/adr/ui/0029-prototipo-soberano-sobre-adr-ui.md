---
id: requisitos-design-system-adr-ui-0029-protótipo-soberano-sobre-adr-ui
---

# ADR UI-0029 · Protótipo Cowork é SOBERANO sobre ADR UI — divergência é ADR errada, não decisão a debater

- **Status**: proposto
- **Data**: 2026-08-28
- **Decisores**: Wagner (decisão), Claude Code (executor/registro)
- **Categoria**: ui · governança · fundações
- **Emenda parcial**: [UI-0013](0013-constituicao-ui-v2-camadas.md) — a Constituição UI v2 define a hierarquia de CAMADAS
  (Fundações → Shell → Padrão de Tela → Módulo); esta ADR define a hierarquia de **FONTES** quando uma ADR UI e o
  protótipo discordam. Não altera as camadas.
- **Refs**: [ADR 0299](../../../../decisions/0299-figma-nao-e-fonte-de-design.md) (fonte de design = protótipo Cowork
  + DS + charter) · [ADR 0282](../../../../decisions/0282-protocolo-v2-colapso-ratificacao.md) (o Code GERA, não espera)
  · [ADR 0374](../../../../decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md) (espelho é leitura)

- **Generaliza**: [UI-0028](0028-sidebar-segue-o-prototipo-hue-295-supersede-parcial-0027.md) (accepted, 2026-08-28) — ela decidiu o **caso concreto** (os 8 `--sb-*` passam a ser os do protótipo, hue 295, supersede parcial da UI-0027, medindo 47 divergências com a mesma sonda nos dois lados). Esta ADR **não a repete e não a reabre**: eleva a mesma lógica a **regra geral** e resolve o que ela não tratava — o escopo (forma × visibilidade × dado) e a cadeia de precedência. Onde as duas falarem de token de sidebar, **a UI-0028 vence**: ela mediu, esta não.

> ⚠️ **Nota de processo (2026-08-28):** esta ADR nasceu numa sessão que **não rodou `whats-active`** e por isso redescobriu o eixo 295×240 que a UI-0028 já tinha decidido e landado no mesmo dia. É a classe LC-19 (autorar em paralelo a um tema com dono), no vetor em que ela **tem** saída mecânica — §5 2026-08-13. Registrado aqui porque o custo foi real: um número de ADR colidido e trabalho refeito.

## Contexto

Sessão 2026-08-28. Ao auditar paridade da sidebar contra `prototipo-ui/cowork/sidebar.jsx`, cada divergência
encontrada virou uma discussão sobre **qual artefato manda** — a ADR UI que decidiu aquilo, ou o protótipo.
O agente chegou a recomendar preservar uma decisão de ADR contra o que o protótipo mostrava.

[W] cortou, textual: *"ADRs UI que estiverem divergentes ao protótipo estão erradas"*, *"muito simples a regra"* e
— a razão, que é o que importa — *"se meu protótipo mudar eu vou querer que a tela mude também. por isso que estou
sendo categórico, paridade com protótipo."*

O pedido não é um conserto pontual: é um **invariante**. Protótipo muda ⇒ tela muda.

## Decisão

**Quando uma ADR UI diverge do protótipo Cowork, a ADR está errada.** O protótipo vence, e a ADR é emendada ou
superseded — não se "pondera", não se abre exceção per-tela, não se pede decisão nova a [W] pra cada caso.

### O ESCOPO da soberania — forma, não visibilidade

O protótipo manda na **FORMA**; o código manda em **QUEM VÊ** e no **DADO**. Sem essa linha a regra
se auto-destrói, porque o protótipo é declaradamente um mock de UM tenant:

| decide | quem manda | por quê (medido 2026-08-28) |
|---|---|---|
| **forma** — layout, hierarquia, espaçamento, cor, tipografia, ícone, rótulo, afordância, estado | **protótipo** | é o artefato que [W] autora; é a fonte de design ([ADR 0299](../../../../decisions/0299-figma-nao-e-fonte-de-design.md)) |
| **visibilidade** — permissão, pacote por business, módulo instalado | **código / ADR** | o protótipo simula papel com `podeVer(papel)` + `MOCK.SIDEBAR_PAPEIS` e renderiza um tenant fixo ("Oimpresso Matriz · Administrador"). As 3 camadas de habilitação por business são **Tier 0** (`proibicoes.md`, *"junto com business_id"*) |
| **dado** — contagem, valor, texto vindo do banco | **código** | os contadores do protótipo são mock — `SIDEBAR_COUNTS = { chat: 3, atendimento: 6, tarefas: 6 }`, e o `12` de Produtos é o **número de ghosts** daquele hub (`sidebar.jsx:178`), não um contador de dado; o vivo tem `shell.sidebar_counts` reais |

Corolário: uma ADR anterior que decide **forma** vira derivada e cai se divergir. Uma que decide
**visibilidade** ou **dado** não é tocada por esta regra — protótipo não revoga permissão nem tenancy.

Corolários que fecham as brechas que apareceram nesta sessão:

1. **Divergência é DEFEITO, não pauta.** O agente não devolve a [W] "qual dos dois vale?" — a regra já respondeu.
   O que ainda é de [W]: o conteúdo do protótipo (ele muda lá) e o merge (R10).
2. **A comparação é por PAPEL, nunca por classe CSS.** Duas correções nesta mesma sessão vieram de medir
   `sb-shortcuts` (classe) em vez do papel "bloco de atalhos no topo" — o protótipo o renderiza como `.sb-item`
   solto e o grep concluiu ausência. Ver §5 2026-08-18 (grep de string literal) e 2026-07-15 (achado sem varredura).
3. **ADR UI passa a ser DERIVADA.** Ela registra *por que* e *como* se implementou o que o protótipo mostra —
   deixa de ser fonte independente de verdade visual.
4. **Vale pra ADR UI (`adr/ui/`).** ADR de núcleo (`memory/decisions/`) que decide visual de tela entra aqui
   pela cláusula visual — o resto dela (contrato, dado, multi-tenant) não é afetado.

### Onde o protótipo entra na CADEIA DE PRECEDÊNCIA

`proibicoes.md` tem uma regra Tier 0 que resolve conflito entre artefatos de uma tela:

> **teste verde citando o UC > `.casos.md` > `.charter.md` > `SPEC.md`** — e conflito
> detectado = corrigir o PERDEDOR no MESMO PR.

O protótipo **não aparece nessa cadeia**, e essa omissão é o conflito real: hoje um teste
verde que fixa a forma ANTIGA ganha do protótipo, e o agente que "obedece a lei" reintroduz
o design velho com CI verde. Esta ADR fecha isso, **separando os dois eixos**:

| a discordância é sobre… | cadeia que vale |
|---|---|
| **FORMA** (layout, hierarquia, cor, tipo, ícone, rótulo, afordância, estado) | **protótipo** > teste > casos > charter > SPEC |
| **comportamento · visibilidade · dado** | a cadeia original, **intacta**: teste > casos > charter > SPEC (protótipo não entra) |

Consequência operacional, que é o ponto: quando o protótipo muda a forma e um teste/caso/
charter fixa a forma anterior, **o perdedor é o teste/caso/charter** — e ele se corrige no
MESMO PR, exatamente como a regra original já manda. O que NÃO muda: teste que prova
**comportamento** segue soberano; protótipo não revoga comportamento provado.

Sinal de que alguém aplicou errado: um PR que muda a forma pro protótipo e **desabilita** o
teste em vez de reescrevê-lo. Reescrever é o certo; desabilitar é fuga.

## Não-decidido aqui

- **Tier 0 permanece intocado.** Protótipo não revoga `business_id`, append-only legal, regra de valor/estoque
  nem LGPD. Divergência protótipo × Tier 0 é decisão [W], não aplicação automática desta regra.
- **Qual texto fica quando os dois são plausíveis** (ex.: topo do sidebar — protótipo diz "Visão geral", vivo diz
  "Forja") segue sendo [W]: a regra diz que o protótipo vence, mas se [W] quer "Forja", ele muda o protótipo.

## Residual honesto — esta regra NÃO é enforçável hoje

Registrar isto é parte da decisão; ADR que promete mecanismo inexistente é a classe LC-15 deste projeto.

Medido em 2026-08-28:

| perna | estado | por quê |
|---|---|---|
| protótipo renderiza fiel | ✅ (ver errata) | ~~faltam os `_ds/`~~ — **ERRATA 2026-08-28, mesma sessão:** os 3 arquivos + 7 fontes são repostos por `node scripts/governance/cowork-mirror-freshness.mjs --preview-ds` (portão fail-closed do protocolo), e já existem **versionados** em `scripts/design-sync/mirror-snapshot/` (11 arquivos no git). O `_ds/` do espelho é cópia de build, ignorada por `prototipo-ui/cowork/.gitignore:26` — por isso `git ls-files` devolve 0 e o `--sla` não os conta. Nunca faltaram: faltava rodar o painel. O resíduo REAL é outro: em clone limpo/CI é preciso rodar o `--preview-ds` antes de medir. |
| lado vivo renderizável sem humano | ❌ | a sidebar só existe logado; `staging.oimpresso.com` não respondeu, `oimpresso.com` exige sessão. |
| régua | ✅ | [`design-diff.mjs`](../../../../../prototipo-ui/design-diff.mjs) (`--probe`/`--compare --check`, D2/D4/D6/D8/D9) já existe e é o instrumento certo. |
| gatilho "protótipo mudou ⇒ confere a tela" | ❌ | `cowork-mirror-freshness` mede **frescor do espelho**, nunca **divergência da tela**. Ninguém deriva a segunda da primeira. |

Enquanto as 3 pernas ❌ não fecharem, esta regra vale **culturalmente** (o agente aplica; ver Consequências) e a
verificação é manual e parcial — só o eixo ESTRUTURAL (DOM), que é imune a CSS faltando. **Não** criar gate novo
antes disso: um comparador alimentado por protótipo sem DS produz divergência falsa e vira gate de teatro
(§5 2026-07-09 · ADR 0290 já matou o render-diff não-hermético).

## Consequências

- Toda ADR UI viva passa a ser auditável contra o protótipo. A primeira aplicação já produziu veredito:
  **UI-0011 não diverge** — o protótipo tem o bloco de atalhos no topo.

  ⚠️ **Retratação (refutação GT-G5 rodada 2):** uma versão anterior desta seção afirmava que a
  [ADR 0180](../../../../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) *"erra na cláusula de
  exclusão, proibindo ghost no sidebar"*. **Isso é falso e está retirado.** Medido: a 0180 **não tem
  cláusula de exclusão** (`grep -icE "fora de escopo|não-decidido|exclui"` → **0**); o que existe é o
  título de uma *justificativa* — §"Por que header ghosts **e não** sub-itens no sidebar" — e a própria
  `0180:120` põe ghost **dentro** do sidebar: *"Click direito em ghost → 'Fixar no sidebar'. Renderiza
  seção FIXADOS no topo."* Eu li um cabeçalho de justificativa como cláusula normativa e declarei uma
  ADR aceita "errada" a partir disso — a lápide §5 2026-07-15 (achado derivado de leitura, sem varredura
  contada), cometida dentro de uma ADR nova.

  O que **se sustenta** e fica: o protótipo renderiza ghost nos **dois** lugares — sidebar (`GhostList`
  + `.sb-ghost-count` em 23 itens do `MENU`, 27 com o `SUPERADMIN_MENU`, + "⋯ mais N") e header
  (`ph-nav`, por `app.jsx`). Isso **converge** com a 0180, não a contradiz.
- Fechar as 3 pernas ❌ acima vira pré-requisito de qualquer promoção a gate — e o `_ds/` é a primeira.
- **Regras anteriores aposentadas na mesma leva** (normalização de metadado, corpo intacto): [UI-0009](0009-cockpit-sidebar-light-padrao.md),
  [UI-0014](0014-sidebar-light-mantida-v2-parcial.md) e [UI-0019](0019-sidebar-light-definitivo-supersede-0009-0014.md)
  liam `Status: accepted` afirmando **sidebar light** — já declaradas históricas pela UI-0019 §Decisão-2 e pela
  UI-0023, mas com a linha `Status` nunca virada. Contradiziam o código (`Sidebar — DARK FIXO`) e o protótipo.
  PR precisa da label `adr-metadata-normalization` ([ADR 0257](../../../../decisions/0257-adr-status-lifecycle-kind-modelo-canonico.md)).

## Estado-alvo — paridade ENFORÇADA por máquina ([W] 2026-08-28)

[W], textual: *"quando for aplicar essas máquinas eu gostaria da paridade"*. O destino não é conferência manual:
é o gate que quebra quando a tela para de espelhar o protótipo. A ordem é obrigatória — cada passo é
pré-requisito do seguinte, e antecipar o último produz gate de teatro:

| # | passo | destrava |
|---|---|---|
| 1 | ~~descer o `_ds/`~~ → **rodar `--preview-ds` antes de qualquer medição** (e garantir que o CI o rode) | protótipo renderiza fiel ⇒ D4/D6 deixam de dar divergência falsa. Corrigido pela errata acima: o passo nunca foi de transporte, é de execução. |
| 2 | lado vivo renderizável sem humano (staging que responda, ou harness do shell) | comparação deixa de exigir sessão logada de [W] ⇒ pode rodar sozinha |
| 3 | acoplar `design-diff --compare --check` ao gatilho de sync do espelho | *"protótipo mudou ⇒ confere a tela"* vira mecânico, não lembrança |
| 4 | promover a required, com mordida provada | [ADR 0336](../../../../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) |

Enquanto 1-3 não fecharem, a paridade é aplicada pelo agente (esta ADR) e verificável só no eixo estrutural.
