---
slug: 0376-sec5-derivado-limite-no-contexto-arqueologia-na-fonte
number: 376
title: "§5 vira DERIVADO — o limite fica no contexto, a arqueologia sai para memory/licoes-rejeitadas.md"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-11"
accepted_via: "Pedido do dono [W] 2026-08-11: 'por favor remova as lapides agora permito alteracao nas adrs, pode revisar isso para mim'. O agente MEDIU antes de agir e recomendou um caminho DIFERENTE do literal — separar em vez de remover — porque a medicao mostrou que 84 das 104 lapides nao tem defesa mecanica e a remocao apagaria a unica defesa dessas classes. O merge desta ADR e o ato de ratificacao; o agente nao ratifica. Se [W] reafirmar a remocao literal, esta ADR e superseded por outra."
module: governance
quarter: 2026-Q3
tags: [memoria, governanca, contexto, custo, tier-0, append-only, derivado]
supersedes: []
supersedes_partially: []
superseded_by: []
related_adrs:
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0344-two-strikes-cobre-processo
  - 0094-constituicao-v2-7-camadas-8-principios
---

# ADR 0376 — §5 vira DERIVADO: o limite fica no contexto, a arqueologia sai para a fonte

## Contexto

Pedido do dono ([W], 2026-08-11): **"por favor remova as lápides agora permito alteração nas ADRs, pode revisar isso para mim"** — em reação direta ao número que o agente havia reportado duas vezes na mesma sessão: o §5 é 83,9% do `proibicoes.md`, e o `CLAUDE.md:76` faz `@memory/proibicoes.md`, então o arquivo **inteiro** entra em toda sessão.

O custo é real e **não tem teto**. Medido em 2026-08-11 pelo `lapide-recheck`:

| medida | valor |
|---|---|
| lápides no §5 | **104** |
| chars dos corpos | **349.215** = 83,9% do arquivo |
| ritmo | **1,55 lápide/dia** em 67 dias |
| teto declarado | **nenhum** |

O próprio `lapide-recheck.mjs:209` já registrava o problema: *"~106k tokens carregados em TODA sessão"*. No ritmo atual seriam ~380 lápides em seis meses. A preocupação do dono procede e precisava de ação.

## Decisão

O §5 **não é removido**. Ele é **partido em duas partes por função**, e a que não previne sai do contexto:

- **FONTE** — `memory/licoes-rejeitadas.md`: as 104 lápides **íntegras** (o que foi tentado · por que caiu · o limite · evidência · recibos). **Append-only Tier 0**, como era o §5.
- **DERIVADO** — região §5 de `memory/proibicoes.md`: os cabeçalhos `### <data> — <título>` + **só o bloco "O limite"** de cada lápide. Gerado por `scripts/governance/sec5-derive.mjs`, nunca editado à mão.

### Por que ESTA linha de corte, e não outra

Medido no corpus real, não estimado:

| parte | chars | % dos corpos |
|---|---|---|
| **"O limite"** — a regra positiva, o que NÃO fazer | 78.558 | **22,5%** |
| arqueologia — tentativa, por que caiu, evidência, recibos | 270.657 | **77,5%** |

O limite é a parte que **previne**; o resto é o que se lê ao **investigar**. E a extração é mecânica: 102 bullets `- **O limite` em formato consistente, presentes em 101 das 104 lápides.

Resultado: `proibicoes.md` de **413.339 → 159.148 chars (−61,5%)**, com **zero** perda de conhecimento — a fonte guarda tudo.

### Por que DERIVADO, e não "escrever nos dois lugares"

Espelhar o limite à mão em dois arquivos garante drift no primeiro esquecimento — [ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md): *derivado+enforçado sobrevive; escrito+lembrado apodrece*. Escreve-se **num lugar só** (a fonte); o §5 é gerado; o CI confere.

## Por que NÃO a remoção literal

O pedido foi "remova as lápides". O agente mediu antes e recomendou o contrário, com três fatos:

