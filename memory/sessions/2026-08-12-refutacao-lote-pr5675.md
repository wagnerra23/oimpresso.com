---
date: "2026-08-12"
topic: "PR 5675 — refutação adversarial do lote PiiRedactor→app/Support (GT-G5)"
type: session
status: closed
module: governance
pr: 5675
verdict: reprovado
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
- Nota honesta: `Cliente Larissa ... larissa@rotalivre.com.br` referencia a operadora real do biz=4, mas é fixture pré-existente de teste de redação, com CPF sintético, agora marcada — preexistente + marcada = não conta, registrado aqui por transparência.

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
