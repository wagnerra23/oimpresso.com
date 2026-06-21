---
slug: lei-de-uma-tela-pre-voo-verdade-proporcao
number: PENDENTE
title: "A Lei de Uma Tela — pré-voo VERDADE → PROPORÇÃO → MANDATO → PROVA acima de R1-R14"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-06-08
decided_by: [Wagner]
module: governance
supersedes: []
related: [0094, 0095, 0130, 0168, 0231, 0233, 0256]
tags: [governance, protocolo-wagner, pre-flight, verdade-viva, proporcao, tier-0, anti-drift]
---

# ADR — A Lei de Uma Tela (pré-voo de todo turno)

## Status

`aceito` — aprovado por Wagner 2026-06-08 ("aprovar"). Origem: auditoria Cowork
`Auditoria CC - 2026-06-08` (handoff bundle claude.ai/design, projeto "Oimpresso ERP
Comunicação Visual"). O próprio relatório registra que mexer na constituição é soberania do
Wagner: *"gravo como proposta pro Code numerar sob teu OK — não cunho ADR sozinho."*

> **Número PENDENTE:** o working-tree desta branch ia até ADR 0250; main pode conter
> 0251-0256 de PRs recentes (#2385-2391). O número definitivo deve ser cravado no merge
> (via gerador de índice de ADR / Log4brains) pra evitar colisão — ver
> [adr-index-generator].

## Contexto

Numa única sessão de design, Claude afirmou/planejou/produziu **4×** sobre premissa não
verificada contra o `@main`:

| # | Afirmei | Verdade viva | Causa |
|---|---|---|---|
| 1 | "falta drag-drop no Oficina" | já existia nos 2 lados (PR #2228) | li Refs do charter (texto stale), não o código |
| 2 | "Sells está azul, vira roxo" | `sells-cowork.css@main` já era roxo | li a **cópia local** `resources/css/` |
| 3 | "convergência Oficina falta" | já mergeada — PR #2417, 20/20 verde | não reli o charter@main |
| 4 | "Compras é a última ilha (navy)" | `cowork-compras-bundle.css@main` já aliasado | de novo a cópia local |

Agravante estrutural: sobre as premissas erradas de #2/#3/#4 não foi feita uma nota — foi
construído um **Mapa de Identidade de 6 fases + cronograma de ondas + censo de craft**. Uma
catedral sobre areia: quanto maior o artefato, mais ele *parecia* sólido e mais escondia que
a fundação não tinha sido verificada.

## Problema

Não foram 4 erros diferentes — foi **um erro, quatro vezes**: *produzir antes de estabelecer
a verdade viva.* A R3 (pré-flight) e a R14 (proxy ≠ funcionando) já cobriam "leia antes de
afirmar" — e mesmo assim o erro repetiu. Diagnóstico: **regra passiva que não trava no momento
da ação não vale nada.** É a mesma doença curada no código do projeto (warning aspiracional →
trava de CI). A 8ª regra numa lista de 7 que já falhou não resolve.

### Causas-raiz (3 mecanismos, não "desatenção")

- **A. A fotocópia que parece o original.** O projeto Cowork contém cópias de arquivos do repo
  (`resources/css/*`, `*.tsx`, charters). São visualmente idênticas ao git mas envelhecem.
  Tratadas como verdade = o veneno: não é arquivo obviamente velho, é um sósia.
- **B. Produzir gratifica mais que verificar.** Um Mapa bonito dá sensação de progresso; um
  `read` chato não. Daí a inversão da ordem certa.
- **C. Regra passiva ≠ trava ativa.** Uma regra só funciona como checkpoint obrigatório e
  barato no fluxo — não como lembrete atropelável sob empolgação.

## Decisão

Gravar no **topo** do [PROTOCOLO-WAGNER-SEMPRE.md](../../reference/PROTOCOLO-WAGNER-SEMPRE.md)
(o always-read de toda sessão), **acima** de R1-R14 que ela **reorganiza — não substitui**,
uma sequência obrigatória de pré-voo que cabe numa tela:

### `VERDADE → PROPORÇÃO → MANDATO → PROVA`

1. **VERDADE** — li a fonte viva NESTE turno? Todo fato sobre o repo = lido de `@main` agora,
   tag `✓lido` / `⚠não-verifiquei`. Cópia local nunca conta como estado do repo.
   *(reorganiza R3 + R14)*
2. **PROPORÇÃO** ⭐ **(portão novo)** — o tamanho do que vou produzir cabe na minha certeza?
   Premissa não-`✓lida` → o menor sondador possível (pergunta/grep/read). **Nenhum artefato
   maior que 1 arquivo sem `✓lido` da premissa neste turno.** *(não existia em R1-R14)*
3. **MANDATO** — isto já foi decidido? Decidido → EXECUTO, não pergunto.
   *(reorganiza R11 + R13)*
4. **PROVA** — vi 🔴 e 🟢, no escopo completo? Todo ✅ com número/screenshot no mesmo turno.
   *(reorganiza R1 + R14)*

Adicionada também ao auto-check de fim de turno do protocolo (seção "Como Claude detecta
violação no meio da sessão").

## Por que é melhor (não é só "mais regra")

1. **É sequência, não lista.** Ordem obrigatória (Verdade primeiro) é trilho — não dá pra
   produzir no Portão 2 sem passar pelo 1.
2. **Tem o portão que faltava** — Proporção é a cura direta da catedral-sobre-areia.
3. **É barata** — um `read` custa segundos, um Mapa errado custou a sessão. Pré-voo barato é
   o que sobrevive ao calor do momento.
4. **Aplica em Claude o que aplicamos no código** — disciplina mecânica, não aspiracional. A
   garantia final continua sendo os gates de CI do git; a Lei reduz o erro, a máquina é a rede.

## Consequências

- **Positivas:** trava o padrão de drift mais caro da sessão; unifica R1/R3/R11/R13/R14 num
  trilho memorável; não infla a lista de regras (condensa).
- **Custo:** disciplina extra de pré-voo por turno (segundos); risco de virar mais texto
  passivo se não houver ativação no momento certo (mitigar via skill `wagner-protocol-enforce`
  + hook, como R12/R13).
- **Reversível:** se reprovado no futuro, basta remover o bloco do protocolo (nenhum código
  tocado).

## Follow-ups ao cravar número

1. Substituir `number: PENDENTE` pelo número definitivo + mover de `proposals/drafts/` pra
   `memory/decisions/NNNN-lei-de-uma-tela-pre-voo-verdade-proporcao.md`.
2. Atualizar `related_adrs` do PROTOCOLO-WAGNER-SEMPRE.md (frontmatter) com o número.
3. (Opcional) hook advisory `Stop` análogo a `nudge-recommend-not-menu.ps1` reforçando o
   Portão 2 (Proporção) — o portão sem rule-pai anterior.
