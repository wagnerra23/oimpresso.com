---
date: "2026-07-27"
topic: "Sentinela de exposição Tier-0 publica no Daily Brief + o medidor que contava 4 telas cobertas onde havia 29 (perna casos_coverage morta)"
authors: [C, W]
type: session
module: governance
pii: false
prs: [4843]
related_adrs:
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
---

# Sentinela de exposição Tier-0 no brief + o medidor consertado

> **Pergunta de entrada [W]:** levantar quais módulos justificam um `ANTI-REGRESSAO-*.md`
> (destilado do legado Delphi), porque a Camada 1 do `sdd-from-source` mordeu no piloto
> Produto mas só roda onde o destilado existe — medido: **2 arquivos, 1 módulo de ~40**.
> **Onde terminou:** o gargalo não era o destilado; era o **canal** de uma régua que já
> existia, e um **defeito no medidor** dela.

## 1. O que foi entregue

[PR #4843](https://github.com/wagnerra23/oimpresso.com/pull/4843) (94 checks verdes, merge
`2704fa59fe`, deploy verde, smoke real em prod):

| Peça | O quê |
|---|---|
| `scripts/qa/exposicao-tier0.mjs` | modo **`--stdout`** (JSON puro; não grava baseline, não imprime relatório) + `trend` para o delta viver na sentinela, não em PHP |
| `Modules/Governance/Services/ExposicaoTier0BriefLineService.php` | transporte pro Daily Brief no padrão dos 10 irmãos (`line(): ?string` + `inject()`), pós-LLM determinístico, degrada pra `null` |
| `Modules/Brief/Console/Commands/GenerateBriefCommand.php` | `->inject()` após o Brain B |
| `governance.exposicao_tier0_brief_line` | kill-switch `GOVERNANCE_EXPOSICAO_TIER0_BRIEF_LINE=false` |
| `scripts/lib/uc-regex.mjs` | `ucsDeclaredInCasos()` — fonte única do **parser**, não só do regex |
| `scripts/lib/uc-regex.test.mjs` | 9 testes, com **controle-negativo da raiz**; registrado em `governance-script-tests.yml` |

Smoke real em prod (`php artisan tinker` chamando o Service, shell-out de verdade):

```
🟡 Exposição Tier-0: 89/118 quentes sem teste (Δ-30) · topo: Sells/Show.tsx
```

E o `inject()` sobre o brief real de `mcp_briefs`, provando que entra como 1º bullet do
`## FLAGS` sem quebrar os bullets existentes nem o `---END---`.

## 2. O defeito no medidor (o achado que pagou a sessão)

Ao plugar, a sentinela reportava **Cliente 0/7 cobertas** e `npm run screen:files` reportava
**21 UC citados por teste**. Duas portas vivas discordando sobre o mesmo fato.

**Raiz:** `ucHeadRe()` é ancorado em `^UC-`, mas o heading canônico é `## UC-CEDI-01 · …`.
A sentinela aplicava o regex na **linha crua** (que começa em `##`) → nunca casava. Logo
`hasCasosCoverage()` devolvia `false` pra **toda** tela com heading markdown, e
`covered = e2e || casos` era de fato `covered = e2e`: a perna `casos_coverage` estava
**morta**, e o piso Tier-0 subcontado.

| | antes | depois |
|---|---|---|
| cobertas (piso) | 4 | **29** |
| débito quente | 120 | **89** |
| Cliente | 0/7 | **7/7** |
| topo do ranking | `Financeiro/Unificado` (falso) | `Sells/Show.tsx` |

A divergência entre as duas portas **desapareceu** — é a prova de que o fix está certo.

⚠️ O ganho de 25 é **destravamento de medição, não cobertura nova**. Ninguém escreveu teste.

**Raiz da raiz:** a lib nasceu para matar *"4 regex que deviam ser iguais e drifaram"* — e
matou. Mas o **uso** dela (o `split(/^##\s+/m).slice(1)` obrigatório antes do match) seguiu
copiado em 6 consumidores e drifou pela mesma porta. Varredura contada: **6 consumidores,
5 corretos, 1 com o bug**. O `casos-coverage-guard.mjs` (dono do formato, gate required)
sempre fez o split certo e **não foi tocado** — migrá-lo é PR próprio, não carona.

