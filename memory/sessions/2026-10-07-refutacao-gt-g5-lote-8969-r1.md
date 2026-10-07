---
date: "2026-10-07"
topic: "Refutação GT-G5 r1 do lote #8969 — regeneração de prototipo_sha/gerado_em em 32 map.json: 234 itens medidos contra origin/main, 0 refutados, PII 0 hits (7/7 controles)"
authors: ["C"]
prs: [8969]
outcomes:
  - "Lote APROVADO: 32 map.json, 234 itens verificados (103 paths · 32 sha · 32 âncoras · 32 regenerações chave a chave · 32 gerado_em/stale · 3 máquinas), 0 erros, error_rate 0%"
  - "Os 32 maps eram EXATAMENTE os 32 stale em origin/main (82 maps, 80 com sha256:, 48 ok, 32 stale — nenhum stale fora do lote); o sha novo bate com contentHash dos blobs de origin/main"
  - "PII: 0 hits em 64 linhas `+` com 7/7 controles positivos casando; árvore limpa; nenhuma evidência anterior aberta"
---

# Session log 2026-10-07 — Refutação GT-G5 r1 · lote #8969 (32 map.json)

## TL;DR

Veredito **aprovado**. 234 itens verificados contra `origin/main` (8904fa751a), **0 erros confirmados**, error_rate **0,00%**, PII **0 hits** (7/7 controles positivos). O lote muda SÓ `prototipo_sha` + `gerado_em` em 32 `*.map.json` (64 linhas `+`, 64 `-`); os 32 eram exatamente os 32 maps stale de `origin/main`, o sha novo é o `contentHash` combinado dos blobs de `origin/main`, e a regeneração com `gerar-map.mjs --atualizar` reproduz os 32 arquivos chave a chave (1618 chaves, 0 diferenças).

## Cabeçalho

