---
slug: 0243-processo-memoria-evolucao-design-cowork
number: 243
title: "Processo de memória/evolução de design do Cowork — loop medido e auto-corretivo (3 planos · anéis Avaliar/Testar/Adotar/Descartar · DS-GUARD · bateria · benchmark · gatilho de reestruturação) ratificado como método canônico"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-06-02"
decided_at: "2026-06-02"
module: governance
quarter: 2026-Q2
tier: CANON
trust_level: tier-0-irrevogavel
tags: [governance, design-evolution, cowork-loop, charter, decision-register, technology-radar, ds-guard, anti-regressao, benchmark, licoes, append-only, claude-design]
related:
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0236-governanca-evolucao-doc-design
  - 0238-soberania-constituicao-wagner
  - 0239-governanca-design-system-git-ssot-regressao-ia
  - 0241-loop-design-cowork-code-autonomo-zero-humano
related_adrs: [0061, 0094, 0107, 0114, 0129, 0143, 0236, 0238, 0239, 0241, 0242, "UI-0013"]
parent_charter: mission.constituicao-v2
supersedes: []
authors: [wagner, claude-code]
---

# ADR 0243 — Processo de memória/evolução de design do Cowork (método canônico)

> **Status:** ✅ Decidida por Wagner 2026-06-02 ("pode fazer" — OK explícito pra [CL] numerar/versionar sob soberania ADR 0238). Numerada (0243) e portada pro git pelo [CL].
> **Pendente:** merge em `main` por [W] = ratificação. O método em si vive em [`prototipo-ui/PROCESSO_MEMORIA_CC.md`](../../prototipo-ui/PROCESSO_MEMORIA_CC.md) (PR #2106); esta ADR é a camada de **lei** que o eleva a canon. A defesa CI (R5) é wiring de follow-up — o Cowork não roda CI, então hoje os checks disparam quando o [CC]/[CL] os chama.

## Contexto

A memória de design do Cowork ([CC]) regredia por esquecimento: paleta inventada de novo (L-02/L-23), `.html` de tela na raiz (L-21), decisão reprovada re-proposta meses depois, "melhoria de passagem" mexendo no que já estava travado. A sessão 2026-06-02 fechou com **recidiva 75%** (3 erros, todos repetindo uma L-NN existente) — o spike disparou uma reestruturação que produziu um **sistema de evolução medido e auto-corretivo**: 3 planos, loop de anéis, guard mecânico, bateria de testes, benchmark, gatilho de reestruturação e regra de sobrevivência, consolidados em `PROCESSO_MEMORIA_CC.md` + a 1ª tela instrumentada (Produção/Oficina, charter + register) + a lição do erro que originou (L-23).

Wagner, 2026-06-02, autorizou formalizar ("pode fazer"). Isto **consolida** (não contradiz): 0114 (loop Cowork), 0236 (governança de evolução de doc de design), 0238 (soberania de [W]), 0239 (governança do DS — git SSOT + regressão-IA), 0241 (loop autônomo zero-humano), 0242 (charters de papel) e a Constituição UI v2 (UI-0013).

## Decisão — o método (8 invariantes)

### R1 · `PROCESSO_MEMORIA_CC.md` é o método canônico e **always-read**
Define COMO a memória de design do [CC] evolui sem regredir. É a raiz: Charter, Register e a ponte pro git são instâncias dele. **Ler no início de todo chat [CC]**, junto com STATUS. A §7 do doc é honesta: este read é a **única dependência crítica** — sem ele o método vira documento morto (~4/10). Por isso o ponteiro always-read é piso intocável (R8).

### R2 · 3 planos, 2 velocidades, herança sem contradição
**Sistema** (cross-tela, lento, [W]+ADR) ⊃ **Tela** (`*.charter.md` lei + `*.decisoes.md` debate, médio, [CC] propõe) ⊃ **Processo** (meta, este método). Camada superior **herda** das inferiores e **nunca contradiz** — mesma forma da Constituição UI v2 (UI-0013).

### R3 · Loop de anéis com **fonte única**
`🔍 Avaliar → 🧪 Testar → ✅ Adotar → ⛔ Descartar` (Technology Radar). **Adotar** → texto canônico migra pro Charter (vira lei da tela) + ponte git. **Descartar** → vira anti-pattern no Charter + lição L-NN (nunca re-proposto sem citar por que caiu). Um item existe em **UM lugar autoritativo por vez** (Register em debate; Charter quando gradua; o outro vira espelho/lápide). Proibido descrever a mesma decisão por extenso nos dois — é como se cria contradição.

### R4 · Charter + Register por tela, **irmãos obrigatórios**
Convenção pasta-por-tela: `prototipo-ui/prototipos/<tela>/charter.md` (lei travada) + `decisoes.md` (debate vivo). Todo charter tem decisoes irmão e vice-versa (Teste de Integridade IT2). Charter guarda só o que fechou; Register guarda o que está em movimento.

### R5 · **Defesa que dispara > regra que se lê** (catraca de tipo-forte)
Regra que depende de lembrar de ler é fraca. Mecanismos que disparam sozinhos, sobre os arquivos tocados:
- **DS-GUARD** — gate mecânico que pega paleta inventada (L-02/L-23) e `.html` de tela na raiz (L-21); arquivo ilegível = FALHA, nunca skip silencioso; árvore-inteira = relatório de dívida, não bloqueio.
- **Bateria de Testes de Evolução** — 14 checks (5 duros) cobrindo L-01…L-23; qualquer duro falho = INVÁLIDO (não adota).
- **Testes de Integridade** (IT1–IT7) — a própria memória não corrompeu (espinha existe, charter↔register pareados, LICOES contíguo, benchmark logado, sem link morto).
Compõe ferramentas que **já existem** no repo (`screen-grade`, eslint `ds/*`, `visual-regression`, `critique-score`) — não inventa. A defesa só **sobe** de tier (FRACA→FORTE), nunca desce.

### R6 · Medir é inegociável + gatilho de auto-conserto
**Benchmark por sessão** (append-only): recidiva→0, escapes a [W]→0, defesa-forte→100%, confiança composta≥9, detecção shift-left subindo. **Gatilho de Reestruturação** dispara se recidiva>30% **ou** ≥2 escapes a [W] em 3 sessões **ou** a mesma L-NN reincide **ou** confiança<7 → PARA feature, roda a Bateria como auditoria, **sobe a defesa um tier** (lição que repete = mecanizar, não reescrever).

### R7 · `LICOES_CC` é **append-only Tier 0**
Erro novo → +1 `L-NN` (numeração contígua, sem buraco/duplicata — IT4) **e** +1 teste na Bateria (a bateria cresce com os erros). Nunca reescreve nem deleta uma L-NN.

### R8 · Soberania preservada — o método é de [CC], **não** é lei suprema
Descreve só como [CC] obedece; é subordinado a PROTOCOL/BRIEFING/Constituição. **Constituição, ADR e token = só [W]** (ADR 0238): [CC] **propõe**, [CL] aplica via PR, [W] mergeia/numera. Nenhuma evolução é "adotada" sem **Bateria ≥90 + zero duro-falho + OK de [W]** (Tier 0). Piso intocável: espinha always-read (STATUS→PROCESSO) + soberania de [W].

> **Invariantes (nunca):** nunca pular o read de início de chat · nunca adotar sem Bateria≥90 + zero-duro + OK [W] · nunca paleta inventada ou `.html` de tela na raiz · nunca charter sem register irmão · nunca regredir item descartado sem citar por que caiu · nunca deletar L-NN (append-only) · nunca medir-de-menos (sem benchmark, processo é "não-verificado").

## Mecanismo (compõe ferramentas existentes)

| Peça da regra | Já existe no repo | Follow-up de wiring |
|---|---|---|
| R1 always-read | `PROCESSO_MEMORIA_CC.md` + ponteiro no STATUS/CLAUDE.md do Cowork | hook de início de chat que cobra o read |
| R3/R4 charter↔register | convenção `prototipos/<tela>/`, `CharterHealthChecker` (ADR 0242) | check IT2 (charter sem decisoes irmão) no health-check |
| R5 DS-GUARD/Bateria | `screen-grade` · eslint `ds/*` · `visual-regression.yml` · `critique-score` (plugin) | `ds-regression-gate.yml` (ADR 0239 R3) + changed-files automático |
| R6 benchmark/gatilho | tabela de tendência no doc (manual) | logar via tooling no fim de sessão; gatilho automático |
| R7 LICOES append-only | `memory/LICOES_CC.md` (este PR) | guard de contiguidade L-NN (IT4) no CI |

## Responsabilidades

| Papel | Sobre o método | Pode | Não pode |
|---|---|---|---|
| **[W]** | soberano | ratificar (merge), definir o que adota, numerar ADR | — |
| **[CC] Cowork** | dono do método | rodar o loop, propor evoluções, manter charter/register/benchmark, chamar o DS-GUARD/Bateria | editar/numerar/commitar a constituição; adotar sem OK de [W]; ser fonte do git |
| **[CL] Code** | executor | transportar pro git via PR, rodar os guards, validar contra `origin/main` (§10.4) | mergear sem [W]; cunhar número de ADR sem OK de [W] |

## Consequências

- **Positiva:** acaba a regressão por esquecimento — o loop é medido (benchmark) e se auto-conserta (gatilho), com defesa que dispara em vez de regra que se lê. O método é citável (uma raiz, não combinado verbal) e cobre todos os erros já cometidos (L-01…L-23).
- **Negativa:** mais ritual por evolução (read de início de chat + Bateria antes de adotar). **De propósito** — é o preço de não regredir. A dependência crítica é o read (§7): se ele não acontece, o método despenca.
- **Custo de wiring:** o Cowork não roda CI, então hoje DS-GUARD/Bateria/IT dependem do [CC]/[CL] os chamar (mitigado pela §9 do doc, não curado). Follow-up: hooks Cowork + `ds-regression-gate.yml` + changed-files automático.
- **Não supersede nada:** é a camada meta que consolida 0114/0236/0238/0239/0241/0242 — additiva.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-06-02 | [W] decide ("pode fazer") + [CL] redige | ratifica o `PROCESSO_MEMORIA_CC` como método canônico (R1–R8). Origem: handoff Cowork `ALwoVssQOY` → prompt `PROMPT_PARA_CODE_ARQUITETURA-MEMORIA-CC.md` → PR #2106. |
