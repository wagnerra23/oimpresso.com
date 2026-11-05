---
date: "2026-11-05"
topic: "Refutação GT-G5 r1 do lote do PR #10376 (24 *.map.json regenerados por gerar-map --atualizar) — 24 itens, 0 erros, 0 PII; aprovado"
authors: ["C"]
prs: [10376]
outcomes:
  - "24 mapas verificados (100%): prototipo_sha novo = hash do conteúdo atual do protótipo; diff só em prototipo_sha e gerado_em"
  - "Causa confirmada por blob: 23 mapas envelheceram no #10055 e Sells/vendas no #10384"
  - "0 hits de PII no diff dos 24, com controle positivo"
---

# Refutação GT-G5 — PR #10376 (commit 45a4bea1ec, 23 `*.map.json`, mais `Sells/vendas.map.json` no working tree: 24 itens)

## TL;DR

Os 24 `*.map.json` regenerados no PR #10376 conferem. Em cada um, o `prototipo_sha` novo é o hash do conteúdo atual do protótipo, e o diff muda só `prototipo_sha` e `gerado_em`. A causa do envelhecimento, confirmada pelo conteúdo dos arquivos, é o #10055 para 23 mapas e o #10384 para o de Vendas. Resultado: 0 erros, 0 dados pessoais, aprovado.

- **Data:** 2026-11-05
- **Refutador:** Claude Opus 5.5 (`claude-opus-5-5`), sessão nova sem contexto do gerador (subagente com prompt próprio).
- **Gerador declarado:** commit `[CC]`, Claude Opus 5.5. Mesmo tier: aceito pela §4.2 (teto de política = opus), com sessão fresca.
- **Tipo:** `anchors` · amostra 100% (24/24). Sem amostragem aleatória, logo sem seed.
- **Repo:** clone raso (`is-shallow-repository = true`), mas `75e44b87d8` e `75e44b87d8^` existem como objetos (`git cat-file -t` = commit). Nenhuma conclusão por data; tudo por conteúdo de blob.
- **Base:** `origin/main...HEAD` = 0 atrás / 1 à frente (após `git fetch origin main`). Os 7 protótipos têm blob idêntico em HEAD e em `origin/main`.

## Checklist §3

- [x] Sessão fresca (sem contexto do gerador)
- [x] Tier do refutador (opus × opus, aceito pela §4.2)
- [x] Amostra 100% dos anchors
- [x] Cada item verificado contra o código real (blobs em HEAD, `origin/main`, `75e44b87d8` e `75e44b87d8^`), não contra o texto do PR
- [x] REFUTADOs anotados com evidência (nenhum)
- [x] Scan PII no diff (0 hits; controle positivo do regex = 2/2)
- [x] `error_rate_pct` calculado: 0 < 2
- [ ] Entry no ledger: fora do meu escopo (somente leitura); cabe ao PR

## Método (reproduzível)

Script `C:/Users/wagne/AppData/Local/Temp/claude/ddbf33d759c84b24/37c64f00-d98a-48af-86d4-237d74f82b00/scratchpad/ref.mjs`, saída `C:/Users/wagne/AppData/Local/Temp/claude/ddbf33d759c84b24/37c64f00-d98a-48af-86d4-237d74f82b00/scratchpad/ref.json`.

1. **Conjunto de arquivos de protótipo por mapa:** mesma lógica do `verificarMapa()` em `scripts/governance/design-code-map-check.mjs:191-256`: `partes[].prototipo.arquivo` não-placeholder e existente; fallback no frontmatter `prototipo:` do `gap_fonte` via `resolverArquivosPrototipo`. Os 23 resolveram pelas partes, 1 arquivo cada; 7 arquivos distintos ao todo.
2. **Hash novo:** comparado a três cálculos: (a) `computeProtoHash` do repo (`scripts/design/gerar-map.mjs:124`) no disco; (b) reimplementação própria (`rel:contentHash(blob)` ordenado → sha256 → 12 hex) sobre o disco; (c) a mesma reimplementação sobre `git cat-file -p HEAD:<path>`. Os três batem nos 23.
3. **Hash antigo × #10055:** a reimplementação aplicada a `75e44b87d8^:<path>` e a `75e44b87d8:<path>`. Em todos os 23, o antigo = pré-#10055 e o novo = pós-#10055. **Discrimina:** antigo ≠ novo e pré ≠ pós nos 23, então a igualdade não é constante. `git diff --name-only 75e44b87d8^ 75e44b87d8` lista os 7 protótipos, e `git log 75e44b87d8..HEAD -- <7 arquivos>` = vazio: nenhum outro commit mexeu neles depois.
4. **Diff:** comparação JSON profunda HEAD~1 × HEAD por arquivo. As únicas chaves diferentes são `/prototipo_sha` e `/gerado_em`. `--numstat` = 2/2 por arquivo; 92 linhas +/- no total (23 × 4), nenhuma fora dessas duas chaves.

