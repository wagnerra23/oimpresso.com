---
date: "2026-09-14"
topic: "Refutação adversarial GT-G5 r1 do lote do PR #7262 (64 .map.json em memory/requisitos): refresh de prototipo_sha + gerado_em após o re-export do protótipo. 7375 itens verificados, 0 erros confirmados, 0 hits de PII."
authors: ["C"]
prs: [7262]
outcomes:
  - "APROVADO: 64/64 map.json regeneram BIT-A-BIT idênticos ao lote pelo gerador canônico (5697 chaves comparadas, 0 divergência); os 64 prototipo_sha recomputados por computeProtoHash batem 64/64 e nenhum batia em origin/main."
  - "Controle positivo+negativo fechados: o predicado do design-code-map-check acusa 64/64 STALE em origin/main e 0 no HEAD; o comparador de regeneração acusa 64 divergências contra main e 0 contra HEAD."
  - "⚠️ Esta rodada teve exposição acidental de linhas de memory/sessions/*refutacao* por um `rg` de varredura (declarado abaixo, §Integridade); toda a análise substantiva foi concluída ANTES dessa exposição."
---

# Session log 2026-09-14 — Refutação GT-G5 r1 · lote PR #7262

## TL;DR

**APROVADO.** 7375 itens verificados, **0 erros confirmados** (error_rate **0,00%**), **0 hits de PII** com 7/7 controles positivos. O lote é uma regeneração mecânica fiel: os 64 `.map.json` reproduzem bit-a-bit a saída do gerador canônico sobre o `gap_fonte` de cada um, e os 64 `prototipo_sha` recomputados independentemente batem 64/64 — enquanto nenhum dos valores de `origin/main` batia.

---

## Cabeçalho da rodada

| Campo | Valor |
|---|---|
| Base | `origin/main` = `73182439581f029604f2046bd4dd1102ba6ffa03` |
| HEAD | `c8106c460e9e8c7cf8b6cc5dd341082d856bbdac` |
| Repo raso? | **false** (`git rev-parse --is-shallow-repository`) — datas de git valem como recibo |
| Sessão fresca? | Sim — instância nova, sem contexto do gerador nem de rodadas anteriores |
| Árvore ao fim | limpa (`git status --short` vazio, exceto esta evidência) |
| Tipo do lote | `anchors` · amostra = **100%** dos itens |

### §Integridade — declaração obrigatória (§6 anti-gaming)

`abriu_evidencia_anterior: **true**`. **Não abri nenhum arquivo deliberadamente**, mas um comando de varredura executado no **final** da rodada —

```
rg --hidden -g '!.git/**' -g '!memory/requisitos/**' -n '_acionavel' .
```

— imprimiu, junto com os hits legítimos em `scripts/`, **linhas substantivas de `memory/sessions/*refutacao*`** de outros lotes (6897, 6908, 6914, 7224), incluindo metodologia e achados. Registro isto porque esconder seria a falha pior.

**O que isso NÃO contaminou (verificável pela ordem dos comandos):** toda a análise central — recomputação dos 64 shas, predicado do checker em `origin/main`, regeneração chave a chave dos 64, e a conferência das 2 mudanças de `_acionavel` contra a prosa do `gap.md` — foi **concluída antes** desse comando. Nenhuma conclusão desta evidência deriva do que apareceu ali; a exposição ocorreu ao medir *quem lê `_acionavel`*, e a resposta usada (4 arquivos não-`memory/`) veio do comando seguinte, já com `-g '!memory/**'`. A decisão de descartar ou não a rodada é do workflow.

### §3 — checklist do protocolo

- [x] Escopo re-medido pelo próprio refutador (não copiado do enunciado)
- [x] Tudo medido contra `origin/main` (`git show origin/main:<path>`, `git ls-tree`), nunca contra o diff
- [x] Corpo do PR / commit message **não** usados como evidência
- [x] Claims negativas com varredura contada + controle positivo
- [x] Sondas com exit code lido e controle positivo
- [x] Controle negativo para cada comparador
- [x] Árvore limpa após sondagens

