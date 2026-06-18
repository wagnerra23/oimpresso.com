---
date: "2026-06-18"
topic: "Plano adversarial — memória de proveniência design→código (reuso máximo da catraca Contrato de Tela)"
hour: "15:00 BRT"
authors: [C]
related_adrs:
  - 0264-governanca-executavel-trio-de-tela
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0255-contrato-de-view-deterministico
  - 0256-knowledge-survival-catraca-sentinela-gate
  - 0283-loop-handoff-zero-paste
outcomes:
  - "Mapa peça→estrutura-existente (5 peças, todas ESTENDEM, nenhuma reinventa)"
  - "Plano sequenciado em 6 PRs pequenos com check mecânico por PR"
  - "Top 5 modos de falha + versão mínima-que-morde"
  - "Veredito GO-COM-CONDIÇÕES + 4 condições inegociáveis"
---

# Plano — memória de proveniência design→código (adversário-arquiteto)

> **Mandato:** formalizar a "memória de proveniência design→código" **reusando** a estrutura que já existe
> (catraca Contrato de Tela, âncoras `data-contract`, charter, trio-de-tela, gates-registry, schemas de memória,
> git como changelog), **sem inventar sistema novo**, e **atacar o próprio plano** antes de entregar.
> **Cético por default:** o `SYNC_LOG.md` manual provou que matriz à mão MORRE — este doc tem que provar que NÃO vira o próximo.

---

## 0. Inventário — o que JÁ existe (lido, não suposto)

A cadeia de proveniência **já está 70% construída**. Não falta sistema; falta **fechar 2 elos quebrados e ligar 1 gerador**.

| Peça da cadeia | Existe hoje? | Onde | Estado |
|---|---|---|---|
| **Fonte (design)** | Parcial | `prototipo-ui/prototipos/<tela>/*.jsx` (tracked) + `charter.visual_source:` (8 charters) | **Fonte fantasma**: `inbox-page.jsx` tem **7 cópias** (canon + bundle + 5× em `_BACKUP-NAO-USAR/`). O `visual_source` aponta 1; o `fonte` do contrato aponta prosa vaga ("prototipo-ui (Cowork...)") **não-checável por máquina** |
| **contract.json** | Sim | `prototipo-ui/contrato/*.contract.json` + `contract.schema.json` (já tem campo `fonte`) | 1 ativo (`caixa-unificada`), schema bom, advisory |
| **âncora `data-contract`** | Sim | 3 `.tsx` da Caixa (`reconnect-cta`, `reconnect-modal`, `guia*`...) | Funciona; ponte real protótipo↔código |
| **commit `Refs:`** | Sim | `commit-discipline` Tier A obriga `Refs:` | Mas **`Refs: contract:<tela>` = 0 usos**. Elo commit↔contrato NÃO existe |
| **PR body (claim-evidence)** | Sim | `infra-contract-required.yml` + `handoff-scope-guard.yml` + `INFRA-CONTRACT.md` + `<!-- design-deviation -->` | Padrão maduro Default-FAIL + escape declarado. **A reusar, não duplicar** |
| **decisão (charter)** | Sim | `*.charter.md` → `## Decisões & reclamações` (append-only) + `## Contrato visual` | Já é o "por quê" humano, lido via `charter-first` Tier A |
| **gerador do mapa** | **NÃO** | — | `--map` não existe em `contrato-de-tela.mjs`. **Único componente genuinamente novo** (e mesmo assim é um 4º modo de um script que já existe) |
| **gates-registry** | Sim | `scripts/governance/gates-registry.json` + `memory-health` Check G | Workflow novo TEM que se registrar senão `memory-health` falha (dente real, umbrella, todo PR) |
| **changelog imutável** | Sim | git (conventional + corpo PR + `Refs:`) | Já é a fonte append-only |

**Conclusão do inventário:** a "memória que vale ouro" é **derivável** de artefatos que já existem (`*.contract.json.fonte` + `data-contract` no `.tsx` + `git log`). O que mata o `SYNC_LOG` não é mais documento — é **parar de manter à mão e gerar do que a máquina já sabe**. O plano abaixo é majoritariamente **deleção + 1 gerador + 2 elos**, não construção.

---

## 1. Mapa peça→estrutura-existente (a regra: ESTENDE, não cria)

