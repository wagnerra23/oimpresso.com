---
date: "2026-08-12"
topic: "PR 5675 — refutação adversarial do lote PiiRedactor→app/Support (GT-G5)"
type: session
status: closed
module: governance
pr: 5675
verdict: aprovado
---

# PR #5675 — refutação adversarial do lote (protocolo GT-G5)

## TL;DR

Lote da mudança `Modules/Jana/Services/Privacy/PiiRedactor.php` → `app/Support/Privacy/PiiRedactor.php` (152 arquivos, 17 em `memory/requisitos/`). A mecânica do move está **certa e completa no eixo código** (classe existe, namespace certo, zero referência viva órfã, consumidores migrados, SUPERFICIE confirmado por oráculo, selftests verdes, PII scan limpo). Mas o lote **REPROVA por 3 erros em 24 itens = 12,5%** — todos da mesma família: **reescrever registro DATADO como se fosse doc vivo**, fabricando afirmações falsas em qualquer leitura temporal. Um 4º erro da mesma família vive fora dos 17 (lápide append-only Tier 0 do `licoes-rejeitadas.md`) e agrava o veredito.

```
itens_verificados: 24
erros_confirmados: 3
error_rate_pct: 12.5
pii_scan: true
pii_hits: 0
veredito: reprovado
```

## Independência (§2.2 e §6 do protocolo)

- [x] Sessão fresca — zero contexto do gerador; branch avaliada como estava em disco (`6a0fb84dce`), base `origin/main` (`86a4adce51`), merge-base `438e20e65c`.
- [x] Refutador: `fable-5` (tier máximo da escala).
- [x] Amostra: **100%** dos itens (categoria `anchors` — 24 linhas alteradas nos 17 docs de `memory/requisitos/`), sem seed (não houve amostragem).
- [x] Toda evidência colhida do git/filesystem/oráculos, nunca do texto do PR.
- [x] Não consertei nada, não commitei, não abri PR.

## O que foi verificado (evidência do código real)

### Mudança-mãe (pré-condição de todos os itens)

| Prova | Comando | Resultado |
|---|---|---|
| Path novo existe na branch | `git ls-tree claude/piiredactor-para-app-support -- app/Support/Privacy/PiiRedactor.php` | blob `3e150d7d447` ✓ |
| Namespace correto | `git show <branch>:app/Support/Privacy/PiiRedactor.php` | `namespace App\Support\Privacy;` (única linha alterada no rename, similarity 99%) ✓ |
| Path antigo saiu da árvore | `git ls-tree <branch> -- Modules/Jana/Services/Privacy/` | só `RetentionPurgeService.php` ✓ |
| Autoload cobre | `composer.json:84` | `"App\\": "app/"` ✓ |
| FQCN novo em uso (controle positivo) | `git grep -F -f pat_new.txt --name-only <branch>` | **135 arquivos** ✓ |
| FQCN antigo órfão em código | `git grep -F -f pat_old.txt --name-only <branch>` | **1 hit**, e é fóssil datado (`memory/sessions/2026-08-12-arte-shared-kernel-laravel.md`) ✓ |
| Path antigo (forma slash) restante | `git grep` na branch | só fósseis (`memory/handoffs/`, `memory/sessions/`, `memory/research/`) + fixture sintética do selftest `test-lane-coverage.mjs:486` (`PiiRedactorNumeroCru.php`, path sintético que também não existia no main) ✓ |
| Main não criou consumidor novo do FQCN antigo pós merge-base | `git diff 438e20e..origin/main \| grep '^+.*Modules.Jana.Services.Privacy.PiiRedactor'` | rc=1, zero ✓ (branch 4↔4 commits vs main) |

⚠️ Nota de método: a primeira rodada de grep (BRE com escaping inline) devolveu **zero** pro padrão NOVO que eu sabia existir — controle positivo pegou o vício (§5 2026-08-01); refeito com `-F -f <pattern-file>`.

### Consumidores reais conferidos no disco da branch

- `Modules/Governance/Http/Middleware/ActionGate.php:10,119,124` — `use App\Support\Privacy\PiiRedactor;` + `class_exists(PiiRedactor::class)` **fail-open preservado** ✓
- `Modules/ComunicacaoVisual/Services/OrcamentoCalculator.php:6,70` — use novo + `app(PiiRedactor::class)->redact(...)` ✓ (bate com o code-block citado em `PII-LGPD.md §2`)
- `Modules/Forja/Services/ForjaAuditService.php:8,55` — use novo + injeção por construtor ✓
- `Modules/Jana/Services/Telemetry/LangfuseClient.php` — existe (ref viva do SPEC) ✓
- `.github/workflows/jana-logica-pura-pest.yml:42` — lane paths atualizado pro path novo ✓
- `node scripts/governance/test-lane-coverage.mjs --selftest` → **35/35** ✓

