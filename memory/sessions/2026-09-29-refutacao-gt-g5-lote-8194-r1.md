---
date: "2026-09-29"
topic: "Refutação GT-G5 r1 do lote #8194 (25 .map.json re-ancorados após o handoff 43): 821 itens, 18 refutados (2,19%) — re-ancoragem mecânica ok, mas prosa/status/_acionavel ficaram stale contra o protótipo que o mesmo PR importa"
authors: ["C"]
prs: [8194]
outcomes:
  - "Veredito REPROVADO: 18 erros em 821 itens (2,19% ≥ 2%) — 0 âncora de path ausente, 0 PII; os erros são de prosa/status/_acionavel que o re-ancorar não acompanhou e 3 ranges de protótipo fora do arquivo"
  - "Máquina derivada verde (design-code-map-check --check --strict rc=0; regen com gerar-map --atualizar = 25/25 idênticos) — o checker não valida bounds de prototipo.linhas, por isso os 3 ranges fora do arquivo passam"
  - "Conserto sugerido ao gerador: re-ler status/acao das partes cujo range mudou de conteúdo; citar linhas novas; coluna Ação dos gap.md das 6 partes 'Nada' (fonte do _acionavel); n/a + _nota na parte cujo conteúdo saiu do hrm-extras.jsx"
---

# Refutação GT-G5 · lote #8194 · rodada r1

## TL;DR

**REPROVADO.** 821 itens verificados em 25 `.map.json` (100% das partes), **18 refutados → error_rate 2,19%** (≥ 2%). Nenhum path ausente e PII = 0 (7/7 controles positivos); os erros são (a) prosa/status/citações de linha que o re-ancorar deixou stale contra o protótipo que o **mesmo PR** importa (handoff 43), (b) 6 `_acionavel: true` ao lado de `acao` "Nada a fazer/Feito" e (c) 3 ranges de protótipo fora do arquivo.

## Cabeçalho