| # | Peça pedida | ESTENDE o quê (não reinventa) | Check MECÂNICO que enforça (senão drifta) | Veredito |
|---|---|---|---|---|
| **1** | **Cadeia + 5 réguas no RUNBOOK** | `RUNBOOK-contrato-de-tela.md` (já existe) — vira seção `## Cadeia de proveniência` + `## Réguas` | Nenhum check novo: as réguas são **descrições das catracas que já mordem** (régua 2 ⇒ schema `required:[fonte]`; régua 3 ⇒ `contrato-de-tela.mjs`; régua 4 ⇒ charter append-only). Régua sem catraca = prosa, **não entra** | **Doc-only. Estende.** |
| **2** | **Gerador `--map`** | 4º modo de `scripts/contrato-de-tela.mjs` (Node puro, já tem `--root`, self-test, registry) — lê `*.contract.json` + `git log` → tabela 🟢/🔴/âncora-faltando + `fonte` + último PR | `--map --check`: FALHA se um `*.contract.json` tem `fonte` apontando arquivo **inexistente** ou se uma seção tem âncora-faltando E não há `<!-- design-deviation -->`. Roda no `contrato-de-tela.yml` que já existe | **Novo modo, script existente. Estende.** |
| **3** | **Convenção `Refs: contract:<tela>`** | `commit-discipline` Tier A (já obriga `Refs:`) + skill (description) | Lint barato no `--map`: contrato cujo `alvo` foi tocado no PR mas **nenhum commit** cita `Refs: contract:<tela>` = WARN (advisory) → fecha o elo commit↔contrato pro `--map` saber "último PR que tocou" | **Convenção, regra existente. Estende.** |
| **4** | **6ª régua — esteira ≠ armazém + `bundle-lint`** | `score-mechanized`/`ds-guard` são `.mjs` Node puros no mesmo molde → `bundle-lint.mjs` flagra resíduo (`Adversário/Auditoria/Avaliacao/GAPS_v*/PROMPT_*/FORCE_*/_arquivo`) que voltou no bundle | Workflow `bundle-lint.yml` (advisory) **+ registro no gates-registry** (senão `memory-health` Check G falha). Lista os 3 baldes: DESIGN-MANTÉM / INGERE→memory→APAGA / APAGA-resíduo | **Novo lint, molde existente. Estende.** ⚠️ ver ataque #2 |
| **5** | **Ingestão dos planos de tela** | `memory/sessions/*.md` (schema `session.schema.json`: `date`+`topic`) OU `memory/requisitos/<Mod>/` — padrão de memória que já existe | `memory-schema-gate` (já existe) valida o frontmatter na ingestão. Proveniência = campo `fonte:`/link pro bundle. Depois: lista "seguro apagar do bundle" pro `bundle-lint` | **Ação humana + schema existente. Estende.** ⚠️ ver ataque #4 |

**Nenhuma peça cria sistema novo.** Peça 2 e 4 adicionam código, mas no molde exato (`.mjs` Node puro, self-test, registry, advisory→required) das catracas que já passaram pelos 2 adversários em 2026-06-18.

---

## 2. Plano sequenciado (6 PRs · 1 PR = 1 intent · cada um com critério mecânico)

Ordem fixa. **PR-0 é pré-requisito inegociável** (sem fonte-única, todo o resto roda sobre vazio = teatro — veredito dos adversários no RUNBOOK §3).

### PR-0 — Consertar a fonte-da-verdade (DESBLOQUEADOR, sem dependência)
- **Entrega:** escolher a versão canônica de cada protótipo de tela ativo, garantir 1 cópia versionada em `prototipo-ui/prototipos/<tela>/`, **apagar as 5 cópias órfãs** de `_BACKUP-NAO-USAR/` (ou movê-las p/ fora do git). Atualizar `contract.json.fonte` da Caixa de prosa vaga (`"prototipo-ui (Cowork...)"`) pra **caminho exato** (`prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx`).
- **Estende:** `charter.visual_source:` (já aponta o path certo — alinhar o `fonte` do contrato a ele).
- **Check mecânico:** o `--map --check` do PR-2 vai FALHAR se `fonte` ≠ arquivo existente. Antes dele: `git ls-files | grep -c inbox-page.jsx` deve cair de 7 → 1-2 (canon + bundle-fonte). **Critério binário.**
- **Esforço:** ~1-2h. **Dependência:** nenhuma.
- ⚠️ **Honestidade:** "qual é a versão canônica" é **julgamento humano** (Wagner/design). A máquina só trava DEPOIS de declarada.

