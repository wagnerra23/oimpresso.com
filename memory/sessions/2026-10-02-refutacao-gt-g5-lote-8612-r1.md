---
date: "2026-10-02"
topic: "Refutação GT-G5 r1 do lote #8612 — API-CONTRATO-v1 §9–§12 movidas para api/tela-NN.md + 3 _STATUS regerados: 308 itens, 1 erro (0,32%), 0 PII — APROVADO"
authors: ["C"]
prs: [8612]
outcomes:
  - "Conservação de conteúdo provada por script: 188/188 linhas não-vazias das §9–§12 de origin/main presentes nos 12 arquivos novos; as 20 linhas não-verbatim são exatamente as transformações declaradas (16 títulos, 2 ponteiros §9.2→link, 1 link ADR 0426 com ../ a mais, 1 prefixo de área)"
  - "Âncoras 28/28 resolvem em HEAD (controle negativo = False); rotas 33/33 idênticas entre routes/api.php de origin/main e routes/api/app/*.php, 0 rotas soltas; requisitos-status Ponto/Repair/Sells --check e plans-index --check rc=0, regen byte-idêntico"
  - "1 REFUTADO leve: a tabela da nova §9 promete cobrir 'código e testes que citam a numeração antiga', mas OficinaController.php:18 cita '§11' nu e a tabela só tem §11.1/§11.2/§11.4 — error_rate 0,32% < 2%, lote APROVADO; PII 0 hits com 7/7 controles positivos"
---

## TL;DR

**APROVADO.** 308 itens verificados em 6 grupos · 1 erro confirmado (leve: citação `§11` nua em `app/Http/Controllers/Api/App/OficinaController.php:18` sem linha correspondente na tabela da nova §9) · error_rate **0,32%** (< 2%) · PII **0 hits** com 7/7 controles positivos casando.

## Cabeçalho

| Campo | Valor |
|---|---|
| Base (origin/main) | `e06fc6e69f` |
| HEAD (branch `claude/app-api-arquivos-por-tela`) | `34e8676712` |
| Repo raso | `false` (`git rev-parse --is-shallow-repository`) |
| Sessão fresca | sim — instância nova, sem contexto do gerador |
| Modelo | Fable 5.1 (tier máximo disponível) |
| Arquivo de evidência pré-existente | não (`ls` rc=2 antes de escrever) |
| `abriu_evidencia_anterior` | false — nenhum `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje foi aberto |
| Corpo do PR / commit message lidos como evidência | não |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable 5.1 = tier máximo; igualdade só aceita no máximo)
- [x] Amostra: 100% anchors (tipo do lote = anchors; sem prosa destilada amostrada, logo sem seed)
- [x] Cada item verificado contra o código real em origin/main (`git show origin/main:<path>` com `MSYS_NO_PATHCONV=1`, `git ls-tree`), não contra o texto do PR
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII no diff (7 padrões × linhas `+` × controle positivo) — 0 hits
- [x] `error_rate_pct` calculado e < 2
- [ ] Entry no ledger — **fora do meu mandato** (o prompt proíbe escrever no ledger; fica para o gerador/humano)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **16 arquivos**: 1 M (`AppMobile/API-CONTRATO-v1.md`), 12 A (`AppMobile/api/tela-{03,05,06,07,19,20,23,25,26,29,30,39}-*.md`), 3 M (`Ponto|Repair|Sells/_STATUS-GENERATED.md`). Bate com o lote declarado. `git ls-tree HEAD -- memory/requisitos/AppMobile/api/` = 12 entradas (nenhum arquivo extra na pasta). Contexto fora de `memory/requisitos`: `routes/api.php` M + 13 A em `routes/api/app/`.

## Resultado por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1. Conservação de conteúdo | 233 | 233 | 0 | `conserva.py` + `blocos.py`: 188 linhas não-vazias das §9–§12 de origin/main (168 verbatim + 20 transformadas, cada uma conferida contra a lista declarada) · 17 classes de linha nova nos arquivos (todas declaradas) · 12 títulos `(tela NN)` × nome de arquivo · 12 seções `###` caindo no arquivo da tela certa, com ordem preservada (diff direto para 11.1/11.2) · 4 linhas de abertura da §11 em `tela-07` (§9, §10 e §12 não tinham texto de abertura em origin/main) |
| 2. Âncoras | 41 | 41 | 0 | `links.py`: 28 links relativos (12 arquivos novos + nova §9) resolvidos para path e checados com `git ls-tree HEAD`; 12 linhas da tabela §x.y → arquivo que de fato contém a seção (mapeamento por 1ª linha de conteúdo de cada `###` antigo); 1 controle negativo (`api/tela-99-nao-existe.md` → False) |
| 3. Afirmações da nova §9 | 6 | 5 | 1 | (a) 12 arquivos no padrão `api/tela-NN-<nome>.md` ✓ · (b) rotas em `routes/api/app/<área>.php`: 13 arquivos, 33/33 statements idênticos ao grupo `app` de origin/main (sets normalizados, `diff` vazio), 0 `Route::` solto no grupo em HEAD ✓ · (c) "sem mudança de conteúdo" ✓ (grupo 1) · (d) "ponteiros internos viraram links" ✓ (2 linhas em tela-29) · (e) "abertura de área → 1ª tela" ✓ · (f) "código e testes que citam a numeração antiga continuam valendo por esta tabela" ✗ — ver REFUTADOS |
| 4. Célula íntegra (tabela §9) | 14 | 14 | 0 | `awk -F'|'`: 14 linhas (cabeçalho + separador + 12), todas com exatamente 3 pipes; 12 links da tabela não truncados (resolvidos no grupo 2) |
| 5. Máquina derivada | 7 | 7 | 0 | `requisitos-status.mjs Ponto/Repair/Sells --check` rc=0/0/0 · `plans-index.mjs --check` rc=0 · regen `--write` dos 3 e `diff -q` contra HEAD = idêntico (árvore restaurada; `git status --short` vazio) |
| 6. Scan PII | 7 | 7 | 0 | `pii.py` sobre 306 linhas `+` do diff de `memory/requisitos`: 0 hits em 7 padrões; 7/7 controles sintéticos casaram |
| **Total** | **308** | **307** | **1** | error_rate = 1/308 = **0,32%** |