## Tabela dos 23

| Arquivo | Veredito | Evidência |
|---|---|---|
| Compras/compras.map.json | CONFIRMADO | fonte `compras-page.jsx` (via partes) · novo `sha256:94f998281222` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:64a8682faa0f` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-07→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Essentials/holidays-index.map.json | CONFIRMADO | fonte `hrm-page.jsx` (via partes) · novo `sha256:d06161adde91` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:91a4e5cff07e` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-06→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Essentials/tipos.map.json | CONFIRMADO | fonte `hrm-page.jsx` (via partes) · novo `sha256:d06161adde91` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:91a4e5cff07e` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-06→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Fiscal/fiscal-cockpit.map.json | CONFIRMADO | fonte `fiscal-page.jsx` (via partes) · novo `sha256:cb85693fe445` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:acc9ccf2f756` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-07→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Fiscal/fiscal-config.map.json | CONFIRMADO | fonte `fiscal-subpages.jsx` (via partes) · novo `sha256:32a552b4bea4` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:1e30b295a1d2` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-09-14→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Fiscal/fiscal-dfe.map.json | CONFIRMADO | fonte `fiscal-subpages.jsx` (via partes) · novo `sha256:32a552b4bea4` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:1e30b295a1d2` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-09-14→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Fiscal/fiscal-eventos.map.json | CONFIRMADO | fonte `fiscal-subpages.jsx` (via partes) · novo `sha256:32a552b4bea4` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:1e30b295a1d2` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-09-14→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Fiscal/fiscal-nfe.map.json | CONFIRMADO | fonte `fiscal-page.jsx` (via partes) · novo `sha256:cb85693fe445` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:acc9ccf2f756` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-07→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Fiscal/fiscal-nfse.map.json | CONFIRMADO | fonte `fiscal-page.jsx` (via partes) · novo `sha256:cb85693fe445` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:acc9ccf2f756` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-07→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Fiscal/fiscal-sped.map.json | CONFIRMADO | fonte `fiscal-subpages.jsx` (via partes) · novo `sha256:32a552b4bea4` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:1e30b295a1d2` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-09-14→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| OficinaAuto/ordens-servico-board.map.json | CONFIRMADO | fonte `oficina-page.jsx` (via partes) · novo `sha256:9a1bc6b8943e` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:a2762c9601bc` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-06→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| OficinaAuto/service-orders-board.map.json | CONFIRMADO | fonte `oficina-page.jsx` (via partes) · novo `sha256:9a1bc6b8943e` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:a2762c9601bc` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-06→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| RecurringBilling/cobranca-recorrente-configuracoes.map.json | CONFIRMADO | fonte `cobranca-recorrente-page.jsx` (via partes) · novo `sha256:74d2159744ee` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:f2cd15240afc` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-26→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| RecurringBilling/cobranca-recorrente-faturas.map.json | CONFIRMADO | fonte `cobranca-recorrente-page.jsx` (via partes) · novo `sha256:74d2159744ee` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:f2cd15240afc` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-26→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| RecurringBilling/cobranca-recorrente-planos.map.json | CONFIRMADO | fonte `cobranca-recorrente-page.jsx` (via partes) · novo `sha256:74d2159744ee` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:f2cd15240afc` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-26→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| RecurringBilling/cobranca-recorrente.map.json | CONFIRMADO | fonte `cobranca-recorrente-page.jsx` (via partes) · novo `sha256:74d2159744ee` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:f2cd15240afc` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-26→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Repair/repair-dashboard.map.json | CONFIRMADO | fonte `repair-page.jsx` (via partes) · novo `sha256:9996a9394922` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:0508ec5a639a` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-05→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Repair/repair-device-models.map.json | CONFIRMADO | fonte `repair-page.jsx` (via partes) · novo `sha256:9996a9394922` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:0508ec5a639a` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-05→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Repair/repair-index.map.json | CONFIRMADO | fonte `repair-page.jsx` (via partes) · novo `sha256:9996a9394922` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:0508ec5a639a` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-05→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Repair/repair-jobsheet.map.json | CONFIRMADO | fonte `repair-page.jsx` (via partes) · novo `sha256:9996a9394922` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:0508ec5a639a` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-05→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Repair/repair-producao-oficina.map.json | CONFIRMADO | fonte `repair-page.jsx` (via partes) · novo `sha256:9996a9394922` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:0508ec5a639a` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-05→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Repair/repair-settings.map.json | CONFIRMADO | fonte `repair-page.jsx` (via partes) · novo `sha256:9996a9394922` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:0508ec5a639a` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-05→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |
| Repair/repair-status.map.json | CONFIRMADO | fonte `repair-page.jsx` (via partes) · novo `sha256:9996a9394922` = computeProtoHash(disco) = hash próprio em HEAD · antigo `sha256:0508ec5a639a` = hash próprio em `75e44b87d8^`, novo = hash em `75e44b87d8` · diff JSON só `/prototipo_sha`,`/gerado_em` (2026-10-05→2026-11-04) · numstat 2/2 · 0 commits no protótipo após 75e44b87d8 |