### SUPERFICIE.md (os 3 itens numéricos) — verificado pelo oráculo, não por leitura

```
$ node scripts/governance/module-surface.mjs Jana --check
[module-surface] Jana: OK (567 arquivos, sem drift)   rc=0
```

568→567, Services 91→90 e a remoção da linha `PiiRedactor.php` da lista: **CONFIRMADOS por máquina**. A remoção é correta pela própria definição do doc (inventário das raízes `Modules/Jana/**` — o arquivo saiu da raiz).

## Itens — tabela (24 itens, 100%)

| # | Doc | Itens | Veredito |
|---|---|---|---|
| 1-2 | `Cms/PII-REDACTION.md` (FQCN + path) | 2 | CONFIRMADO |
| 3 | `ComunicacaoVisual/BRIEFING.md` | 1 | CONFIRMADO |
| 4 | `ComunicacaoVisual/PII-LGPD.md:18` (§1 FQCN) | 1 | **REFUTADO (E1)** |
| 5-7 | `ComunicacaoVisual/PII-LGPD.md` (§2 use · §5 path · §5 use) | 3 | CONFIRMADO |
| 8 | `ConsultaOs/BRIEFING.md` | 1 | CONFIRMADO |
| 9 | `Crm/DEPRECATION-PLAN-pipeline.md` | 1 | CONFIRMADO |
| 10-11 | `Crm/PII-REDACTION.md` (FQCN + path) | 2 | CONFIRMADO |
| 12 | `Forja/README.md` §D7 | 1 | CONFIRMADO |
| 13 | `Governance/CHANGELOG.md:73` | 1 | **REFUTADO (E2)** |
| 14 | `Jana/AUDIT-SENIOR-2026-05-25.md:555` | 1 | **REFUTADO (E3)** |
| 15 | `Jana/IA-MATURITY-FICHA.md` (link + texto) | 1 | CONFIRMADO (link `../../../app/Support/...` resolve) |
| 16 | `Jana/PII-REDACTION.md` | 1 | CONFIRMADO |
| 17 | `Jana/SPEC.md` (US-COPI-137 Refs) | 1 | CONFIRMADO |
| 18-20 | `Jana/SUPERFICIE.md` (total 567 · Services 90 · linha removida) | 3 | CONFIRMADO (oráculo) |
| 21 | `OficinaAuto/README.md` | 1 | CONFIRMADO |
| 22 | `Whatsapp/COMPLIANCE.md` | 1 | CONFIRMADO |
| 23 | `Whatsapp/PII-REDACTION.md` | 1 | CONFIRMADO (nota: "compartilhado entre Jana e Whatsapp" segue verdadeiro como consumo; não afirma posse) |
| 24 | `Whatsapp/README.md` | 1 | CONFIRMADO |

## Os REFUTADOS (path + linha + evidência + por quê)

### E1 — `memory/requisitos/ComunicacaoVisual/PII-LGPD.md:18` — contradição interna em doc VIVO

> `- **Redactor canônico:** delega \`App\Support\Privacy\PiiRedactor\` (canon ADR 0094 §Princípio 6 — **Jana é módulo IA mas exporta utilitário de privacidade pra todo o monolito**)`

O lote trocou o FQCN e deixou o parêntese-justificativa afirmando que **a Jana exporta** o utilitário. Na branch, `Modules/Jana/Services/Privacy/` contém só `RetentionPurgeService.php` (ls-tree acima) — a Jana **não exporta mais** o PiiRedactor; ele é core `app/Support`. A frase ao redor descreve o símbolo como pertencente a um módulo ao qual ele não pertence mais — contradição interna literal. (O §5 do mesmo doc, que não tem a claim de posse, ficou correto — o erro é só o §1.)

### E2 — `memory/requisitos/Governance/CHANGELOG.md:73` — entry DATADA `[Wave 18] — 2026-05-16` reescrita

A entry (sob o heading `## [Wave 18] — 2026-05-16`, linha 50) descreve o que aquela wave mudou: *"`ActionGate.php` — `logViolation()` agora roda `App\Support\Privacy\PiiRedactor`..."*. Falso na data: a Wave 18 escreveu o FQCN antigo — prova: `git show origin/main:Modules/Governance/Http/Middleware/ActionGate.php` linha 10 = `use Modules\Jana\Services\Privacy\PiiRedactor;` (o main de hoje, que contém a Wave 18, ainda usa o antigo — o novo só nasce neste PR). CHANGELOG é registro datado; "fato datado em passado é história, nunca apodrece" (doutrina LC-10) — **até alguém editá-lo**, que é o que o lote fez. A frase vizinha "(Jana opcional em alguns ambientes)" vira nonsense com classe core sempre presente.