---

## Escopo medido

`git diff --name-status origin/main...HEAD` devolve **64 arquivos, todos `M`, todos sob `memory/requisitos/**/*.map.json`** — idêntico à lista do enunciado (re-medido, não copiado). O diff total do PR é **exatamente esses 64** (nenhum arquivo fora de `memory/requisitos`): `131 insertions(+), 131 deletions(-)`.

**Conteúdo real do diff** (extraído linha a linha, filtrando `prototipo_sha`/`gerado_em`):

| Mudança | Ocorrências | Onde |
|---|---|---|
| `prototipo_sha` + `gerado_em` | 64 arquivos (128 linhas) | todos |
| `_acionavel: true → false` | 1 | `Compras/compras-grade-matrix.map.json` (parte `totais-on-the-fly`) |
| `_acionavel: false → true` | 1 | `Fiscal/fiscal-config.map.json` (parte `certificado-e-regime`) |
| newline final de arquivo | 1 | `Arquivos/arquivos-index.map.json` (`\ No newline at end of file` removido) |

Nenhum `*-gap.md` e nenhum arquivo sob `resources/js/**` foi tocado (`0` e `0`) — logo **todas as âncoras são pré-existentes**; o lote não promoveu nem criou âncora nenhuma.

---

## Tabela por grupo

| # | Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---:|---:|---:|---|
| 1 | Âncora existe em `origin/main` | 1310 | 1310 | 0 | 64 map em main (`git ls-tree`/`M` no diff) + 64 `gap_fonte` no disco + **571** `partes[].prototipo.arquivo` + **553** `partes[].vivo.arquivo` + 29 `mapping.source` + 29 `mapping.target`; 39 fontes distintas do frontmatter, **0 ausentes**. Controle negativo: `git ls-tree origin/main -- memory/requisitos/__nao_existe__.map.json` → vazio |
| 2 | Âncora não revogada · lida pelo leitor real | 141 | 141 | 0 | **64 regenerações** com `gerar()` + `fundirComExistente()` (o leitor real) · `ancora.mjs` em 10 telas → `âncora ✓` em 10/10 · 64 cruzamentos map×charter dono (`related_prototype`/`bundle_source`/`visual_source`) · 3 charters com `REVOGADA`/`MIS-ANCHOR` abertos e analisados |
| 3 | Ação × veredito · afirmação sobre código | 130 | 130 | 0 | as 2 mudanças de `_acionavel` conferidas **contra a linha da tabela do `gap.md`** (fonte que o gerador consome) + 64 `gerado_em` + 64 `prototipo_sha` recomputados |
| 4 | Célula íntegra · derivado == fonte | 5761 | 5761 | 0 | 64/64 `JSON.parse` OK + **5697 chaves** comparadas uma a uma entre a regeneração e o commitado (`flat()` recursivo, `_doc` ignorado por ser constante): **0 divergência** |
| 5 | Máquinas derivadas | 26 | 26 | 0 | `design-code-map-check --check --strict` rc=**0** · `plans-index --check` rc=**0** · `doc-id-index --check-collisions` rc=**0** · `requisitos-status <Mod> --check` nos 23 módulos |
| 6 | Scan PII | 7 | 7 | 0 | 7 padrões sobre as 131 linhas `+`, cada um com controle positivo sintético |
| | **TOTAL** | **7375** | **7375** | **0** | |

---

## REFUTADOS

**Nenhum.** Os dois candidatos que investiguei a fundo — as mudanças de `_acionavel` — resolveram-se a favor do lote, e a justificativa está documentada em §Observações (itens O-1 e O-2) por transparência, já que num lote de refresh de sha uma mudança semântica é exatamente o que um refutador deve atacar primeiro.

---

## Como cada grupo foi provado

### Grupo 1 · a âncora existe

Recomputei, independentemente do lote, o `prototipo_sha` de cada map: li o `gap_fonte`, resolvi o campo `prototipo:` do frontmatter com `resolverArquivosPrototipo()` e computei `computeProtoHash()` (as funções canônicas de `scripts/design/gerar-map.mjs`).

