---
date: "2026-09-14"
topic: "Refutação adversarial GT-G5 (rodada r2) do lote do PR 7262 — 64 map.json com prototipo_sha refrescado + 1 gap.md do Fiscal; 329 itens medidos contra origin/main, 1 refutado"
authors: ["C"]
prs: [7262]
outcomes:
  - "APROVADO com 1 refutado: 329 itens verificados, error_rate 0,30% (< 2), PII 0 hits com 7/7 controles positivos casando."
  - "Refutado: a célula 'Estado no vivo' da linha 'Certificado e regime' em fiscal-config-gap.md afirma, em presente, dois fatos que origin/main contradiz — e cita um intervalo de linha que não contém o que ela alega."
  - "Os 64 prototipo_sha foram recomputados de forma independente pelo gerador do repo: 64/64 batem e 64/64 estavam de fato stale em origin/main; regen chave-a-chave de 5.697 chaves deu 0 divergência."
---

## TL;DR

**APROVADO.** 329 itens verificados a 100% de amostra contra `origin/main`, **1 erro confirmado**
(`error_rate = 0,30%`, teto 2%), **0 hits de PII** com os 7 controles positivos casando. O erro é a
célula *"Estado no vivo"* da única linha de tabela do `fiscal-config-gap.md`: o lote reescreveu a
linha inteira e deixou nela duas afirmações em presente que o código em `origin/main` contradiz,
além de um intervalo de linha citado que não contém o que se alega. Todo o resto — os 64
`prototipo_sha`, os 64 `gerado_em`, o flip de `_acionavel` e os 193 paths citados — confere.

## Cabeçalho da rodada

| Campo | Valor |
|---|---|
| Base | `7318243958` (`origin/main` no início e no fim da sessão) |
| HEAD do lote | `3566909fa8` (commits `c8106c460e` + `3566909fa8`) |
| Repo raso | **false** (`git rev-parse --is-shallow-repository`) — datas de git valem como recibo |
| Sessão fresca | **sim** — instância nova, sem contexto do gerador nem de rodada anterior |
| Evidência anterior aberta | **não** (ver §Higiene abaixo) |
| Tipo | `anchors` · amostra **100%** (sem seed: seed só se aplica à amostragem de prosa ≥30%) |

⚠️ **A working tree foi revertida por OUTRA sessão no meio da verificação.** O reflog registra
`HEAD@{0}: checkout: moving from claude/map-stale-refresh to origin/main`, e a partir daí
`git diff origin/main...HEAD` passou a devolver **vazio** — sonda vazia que é falha de medição, não
ausência (§5 2026-07-31 / 2026-08-01). Peguei pelo controle: o mesmo comando devolvia 39,8 KB
minutos antes. **Todas as medições decisivas foram então re-ancoradas no commit imutável
`3566909fa8`** (`git show 3566909fa8:<path>`), nunca na working tree. Isso é legítimo porque medi
antes que **só os 65 arquivos de `memory/requisitos` diferem** entre `7318243958` e `3566909fa8`
(`git diff --name-only … -- . ':(exclude)memory/requisitos'` → vazio; controle positivo sem o
exclude → 65), logo `prototipo-ui/**`, `resources/js/**`, `app/**` e `scripts/**` são byte-idênticos
nos dois lados e podem ser lidos do disco sem perda de fidelidade.

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Opus 5; gerador declarado como Haiku/Sonnet no fluxo do §2)
- [x] Amostra: **100%** dos anchors (seed n/a — só exigida em prosa ≥30%)
- [x] Cada item verificado contra o código real em `origin/main`, **não** contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] **Scan PII no diff** — 7 padrões, cada um com controle positivo (7/7 casaram)
- [x] `error_rate_pct` calculado e < 2
- [ ] Entry no ledger — **não escrita** (fora do meu mandato; o refutador não escreve no ledger)

## Escopo medido

`git diff --name-status 7318243958 3566909fa8` → **65 arquivos, todos `M`**, 131 inserções / 131
deleções. Decomposto:

