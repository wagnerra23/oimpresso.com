---
date: "2026-09-28"
topic: "Refutação GT-G5 r1 do lote #8073 (gap.md + map.json das threads 23-26 do Ponto): 220 itens verificados contra origin/main, 1 refutado, PII 0/7 controles ok — aprovado"
authors: ["C"]
prs: [8073]
outcomes:
  - "Lote APROVADO: error_rate 0,45% (1 de 220) — abaixo do teto de 2% do PROTOCOLO-REFUTADOR-BACKFILL §2.6"
  - "1 refutado: relatorios-index.map.json parte `rodape-legal` afirma 'mesma copy do charter' e o charter Relatorios/Index não carrega copy de rodapé nenhuma (só uma frase de Mission com redação diferente)"
  - "PII: 0 hits em 1071 linhas `+`, 7 de 7 controles positivos casaram; 4 checks de máquina (design-code-map-check --strict · requisitos-status Ponto · plans-index · doc-id-index) rc=0"
---

## TL;DR

Veredito **aprovado**: 220 itens verificados contra `origin/main` (e526181091), **1 erro confirmado** (error_rate **0,45%**), **0 hits de PII** com 7/7 controles positivos. O único refutado é uma citação de fonte inexistente (`rodape-legal` → "mesma copy do charter"); todas as âncoras, linhas citadas, decisões da ATA, "PARAR SE" das threads e afirmações sobre código fecharam.

## Cabeçalho

