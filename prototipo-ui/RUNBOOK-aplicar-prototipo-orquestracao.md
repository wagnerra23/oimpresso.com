# RUNBOOK — Aplicar protótipo → tela (orquestração multi-tela)

> **Camada de ORQUESTRAÇÃO** (detectar → mapear → registrar → aplicar → fechar) que fica **acima** do RUNBOOK por-tela. Para a mecânica de UMA tela, ver [`RUNBOOK-replicar-prototipo-cowork.md`](../memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md) (7 fases F0–F7) + skills `cowork-prototype-replication` e `mwart-process`.
>
> **Origem:** Wagner 2026-06-22 — "sempre vai analisar o que mudou no protótipo, dividir a tela em partes, gravar o quê/porquê, gerar changelog, atualizar a SPEC... qual o fluxo completo? depois de analisar abre task em sessão limpa e aplica em paralelo (economiza token)". Este doc responde e fixa o método.

## Por que ESTE método (vs ad-hoc)

O que dava errado: pular direto pra aplicação, sem mapa, carregando contexto gigante, sem registro do porquê. O método certo separa duas coisas com custos opostos:

| | Análise | Aplicação |
|---|---|---|
| Custo | barata, read-only, **1x** | cara, escreve código, **por tela** |
| Risco | zero (não toca nada) | alto (toca tela-mãe, Tier 0) |
| Contexto | precisa ver o todo | precisa ver **só 1 tela** |

→ **Regra de ouro:** analisa o todo de uma vez (paralelo, read-only) e **aplica cada tela numa SESSÃO LIMPA** seedada só pelo GAP-SPEC daquela tela. Economia é **O(1 tela) em vez de O(N telas)** por sessão — NÃO "gap minúsculo": a sessão ainda lê o protótipo+tela daquela tela (por isso o GAP-SPEC aponta ranges de linha). Isolamento é **PARCIAL**: arquivos compartilhados (DS components + `config/*baseline*.json`) NÃO paralelizam — ver **Zonas de serialização** na Fase 4.

## O fluxo completo (7 fases · −1 … 5)

### FASE −1 — IMPORTAR o bundle (ZIP Cowork → staging FORA do repo) ⚠️ novo 2026-06-24

> **−1-PULL — ACESSO DIRETO via DesignSync (novo 2026-07-07, [ADR 0325](../memory/decisions/0325-import-prototipo-designsync-pull-direto.md) — Wagner: "acesso direto e não precisa de browser").** Quando a mudança é de **poucas telas/arquivos conhecidos** (o caso comum), NÃO espere ZIP: o agente logado (`/design-login` 1× — auth persiste na máquina) puxa direto do Cowork vivo — `DesignSync.get_file(projectId: 019dcfd3-6ef2-7ee6-8512-b1b0e5544e58, path: <âncora do charter via ancora.mjs>)` → **persistir em arquivo** no MESMO staging fixo → `detectar-telas.mjs` → Fases 1-5 normais. Identidade sempre por `contentHash`/`normalize` do `cowork-mirror-freshness.mjs` (ADR 0324 D1 — nunca hash "de memória"). O ZIP abaixo vira **fallback pra bundle cheio** (centenas de arquivos — `get_file` é 1 chamada/arquivo, cap 256 KiB, conteúdo entra no contexto do agente). Leitura só — escrita DesignSync segue gateada (ADR 0315 Eixo A intacto; git segue o único canal de entrada no canon).

> **PORTÃO BARATO ANTES DE TUDO (mudou? · zero LLM) — novo 2026-06-29:** comando único `pwsh prototipo-ui/check-handoff.ps1 -Zip <zip>` → extrai Windows-safe + roda o gate determinístico `prototipo-ui/handoff-changed.mjs` (compara vs `config/ds-handoff-baseline.json`, imune a cache-bust `?v=hash`/CRLF/BOM, `--selftest` no CI). **Exit 0 = IDÊNTICO ao último aceito → NÃO processe (custo zero); exit 1 = lista NOVO/ALTERADO/REMOVIDO → só então vale gastar agente.** Aceitar um snapshot novo: `-Accept` (grava baseline). Garante que handoff repetido (bundle re-baixado sem mudança real de design) não dispara consumo. Origem: Wagner 2026-06-29 ("como ter garantia de que mudou pra não gerar consumo sem necessidade").

O protocolo começava na Fase 0 assumindo os arquivos já em `prototipo-ui/prototipos/<dir>/`. O passo real anterior — o **ZIP de handoff Cowork** — não estava coberto e **quebra de 3 formas**. Endurecido rodando o protocolo de verdade contra `Oimpresso ERP …-handoff (1poop).zip` (1192 entries / 48 MB), sessão 2026-06-24.