### PR-1 — RUNBOOK: seção Cadeia + 5 réguas (doc-only)
- **Entrega:** `## Cadeia de proveniência` (`fonte → contract.json → data-contract → commit Refs → PR → charter`) + `## Réguas` (1-fato-1-lugar · proveniência-obrigatória · máquina>prosa · append-only · derivado-não-mantido) **no RUNBOOK que já existe**. Cada régua cita a catraca que a morde (régua sem catraca = não entra).
- **Estende:** `RUNBOOK-contrato-de-tela.md`.
- **Check mecânico:** `memory-schema-gate` (RUNBOOK tem seções obrigatórias). **Não inventa gate** — se a régua não mapeia pra catraca existente, ela é cortada na review.
- **Esforço:** ~1h. **Dependência:** PR-0 (pra citar a fonte certa).

### PR-2 — `--map` (o gerador que mata o SYNC_LOG)
- **Entrega:** 4º modo em `contrato-de-tela.mjs`: `--map` (gera tabela markdown protótipo→prod: 🟢 portado / 🔴 só-protótipo / ⚠️ âncora-faltando + `fonte` + último PR via `git log -1 --format` no `alvo`) e `--map --check` (FALHA em `fonte` inexistente OU âncora-faltando sem `<!-- design-deviation -->`). Self-test ganha 2 casos (mapa bom / mapa com fonte quebrada). **Output do `--map` NÃO é commitado** (derivado-não-mantido — régua 5): gera on-demand no CI/local. Se quiser artefato visível, escreve em `prototipo-ui/contrato/MAPA.generated.md` com header "GERADO — não edite" e um check que falha se editado à mão.
- **Estende:** `scripts/contrato-de-tela.mjs` + `contrato-de-tela.yml` (já registrado).
- **Check mecânico:** o próprio self-test (job `selftest` HARD do workflow) + `--map --check` no job `contratos`. **Quem vigia: o gate-selftest que já existe.**
- **Esforço:** ~3-4h. **Dependência:** PR-0 (fonte real pra apontar).