| Conteúdo | Qtde |
|---|---|
| `.map.json` com `prototipo_sha` + `gerado_em` trocados | 64 |
| `.map.json` com `_acionavel` trocado (além do sha/data) | 1 (`Compras/compras-grade-matrix.map.json`) |
| `.map.json` que ganhou newline final | 1 (`Arquivos/arquivos-index.map.json`) |
| `-gap.md` com linha de tabela reescrita | 1 (`Fiscal/fiscal-config-gap.md`) |

## Tabela por grupo

| # | Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|---|
| G1 | `prototipo_sha` (valor novo) | 64 | 64 | 0 | `gerar()` de `scripts/design/gerar-map.mjs` reimportado, recomputando `computeProtoHash` a partir do `prototipo:` de cada `gap.md` do lote |
| G2 | `gerado_em` | 64 | 64 | 0 | igual a `2026-09-14` (data de hoje) em 64/64; é o que o CLI grava (`hoje = new Date()…slice(0,10)`) |
| G3 | `_acionavel` (flip) | 1 | 1 | 0 | `ehAcionavel()` sobre a célula Ação real do `gap.md` |
| G4 | Linha de tabela derivada (`fiscal-config-gap.md`) | 1 | 0 | **1** | abri `Config.tsx` em `origin/main` e contei |
| G5 | Chaves de frontmatter do `gap.md` do lote | 6 | 6 | 0 | `id`/`tela`/`prototipo`/`tela_viva`/`gerado_em`/`comparacao` — paths resolvidos por `git ls-tree` |
| G6 | Paths distintos citados pelos arquivos do lote | 193 | 193 | 0 | `git ls-tree 7318243958 -- <path>` em cada um (1.249 referências → 193 paths distintos) |
| | **TOTAL** | **329** | **328** | **1** | |

`error_rate_pct = 1 / 329 = 0,30%`.

> **Denominador alternativo, declarado para quem quiser conferir:** contando só o que o lote
> *mudou* (64 sha + 64 data + 1 `_acionavel` + 1 linha = **130**), o erro dá **0,77%**. Os dois
> ficam abaixo do teto de 2%, então o veredito não depende da escolha de unidade.

### Medições de suporte (todas com rc literal)

| Medição | Resultado |
|---|---|
| `computeProtoHash` recomputado vs salvo no lote | **64/64 batem**, 0 divergem |
| O sha antigo de `origin/main` estava de fato stale? | **64/64 sim** (antigo ≠ recomputado) — o refresh não foi gratuito |
| Regen chave-a-chave (`gerar` + `fundirComExistente` vs o map do lote) | **5.697 chaves comparadas, 0 map divergente** |
| `verificarMapa()` (o predicado do gate) por map | **64 sem drift, 0 com drift** |
| `node scripts/governance/design-code-map-check.mjs --check --strict` | **rc=0** — *"nenhum map.json com âncora quebrada ou sha stale"* |
| `node scripts/governance/design-code-map-check.test.mjs` | **rc=0** (SELFTEST OK) |
| `node scripts/design/gerar-map.mjs --selftest` | **rc=0** |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | **rc=0** — 0 colisão em 2.729 ids |
| `node scripts/governance/anchor-lint.mjs --check` | **rc=0** |
| `node scripts/governance/plans-index.mjs --check` | **rc=0** |
| `node scripts/governance/requisitos-status.mjs Fiscal --check` | **rc=0** |
| Âncora por tela (`scripts/design/ancora.mjs --staging prototipo-ui/cowork/Wagner`) | 61/64 `âncora ✓`; 3 com `n/a` declarado ou sem charter — todos legítimos (§Observações) |
| Integridade da célula do `gap.md` | 4 pipes por linha (3 células), 4 backticks (2 pares), 4 asteriscos (1 par), 0 reticências |

## REFUTADOS (1)

### R-1 — `memory/requisitos/Fiscal/fiscal-config-gap.md` : célula "Estado no vivo" da linha "Certificado e regime"