## REFUTADOS

### R1 — `API-CONTRATO-v1.md` nova §9: a tabela não cobre a citação `§11` nua que existe no código

- **Arquivo:** `memory/requisitos/AppMobile/API-CONTRATO-v1.md`, §9 (linhas 349–350 em HEAD): *"Código e testes que citam a numeração antiga continuam valendo por esta tabela (fixa; não acrescente linhas)"*.
- **Evidência:** `git grep -nE "§11[^.0-9]|§11$" HEAD -- app tests Modules routes` devolve, para o contrato do app, **1 de 1**: `app/Http/Controllers/Api/App/OficinaController.php:18` → `memory/requisitos/AppMobile/API-CONTRATO-v1.md §11 (Onda D)`. A tabela tem linhas para `§11.1`, `§11.2`, `§11.4` — nenhuma para `§11` (a área, cujo texto de abertura foi para `tela-07`). As outras 20 citações de `§9.x/§10.x/§11.x/§12.x` em `app/`, `tests/`, `Modules/` e `.github/workflows/sells-pest.yml` (varredura `git grep -nE "§(9|10|11|12)\.[0-9]" HEAD` filtrada por `contrato|AppMobile|API-CONTRATO`, 20 de 20 conferidas) têm linha na tabela.
- **Por que conta como erro:** a frase é uma claim de cobertura ("continuam valendo por esta tabela") sobre um conjunto enumerável, e o conjunto tem 1 membro fora. **Gravidade: leve** — o leitor resolve pelas 3 linhas `§11.x` + o prefixo `**Área (11. …)**` em `tela-07`; nenhum link quebra, nenhum conteúdo some. Conserto óbvio (1 linha `§11 → tela-07-ordens-de-servico.md`, ou ajustar o docblock do controller) é decisão do gerador — a tabela se declara "fixa".

## Observações não contadas