> ### ⚠️ ERRATA (mesmo dia, ~19h) — a varredura acima está INCOMPLETA
>
> Aqueles "6 consumidores" saíram de `git grep -ln ucHeadRe`, isto é, **por SÍMBOLO da
> lib** — que por construção **só acha quem já importa**. As cópias **inline** do regex,
> que nunca importaram, são invisíveis a essa busca.
>
> A sessão do chip 4 achou o que eu perdi ([PR #4885](https://github.com/wagnerra23/oimpresso.com/pull/4885)):
> **3 regex de UC fora da fonte única**, e um deles **perdia UC inteiro**. O do
> `screen-coverage-map.mjs:177` é `/^(UC-[A-Z0-9]{0,8}-?\d{1,3})\b/i` — **sem o
> `[a-zA-Z]?`** do `UC_CORE`, então o `\b` falha antes do `b` e `UC-DSR-08b` **não casa
> nada**: o UC desaparece, não é truncado.
>
> Re-medido por mim para confirmar (44 `.casos.md`): **lib 181 × regex do map 178 →
> diferença 3** (`UC-DSR-01b/04b/08b`, todos com heading canônico e teste que os cita).
> Consequência: **dois gates required discordando do mesmo fato** — o `casos-gate` (usa a
> lib) cobrava Status+teste dos 3, e `npm run screen:files` não os enxergava.
>
> Varredura correta é **por comportamento** (o regex), não pelo nome da função:
> `git grep -lnE "UC-\(\?|UC-\[A-Z|'UC-" -- 'scripts/**/*.mjs'` → **6 arquivos**, incluindo
> `scripts/governance/requisitos-status.mjs`, que eu nunca vi.
>
> Lição fina, e é a 5ª instância de LC-08 desta sessão: **varredura "contada" por símbolo
> não é varredura por comportamento** — o número 6/5/1 era honesto no que mediu e
> enganoso no que sugeria. Cometida enquanto eu investigava exatamente esta classe.

## 3. Convergência com sessão paralela

No meio do trabalho, os PRs #4836/#4840 corrigiram o **universo** da mesma sentinela
(243 → 235 telas, excluindo `components/` sem underscore). Conflito textual no import,
resolvido mantendo os dois fixes — atacam camadas diferentes:

| | universo | quentes | cobertas | débito |
|---|---|---|---|---|
| dois bugs | 243 | 124 | 4 | 120 |
| só o fix de universo | 235 | 118 | 4 | 114 |
| **os dois** | **235** | **118** | **29** | **89** |

O comentário do #4840 registra que um `components/Drawer.tsx` classificado dinheiro/estoque
*"inflava o débito com alvo errado"* — era exatamente o **#1 do ranking** que eu havia
reportado. Duas sessões acharam o mesmo defeito por caminhos independentes.

## 4. Retrato do débito (medido 2026-07-27; re-rodar as portas antes de citar)

| Métrica | Valor | Porta |
|---|---|---|
| telas | 235 | `screen-coverage:report` · `exposicao-tier0` (convergiram) |
| quentes Tier-0 | 118 (50%) | `exposicao-tier0` |
| **quentes sem teste de comportamento** | **89** — topo `Sells/Show.tsx` | `exposicao-tier0` |
| telas sem `casos.md` | 192 de 235 | `casos:report` |
| UC declarados | 181 | `casos-coverage-guard --json` |
| **UC com prova executável** | **32 (18%)** | idem |
| UC órfãos | 28 | idem |
| E2E Browser | 9 de 235 (3,8%) | `screen-coverage:report` |
| a11y (axe) | 3 de 235 (1,3%) | idem |

## 5. O que investiguei e NÃO era problema

Procurei mais defeitos da classe do dia. Quatro candidatos, todos se explicaram:

- **Portas divergindo (235/237/243)** → convergiram em 235.
- **Scripts órfãos** → **1 de 105**, e é o `charter-promote-signal`, que o CLAUDE.md marca
  "não ligue, escreve estado, é decisão [W]". Triagem fechada.
- **5 contadores do `casos-guard` sempre zero** → medição real: manifesto
  `scripts/casos-test-results.json` está em `main`, fresco (auto-PR diário, 32 UC `pass`).
- **G-7 dormindo em CI** (`if (!manifest) return []`) → não dorme. O workflow ainda se
  protege com skip **declarado** (`echo "baseline ausente… — skip"`), não silencioso.

Registro porque "procurei e não achei" é resultado — evita a próxima sessão repetir a busca.

## 6. Colisão das 6 sessões paralelas (medido)

Seis chips foram abertos e iniciados. Quatro deles (`Sells/Show`, Tributação NfeBrasil,
Fiscal, UC órfãos) regravam o **mesmo** `scripts/casos-coverage-baseline.json`, que é uma
**lista itemizada de 220 violações** num arquivo único.

Risco de uma sessão reintroduzir o que a outra removeu: **contido por gate required**.
`Casos-coverage · ratchet (trio + rastreabilidade)` é required com `enforce_admins`, e o step
"Baseline só-desce" roda `--check-baseline-shrink` contra o baseline de `main`; crescer exige
trailer `BASELINE-GROW` no commit. Sobra **conflito textual**, que é visível e resolvível.

(`baseline-tamper-guard` — o guard genérico de outros baselines — é **advisory**, não está no
`required-checks-baseline.json`.)

## 7. Meus erros de medição (4 nesta sessão)

Todos da classe **LC-08**, e cada um mudou o alvo do trabalho — por isso ficam registrados,
não apagados. Ledger incrementado 13 → 14 em [`LICOES_CODE.md`](../LICOES_CODE.md).

1. **Gate medido por janela de 12 linhas** em vez do escopo do método → classifiquei telas
   como "sem gate" quando o gate estava no topo do método.
2. **Ranqueei por exposição em vez de gap** → troquei Purchase por Cliente, quando Cliente
   era o módulo **mais** bem coberto (7/7 trio, 21 UC com teste). Exposição mede risco
   potencial; gap mede risco descoberto.
3. **Proxy errado no ranking** → contei `num_uf`/`final_total` no **Controller** quando a
   sentinela já ranqueava por exposição da **tela** em 4 categorias.
4. **`grep` com padrão que não casa o nome real** → `casos-result` não casa
   `casos-test-**results**.json`; concluí que um gate required dormia sempre.
5. **Varredura "contada" por SÍMBOLO em vez de comportamento** → `git grep ucHeadRe` só acha
   quem já importa a lib; as 3 cópias inline (uma perdendo 3 UC) ficaram invisíveis. Achadas
   pela sessão do chip 4 ([#4885](https://github.com/wagnerra23/oimpresso.com/pull/4885)) e
   re-confirmadas por mim (181 × 178). Ver errata em §2.

Dois corolários, ambos sobre o mesmo vício:

- *Quando o resultado de um filtro sustenta uma conclusão forte, teste o filtro contra um caso
  que você SABE que existe antes de concluir a ausência.* (erros 4 e 5)
- *Varredura por símbolo prova o que ele nomeia, não a classe. Para achar cópias que drifaram,
  procure o **comportamento** (o regex, a operação), nunca o nome da função.* (erro 5)

## 8. Sobre a pergunta original (ANTI-REGRESSAO)

A pergunta de entrada segue **aberta e válida** — este trabalho não a respondeu, redirecionou
a prioridade. O que a sessão acrescentou para decidi-la:

- Os ≥6 achados da Camada 1 se separam em **3 classes** com custo e mecanização opostos:
  **(A)** mismatch de chave frontend↔validator — **já tem máquina** (`AutosaveContractRunner`,
  11 fixtures, CI); **(B)** campo omitido tratado como zero — sem harness, o Produto fez à
  mão; **(C)** comportamento de UI legado (`@can` que esconde preço de compra, menu de 10
  ações → 1) — **não mecanizável**, exige o print do Delphi.
- Destilar `ANTI-REGRESSAO` é o instrumento certo **só para (C)**. O gargalo de (C) não é
  redação: é **acesso ao Office Comercial rodando** (a `origem:` dos 2 arquivos é print da
  v2026.1.1.38; as asserções `[?]` dizem "confirmar com o legado"). Baseline de custo:
  **186 asserções** para 2 telas.
- Contexto que muda a urgência: as telas duais de Cliente estão **live para todos os
  tenants** desde 2026-05-21 (7/7 flags `mwart.cliente_*` ON com `business_ids = []`,
  verificado por `php artisan config:show mwart` no runtime, não pelo `.env`), e Repair está
  contido em `biz=[1]` com os writers OFF.
- Diagnóstico do payload de Cliente: `Edit.tsx` manda 28 chaves, o `only()` do
  `processContactUpdate` aceita 52, e as 3 mandadas-e-descartadas são **3/3 falsos
  positivos** (`contact_type_radio` tem alias em `ContactController:2552`; `credit_limit` e
  `opening_balance` também não são aceitos pelo `store()` — sem assimetria — e não têm UI).
  O diff estático de chaves não rendeu bug.

## Refs

- [PR #4843](https://github.com/wagnerra23/oimpresso.com/pull/4843) · [#4836](https://github.com/wagnerra23/oimpresso.com/pull/4836)/[#4840](https://github.com/wagnerra23/oimpresso.com/pull/4840) (universo, sessão paralela)
- [ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md) (catraca/sentinela/cadência) · [ADR 0264](../decisions/0264-governanca-executavel-trio-dominio-e2e.md) (trio + G-2)
- [session da auditoria da Camada 1](2026-07-27-auditoria-camada1-sdd-mordida.md) (origem da pergunta)
