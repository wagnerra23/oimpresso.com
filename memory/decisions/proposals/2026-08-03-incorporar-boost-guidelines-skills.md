---
tipo: proposta-incorporacao
status: recusado
proposto_por: [C]
proposto_em: "2026-08-03"
decide: [W]
rejected_at: "2026-08-03"
rejected_via: "[W] 2026-08-03 no chat: 'não instalar' — e, em seguida, o motivo de fundo: 'não confio nessa que temos, ela é manualmente a descoberta do contexto' + 'gostaria que não fosse prosa, e sim descoberta por maquina'"
rejected_reason: "A Fase 0 mediu que a cobertura versionada do Boost não alcança Laravel 13 nem Inertia React 3 (sem fallback no GuidelineComposer), sobrando ~6 linhas de ganho contra manutenção perpétua de 2 overrides que contornam 3 conflitos duros com a lei local. E o Boost NÃO resolve o problema de fundo que [W] nomeou: as guidelines dele são 'curated by Laravel maintainers' — também prosa humana, só mantida por outra equipe. REABRE só se o pacote publicar laravel/13 + inertia-react/3 E alguém re-rodar a tabela de conflito. O tema 'contexto derivado por máquina em vez de prosa' NÃO passa por esta recusa — é outro trabalho."
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
---

# Proposta — incorporar as guidelines e skills do `laravel/boost` (com a Fase 0 já executada)

> **Origem:** grade comparativa oimpresso × Laravel nativo (2026-08-03). Duas dimensões nossas
> ficaram baixas — **contexto de framework versionado (3/10)** e **frescor/manutenção (4/10)** —
> e a causa medida não é arquitetura: é **capacidade comprada e não colhida**. O `laravel/boost`
> está no `composer.json` como `require-dev` desde o upgrade da stack IA, o MCP dele está
> registrado no `.mcp.json`, mas `.ai/guidelines` e `.ai/skills` têm **0 arquivos**.
>
> Esta proposta traz a **Fase 0 já executada** (ler antes de instalar). O resultado dela **mudou
> o plano** — ver §3.

## 1. O que está em jogo

O Boost entrega quatro camadas: **guidelines** (carregadas upfront, versionadas por pacote),
**skills** (sob demanda), **MCP tools** e a **Documentation API** (17k trechos com embeddings,
filtrada pela versão instalada). Colhemos só as MCP tools.

## 2. Fase 0 — o que foi medido (não lido na doc)

Medido em `vendor/laravel/boost` v2.4.13 (= última no Packagist, 2026-07-17).

### 2.1 O que existe de fato no pacote

| Item | Doc 13.x afirma | **Medido no disco** |
|---|---:|---:|
| Guidelines `.blade.php` | "16+ pacotes" | **41 arquivos** em 23 dirs + `foundation` + `enforce-tests` |
| Skills (`SKILL.md`) | **12** | **4** (`laravel-best-practices`, `pennant-development`, `tailwindcss-development` ×2) |
| Colisão com nossas 74 skills | — | **0** (os 3 nomes estão livres) |
| Instalação seletiva | — | ✅ `--guidelines`, `--skills`, `--mcp`, `--list-tests` |

A documentação **oversell as skills**: são 12 no site, 4 no pacote instalado. O plano abaixo
trabalha com 4.

### 2.2 ⚠️ A cobertura versionada NÃO alcança as nossas duas versões principais

Este é o achado que mais muda a decisão. O `GuidelineComposer` monta o caminho como
`.ai/<pacote>/<major>` (`GuidelineComposer.php:196`) e `guidelinesDir()` devolve vazio quando o
diretório não existe — **não há fallback para a versão anterior**.

| Pacote | Nossa versão | Dirs no pacote | Resultado |
|---|---:|---|---|
| `laravel/framework` | **13** | `11`, `12` | ❌ **sem guideline versionada** |
| `@inertiajs/react` | **3** | `1`, `2` | ❌ **sem skill versionada** (perde-se o arquivo de 371 linhas) |
| `pestphp/pest` | **4** | `3`, `4` | ✅ casa |
| `php` | **8.4** | `8.2`…`8.5` | ✅ casa |
| `tailwindcss` | **4** | `3`, `4` | ✅ casa (skill) |

Ou seja: a propaganda "version-aware" **é verdadeira, mas hoje não nos serve nas duas maiores**.
O que sobra de versionado para nós é Pest 4, PHP 8.4 e Tailwind 4 — útil, porém menor do que a
nota 9/10 que dei ao Laravel nessa dimensão sugeria. **Corrijo aqui: para o nosso caso concreto,
o valor da d1 é bem menor.**

Perda concreta e nominal: o `laravel/12/core.blade.php` tem um ramo que detecta
`app/Http/Kernel.php` e instrui *"este projeto veio do Laravel 10 e não migrou para a estrutura
nova — isso é perfeitamente aceitável, siga a estrutura existente"*. **Nós temos exatamente esse
arquivo** (herança UltimatePOS). Seria a guideline mais útil do lote — e é justamente a que não
instala.