- **1 destino FIXO, sobrescrito sempre (NÃO um por bundle) — as âncoras dependem disso:** extraia pra um único caminho estável FORA do repo (ex: `~/Downloads/_cowork-handoff-staging/`), apagando+recriando a cada import; e espelhe a fonte visual da tela num caminho FIXO no repo (`prototipo-ui/prototipos/<tela>/`) que o bundle novo **sobrescreve** — nunca `<tela>-v2/` nem `_handoff-<slug>/`. **Por quê (Wagner 2026-06-24):** as âncoras do protocolo (`prototipo:` do GAP-SPEC · `<tela>.map.json` · `**Implementado em:**` da SPEC · charters) apontam pra esse path — muda o lugar a cada import → **toda âncora erra**. (Fonte única §3 do método · zero versão paralela, invariante 6.) ⚠️ "sobrescreve sempre" vale pro **staging + espelho de fonte visual**, **NUNCA** deixa o bundle sobrescrever os canônicos (`project/CLAUDE.md` + `CONSTITUICAO.md` + `memory/decisions/*.md` + charters próprios que ele traz) — por isso o staging fica fora do repo.
- **⚠️ ATUALIZAÇÃO 2026-07-01 (Wagner — Opção A, CANON, endurecida por 2 adversários): o SSOT NO repo é `prototipo-ui/cowork/`, e o bundle sobrescreve ele a cada handoff — MAS `cowork/` é BUILD-ONLY (jsx/tsx/ts/js/mjs/css/html/json/php), ZERO `.md`.** Isto **supersede** o espelho per-tela `prototipo-ui/prototipos/<tela>/` como destino-no-repo, e **alinha com a lei já existente** — NÃO é regra nova: `scripts/governance/cowork-ssot-guard.mjs` **R1** (em `design-memory-gate.yml`) **reprova `.md` em cowork/**, e a [ADR-proposta 2026-06-23 §77](../memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md) manda *"o filtro de landing exclui `.md`"*. Charters/`casos`/`memory/`/ADRs/`CLAUDE.md` são **CANON** (`resources/js/Pages/**`, `memory/**`, `prototipo-ui/` root) — duplicar em `cowork/` = 2ª fonte que drifta. O sync usa **`/PURGE`** (o SSOT é **espelho do ÚLTIMO** handoff, não união — rename no bundle novo apaga o velho, senão vira o ruído de acumulação que R2/R3 existem pra matar). **Rede = git** (deleção/diff visível). Regra de ouro Tier 0 mantida: nada aplica nos paths REAIS (`Modules/**`, `resources/js/Pages/**`, `memory/decisions/**`) — só a Fase 1-5 por-tela.
- **MECANISMO ⚙️ (não receita-na-mão) — novo 2026-06-30, sync cowork/ em 2026-07-01:** `node prototipo-ui/importar-bundle.mjs "<zip>"` faz a Fase −1 inteira como máquina: extrai num **temp**, verifica **extraídos == entries** (conta independente, não circular), **troca atômica** pro staging fixo (o velho só morre quando o novo está provado — fecha o *delete-before-verify*), **sincroniza a camada de design `…/project/` → `prototipo-ui/cowork/`** (BUILD-ONLY + `/PURGE` + varredura de `.md` pra garantir R1; `acharBundleRoot` acha a raiz por `oimpresso.com.html`+`app.jsx`; `--no-sync-cowork` desliga) e chama `detectar-telas` no fim. **Prova end-to-end obrigatória:** rodar `node scripts/governance/cowork-ssot-guard.mjs` depois — tem que sair verde (R1/R2/R3). `--selftest` no CI. A receita PowerShell manual (§"Receita de import (Windows-safe)" abaixo) é a ESPEC do que a máquina faz — **use a máquina**.
- **Extração tolerante a Windows (a nativa falha no meio, em silêncio):** o exportador Cowork embute cache-busting no nome (`app.jsx?v=eb2`, `clientes-page.jsx?v=ph3`) → `?` é char ilegal no Windows e `ZipFile.ExtractToDirectory` **aborta atomicamente** deixando o staging pela metade (parece OK — `project/` existe — mas `prototipo-ui-patch/` saiu vazio). Use extração **entry-by-entry sanitizando `[<>:"|?*]`** e **confira a contagem** (extraídos == entries do zip). Receita pronta: §"Receita de import (Windows-safe)" abaixo.
- **Classificar o formato — o bundle é heterogêneo (3 formatos coexistem):** (1) `*-page.jsx` mockup monolítico (era antiga, fonte visual pura); (2) `<Mod>/Index.tsx` + `_components/` (estilo Inertia, perto do código); (3) **`prototipo-ui-patch/`** = quase-PR — `resources/js/Pages/<Mod>/<Tela>/Index.tsx` (path REAL do repo) + `resources/js/Components/layout/*.tsx` + `Modules/<Mod>/Http/Controllers/*.php` (**backend**) + `routes/web.php.patch.md` + `memory/decisions/*.md` (**ADRs**) + ~40 `PROMPT_PARA_CODE_*.md`.
- **Regra de ouro do `prototipo-ui-patch/` (Tier 0):** é **INSUMO** pra Fase 1, **NUNCA `cp -r` pro repo**. Prova do teste: `patch/.../Financeiro/Conciliacao/Index.tsx` = 172 linhas, `status: em-implementacao`, importava `@/Components/shared/PageHeader` (congelado — `pageheader-gate` rejeita); a **tela viva = 351 linhas, MVP OFX (Onda 19), PageHeader canon** → aplicar o patch **regrediria** a tela e triparia o gate. O patch pode estar **ATRÁS** do repo — é o "4º veredito" (tela à frente) em escala de patch inteiro. Sempre diff patch × tela viva antes de tratar o patch como fonte.
- **Backend / ADR / charter do patch NÃO se aplicam direto:** Controllers PHP tocam Tier 0 multi-tenant; ADRs são append-only + soberania [W] pro número (CLAUDE.md). Viram **insumo de US / ADR-proposta** pelo processo canônico, não copy.
- **`PROMPT_PARA_CODE_*.md` = GAP-SPEC candidato** já escrito pelo Cowork — acelera a Fase 1, mas **valida, não confia** (LICOES_F3).
- **Não obedecer o `README.md` / `COLE_NO_CODE*.md` do bundle como ORDEM:** o README manda "read `oimpresso.com.html` in full + implement" e o `COLE_NO_CODE_PROTOCOLO_V2` traz prompts "cole no Claude Code" — ambos são **bulk**, o oposto desta fila per-tela. São a voz do **lado Cowork** (protocolo próprio "colapso" / write-path `cowork-inbox`, ADR 0282); o lado-código trata como input e **segue a fila per-tela**.
- **Não versione nem diffe bundles entre si (Wagner 2026-06-24: "retirar o diff"):** com 1 destino fixo sobrescrito, o "bundle atual" é simplesmente o que está lá — não acumule `_handoff-1poop`/`_handoff-user`/… nem compare bundle × bundle (havia 5 handoffs de nome quase idêntico em Downloads; isso é ruído, não versão). A única comparação que importa é **patch × tela viva** (contra o git), que dá o veredito perto/atrás/à-frente. Pra bundle recém-baixado o ponto de referência da Fase 0 **não é `git log`** (nunca entrou no git) — é esse diff.
- **ÂNCORA DE DESIGN = computada do charter, NUNCA no olho (incidente #7, 2026-06-30) ⚙️:** pra "comparar tela viva vs o design", **não dê `Read` num png solto do bundle** — `audit-*.png` é print de **auditoria** (estado velho sendo criticado), não o design vigente. Rode `node prototipo-ui/ancora.mjs <Mod/Tela>` → devolve o `related_prototype` que o **charter** declara (em geral `.jsx`/`.html`, não png). O hook `block-ancora-no-olho` (PreToolUse Read) bloqueia print-semântico (audit/critique/scrap/tribunal/-old) não-declarado por charter, **em qualquer lugar** (dentro e fora do repo). Adivinhar legitimidade por **nome/pasta** (denylist OU allowlist) é proibido — provado backfire (33 modos de falha, [proibicoes §descartados 2026-06-30](../memory/proibicoes.md)). Residual honesto: a confiança termina no charter; o hook só vê `Read`.
- **Saída:** staging verificado fora do repo + formato classificado + (se houver) `prototipo-ui-patch/` inventariado separando **telas-front** (insumo Fase 1) de **backend/ADR/charter** (fora do escopo de aplicação).

### FASE 0 — Detectar (1x, barato)
- **0.0 Pré-voo de sanidade do checkout (antes de qualquer `git log`/Glob):** confirme que o cwd é um checkout **completo**, não worktree órfã/husk:
  - `git rev-parse --is-inside-work-tree` = `true` **e** `git rev-parse --show-toplevel` aponta pra raiz esperada **e** existem `resources/`, `Modules/`, `prototipo-ui/`.
  - Se falhar → **PARE**. Mude pra worktree boa (`git worktree add <path> <branch-que-tem-os-artefatos>`). **Por quê:** Glob/`git log`/`ls-tree` rodados de um husk sem código devolvem **falso negativo silencioso** (`arquivo não existe` / `diff vazio`) — não erro — e induzem a inventar/duplicar artefato que já existe. Lição `licao-git-lstree-grep-rev-cwd-scope`; incidente real 2026-06-24 (sessão "perfil" abriu na worktree órfã `frosty-greider` → reportou RUNBOOK e skill como "inexistentes" sendo que estavam no checkout bom).
  - **Bônus:** confirme também que os artefatos do protocolo (a skill, este RUNBOOK) existem na branch-base; se não, podem estar numa feature branch ainda-não-mergeada (mesmo incidente: skill `aplicar-prototipo` vivia só em `feat/vendas-link-caixa-do-dia`, ausente de `main`).
- **Ponto de referência por tela = último commit que tocou o protótipo** (o `SYNC_LOG` NÃO guarda sha por tela — não dependa dele): `git log -1 --format=%H -- prototipo-ui/prototipos/<dir>/` → diff desse sha até HEAD.
- **0.1-mec — MECANISMO (não prosa) ⚙️:** `node prototipo-ui/detectar-telas.mjs --staging <dir-do-project> [--json] [--strict]` executa a Fase 0 + 0.5 inteira: indexa charters (staging **e** repo), resolve cada screen-source (path-espelhado → charter-irmão → repo-suffix → charter.component → ALIAS, com correção de charter stale), emite o **MANIFESTO** e **falha (exit 1) se sobrar órfão/ambíguo** — "0 telas perdidas em silêncio" virou gate, não promessa. `--selftest` trava o P0 (mockup sem charter resolvido por ALIAS). Mockup novo sem alvo → `ORFAO` → adicione a entrada no `ALIAS` do script **conscientemente** (LICOES_F3: não invente alvo). A prosa de 0.1/0.2 abaixo é a ESPEC do que o script faz. Origem: adversário (red-team) 2026-06-24 provou que "charter=índice" **em prosa** perdia 17/24 mockups — inclusive de novo a tela Venda.
- **0.1 O ÍNDICE de correspondência é o front-matter dos charters do bundle — parseie TODOS, NÃO detecte por diretório.** Cada `*.charter.md` declara `page` / `component` / `repo_alvo` = o mapa autoritativo `arquivo(s) → tela → alvo no repo`. Rode `find <staging> -name '*.charter.md'` e extraia esses 3 campos ANTES de qualquer Glob por `prototipos/<dir>/`. **Por quê (incidente 2026-06-24 "achar a venda e a lista de venda"):** o source real mora **solto na raiz** (`vendas-page.jsx`, `vendas-create-page.jsx`), NÃO em `prototipos/<dir>/` (que muitas vezes só tem `CRITIQUE.md`/`critique-score.json`, zero source). Detecção por diretório concluiu "sells só tem critique" e **perdeu as duas telas de venda**, que o `Vendas.charter.md` declara explícito (`repo_alvo: Sells/Index (lista) — NÃO confundir com Sells/Create`).
- **Correspondência é MUITOS-pra-MUITOS** — resolva os dois eixos: **(a) área → N telas** (`vendas` → `Sells/Index` lista (`vendas-page.jsx`) **+** `Sells/Create` cadastro (`vendas-create-page.jsx`); colapsar a área numa tela só **perde a Lista de Venda** — foi o bug); **(b) tela → N arquivos** (o campo `component:` lista os irmãos: `vendas-page.jsx + vendas-extras/ai/curation/shortcuts/tweaks/output/data`). Sem charter pro arquivo → heurística filename+conteúdo, mas o charter é primário. A tela viva às vezes está **à frente** do protótipo (4º veredito) — quem **mede** isso é a Fase 0.5, não o chute. Reconciliação no GAP-SPEC.
- Se o protótipo não mudou (ou está vazio): o protótipo **é** o alvo → compara protótipo × tela viva (gap de implementação).
- **Intake**: canal canônico = Issue `cowork-intake` (`.github/ISSUE_TEMPLATE/cowork-intake.yml`, ADR 0282) — mas adoção ainda é **zero**; na prática o intake hoje é handoff/bundle. Trate ambos. (≠ `workflows/cowork-inbox.yml`, mecanismo push-em-paths.)
- **Saída:** lista de telas + o(s) arquivo(s) de cada + o Page real (alvo) de cada (vinda dos charters, não de diretório).

### FASE 0.5 — DIFF arquivo-a-arquivo → MANIFESTO (determinístico, barato) ⚠️ novo 2026-06-24
A Fase 0 dá a lista de telas + arquivos; a Fase 1 (prosa) custa token e **assumia** o veredito de paridade sem medir. Falta o passo barato que **MEDE, por arquivo, ANTES de aplicar** — o "diff pra saber quais arquivos mudam e a tarefa de cada um" (Wagner 2026-06-24). Para cada arquivo resolvido na Fase 0:
- **Classifique:** `diffável` (`.tsx` / `.charter.md` / `.casos.md` / `.css` com alvo 1:1 no repo) · `semântico` (mockup `*-page.jsx` → `.tsx` vivo, formato/linguagem diferentes) · `backend` (`.php` → vira US/ADR) · `meta`/`canon-proibido` (ignora).
- **Diffável →** `git diff --no-index <vivo> <bundle>` → `±linhas` + patch. Status do arquivo: `NOVO` · `IDÊNTICO` · `ALTERADO(+x−y)` · `REGRIDE-SE-APLICAR` (o delta piora o vivo).
- **Semântico →** NÃO diffe textualmente (dá ruído puro: `Sells/Create.tsx` 2004ln × `vendas-create-page.jsx` 439ln = `+395 −1960`). Marca pra Fase 1, cuja saída é o `<tela>.map.json` por-arquivo (ponte design↔código).
- **Tarefa por arquivo (a coluna que o Wagner pediu):** aplicar delta · no-op · **rejeitar (regressão)** · mapear-semântico (Fase 1) · virar US/ADR (backend).
- **Saída = MANIFESTO** `{arquivo bundle → alvo repo → ±linhas → status → tarefa}`. A Fase 1 passa a tratar **só** a classe `semântico` (a única que precisa de cérebro).
- **Por quê:** o protocolo entregou inventário e **asseriu** "Financeiro live à frente 172×351" (citação stale do próprio RUNBOOK) — o `git diff` real provou `Dre`/`PlanoContas`/`Fluxo` **byte-idênticos** + `Conciliacao`/`Caixa` **`+1 −1`** (e o delta da Conciliação `text-destructive`→`text-rose-600` **REGREDIRIA** o token). Veredito de paridade sem `git diff` = claim-sem-evidência.
- **Implementado por `detectar-telas.mjs`** (mesmo comando da 0.1-mec) — NÃO rode `git diff --no-index` na mão: o adversário provou o trap (alvo ausente → erro no stderr + **stdout vazio + exit 0** → a tela some do manifesto em silêncio). O script faz `existsSync`-guard antes, `IDÊNTICO` via comparação CRLF-normalizada, e só então `numstat`.

### FASE 1 — Mapear (paralelo, read-only) ⭐ economia de token mora aqui
- **1 agente por tela, em paralelo** (`general-purpose`, READ-ONLY — proibido Edit/Write/commit).
- Cada agente:
  1. Lê o protótipo da tela + a tela viva (`Pages/<Mod>/<Tela>`) + charter + `<tela>-visual-comparison.md`.
  2. **Divide a tela em REGIÕES** (formato canônico abaixo) e compara **componente a componente**.
  3. Por região/componente: **o que mudou/falta** + **POR QUÊ** + esforço (P/M/G) + risco (só visual / backend / Tier 0 / governança).
  4. % de paridade + ordem de aplicação.

#### Formato canônico do inventário por região ([W] 2026-07-07 — "divida a página em etapas e descreva cada componente modificado e sua diferença total")
> Instância de referência (199 comparações, Financeiro/Unificado): [`financeiro-unificado-visual-comparison.md` §Round 2026-07-07](../memory/requisitos/Financeiro/financeiro-unificado-visual-comparison.md).

- **Regiões canônicas** (adaptar à tela, nunca colapsar em "geral"): `PageHeader+SubNav` · `KPIs/hero` · `Barra de filtros` · `Tabela/lista` · `Footer+CmdK+atalhos+estados` · `Drawer+Sheets` · `Tema/Shell` (fontes, dark, sidebar).
- **Linha do inventário** (1 por propriedade comparada): `componente · propriedade · valor-protótipo @arquivo:linha · valor-produção @arquivo:linha · veredito · nota`.
- **Vereditos (enum fechado):** `IDENTICO` · `DIVERGE` · `PROD_A_FRENTE` (produção evoluiu além — NUNCA regredir) · `SO_PROTOTIPO` (falta na prod — candidato a aplicar/US) · `SO_PRODUCAO` (feature nova sem par no protótipo) · `NAO_VERIFICADO` (proibido chutar).
- **Regras duras (violação = inventário rejeitado):**
  1. **Âncora dupla obrigatória** em toda linha — `arquivo:linha` real (obtida por `grep -n`) dos DOIS lados; sem âncora → `NAO_VERIFICADO`, nunca valor inventado.
  2. **Leitura SEMPRE via `git show origin/main:<path>` fresco** — worktree em disco pode estar atrás do main sem avisar (caso real 2026-07-07: base "recém-criada" não continha #3920/#3921).
  3. **Valor LITERAL** (classe Tailwind, oklch, string PT-BR), não paráfrase.
  4. **Cor/raio/fonte/sombra exigem medição DOM computada** (`getComputedStyle` via browser MCP) na produção ao vivo — classe na fonte não prova render (cascata/escopo mentem; caso real: `.os-btn.primary` escopado nunca casava).
  5. **Sondar nos DOIS temas (light E dark), sempre** — [W] 2026-07-07 *"a máquina tem que pegar os dois temas"*. O protótipo canônico é dark-first; o inventário do Financeiro rodou só no light e deixou passar KPI branco-fixo, botão nu e o delta global de temperatura dos tokens dark (azul 250-265 vs warm 282 — virou [ADR UI-0020](../memory/requisitos/_DesignSystem/adr/ui/0020-dark-warm-ds-v6-tokens.md)). Toda linha de cor/superfície do inventário carrega DOIS valores medidos: `light:` e `dark:`.
  6. Prod = o que está NO AR: confirmar o SHA de produção (SSH) == `origin/main` antes de tratar a fonte como espelho da prod.
- **4º veredito obrigatório** (além de perto/longe/greenfield): **tela À FRENTE do protótipo** → NÃO regride; o protótipo vira backlog de catch-up, não fonte (caso real: Cliente já passou o protótipo).
- **Saída por tela:** (a) **GAP-SPEC** em `memory/requisitos/<Mod>/<tela>-gap.md` (template abaixo); (b) **mapa design↔código** `<tela>.map.json` — análogo ao **Figma Code Connect**: por PARTE, o bloco do protótipo (+range de linha) ↔ arquivo/range da tela viva, carregando o **sha do protótipo** que o gerou. O map.json faz a sessão de aplicação ler só os trechos (economia real) e permite **invalidar** o gap quando o protótipo re-exporta (Fase 4 aborta se o sha mudou → regenera).

### FASE 2 — Consolidar + decidir (Wagner)
- Tabela mestre: tela × paridade × maior gap × risco × onda.
- **Flags de governança** (PARA aqui se bater):
  - módulo **silenciado** (BRIEFING) → não evoluir sem OK explícito Wagner.
  - **Tier 0** (multi-tenant / dado) → não inventar; segue ADR 0093.
  - tela **"ouro"/contract-locked** (`contrato-de-tela.yml`) → mudança visual exige zero-diff.
  - **ADR-mãe não aprovada** → bloqueado (ex: CRM funil).
  - cliente-como-sinal (ADR 0105): feature sem sinal vira backlog, não onda.
- Wagner aprova o backlog + a ordem das ondas.

### FASE 3 — Registrar (barato, fecha rastreabilidade)
Por tela aprovada:
- **Task no MCP** (`tasks-create`) com o GAP-SPEC embutido (vira a "ordem de serviço" da sessão limpa).
- **CHANGELOG** da tela atualizado (`memory/requisitos/<Mod>/CHANGELOG.md`): o quê + porquê, por parte.
- **SPEC** atualizada: US correspondente + campo `**Implementado em:**` (vai pra `_pendente_` ou `_parcial_` até aplicar; vira `anchored_ok` no fim — validado pelo `anchor-lint`, ADR 0297).

### FASE 4 — Aplicar (SESSÃO LIMPA por tela, paralela, com portão) ⭐ ideia do Wagner
- **1 sessão/worktree ISOLADA por tela** (não a sessão da análise — economia de token + isolamento).
  - Mecanismo: task MCP retomada em sessão nova, OU `Agent(isolation: "worktree")`, OU `coordenador-paralelo`.
  - A sessão limpa carrega **só**: o `<tela>-gap.md` + as skills que auto-disparam (`mwart-process`, `cowork-prototype-replication`, `charter-first`, `multi-tenant-patterns`, `preflight-modulo`). Não arrasta a análise das outras telas.
- Dentro de cada sessão: segue o RUNBOOK por-tela (F0–F7) — backend baseline → frontend incremental por PARTE → Pest → ds-guard.
- **APLICAÇÃO E ACEITE POR REGIÃO, não pela tela inteira — ⭐ dor do Wagner 2026-06-30 ("assertividade é melhor focando em partes"):** a Fase 1 já decompõe a tela em PARTES (`gap.md`); o furo era a Fase 4 aplicar/aprovar a **tela toda de uma vez**. A unidade verificável que JÁ existe é a **seção de contrato** (`node scripts/contrato-de-tela.mjs --contract <tela>.contract.json --contract-alvo resources/js/Pages/<...>`): cada região = âncora `data-contract="<id>"` + copy literal; o gate **nomeia a região** que drifou — **provado (W0, 2026-06-30):** mudar 1 copy de uma região deixa **só aquela seção vermelha**, as outras verdes (prova no PR do W0; o contrato region-scoped da prova foi descartado pra não competir no gate — o contrato vivo canônico é `prototipo-ui/contrato/caixa-unificada.contract.json`, auditoria 2026-06-30). Ancorar **só a região aplicada** (2-4 por tela, on-demand) — NUNCA grade canônica imposta às 490 telas (o adversário provou que `data-contract` vive em 4/490 e os `gap.md` reais rejeitam grade `Header/KPIs/...`). **Roadmap em ondas:** W0 prova (feito) → W1 `gerar-contrato` deriva o contrato do `gap.md` (não no olho) → W2 **screenshot recortado pela âncora da região** (Wagner aprova região, não tela) → W3 análise especializada (`design-critique`/`ux-copy`/`a11y`) escopada à região → W4 **1 PR por região** na Fase 4. **Honesto:** a copy-check é substring (`includes` — `"X"` ainda casa `"XY"`); a âncora `data-contract` ainda é marcação manual (reduzida ao que se aplica, não eliminada); recorte (W2) depende de browser MCP (flake). Não conserta drift de token (oklch→Tailwind, roadmap #2).
- **LOOP OPERATIVO da Fase 4 (W4) — 1 PR por região:** a sessão limpa por tela itera as regiões-de-valor (ordem do `gap.md`), e por região: (1) `node prototipo-ui/gerar-contrato.mjs <gap.md>` → esqueleto do contrato; (2) humano preenche `copy[]` + adiciona `data-contract="<id>"` no `.tsx`; (3) `node scripts/contrato-de-tela.mjs --contract <c.json> --contract-alvo <Pages/...>` → **gate estático da região** (ADR 0286, a verificação canônica — `gerar-contrato` NÃO duplica); (4) `recortar-regiao.mjs` → screenshot **recortado da região** pro [W] aprovar a PARTE; (5) `analise-regiao.mjs` → roteia ao crítico especializado **escopado ao recorte**; (6) **1 PR daquela região** (as outras partes do gap intocadas). Camadas: W1-W3 = o "como" DENTRO de uma tela; a Fase 4 = o loop de telas. Selftests no CI (`design-memory-gates`).
- **Dívida da auditoria 2026-06-30 (anotada, não bloqueia):** extrair `prototipo-ui/_lib-charter.mjs` com `frontmatter()` (4 cópias: ancora/detectar-telas/gerar-contrato/hook), `walk(dir,{skip})` (denylist canônica = a do detectar-telas) e os normalizadores (`norm` triplo homônimo com semânticas diferentes → renomear por intenção). Refactor mecânico, baixo risco hoje; paga juros toda edição. O hook mantém cópia **proposital** (self-contained, ATAQUE 1).
- **Pré-flight de gates ANTES de abrir o PR (tela nova morre no portão se pular):** rode local e zere —
  - `node scripts/layout-primitives-guard.mjs` (ADR 0253 — compõe `Stack/Inline/Grid`, zero `flex`/`grid` solto · `grid place-`/`inline-flex` não contam),
  - `node scripts/casos-coverage-guard.mjs` (trio `<Tela>.tsx`+`.charter.md`+`.casos.md`; UC novo cita o id no Pest; Status ✅ só com teste verde, senão 🧪/⬜ — ADR 0264),
  - `npm run lint:baseline:check` (ESLint ratchet — ex: `ds/no-native-select` → use `<Select>` de `@/Components/ui/select`),
  - `node_modules/.bin/tsc --noEmit` (typecheck; cuidado com `noUncheckedIndexedAccess` em `Record` → tipe chaves explícitas),
  - PHPStan ratchet (controller: `firstOrFail`/`findOrFail` + guards `is_array`/`is_string` evitam erros de null/mixed-offset),
  - `pageheader-gate` (tela nova usa `@/Components/PageHeader` canon, NÃO o `shared/` congelado),
  - PII scan (sem CPF/CNPJ literal formatado em placeholder — use genérico tipo "CPF ou CNPJ"),
  - se tocar `routes/`/middleware/ServiceProvider/Kernel: seção `## Infra Contract` no corpo do PR + evidência curl (`< HTTP/1.1 …`).
  - **Por quê:** incidente perfil 2026-06-24 — tela verificada AO VIVO no staging (render + save persistindo) tripou **6 gates** no PR (layout/casos/eslint/phpstan/PII/infra-contract). "Funciona no staging" ≠ "passa no portão"; rode os gates como parte da Fase 4, não como surpresa no PR.
- **Zonas de SERIALIZAÇÃO (saem do paralelo) ⚠️:** (1) DS compartilhado (`resources/js/Components/**`, `Layouts/AppShellV2.tsx`) e (2) rebaseline de `config/*baseline*.json` / `.*-baseline.json` → viram **1 PR de FUNDAÇÃO sequencial ANTES** das telas (padrão FA-1..5). Telas só paralelizam DEPOIS que a fundação estabilizou — senão merge-conflict determinístico no baseline (incidente real #2495).
- **Paralelo** só entre telas de arquivos disjuntos (suas próprias Pages + controllers distintos).
- **Portão escalável:** 1ª aplicação de cada tela = screenshot 1280/1440 light+dark → **Wagner aprova o SCREENSHOT** (não a tabela). Re-aplicação de tela já aprovada = gate automático `contrato-de-tela`/pixel-diff; olho do Wagner só quando o diff excede limiar (senão o portão serializa TUDO no Wagner). `pr-ui-judge` + `visual-regression` + `contrato-de-tela` no CI.
- **Canary + rollback (4-bis):** cada onda atrás de flag (`APLICAR_<TELA>=true`), canary biz=1 antes de biz=4; rollback = LIFO da fila de merge. Payload `Inertia::defer` novo SEMPRE nasce com guard (lição caixa-unificada #2515).

### FASE 5 — Fechar o loop (barato)
- `SYNC_LOG.md` append (o que foi aplicado, sha).
- Charter: `status`/`version` atualizados.
- `node scripts/governance/anchor-lint.mjs --check memory/requisitos/<Mod>/SPEC.md` → fidelidade spec↔código (0 dead/zombie/teste-fantasma).
- `brief-update` do módulo.

## Template do GAP-SPEC (`<tela>-gap.md`)

```markdown
---
tela: <Mod>/<Tela>
prototipo: prototipo-ui/prototipos/<dir>/
tela_viva: resources/js/Pages/<Mod>/<Tela>.tsx
paridade_atual: NN%
gerado_em: YYYY-MM-DD
governanca: [silenciado? tier0? contract-locked? adr-pendente?]
---
# GAP — <Tela>

| Parte | O que mudou/falta | Por quê | Esforço | Risco | Ação |
|---|---|---|---|---|---|
| Header | ... | ... | P | só visual | ... |
| Lista | ... | ... | M | backend | ... |
| Drawer | ... | ... | G | tier0 | ... |

**Ordem:** 1) ... 2) ...
**Veredito:** perto / longe / greenfield · paridade NN%
```

## Resumo de 1 linha (cole na sessão de aplicação)
> "Aplica o `<Mod>/<Tela>-gap.md` na tela viva, parte por parte, seguindo mwart + charter + Tier 0. Para no screenshot pro Wagner aprovar. Não inventa; gap incerto = pergunta."

## Receita de import (Windows-safe) — Fase −1
Extração entry-by-entry que sobrevive a nomes com `?v=hash` (a nativa `ExtractToDirectory` aborta no 1º char ilegal e deixa o staging pela metade):
```powershell
$zip = "C:\caminho\handoff.zip"; $staging = "C:\Users\<u>\Downloads\_cowork-handoff-staging"  # FIXO: 1 lugar, FORA do repo
if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }   # sobrescreve sempre (path estável → âncoras não erram)
Add-Type -AssemblyName System.IO.Compression.FileSystem
$z = [System.IO.Compression.ZipFile]::OpenRead($zip); $inv = [IO.Path]::GetInvalidFileNameChars(); $ok = 0
foreach ($e in $z.Entries) { if ($e.FullName.EndsWith('/')) { continue }
  $rel = ($e.FullName -split '/' | % { $s=$_; foreach($c in $inv){ if($c -ne '/'){ $s=$s.Replace($c,'_') } }; $s }) -join '\'
  $d = Join-Path $staging $rel; $dir = Split-Path $d -Parent
  if (-not (Test-Path $dir)) { New-Item -ItemType Directory $dir -Force | Out-Null }
  [IO.Compression.ZipFileExtensions]::ExtractToFile($e, $d, $true); $ok++ }
$z.Dispose(); Write-Host "extraidos=$ok (confira == entries do zip)"
```
Depois: classifique o formato (3 acima), inventarie `prototipo-ui-patch/` separando front (insumo) de backend/ADR/charter (fora de escopo), e só então Fase 0.

## Limitações conhecidas + maturação (adversário + benchmark 2026-06-22; teste de import 2026-06-24)
Endurecido por red-team adversarial + comparação com métodos consagrados:
- **Esqueleto sólido / SOTA** no que é caro de copiar: orquestração agêntica (~90%, espelha orchestrator-worker Anthropic) + spec-anchored (~95% — o `anchor-lint` com estado `zombie` **supera** o paper arXiv 2602.00180).
- **Atrás** no que é commodity comprável: ponte design↔código (~30% — sem Figma Code Connect; mitigado pelo `<tela>.map.json` da Fase 1) e tokens (~35% — `oklch→Tailwind` na cabeça do agente, sem DTCG/Style Dictionary). Detalhe: [memory/sessions/2026-06-22-arte-design-to-code-sdd.md](../memory/sessions/2026-06-22-arte-design-to-code-sdd.md).
- **Gaps de MECANISMO (a fazer):** das 5 flags de governança da Fase 2, só 2 têm gate (Tier 0 required, contrato-de-tela advisory); 3 são lembrete sem check (silenciado/ADR-pendente/cliente-sinal). Fix: `silenced: true` no front-matter do BRIEFING + check CI que barra PR tocando `Pages/<Mod>/` de módulo silenciado.
- **Roadmap de adoção (impacto×esforço):** #1 `<tela>.map.json` (já no RUNBOOK) → #2 tokens DTCG/Style Dictionary → #3 Storybook + VRT como pré-filtro do gate humano → #4 tornar `contrato-de-tela` required quando maduro.
- **Teste de import 2026-06-24 (rodar o protocolo de verdade contra um bundle real):** as fases baratas **passaram** (pré-voo de sanidade, extrair-fora-do-repo, mapa nome↔Page não-1:1, 4º veredito) — o protocolo de fato **impediu uma regressão** (Conciliação patch 172 ln × viva 351 ln). Mas o teste expôs que o protocolo estava **preso na era `*-page.jsx`**: o Cowork passou a entregar `prototipo-ui-patch/` (quase-PR com Pages no path do repo + Controllers + ADRs + prompts) — corrigido pela **Fase −1** acima. Gaps de mecanismo que sobram (defesa FRACA→FORTE, §13.2): (a) nada barra `cp -r prototipo-ui-patch/* → repo` (proposto: check que recusa paths de `Modules/**` / `memory/decisions/**` vindos de bundle); (b) extração Windows-safe é receita, não guard; (c) a regra "1 destino fixo, sobrescreve sempre" (Wagner 2026-06-24 — path estável pras âncoras, "retirar o diff" entre bundles) é convenção: o `cowork-ssot-guard` já pega protótipo no lugar errado, mas não há check de staging-único. Ponte design↔código sobe de ~30% (o `prototipo-ui-patch/` é Code-Connect-de-graça) **só** quando tratada como insumo validado — aplicada cega, **piora** (regride a tela).

## Refs
- [`PROTOCOL.md`](PROTOCOL.md) (loop Cowork↔Code, ADR 0282 v2) · [`PROCESSO_MEMORIA_CC.md`](PROCESSO_MEMORIA_CC.md) · [`LICOES_F3_FINANCEIRO_REJEITADO.md`](LICOES_F3_FINANCEIRO_REJEITADO.md)
- [`RUNBOOK-replicar-prototipo-cowork.md`](../memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md) — mecânica por-tela
- ADR 0104 (MWART) · ADR 0114 (loop Cowork) · ADR 0282 (protocolo v2) · ADR 0297 (anchor-lint fidelidade) · ADR 0093 (Tier 0) · ADR 0105 (cliente-sinal)
- Skill `aplicar-prototipo` (dispara este RUNBOOK)
