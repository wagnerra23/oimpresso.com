# Feedback Wagner — Claude mais autônomo (2026-05-25)

> **Origem:** sessão `frosty-greider-83ab2f` 2026-05-25. Wagner perguntou "falta muito pra tornar um agente autônomo?" — Claude respondeu sobre o ADS (produto) mas Wagner clarificou: queria EU (Claude Code aqui) operar mais autônomo no projeto, sem listar opções a/b/c, sem pedir permissão pra ações rotineiras.

## Regra

Wagner já delegou supervisão via [ADR 0040](../decisions/0040-policy-publicacao-claude-supervisiona.md) + skill [`publication-policy`](../../.claude/skills/publication-policy/SKILL.md) + R11 do [PROTOCOLO-WAGNER-SEMPRE](PROTOCOLO-WAGNER-SEMPRE.md) ("continuar autonomamente até desfecho dentro do escopo pré-aprovado").

**O problema NÃO é falta de regra — é Claude não cumprindo a regra existente.** Quando Wagner pede ajuste, isso é sinal de degradação comportamental, não de gap na governança.

## 3 reflexos que Claude DEVE cortar

| Reflexo proibido | Comportamento certo |
|---|---|
| Listar opções `(a) (b) (c) qual você quer?` quando há heurística clara | Aplicar heurística: reversível <5min + escopo próprio → executar direto. Reportar depois. |
| Perguntar "posso commitar/pushar/abrir PR?" em branch própria | **Pode.** Faça. Reporte no fim. |
| Empilhar perguntas no fim do turno ("quer X? Y? Z?") | Executar o que está na matriz Claude. Escalar UMA coisa (a do Wagner). Pular o resto. |

## Quando AINDA escalar (sem mudança)

A matriz da `publication-policy` permanece autoritativa:
- Push direto `main` · Merge PR pra `main`
- Migration prod · `.env` prod
- Dep que afeta runtime crítico (DB, IA, pagamento)
- Comunicação externa cliente / comercial / legal
- Mudança Tier 0 IRREVOGÁVEL (multi-tenant, MWART, F3 Cowork)

## Heurística de dúvida

> "Em dúvida real → produzir o draft pronto + perguntar 1 linha. Não 3 perguntas." (publication-policy linha 74)

## Sinal de violação

Wagner pergunta "voce não consegue?" / "tem que procurar outro?" / "trabalha mais autônomo" → Claude empilhou opções em vez de executar. Re-ler este doc + cumprir.

## Pareado com

- [ADR 0040](../decisions/0040-policy-publicacao-claude-supervisiona.md) — política de publicação
- [PROTOCOLO-WAGNER-SEMPRE](PROTOCOLO-WAGNER-SEMPRE.md) R10/R11 — aprovação cobre escopo, não ação isolada
- [Skill `publication-policy`](../../.claude/skills/publication-policy/SKILL.md) — matriz operacional
- [Skill `wagner-request-refiner`](../../.claude/skills/wagner-request-refiner/SKILL.md) — refinar pedido vago (mas NÃO virar 3 perguntas)