### 2.3 Correção de um alerta meu anterior

Eu havia dito duas vezes que `boost:install` podia **sobrescrever** o `CLAUDE.md`. **Está errado.**
Lido em `GuidelineWriter.php:55-77`: o conteúdo é envolvido em
`<laravel-boost-guidelines>…</laravel-boost-guidelines>`; se o bloco já existe ele é substituído
**no lugar**, e se não existe o texto é **anexado ao fim** com separador `===`. Conteúdo humano é
preservado.

### 2.4 ⚠️ Mas existe um risco maior, e é específico nosso

`CLAUDE.md` está em `CURRENT_STATE_DOCS` (`memory-health.mjs:253` e `:295`) — o corpus do
**fact-anchor Check T, que é required**. Anexar 41 guidelines cheias de "Laravel 12", "Pest 3",
"Inertia v1" dentro do `CLAUDE.md` joga esse texto na frente de um gate que **derruba o PR**
quando um fato contradiz o `composer.json`. Os mesmos arquivos também entrariam nos checks de
link quebrado e de "ADR morta citada como canon".

Nada disso é defeito do Boost — ele não tem como saber. É consequência de termos um gate de
fatos sobre o `CLAUDE.md`.

## 3. Tabela de conflito — guideline × nossa lei

O gate desta Fase 0. 🔴 = a guideline manda fazer o que a nossa lei proíbe.

| Sev | Guideline | O que ela instrui | Nossa lei | Efeito se instalar cru |
|:--:|---|---|---|---|
| 🔴 | `enforce-tests` | *"run the affected tests… `php artisan test --compact`"* | testes **só no CT 100** ([ADR 0062](../0062-separacao-runtime-hostinger-ct100.md)) | manda rodar o comando que o hook `block-test-fora-ct100.mjs` **bloqueia** |
| 🔴 | `pest/core` + `pest/4` | repete *"Run tests: `php artisan test`"* | idem | mesma instrução, em mais 2 arquivos |
| 🔴 | `foundation` | *"You must only create documentation files if explicitly requested by the user"* | REGRA PRIMÁRIA **"mexeu, REGISTRA"** — session log, handoff, BRIEFING e ADR são **obrigatórios** | **inverte** a lei mais cobrada do projeto |
| 🟠 | `boost/core` | *"**Always** use `search-docs` before making code changes. **Do not skip this step**"* | `brief-first` Tier A: `brief-fetch` antes de qualquer outra tool | dois "sempre primeiro" competindo |
| 🟠 | `boost/core` | *"To check environment variables, read the `.env` file directly"* | `memory-first-secret-search` (índice antes) + proibição de segredo em claro | rota alternativa ao índice de segredos |
| 🟠 | `boost/core` | *"Use `database-query` to run read-only queries"* | Hostinger ≠ CT 100 | não diz **qual** banco |
| 🟠 | `laravel/core` | *"Use `make:` commands to create new files"* | `criar-tela.mjs` carimba o trio; RUNBOOK-criar-modulo tem 8 peças | agente cria tela fora do gerador |
| ✅ | `boost/core` | artisan como oráculo (`route:list`, `config:show`, `list`) | é a nossa doutrina anti-LC-08 | **reforça** o que já pregamos |
| ✅ | `foundation` | *"não criar script de verificação quando o teste cobre"* · *"não trocar dependência sem aprovação"* · *"conferir arquivos irmãos"* | alinhado | ganho limpo |
| ✅ | `pest/*` | *"Do NOT delete tests without approval"* | alinhado | ganho limpo |
| ✅ | `php/8.4` | `array_find`, `array_any`, `array_all`, chain sem parênteses | neutro | ganho puro |
| ✅ | `inertia-laravel/core` | deferred props + skeleton animado | é a nossa regra `Inertia::defer` default | **reforça** |
| ⚪ | `laravel/12` | *"veio do L10, tem `Http/Kernel.php`, está certo, não migre"* | seria o melhor item do lote | **não instala** (somos 13) |

**Veredito da Fase 0:** há ganho real (as 6 linhas ✅), mas **três conflitos duros**, e os três
saem de arquivos que entram por padrão. Instalar o lote cru colocaria no contexto do agente
instruções que a lei local proíbe — o oposto do que a incorporação quer.

## 4. Plano revisado

### Fase 1 — redirecionar o alvo (config, ~10 linhas)
`vendor:publish --tag=boost-config` e apontar
`boost.agents.claude_code.guidelines_path` → **`.ai/BOOST-GUIDELINES.md`**.
Fora do `CLAUDE.md` (§2.4) e **fora de `.ai/guidelines/`**, que é o diretório de *entrada* das
nossas guidelines customizadas — escrever a saída lá criaria realimentação.
**Gate:** `memory-health` com `fails: 0` antes e depois.