```
total 64 · shaOK 64 · shaBAD 0
main era stale (sha salvo != recomputado): 64
main já estava correto (mudança desnecessária): []
gap_fonte ausente: 0 · arquivo de protótipo faltando: 0 · sem protoArqs: 0
headSha sentinela ('sem-arquivo'/'sem-historico'): 0
gerado_em != 2026-09-14: 0
```

Os 64 novos valores **são** os corretos e **nenhum** dos 64 valores de `origin/main` era. Não há refresh desnecessário (nenhum arquivo entrou no lote já estando correto) nem sentinela mascarando ausência de fonte.

`mapping.source` (29) e `mapping.target` (29) resolvem no disco: **0 faltando**. Nenhum `partes[].vivo.arquivo` aponta para `Components/` ou `Layouts/` — a armadilha "âncora de fundação apontada pra consumidor de tela" **não ocorre** (lista vazia), e os 3 maps de fundação (`_DesignSystem/*`) não estão no lote.

### Grupo 2 · a âncora é lida pelo leitor real, e não está revogada

Rodei o **gerador canônico** sobre o `gap_fonte` de cada um dos 64 e fundi com o map de `origin/main` (`fundirComExistente`, o caminho exato que `--atualizar` percorre), comparando chave a chave com o que o lote entregou:

```
arquivos do lote: 64 | com divergência vs regeneração: 0
```

**Controle negativo do comparador** (obrigatório — comparador que nunca acusa não prova nada): o mesmo script apontado para `origin/main` acusa **64/64 divergentes**, listando `prototipo_sha` e `gerado_em` em cada um. O comparador morde.

`node scripts/design/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork/Wagner` em 10 telas → `âncora ✓` em 10/10, e o arquivo resolvido bate com o que o map usa em todas (ex.: `Cliente/Index` → `clientes-page.jsx`; `OficinaAuto/ServiceOrders/Board` → `oficina-page.jsx`; `Repair/ProducaoOficina/Index` → `repair-page.jsx`).

**Os 3 charters com `REVOGADA`/`MIS-ANCHOR`** (varredura contada: `rg --hidden -g '!.git/**' -il 'REVOGADA|MIS-ANCHOR' --glob '*.charter.md'` → 3 de 232 charters com `related_prototype`, controle positivo = 232):

| Charter | O que está revogado | Atinge o lote? |
|---|---|---|
| `Cliente/Index.charter.md:78` | a **onda de features** "PTDP Onda 1" (BrunaGreeting + SavedViews), reprovada por [W] | **Não** — `related_prototype: clientes-page.jsx` (l.5) segue ativo, e é a âncora que `clientes.map.json` usa |
| `Repair/ProducaoOficina/Index.charter.md:15` | `oficina-page.jsx` como âncora **desta tela** (MIS-ANCHOR, 2026-06-30) | **Não** — o map usa `repair-page.jsx`, coerente com `bundle_source` (l.7) |
| `Jana/Index.charter.md` | — | **Não** — tela fora do lote |

Os 2 maps que usam `oficina-page.jsx` (`ordens-servico-board`, `service-orders-board`) são de **outra tela** (`OficinaAuto/ServiceOrders/Board`), cujo charter declara `visual_source: oficina-page.jsx` (l.4) — para ela o arquivo é a âncora correta, como o próprio `design-code-map-check` registra ao explicar por que `kanban-producao-gap.md` está fora do denominador.

### Grupo 3 · as 2 mudanças de `_acionavel`

`_acionavel` é derivado por `ehAcionavel(p.acao)` (`gerar-map.mjs:186`) da **coluna "Ação" da tabela do `gap.md`** — não do campo `acao` do próprio map (que `fundirComExistente` preserva quando enriquecido à mão). `ehAcionavel` (`gerar-contrato.mjs:45-49`) devolve `false` só quando a ação começa por `Nada|Nenhuma|NÃO RESSUSCITAR|Não`.

