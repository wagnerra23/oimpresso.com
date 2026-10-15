---
id: cowork-producao-celular-casos
continua: cowork-inbox/producao/Producao.casos.md (UC-PCV-01..30)
forma: Mobile Producao.dc.html
owner: wagner
---
# Casos de uso — Produção no celular (UC-PCV-31..50)

> Mesmas regras do base: `business_id` em tudo, apontamento append-only, PT-BR, tenant 98 × 99, discriminante + controle negativo.

### UC-PCV-31 · Dois cronômetros: máquina e pessoa · `must` · ✅ A1 A3
- **Aceite:** Dado Diego com a Roland imprimindo (posto máquina) · Quando ele inicia o acabamento (posto pessoa) · Então os dois apontamentos ficam abertos · E a hora da Roland não conta como hora do Diego · E ele não consegue abrir um 2º apontamento de pessoa.

### UC-PCV-32 · Custo real soma máquina, pessoa e terceiro · `must` valor · ✅
- **Aceite:** Dado 2 h de máquina a R$ 42 + 1 h de pessoa a R$ 28 + terceiro R$ 480 · Então o custo real = R$ 592 · E a conta bate por dois caminhos (dupla prova).

### UC-PCV-33 · Motivos vêm do cadastro do posto · `must` · ✅ A4 A5
- **Aceite:** Dado o posto Roland com 6 motivos de pausa · Então o A4 mostra só esses 6 · E outro posto mostra os dele.

### UC-PCV-34 · QR de etapa alheia não aponta · `must` · ✅ A2
- **Aceite:** Quando Diego lê a etiqueta da Laminação (Felipe) · Então vê "está em Laminação, com Felipe" · E nenhum apontamento é gravado.

### UC-PCV-35 · Deixar a máquina rodando · `must` · ✅ A3
- **Aceite:** Quando o operador toca "Deixar rodando" · Então volta ao A1 · E o apontamento da máquina segue aberto sem pausa.

### UC-PCV-36 · Fecha pela saída do ponto · `must` · ✅ A7 · depende D-PONTO
- **Aceite:** Dado apontamento de pessoa aberto e saída no ponto às 17:52 · Então fecha às 17:52 marcado "automático" · Sem saída · Então fecha no fim do turno · E horas de pessoa no dia > presença + 30 min geram pendência ao gestor · E o Ponto não é alterado.

### UC-PCV-37 · Etiqueta por modelo da empresa · `must` · ✅ E1 E2
- **Aceite:** Empresa com `termica_100x50_etapa` → uma etiqueta por etapa, QR abre a etapa · Empresa com `a4_os` → uma faixa por OS, QR abre "escolha sua etapa".

### UC-PCV-38 · Chegada e veículo · `must` · ✅ I2 I7
- **Aceite:** "Cheguei" grava hora e local · O veículo aponta horas e km como máquina · E entra no custo real.

### UC-PCV-39 · Prova máxima do protocolo · `must` · ✅ I5 I6
- **Aceite:** Concluir exige foto antes, depois e assinatura · O cliente confirma o OTP do WhatsApp · O PDF guarda local, hora, aparelho, OTP e sha256 · Alterar o PDF depois quebra o hash (teste).

### UC-PCV-40 · Critério do piloto · `should` · ⬜
- **Aceite:** O painel interno mostra os 4 números por semana em biz=1 · E o recibo da virada os cita.

### UC-PCV-41 · Terminal fixo · `must` · ✅ T1 · depende D-APARELHO
- **Aceite:** Terminal do posto Roland mostra só o romaneio da Roland · Crachá do Felipe troca o operador · Toques gravam com o usuário do crachá · 2 min inativo → volta ao crachá.

### UC-PCV-42 · Não foi possível instalar · `must` · ✅ I4
- **Aceite:** Motivo + foto obrigatórios · A instalação vai para "a reagendar" · O balcão é avisado · O cliente não recebe mensagem automática.

### UC-PCV-43 · Ferramentas por QR · `should` · ✅ I7
- **Aceite:** "Sair para a rota" só com todos os itens lidos e km de saída · Item não devolvido fica pendente no nome da equipe.

### UC-PCV-44 · Visita vira orçamento · `should` · ✅ V1
- **Aceite:** Visita com 2 fotos, 4,80 × 1,20 m, alvenaria, andaime → rascunho de orçamento com 5,76 m², superfície e acesso preenchidos.

### UC-PCV-45 · Retalho com baixa própria · `should` valor · ✅ A11
- **Aceite:** Guardar 1,37 × 2,10 cria R-NNNN de 2,88 m² · Usar o retalho numa OS baixa do retalho, não do rolo · Soma de estoque bate antes e depois.

### UC-PCV-46 · Imprimir junto · `should` · ✅ A10
- **Aceite:** Duas OS mesma mídia e máquina no dia → sugestão · Aceitar reprograma e registra evento · Recusar esconde a sugestão no dia · Mídia diferente não sugere (discriminante).

### UC-PCV-47 · Pesquisa · `should` · ✅ I8
- **Aceite:** Só depois do protocolo · Uma resposta por OS · Nota ≤ 2 gera alerta ao gestor no dia.

### UC-PCV-48 · Terceiro por link · `must` · ✅ I9
- **Aceite:** O link não mostra preço de venda nem outra OS · Expira na conclusão · Protocolo concluído cria 1 conta a pagar do valor combinado.

### UC-PCV-49 · Mídia pelo RIP · `could` · ✅ A9 · depende D-RIP
- **Aceite:** Leitura do RIP casa com a etapa pelo arquivo · Só baixa após confirmação do operador · Corrigir grava o valor do operador e mantém a leitura original.

### UC-PCV-50 · Alertas no Início do app · `should` · ✅ L1
- **Aceite:** Com 2 atrasadas e 1 parada por arte → 3 itens, cada um com link · Sem problema nenhum → bloco não aparece.
