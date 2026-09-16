# PATCH do índice · ancora · 2026-09-16

> A pasta no `main` pode estar à frente desta cópia. Isto é **patch**, não índice reescrito: aplique os 4 objetos abaixo no `00-INDICE.md` que estiver no `main`.

## 1) ⚠️ CORREÇÃO DURA — `ALVO` e todos os `prefixo`/`provas` das threads 01–03

`prototipo-ui/ancora.mjs` **não é o arquivo que os consumidores carregam**. Medido em `c1f77b029185`: `.claude/hooks/post-merge-ui-smoke-required.mjs:316` importa `scripts/design/ancora.mjs` (e `:318` degrada citando esse caminho), `post-merge-ui-smoke-required.test.mjs:175` faz `existsSync` nele, e `block-ancora-no-olho.mjs:84`, `charter-validate.mjs:117`, os 3 workflows e a skill `refutador-gt-g5` todos mandam `node scripts/design/ancora.mjs`.

```json
{ "variaveis": { "ALVO": "scripts/design/ancora.mjs", "FIXO": "prototipo-ui/cowork/Wagner" } }
```

Nas threads **01, 02 e 03**: trocar `prefixo` e o `path` de toda `prova` de `prototipo-ui/ancora.mjs` → `scripts/design/ancora.mjs`, **remedindo o sha e o tamanho** (os 49.089 B eram do caminho antigo). **Nenhuma das três executa antes desta correção** — iam editar arquivo que ninguém importa. Defeito meu: registrei a mudança de casa em 14/09 e não corrigi este índice.

## 2) `threads[]` — acrescentar

```json
{
  "id": "07",
  "titulo": "Cruzar as 162 telas do application-report com trio/contrato/ancora usando os donos existentes",
  "dono": "CL",
  "arquivo": "07-cruzar-report-trio-contrato.md",
  "prefixo": ["_saida-07.md"],
  "nao_toca": ["resources/js/Pages/**", "memory/**", "governance/**", "prototipo-ui/cowork/**"],
  "depende_threads": [],
  "depende_decisoes": [],
  "nota_provas": "e EXECUCAO com exit code e numeros; nenhum script novo — os donos ja existem",
  "provas": [
    { "tipo": "execucao", "cmd": "node scripts/design/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork/Wagner (e o 2o staging)", "recibo": "_saida-07.md" },
    { "tipo": "execucao", "cmd": "node scripts/design/casos-coverage-guard.mjs --report && node scripts/design/design-coverage.mjs && node scripts/design/screen-coverage-map.mjs", "exige": "tabela source·target·module·state·charter?·casos?·contrato?·ancora(E0-E4)·componente-faltante" },
    { "tipo": "execucao", "cmd": "npm run tokens:build && node scripts/design-sync/ds-push.mjs && node scripts/governance/ds-mirror-drift.mjs", "exige": "VALOR e totalDiverge reportados" },
    { "tipo": "leitura", "path": "scripts/qa/prototipo-readiness.mjs", "nota": "confirmar ou derrubar por leitura direta + controle positivo; busca bounded nao prova ausencia" }
  ]
}
```

## 3) `§2` (linhas de estado) — acrescentar

- **2026-09-16 · delta `6270479…` promovido às 18:09:02Z** (`mirrorScope: tree`, 702 arquivos · 10.363.749 B, `unchanged: 278`): `transportChanges: 424` · `screens: 162` · **`tested: 0`** · **`smoked: 0`** · `pending: 134` ⇒ transporte, **não** aplicação de tela.
- **2026-09-16 · o `application-report` não carrega entendimento:** busca por `charter|casos|contract|anchor|reason|readiness` no indent das entradas = **0 hit**; entrada é só `source→target→module→applicationState`.
- **2026-09-16 · `ponto-fechamento` e `ponto-mobile` saem como `to-create`** ⇒ o furo **F4** de 14/09 ("sem receptor") passou de leitura de charter a **fato de máquina**.
- **2026-09-16 · trio medido:** 330 `charter`+`casos` em `Pages/**`, com 15 diretórios em assimetria (charter sem UC, ou UC sem charter) · **36** contratos para **162** telas.

## 4) Tabela de threads — acrescentar linha

| **07** | cruzar as 162 do report com trio/contrato/âncora (donos existentes) | `_saida-07.md` | **CABE** · sem decisão pendente · **faça antes de 01–03** (traz a correção de caminho do §1) |