**Item.** Linha 14 do arquivo (a única linha de dados da tabela). O lote **reescreveu a linha
inteira** — no diff ela aparece como um único `+` — trocando a célula *Ação* de `**Decidir.** …`
para `**Nada a fazer** - FECHADO em 2026-09-04 …` e **reemitindo a célula "Estado no vivo"
verbatim**.

**O que a célula afirma** (presente, sem qualquer qualificador de data):

> *Card do certificado na região ancorada; regime e tributação default existem FORA dela
> (Config.tsx:469-488); "Envio de documentos" não existe no arquivo*

**O que `origin/main` diz** (`resources/js/Pages/Fiscal/Config.tsx`, blob
`9dbecfc6b14dec88c3c49f58bf7bbdeebbc6b956`, 900 linhas — conferi que o arquivo que li tem esse
mesmo hash via `git hash-object`):

| Afirmação da célula | Medição em `origin/main` | Veredito |
|---|---|---|
| `"Envio de documentos" não existe no arquivo` | **4 ocorrências** — linhas 89, 549, 550 e **558** (esta é o rótulo renderizado, dentro de uma `<section>` que vai de 556 a 596) | **FALSA** |
| `regime e tributação default existem FORA dela` | `<dt>Regime</dt>` na **linha 523** e `<dt>Tributação default</dt>` na **linha 537**, ambas **DENTRO** da região: `<div data-contract="fiscal-config-cert-regime">` abre em **371** e fecha em **597** | **FALSA** |
| `(Config.tsx:469-488)` como o lugar de regime/tributação | 466–492 é o **formulário de upload do certificado** (`<input type="file" accept=".pfx,.p12">`, campo *"Senha do certificado"*) — não há regime nem tributação ali | **LINHA ERRADA** |

**Por que é erro do lote, e não dívida herdada.** Três razões independentes, cada uma suficiente:

1. **A própria célula *Ação* da mesma linha sabe que aquilo caducou** e diz com todas as letras:
   *"A analise anterior media 573 linhas e 0 ocorrencias de 'Envio de documentos' — era verdade em
   2026-08-28 (…). Re-medido contra origin/main em 2026-09-14: 900 linhas e 4 ocorrencias."*
   O autor mediu, registrou o número novo na célula 3 e deixou o número velho **em presente** na
   célula 2, na mesma linha. É afirmação em presente sobre comportamento medido — a família da
   lápide §5 2026-08-17 (*"comentário de código que se autodefende com medição obsoleta"*) e da
   LC-10 (*fato datado em passado pode; presente apodrece*).
2. **É exatamente o vetor que o commit diz fechar.** A célula 2 é a premissa crua da qual o
   veredito antigo (`**Decidir.**`) se re-deriva. O lote tenta impedir essa re-derivação com um
   pedido em prosa — *"Nao ressuscitar o veredito por re-derivacao desta linha"* — em vez de
   corrigir o fato. Súplica escrita guardando premissa falsa é "escrito + lembrado apodrece"
   ([ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)); a próxima
   sessão que ler a célula 2 tem material completo para reabrir o que foi fechado.
3. **O artefato derivado já estava certo e contradiz a célula.** `fiscal-config.map.json` (tocado
   por este mesmo lote) traz, na parte `certificado-e-regime`, `status: "fechado"`,
   `_acionavel: false` e a `acao` dizendo literalmente *"regime e tributação continuam no card
   'Identificação fiscal & numeração', que passou a viver DENTRO da região ancorada"*. Ou seja: o
   `.map.json` afirma o oposto da célula 2 do `.gap.md` que o gera. O lote reconciliou a célula 3
   com o derivado e deixou a célula 2 divergente.

**Não é dano de "âncora quebrada":** o gate não vê isto, porque `design-code-map-check` valida
existência de path, `data-contract` e frescor de sha — nunca a veracidade de prosa. Por isso o item
aparece aqui e não no CI.

**Correção mínima (não apliquei — não edito o lote):** reescrever a célula 2 para o estado medido
hoje (região `fiscal-config-cert-regime` 371–597 contendo certificado, identificação/numeração com
regime e tributação default, e o card "Envio de documentos"), ou datá-la explicitamente como
retrato de 2026-08-28.

