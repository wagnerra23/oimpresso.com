# oimpresso — textos das lojas (pt-BR) · escopo D16 · RASCUNHO para [W] revisar

> **Escopo (D16, [W] 2026-10-02):** todas as telas do protótipo entram no app **antes** do envio às lojas, exceto as
> que o [W] tirou em 2026-10-02: **24, 31, 32 e 33** (Equipamentos, Equipamento, Novo equipamento, Locais) e **27**
> (Detalhe da OP, que fica na retaguarda web). Estes textos não citam nenhuma delas.
> Decisões em [`../DECISOES.md`](../DECISOES.md). Screenshots: `store-assets/screenshots/` no repo `oimpresso-app`.
>
> **Regras que valem para o texto todo:**
> - A ficha só vai à loja quando cada função descrita existir no build enviado (D9: função ausente = recusa).
>   Antes de enviar, conferir a lista "Conferir no build" no fim deste arquivo.
> - Sem "REP-P", "REP" nem "registrador oficial" até INPI e certificado ICP-Brasil (levantamento legal #8417).
>   Usar "registro de ponto". Não usar "certificado", "homologado" nem "assinado" até a sessão LEGAL confirmar.
> - Sem valores em reais e sem nome de cliente real.
> - Limites de caracteres conferidos em 2026-10-01 (Play Console Help + App Store Connect). As contagens ao lado de
>   cada campo foram medidas com `len()` em Python sobre o texto entre crases.
>
> Histórico: até 2026-10-05 este arquivo descrevia as 7 áreas da v1 (D13), substituída pela D16.

## Comum
- **Nome do app:** `oimpresso` (9/30), decidido por [W] em 2026-10-01: "oimpresso nos dois". Reserva, só com ok [W], se o nome estiver ocupado na Apple: `oimpresso ERP` (13/30).
- **Categoria:** Negócios (Play: Business · Apple: primária Business, secundária Productivity).
- **Público:** adultos, uso profissional (B2B). Sem anúncios. Sem compras no app.
- **Login exigido:** sim, com conta criada pela empresa. Sem cadastro aberto.

## Google Play
- **Título:** `oimpresso` (9/30)
- **Descrição curta:** `Vendas, pedidos, produção, estoque, financeiro e ponto da empresa no celular.` (77/80)
- **Descrição completa (≤4000):**

O oimpresso leva para o celular o sistema de gestão da sua empresa. Acompanhe pedidos e produção, consulte produtos, estoque e financeiro, cuide das ordens de serviço da oficina e registre o ponto com a localização do aparelho.

Vendas e pedidos
• Início: o resumo do dia, com faturamento, pedidos atrasados, itens com estoque baixo e contas a receber e a pagar.
• Pedidos: consulte cada pedido, o prazo e a etapa em que está.
• Venda rápida: registre uma venda no balcão pelo celular, com carrinho e forma de pagamento.
• Orçamentos: veja as propostas enviadas e as aprovadas.
• Pessoas: clientes, fornecedores e colaboradores num cadastro só, com a ficha de cada um.

Produção e tarefas
• Produção: a fila de trabalho por etapa.
• Tarefas: as pendências de todos os setores numa caixa só, com o detalhe de cada uma.
• Notificações: avisos do sistema reunidos num lugar.

Produtos e estoque
• Produtos: o catálogo com preço, unidade e saldo. Cadastre um produto novo pelo celular.
• Estoque: saldo por loja e os itens abaixo do mínimo.
• Movimentações: o histórico de entradas e saídas de cada item.

Financeiro
• Financeiro: saldo do mês, contas a receber e a pagar, e o extrato.
• Pagamentos: gere um link de cobrança e veja quem já pagou.
• Fiscal: as notas emitidas e as que voltaram com rejeição.
• Relatórios e Dashboard: os números de vendas, produção, estoque e resultado.

Oficina
• Ordens de serviço: o pátio por etapa, as ordens paradas e as prontas para retirar.
• Veículos: a lista de veículos e o histórico de serviços de cada um.

Equipe e ajustes
• Equipe: quem está na equipe e a carga de trabalho de cada pessoa.
• Assistente: pergunte à Jana, a assistente do sistema, sobre os dados da sua empresa.
• Meu menu: escolha até três módulos para a barra de baixo.

Registro de ponto
• Bata o ponto: entrada, saída para almoço, retorno e saída. Cada marcação vai com a localização do aparelho no momento do registro e recebe um número sequencial gerado pelo servidor. Depois de feita, não pode ser alterada.
• Veja as marcações de hoje e o seu espelho do mês.
• Justifique uma falta ou um esquecimento. O pedido vai para o seu gestor, que valida as marcações feitas fora do local da empresa.
• A localização é lida só quando você bate o ponto, com o app aberto. Sem rastreamento em segundo plano. Sem câmera e sem biometria.

Para quem é: empresas clientes do oimpresso e os seus colaboradores. O acesso é liberado pela empresa; não é possível criar conta pelo app. Cada pessoa vê só o que o seu perfil permite, e cada módulo aparece só para a empresa que o contratou.

- **Novidades desta versão (Play, ≤500):** `Primeira versão do oimpresso nas lojas: venda rápida, pedidos, produção, tarefas, produtos, estoque, financeiro, ordens de serviço e registro de ponto no celular.` (162/500)

## Apple App Store
- **Nome:** `oimpresso` (9/30)
- **Subtítulo (30):** `Gestão e ponto da sua empresa` (29/30)
- **Texto promocional (170):** `Pedidos, produção, estoque, financeiro, oficina e o ponto da equipe no mesmo app. Ponto com localização, sem câmera e sem biometria.` (132/170)
- **Palavras-chave (100):** `ERP,gestão,pedidos,venda,produção,estoque,financeiro,oficina,ponto,jornada,clientes,orçamento` (93/100)
- **Descrição:** a mesma da Play acima.
- **Novidades desta versão (What's New):** a mesma da Play acima. Na 1ª versão a Apple não mostra este campo; ele passa a valer na 2ª.
- **URL de privacidade:** `https://oimpresso.com/privacidade` (#8437)
- **Informações para revisão:** conta demo da sessão CONTA DEMO, com a credencial só no campo do console. Nota: "Acesso B2B criado pela empresa. A tela de ponto pede localização; fora do local da empresa a marcação é aceita e sinalizada para o gestor. As capturas mostram o app de quem tem o sistema; a conta de colaborador vê só o Ponto e o Mais."

## Classificação etária
- **Apple (faixas 4+/9+/13+/16+/18+):** "Nenhum" em todo o questionário → **4+**. O Assistente conversa com o sistema, não com outras pessoas: responder "Não" a comunicação entre usuários. Se o questionário perguntar por conteúdo gerado por IA, responder que existe (o Assistente responde com IA sobre os dados da empresa).
- **Google (IARC):** "Todos os outros tipos de app"; "Não" a violência, sexo, linguagem, drogas e jogos de azar; "Não" a compartilhar localização com outros usuários (vai só ao empregador); "Não" a interação entre usuários. Esperado: **Livre (L)**.

## Conferir no build antes de enviar
Cada item abaixo tem de abrir no build enviado. O que faltar sai do texto antes do envio.

| Função citada | Tela do protótipo | No `main` do app em 2026-10-05 (`f107dcf`) |
|---|---|---|
| Início, Pedidos, Produção, Tarefas, Pessoas, Ponto, Mais, Conta | 00, 01, 02, 10, 12, 17, 18, 21, 22, 36, 37, 38 | sim |
| Orçamentos, Notificações, Detalhe da tarefa, Nova pessoa, Ficha cadastral | 04, 16, 28, 09, 34 | sim |
| Produtos, Estoque, Movimentações | 19, 05, 29 | sim |
| Cadastrar produto pelo celular | 20 | sim (#51) |
| Venda rápida | 11 | sim (#39) |
| Financeiro, Pagamentos, Fiscal, Relatórios, Dashboard | 06, 15, 14, 13, 35 | sim (Pagamentos já gera link de cobrança) |
| Ordens de serviço, Veículos, histórico de OS | 07, 03, 08, 23 | sim |
| Equipe, Assistente, Meu menu, Validar ponto | 26, 25, 30, 39 | sim |