| Campo | Valor |
|---|---|
| Base | `origin/main` = `8904fa751ab3800b7a71d71f84a74cecea4055bd` |
| HEAD do lote | `28d46b1c039302727df3901b95f80d461894d3aa` (branch `claude/fix-design-maps-stale`) |
| merge-base | `59f777d978` — a branch está **atrás** de main (21 arquivos a mais em main; **nenhum** em `prototipo-ui/`, `scripts/`, `*-gap.md` ou nos 32 maps — medido com `git diff --name-status <mb> origin/main`) |
| Repo raso | `git rev-parse --is-shallow-repository` = **false** |
| Sessão fresca | sim — instância nova, zero contexto do gerador; nenhum `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje aberto |
| Corpo do PR / commit message | **não lidos como evidência** (são a claim) |
| Tipo | anchors · amostra 100% |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (refutador: fable; o commit do lote é `[CL]`)
- [x] Amostra: 100% anchors (32/32 arquivos, todo path, toda chave)
- [x] Cada item verificado contra `origin/main` (`git ls-tree` / `git show origin/main:<path>` lidos como blob), não contra o diff
- [x] Cada REFUTADO anotado com evidência — n/a, 0 refutados
- [x] Scan PII no diff — 7 padrões × 64 linhas `+`, 0 hits, 7/7 controles positivos
- [x] `error_rate_pct` calculado e < 2 (= 0)
- [ ] Entry no ledger — fora do mandato desta rodada (o refutador não escreve no ledger)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **32 `M`**, todos `*.map.json` (lista idêntica à do mandato). `git diff --numstat` da área: 64 `+` / 64 `-`. Chaves que diferem HEAD × `origin/main` por arquivo (medido chave a chave em node): **só `prototipo_sha` e `gerado_em`** nos 32 — `partes[]`, `tela`, `gap_fonte`, `acao`, `status`, `linhas`, `vivo.ancora`, `_acionavel` **idênticos** a `origin/main`.

## Tabela por grupo

| # | Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---:|---:|---:|---|
| 1 | Âncora existe em `origin/main` — todo path citado (`prototipo.arquivo` das partes, `vivo.arquivo`, arquivos do frontmatter `prototipo:` do gap, `gap_fonte`) | **103** paths distintos | 103 | 0 | `git ls-tree origin/main -- <path>` devolve blob em 103/103; controle negativo `memory/requisitos/Ponto/NAO-EXISTE.map.json` → vazio; nenhum proto fora de `prototipo-ui/`; vivo fora de `resources/js/Pages/` = 10 arquivos de `Modules/Whatsapp/Resources/js/Pages/**` (Page de módulo, legítimo) + `Modules/Ponto/Http/Controllers/BancoHorasController.php` (parte de backend declarada, existe) — nenhuma fundação (`Components/**`) apontada para consumidor |
| 2 | `prototipo_sha` novo = `contentHash` combinado dos blobs de `origin/main` | **32** | 32 | 0 | recomputado em node lendo `git show origin/main:<proto>` com o `contentHash` de `cowork-mirror-freshness.mjs`, pelos DOIS resolvedores (arquivos das partes, como o checker; e frontmatter `prototipo:` do gap, como o gerador) — 32/32 batem nos dois; o sha salvo em `origin/main` **não** batia em 32/32 (stale real) |
| 3 | Âncora não revogada / lida pelo leitor real | **32** telas | 32 | 0 | `node scripts/design/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork/Wagner` → `âncora ✓` em 32/32, rc=0; nenhuma `REVOGADA`/`MIS-ANCHOR`; o arquivo resolvido é o mesmo que o map cita (ponto-telas · essenciais-page · fiscal-page · kb-page · financeiro-page · pg-cobranca · compras · manufacturing-* · produtos/inbox via bundle · vendas-page) |
| 4 | Regeneração com o gerador do repo, chave a chave | **32** arquivos (1618 chaves) | 32 | 0 | `node scripts/design/gerar-map.mjs <gap_fonte> --atualizar` (stdout, não escreve) para os 32 → diff chave a chave contra HEAD (topo + cada parte por id): **0 diferenças em 1618 chaves**, 0 partes ausentes/novas; `acao`, `_acionavel`, `status`, `linhas`, `vivo.ancora` idênticos |
| 5 | `gerado_em` + premissa "stale do main" | **32** | 32 | 0 | `gerado_em` = `2026-10-07` nos 32 (é o que `--atualizar` grava — `hoje`); varredura dos **82** `*.map.json` de `origin/main`: 80 com `sha256:`, 2 legado, **48 ok, 32 STALE** — os 32 stale são exatamente o lote (0 stale fora, 0 do lote não-stale); os protótipos mudaram **depois** dos `gerado_em` antigos (`ponto-telas.jsx` 2026-10-06 `#8806`; `essenciais-page.jsx` 2026-10-07; `fiscal-page.jsx` 2026-10-06; `kb-page.jsx` 2026-10-07 `#8897`; `financeiro-page.jsx` 2026-10-06) |
| 6 | Máquina derivada | **3** | 3 | 0 | `design-code-map-check.mjs --check --strict` → `[OK] nenhum map.json com âncora quebrada ou sha stale`, **rc=0** · `doc-id-index.mjs --check-collisions` → `0 colisão em 2927 ids`, **rc=0** · `plans-index.mjs --check` → em dia, **rc=0**. Nenhum arquivo "regenerado mas fora do diff": o lote só afirma regenerar os 32 maps e são os 32 do diff |
| | **Total** | **234** | **234** | **0** | |

## REFUTADOS

Nenhum.

## Observações não contadas

1. **`acao` do map ≠ coluna Ação do gap em 25 partes de 7 maps** (Financeiro/unificado 12 · Ponto/configuracoes-index 6 · intercorrencias-index 3 · intercorrencias-create 2 · importacoes-create 1 · importacoes-show 1). É divergência **pré-existente em `origin/main`** (o lote não toca `acao`) e **por desenho**: `--atualizar` preserva `acao` por id (docblock do `gerar-map.mjs`, "preserva o preenchido") — o map carrega a versão mais detalhada/medida (ex.: unificado `header-acoes`: gap diz "aplicar delta", map diz "no-op — ENTREGUE (medido 2026-09-21)…"). Não é erro do lote; se for dívida, é do gap, não deste PR.
2. **`gerado_em` do map não é a idade da afirmação.** O checker lê `gerado_em` do **gap** para a "idade da afirmação" (bloco `idadeDaAfirmacao`), não o do map — carimbar `2026-10-07` no map não rejuvenesce a prosa das `acao` (que seguem datadas por dentro: "medido em 2026-09-29", "#8118", etc.).
3. A branch está atrás de `main` (merge-base `59f777d978`): nenhum dos 21 arquivos que main ganhou toca o lote, os protótipos, os gaps ou os scripts — verificado por `git diff --name-only origin/main HEAD -- prototipo-ui scripts 'memory/requisitos/**/*-gap.md'` = vazio, logo a árvore de trabalho é equivalente a `origin/main` para o que as sondas leem.
4. Lição de sonda, registrada para honestidade: o controle positivo do padrão de reais **não casou** na 1ª rodada (6/7) — o heredoc colapsou o par de barras do `\s`/`\d` (§5 2026-08-19) e o símbolo virou âncora de fim de linha. O controle positivo pegou antes de o "0 hits" entrar no relatório; corrigido com `\x24` por Edit, 7/7.
5. 2 maps de `origin/main` têm `prototipo_sha` legado (git-sha) e estão fora do lote por não serem `sha256:` — nada a fazer aqui.