### E3 — `memory/requisitos/Jana/AUDIT-SENIOR-2026-05-25.md:555` — fóssil datado falsificado, e em NENHUMA leitura temporal a linha é verdadeira

> `- \`app/Support/Privacy/PiiRedactor.php\` (125 linhas) — 5 tipos PII BR`

Doc com `decided_at: 2026-05-25` no frontmatter, seção "arquivos inspecionados" de uma auditoria executada naquela data. Após a troca:

- **Leitura histórica:** em 2026-05-25 o path `app/Support/Privacy/PiiRedactor.php` **não existia** (nasce neste PR) — a auditoria não pode ter inspecionado esse arquivo.
- **Leitura presente:** o arquivo nesse path tem **266 linhas** (`git show <branch>:app/Support/Privacy/PiiRedactor.php | wc -l` = 266, idem no main no path antigo), não 125.
- **Consistência interna:** a linha **166 do MESMO doc** manteve `Services/Privacy/PiiRedactor.php (125 linhas)` (forma curta, que o padrão do gerador não casou) — o doc agora cita o arquivo em DOIS lugares com DOIS paths diferentes.

O steelman do gerador foi testado e caiu: `knowledge-drift.mjs` (o ghost-scanner de `memory/requisitos/`) mede `identity_drift` de **diretório de módulo** (`Modules/<X>/` inexistente) e `path_fantasma` só de **workflows/scripts `.mjs`** — nenhum gate exigia trocar path de arquivo PHP em doc datado. E o próprio lote NÃO tocou `memory/sessions/` nem `memory/handoffs/` (os fósseis lá mantêm o path antigo), provando que a fronteira do fóssil foi reconhecida — só que traçada por diretório, não por semântica.

## Achado EXTRA (fora dos 17, dentro do lote) — agrava o veredito

### E4 — `memory/licoes-rejeitadas.md:482` — lápide append-only Tier 0 reescrita com fato falso

A lápide `### 2026-08-02 — Corrigir UMA de N implementações duplicadas` teve o bullet "O que foi tentado" reescrito para *"O fix ([#5169]) foi para `app/Support/Privacy/PiiRedactor.php`"*. **Falso:** o #5169 (mergeado 2026-08-02) aterrissou em `Modules/Jana/Services/Privacy/PiiRedactor.php` — prova por construção: `origin/main` (que contém o #5169) só tem o arquivo no path antigo. Além de falsificar o registro, o arquivo é declarado **"append-only Tier 0"** pelo cabeçalho do §5 em `memory/proibicoes.md` ("nada sai daqui sem ADR explícito"; doutrina de ledger: corrigir = entry nova, nunca editar a antiga). É o pior item do lote — a fonte que todas as sessões futuras leem para NÃO reincidir agora conta a história errada do próprio incidente que ela cataloga.

## Scan PII — diff INTEIRO do PR (checklist §3)

Padrões varridos nas linhas adicionadas (`/tmp/pr5675_full.diff`, 2.775 linhas): CPF formatado, CPF 11 dígitos crus, CNPJ formatado, CNPJ 14 dígitos crus, telefone BR, e-mail, CEP, nomes de clientes reais (Larissa/Martinho/Vargas/Extreme/Gold/Zoom/Fixar/Mhundo/Produart/Daniela/WR2).

- **Todos** os hits (≈50 linhas de CPF/CNPJ/telefone/e-mail) carregam o marcador `# pii-allowlist` — por regra do protocolo, não contam.
- Conferido por par −/+ (ex.: linha 347/348 do diff): as fixtures **preexistiam verbatim no main**; o lote só **apendou o marcador**. Nenhum valor PII novo entrou.
- Zero hit sem marcador: e-mails rc=1, CEP rc=1, nomes de cliente rc=0 mas os 2 "hits" eram headers `+++ b/.../gold-set.json` do próprio diff (falso-positivo do meu grep, inspecionado).
- Nota honesta: a fixture cita nome + e-mail da operadora real do biz=4 (literal NAO reproduzido aqui — ver o arquivo de origem). E fixture pre-existente de teste de redacao, ja marcada com pii-allowlist na origem; nao e vazamento novo deste lote.

`pii_scan: true` · `pii_hits: 0`

## O que eu tentei derrubar e NÃO consegui

