# oimpresso — textos das lojas (pt-BR) · VERSÃO COMPLETA (futura) · RASCUNHO

> ⏸️ **Não usar na 1ª submissão** — promete Tarefas, Pedidos, Produção e Pessoas, que o app ainda não tem
> (sessão que gerencia o app, 2026-10-01). Vale quando essas telas entrarem; até lá use [`listagem-pt-BR.md`](listagem-pt-BR.md).

> Escopo decidido por [W] em 2026-10-01: **um app só**, o ERP no celular **junto com o ponto** (`com.oimpresso.app`).
> Base decidida por [W] (2026-10-01, coordenação): **Capacitor** (decisões vigentes em [`../DECISOES.md`](../DECISOES.md)); o Expo de `mobile/` fica fora das lojas.
> **O que o app mostra (D5 em [`../DECISOES.md`](../DECISOES.md)):** telas próprias no `oimpresso-app`, visual do protótipo
> Mobile (v1: Início, Tarefas, Pedidos, Produção, Pessoas, Ponto, Mais). Textos descrevem só essas funções — **não**
> prometer Venda rápida, Finanças, OS, Estoque nem Fiscal até existirem no app.
> ⚠️ Levantamento legal (#8417): até INPI e certificado ICP-Brasil, **não** anunciar "REP-P", "REP" nem "registrador oficial" —
> usar "registro de ponto do oimpresso". Limites conferidos em 2026-10-01 (Play Console Help + App Store Connect).
> Não usar "certificado", "homologado" nem "assinado" até a sessão LEGAL confirmar (ADR 0413 tirou "assinada").
> Só listar o que o build enviado faz de verdade — se o ponto ainda não estiver no build, tirar o bloco do ponto.

## Comum
- **Nome do app:** `oimpresso` (9/30) — **decidido por [W] em 2026-10-01: "oimpresso nos dois"** (loja e aparelho). Reserva, só com ok [W], se o nome estiver ocupado na Apple: `oimpresso ERP` (13/30)
- **Categoria:** Negócios (Play: Business · Apple: primária Business, secundária Productivity)
- **Público:** adultos, uso profissional (B2B). Sem anúncios. Sem compras no app.
- **Login exigido:** sim — conta criada pela empresa. Sem cadastro aberto.

## Google Play
- **Título:** `oimpresso` (9/30)
- **Descrição curta:** `Pedidos, produção, tarefas, pessoas e ponto da sua empresa no celular.` (70/80)
- **Descrição completa (≤4000):**

O oimpresso leva para o celular o essencial do sistema da sua empresa: acompanhe pedidos e produção, resolva as tarefas do dia, consulte clientes e fornecedores, e registre o ponto com a localização do aparelho.

Gestão no celular:
• Início — o resumo do dia da sua empresa.
• Tarefas — as pendências que chegam de todos os setores, numa caixa só.
• Pedidos — consulte e acompanhe cada pedido e a etapa em que está.
• Produção — veja a fila de produção e o andamento de cada trabalho.
• Pessoas — clientes, fornecedores e colaboradores num cadastro só.

Registro de ponto do oimpresso:
• Bata o ponto: entrada, saída para almoço, retorno e saída. Cada marcação vai com a localização do aparelho no momento do registro e recebe um número sequencial gerado pelo servidor; depois de feita, não pode ser alterada.
• Veja as marcações de hoje e o seu espelho do mês.
• Justifique uma falta ou esquecimento — o pedido vai para o seu gestor.
• A localização é lida só quando você bate o ponto, com o app aberto. Sem rastreamento em segundo plano. Sem câmera e sem biometria no ponto.

Para quem é: empresas clientes do oimpresso e seus colaboradores. O acesso é liberado pela empresa; não é possível criar conta pelo app. Cada pessoa vê só o que o seu perfil permite.

## Apple App Store
- **Nome:** `oimpresso` (9/30)
- **Subtítulo (30):** `Pedidos, produção e ponto` (25/30)
- **Texto promocional (170):** `Pedidos, produção, tarefas e o ponto dos colaboradores no mesmo app. Ponto com localização, sem câmera e sem biometria.` (119/170)
- **Palavras-chave (100):** `ERP,gestão,pedidos,produção,tarefas,clientes,fornecedores,ponto,jornada,gráfica,equipe` (86/100)
- **Descrição:** a mesma da Play acima.
- **URL de privacidade:** `https://oimpresso.com/privacidade` (#8437)
- **Informações para revisão:** conta demo da sessão CONTA DEMO (credencial só no campo do console). Nota: "Acesso B2B criado pela empresa. A tela de ponto pede localização; fora do local da empresa a marcação é aceita e sinalizada para o gestor."

## Classificação etária
- **Apple (faixas 4+/9+/13+/16+/18+):** "Nenhum" em todo o questionário → **4+**. ⚠️ Se a aba **Chat** passar a trocar mensagens entre pessoas, responder "Sim" a comunicação entre usuários (pode subir a faixa) — hoje o chat é com o sistema.
- **Google (IARC):** "Todos os outros tipos de app"; "Não" a violência, sexo, linguagem, drogas, jogos de azar; "Não" a compartilhar localização com outros usuários (vai só ao empregador); interação entre usuários conforme o Chat acima. Esperado: **Livre (L)**.
