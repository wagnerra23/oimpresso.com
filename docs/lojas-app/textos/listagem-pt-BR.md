# oimpresso — textos das lojas (pt-BR) · RASCUNHO para [W] revisar

> Escopo decidido por [W] em 2026-10-01: **um app só**, o ERP no celular **junto com o ponto** (`com.oimpresso.app`).
> Base decidida por [W] (2026-10-01, coordenação): **Capacitor com o ERP web inteiro**; o Expo de `mobile/` fica fora das lojas.
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
- **Descrição curta:** `Pedidos, produção, ordens de serviço e ponto da sua empresa no celular.` (71/80)
- **Descrição completa (≤4000):**

O oimpresso é o ERP da sua empresa no celular. Acompanhe vendas, produção e ordens de serviço de onde estiver, e registre o ponto com a localização do aparelho.

Gestão no celular:
• Vendas, orçamentos e pedidos — consulte, acompanhe e faça uma venda rápida.
• Produção — veja em que etapa está cada trabalho.
• Ordens de serviço e manutenção — status, equipamentos e fotos do serviço.
• Clientes e produtos — consulta e cadastro.
• Estoque, financeiro, fiscal e relatórios — os números do dia na mão.
• Tarefas e notificações da equipe.

Registro de ponto do oimpresso:
• Bata o ponto: entrada, saída para almoço, retorno e saída. Cada marcação vai com a localização do aparelho no momento do registro e recebe um número sequencial gerado pelo servidor; depois de feita, não pode ser alterada.
• Veja as marcações de hoje e o seu espelho do mês.
• Justifique uma falta ou esquecimento — o pedido vai para o seu gestor.
• A localização é lida só quando você bate o ponto, com o app aberto. Sem rastreamento em segundo plano. Sem câmera e sem biometria no ponto.

Para quem é: empresas clientes do oimpresso e seus colaboradores. O acesso é liberado pela empresa; não é possível criar conta pelo app. Cada pessoa vê só o que o seu perfil permite.

## Apple App Store
- **Nome:** `oimpresso` (9/30)
- **Subtítulo (30):** `Gestão e ponto no celular` (25/30)
- **Texto promocional (170):** `Vendas, produção, ordens de serviço e o ponto dos colaboradores no mesmo app. Ponto com localização, sem câmera e sem biometria.` (128/170)
- **Palavras-chave (100):** `ERP,gestão,pedidos,orçamento,produção,OS,estoque,financeiro,ponto,jornada,gráfica,oficina,vendas` (96/100)
- **Descrição:** a mesma da Play acima.
- **URL de suporte / privacidade:** _(sessão PRIVACIDADE/PWA — aguardando)_
- **Informações para revisão:** conta demo da sessão CONTA DEMO (credencial só no campo do console). Nota: "Acesso B2B criado pela empresa. A tela de ponto pede localização; fora do local da empresa a marcação é aceita e sinalizada para o gestor."

## Classificação etária
- **Apple (faixas 4+/9+/13+/16+/18+):** "Nenhum" em todo o questionário → **4+**. ⚠️ Se a aba **Chat** passar a trocar mensagens entre pessoas, responder "Sim" a comunicação entre usuários (pode subir a faixa) — hoje o chat é com o sistema.
- **Google (IARC):** "Todos os outros tipos de app"; "Não" a violência, sexo, linguagem, drogas, jogos de azar; "Não" a compartilhar localização com outros usuários (vai só ao empregador); interação entre usuários conforme o Chat acima. Esperado: **Livre (L)**.