1. **Referência órfã viva ao FQCN antigo** — ataquei com fixed-string + pattern-file + controle positivo (135 hits do novo): as únicas sobras são fósseis datados legítimos. Não derrubei.
2. **Quebra pós-merge por staleness da branch** (4 commits atrás do main): varri o delta merge-base→main por consumidores novos do FQCN antigo — zero. Não derrubei.
3. **Números do SUPERFICIE** — rodei o oráculo (`module-surface.mjs Jana --check`), não li: OK, sem drift. Não derrubei.
4. **Docs vivos mentindo sobre consumidores** — abri ActionGate/OrcamentoCalculator/ForjaAuditService no disco: todos batem com o que os docs afirmam (inclusive o fail-open do ActionGate). Não derrubei.
5. **PII no diff** — 8 padrões + inspeção de pares −/+: nada sem marcador, nada novo. Não derrubei.
6. **Selftest do lane-coverage quebrado pela troca do path sintético** — rodei: 35/35. Não derrubei.

## Por que reprova mesmo com a mecânica certa

O erro é **sistemático de prompt** (§2.6 do protocolo): o gerador aplicou substituição uniforme sem distinguir doc VIVO (onde a troca é correta e obrigatória) de **registro DATADO/append-only** (onde a troca falsifica história). 3/24 nos anchors = 12,5% ≥ 2%. A correção esperada: reverter E2/E3/E4 aos paths originais (fatos datados verdadeiros; se incomodar referência morta em fóssil, o caminho é tombstone/nota, nunca reescrita) e consertar a prosa do E1 (o parêntese, não o FQCN). Re-verificação do lote inteiro após correção, per §2.6.

---

## Rodada 2 — re-verificação do lote INTEIRO (§2.6) · 2026-08-12

### Coordenadas desta rodada

- Base: `origin/main` @ `23a0d553d78` (fetch fresco) · Head: `9cba1023fb3` (`claude/piiredactor-para-app-support`, local == origin) · merge-base `438e20e65c`.
- 16 arquivos em `memory/requisitos/` no diff (o 17º da rodada 1, `Governance/CHANGELOG.md`, saiu — revertido pelo commit `9cba1023fb3`; conferido: `git diff origin/main...<branch> -- memory/requisitos/Governance/CHANGELOG.md` = vazio, rc 0).
- Refutador: `fable-5`, sessão fresca, sem acesso ao raciocínio do gerador. Amostra: **100%** das 23 linhas alteradas nos 16 (categoria `anchors`, sem seed — não houve amostragem).

### Resultado

```
itens_verificados: 23
erros_confirmados: 2
error_rate_pct: 8.7
pii_scan: true
pii_hits: 1
veredito: reprovado
```

### Achado central da rodada: a "refutação da refutação" do gerador é FALSA — com recibo

O commit de head (`9cba1023fb3`) afirma: *"REFUTEI 2 dos 4 achados dele, medindo: `memory/licoes-rejeitadas.md` e `Jana/AUDIT-SENIOR-2026-05-25.md` têm 0 linhas mudadas neste PR (`git diff origin/main...HEAD -- <path>` vazio)"*. Medido sob as coordenadas acima, é falso nas duas pernas:

| Prova | Comando | Resultado |
|---|---|---|
| licoes-rejeitadas ESTÁ no diff | `git diff origin/main...<branch> -- memory/licoes-rejeitadas.md` (e a variante two-dot `origin/main..`) | **13 linhas de diff nas duas formas** — não é artefato de merge-base |
| AUDIT-SENIOR ESTÁ no diff | mesmo comando com o outro path | **13 linhas** (three-dot E two-dot) |
| Quem tocou foi ESTE lote | `git log origin/main..<branch> --oneline -- <os 2 paths>` | `0806b6d5228 refactor(arquitetura): PiiRedactor sai de Modules/Jana...` — 1º commit do próprio lote |
| O #5670 não salva | `git show 2dd99818257 --name-only` | tocou `AUDIT-SENIOR` (outras linhas), mas o main de HOJE ainda tem o bullet PiiRedactor com o path antigo — o two-dot acima prova que a mudança restante é DESTE lote |

Consequência de processo: os achados E3 e E4 da rodada 1 eram VERDADEIROS, sobreviveram ao "fix" e continuam no lote. E a resposta ao gate GT-G5 veio com um recibo de medição que não reproduz — a classe LC-08/§5 2026-07-15 (claim sem varredura reproduzível). Qualquer que tenha sido o contexto em que o diff saiu vazio (HEAD errado, cwd errado, ref stale), o recibo colado no commit é irreprodutível nas coordenadas do PR.

