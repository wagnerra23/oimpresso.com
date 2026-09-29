---
date: "2026-09-29"
topic: "Refutação GT-G5 r1 do lote PR #8195 (25 maps re-ancorados + 25 gap.md re-medidos pós-handoff 43): 624 itens medidos contra origin/main, 0 refutados, PII 0 hits com 7/7 controles"
authors: ["C"]
prs: [8195]
outcomes:
  - "Lote APROVADO na r1: 624 itens verificados (100% das âncoras, partes, células e máquinas), 0 erros confirmados, error_rate 0,00%"
  - "Scan PII sobre 915 linhas `+`: 0 hits nos 7 padrões, com os 7 controles positivos casando; nomes de cliente/piloto = 0"
  - "Máquinas: design-code-map-check --check --strict rc=0 (0 âncora quebrada, 0 sha stale); requisitos-status Ponto rc=0; plans-index rc=0; doc-id-index rc=0; Essentials/Governance rc=1 é pré-existente (sem _STATUS-GENERATED.md em origin/main, fora do lote)"
---

## TL;DR

**APROVADO.** 624 itens verificados contra `origin/main` (107c7151dc) · **0 erros confirmados** · error_rate **0,00%** · PII **0 hits** (7/7 controles positivos OK). Todos os 60 paths citados pelos 25 `.map.json`/frontmatter existem; os 25 charters resolvem `âncora ✓` no `ancora.mjs`; `gerar-map.mjs --atualizar` reproduz os 25 maps chave a chave (sha, ids, status, acao, arquivos, linhas); toda faixa de linha citada (194 partes + prosa dos 25 gap.md) contém o que a célula afirma; os 27 PRs e 11 ADRs citados existem no histórico com as datas declaradas.

## Cabeçalho