1. **`§9.4` e `§9.3` ainda citados no próprio v1** (§8 "Ajustes de escopo", linhas 340–341 em HEAD) sem virar link — `grep -cE "§(9|10|11|12)\.[0-9]"` no v1 = 15 ocorrências (12 da tabela + 2 da §8 + 1 na prosa da §9). Resolvem pela tabela; a frase "ponteiros internos viraram links" valeu para os arquivos novos, não para o v1. Não contado: não há âncora quebrada.
2. **Ponteiros `§6`, `§0`, `§7.1` e "Mesma permissão da 07" dentro dos arquivos novos** continuam como texto solto apontando para o v1. O cabeçalho de cada arquivo cobre `§0` e `§6`; `§7.1` (em `tela-30`) não está no cabeçalho. Preservação literal, como a claim diz — não é erro.
3. **A minha primeira sonda de reais estava cega**: o símbolo de moeda montado sem escape virou âncora de fim-de-linha no regex e o controle positivo **falhou** (6/7). Corrigido e re-rodado → 7/7. Fica como recibo de que o controle positivo fez o trabalho dele (§5 2026-08-01).
4. **`php -l` nos 14 arquivos de rota não rodou** (`php` fora do PATH nesta máquina) — a sintaxe PHP das rotas não foi medida aqui; a identidade dos 33 statements foi.
5. **Os 3 `_STATUS-GENERATED.md`** mudam contadores (ex.: Sells `US no SPEC 57→59`, `UC declarados 73→89`) que vêm de outros merges já presentes na branch, não deste lote; o que o lote garante é `--check` rc=0 em HEAD e regen idêntico — confirmado.
6. **Ordem de carga das rotas:** `glob()` do PHP devolve ordenado; os 13 arquivos têm prefixos de path disjuntos entre si, então a ordem alfabética não cria colisão. Não medido em runtime (`route:list`) — fora do escopo do lote de `memory/requisitos`.
7. Nenhum doc do repo usa âncora `API-CONTRATO-v1.md#…` (`git grep "API-CONTRATO-v1.md#"` → 0, rc=1), logo mover headings não quebra link de heading em lugar nenhum.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`, 306 linhas)

| Padrão | Hits | Controle positivo |
|---|---|---|
| CPF pontuado | 0 | casou |
| CPF cru (11 dígitos isolados) | 0 | casou |
| CNPJ | 0 | casou |
| Telefone BR | 0 | casou |
| Telefone cru (10–11 dígitos) | 0 | casou |
| E-mail | 0 | casou |
| Valor em reais (símbolo + dígito; padrão descrito, não reproduzido) | 0 | casou |

`pii_hits = 0` · controles 7/7.

## Comandos reproduzíveis

```bash
# estado
git rev-parse --is-shallow-repository; git rev-parse --short HEAD origin/main
git diff --name-status origin/main...HEAD -- memory/requisitos

# fonte antiga (Git Bash: MSYS_NO_PATHCONV=1 no show)
MSYS_NO_PATHCONV=1 git show origin/main:memory/requisitos/AppMobile/API-CONTRATO-v1.md > old_v1.md

# conservação (scripts no scratchpad da sessão: conserva.py / blocos.py — lógica: linhas não-vazias
# das §9–§12 antigas ∈ união dos api/tela-*.md; sobras = transformações declaradas; título (tela NN) × nome)
PYTHONIOENCODING=utf-8 python conserva.py; PYTHONIOENCODING=utf-8 python blocos.py

# âncoras (links.py: resolve cada [..](rel) e checa git ls-tree HEAD -- <path>; controle negativo tela-99)
PYTHONIOENCODING=utf-8 python links.py

# rotas: 33 statements do grupo app em origin/main × 13 arquivos novos (continuação ->cep juntada)
MSYS_NO_PATHCONV=1 git show origin/main:routes/api.php | sed -n '/prefix(.app.)/,$p' | perl -0pe 's/\n\s+->/ ->/g' \
  | grep -E "^\s*Route::(get|post|put|patch|delete)\(" | sed -E 's/^\s+//' | sort > old_routes.txt
cat routes/api/app/*.php | perl -0pe 's/\n\s+->/ ->/g' | grep -E "^\s*Route::(get|post|put|patch|delete)\(" | sed -E 's/^\s+//' | sort > new_routes.txt
diff old_routes.txt new_routes.txt && echo IDENTICAS
grep -cE "^\s*Route::(get|post|put|patch|delete)\(" routes/api.php   # 0

# máquina derivada
for m in Ponto Repair Sells; do node scripts/governance/requisitos-status.mjs $m --check; echo rc=$?; done
node scripts/governance/plans-index.mjs --check; echo rc=$?

# citações da numeração antiga no código
git grep -nE "§(9|10|11|12)\.[0-9]" HEAD -- . ':!memory/requisitos/AppMobile/' | grep -iE "contrato|AppMobile|API-CONTRATO"
git grep -nE "§11[^.0-9]|§11$" HEAD -- app tests Modules routes | grep API-CONTRATO   # 1 hit

# tabela
sed -n '/^| Antiga/,/^$/p' memory/requisitos/AppMobile/API-CONTRATO-v1.md | awk -F'|' '{print NF-1}' | sort | uniq -c   # 14× 3
```

```json
{"itens_verificados": 308, "erros_confirmados": 1, "error_rate_pct": 0.32, "pii_hits": 0, "veredito": "aprovado"}
```