| Campo | Valor |
|---|---|
| Base (`origin/main`) | `a71c2f2d052fc2c4b5f5009e9b59a93b337e3f21` |
| HEAD do lote | `4b0f55373f47f3c8d4c958e94baededa7a62fd79` (commits do PR: `aa7964346` importa handoff 43 · `4b0f55373` re-ancora os 25 maps) |
| Repo raso | `git rev-parse --is-shallow-repository` → `false` |
| Sessão fresca | sim — instância nova; não abri `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje (`git grep -l _acionavel` listou nomes de sessões de refutação antigas; só nomes, nenhum conteúdo lido) |
| Corpo do PR / commit message | não usados como evidência |
| Lote medido | `git diff --name-status origin/main...HEAD -- memory/requisitos` → 25 `M`, todos `*.map.json` (Essentials 3 · Governance 4 · Ponto 18) |
| Árvore | `git status --short` vazio antes/depois das sondagens (só este arquivo ao final) |

**Frame de medição declarado:** o PR contém DOIS commits — o handoff 43 muda os `.jsx` do protótipo e o lote re-ancora `prototipo.linhas` a esse protótipo novo. Logo: **paths** e **lado vivo** (`vivo.arquivo/linhas`, código React/PHP) medidos contra `origin/main`; **conteúdo do range do protótipo** medido contra `HEAD` (o protótipo que o lote diz ancorar) **e** contra `origin/main` (o range antigo), comparando os dois blocos. Sem isso a verificação seria impossível: as linhas novas não existem em `origin/main`.

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (refutador Fable 5.1; gerador = Claude no PR — igualdade só se o gerador já for tier máximo)
- [x] Amostra: 100% anchors (194 partes de 25 maps; toda chave de topo; todo path; toda citação de linha dentro de `acao`/`_nota`)
- [x] Cada item verificado contra `origin/main` (paths, vivo, vivo.linhas) e contra o protótipo do próprio PR (ranges), não contra o diff
- [x] Cada REFUTADO com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff — 7 padrões, 0 hits, 7/7 controles positivos casaram
- [x] `error_rate_pct` calculado: 18/821 = **2,19%** (≥ 2 → reprovado)
- [ ] Entry no ledger — NÃO é deste refutador (lote reprovado volta ao gerador)

## Escopo medido

| Medida | Valor |
|---|---|
| Arquivos do lote | 25 |
| Partes | 194 |
| `prototipo.arquivo` ≠ n/a | 176 |
| `vivo.arquivo` ≠ n/a | 171 |
| Ranges de protótipo re-ancorados (old@main × new@HEAD) | 175 |
| Ranges de vivo com número | 162 |
| Linhas `+` do diff em `memory/requisitos` | 165 |

## Tabela por grupo

| # | Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|---|
| 1 | Âncora existe em `origin/main` (gap_fonte 25 + prototipo.arquivo 176 + vivo.arquivo 171) | 372 | 372 | 0 | `git ls-tree origin/main -- <path>` por item; controle negativo `__nao_existe__` → `false`; controle positivo → `true`; 0 `vivo.arquivo` sob `Components/` |
| 2 | Âncora não revogada + frontmatter lido pelo leitor real (25 telas via `ancora.mjs --staging prototipo-ui/cowork/Wagner` + 25 topos `prototipo_sha/gerado_em/tela/gap_fonte` regenerados) | 50 | 50 | 0 | 24× `âncora ✓`, 1× `n/a` declarado (Settings/Index, pré-existente); regen `gerar-map.mjs <gap> --atualizar` = 25/25 idênticos chave a chave |
| 3 | Ação × veredito da prosa + afirmação sobre código/protótipo (por parte) | 194 | 179 | **15** | old-range@main × new-range@HEAD por parte; toda citação `:NNN`/`arquivo.jsx:NNN` dentro de `acao`/`_nota` aberta nos dois refs; `_acionavel` × `acao`/`status` |
| 4 | Célula íntegra (acao == regen; ranges dentro do arquivo; sem truncamento/pipe) | 194 | 191 | **3** | regen chave a chave (0 diff); bounds de `prototipo.linhas` contra `wc -l` do arquivo em HEAD |
| 5 | Máquina derivada | 4 | 4 | 0 | rc literais abaixo |
| 6 | PII (7 padrões × linhas `+`) | 7 | 7 | 0 | hits 0 · controles 7/7 |
| | **Total** | **821** | **803** | **18** | **error_rate = 2,19%** |

> Nota sobre o denominador: G3 e G4 medem propriedades distintas das mesmas 194 partes. Sem G4 no denominador o resultado seria 18/627 = 2,87% — o veredito não depende dessa escolha. Ele depende dos 6 itens de `_acionavel` (ver §Refutados 10–15 e §Observações): sem eles, 12/821 = 1,46%.

## REFUTADOS

### 1. `Essentials/settings-index.map.json` · `cobertura-das-chaves-de-configuracao`
- **Afirmação (acao, status `paridade`):** "Medido hoje: o protótipo tem 10 (`:573` `:574` `:579` `:580` `:585` `:586` `:587` `:588` `:593` `:594`), não 12 … 1:1 com as 10 chaves vivas".
- **O que o protótipo do PR diz:** `HEAD:prototipo-ui/cowork/Wagner/hrm-extras.jsx` tem **294 linhas** — as 10 linhas citadas estão **fora do arquivo**; o bloco de Configurações (HEAD `:262-276`) tem **5 campos** (2 Licenças + 2 Folha + 1 flag) e o próprio protótipo escreve (`:277`) *"Tolerância de marcação e localização obrigatória **saíram daqui**"*. Em `origin/main` (612 linhas) as 10 citações eram corretas (`:573`…`:594`).
- **Porquê é erro do lote:** o lote re-ancorou `prototipo.linhas` desta parte (573-594 → 264-276) e carimbou `gerado_em: 2026-09-29`, mas deixou 10 citações de linha stale e a afirmação "10 campos, paridade" que o range novo contradiz (5 × 10 chaves vivas → não é paridade).

### 2. `Ponto/banco-horas-show.map.json` · `ajuste-manual`
- **Afirmação:** "A soma local de saldo do protótipo (`ponto-telas.jsx:362`) é artefato de mock".
- **Código:** `origin/main:…ponto-telas.jsx:362` = `setSaldos((ss) => …saldo_minutos + m…)` ✓; `HEAD:…ponto-telas.jsx:362` = `const [obs, setObs] = useState("");` ✗ — o `setSaldos` está em **HEAD:383**.
- **Porquê:** citação de linha errada após o handoff que o lote diz ter re-ancorado (a parte teve `prototipo.linhas` atualizado, a prose não).

### 3. `Ponto/colaboradores-edit.map.json` · `configuracao-de-ponto-campos`
- **Afirmação:** "o help do PIS (`:677`) não existe no vivo".
- **Código:** `origin/main:…ponto-telas.jsx:677` = `<window.PtCampo label={"PIS"} help=…>` ✓; `HEAD:677` = `);` ✗ — o PIS está em **HEAD:705**.
- **Porquê:** citação stale, mesma classe de #2.

### 4. `Ponto/intercorrencias-index.map.json` · `detalhe-da-intercorrencia`
- **Afirmação (status `prototipo-corrige`):** "o drawer vira rota `Show`. O `window.confirm` do drawer (`ponto-telas.jsx:270`, `:273`) sai junto."
- **Código:** o lote re-ancorou a parte para `HEAD:258-304`, cujo bloco começa em `// ── Show ──` (o drawer JÁ virou Show no protótipo do PR); `HEAD:270` = `</>}` e `HEAD:273` = `</div>` — os `window.confirm` estão em **HEAD:269 e :272** (ainda existem). Em `origin/main` `:270`/`:273` continham os `window.confirm` ✓.
- **Porquê:** a linha citada não contém o que se afirma; e a parte da correção "vira Show" já está no range que o lote ancorou — o status/acao não foi relido.

