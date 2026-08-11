---
slug: runbook-governance-gate-ci
title: "RUNBOOK — Governance Gate CI (pre-merge)"
type: runbook
authority: canonical
lifecycle: ativo
owner: W
last_updated: "2026-08-11"
last_validated: "2026-08-11"
related_workflow:
  - .github/workflows/governance-gate.yml
  - .github/workflows/module-grades-gate.yml
related_script: .github/scripts/pii-scan.sh
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0095-skills-tiers-convencao-interna
  - 0130-handoff-append-only-mcp-first
  - 0155-rubrica-module-grade-v3
  - 0160-scoped-scorecards
charter_adr: 0094
pii: false
---

# RUNBOOK — Governance Gate CI

Workflow `.github/workflows/governance-gate.yml` é o **Mecanismo #2 ENFORCEMENT** prescrito pela Constituição v1.1.0 Art. 8 (Policy Gating) e detalhado em [ENFORCEMENT.md §2 #2](../../governance/ENFORCEMENT.md). Bloqueia merge de PR que toque camadas críticas sem artefatos obrigatórios.

Time MCP entra em breve. Sem CI gate, drift escapa quando hook local foi pulado/ignorado.

## §0. Recibo de validação (o que sustenta o `last_validated`)

> `last_validated` é **medição datada**, não afirmação atemporal (proibições §5 2026-07-17).
> A tabela abaixo diz **o que rodou e deu**, e a lista seguinte diz **o que NÃO foi medido** —
> sem isso o carimbo viraria campo auto-declarado (§5 2026-07-01/07-09).
> **Se a data incomodar, re-rode os comandos; não edite o número.**

**Rodado em 2026-08-11, Windows/Git Bash, worktree limpa, base `3ccee85b6bf`:**

| § | Comando | Esperado | Deu |
|---|---|---|---|
| §4 · §7B | `pii-scan.sh -v` com CPF fake literal | exit 1 | ✅ exit 1 · `PII detectada (1 ocorrência(s) CPF/CNPJ literal).` |
| §4 · §7B | idem + `// pii-allowlist` na mesma linha | exit 0 | ✅ exit 0 · `[allowlist]` |
| §1 Job 4 | `merge-marker-scan.sh -v` em arquivo limpo | exit 0 | ✅ exit 0 |
| §1 Job 4 | idem em fixture com `<<<<<<<`/`>>>>>>>` (bite-test) | exit 1 | ✅ exit 1 · 2 linhas (o `=======` nu **não** conta, por desenho) |
| §1 Job 5 | `blade-migration-census.mjs --ratchet` | exit 0 em árvore limpa | ✅ exit 0 · `471 endpoints em Blade` |
| §6 | parse YAML de `Modules/Vestuario/SCOPE.md` | parseia | ✅ com `python` (ver pegadinha em §6) |
| §11 | `git diff origin/main...HEAD -- 'Modules/*/module.json'` | vazio | ✅ vazio |

**NÃO validado nesta passada** (declarado, não escondido):

- §3 (editar Constituição) e §5 (reverter ADR/handoff) — exigiriam violar append-only de propósito num PR real.
- §7 **Opção A** (`act`) — não instalado nesta máquina.
- §11 `jq` — **ausente** nesta máquina (ver §11 edge cases); o passo do bucket roda no runner, não aqui.
- §8/§9/§10 — prosa e tabelas de contexto, não têm comando.

## §1. Jobs

> **5 jobs** em [`governance-gate.yml`](../../../.github/workflows/governance-gate.yml) — `block-adr-edits`,
> `scope-md-drift`, `pii-scan`, `merge-marker-scan`, `blade-migration-ratchet`. O §11 documenta um **6º**
> job que mora em outro workflow (`module-grades-gate.yml`), não neste.
>
> ⚠️ O gate de schema deste próprio RUNBOOK (`RUNBOOK (memory/requisitos/**/RUNBOOK*.md)`) é **advisory** —
> medido em 2026-08-11 contra a união `classic_protection ∪ rulesets` de
> [`required-checks-baseline.json`](../../../governance/required-checks-baseline.json) (43 contexts): ele não
> está lá. Dos jobs abaixo, só **`pii-scan`** é required.