## Observações — NÃO contadas como erro

1. **`memory/requisitos/Essentials/tipos.map.json` está stale e ficou de fora do lote.** Regenerando
   da fonte: `sha256:69d6f13d44d8`; o salvo é `sha256:1b1cc5c4264f` (`gerado_em: 2026-09-06`). O
   gate não enxerga porque **todas** as partes desse map têm `prototipo.arquivo: "n/a"`, e
   `verificarMapa` só recomputa staleness se `arquivosPrototipoReais` for não-vazio. Curiosidade
   que vale registrar: o `tipos-gap.md` e o `holidays-index-gap.md` declaram **o mesmo**
   `prototipo-ui/cowork/Wagner/hrm-page.jsx`, e o lote refrescou o segundo para
   `sha256:69d6f13d44d8` — então depois deste PR o repo afirma **duas identidades diferentes para o
   mesmo arquivo-fonte**. **Não conto como erro** porque a condição é idêntica em `origin/main`
   (dívida herdada, não própria — §5 2026-08-24) e o arquivo não é item do lote.
2. **`_STATUS-GENERATED.md` drifado em 13 de 23 módulos tocados** (`requisitos-status.mjs --check`
   rc=1 em Arquivos, Atendimento, Backup, Compras, Dashboard, Essentials, Governance,
   PaymentGateway, Ponto, Repair, Sells, Superadmin, Suporte). **Provado não-causado pelo lote:**
   `requisitos-status.mjs` não lê `.map.json` (0 ocorrências de `map.json` na saída gerada), e o
   delta de Compras é `UC-CMP-10`, que já existe em
   `origin/main:resources/js/Pages/Compras/Index.casos.md` enquanto o
   `origin/main:memory/requisitos/Compras/_STATUS-GENERATED.md` já dizia `9`. Pré-existente.
