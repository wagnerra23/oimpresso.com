# oimpresso — textos das lojas (pt-BR) · 1ª SUBMISSÃO · RASCUNHO para [W] revisar

> ⛔ **NÃO USAR NA SUBMISSÃO (D13, [W] 2026-10-01):** a v1 voltou às 7 áreas (Início, Tarefas, Pedidos, Produção,
> Pessoas, Ponto, Mais) e a submissão só acontece quando elas existirem no app. Este arquivo "só Ponto" fica guardado
> como histórico. O texto a usar será o de `listagem-pt-BR-completa.md`, revisado quando as telas ficarem prontas.


> **Escopo da 1ª submissão = o que o app tem hoje** (sessão que gerencia o app, 2026-10-01): Login, Início (escala do
> dia + indicadores do ponto), Ponto (Bater · Meu espelho · Justificar) e Conta, mais o lembrete de bater ponto (push).
> Texto de loja que promete função ausente é motivo de recusa. A versão com Tarefas, Pedidos, Produção e Pessoas
> fica em [`listagem-pt-BR-completa.md`](listagem-pt-BR-completa.md) para quando essas telas entrarem no app.
> Decisões em [`../DECISOES.md`](../DECISOES.md). Sem "REP-P", "REP" nem "registrador oficial" (D9).
> Limites conferidos em 2026-10-01 (Play Console Help + App Store Connect).

## Comum
- **Nome do app:** `oimpresso` (9/30) — decidido por [W] em 2026-10-01: "oimpresso nos dois".
- **Categoria:** Negócios (Play: Business · Apple: primária Business, secundária Productivity)
- **Público:** adultos, uso profissional (B2B). Sem anúncios. Sem compras no app.
- **Login exigido:** sim — conta criada pela empresa. Sem cadastro aberto.

## Google Play
- **Título:** `oimpresso` (9/30)
- **Descrição curta:** `Registro de ponto pelo celular, com a localização, no sistema da sua empresa.` (77/80)
- **Descrição completa (≤4000):**

O oimpresso é o registro de ponto pelo celular para quem trabalha em empresas que usam o sistema oimpresso.

Entre com o acesso fornecido pela sua empresa e:
• Veja no Início a sua escala do dia e o resumo do seu ponto.
• Bata o ponto: entrada, saída para almoço, retorno e saída. Cada marcação vai com a localização do aparelho no momento do registro e recebe um número sequencial gerado pelo servidor; depois de feita, não pode ser alterada.
• Consulte o seu espelho do mês: horas trabalhadas e o dia a dia.
• Justifique uma falta, atraso ou esquecimento — o pedido vai para aprovação do seu gestor.
• Receba um lembrete na hora de bater o ponto.

Privacidade e transparência:
• A localização é lida somente no momento em que você bate o ponto, com o app aberto. Não há rastreamento em segundo plano.
• O app não usa câmera, foto nem biometria.
• Se o sinal de GPS estiver fraco, o app pede que você vá para uma área aberta em vez de registrar uma posição imprecisa.

Para quem é: colaboradores de empresas clientes do oimpresso. O acesso é liberado pela empresa; não é possível criar conta pelo app.

## Apple App Store
- **Nome:** `oimpresso` (9/30)
- **Subtítulo (30):** `Ponto da equipe no celular` (26/30)
- **Texto promocional (170):** `Bata o ponto com a localização do aparelho, veja o espelho do mês e justifique ausências. Sem câmera e sem biometria.` (117/170)
- **Palavras-chave (100):** `ponto,registro,jornada,espelho,colaborador,RH,horas,marcação,GPS,funcionário,escala` (83/100)
- **Descrição:** a mesma da Play acima.
- **URL de privacidade:** `https://oimpresso.com/privacidade` (#8437)
- **Informações para revisão:** contas e instruções em `memory/requisitos/Ponto/REVISAO-LOJAS-NOTAS.md` (credenciais só no campo do console).

## Classificação etária
- **Apple (faixas 4+/9+/13+/16+/18+):** "Nenhum" em todo o questionário → **4+**.
- **Google (IARC):** "Todos os outros tipos de app"; "Não" a violência, sexo, linguagem, drogas, jogos de azar; "Não" a compartilhar localização com outros usuários (vai só ao empregador); "Não" a interação entre usuários. Esperado: **Livre (L)**.
