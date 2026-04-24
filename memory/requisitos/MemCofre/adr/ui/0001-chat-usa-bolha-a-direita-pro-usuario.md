# ADR UI-0001 · Chat usa bolha à direita pro usuário, esquerda pro assistente

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: ui

## Contexto

A tela `/docs/chat` renderiza um histórico de mensagens intercaladas entre usuário e assistente. Duas abordagens de layout foram avaliadas:

1. **Alinhamento único (lista vertical)**: todas mensagens alinhadas à esquerda, igual email. Mais fácil de ler, mas não dá pra bater o olho e identificar autor.
2. **Bolhas lado a lado**: usuário à direita, assistente à esquerda — convenção visual do WhatsApp/iMessage/Slack.

## Decisão

Usar **bolhas com alinhamento alternado**:

- Usuário: `flex-row-reverse`, bolha com `bg-primary text-primary-foreground`.
- Assistente: alinhamento padrão à esquerda, bolha `bg-muted/50`.
- Avatar circular pequeno (32px) ao lado da bolha — ícone `UserIcon` ou `Bot`.

Tamanho máximo da bolha: 55 chars (`style={{ maxWidth: '55ch' }}`) pra manter largura de leitura confortável.

## Consequências

**Positivas:**
- Convenção visual familiar — onboarding zero.
- Identificação de autor instantânea no scroll rápido.
- Diferencia tom: resposta técnica (esquerda, neutra) × pergunta (direita, destacada).

**Negativas:**
- Em telas muito estreitas (<400px) as bolhas ficam apertadas. Mitigação: `break-words` + max-width responsivo (Fase futura).
- Contraste do `text-primary-foreground` depende do tema — testar no dark mode.

## Alternativas consideradas

- **Single column**: descartado — perde a distinção visual.
- **Tabela com coluna role**: descartado — engessa layout.
- **Bolhas ambas à esquerda, cor diferente**: viável, mas perde a economia cognitiva do "eu = direita".