## Scan PII

Regex sobre `git diff HEAD~1 HEAD -- 'memory/requisitos/**/*.map.json'`: CPF formatado, 11 dígitos crus, CNPJ formatado, telefone e e-mail. **0 hits** (rc=1). Controle positivo com CPF e e-mail de exemplo: 2/2 casaram. O diff só traz hashes `sha256:` e datas.

## Observação fora do lote (não conta como erro de item)

O commit diz que o check "volta a verde no main". Em HEAD o `design-code-map-check` ainda acusa **1 STALE**: `memory/requisitos/Sells/vendas.map.json` (salvo `sha256:84411b9b7e7b`, atual `sha256:baaf37734a97`). A causa é o #10384 (`27d31d0022`), que mudou `vendas-page.jsx` e entrou no main depois. Esse mapa não faz parte dos 23 e não invalida nenhum item, mas a frase "verde" não vale para o estado atual do branch.


## Item 24 — `memory/requisitos/Sells/vendas.map.json` (working tree, ainda não commitado)

Pedido do coordenador. Verificado com os mesmos 3 critérios e o scan de PII. Script `C:/Users/wagne/AppData/Local/Temp/claude/ddbf33d759c84b24/37c64f00-d98a-48af-86d4-237d74f82b00/scratchpad/v.mjs`.

| Arquivo | Veredito | Evidência |
|---|---|---|
| Sells/vendas.map.json | CONFIRMADO | fonte `vendas-page.jsx` (via partes, 1 arquivo) · novo `sha256:baaf37734a97` = computeProtoHash(disco) = hash próprio no disco = hash próprio em HEAD (o protótipo não tem mudança no working tree) · antigo `sha256:84411b9b7e7b` = hash próprio em `27d31d0022^`, novo = hash em `27d31d0022` (#10384); 0 commits no protótipo depois do #10384 · diff JSON (HEAD × working tree) só `/prototipo_sha`,`/gerado_em` (2026-10-22→2026-11-05) · numstat 2/2 · PII 0 hits |

Com a mudança aplicada, o `design-code-map-check` não acusa mais nenhum DRIFT/STALE no worktree. A observação acima sobre o mapa desatualizado fica resolvida por este item, desde que ele seja commitado no PR.

## Números (24 itens)

- itens_verificados: 24
- erros_confirmados: 0
- error_rate_pct: 0
- pii_hits: 0
- veredito: **aprovado** (0% < 2%)