### 5. `Ponto/relatorios-index.map.json` · `filtros-de-periodo-e-colaborador`
- **Afirmação:** "o campo Formato do wizard, que mostra sempre 'TXT' mesmo para PDF (`:885`)".
- **Código:** `origin/main:885` = `<window.PtCampo label={"Formato"} …>` ✓; `HEAD:885` = `);` ✗ — Formato está em **HEAD:925**.

### 6. `Ponto/relatorios-index.map.json` · `rodape-legal`
- **Afirmação:** "a copy vem do protótipo (`:928`)".
- **Código:** `origin/main:928` = `<Legal>Relatórios legais (AFD/AFDT/AEJ)…` ✓; `HEAD:928` = `{!alvo.disponivel &&` ✗ — a copy está em **HEAD:968**.

### 7. `Ponto/intercorrencias-index.map.json` · `form-embutido-de-nova-e-edicao`
- **Afirmação (status `prototipo-corrige`):** "Protótipo corrige (D-PONTO-DETALHE, R2)" — i.e., o form embutido deve virar página.
- **Código:** o lote re-ancorou a parte de `origin/main:298-301` (`{(nova || editando) && <Card … FormIntercorrencia …>` embutido) para `HEAD:237-248`, que é `// ── Create / Edit ── if (nova || editando) return (<> <div className="pt-sub"><VoltarBtn…>…<h2>…Nova intercorrência</h2>` — página própria com "Voltar". A correção que o status pede já está no range ancorado.
- **Porquê:** o map carimbado hoje afirma que o protótipo ainda precisa corrigir o que o próprio range novo mostra corrigido.

### 8. `Ponto/intercorrencias-create.map.json` · `cabecalho-da-pagina`
- **Afirmação (status `prototipo-corrige`):** "o form vira página com cabeçalho".
- **Código:** `HEAD:240-243` (range ancorado pelo lote) = `<div className="pt-sub"><VoltarBtn …>Voltar à lista</VoltarBtn><div><h2>…"Nova intercorrência"</h2>…` — já é página com cabeçalho. Em `origin/main:298-301` era o `<Card>` embutido.
- **Porquê:** mesma classe de #7.