| Campo | Valor |
|---|---|
| Base | `origin/main` = `107c7151d` (#8194, importa handoff 43) |
| HEAD do lote | `8487ac4bf` (branch `claude/reancora-maps-8194-v2`) |
| Repo raso | `git rev-parse --is-shallow-repository` → **false** (datas de `git log` valem como recibo) |
| Sessão fresca | sim — instância nova, sem contexto do gerador; **não** abri `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje (só vi nomes de 2 refutações antigas numa listagem `rg -l`, sem abrir) |
| Corpo do PR | não lido como evidência |
| Modelo | Fable 5.1 (tier máximo) |
| Rodada | r1 |
| Arquivo de evidência já existia? | não |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (igualdade só no tier máximo — Fable é o teto)
- [x] Amostra: 100% anchors (tipo do lote = anchors; sem seleção aleatória, logo sem seed)
- [x] Cada item verificado contra o código real em origin/main (`git show origin/main:<path>`, `git ls-tree origin/main`, `git grep <pat> origin/main`), não contra o diff
- [x] Cada REFUTADO anotado com evidência — não houve refutado
- [x] Scan PII no diff (7 padrões + controle positivo por padrão) — 0 hits
- [x] `error_rate_pct` calculado e < 2 → 0,00
- [ ] Entry no ledger — fora do meu mandato (o refutador não escreve no ledger)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l` → **50** (= total do diff do PR, `git diff --name-status origin/main...HEAD | wc -l` → 50). 25 `*.map.json` (M) + 25 `*-gap.md` (M): Essentials 3 telas, Governance 4, Ponto 18. Somatório: **194 partes** nos 25 maps; **915 linhas `+`** no diff.

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---:|---:|---:|---|
| 1. Âncora existe em origin/main | 123 | 123 | 0 | 60 paths únicos (map `prototipo.arquivo`/`vivo.arquivo`/`gap_fonte` + frontmatter `prototipo`/`tela_viva`) via `git ls-tree origin/main -- <p>` (controle negativo `resources/js/Pages/NaoExiste/Foo.tsx` → vazio); 27 PRs citados nas linhas `+` via `git log origin/main --grep='(#N)'` (todos achados, datas batem: #8118/#8114/#8115/#8124/#8126/#8128/#8165/#8170 = 2026-09-29; #8073/#8076/#8077/#8078/#8079/#8087/#8091/#8093/#8095/#8096/#8113/#8067 = 2026-09-28; #7884 = 09-24; #7283 = 09-15; #7089 = 09-09; #6789 = 09-05; #6431 = 08-28; #7224 = 09-11); 11 ADRs (0014/0053/0084/0110/0275/0324/0366/0397/0399/0413/0418); 21 threads de playbook (hrm 03/06/07/08/12; ponto 03/06/07/08/12/15/17/21–28/ATA); 4 arquivos (2 contracts, Dashboard-visual-comparison.md, Espelho/Show.tsx). SHAs citados (e4289e688, 1b02b777d, 2e3f8adb4e, 82a6d6532) são commits. Nenhuma âncora de fundação (`Components/**`) apontada como tela. |
| 2. Âncora não revogada e lida pelo leitor real | 50 | 50 | 0 | 25× `node scripts/design/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork/Wagner` → todos `âncora ✓` (rc=0), nenhum REVOGADA/MIS-ANCHOR; os arquivos resolvidos batem com o frontmatter (`hrm-page.jsx` ×3 Essentials — Settings via `bundle_source`, fonte declarada `hrm-extras.jsx` explicada no gap; `governance-page.jsx` ×4; `ponto-telas.jsx` ×15; `ponto-page.jsx` ×3). 25× `node scripts/design/gerar-map.mjs <gap.md> --atualizar` → JSON **IDÊNTICO** ao versionado em `tela`, `gap_fonte`, `prototipo_sha`, `gerado_em`, conjunto de ids, `status`, `acao`, `_acionavel`, `prototipo`, `vivo` — o consumidor real (`fmVal`/`resolverArquivosPrototipo`) lê o frontmatter e produz o mesmo sha (`sha256:834d329a6925` hrm-page ×2, `66a8bd4cdb9c` hrm-extras, `48d1ebf86ca8` governance-page+telas ×2, `53938d9e2417` governance-page ×2, `62977f0aeb68` ponto-telas ×15, `46035c96f6bd` ponto-page ×3). |
| 3. Ação × veredito da prosa · afirmação sobre código | 219 | 219 | 0 | 194 partes: `status`/`acao` do map conferidos contra a célula "Estado no vivo"/"Ação" (nenhuma inversão, omissão, reabertura de decisão [W] ou de Non-Goal; "Decidir" só onde a prosa decide); toda faixa `prototipo.linhas`/`vivo.linhas` aberta em origin/main e a 1ª/última linha confere com o id (dump em scratch, 194/194; 8 falsos-`!!` do meu parser eram `!!f.x` do código ou separador " e "). 25 gap.md: cada citação `arquivo:linha` da prosa nova aberta e conferida (ex.: `EssentialsSettingsController.php:92-100` `authorizeAdmin`, `:98` `abort(403, 'Apenas administradores…')`; `DataController.php:177-192` = **13** abas com `label`, `:193-196` filtro por `perm`; `ponto-page.jsx:10-24` = 13 `ABAS`; `governance-page.jsx:25-30` = 4 `VIEWS` e `:5` comenta a saída de "Notas dos módulos"; `DriftAlerts.tsx:74/:80/:87` warning/success e `:94` `tone="info"` × `governance-telas.jsx:166-168` — a divergência do KPI "Sem SCOPE.md" é real; `Escalas/Index.tsx` só tem `window.confirm` num **comentário** (`:87`) e o `AlertDialog` está em `:276-292`; `Show.tsx` de BancoHoras tem 388 linhas, "Voltar aos saldos" `:187-201`, KPIs Saldo/Lançamentos/Teto/Prazo `:207-217`, cabeçalho Data/Referência/Origem/Minutos/Observação `:235-239`, Portaria em `:374`; `Importacoes/Show.tsx:141-175` tem "(N primeiros)" e as 4 colunas Linha/NSR/Tipo/Mensagem; `AfdParserService.php:168` PiiRedactor; `ColaboradorController.php:48-52` `sem-pis` + `Index.tsx:109-120` "Sem PIS cadastrado"; `Tipos.tsx:198` `leaves_count` × `EssentialsLeaveTypeController.php:104-108` `whereYear` × `:281-283` `count()` sem janela; `hrm-page.jsx:289` "Pedidos no ano" e `:295` conta todas; 17 charters em `Pages/Essentials` = 8 com path + 8 `n/a` + 1 sem campo (Holidays/Index) — exatamente como o gap re-contou). Contagens declaradas re-rodadas: `sort`/`KpiCard`/`KpiGrid` em Holidays = 0/0; `Limpar|reset|clear` em Audit.tsx = 5; `histor|rastro|history` em Policies.tsx = `:108 :109 :116`; `busca|search|filtr|<input` = 15; `rules\.length` = `:72 :163`; `Selo|Ativa"|Desligad` = `:138`; `toast\.success|flash` = 0; `ActionGate|modo aviso` em governance-page = `:105 :326`; Legal/Portaria em Aprovacoes = 0/0; Portaria/append em BancoHoras/Index = 0/0; `cargo` em AprovacaoController = 0; `cargo`/`user_id` em Colaboradores/Edit = 0/0; `escala`/`divergenc` em Espelho/Index = 0/0; Drawer/Sheet/NSR/hash em Espelho/Show = 0 cada e `origem` em `:62 :353 :504`; `[Aa]nular` em Pages/Ponto = 1 (só `Fechamento/Index.charter.md:48`); `rg --hidden -g '!.git/**' -n AnularMarcacaoRequest` = **14**, todos em `memory/**` + o próprio Request (rc=0); `anula` em MarcacaoService = 16 (`-i` 22); `ai\.` em Configuracoes/Index = 0, `pontowr2` só `:19`, 4 `data-contract`; Reps 2 `data-contract`; SHA/filtro/filter em Importacoes/Index = 0/0/0; 671/Anexo/anulad em Relatorios = 0/0/0; `allow_users_for_attendance_from_web` em hrm-extras = 0 e em hrm-data `:143`; `Drawer` em ponto-telas = 0; tokens `--fs-1: 10.5px` `--fs-2: 11.5px` `--fs-3: 12.5px`; telas Fechamento/Conformidade/Mobile existem. Prosa vs canon mais novo: ADR 0418 (13 abas), 0413, 0399, 0014-emenda existem e sustentam as datas carimbadas. |
| 4. Célula íntegra | 219 | 219 | 0 | 194 partes: `gerar-map.mjs` **sem** `--atualizar` (esqueleto derivado só da célula) comparado ao map: ids, `prototipo.arquivo`, `_acionavel` idênticos; `acao` idêntica em 165 partes e **enriquecida** (mesmo veredito + linhas/PRs a mais, nunca invertida) em 29 — preservação declarada no docblock do gerador (`--atualizar` preserva `acao` por id); os 29 pares lidos um a um (ex.: `guarda-de-permissao`, `cobertura-das-chaves`, `regua-conformidade`, 4× `configuracoes-index` "Corrigido no vivo pelo #8078", `sub-navegacao` 13 abas). Nenhum conteúdo de mockup rotulado como "vivo"; reticências só em fatos datados. 25 tabelas: contagem de `|` por linha uniforme dentro de cada tabela (as tabelas de partes = 4; as secundárias 2-col/4-col de `configuracoes-index` e `banco-horas-show` são uniformes entre si); pipes literais em code-span usam `／` (fullwidth). |
| 5. Máquina derivada | 6 | 6 | 0 | `node scripts/governance/design-code-map-check.mjs --check --strict` → **rc=0** ("nenhum map.json com âncora quebrada ou sha stale"; 82/82 telas com gap têm map; idade "mesmo dia 26"); `node scripts/governance/requisitos-status.mjs Ponto --check` → **rc=0**; `… Essentials --check` → **rc=1** e `… Governance --check` → **rc=1** — ambos "`_STATUS-GENERATED.md` não existe": `git ls-tree origin/main` confirma que só `Ponto/_STATUS-GENERATED.md` existe e o lote não toca SPEC/STATUS → pré-existente, não é achado do lote (contado como confirmado-com-observação); `plans-index.mjs --check` → **rc=0**; `doc-id-index.mjs --check-collisions` → **rc=0**. |
| 6. PII | 7 | 7 | 0 | ver tabela abaixo |
| **Total** | **624** | **624** | **0** | |

## REFUTADOS

Nenhum.

## Observações (não contadas como erro)

1. `requisitos-status.mjs Essentials|Governance --check` saem rc=1 em origin/main e em HEAD pela mesma razão (`_STATUS-GENERATED.md` inexistente nesses módulos). Dívida do repo, fora do lote; quem for regenerar faz em PR próprio.
2. 29 das 194 `acao` do map são mais ricas que a célula (linhas, PRs, contagens). É comportamento declarado de `gerar-map.mjs --atualizar`; conferi que nenhuma diverge no veredito. Se um dia a régua passar a exigir `acao == célula` byte a byte, esses 29 precisam de sincronização — hoje o gate `design-code-map-check` aceita.
3. `governance-drift-alerts.map.json` tem 2 partes com `vivo.arquivo` real e `vivo.linhas: "n/a (ausente no vivo — …)"` (`skeleton-carga-diferida`, `nota-escopo-ilegivel`). O gate aceita; o par regen `--atualizar` preserva. Forma aceita, só registro.
4. `rg -l AnularMarcacaoRequest` lista 2 arquivos `memory/sessions/*refutacao*` de rodadas antigas (2026-09-06 e 2026-09-11). Só contei; não abri.
5. `espelho-show-gap.md` cita "#7224 · removido em 2026-09-11": texto pré-existente em origin/main e a data bate com o merge `4f51a9ec78` (2026-09-11).
6. O `ancora.mjs` de `Essentials/Settings/Index` reporta `related_prototype: n/a` no charter e resolve `hrm-page.jsx` pelo `bundle_source`; o gap declara `prototipo: hrm-extras.jsx` e explica a relação (`hrm-page.jsx:549` despacha `<X.Config />`, conferido). Não conflita.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`, 915 linhas)

| Padrão | Hits | Controle positivo casou? |
|---|---:|---|
| CPF pontuado (`\d{3}\.\d{3}\.\d{3}-\d{2}`) | 0 | sim |
| CPF cru (11 dígitos isolados) | 0 | sim |
| CNPJ (`\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}`) | 0 | sim |
| Telefone BR (DDD + 8/9 dígitos com separador) | 0 | sim |
| Telefone cru (10–11 dígitos isolados) | 0 | sim |
| E-mail | 0 | sim |
| Valor em reais (símbolo seguido de dígito; padrão montado por `fromCharCode`, não reproduzido aqui) | 0 | sim |
| Nomes de cliente/piloto (Larissa, Martinho, ROTA LIVRE, Vargas, Extreme, Zoom, Fixar, Mhundo, Produart, Gold) | 0 | — |

Controles positivos: **7 de 7**. O placeholder `12345678000100001` de `Reps.tsx:113` é fictício e pré-existente em origin/main (não está nas linhas `+`).

## Comandos reproduzíveis

```bash
# base / raso / escopo
git rev-parse --is-shallow-repository            # false
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l   # 50

# grupo 1 — existência (controle negativo devolve vazio)
git ls-tree origin/main -- resources/js/Pages/Essentials/Holidays/Index.tsx
git ls-tree origin/main -- resources/js/Pages/NaoExiste/Foo.tsx
git log origin/main --format='%h %cs' --grep='(#8118)' -1                # a3ba831c3 2026-09-29

# grupo 2 — leitor real
node scripts/design/ancora.mjs Ponto/Dashboard/Index --staging prototipo-ui/cowork/Wagner
node scripts/design/gerar-map.mjs memory/requisitos/Ponto/dashboard-index-gap.md --atualizar | diff - memory/requisitos/Ponto/dashboard-index.map.json
node scripts/design/gerar-map.mjs memory/requisitos/Ponto/dashboard-index-gap.md            # esqueleto: comparar acao/status/ids

# grupo 3 — amostras de linha (todas contra origin/main)
git show origin/main:Modules/Ponto/Http/Controllers/DataController.php | sed -n '177,196p'
git show origin/main:resources/js/Pages/Ponto/Escalas/Index.tsx | grep -n 'window.confirm'   # :87 comentário
git show origin/main:resources/js/Pages/governance/Policies.tsx | grep -nEi 'histor|rastro|history'
rg --hidden -g '!.git/**' -n AnularMarcacaoRequest . | wc -l                                  # 14
git show origin/main:resources/js/Pages/Ponto/Espelho/Show.tsx | grep -c Drawer               # 0

# grupo 5 — máquinas (rc literal)
node scripts/governance/design-code-map-check.mjs --check --strict; echo rc=$?    # 0
node scripts/governance/requisitos-status.mjs Ponto --check; echo rc=$?           # 0
node scripts/governance/requisitos-status.mjs Essentials --check; echo rc=$?      # 1 (pré-existente)
node scripts/governance/requisitos-status.mjs Governance --check; echo rc=$?      # 1 (pré-existente)
node scripts/governance/plans-index.mjs --check; echo rc=$?                       # 0
node scripts/governance/doc-id-index.mjs --check-collisions; echo rc=$?           # 0

# grupo 6 — PII sobre as linhas +
git diff origin/main...HEAD -- memory/requisitos | grep '^+' | grep -v '^+++' > plus.txt   # 915 linhas
```

```json
{"itens_verificados": 624, "erros_confirmados": 0, "error_rate_pct": 0.0, "pii_hits": 0, "veredito": "aprovado"}
```