### Job 1 — `block-adr-edits` (HARD — bloqueia merge)

| Sub-regra | O que verifica | Falha se |
|---|---|---|
| ADR canon append-only | `git diff --name-status` em `memory/decisions/NNNN-*.md` | Status `M` ou `R*` em ADR existente |
| Handoff append-only | Idem em `memory/handoffs/*.md` ([ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md)) | Status `M` em handoff existente |
| CONSTITUTION cascade | `memory/governance/CONSTITUTION.md` editada | Falta label `constitution-amendment` OU falta `audit-*.md` novo no mesmo PR |

Mensagens em PT-BR com instruções de resolução (ver shell script no workflow).

### Job 2 — `scope-md-drift` (WARN — só comment, não falha)

Detecta Controllers novos (`A` em `Modules/<X>/Http/Controllers/*Controller.php`) e verifica se aparecem em `Modules/<X>/SCOPE.md.contains[]`. Posta comment na PR com lista de drifts e como resolver.

Complementar ao `scope-guard.yml` (que já roda strict pro mesmo cenário) — este job entrega mensagem humana detalhada quando o strict falha.

### Job 3 — `pii-scan` (HARD — bloqueia merge)

Roda `.github/scripts/pii-scan.sh` nos arquivos AM (Added/Modified) do PR.

- **CPF regex:** `[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}` (padrão XXX.XXX.XXX-XX)
- **CNPJ regex:** `[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}`
- **Exclui:** `vendor/`, `node_modules/`, `public/`, `storage/`, `bootstrap/cache/`, `*.lock`, binários
- **Auto-redact no log** — número detectado vira `[REDACTED-CPF]`/`[REDACTED-CNPJ]` antes de imprimir no log público do GH Action (evita re-vazamento)

Além do marker inline, existe **allowlist EXTERNA** [`.github/pii-scan-allowlist.txt`](../../../.github/pii-scan-allowlist.txt) — ver §4.

### Job 4 — `merge-marker-scan` (falha o job com exit 1)

Roda [`.github/scripts/merge-marker-scan.sh`](../../../.github/scripts/merge-marker-scan.sh) nos arquivos AM do PR. Detecta **marcador de conflito de merge commitado**.

- **Detecta:** linhas que COMEÇAM com `<<<<<<< ` ou `>>>>>>> ` (7 chars + espaço)
- **NÃO usa o separador nu `=======` como sinal** — colide com underline de heading setext (Markdown/RST) e daria falso-positivo. Todo conflito real tem início E fim, então os dois marcadores bastam. _(Medido no bite-test do §7: fixture com os 3 marcadores acusa **2** linhas, não 3.)_
- **Exclui:** `vendor/`, `node_modules/`, `public/`, `storage/`, `bootstrap/cache/`, `.git/` + binários/fontes
- **Fixtures allowlisted** (contêm marcadores por design, via `SKIP_FILE_REGEX`): `.claude/hooks/block-merge-markers.test.mjs` e o próprio `merge-marker-scan.sh`

