---
date: "2026-08-10"
topic: "Faxina na Jana virou auditoria dos 555 arquivos — 56 gaps, e a descoberta de que um comentário virou lei em 7 documentos canon"
authors: [C]
---

# A Jana lida inteira — e o comentário que virou lei

> Sessão de 5 PRs, todos mergeados. Começou como *"faxina na Jana"* e terminou lendo **555 de 555**
> arquivos do módulo, com dois adversários read-only e verificação própria com controle positivo em
> cada instrumento. Base: `origin/main`, clone completo (`--is-shallow-repository=false`).

## Como um comentário travou a limpeza por 3 meses

`resources/js/Pages/Jana/components/JanaCockpitV2.tsx` — 633 linhas, **0 imports** no repo inteiro.

Sete documentos canônicos afirmavam que **não podia ser apagado**: `RUNBOOK-components.md` (com
seção dedicada `⛔ não apague`), `RUNBOOK-cockpit.md`, `SPEC.md` da Jana (2×), `SUPERFICIE.md`,
2 session logs e 1 handoff. Todos com a mesma frase — *"quem o consome é o `Sells/Index.tsx:55`"*.

**A linha 55 é comentário.** E diz o **oposto**: que a tab Insights **saiu** de `/sells`. As linhas
486, 1078 e 1092 do mesmo arquivo dizem *"tab bar removida"*, e o `SellsInsightsView` — o componente
daquela tab — já estava deletado.

Ninguém reabriu a linha. Cada doc herdou do anterior. E o custo é medível: a **onda 4 da
`US-COPI-148`** registrou por escrito que **recuou** dele (*"⚠️ O `JanaCockpitV2.tsx` NÃO foi tocado
— serve a tab Insights de `/sells`"*). A limpeza chegou até a porta e voltou por causa da frase.

Provas da remoção: `tsc` **372 = 372** antes e depois · `build:inertia` com **0** chunks
`JanaCockpitV2*` (1 do irmão vivo) · nenhum PHP renderiza o nome · os dois guards citados como razão
**não citavam o arquivo**.

## Os 56 gaps, e a forma comum

Depois de [W] perguntar *"olhou todos os arquivos?"* (não — 54%) e mandar *"leia o módulo inteiro"*,
fechou em 555/555. O inventário está em
[`requisitos/Jana/AUDIT-GAPS-2026-08-10.md`](../requisitos/Jana/AUDIT-GAPS-2026-08-10.md).

**Em nenhum dos 56 o código está errado.** O que está errado é o **registro sobre o código**, em
duas variantes:

| Variante | Exemplos |
|---|---|
| Artefato de governança afirma sobre alvo que ninguém reabriu | scorecard de tela deletada contado como ✅ pela catraca (193 de 193) · charter apontando pra charter apagado · permissão `copiloto.memoria.manage` que só existe dentro do próprio charter, numa tela LGPD |
| **Presença de registro ≠ execução** | 5 comandos no disco fora do `commands([...])` · `Event::listen` + `singleton` pra cadeia que ninguém invoca (`->avaliar(` = rc=1, controle positivo `->handle(` = 104 arquivos) |

E a cadeia causal que fecha o ciclo: o `module.json` afirma que *"permissions mantêm prefixo legacy
`copiloto.*`"* — **falso**, são 22 keys todas `jana.*`. Os charters que declaram `copiloto.access` e
`copiloto.memoria.manage` estão **repetindo o que o `module.json` diz**.

## O achado Tier 0 — uma trava que nunca correu

`Modules/Jana/Tests/Feature/Ai/BriefDiarioAgentTest.php` está na **última linha do bloco rotulado
`ALLOWLIST VERDE (catraca)`** de `jana-pest.yml`, lane que roda `DB_CONNECTION: mysql`. O arquivo faz
`markTestSkipped` quando o driver **não é sqlite** (`:32-33`). Não está em `.github/ci-sqlite-pest.list`.
A nightly do CT 100 também é MySQL.

**Nunca roda, em superfície nenhuma, e sai verde** — skip é exit 0. Um dos seus 6 casos é
`R-COPI-202-003 — Tier 0 cross-tenant: 5 Tools(biz=1) NUNCA expoem dados de biz=99`.

Medido repo-wide: **40** arquivos nesse estado, e **38 são a matriz do `modules-pest.yml`** — que
roda **sqlite** enquanto os arquivos exigem **mysql**.

## O que foi consertado nas máquinas

`scripts/governance/test-lane-coverage.mjs` ganhou o **eixo 2**. O eixo 1 responde *"a lane
ALCANÇA o arquivo?"*; o novo responde *"a lane que o alcança deixa ele RODAR?"*.

FP medido **antes** de construir (regra "LIGUE A MÁQUINA" item 4): 1.496 testes · 156 com guard de
driver top-level · **40** o achado · 245 são órfãos do eixo 1 (separados no relatório, somar
inflaria) · **15 descartados** por terem guard **por-caso** — o FP controlado.

Estende o dono, advisory, selftest **35/35** com 2 bite-tests e 4 controles negativos, wirado no CI.

## O que eu errei, e o padrão

Cinco erros, todos **LC-08** (ledger incrementado 75 → 77):

1. `rc=$?` depois de pipe, **3×** — media o `tail`. Uma delas registrou "anchor-lint verde" quando
   estava vermelho por dívida pré-existente.
2. `npm run build` como prova de mudança em `.tsx` — é o config do **Tailwind**: 1 módulo, zero `.tsx`.
3. Regex de import ancorado em linha → falso-positivo no `SellsTabelaUnificada` (import multi-linha).
4. Contei **115 → 103 → 101** antes de acertar.
5. Dei briefing errado ao adversário (*"fora da lane = vermelho invisível"*); ele foi à porta viva e
   me corrigiu — **"FORA DO PR ≠ NUNCA RODA"**, porque a nightly roda a árvore inteira.

⚠️ **O padrão que importa:** em **três** momentos a correção veio de [W] perguntando, não de mim
medindo — *"olhou todos?"* · *"quem é o responsável?"* · *"pode ser melhorado?"*. Em todos, o
instrumento me devolvia um **número plausível**, e plausível é o que engana.

## O que ficou registrado pra durar

- **`GUIA-DO-SISTEMA.md` §B8** (v1.5.0) — quem pode alterar (`CODEOWNERS`, `enforcement: everyone`),
  as 4 camadas com o **comando ao lado de cada número**, o passo a passo, e como um arquivo sobrevive.
- **`proibicoes.md` §5 2026-08-10** — a lápide da classe, com o limite: *não usar `arquivo:linha`
  como prova de consumo sem abrir a linha*.
- **6 chips** abertos e iniciados por [W], cobrindo mover/apagar · conferir · construir · testar.

A regra que resume a sessão, e está no B8.4: **um fato só sobrevive se algum comando o RECALCULA.**