### PR-3 — Convenção `Refs: contract:<tela>` + lint advisory
- **Entrega:** documentar a convenção na skill `commit-discipline` (description) + RUNBOOK; `--map` passa a procurar `Refs: contract:<tela>` nos commits da branch pra resolver "último PR que tocou" com precisão (sem isso, cai no heurístico `git log` do alvo — funciona, só menos preciso).
- **Estende:** `commit-discipline` Tier A (regra existente).
- **Check mecânico:** WARN advisory no `--map` (contrato com `alvo` tocado mas sem `Refs: contract:`). **NÃO promover a required** (ver ataque #3 — vira ruído).
- **Esforço:** ~1h. **Dependência:** PR-2.

### PR-4 — `bundle-lint` (esteira ≠ armazém)
- **Entrega:** `scripts/bundle-lint.mjs` (Node puro, self-test, molde `ds-guard`) que flagra no bundle do design: resíduo adversarial (`Adversário*`/`Auditoria*`/`Avaliacao*`/`GAPS_v*`/`PROMPT_*`/`FORCE_*`/`_arquivo`) + arquivos já ingeridos em `memory/` (lista `ingeridos.json`). `bundle-lint.yml` advisory **+ entrada no gates-registry** (senão `memory-health` Check G morde). 3 baldes documentados no RUNBOOK.
- **Estende:** molde `score-mechanized.mjs`/`ds-guard.mjs` + `gates-registry.json`.
- **Check mecânico:** self-test do próprio lint + `memory-health` Check G (registro obrigatório). ⚠️ **só morde se o bundle estiver no git** — ver ataque #2.
- **Esforço:** ~2-3h. **Dependência:** nenhuma técnica; PR-5 alimenta a lista `ingeridos`.

### PR-5 — Ingerir os planos de tela + lista "seguro apagar"
- **Entrega:** ingerir os planos duráveis do bundle (`AUDITORIA_MODULOS.md`, `Integração Vendas × Oficina - Storyboard.html`, `Método KB-9.75.html`, etc. — os que são conhecimento, não prompt descartável) pra `memory/requisitos/<Mod>/` ou `memory/sessions/` no padrão do projeto, com `fonte:` apontando o bundle. Produzir `prototipo-ui/contrato/ingeridos.json` (alimenta `bundle-lint`) + lista "seguro apagar do bundle".
- **Estende:** `memory/sessions/` + `session.schema.json` (já existe).
- **Check mecânico:** `memory-schema-gate` valida o frontmatter; `bundle-lint` (PR-4) passa a flagrar o ingerido se ele voltar no bundle.
- **Esforço:** ~2-3h. **Dependência:** PR-4 (consome `ingeridos.json`). ⚠️ **a APAGAÇÃO do Cowork é ação do dono** — ver ataque #4.

**Sequência de dependência:** PR-0 → {PR-1, PR-2} → PR-3 ; PR-4 → PR-5 (paralelo ao tronco). **Caminho crítico = PR-0 → PR-2** (o resto pendura).

---

## 3. ATAQUE ao plano — Top 5 modos de falha (rankeados)

### #1 (MAIS PROVÁVEL) — `--map` vira o novo SYNC_LOG morto
**Como falha:** se o `--map` gerar um arquivo `MAPA.generated.md` **commitado**, ele drifta na primeira vez que alguém edita o `.tsx` sem regenerar — exatamente a morte do SYNC_LOG, só que com mais YAML. "Derivado mantido à mão" é o anti-padrão que o projeto já viveu.
**Defesa:** régua 5 (derivado-não-mantido) é **inegociável**: o `--map` é **comando**, não arquivo. Roda on-demand (CI/local). Se um artefato visível for exigido, ele nasce com header "GERADO" + um check que **falha se o hash do conteúdo ≠ o que `--map` produziria** (o arquivo não pode divergir do gerador, por construção). **Sem `--check` que prenda o derivado ao gerador, NÃO commitar o mapa.**

### #2 — `bundle-lint` não morde porque o bundle não está no git (ou está, e é 4.7M de ruído)
**Como falha:** o bundle `cowork-2026-05-26-comunicacao-visual/` (4.7M, 68 .md, 65 .jsx) — se NÃO for trackeado, `bundle-lint` no CI não vê nada (verde vazio = teatro). Se FOR trackeado, vira 4.7M de peso no repo que o lint "aprova" linha a linha sem ninguém olhar.
**Defesa:** `bundle-lint` tem que rodar sobre **o que está no git** e o gate só vale se o bundle estiver versionado num path declarado (`prototipo-ui/cowork-*/`). **Decisão de fronteira (humana):** o bundle inteiro NÃO deveria estar no git — só `fonte-viva` (os `.jsx`/`.css` que `contract.json.fonte` aponta) + `handoff-ativo`. **Versão mínima honesta:** `bundle-lint` flagra resíduo **só nos paths fonte-viva declarados**; o resto do bundle sai do git (PR-0/PR-5). Um lint sobre 4.7M de armazém é o teatro que o mandato quer evitar.

### #3 — `Refs: contract:<tela>` promovido a required = ruído que ninguém adota
**Como falha:** o repo tem 60+ gates, ≥6 advisory ignorados. Forçar `Refs: contract:` como required quebra todo PR de design que esqueceu o sufixo — vira fricção, o time pede `--admin`, e aí o gate **não é required de verdade** (condição 2 do RUNBOOK). Convenção de commit-message é **frágil** (humano esquece) e **cara de enforçar** (precisa parsear intenção).
**Defesa:** **NUNCA required.** É WARN advisory que só melhora a precisão do "último PR" no `--map`. O `--map` funciona SEM ela (heurístico `git log` no alvo). É um *nice-to-have* que não pode virar bloqueio. Se virar ruído, **corta-se a peça 3 inteira** — o `--map` sobrevive sem ela.

### #4 — A ingestão (PR-5) trava porque "o que é durável" e "apagar do Cowork" são julgamento humano
**Como falha:** decidir quais dos 68 .md são conhecimento durável vs prompt descartável é **não-automatizável** (julgamento). E **apagar do Cowork é ação do dono** fora do git (o bundle é export de uma ferramenta externa). Se o plano fingir que a máquina faz isso, PR-5 fica eternamente "pendente" — o destino do SYNC_LOG.
**Defesa:** separar o **mecânico** do **humano** explicitamente. Mecânico: `bundle-lint` flagra padrões de resíduo + arquivos já-ingeridos (lista). Humano: Wagner decide o que ingerir e **executa a deleção no Cowork**. O plano entrega a **lista "seguro apagar"** como output, não a deleção. **Honestidade no doc:** PR-5 é semi-manual por natureza; sucesso = "lista gerada + ingeridos com proveniência", não "bundle limpo".

### #5 — O "por quê" continua dependendo de alguém escrever no charter
**Como falha:** a régua "proveniência obrigatória" cobre **de onde veio** (fonte) e **como foi feito** (commit/PR) mecanicamente, mas **por que foi decidido assim** vive no `## Decisões & reclamações` do charter — que é **prosa humana append-only**. Se ninguém escrever, o "ouro" (o porquê) some, e nenhum gate pega isso (não dá pra mecanizar "faltou registrar uma decisão que talvez nem tenha existido").
**Defesa:** aceitar o limite. O "por quê" é **irredutivelmente humano** — o máximo mecânico é o `charter-first` Tier A (obriga LER o charter antes de editar) + `<!-- design-deviation -->` (obriga DECLARAR desvio intencional no PR). Isso captura o porquê **no momento do desvio** (barato, no fluxo). O porquê "estratégico" amplo continua dependendo de disciplina — e o plano **não promete** mecanizar o que não dá.

---

## 4. Versão MÍNIMA-QUE-MORDE (se só desse pra fazer 1 coisa)

**PR-0 + PR-2 (`--map --check`), e nada mais.**

- **PR-0** conserta a fonte fantasma (7 cópias → 1). Sem isso tudo é teatro.
- **PR-2** dá o `--map --check` que FALHA quando `contract.json.fonte` aponta arquivo inexistente. **Esse é o dente:** ele transforma "proveniência" de prosa em invariante checada — a fonte do contrato TEM que existir e ser a declarada. Roda no `contrato-de-tela.yml` que já existe e já tem self-test HARD.

Isso entrega 80% do valor ("de qual pedaço do protótipo veio + a fonte não pode mentir") com 2 PRs e **zero gate novo** (reusa o workflow existente). PR-1/3/4/5 são camadas de conforto e higiene — valiosas, mas o **núcleo que morde** são esses dois. Tudo que for além disso só entra se passar no teste "isto morde e é barato?".

---

## 5. Veredito

### GO-COM-CONDIÇÕES

O plano é **majoritariamente reuso e deleção**, não construção — o que é o sinal certo de que NÃO vira shelfware (não há sistema novo pra abandonar; há catracas existentes a estender). A cadeia de proveniência já está 70% pronta; o gap real é **fonte-única (PR-0)** + **um modo de script (PR-2)**. As outras 3 peças têm valor mas carregam os modos de falha #2/#3/#4 — são "GO" só sob condição.

### Condições inegociáveis (sem elas é teatro)

1. **PR-0 ANTES de tudo.** Gate sobre fonte fantasma (7 cópias) = teatro garantido. A versão canônica é decisão humana; a máquina só trava depois. **Sem PR-0, NO-GO no resto.**
2. **O mapa é COMANDO, não ARQUIVO** (régua 5). Se commitar `MAPA.generated.md`, ele só existe com um check que o prende ao gerador (hash). Senão, é o SYNC_LOG reencarnado. **Não-negociável.**
3. **`Refs: contract:` e `bundle-lint` ficam ADVISORY.** O repo já tem ≥6 advisory ignorados e mergeou vermelho 2×/24h. Promover qualquer um desses a required sem 1-2 semanas de falso-positivo=0 medido = quebrar PR de design por fricção → time pede `--admin` → gate fake. **Required só o `--map --check` (e só quando a fonte-única estabilizar).**
4. **O bundle de 4.7M sai do git; só fonte-viva fica.** `bundle-lint` sobre armazém é o teatro que o mandato combate. Lint só vale nos paths `fonte` declarados pelos contratos.

### O que o plano HONESTAMENTE não resolve
- O **"por quê" estratégico** continua dependendo de humano escrever no charter (modo #5). Mecanizável só o desvio pontual (`design-deviation`), não a decisão ampla.
- A **escolha da versão canônica** (PR-0) e o **que ingerir** (PR-5) são julgamento — a máquina trava depois da declaração, não decide por ela.
- **Apagar do Cowork** é ação do dono fora do git. O plano entrega a *lista* "seguro apagar", não a faxina.

---

## Refs
- `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` — a catraca que tudo estende (validada por 2 adversários 2026-06-18)
- `scripts/contrato-de-tela.mjs` (+ `.test.mjs`) — onde entra `--map` (4º modo)
- `prototipo-ui/contrato/contract.schema.json` — já tem campo `fonte` (proveniência parcial)
- `prototipo-ui/contrato/caixa-unificada.contract.json` — 1º contrato ativo (piloto)
- `scripts/governance/gates-registry.json` + `memory-health` Check G — dente que obriga registro de gate novo
- `.github/workflows/{infra-contract-required,handoff-scope-guard}.yml` + `INFRA-CONTRACT.md` — padrão claim-evidence a REUSAR (omissão/desvio), não duplicar
- `memory/sessions/2026-06-18-arte-ponte-design-producao.md` — decisão DS-como-contrato (o caminho que este plano operacionaliza)
- `scripts/memory-schemas/session.schema.json` — schema de ingestão (PR-5)
- **Evidência da morte do manual:** `prototipo-ui/SYNC_LOG.md` (stale 2026-05-09) + `prototipo-ui/cowork-*/project/SYNC_LOG.md` (vazio "aguardando primeira sync") — DUAS matrizes mortas