**Por que existe:** complementa o hook runtime `.claude/hooks/block-merge-markers.mjs` (PreToolUse) — o hook barra no Write/Edit local, este pega o que entrou por caminho que não passou pelo hook (merge manual, cherry-pick, edição fora do agente). Origem 2026-07-02: 11 CHANGELOG/README de módulos entraram em `origin/main` com marcadores commitados; nenhum gate os pegava (corrigido no PR #3660).

### Job 5 — `blade-migration-ratchet` (advisory — `continue-on-error: true` no workflow)

Roda `node scripts/governance/blade-migration-census.mjs --ratchet`. É a trava **pré-merge** da migração Blade→React ([ADR 0277](../../decisions/0277-rota-migracao-blade-ondas-completude.md), peça 3/3).

- **Catraca só-desce:** reprova apenas **SUBIDA** de endpoints Blade por escopo. Descer, ficar igual, ou o escopo sumir do censo **passam**
- **Derivado da árvore**, nunca digitado à mão — é análise **estática**: afirma "este endpoint declarado resolve para um método que renderiza Blade", não "esta rota responde". Divergências ficam como `indeterminado` no `--report`, nunca silenciadas
- **Estado medido em 2026-08-11:** `471 endpoints em Blade`, catraca verde

**Por que advisory:** nasce advisory por [ADR 0275](../../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md); promoção exige mordida provada ([ADR 0336](../../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)). Fica **vermelho e visível** sem bloquear.

> **Fronteira (não duplica régua):** `screen-coverage-map` mede charter/e2e/scorecard por TELA; `casos-coverage-guard` mede UC↔teste; `module-surface` inventaria arquivos. Este mede **endpoint serve Blade ou Inertia?** — o eixo que nenhum outro mede.

## §2. Como fazer PR válida (workflow padrão)

```
1. nova branch: git checkout -b claude/<slug>
2. mexer (Edit/Write)
3. git add <arquivos específicos>           # NUNCA git add -A
4. git commit -m "feat(...): ... [W+C]"     # Refs: ADR-NNNN se aplicável
5. git push -u origin claude/<slug>
6. gh pr create --title ... --body ...
7. CI verde (todos jobs governance-gate ok) + review Wagner
8. gh pr merge --squash
```

## §3. Como editar Constituição (Art. 10 §10.4 Cascade Review)

```
1. Criar ADR formal: memory/decisions/NNNN-constitution-amendment-vX.Y.md
2. Editar CONSTITUTION.md:
   - bump version no frontmatter
   - adicionar entry em amendments[]
   - editar conteúdo do artigo
3. Criar audit cascade: memory/governance/audit-YYYY-MM-DD-vX.Y.md
   (documenta revisão das camadas L2-L7 impactadas)
4. PR contém OS 3 arquivos (ADR + CONSTITUTION + audit)
5. Aplicar label 'constitution-amendment' na PR via GH UI
6. CI governance-gate Job 1 verifica:
   ✅ label presente
   ✅ audit-*.md novo no mesmo PR
7. Wagner aprova + merge
```

**Exemplo histórico:** v1.0.0 → v1.1.0 em 2026-05-05 — ver [`audit-2026-05-05-v1.1.md`](../../governance/audit-2026-05-05-v1.1.md).

## §4. Override pii-scan (falso-positivo)

Cenário: Pest factory de teste, fixture, doc explicando formato CPF/CNPJ.

**Solução:** adicionar comment `# pii-allowlist` na MESMA linha:

```php
$payload = ['cpf' => '000.000.000-00']; // pii-allowlist (Pest factory placeholder)
```

```ts
const cpfMock = '111.222.333-44'; // pii-allowlist (Storybook fixture)
```

Linhas com `pii-allowlist` são ignoradas pelo scanner. Use SOMENTE pra PII fictícia/placeholder — NUNCA pra PII real "transitória".

### Quando o marker inline NÃO serve — allowlist externa

Há dois casos em que colar `pii-allowlist` na linha é errado, e existe a allowlist
externa [`.github/pii-scan-allowlist.txt`](../../../.github/pii-scan-allowlist.txt) pra eles:

| Caso | Por que o inline não serve |
|---|---|
| **Arquivo append-only** (ADR canon, handoff) | editar a linha pra inserir o marker viola o Job 1 |
| **A linha é um EXEMPLO cujo sentido depende de NÃO ter o marker** | o marker muda o que o exemplo demonstra |

O 2º caso é o **deste próprio RUNBOOK**: o §7 Opção B tem um par de exemplos —
um sem marker (deve falhar, exit 1) e um com marker (deve passar, exit 0). Colar
`pii-allowlist` no primeiro tornaria os dois **idênticos**, e a documentação passaria
a mentir sobre o que ela demonstra. É a mesma armadilha do `.gitleaksignore` que
reproduzia o padrão que explicava (proibições §5 2026-08-05).

**Formato** (1 por linha, `#` = comentário):

```
caminho/relativo/do/arquivo.md|literal-fake-exato
```

O casamento é **`path` + `literal` juntos** — não isenta o arquivo inteiro. Qualquer
outro CPF/CNPJ no mesmo arquivo continua sendo pego (comprovado por controle negativo
no §7 Opção B).

> ⚠️ **Regra dura, idêntica à do inline:** só PII **sintética/fake**. CPF/CNPJ real
> JAMAIS entra na allowlist — real se redige na fonte (`[REDACTED]`, faker, `PiiRedactor`).
>
> 📌 **Escopo:** o cabeçalho do arquivo nasceu descrevendo o caso 1 ("uso EXCLUSIVO pra
> arquivo append-only"). O caso 2 foi acrescentado em 2026-08-11 — a mecânica é a mesma
> (`path|literal`), o que mudou é a justificativa aceita.

## §5. Como reverter ADR/handoff editado por engano

Se PR for bloqueada pelo Job 1 por ter editado ADR/handoff existente:

```bash
# Reverter o arquivo pra versão de origin/main
git checkout origin/main -- memory/decisions/NNNN-arquivo-em-questao.md

# OU criar ADR nova superseding (caso a edição era válida):
cp memory/decisions/NNNN-antiga.md memory/decisions/MMMM-nova-slug.md
# editar MMMM com frontmatter 'supersedes: [NNNN]' + lifecycle: ativo
# editar NNNN apenas pra mudar lifecycle: superseded (PATCH permitido,
#   ainda assim via PR SEPARADA pra rastreabilidade)

git add memory/decisions/MMMM-nova-slug.md
git commit -m "feat(adr): supersede NNNN com nova MMMM [W+C]"
```

## §6. Troubleshooting

### "constitution-amendment label faltando" mas adicionei

GH Actions usa snapshot do PR no momento do trigger. Após adicionar label:

```bash
# Force re-run do workflow
gh workflow run governance-gate.yml --ref claude/<slug>
# OU empurra commit vazio
git commit --allow-empty -m "ci: re-trigger governance-gate"
git push
```

### "Job pii-scan timeout 5min"

Improvável (regex simples), mas se acontecer:
- Provável regex regression em alguma extensão binária não-filtrada
- Adicionar extensão ao `SKIP_EXT_REGEX` em `.github/scripts/pii-scan.sh`
- Abrir PR de fix do script primeiro, depois retomar PR principal

### "Job scope-md-drift falhou em parse YAML"

Frontmatter de algum SCOPE.md está mal-formado. Validar:

```bash
python3 -c "import yaml; yaml.safe_load(open('Modules/<X>/SCOPE.md', encoding='utf-8').read().split('---')[1])"
```

> ⚠️ **Windows:** `python3` costuma resolver pro *stub* da Microsoft Store — ele imprime
> "Python não foi encontrado" e sai **rc=49**, o que se lê como "Python ausente" sem ser.
> O binário real é `python`. Confira com `command -v python3` / `command -v python` antes
> de concluir ausência (medido 2026-08-11). No runner `ubuntu-latest`, `python3` é o certo.

## §7. Testando localmente antes de PR

### Opção A — `act` (GitHub Actions runner local)

```bash
# Instalar: https://github.com/nektos/act
act pull_request -W .github/workflows/governance-gate.yml \
  --container-architecture linux/amd64 \
  -e .github/test-events/pr.json
```

Limitações: `gh api` chamadas pra labels precisam mock; `act` não cobre permissions PR write 100%.

### Opção B — Smoke script-only

```bash
# Testar pii-scan.sh local em arquivos específicos
bash .github/scripts/pii-scan.sh -v path/to/file.php

# Testar com PII literal (deve falhar exit 1)
echo "\$cpf = '123.456.789-00';" > /tmp/pii-test.php
bash .github/scripts/pii-scan.sh -v /tmp/pii-test.php
# Esperado: exit 1 + "::error::PII detectada (1 ocorrência(s) CPF/CNPJ literal)."

# Testar allowlist (deve passar exit 0)
echo "\$cpf = '123.456.789-00'; // pii-allowlist" > /tmp/pii-test2.php
bash .github/scripts/pii-scan.sh -v /tmp/pii-test2.php
# Esperado: exit 0 + "[allowlist] ..."
```

> Escreva os dois casos em arquivos **diferentes** (`pii-test.php` / `pii-test2.php`):
> sobrescrever o mesmo path deixa o 2º resultado indistinguível de "o 1º nunca rodou".

**Controle negativo da allowlist externa** — prova que a entrada `path|literal` NÃO
isenta o arquivo inteiro (rodado 2026-08-11):

```bash
# 1) arquivo COM entrada na allowlist externa → exit 0
bash .github/scripts/pii-scan.sh -v memory/requisitos/Infra/RUNBOOK-governance-gate-ci.md
# Deu: exit 0 · o -v lista cada linha isenta como [allowlist] / [allowlist-externa].
# (Não fixamos a CONTAGEM aqui: ela muda a cada edição do RUNBOOK — quem quiser o
#  número roda `... -v <arquivo> | grep -c '^\[allowlist'`.)

# 2) MESMO arquivo, CPF fake DIFERENTE → ainda deve falhar.
#    O literal é MONTADO EM DUAS PARTES de propósito: escrito inteiro nesta linha,
#    ele seria detectado no próprio RUNBOOK — e ele NÃO está na allowlist (que só
#    cobre 123.456.789-00). Isso é a demonstração, não um contorno.
RUNBOOK=memory/requisitos/Infra/RUNBOOK-governance-gate-ci.md
FAKE="987.654.321"; FAKE="${FAKE}-00"          # sozinha, a 1ª metade não casa o regex
echo "CONTROLE-NEGATIVO-TEMP $FAKE" >> "$RUNBOOK"
bash .github/scripts/pii-scan.sh -v "$RUNBOOK"
# Deu: exit 1 · "PII detectada (1 ocorrência(s))"
# → REMOVER a linha temporária depois (ela não pode ser commitada)
```

Sem o passo (2), o exit 0 do passo (1) seria indistinguível de "isentei o arquivo inteiro".

> 🔁 Este bloco é auto-demonstrativo: ele **não pode** conter o 2º CPF por extenso,
> porque o gate que ele documenta o pegaria. Documentar um scanner reproduzindo o
> padrão que ele procura é a armadilha do `.gitleaksignore` (proibições §5 2026-08-05).

### Opção B-2 — Smoke merge-marker-scan (Job 4)

```bash
# Arquivo limpo (deve passar exit 0)
bash .github/scripts/merge-marker-scan.sh -v README.md
# Esperado: exit 0 + "[merge-marker-scan] ✅ Nenhum marcador de conflito"

# Bite-test: fixture com marcadores (deve falhar exit 1)
printf '%s\n' '<<<<<<< HEAD' 'a' '=======' 'b' '>>>>>>> outra' > /tmp/mm-test.md
bash .github/scripts/merge-marker-scan.sh -v /tmp/mm-test.md
# Esperado: exit 1 + "Marcador de conflito de merge commitado (2 linha(s))."
#           ⚠️ DOIS, não três — o `=======` nu não é sinal (ver Job 4).
```

### Opção B-3 — Smoke catraca Blade→React (Job 5)

```bash
node scripts/governance/blade-migration-census.mjs --ratchet
# Esperado em árvore limpa: exit 0 + "✅ catraca Blade→React OK — nenhum escopo subiu"

node scripts/governance/blade-migration-census.mjs --report   # o censo por escopo
node scripts/governance/blade-migration-census.mjs --selftest  # núcleo determinístico
```

### Opção C — Push pra branch experimental

PR draft contra `main` aciona o gate sem precisar merge — vê resultado direto.

## §8. Sugestão evolução

| Mecanismo | Status | Próximo passo |
|---|---|---|
| #2 Pre-merge gate (este) | ✅ implementado | calibrar 4 semanas; converter Job 2 pra strict se sinal estável |
| #6 Mutation testing | ⏸️ Fase 5 | Pest tests gerados de `mcp_governance_rules` |
| #8 Public audit dashboard | ⏸️ Fase 5 | UI `/governance/audit` ([ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md)) |

## §9. Edge cases conhecidos

| Cenário | Comportamento esperado | Justificativa |
|---|---|---|
| `git mv memory/decisions/0010-old.md memory/decisions/0010-new.md` | Job 1 falha (status `R*`) | Rename de ADR = mudança de slug = potencial edit. Forçar nova ADR superseding. |
| ADR NNNN duplicado (2 arquivos com mesmo NNNN, status A em ambos) | Job 1 passa (não detecta) | TODO futuro: validar unicidade numérica via `adr-lint.yml` (já existe — schema valida frontmatter, não unicidade slug NNNN cross-files). |
| CONSTITUTION ratificada num PR + audit no PR seguinte | Job 1 falha | Cascade DEVE estar no mesmo PR (§10.4). Se split intencional, label fica em ambos PRs e Wagner override manual via `gh pr merge --admin`. |
| pii-scan pega CPF/CNPJ em `memory/sessions/*.md` | Falha | Session logs DEVEM redactar PII (skill `commit-discipline`). Use placeholder `[REDACTED-CPF]` ou faker. |

## §10. Quando hook local diverge do CI

Hook local pode ser pulado (`git commit --no-verify`) ou estar desatualizado. CI é a **fonte de verdade**. Wagner regra 2026-05-15:

> "vao entrar os outros no MCP e isso vai ficar uma zona caralho"

Time inteiro pode forçar `--no-verify` localmente; CI bloqueia no merge. Branch protection em `main` (configurada via UI GitHub) marca este workflow como **required check** — sem isso, drift escapa.

Ver [RUNBOOK-branch-protection.md](RUNBOOK-branch-protection.md) pra setup branch protection.

## §11. Bucket Change Detection (Wave 20 — ADR 0160)

> Workflow: `.github/workflows/module-grades-gate.yml` (job `bucket-change-detection`).
> Implementado em 2026-05-16 (Wave 20, agent B). Anti-gaming Scoped Scorecards.

### O que detecta

Diff em `governance.bucket` dentro de qualquer `Modules/*/module.json` entre `base_ref` (geralmente `main`) e `HEAD` do PR. Compara via `jq -r '.governance.bucket // empty'` entre as duas versões.

### Por que existe

ADR 0160 introduziu **Scoped Scorecards** — cada módulo é classificado num bucket (`vertical-deep`, `shared-infra`, `compliance-core`, etc) e a rubrica `module-grade` aplica dimensões D1-D9 com **pesos diferentes por bucket**. Anti-gaming: trocar bucket sem aprovação Wagner pode fazer a nota subir artificialmente sem melhoria real do módulo.

**Exemplo do vetor de gaming bloqueado:** módulo `vertical-deep` ganha nota 65/100 (pesado em D9 — profundidade vertical). Dev troca pra `shared-infra` (D9 desaparece, peso vai pra D3 — testes), nota sobe pra 78/100 sem 1 linha de código novo. CI agora bloqueia.

### Quando aplicar label `bucket-change-approved`

| Cenário | Aplicar label? | Justificativa adicional |
|---|---|---|
| Módulo nasceu com bucket placeholder, agora classificou corretamente | ✅ sim | Comentar bucket→bucket no PR + linkar ADR 0160 §classificação |
| Módulo amadureceu de `vertical-deep` pra `shared-infra` (Repair virou genérico) | ✅ sim | Exige ADR per-PR explicando shift de arquitetura |
| Refactor não toca bucket mas `module.json` foi reescrito + bucket equivalente | ❌ não | `governance.bucket` mantém valor → diff vazio → gate passa silencioso |
| Dev quer "subir nota fácil" trocando bucket sem aprovação | ❌ jamais | Gate bloqueia. Wagner não aplica label. PR fecha. |

### Como aplicar a label

```bash
# Wagner com permissão write no repo:
gh pr edit <PR_NUMBER> --add-label "bucket-change-approved"

# OU via UI GitHub: PR page → Labels (sidebar direita) → marcar bucket-change-approved
```

### Override de emergência

Cenário hipotético: bucket reclassificado em produção fora-de-hora (incident), Wagner indisponível, time precisa mergear.

Comentar no PR:

```
/bypass-bucket-gate <razão técnica clara>
```

Convenção: PR mergeado via bypass **DEVE** gerar ADR follow-up `lifecycle: historical` em `memory/decisions/NNNN-bucket-bypass-<modulo>.md` no PR seguinte, explicando o bucket assumido + revisão pós-incident. Bypass sem ADR follow-up é débito Tier 0 que aparece no audit semanal Wagner.

> ⚠️ O bypass por enquanto é **honor system** (comentário humano, sem enforcement automático). Próxima evolução: action workflow lê comments e injeta label `bucket-change-approved` quando detecta `/bypass-bucket-gate` (TODO Fase 4 governance).

### Setup inicial — criar a label no repo

Label precisa existir no repositório GitHub. Comando one-shot Wagner roda manualmente (Tier 0 — Claude não modifica labels GitHub sem aprovação):

```bash
gh label create "bucket-change-approved" \
  --description "Mudança de governance.bucket em module.json aprovada por Wagner (ADR 0160 — Wave 20)" \
  --color "0E8A16"
```

Cor `0E8A16` = verde GitHub (label de aprovação). Se já existir, comando retorna erro idempotente — ignorar.

### Testando localmente antes de PR

```bash
# Confirmar diff no module.json antes de push
git diff origin/main...HEAD -- 'Modules/*/module.json'

# Extrair bucket atual do módulo X
jq -r '.governance.bucket // empty' Modules/Vestuario/module.json

# Simular o gate (rodando os comandos do step manualmente)
for f in $(git diff --name-only --diff-filter=AM origin/main...HEAD -- 'Modules/*/module.json'); do
  base=$(git show "origin/main:$f" 2>/dev/null | jq -r '.governance.bucket // empty')
  head=$(jq -r '.governance.bucket // empty' "$f")
  [ "$base" != "$head" ] && echo "BUCKET CHANGED: $f ($base → $head)"
done
```

### Edge cases conhecidos

| Cenário | Comportamento esperado |
|---|---|
| `module.json` novo (módulo recém-criado) sem `governance.bucket` na base | `base_bucket` = vazio, `head_bucket` = vazio → diff falso, gate passa. Quando Wave 20 agent A definir bucket no mesmo PR, diff aparece e exige label. |
| `git show "origin/main:$file"` falha (arquivo novo) | `2>/dev/null` suprime, `base_bucket` = vazio → comparado normalmente. |
| `jq` ausente no runner | GitHub Actions `ubuntu-latest` tem `jq` pré-instalado. **Local Windows não tem — e git-bash NÃO traz `jq` junto** (medido 2026-08-11: `command -v jq` → ausente no Git Bash). Use WSL, instale `jq`, ou troque por `node -e "console.log(require('./Modules/X/module.json').governance?.bucket ?? '')"`. |
| PR rebase com base nova após bucket aprovado | Label persiste; gate reavaliará o diff vs nova base_ref. Se bucket continua diferente da base atual, label segue válida. |
| Mudança de `governance.bucket` + outras chaves no mesmo PR | Gate só olha bucket. Outras chaves passam pelo gate de regressão (job `module-grades-gate`). |