| Arquivo · parte | Lote | Linha da fonte (`gap.md`) | Veredito |
|---|---|---|---|
| `compras-grade-matrix` · `totais-on-the-fly` | `true → false` | `compras-grade-matrix-gap.md:77` — Ação = **"Nada a fazer."** | **CONFIRMADO** — `false` é o valor correto; `true` em `origin/main` era drift map×gap, e o lote **corrigiu** |
| `fiscal-config` · `certificado-e-regime` | `false → true` | `fiscal-config-gap.md:14` — Ação = **"\*\*Decidir.\*\* Dos 4 cards do protótipo…"** | **CONFIRMADO** — `true` é o valor correto pela fonte; `false` em `origin/main` era drift |

Ambas são **correções de drift pré-existente** entre o map e o seu `gap_fonte`, produzidas pela regeneração — não invenções do lote. Abri as duas linhas citadas e conferi o texto (item 3: "a linha citada tem que conter o que se afirma").

### Grupo 5 · máquinas

```
scripts/governance/plans-index.mjs --check                      rc=0
scripts/governance/doc-id-index.mjs --check-collisions          rc=0
scripts/governance/design-code-map-check.mjs --check --strict   rc=0
```

O `design-code-map-check` no HEAD imprime literalmente `[OK] nenhum map.json com âncora quebrada ou sha stale`, sobre **67** maps.

**Controle positivo desse rc=0** (senão "verde" não prova execução): reproduzi o predicado exato do checker (`design-code-map-check.mjs:191-240` — os arquivos vêm de `partes[].prototipo.arquivo`, ignorando `TODO`/`n/a`) aplicado ao estado `origin/main` de cada map do lote:

```
origin/main, os 64 do lote, predicado do CHECKER:  STALE: 64 | OK: 0 | não-avaliado: 0
HEAD, TODOS os 67 maps:                            ainda STALE: 0
```

O conjunto do lote é **exatamente** o conjunto que o checker acusava. Não há stale omitido dentro do alcance do checker.

`requisitos-status.mjs <Mod> --check`: rc=0 em 9 módulos, rc=1 em 14. **O rc=1 é pré-existente e não é do lote** — provado em três frentes: (a) o gerador **não lê** `.map.json` (`grep -c 'map\.json\|prototipo_sha\|gerado_em' scripts/governance/requisitos-status.mjs` → **0**, rc=1 = *rodou e não achou*; controle positivo no mesmo arquivo: `grep -c 'requisitos'` → **15**); (b) o `_STATUS-GENERATED.md` do Repair não cita nenhum desses termos (0 hits); (c) nenhum `_STATUS-GENERATED.md` foi tocado pelo lote (0 arquivos). As mensagens são `não existe — rode com --write` e `está DRIFADO vs a árvore`, ambas sobre a árvore de requisitos, não sobre os maps.

---

## Scan PII

Sobre as **131 linhas `+`** de `git diff origin/main...HEAD -- memory/requisitos`. Cada padrão rodado também contra uma linha sintética que casa.

| # | Padrão | Hits | Controle positivo |
|---|---|---:|---|
| 1 | CPF pontuado | 0 | ✅ casou |
| 2 | CPF cru (11 dígitos isolados) | 0 | ✅ casou |
| 3 | CNPJ | 0 | ✅ casou |
| 4 | Telefone BR formatado | 0 | ✅ casou |
| 5 | Telefone cru (10–11 dígitos) | 0 | ✅ casou |
| 6 | E-mail | 0 | ✅ casou |
| 7 | Valor em reais (símbolo seguido de dígito — literal **não** reproduzido aqui, por causa do hook `block-brl-values-in-memory`) | 0 | ✅ casou |
| | **TOTAL** | **0** | **7/7** |

Nomes de cliente do CRM (`Larissa`, `Martinho`, `ROTA LIVRE`) nas linhas `+`: **0** (controle positivo do padrão: casou).

**Dois defeitos da minha própria sonda, corrigidos e registrados** (não escondidos):

