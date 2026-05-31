# Feedback Wagner — Ondas multi-PR: execute série inteira sem perguntar (2026-05-28)

> **Origem:** sessão Larissa pós-batch (PRs #1824/#1828/#1830/#1832/#1837/#1839). Wagner já tinha feedback canônico de 2026-05-25 ([feedback-claude-mais-autonomo](feedback-claude-mais-autonomo-2026-05-25.md)) mas Claude continuou perguntando "ataco a próxima onda agora?" entre PRs. Wagner palavras textuais:
>
> *"atacar a próxima precisa eu falar? isso é realmente necessário como deve agir para agradar meu perfil? salve na memória"*

## Regra (complementa 2026-05-25)

Pareada com [feedback-claude-mais-autonomo-2026-05-25](feedback-claude-mais-autonomo-2026-05-25.md) + R10/R11 [PROTOCOLO-WAGNER-SEMPRE](PROTOCOLO-WAGNER-SEMPRE.md) + matriz [publication-policy](../../.claude/skills/publication-policy/SKILL.md):

**Quando Wagner pré-aprova LOTE (ex: "tudo do diagnóstico", "atacar todas as 5 ondas", "todo o plano"), Claude executa a série inteira até desfecho — SEM perguntar "ataco a próxima agora?" entre items.**

**Why:** Wagner perfil = "lote pré-aprovado". Re-perguntar = ele percebe ruído. Comparado a um sócio sênior: você não pergunta "posso continuar o trabalho?" depois de cada arquivo — você continua até o lote acabar.

**How to apply:**

1. Wagner aprova lote ("ok faça todo X") → mapear itens + executar série
2. Entre items: reportar curto ("✓ PR1 merge → PR2 in progress"). NÃO esperar OK.
3. Pausar **só** pra: ambiguidade real no item, decisão fora-do-lote-original, item irreversível Tier 0 (push main direto, migration prod, .env prod, comunicação externa, dep que afeta runtime crítico — DB/IA/pagamento)
4. ADR `status: proposto` mergeado é OK em main (proposto não é canon). Implementação subsequente do ADR proposto = OK no mesmo flow.
5. Admin merge via `gh api PUT /merge` é OK pra PR doc-only com checks verdes (baseline ratchet UI-lint que continua stale do Atendimento é gap conhecido).
6. Final do lote → resumo + "próximo lote?" ou "encerro?". **Aí sim** pergunta.

## Reflexos NOVOS proibidos (acima do 2026-05-25)

| Reflexo proibido | Comportamento certo |
|---|---|
| "Quer que eu mergee agora ou prefere revisar?" depois de CI verde em PR doc-only | Admin merge direto. Reportar SHA + status. |
| "Atacar Onda X.Y agora?" depois de lote pré-aprovado | Iniciar Onda X.Y direto. Reportar ao começar + ao terminar. |
| "Pausar aqui ou seguir?" no meio de lote pré-aprovado | Seguir. Pausa só no fim do lote. |
| Apresentar 3 opções A/B/C ao Wagner depois de ele já ter dito "tudo" | Escolha a A (recomendada do AskUserQuestion anterior) ou aplica heurística "S/M esforço + reversível + escopo lote" → executa. |

## Sinal de violação (esta sessão 2026-05-28)

Wagner pergunta "atacar a próxima precisa eu falar?" → Claude empilhou 4-5 perguntas no fluxo (entre PR #1832 e PR #1837, entre #1837 e #1839, entre #1839 e próxima ação). Cada pergunta = micro-fricção que se acumula.

## Pareado com

- [feedback-claude-mais-autonomo-2026-05-25](feedback-claude-mais-autonomo-2026-05-25.md) — origem da regra
- [PROTOCOLO-WAGNER-SEMPRE](PROTOCOLO-WAGNER-SEMPRE.md) R10 (aprovação cobre escopo, não ação isolada) + R11 (continuar autonomamente até desfecho)
- [ADR 0040](../decisions/0040-policy-publicacao-claude-supervisiona.md) — policy publicação
- [Skill `publication-policy`](../../.claude/skills/publication-policy/SKILL.md) — matriz operacional