| campo | valor |
|---|---|
| PR / lote | #8073 · branch `claude/ponto-gap-threads-23-26` · rodada r1 |
| base | `origin/main` = `e526181091` |
| HEAD | `b85d228dc3` |
| repo raso | `git rev-parse --is-shallow-repository` → **false** (datas de git valem) |
| sessão fresca | sim — instância nova, sem contexto do gerador; **não** abri `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje; corpo do PR / commit message **não** usados como evidência |
| modelo refutador | fable (tier máximo) |
| tipo | prosa · amostra 5 de 16 arquivos (31,25%, ≥ 30%) · dentro de cada arquivo, 100% das afirmações |
| base citada pelo lote | o lote diz `origin/main e4289e688`; é o pai de HEAD e ancestral de `origin/main` (1 commit atrás). **Os 10 blobs citados são idênticos** entre `e4289e688` e `e526181091` (medido por `git ls-tree`), então nenhuma linha citada ficou stale — não é erro, fica como observação |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (fable = tier máximo; igualdade só no tier máximo)
- [x] Amostra: 100% anchors dos arquivos amostrados / ≥30% prosa (seed declarada abaixo)
- [x] Cada item verificado contra o código real em `origin/main`, não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff (7 padrões + 7 controles positivos) — hits = 0
- [x] `error_rate_pct` calculado e < 2 (0,45)
- [ ] Entry no ledger — **não é deste artefato** (mandato: não escrever no ledger; o gerador/humano adiciona a entry no mesmo PR)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **16 arquivos, todos `A`** (8 `-gap.md` + 8 `.map.json` em `memory/requisitos/Ponto/`), batendo com a lista do mandato.

**Amostra determinística** — seed `b85d228d`; `sha1(seed + path)` ordenado asc; `ceil(0.3 × 16) = 5`:

| sha1 (prefixo) | arquivo |
|---|---|
| `044f4832` | `memory/requisitos/Ponto/importacoes-index.map.json` |
| `14c15d4e` | `memory/requisitos/Ponto/importacoes-index-gap.md` |
| `1945cd37` | `memory/requisitos/Ponto/colaboradores-index.map.json` |
| `28e2b298` | `memory/requisitos/Ponto/relatorios-index.map.json` |
| `5e30b691` | `memory/requisitos/Ponto/configuracoes-reps.map.json` |

Os 3 `-gap.md` que são `gap_fonte` dos map.json amostrados (`colaboradores-index`, `relatorios-index`, `configuracoes-reps`) foram abertos **como fonte de comparação** das células (item 4), não como prosa amostrada.

## Tabela por grupo

| # | Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---:|---:|---:|---|
| 1 | Âncora existe em `origin/main` | 30 | 30 | 0 | `git ls-tree origin/main -- <path>` devolve blob para os 19 paths únicos citados (protótipo, 4 `.tsx`, 4 charters, 4 threads, ATA, routes.php, 4 controllers) + 2 links relativos (`importacoes-create-gap.md`/`-show-gap.md`, no lote) + 4 `gap_fonte` + threads 12/17/27 citadas + commit `e4289e688` ancestral. Controle negativo: `resources/js/Pages/Ponto/Importacoes/NAO-EXISTE.tsx` → MISSING. Nenhuma âncora aponta `Components/**` |
| 2 | Âncora não revogada · lida pelo leitor real | 13 | 13 | 0 | `node scripts/design/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork/Wagner` → `âncora ✓ [related_prototype (charter)] ponto-telas.jsx` nas 4 telas; 4 charters sem `REVOG`/`MIS-ANCHOR`; `gerar-map.mjs --atualizar` regenerado a partir de cada gap.md: `tela`, `gap_fonte`, `prototipo_sha` (`sha256:e4d0b5a3707e`) e ids das partes idênticos nos 4; `fmVal` lê `tela`/`prototipo`/`tela_viva`/`gerado_em` (`gerar-map.mjs:165-209`) e os 4 gap.md têm os 4 campos |
| 3 | Ação × veredito da prosa · afirmação sobre código | 61 | 60 | **1** | Abri em `origin/main` cada linha citada de `ponto-telas.jsx` (:564-659, :716-846, :849-931, :946-989), dos 4 `.tsx`, dos 4 controllers e do `routes.php:112-113`; contei colunas (proto 9 × vivo 8 em Importações; proto 9 × vivo 7 em Colaboradores; proto 5 c/ CNPJ × vivo 5 c/ Ativo em REPs); conferi as 8 decisões `D-*` na ATA-DECISOES-2026-09-14 (incl. as citações literais) e os 4 "PARAR SE" nas threads 23-26; charters (20/pág, 25/pág, "quando (humanizado)", CTA, Non-Goals) |
| 4 | Célula íntegra · `acao` == célula · linhas/status por parte | 104 | 104 | 0 | 25 partes × 4 (linhas proto · linhas vivo · status coerente com a célula · `acao` == célula): `gerar-map.mjs` sem `--atualizar` (esqueleto fresco) dá `acao` e `_acionavel` **byte-idênticos** ao map nas 25 partes; toda linha de tabela dos 4 gap.md tem exatamente 4 pipes (3 células, nenhum pipe cru); nenhum code-span truncado; nada do mock rotulado como "vivo" |
| 5 | Máquina derivada | 5 | 5 | 0 | rc literal abaixo; árvore limpa após as sondas |
| 6 | Scan PII | 7 | 7 | 0 | 7 padrões sobre as 1071 linhas `+`, cada um com controle positivo sintético |
| | **Total** | **220** | **219** | **1** | |

## REFUTADOS

### R-1 · `memory/requisitos/Ponto/relatorios-index.map.json` · parte `rodape-legal` (e a célula-fonte em `relatorios-index-gap.md:28`)

- **Afirmação do lote:** `"acao": "Incorporar — mesma copy do charter."`
- **O que `origin/main` diz:** `resources/js/Pages/Ponto/Relatorios/Index.charter.md` (blob `68787693`) não tem rodapé, `## Contrato visual`, nem copy alguma sobre AFD/AFDT/AEJ + ReportService. As únicas frases próximas são a Mission `:25` (*"Hoje só o Espelho está disponível; os demais aparecem como 'Em breve'"*) e o Non-Goal `:38` (*"a maioria hoje `abort(501)`"*) — nenhuma é copy de rodapé e nenhuma coincide com a do protótipo (`ponto-telas.jsx:928`: *"Relatórios legais (AFD/AFDT/AEJ) seguem o layout da Portaria 671/2021 Anexo I. Hoje só o Espelho está implementado em ReportService."*).
- **Por que é erro do lote:** a ação aponta o implementador para uma fonte que não existe ("a copy do charter"). Quem for executar procura no charter e não acha — ou pior, herda a Mission como se fosse a copy contratada. A fonte real da copy é o protótipo `:928` (soberano no eixo FORMA, UI-0029), e é isso que a célula deveria dizer. Mesma família de "citar fonte inexistente" (§5 2026-09-04).
- **Linha/commit:** `relatorios-index-gap.md:28` (coluna Ação) · `relatorios-index.map.json:126` · commit `b85d228dc3`.

## Observações não contadas

1. **`status: prototipo-a-frente` em 2 partes "ausente nos dois lados"** — `importacoes-index.map.json` › `filtro-por-estado-e-tipo` e `configuracoes-reps.map.json` › `inativar-rep` têm `prototipo.arquivo = n/a` **e** `vivo.arquivo = n/a`, mas `status: prototipo-a-frente`. Semanticamente o "à frente" ali é a **decisão da ATA** (D-IMP-FILTRO / D-REP-ATIVO), não o protótipo. Não contei como erro porque `status` é texto livre — nenhum consumidor (`design-code-map-check.mjs`, `consumir-map.mjs`, `gerar-map.mjs`) define enum, e os precedentes em `origin/main` para n/a+n/a usam `decidir-w` (`Essentials/settings-index.map.json`) e `vivo-a-frente` (`Essentials/tipos.map.json`). Fica registrado: se um dia o vocabulário virar contrato, essas 2 partes precisam de um valor tipo `ausente-nos-dois`/`decisao-w`.
2. **Base citada `origin/main e4289e688`** — no momento da refutação `origin/main` já é `e526181091`; blobs idênticos (ver cabeçalho). Fato datado correto no dia da geração.
3. **`ColaboradorController@index (:15-60)`** — o método vai de `:15` a `:62`; range informativo, o conteúdo citado (`paginate(25)` em `:41`, filtro só por `q`) está dentro.
4. **`Relatorios/Index.tsx:55-60` como "a regra vem do backend"** — a linha citada é o comentário da interface (*"Quem decide é o backend"*), e o `RelatorioController.php:45-47` confirma `requer_colaborador` no payload. Citação de comentário, mas o código concorda — não é erro.
5. Item do mandato "path sob `Pages/**` vs `Components/**`": **0** âncoras do lote apontam `resources/js/Components/**` (todas as 4 `tela_viva` são `Pages/Ponto/**`).

## Scan PII

Sobre `git diff origin/main...HEAD -- memory/requisitos | grep '^+'` (1071 linhas, excluindo `+++`). O 7º padrão é o símbolo de moeda brasileira seguido de espaço opcional e dígito — montado por `charCode` na sonda, **não** reproduzido aqui.

| padrão | hits | controle positivo |
|---|---:|---|
| CPF pontuado (`ddd.ddd.ddd-dd`) | 0 | OK |
| CPF cru (11 dígitos isolados) | 0 | OK |
| CNPJ pontuado | 0 | OK |
| telefone BR `(dd) 9dddd-dddd` | 0 | OK |
| telefone cru (10–11 dígitos isolados) | 0 | OK |
| e-mail | 0 | OK (os 4 `@` do diff são `Controller@método`) |
| valor em reais | 0 | OK |
| **total** | **0** | **7/7** |

Nomes: varredura de pares `Maiúscula Maiúscula` nas linhas `+` devolve só termos de domínio ("Code Connect", "Reforma Trabalhista", "Ações Cancelar"); nenhum nome de cliente do CRM. O placeholder `12345678000100001` (`Reps.tsx:112`, 17 dígitos) já está em `origin/main` e é declarado fictício pelo próprio lote.

## Máquina derivada (rc literal)

| comando | rc | saída relevante |
|---|---:|---|
| `node scripts/governance/design-code-map-check.mjs --check --strict` | 0 | `[OK] nenhum map.json com âncora quebrada ou sha stale. 82 âncora(s) TODO pendente(s)` · 5 WARN pré-existentes (maps de outros módulos sem `gerado_em`) |
| `node scripts/governance/requisitos-status.mjs Ponto --check` | 0 | `✓ memory/requisitos/Ponto/_STATUS-GENERATED.md em dia.` |
| `node scripts/governance/plans-index.mjs --check` | 0 | `✓ PLANS-INDEX-GENERATED.md em dia (8 registrados, 27 pendentes)` |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | 0 | `OK: 0 colisão de id em 2850 ids` |
| `git status --short` após todas as sondas (incl. 8 runs do `gerar-map.mjs`) | 0 | vazio |

Nenhum arquivo derivado que o lote diga ter regenerado está fora do diff (o diff são só os 16 arquivos novos; `_STATUS-GENERATED.md` e `PLANS-INDEX-GENERATED.md` estão em dia sem precisar de regeneração).

## Comandos reproduzíveis

```bash
# base / HEAD / raso / lote
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos

# amostra determinística (seed b85d228d)
git diff --name-status origin/main...HEAD -- memory/requisitos | awk '{print $2}' \
 | while read p; do printf '%s  %s\n' "$(printf '%s' "b85d228d$p" | sha1sum | cut -c1-40)" "$p"; done | sort | head -5

# grupo 1 — existência (+ controle negativo)
for p in prototipo-ui/cowork/Wagner/ponto-telas.jsx resources/js/Pages/Ponto/Importacoes/Index.tsx \
  resources/js/Pages/Ponto/Relatorios/Index.charter.md Modules/Ponto/Http/Controllers/RelatorioController.php \
  prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/ATA-DECISOES-2026-09-14.md \
  resources/js/Pages/Ponto/Importacoes/NAO-EXISTE.tsx; do git ls-tree origin/main -- "$p"; done
git merge-base --is-ancestor e4289e688 origin/main && echo ancestral

# grupo 2 — âncora + regeneração
node scripts/design/ancora.mjs Ponto/Relatorios/Index --staging prototipo-ui/cowork/Wagner
node scripts/design/gerar-map.mjs memory/requisitos/Ponto/relatorios-index-gap.md > /tmp-da-sessao/fresh.json   # comparar acao/_acionavel chave a chave

# grupo 3 — linhas citadas (exemplos)
git show origin/main:prototipo-ui/cowork/Wagner/ponto-telas.jsx | awk 'NR>=820&&NR<=843{printf "%5d  %s\n",NR,$0}'
git show origin/main:resources/js/Pages/Ponto/Relatorios/Index.charter.md | cat -n
git show origin/main:Modules/Ponto/Http/Controllers/RelatorioController.php | awk 'NR>=99&&NR<=103'
git show origin/main:prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/25-gap-relatorios.md | grep -n 'PARAR SE'

# grupo 4 — integridade de célula
awk -F'|' '/^\| /{print FILENAME": "NF-1" pipes"}' memory/requisitos/Ponto/relatorios-index-gap.md | sort | uniq -c

# sondas negativas com controle
git show origin/main:resources/js/Pages/Ponto/Relatorios/Index.tsx | grep -c data-contract   # 0 (controle className: 21)
git show origin/main:resources/js/Pages/Ponto/Importacoes/Index.tsx | grep -ci filtro         # 0, rc=1 (controle 'importacoes': 15)

# grupo 5
node scripts/governance/design-code-map-check.mjs --check --strict; echo rc=$?
node scripts/governance/requisitos-status.mjs Ponto --check; echo rc=$?
node scripts/governance/plans-index.mjs --check; echo rc=$?
node scripts/governance/doc-id-index.mjs --check-collisions; echo rc=$?

# grupo 6 — PII (sonda em scratchpad; o padrão de moeda é montado por charCode)
git diff origin/main...HEAD -- memory/requisitos | grep -E '^\+' | grep -vE '^\+\+\+' > plus.txt && node pii.mjs plus.txt
```

```json
{"itens_verificados": 220, "erros_confirmados": 1, "error_rate_pct": 0.45, "pii_hits": 0, "veredito": "aprovado"}
```