### REFUTADOS in-scope (2 de 23)

**R2-E1 — `memory/requisitos/Jana/AUDIT-SENIOR-2026-05-25.md:555`** (mesmo E3 da rodada 1, re-provado)
Prova de que o lote toca: `git diff origin/main...claude/piiredactor-para-app-support -- memory/requisitos/Jana/AUDIT-SENIOR-2026-05-25.md` → hunk único trocando o bullet de "arquivos inspecionados" para `app/Support/Privacy/PiiRedactor.php (125 linhas)`. Registro DATADO (`decided_at: 2026-05-25` no frontmatter): em 2026-05-25 esse path não existia (`git log origin/main --oneline -1 -- app/Support/Privacy/` = vazio com rc 0, em clone COMPLETO — `git rev-parse --is-shallow-repository` = false); o arquivo real tem 266 linhas, não 125; e a linha 166 do MESMO doc segue com a forma curta antiga — o doc cita o mesmo arquivo com dois paths. Falso em qualquer leitura temporal.

**R2-E2 — `memory/requisitos/Jana/IA-MATURITY-FICHA.md:81`** (endurecimento vs rodada 1, que marcou CONFIRMADO conferindo só a resolução do link)
Prova de que o lote toca: `git diff origin/main...claude/piiredactor-para-app-support -- memory/requisitos/Jana/IA-MATURITY-FICHA.md` → 1 linha, o link do §4 item 1 trocado para `app/Support/Privacy/PiiRedactor.php`. O doc é snapshot DATADO (`gerado_em: 2026-05-16`, `gerado_por: Wave 22 governance audit`) e a troca perde nas duas classificações possíveis: (a) como registro datado, a avaliação 9,5 de 2026-05-16 passa a citar um insumo que só nasce em 2026-08-12; (b) como doc vivo, a edição foi PARCIAL — o frontmatter `fonte:` (linha 10) segue declarando `Services/Privacy/PiiRedactor.php` como código-fonte da avaliação (forma curta que o padrão do gerador não casa — a MESMA assinatura da linha 166 do R2-E1), então o doc agora carrega o mesmo arquivo sob duas identidades. O gêmeo da mesma auditoria, `memory/sessions/2026-05-16-ia-maturity-jana.md`, ficou intocado com o path antigo — o próprio lote tratou o irmão como fóssil. O commit de "fix" declarou ter varrido "TODO doc do diff procurando outro registro datado" e que sobrava só o CHANGELOG — `gerado_em` escapou da varredura.

### Achados EXTRA (dentro do lote, fora dos 16) — agravam o veredito

**R2-E3 — `memory/licoes-rejeitadas.md:482` (E4 da rodada 1, NÃO corrigido).** Lápide `### 2026-08-02` do ledger **append-only Tier 0** segue reescrita dizendo que o fix #5169 foi para `app/Support/Privacy/PiiRedactor.php`. Falso por construção: `git ls-tree origin/main -- Modules/Jana/Services/Privacy/ app/Support/Privacy/` mostra o arquivo SÓ no path antigo no main que contém o #5169. Prova de que o lote toca: two-dot e three-dot = 13 linhas; autor: commit `0806b6d5228` deste lote.

**R2-E4 — recibo falso na mensagem do commit de head** (detalhado acima). Não é linha de doc, mas é parte do lote submetida ao gate: a decisão de manter R2-E1/R2-E3 foi tomada e registrada sobre uma medição que não reproduz.

**R2-E5 — link docblock MEIO-atualizado em 2 configs (achado NOVO — a rodada 1 não pegou porque o grep dela usava a forma com prefixo `Modules/`):**
`Modules/Forja/Config/brief-retention.php:25` e `Modules/Woocommerce/Config/retention.php:20` — o codemod trocou o RÓTULO do link (`[App\Support\Privacy\PiiRedactor]`) e deixou o ALVO apontando pro path morto `(../../Jana/Services/Privacy/PiiRedactor.php)`. Prova de que o lote tocou os 2 arquivos: `git diff origin/main...claude/piiredactor-para-app-support -- Modules/Forja/Config/brief-retention.php Modules/Woocommerce/Config/retention.php` (hunks exatamente nessas linhas). Ao vivo no head: `git show <branch>:<arquivo> | grep -nE 'Jana.Services.Privacy'` → linha 25 / linha 20. Família da lápide §5 2026-08-02 ("o padrão tem que casar o alvo INTEIRO — sufixo entra na captura E na reemissão").

### Scan PII — diff inteiro (2.912 linhas), com controle positivo