### 9. `Ponto/importacoes-create.map.json` · `tela-propria-ou-card-inline`
- **Afirmação (status `vivo-a-frente`):** "Nada no vivo. `D-PONTO-DETALHE` = ROTA PRÓPRIA — quem muda é o protótipo (R2); é a thread 28."
- **Código:** `origin/main:799-823` = `{nova && <Card … "Upload do arquivo" …>` inline; `HEAD:822-852` (range ancorado pelo lote) = `if (nova) return (<> <div className="pt-sub"><Voltar …>Voltar às importações</Voltar><div><h2>Nova importação</h2>…` — o protótipo já mudou para tela própria.
- **Porquê:** o veredito "quem muda é o protótipo" está cumprido no range que o lote ancorou; o status deveria ter sido relido (paridade ou decidir), não carimbado como está.

### 10–13. `Ponto/configuracoes-index.map.json` · `regras-clt-e-reforma-trabalhista`, `banco-de-horas`, `rep-e-imutabilidade`, `afd-e-esocial`
- **Afirmação (diff do lote):** `_acionavel: false → true` em 4 partes cujo `status` é `paridade` e cujo `acao` é "Corrigido no vivo pelo #8078 (2026-09-28)… **Nada a fazer**; o que sobra é FORMA."
- **O que `origin/main` diz:** o map tinha `_acionavel: false` (commit `480b65661`, #8096, que também gravou no `configuracoes-index-gap.md:70-75` a tabela "paridade" para as 4 partes). A coluna **Ação** do gap (`:45-48`) ainda diz "**Corrigir o vivo**" — é dela que `gerar-map.mjs` deriva `ehAcionavel()` (`scripts/design/gerar-map.mjs:186-199`; regex de `gerar-contrato.mjs:46-50`: só `Nada|Nenhuma|Não…` dá false).
- **Porquê é erro do lote:** a célula derivada REABRE um veredito "Nada a fazer" registrado em prosa mais nova (map `acao` + tabela paridade do próprio gap). O gerador documenta exatamente esta armadilha (#7262: *"um gap stale reintroduz o valor velho a cada regeneração… Conserto é sempre no gap"*) — o lote regenerou sem aplicar o conserto documentado e commitou o artefato contraditório. Regen com `--atualizar` reproduz o `true` (é fiel ao gerador — G4 confirmado); o erro é de G3 (prosa vence).

### 14. `Ponto/importacoes-show.map.json` · `amostra-de-erros`
- **Afirmação:** `_acionavel: false → true` com `status: paridade`, `acao: "Feito 2026-09-29 — UC-IMPSH-06…"`.
- **Fonte:** `importacoes-show-gap.md:28` Ação = "**Incorporar** — D-IMP-EXTRAS…" (stale; o `Feito` veio no #8124 `c50ec8d6b`, que setou `false` à mão no map).
- **Porquê:** mesma classe de 10–13.

### 15. `Ponto/intercorrencias-create.map.json` · `anexo-de-comprovante`
- **Afirmação:** `_acionavel: false → true` com `status: paridade`, `acao: "Feito 2026-09-29 — UC-INTCRE-04…"`.
- **Fonte:** `intercorrencias-create-gap.md:28` Ação = "**Gap real (D-INTERC-ANEXO: INCORPORA).** Implementar…" (stale).
- **Porquê:** mesma classe de 10–13.

### 16. `Essentials/settings-index.map.json` · `chave-de-presenca-dentro-do-hrm` (G4)
- **Afirmação:** `prototipo: { arquivo: hrm-extras.jsx, linhas: "585-588, 593" }` — **inalterado** pelo lote enquanto todas as outras partes do mesmo arquivo foram re-ancoradas (573→264 etc.).
- **Código:** `HEAD:prototipo-ui/cowork/Wagner/hrm-extras.jsx` tem **294 linhas** → range inexistente. Em `origin/main` `:585-588` eram os 4 `grace_*` e `:593` o `is_location_required` — conteúdo que **saiu do arquivo** no handoff 43 (`git grep` em HEAD: as chaves só aparecem em `hrm-data.jsx` e no playbook; `_saida-09.md:37` *"As 5 chaves de presença foram aposentadas nas Configurações"*).
- **Porquê:** omissão de re-ancoragem (o formato `"585-588, 593"` não é um range simples e o lote não tratou); o caminho correto está no próprio lote, na parte irmã `permitir-marcacao-via-web` (`n/a` + `_nota`). `design-code-map-check.mjs` só verifica `existsSync(prototipo.arquivo)` (`:205-206`), não bounds — por isso passa.

### 17. `Governance/governance-dashboard.map.json` · `atalhos-de-governanca-documentos-canonicos` (G4)
- **Afirmação:** `prototipo.linhas: "388-440"` (era `389-442`).
- **Código:** `HEAD:prototipo-ui/cowork/Wagner/governance-page.jsx` tem **436 linhas** (`origin/main`: 438). Os dois ranges terminam depois do EOF; o lote escreveu um valor novo e ainda inválido.
- **Porquê:** range de âncora fora do arquivo, escrito pelo lote (pré-existente em forma diferente — não absolve o valor novo).

### 18. `Governance/governance-policies.map.json` · `estado-vazio-sem-regras` (G4)
- Idêntico a #17 (`388-440` em arquivo de 436 linhas).

## Observações não contadas

- **Governance `abas-do-shell` (4 maps, `paridade`) — CONFIRMADO, mas atenção:** o protótipo perdeu a aba "Notas dos módulos" (`origin/main:governance-page.jsx:24-30` 5 vistas → `HEAD:25-30` 4 vistas). O vivo não tem essa aba (`git grep -n 'Notas dos m' origin/main -- resources/js` → rc=1, 0 hits; controle positivo `git grep -c Drift … DriftAlerts.tsx` = 8) e a lista é derivada do `GovernancaSubNav` — paridade fica mais verdadeira, não menos.
- **Citações ±1 em `governance-page.jsx`** (`74-102`, `331-335`, `302-306`, `337-342` nas partes `regua-…`, `aviso-alternar…`, `busca-de-politicas…`): o handoff removeu 1 linha antes delas; os ranges ainda cobrem o conteúdo afirmado (`data-contract="politicas-aviso-historico"`, `filtradas = useMemo`, `gov-toolbar`). Não contado.
- **`Essentials/holidays-index` · `colunas-da-tabela` cita `:397` como `H.dias(a.ini,a.fim)`:** já estava errado em `origin/main` (`:397` vazio; `H.dias` em `:52,119,204,224,225`) — pré-existente, não é do lote.
- **`Essentials/settings-index` · `agrupamento-em-cards` `vivo.linhas: 63-180`** em `origin/main:resources/js/Pages/Essentials/Settings/Index.tsx` de **160 linhas** — lado vivo, intocado pelo lote; drift pré-existente.
- **Vivo `abas-do-shell` aponta linhas fracas** (`Audit.tsx:78` é comentário; `Policies.tsx:70` é `),`) — lado vivo, pré-existente.
- **`Essentials/Settings/Index`:** o charter declara `related_prototype: n/a (herda PT-02)` e o map ancora `hrm-extras.jsx` via frontmatter do gap; `gerar-map` avisa (*"âncora computada do charter não cita hrm-extras.jsx"*). Pré-existente.
- **`_nota` nova em `permitir-marcacao-via-web`** ("a nota sobre allow_users_for_attendance_from_web saiu do card Regras… tolerância e localização são do Ponto") — **confirmada** contra `HEAD:hrm-extras.jsx:277`.
- **`_acionavel` tem consumidor zero por máquina** (docblock de `gerar-map.mjs:192-198`: nem `design-code-map-check` nem `consumir-map` o leem). Contei os 6 flips porque o mandato é explícito (célula derivada que reabre "Nada a fazer") e o conserto é barato e documentado (coluna Ação dos 3 gap.md → começar por "Nada — …"). Se [W] classificar o campo como decorativo, o lote fica em 12/821 = 1,46% — registro o fork para não esconder que o veredito depende disso.
- **`intercorrencias-index` · `barra-superior-e-acao-primaria` e `lista-de-intercorrencias`** (`prototipo-corrige`) — confirmados: a lista em HEAD ainda tem coluna "Prioridade" e não tem "Criada" (`HEAD:324`), e o botão usa `onClick={onNovo}` (prop do pai) — a correção pedida ainda não está no protótipo.
- **`requisitos-status.mjs Essentials|Governance --check` rc=1** por `_STATUS-GENERATED.md` inexistente — também inexistente em `origin/main` (`git ls-tree` só devolve o de Ponto). Pré-existente e fora do lote; não contado em G5.

## Scan PII (linhas `+` do diff em `memory/requisitos`: 165)

| Padrão | Hits | Controle positivo |
|---|---|---|
| CPF pontuado | 0 | casou |
| CPF cru (11 dígitos isolados) | 0 | casou |
| CNPJ | 0 | casou |
| Telefone BR formatado | 0 | casou |
| Telefone cru (10–11 dígitos) | 0 | casou |
| E-mail | 0 | casou |
| Valor em reais (símbolo seguido de dígito; regex montada por `String.fromCharCode`, sem literal) | 0 | casou |
| Nomes de cliente CRM (`cliente|contact|razão social` nas linhas `+`) | 0 | — |

**pii_hits = 0 · controles 7/7.**

## Máquina derivada (rc literais)

| Comando | rc |
|---|---|
| `node scripts/governance/design-code-map-check.mjs --check --strict` | **0** (82/82 telas com map; STALE só em Compras/KB/Produto/RecurringBilling por `gerado_em` ausente no gap — fora do lote) |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | **0** |
| `node scripts/governance/plans-index.mjs --check` | **0** |
| `node scripts/governance/requisitos-status.mjs Ponto --check` | **0** |
| `node scripts/design/gerar-map.mjs <gap> --atualizar` × 25 → diff chave a chave vs HEAD | **0** diferenças em 25/25 (rc=0 em todos) |
| `node scripts/design/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork/Wagner` × 25 | rc=0 em 25/25 (24 `âncora ✓`, 1 `n/a` declarado) |

## Comandos reproduzíveis

```bash
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos            # 25 M
git ls-tree origin/main -- memory/requisitos/__nao_existe__.map.json       # controle negativo (vazio)
git ls-tree origin/main -- memory/requisitos/Ponto/espelho-show.map.json   # controle positivo (blob)
# bounds dos ranges de protótipo em HEAD
git show HEAD:prototipo-ui/cowork/Wagner/hrm-extras.jsx | wc -l           # 293/294 (< 585)
git show HEAD:prototipo-ui/cowork/Wagner/governance-page.jsx | wc -l      # 436 (< 440)
# citações stale (exemplos)
git show origin/main:prototipo-ui/cowork/Wagner/ponto-telas.jsx | sed -n '362p;677p;885p;928p;270p;273p'
git show HEAD:prototipo-ui/cowork/Wagner/ponto-telas.jsx | sed -n '362p;677p;885p;928p;270p;273p'
git show HEAD:prototipo-ui/cowork/Wagner/ponto-telas.jsx | grep -nE 'window\.confirm|── Show ──|── Create / Edit ──|setSaldos\(\(ss\)|label=\{"PIS"\}|label=\{"Formato"\}|<Legal>Relat'
# _acionavel: fonte
git show origin/main:memory/requisitos/Ponto/configuracoes-index-gap.md | sed -n '42,48p;70,75p'
sed -n '186,199p' scripts/design/gerar-map.mjs; sed -n '46,50p' scripts/design/gerar-contrato.mjs
git log -1 --format=%h -S'"_acionavel": false' origin/main -- memory/requisitos/Ponto/configuracoes-index.map.json   # 480b65661
# regen + máquinas
for f in $(git diff --name-only origin/main...HEAD -- memory/requisitos); do node scripts/design/gerar-map.mjs "${f%.map.json}-gap.md" --atualizar > /tmp/regen/$(basename $f); done
node scripts/governance/design-code-map-check.mjs --check --strict; echo rc=$?
git grep -n 'Notas dos m' origin/main -- resources/js; echo rc=$?          # rc=1 (0 hits)
```

```json
{"itens_verificados": 821, "erros_confirmados": 18, "error_rate_pct": 2.19, "pii_hits": 0, "veredito": "reprovado"}
```