## Scan PII

Sobre as **64** linhas `+` de `git diff origin/main...HEAD -- memory/requisitos` (32 × `gerado_em` + 32 × `prototipo_sha`, confirmado por `uniq -c` após mascarar valor):

| Padrão | Hits | Controle positivo |
|---|---:|---|
| CPF pontuado | 0 | casou |
| CPF cru (11 dígitos isolados) | 0 | casou |
| CNPJ | 0 | casou |
| Telefone BR | 0 | casou |
| Telefone cru (10–11 dígitos) | 0 | casou |
| E-mail | 0 | casou |
| Valor em reais (símbolo + dígito — padrão não reproduzido aqui) | 0 | casou |

Nomes de cliente do CRM: 0 (as linhas `+` só carregam hash e data).

## Comandos reproduzíveis

```bash
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos            # 32 M
git diff --name-only origin/main HEAD -- prototipo-ui scripts 'memory/requisitos/**/*-gap.md'   # vazio
git ls-tree origin/main -- memory/requisitos/Ponto/NAO-EXISTE.map.json      # controle negativo: vazio
for f in $(git diff --name-only origin/main...HEAD -- memory/requisitos); do
  gap=$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).gap_fonte)' "$f")
  node scripts/design/gerar-map.mjs "$gap" --atualizar > /dev/null   # stderr: "# N partes · prototipo_sha=…"
done                                                                   # diff chave a chave vs HEAD: 0/1618
for t in <Mod/Tela de cada map>; do node scripts/design/ancora.mjs "$t" --staging prototipo-ui/cowork/Wagner; done   # 32× "âncora ✓"
node scripts/governance/design-code-map-check.mjs --check --strict     # rc=0
node scripts/governance/doc-id-index.mjs --check-collisions            # rc=0
node scripts/governance/plans-index.mjs --check                        # rc=0
# sha de origin/main: contentHash(git show origin/main:<proto>) combinado como computeProtoHash (scripts/design/gerar-map.mjs)
```

```json
{"itens_verificados": 234, "erros_confirmados": 0, "error_rate_pct": 0, "pii_hits": 0, "veredito": "aprovado"}
```