1. **Só 20 das 104 (19%) têm defesa mecânica.** As outras **84 são prosa pura** — previnem apenas *sendo lidas*. Removê-las apaga a única defesa dessas classes.
2. **O ledger mede LC-08 em 86 ocorrências.** Reincidência é o problema nº1 medido do projeto; remover o registro que a combate é apostar contra a evidência do próprio sistema.
3. **Nunca houve remoção intencional.** O único corte grande do histórico (`del=266`) foi o squash acidental do #2413, restaurado em `8cd20a34863`.

A separação entrega **−61,5%** do custo que motivou o pedido sem pagar esse preço. Se [W] ainda quiser a remoção literal, é decisão dele — e vira ADR sucessora, não edição desta.

## Consequências

**Positivas**
- Custo por sessão cai ~61,5% do `proibicoes.md`, e a parte que cresce 1,55/dia deixa de entrar no contexto.
- O limite — o que previne — continua em toda sessão, integralmente (102 de 102).
- Append-only Tier 0 **preservado na fonte**; o git preserva a origem de tudo.

**Negativas / custo aceito**
- **Quem investiga precisa abrir outro arquivo.** O aviso no topo do §5 diz onde, e a ordem/cabeçalhos são idênticos.
- **Uma lápide nova precisa nascer na fonte**, não no §5. Se alguém editar o §5 à mão, o `--check` reprova e a edição é perdida na próxima regeneração — o erro é barulhento, não silencioso.
- **O `lapide-recheck` passou a ler a fonte** (as âncoras vivem nos corpos). Sem isso ele reportava 27 âncoras intactas em vez de 76 — medido durante o split.

## Enforcement

`scripts/governance/sec5-derive.mjs --check`, wirado em `governance-script-tests.yml` (**advisory** de nascença — [ADR 0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md)/[0275](0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md); gates novos nunca nascem required). Duas pernas **independentes**:

1. **NÃO-PERDA** — todo bullet `- **O limite` da fonte aparece no derivado. É o que importa: sem essa perna, o check só provaria *"o derivado é o que o gerador cospe"*, que é tautológico (§5 2026-06-05).
2. **SINCRONIA** — o §5 no disco é byte-idêntico ao gerado (anti-drift de quem editar o derivado).

`--selftest` com **18 asserts**, incluindo 6 bite-tests e 3 controles negativos.

⚠️ **A perna (1) nasceu com buraco, e o buraco foi real.** A 1ª versão do extrator pegava **um** limite por lápide, e a lápide **2026-08-03** tem **dois eixos, logo dois limites** — o segundo foi comido, e o invariante não viu porque conferia só a primeira linha de cada lápide. Achado pelo `--audit` (contagem de bullets ≠ 1), não por releitura. Ambos foram corrigidos e o par virou bite-test fixo. Fica registrado porque é a própria lição do §5 aplicada a quem estava mexendo nele: *quem assume 1 onde pode haver N perde em silêncio*.

## Gate de reversão

Reverter é barato e não perde nada: a fonte tem o texto íntegro, então `git revert` do PR restaura o §5 completo. Sinais que justificariam:

- reincidência medida de classe cujo limite estava no §5 mas cuja arqueologia era necessária para não reincidir (ou seja: o limite sozinho **não** bastava);
- alguém escrever lápide no §5 em vez da fonte com frequência tal que o `--check` vire ruído.

## Alternativas consideradas

| alternativa | por que não |
|---|---|
| **Remover as 104** (pedido literal) | apaga a única defesa das 84 classes sem gate; ver "Por que NÃO a remoção literal" |
| **Tirar o §5 inteiro do `@import`** | corte maior, mas as 84 sem gate param de prevenir passivamente — passa a depender de alguém ir ler |
| **Podar só as 20 que já viraram máquina** | corte de ~19% apenas; não resolve o crescimento de 1,55/dia |
| **Não mexer** | o custo não tem teto e dobra a cada ~2 meses |