1. **1ª rodada deu 6/7 controles**, porque montei o padrão de reais concatenando o símbolo cru — e o `$` é **metacaractere** de regex, então ele casava "R seguido de fim de linha". Corrigido escapando (`R\$`); o controle passou a casar. É a classe §5 2026-08-01 (*controle positivo antes de confiar no resultado*) pegando a si mesma.
2. **1ª rodada acusou 1 hit de "telefone cru"** em `"prototipo_sha": "sha256:2d2084338189"` — falso positivo: é a cauda hexadecimal de um hash, não telefone. Refinei os padrões 2 e 5 com *lookaround* hex (`(?<![\dA-Fa-f:])…(?![\dA-Fa-f])`) e validei nos dois sentidos: **controle negativo** (a linha de sha deixou de casar) **e** **controle positivo pós-refino** (um telefone real numa frase segue casando — o refinamento não cegou o padrão).

---

## Observações (NÃO contadas como erro)

**O-1 · `fiscal-config` fica com uma tensão interna visível, e ela é dívida do `gap.md`, não do lote.** A parte `certificado-e-regime` passa a ter `acao: "FECHADO em 2026-09-04 (item A5, PR 1/3)…"` ao lado de `_acionavel: true`. A contradição é real, mas a causa é o `gap.md:14` **não** ter sido atualizado quando o item fechou em 04/09 — ele ainda diz "**Decidir.**". O lote reproduz a fonte declarada; o `acao` com o veredito humano permanece intacto e não foi invertido nem apagado. Não contei como "reabre um veredito" por três razões medidas: o campo é derivado mecanicamente da fonte; a regeneração é o comportamento documentado e coberto por selftest (`gerar-map.mjs:290`); e `_acionavel` tem **zero leitores de produção** (varredura contada fora de `memory/`: 4 arquivos citam a string — `gerar-map.mjs`, que a **produz**; `governance/sdd-verification-ledger.json`, menção textual; e 2 do Jana, que são `heuristica_acionavel`, substring de outra coisa). **Ação sugerida ao dono, fora deste PR:** atualizar a Ação do `fiscal-config-gap.md:14` para refletir o fechamento — senão todo `--atualizar` futuro reintroduz o `true`.

**O-2 · O sentido oposto também ocorreu e é um ganho.** `compras-grade-matrix` tinha `_acionavel: true` sobre uma parte cuja Ação é "Nada a fazer." — o lote corrigiu para `false`. Registro para que a leitura de O-1 não vire "o lote mexeu em veredito": ele sincronizou os dois lados com a fonte, nas duas direções.

**O-3 · Existe 1 map STALE fora do lote — e ele está num PONTO CEGO do checker, não foi omitido.** `memory/requisitos/Essentials/tipos.map.json` tem `prototipo_sha: sha256:1b1cc5c4264f` enquanto a fonte (`hrm-page.jsx`, via `gap_fonte`) hoje vale `sha256:69d6f13d44d8` — exatamente o mesmo par antes→depois do `holidays-index.map.json`, que o lote **atualizou**. Ele não entrou porque **o checker nunca o avalia**: suas 7 partes têm `prototipo.arquivo: "n/a"`, então `arquivosPrototipoReais` fica vazio e o bloco de staleness (`design-code-map-check.mjs:235`) não roda. O mesmo vale para `_DesignSystem/pageheader-canon-v3.map.json` (12 partes) e `_DesignSystem/sidebar-v3-unificado.map.json` (17 partes), que porém legitimamente valem `sem-arquivo`. **Não é erro do lote** — o lote cobre 64/64 do que o checker acusa —, mas é uma lacuna real da régua: um map pode ficar stale para sempre sem que nenhum gate perceba, desde que suas partes declarem `n/a` no lado protótipo. Fica registrado para o dono do `design-code-map-check`.