### Fase 2 — colher as guidelines, **menos as 3 duras**
`php artisan boost:install --guidelines`, **recusando `enforce-tests`**, e com os dois trechos
duros restantes (`foundation` "documentation files" e o `pest/*` "run tests") **sobrescritos** pelo
mecanismo oficial de override: um arquivo nosso em `.ai/guidelines/<mesmo-path>` substitui o do
Boost. Não é gambiarra — é a extensão documentada.
No `CLAUDE.md`, **um ponteiro** para o arquivo e a precedência explícita:
`proibicoes.md` **>** guidelines do Boost. Ponteiro, nunca cópia.

### Fase 3 — colher as skills (colisão medida = 0)
`--skills`, deixando cair em `.claude/skills` (é onde o Claude Code lê).
**Desmarcar `pennant-development`**: é skill de pacote que não usamos e que o §5 já mandou não
adotar — instalar seria ruído puro de contexto.
**Gate:** `skills-index-generate.mjs --write` regenerado, tier declarado.

### Fase 4 — frescor (d9)
`boost:update --discover` periódico. **Não prescrevo o `post-update-cmd`**: o Boost é
`require-dev` e o deploy roda `composer install --no-dev`; se o hook disparasse lá, o comando não
existiria. **Não medi** se aquele caminho executa `post-update-cmd`. Caminho conservador: alvo
local explícito **ou** cron de governança no CT 100.

### Fase 5 — autoria (d6) — a única que não é "colher"
Traduzir **um** item do Spec Kit: o marcador `[NEEDS CLARIFICATION]` como campo do
`charter.md` que só [W] preenche. A premissa já é lei nossa (*"pedido vago = agente pergunta"*) e
hoje não tem forma escrita. **Não** trazer os artefatos `.specify/specs/**` — seriam paralelos ao
trio charter/casos/teste, o que o §5 proíbe.

## 5. Decisões que são de [W]

| # | Decisão | Recomendação |
|---|---|---|
| D1 | Instalar guidelines? | **Sim, parcial** — as ✅ pagam; as 🔴 saem |
| D2 | Onde escrever | `.ai/BOOST-GUIDELINES.md`, fora do `CLAUDE.md` |
| D3 | `enforce-tests` | **Recusar.** Contradiz [ADR 0062](../0062-separacao-runtime-hostinger-ct100.md) e um hook ativo |
| D4 | `pennant-development` | **Fora** — pacote não usado |
| D5 | Frescor | Decidir depois de medir o deploy |

## 6. O que não entra

Spec Kit como pacote · Pennant · Nightwatch · Dusk · `boost:install` sem flags (escreveria nos
três alvos, incluindo o `.mcp.json`, que é nosso e comentado).

## 7. Ressalvas

- **Nenhum comando do Boost foi executado.** Tudo acima é leitura de `vendor/` e medição do repo.
- **`boost:install` não tem `--dry-run`** entre as opções medidas — a reversibilidade vem do git,
  por isso cada fase é um PR próprio.
- **A Fase 0 pode ter refutado a própria proposta.** Com Laravel 13 e Inertia 3 fora da cobertura
  versionada, o lote que sobra é menor do que a grade sugeria. Se [W] achar que 6 linhas de ganho
  não pagam a manutenção de 2 overrides, **não instalar é resposta legítima** — e a Fase 0 terá
  valido por ter evitado o trabalho.

## 8. Decisão — RECUSADA ([W], 2026-08-03)

**"não instalar".** A Fase 0 refutou a proposta, como ela mesma previa: o lote versionado não
alcança as nossas duas versões principais, e o que sobra não paga a manutenção dos overrides.

### 8.1 O motivo de fundo que [W] levantou — e que é maior que esta proposta

> *"não confio nessa que temos, ela é manualmente a descoberta do contexto"*
> *"gostaria que não fosse prosa, e sim descoberta por maquina"*

A objeção **procede** e é de outra natureza: não é "a nota está baixa", é que a nossa camada de
contexto é **prosa mantida à mão**, e prosa apodrece ([ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md)).

Mas ela **reforça** a recusa em vez de reverter: o `foundation.blade.php` do Boost afirma
literalmente que as guidelines são *"specifically curated by Laravel maintainers"* — **também são
prosa humana**, só mantida por outra equipe e redistribuída via `boost:update`. Adotá-las compraria
**frescor terceirizado**, não **derivação**.

A única parte de fato derivada é a lista de pacotes que o `foundation` renderiza em runtime via
Roster (`app(Roster::class)->packages()`) — e nesse eixo específico nós já temos algo **mais forte**:
o `fact-anchor` (Check T, **required**) não só declara a versão como **derruba o PR** quando o doc
contradiz o `composer.json`.

**O buraco real, que nenhum dos dois cobre, é a CONVENÇÃO** (idioma de framework, padrão de
projeto): nos dois lados ela é prosa humana. Atacar isso é trabalho próprio — registrado como
tema aberto, fora do escopo desta recusa.

### 8.2 O que continua valendo

O **MCP do Boost** segue registrado e em uso no `.mcp.json`. A recusa é só da camada
guidelines/skills.