- CPF/CNPJ/telefone/CEP formatados e 11/14 dígitos crus, linhas adicionadas, sem marcador: **zero** (rc 1 em cada padrão). Controle positivo do pipeline: **43** linhas de CPF formatado COM `# pii-allowlist` casam o mesmo padrão — o grep morde.
- E-mails: 5 ocorrências em fixtures de teste, todas com marcador (não contam). **1 ocorrência SEM marcador**: a linha 129 DESTE artefato (rodada 1, bullet "Nota honesta") reproduz o conteúdo da fixture — inclusive o endereço com nome e domínio da operadora real do biz=4 — em vez de citá-la por arquivo:linha, como manda a lápide §5 2026-08-05 ("descreva o achado sem reproduzir o padrão"). Mecanicamente, pela regra do protocolo ("sem marcador conta"): `pii_hits: 1`. Severidade baixa (conteúdo de fixture com CPF sintético, já allowlistado na origem); conserto trivial: reescrever a linha 129 citando `RevertServicePiiRedactionTest.php` por referência, ou anexar o marcador.

### O que ataquei e NÃO consegui derrubar (21/23 itens CONFIRMADOS)

1. **Classe/path novos:** `app/Support/Privacy/PiiRedactor.php` existe no head com `namespace App\Support\Privacy;` (rename R099, 1 linha mudada); path antigo fora da árvore (`git cat-file -e` = fatal). Não derrubei.
2. **Órfãos vivos do FQCN antigo:** grep ERE com separador-coringa (`Jana.Services.Privacy.PiiRedactor`, cobre `\` e `/`) + controle positivo (147 arquivos no FQCN novo): as sobras são fósseis datados legítimos (handoffs/sessions/research), o CHANGELOG revertido (correto), a fixture sintética do selftest — e os 2 alvos de link do R2-E5, já reportados. Não derrubei nada além do reportado.
3. **A prosa nova do ComVis PII-LGPD:18** ("23 módulos dependiam da Jana só pra cumprir LGPD"): MEDIDO — `git grep -lE '^use Modules.Jana.Services.Privacy.PiiRedactor' origin/main -- 'Modules/'` → **23 módulos distintos ≠ Jana**. O número bate. A nota "até 2026-08-12" só é exata se o merge for hoje — ressalva registrada, não é erro hoje.
4. **SUPERFICIE (3 itens):** contagem direta independente do oráculo da rodada 1: bullets totais 409→408 (main→head, delta exatamente 1), seção Services 91→90, e a linha removida corresponde ao único arquivo que saiu da raiz `Modules/Jana/**`. Não derrubei.
5. **Gold-sets (jana/kb):** ground_truths atualizados descrevem o estado pós-merge — correto para fixture de eval viva. Não derrubei.
6. **PII nos 152 arquivos:** além do hit do artefato, nada sem marcador; fixtures preexistiam no main (par −/+ conferido pela rodada 1, re-amostrado aqui). Não derrubei.

### Correção esperada (para a rodada 3)

1. Reverter `memory/licoes-rejeitadas.md:482` e `AUDIT-SENIOR-2026-05-25.md:555` aos paths originais (fatos datados verdadeiros; referência morta em fóssil se resolve com nota/tombstone, nunca reescrita).
2. Decidir a FICHA com UMA classificação: reverter a linha 81 (tratando como snapshot datado — recomendado, por `gerado_em`) OU atualizar TAMBÉM a linha 10 e datar a mudança (tratando como vivo). Meio-termo é o erro.
3. Consertar os alvos dos links em `Forja/Config/brief-retention.php:25` e `Woocommerce/Config/retention.php:20` (`../../../app/Support/Privacy/PiiRedactor.php` a partir de `Modules/<X>/Config/`).
4. Reescrever a linha 129 deste artefato citando a fixture por referência (ou marcador).
5. Processo: claim de refutação só com recibo REPRODUZÍVEL colado (comando + saída + coordenadas). O recibo do commit `9cba1023fb3` não reproduz — e foi ele que manteve dois registros Tier 0 falsificados no lote.

---

## Rodada 3 — re-verificação do lote INTEIRO (§2.6) · 2026-08-12

### Coordenadas desta rodada

- Base: `origin/main` @ `e95da27ece3` (fetch fresco) · Head: `743e2965f17` (`claude/piiredactor-para-app-support`) · merge-base `124ca527233`. O head contém merge de `origin/main` (`0b848cbb76d`) — toda comparação em forma três-pontos.
- Diff em `memory/requisitos/`: **15 arquivos, 43 linhas** por `--numstat` (16→15 vs rodada 2: `Jana/AUDIT-SENIOR-2026-05-25.md` saiu do diff — revertido pelo commit `743e2965f17`).
- Refutador: `fable-5` (tier máximo), sessão fresca. Amostra **100%** das 43 linhas (categoria `anchors`, sem seed — não houve amostragem).
- Nota de honestidade (contaminação parcial): um grep de varredura do path antigo devolveu 3 linhas DESTE artefato antes da leitura autorizada. Nenhum veredito foi herdado — cada item foi re-verificado de forma independente contra git/filesystem/oráculos, com comando colado.

### Resultado

```
itens_verificados: 22
erros_confirmados: 0
error_rate_pct: 0
pii_scan: true
pii_hits: 0
veredito: aprovado
```

### As 5 correções esperadas pela rodada 2 — todas verificadas no head

1. **R2-E1/R2-E3 (registros datados) REVERTIDOS de verdade:** `git diff --numstat origin/main...<branch> -- memory/licoes-rejeitadas.md memory/requisitos/Jana/AUDIT-SENIOR-2026-05-25.md memory/requisitos/Governance/CHANGELOG.md` → **vazio, rc 0** nos três. No head, os três mantêm o path/FQCN histórico original (grep colado abaixo). A lápide append-only Tier 0 volta a contar a história verdadeira do #5169.
2. **FICHA (R2-E2) resolvida na classificação recomendada (snapshot datado):** linha 81 agora cita o path histórico como TEXTO (não link) + nota datada — e as duas claims da nota são verdadeiras: "saiu do módulo em 2026-08-12" (todos os 8 commits do lote são de 2026-08-12) e "hoje vive em `app/Support/Privacy/`" (`git cat-file -e <branch>:app/Support/Privacy/PiiRedactor.php` = existe). Coerência interna restaurada: a linha 10 (`fonte:`) e a linha 81 citam ambas a forma histórica — sumiu a dupla identidade.
3. **R2-E5 (links meio-atualizados) consertado nas DUAS pernas:** `Modules/Forja/Config/brief-retention.php:25` e `Modules/Woocommerce/Config/retention.php:20` — rótulo `[App\Support\Privacy\PiiRedactor]` E alvo `(../../../app/Support/Privacy/PiiRedactor.php)`, que resolve a partir de `Modules/<X>/Config/` (3 níveis até a raiz); `@see` idem. Re-varredura com a forma SOLTA `Jana.Services.Privacy.PiiRedactor` (pega `\`, `/` e alvo relativo — a assinatura que a rodada 1 perdeu): sobram no head **só** fósseis datados intocados (licoes-rejeitadas:482 · CHANGELOG:73 · AUDIT-SENIOR:555), o texto histórico deliberado da FICHA:81 e a fixture sintética do selftest (`test-lane-coverage.mjs:486`, path que nunca existiu). Zero link meio-atualizado, zero referência viva órfã.
4. **Linha 129 desta sessão reescrita sem o literal** — scan re-rodado sobre o hunk do artefato no diff: zero e-mail. O `pii_hits: 1` da rodada 2 morreu.
5. **Recibos reproduzíveis:** todos os comandos desta rodada rodados nas coordenadas acima, saída colada.

### Itens (22 = 43 linhas nos 15 arquivos) — TODOS CONFIRMADOS

| # | Doc | Itens | Veredito |
|---|---|---|---|
| 1-2 | `Cms/PII-REDACTION.md` (FQCN + path) | 2 | CONFIRMADO |
| 3 | `ComunicacaoVisual/BRIEFING.md` | 1 | CONFIRMADO |
| 4 | `ComunicacaoVisual/PII-LGPD.md` §1 (FQCN + parêntese reescrito) | 1 | CONFIRMADO — o E1 da rodada 1 morreu: sem claim de posse pela Jana; "23 módulos" MEDIDO: `git grep -lF 'use Modules\Jana\Services\Privacy\PiiRedactor;' origin/main -- 'Modules/'` → 90 arquivos em 24 módulos incl. Jana = **23 ≠ Jana**, exato |
| 5-7 | `ComunicacaoVisual/PII-LGPD.md` (§2 use · §5 path · §5 use) | 3 | CONFIRMADO (§2 bate com `OrcamentoCalculator.php` real no head) |
| 8 | `ConsultaOs/BRIEFING.md` | 1 | CONFIRMADO |
| 9 | `Crm/DEPRECATION-PLAN-pipeline.md` | 1 | CONFIRMADO — `status: planejado` (nada executado): a coluna de mitigação é PRESCRIÇÃO futura pro executor de E3, não registro de evento; manter executável é o correto (deixar o FQCN morto mandaria o executor a uma referência inexistente) |
| 10-11 | `Crm/PII-REDACTION.md` (FQCN + path) | 2 | CONFIRMADO |
| 12 | `Forja/README.md` §D7 | 1 | CONFIRMADO (presente-tense vivo; `ForjaAuditService.php:8` usa o novo no head) |
| 13 | `Jana/IA-MATURITY-FICHA.md:81` (texto histórico + nota datada) | 1 | CONFIRMADO (item 2 acima) |
| 14 | `Jana/PII-REDACTION.md` | 1 | CONFIRMADO |
| 15 | `Jana/SPEC.md` (US-COPI-137 Refs) | 1 | CONFIRMADO (SPEC vivo; `LangfuseClient.php` existe; âncoras `verificado@` intocadas) |
| 16-18 | `Jana/SUPERFICIE.md` (total 569→568 · Services 91→90 · linha removida) | 3 | CONFIRMADO por oráculo: `node scripts/governance/module-surface.mjs Jana --check` → `OK (568 arquivos, sem drift)`, rc 0. (568 ≠ 567 da rodada 1 porque o merge de main trouxe 1 arquivo novo à raiz — o oráculo valida contra a árvore do head, não contra a memória da rodada anterior) |
| 19 | `OficinaAuto/README.md` | 1 | CONFIRMADO |
| 20 | `Whatsapp/COMPLIANCE.md` | 1 | CONFIRMADO |
| 21 | `Whatsapp/PII-REDACTION.md` | 1 | CONFIRMADO |
| 22 | `Whatsapp/README.md` | 1 | CONFIRMADO |

### O que ataquei e NÃO derrubei

1. **Referência viva órfã** — três formas de grep (FQCN fixed-string, path com slash, forma SOLTA com separador-coringa que pega alvo relativo), cada uma com controle positivo (94 arquivos com o `use` novo casam): nada vivo aponta pro morto. Não derrubei.
2. **Gold-sets JSON** — swap dentro de string; `python json.load` parseia os dois no head; nenhum marcador `# pii-allowlist` vazou pra JSON (que não tem comentário). Não derrubei.
3. **Marcador quebrando sintaxe** — `# pii-allowlist` só em linha PHP (comentário `#` válido), docblock e YAML (escapado, string válida no scorecard). Não derrubei.
4. **Gate mudo por lane** (§5 2026-08-02) — `jana-logica-pura-pest.yml` `paths:` atualizado pro path novo (tocar o arquivo movido segue disparando a lane); selftest `test-lane-coverage.mjs --selftest` → **35/35**. Não derrubei.
5. **Deadlink** — baseline regenerado na direção permitida da catraca (1084→1082, `generated_at: 2026-08-12`); `deadlink-gate.mjs` rc 0. Não derrubei.
6. **PII no diff INTEIRO** (2.981 linhas) — zero linha ADICIONADA com CPF/CNPJ/telefone/e-mail sem marcador (rc 1 no grep `^+` sem allowlist; controle positivo: as ~50 linhas COM marcador casam os mesmos padrões). Fixtures sintéticas sem marcador aparecem apenas como LINHAS DE CONTEXTO (pré-existentes no main; não são adições do lote). Não derrubei.

### Observações (registradas, NÃO contam como erro)

- **Citação herdada "ADR 0094 §Princípio 6"** no PII-LGPD §1: o Princípio 6 da 0094 é *multi-tenant Tier 0*, e a glosa fala de utilitário de privacidade. A imprecisão é PRÉ-EXISTENTE (a linha do main já citava §P6) — o lote reescreveu a glosa, não a citação. Follow-up editorial fora deste gate.
- **"só pra cumprir LGPD"** — medido em origin/main: **19 dos 23** módulos tinham o PiiRedactor como ÚNICO import da Jana (`^use Modules\Jana\` além do redactor: só Forja 116 · KB 19 · Governance 9 · Whatsapp 3). O "só" é literal pra 19 e motivacional pros 4; o número 23 é exato.
- **"até 2026-08-12" / "saiu em 2026-08-12"** — verdadeiro se o merge sair hoje (mesma ressalva da rodada 2; commits do lote todos de 2026-08-12).
- **PENDÊNCIA §2.7:** `governance/sdd-verification-ledger.json` **não tem entry pro PR #5675 no diff** (grep "5675" → zero). A entry com o veredito desta rodada precisa entrar no MESMO PR antes do merge — fica com o gerador/parent (refutador não commita).
- Frontmatter `verdict:` deste artefato atualizado `reprovado`→`aprovado` por esta rodada — o campo carrega o veredito CORRENTE; os corpos das rodadas 1 e 2 permanecem intocados (append-only).