**O-4 · O drift do `requisitos-status` (14 módulos rc=1) é anterior ao lote** — provado em §Grupo 5. Não conta contra o PR, mas `Arquivos`, `Atendimento`, `Backup`, `Compras`, `Dashboard`, `Essentials`, `Governance`, `PaymentGateway`, `Ponto`, `Repair`, `Sells`, `Superadmin`, `Suporte` seguem com `_STATUS-GENERATED.md` ausente ou drifado.

**O-5 · Por que 64 maps ficaram stale se o commit-base tocou 8 arquivos de protótipo.** Os 64 apontam para **39 fontes distintas** em **33 pares de sha distintos** (vários maps compartilham fonte: 7 para `repair-page.jsx`, 4 para `essenciais-page.jsx`, 4 para `superadmin-page.jsx`…). O intervalo desde a geração anterior (`gerado_em` de 24/08 a 06/09) contém 4 commits em `prototipo-ui/cowork/Wagner`, **669 arquivos distintos tocados**, incluindo `4f51a9ec78 refactor(prototipo): separar fontes por dono e remover paralelos (#7224)`. O staleness é acumulado, não causado só pelo commit-base — e isso é irrelevante para o veredito, porque a prova é aritmética: o hash do conteúdo atual difere do salvo em 64/64 e bate com o novo em 64/64.

**O-6 · Uma melhoria silenciosa, benigna.** `Arquivos/arquivos-index.map.json` ganhou o newline final que faltava (`\ No newline at end of file` sumiu do lado `-`). É efeito do `JSON.stringify` + escrita do gerador; não altera o JSON parseado.

---

## Comandos reproduzíveis

```bash
# escopo (re-medir, nunca copiar do enunciado)
git rev-parse --is-shallow-repository            # false
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l    # 64
git diff --stat origin/main...HEAD | tail -1     # 64 files, 131 ins, 131 del

# o que mudou ALÉM de sha/data (via node, agrupando por arquivo)
git diff origin/main...HEAD -- memory/requisitos > lote.diff
#   -> 2 linhas de _acionavel + 1 newline de EOF; o resto é prototipo_sha/gerado_em

# recomputar os 64 sha de forma independente (scratchpad/verify.mjs)
#   importa resolverArquivosPrototipo + computeProtoHash de scripts/design/gerar-map.mjs
#   -> total 64 · shaOK 64 · shaBAD 0 · main era stale: 64

# regeneração chave a chave (scratchpad/regen-diff.mjs): gerar() + fundirComExistente()
#   -> 64 arquivos | com divergência: 0            (5697 chaves)
# CONTROLE NEGATIVO: mesmo script comparando contra origin/main -> 64 divergências

# predicado do checker aplicado a origin/main (scratchpad/checker-predicate.mjs)
#   -> origin/main: STALE 64 / OK 0 ;  HEAD: STALE 0

# máquinas
node scripts/governance/design-code-map-check.mjs --check --strict   # rc=0
node scripts/governance/plans-index.mjs --check                      # rc=0
node scripts/governance/doc-id-index.mjs --check-collisions          # rc=0
node scripts/governance/requisitos-status.mjs <Mod> --check          # 9× rc=0, 14× rc=1 (pré-existente)

# âncora por tela
node scripts/design/ancora.mjs Cliente/Index --staging prototipo-ui/cowork/Wagner   # âncora ✓

# revogações (varredura contada + controle positivo)
rg --hidden -g '!.git/**' -il 'REVOGADA|MIS-ANCHOR' --glob '*.charter.md' .   # 3
rg --hidden -g '!.git/**' -l  'related_prototype'   --glob '*.charter.md' . | wc -l   # 232 (controle)

# a linha da fonte que decide cada _acionavel
sed -n '77p' memory/requisitos/Compras/compras-grade-matrix-gap.md   # "Nada a fazer."
sed -n '14p' memory/requisitos/Fiscal/fiscal-config-gap.md           # "**Decidir.** …"
```

---

```json
{
  "itens_verificados": 7375,
  "erros_confirmados": 0,
  "error_rate_pct": 0.0,
  "pii_hits": 0,
  "veredito": "aprovado"
}
```