3. **3 telas sem `âncora ✓` no `ancora.mjs`** — `GradeMatrixInput` (não é tela; o próprio map diz
   *"NÃO é tela completa"*, logo não há charter), `Repair/Settings/Index` e `Suporte/Empresas`
   (charters declaram `n/a (herda PT-0X…)`, que o próprio `ancora.mjs` rotula *"declaração
   legítima"*). Os respectivos `gap.md` explicam a escolha e o uso paridade-only de porte reverso.
   §5 2026-08-28 diz que `n/a` declarado não é defeito.
4. **`Essentials/settings-index` ancora em `hrm-extras.jsx` e não no `hrm-page.jsx` do charter** —
   coerente e verificado: `hrm-page.jsx:505` despacha `view === "hrm-config" ? <X.Config />` e
   `hrm-extras.jsx:553` define `Config`, exportado em `window.HrmExtras` (linha 610).
5. **A célula *Ação* nova perdeu os acentos** ("analise", "ocorrencias", "Nao", "unico", "CONSTRUIDO",
   "regiao"). É desvio de qualidade PT-BR, não erro de fato — não conto.
6. **Higiene da árvore.** Não escrevi nada no repo além desta evidência. O `git status` mostra
   ` M governance/sdd-scorecard.json` e ` M` em 3 `.map.json` do Ponto **staged** que apareceram
   entre dois `git status` consecutivos — são de outra sessão ativa nesta worktree compartilhada.
   Confirmei que não foram meus: os únicos escritores de `governance/sdd-scorecard.json` são
   `sdd-scorecard.mjs` e `system-map.mjs` (que não rodei); `anchor-lint.mjs` tem **0** `writeFileSync`
   e `requisitos-status.mjs` só escreve sob `--write`, que não usei. **Não toquei nada disso** —
   mexer em estado compartilhado por posição/presunção é a lápide §5 2026-07-27.
7. **Não abri nenhuma evidência anterior.** O diretório de scratch desta máquina contém arquivos de
   outra sessão (timestamps anteriores ao início desta) e `memory/sessions/…-r1.md` existe como
   untracked — **não li nenhum dos dois**.

## Scan PII

Sobre as **131 linhas `+`** de `git diff 7318243958 3566909fa8 -- memory/requisitos` (controle de que
a sonda rodou: o diff tem 987 linhas; a primeira tentativa devolveu `0 linhas +` e foi descartada
como falha de medição, não como ausência).

| # | Padrão | Hits | Controle positivo |
|---|---|---|---|
| 1 | CPF pontuado (`\d{3}.\d{3}.\d{3}-\d{2}`) | 0 | CASOU |
| 2 | CPF cru (11 dígitos isolados) | 0 | CASOU |
| 3 | CNPJ (pontuado ou 14 dígitos crus) | 0 | CASOU |
| 4 | Telefone BR formatado (`(DD) NNNNN-NNNN`) | 0 | CASOU |
| 5 | Telefone cru (10–11 dígitos isolados) | 1 bruto → **0 real** | CASOU |
| 6 | E-mail | 0 | CASOU |
| 7 | Valor monetário brasileiro (símbolo da moeda seguido de dígito — descrito, não reproduzido) | 0 | CASOU |

**Controles positivos: 7/7 casaram. PII real: 0.**

O único casamento bruto é do padrão 5 sobre a string `"prototipo_sha": "sha256:2d2084338189"` — a
sub-sequência de 10 dígitos dentro de um **hash hexadecimal de conteúdo**. Falso positivo por
construção; não é telefone nem dado pessoal. Nenhum nome de cliente do CRM aparece nas linhas `+`
(o lote só troca hashes, uma data e uma linha de tabela sobre configuração fiscal).

## Comandos reproduzíveis

```bash
# escopo (contra os commits, nunca a working tree — ela foi revertida por outra sessão)
git rev-parse --is-shallow-repository                       # false
git diff --name-status 7318243958 3566909fa8                # 65 M
git diff --name-only 7318243958 3566909fa8 -- . ':(exclude)memory/requisitos'   # vazio
git diff --name-only 7318243958 3566909fa8 -- .             # 65  (controle positivo)

# âncora do Fiscal (R-1)
git ls-tree origin/main -- resources/js/Pages/Fiscal/Config.tsx
git show origin/main:resources/js/Pages/Fiscal/Config.tsx | wc -l              # 900
git show origin/main:resources/js/Pages/Fiscal/Config.tsx | grep -n "Envio de documentos"
git show origin/main:resources/js/Pages/Fiscal/Config.tsx | grep -n "data-contract"
git show origin/main:resources/js/Pages/Fiscal/Config.tsx | sed -n '466,492p'  # form do certificado
git show origin/main:resources/js/Pages/Fiscal/Config.tsx | sed -n '523p;537p;597p'

# máquinas derivadas
node scripts/governance/design-code-map-check.mjs --check --strict   # rc=0
node scripts/governance/design-code-map-check.test.mjs               # rc=0
node scripts/design/gerar-map.mjs --selftest                         # rc=0
node scripts/governance/doc-id-index.mjs --check-collisions          # rc=0
node scripts/governance/anchor-lint.mjs --check                      # rc=0
node scripts/governance/plans-index.mjs --check                      # rc=0
node scripts/design/ancora.mjs "Fiscal/Config" --staging prototipo-ui/cowork/Wagner
```

A recomputação independente dos 64 `prototipo_sha`, o regen chave-a-chave das 5.697 chaves, a
checagem dos 193 paths e o scan PII rodaram por um script de sessão que importa `gerar`,
`fundirComExistente` (de `scripts/design/gerar-map.mjs`), `verificarMapa` (de
`scripts/governance/design-code-map-check.mjs`) e `ehAcionavel` (de
`scripts/design/gerar-contrato.mjs`), lendo **todo** arquivo do lote via `git show 3566909fa8:<path>`.
Nada foi escrito no repo.

## Veredito

```json
{
  "itens_verificados": 329,
  "erros_confirmados": 1,
  "error_rate_pct": 0.3,
  "pii_hits": 0,
  "veredito": "aprovado"
}
```
