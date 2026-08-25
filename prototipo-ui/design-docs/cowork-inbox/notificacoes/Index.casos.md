# Modelos de notificação — casos de uso

> Destino canon: `resources/js/Pages/NotificationTemplate/Index.casos.md` (ao lado do charter).
> Formato Dado/Quando/Então com critério de aceite. Rastreabilidade: R = regra do charter, T = teste em `NotificationTemplateTest.php`.

## Permissão e carga

**UC-NOT-01 — Sem permissão** · R3 · T1
Dado um usuário sem `send_notification`, quando abre `/notification-templates`, então recebe 403 e nenhum modelo é renderizado.

**UC-NOT-02 — Abrir a tela** · R1 · T2
Dado um usuário com `send_notification`, quando abre a tela, então vê os 3 grupos com todos os modelos (core + injetados por módulo), o primeiro modelo selecionado e o canal E-mail ativo.

**UC-NOT-23 — Modelo injetado por módulo** · R1 · T2
Dado um módulo que devolve `notification_list` para `customer`, quando a tela carrega, então o modelo aparece no grupo Cliente com as `extra_tags` que o módulo declarou.

## Navegação entre modelos

**UC-NOT-03 — Trocar de modelo** · —
Dado o modelo "Nova venda" aberto no canal SMS, quando seleciono "Novo pedido" no rail, então o painel troca, o canal volta para E-mail e o editor volta para o modo Visual.

**UC-NOT-19 — Buscar no rail** · —
Dado 11 modelos, quando digito "pagamento" na busca, então o rail mostra só Pagamento recebido, Lembrete de pagamento e Pagamento efetuado, e grupos sem resultado desaparecem.

**UC-NOT-20 — Atalho da busca** · —
Dado o foco fora de um campo, quando tecla `/`, então a busca recebe foco; `Esc` limpa o texto e devolve o foco.

**UC-NOT-14 — Canal indisponível** · R5
Dado o modelo "Extrato do cliente", quando abro, então as abas SMS e WhatsApp estão desabilitadas e só o e-mail é editável.

**UC-NOT-18 — Modelo vazio** · R9 · T10
Dado um modelo com os 3 canais vazios, quando olho o rail, então há o selo `vazio` com a explicação "não é enviado", e nenhum envio acontece.

## Edição e gravação

**UC-NOT-04 — Editar e salvar** · R1 · T3
Dado que altero o assunto, quando salvo, então a linha do modelo é gravada e ao recarregar o novo assunto aparece.

**UC-NOT-05 — Salvar em lote** · R1 · T6
Dado que alterei 3 modelos, quando o header mostra "3 modelos alterados" e clico Salvar, então um único POST grava os 3.

**UC-NOT-06 — Descartar** · —
Dado alterações não salvas, quando escolho "Descartar alterações" na lista do contador, então todos os campos voltam ao último estado salvo e o contador zera.

**UC-NOT-07 — Restaurar padrão** · —
Dado um modelo editado, quando clico "Restaurar padrão desta", então os campos voltam ao texto de fábrica, o contador acusa a diferença e nada é gravado até eu salvar.

**UC-NOT-21 — Visual ↔ HTML** · D2
Dado o corpo em modo Visual com negrito e lista, quando alterno para HTML, então vejo o markup correspondente e editável; ao voltar para Visual o conteúdo é o mesmo.

**UC-NOT-24 — CC inválido** · R8 · T-P3
Dado `cc = "não-é-email"`, quando salvo, então o campo acusa erro de validação no servidor e o modelo não é gravado com endereço inválido.

## Tags

**UC-NOT-08 — Inserir tag no meio do texto** · —
Dado o cursor no meio do assunto, quando clico o chip `{invoice_number}`, então a tag entra naquela posição e o cursor fica depois dela.

**UC-NOT-09 — Inserir tag no corpo** · —
Dado o editor Visual focado, quando clico um chip, então a tag entra no HTML do corpo e a prévia passa a mostrar o valor de exemplo correspondente.

**UC-NOT-10 — Tag desconhecida** · R7 · T12
Dado que digitei `{foo_bar}` no corpo, quando o campo perde o foco, então aparece "tag não reconhecida: {foo_bar}" em âmbar — e salvar continua permitido.

**UC-NOT-11 — Campos personalizados** · —
Dado o grupo Contato colapsado, quando clico "+ 10 personalizados", então os 10 chips `{contact_custom_field_1..10}` aparecem e são inseríveis.

## Canais

**UC-NOT-12 — SMS em 2 segmentos** · R11
Dado um texto de 200 caracteres sem acento, quando olho o rodapé, então leio "2 SMS · 160 por segmento".

**UC-NOT-13 — SMS com acento** · R11 · T14
Dado um texto com acento, quando olho o rodapé, então o limite mostrado é 70 por segmento e a UI diz que é por causa do acento.

**UC-NOT-15 — Aviso do logo** · R6
Dado um modelo do grupo Fornecedor, quando abro, então vejo a faixa avisando que `{business_logo}` só é impresso no e-mail.

**UC-NOT-22 — Teste de envio** · D3 · P6
Dado o canal E-mail, quando clico "Enviar teste pra mim", então a confirmação diz o destino (o e-mail do usuário logado) e uma segunda tentativa imediata é barrada pelo throttle.

## Envio automático

**UC-NOT-16 — Ligar automático** · R4 · T5
Dado "Nova venda", quando ligo o e-mail automático e salvo, então o rail mostra o selo `auto` e `auto_send=1` é gravado.

**UC-NOT-17 — Desligar os três** · R2 · T5
Dado "Lembrete de pagamento" com os 3 canais automáticos ligados, quando desligo todos e salvo, então as 3 colunas gravam `0` (checkbox ausente = 0, nunca "manteve o anterior").

**UC-NOT-25 — Lembrete só com automático ligado** · R10 · T11
Dado `payment_reminder` com `auto_send=0`, quando o comando agendado roda, então nenhum e-mail é disparado.
