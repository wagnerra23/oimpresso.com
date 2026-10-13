---
owner: wagner
last_run: "2026-10-13"
---

# Modelos de notificação — casos de uso

Prova do recorte: `tests/js/notificacoes-selecao.test.tsx` teve cinco passes em 09/10. UC-NOT-03 teve somente troca do modelo/canal medida; modo Visual ficou pendente. Demais critérios de edição/preview/envio não foram promovidos. Backend: NotificationTemplateTest (20 passes, dois todo) e NotificationTemplate/DualTest (sete passes). Navegador/pixels ficaram não medidos.

Editor F3 (13/10): `tests/js/notificacoes-editor.test.tsx` teve dez passes em vitest local; duas mutações (cursor e zeragem dos automáticos) derrubaram os testes correspondentes. Medem o POST montado pelo React, não a gravação no banco — a gravação segue provada pelo NotificationTemplateTest.

> Casos importados do pacote aprovado de 19/08, preservando critérios completos. Seleção, editor em texto/HTML, modo Visual, prévia e restaurar padrão entregues (US-NOTIF-004, 13/10).

Modo Visual, prévia e restaurar (13/10): `tests/js/notificacoes-visual.test.tsx` teve seis passes em vitest local; duas mutações (prévia sem resolver as tags e editor com `allow-scripts`) derrubaram os testes correspondentes. O backend de `padroes` está em `tests/Feature/NotificationTemplate/DualTest.php`. Navegador e pixels não foram medidos.
> Formato Dado/Quando/Então com critério de aceite. Rastreabilidade: R = regra do charter, T = teste em `NotificationTemplateTest.php`.

## Permissão e carga

## UC-NOT-01 — Sem permissão · R3 · T1
Status: Permissão medida no CT100; smoke de navegador pendente.
Dado um usuário sem `send_notification`, quando abre `/notification-templates`, então recebe 403 e nenhum modelo é renderizado.

## UC-NOT-02 — Abrir a tela · R1 · T2
Status: Dados/deferimento e seleção medidos no CT100/React; QA visual completo pendente.
Dado um usuário com `send_notification`, quando abre a tela, então vê os 3 grupos com todos os modelos (core + injetados por módulo), o primeiro modelo selecionado e o canal E-mail ativo.

## UC-NOT-23 — Modelo injetado por módulo · R1 · T2
Status: Injeção e extra_tags medidas no teste dual CT100; navegador pendente.
Dado um módulo que devolve `notification_list` para `customer`, quando a tela carrega, então o modelo aparece no grupo Cliente com as `extra_tags` que o módulo declarou.

## Navegação entre modelos

## UC-NOT-03 — Trocar de modelo · —
Status: Troca, reset de canal e volta ao modo Visual medidos em vitest; navegador pendente.
Dado o modelo "Nova venda" aberto no canal SMS, quando seleciono "Novo pedido" no rail, então o painel troca, o canal volta para E-mail e o editor volta para o modo Visual.

## UC-NOT-19 — Buscar no rail · —
Status: Busca/grupos medidos em React; combinação exata dos 11 modelos pendente.
Dado 11 modelos, quando digito "pagamento" na busca, então o rail mostra só Pagamento recebido, Lembrete de pagamento e Pagamento efetuado, e grupos sem resultado desaparecem.

## UC-NOT-20 — Atalho da busca · —
Status: Atalhos medidos em React; retorno de foco completo pendente.
Dado o foco fora de um campo, quando tecla `/`, então a busca recebe foco; `Esc` limpa o texto e devolve o foco.

## UC-NOT-14 — Canal indisponível · R5
Status: Canais desabilitados medidos em React; edição React pendente.
Dado o modelo "Extrato do cliente", quando abro, então as abas SMS e WhatsApp estão desabilitadas e só o e-mail é editável.

## UC-NOT-18 — Modelo vazio · R9 · T10
Status: Pendente no recorte F3; critérios integrais preservados abaixo, sem declarar prova do editor/envio.
Dado um modelo com os 3 canais vazios, quando olho o rail, então há o selo `vazio` com a explicação "não é enviado", e nenhum envio acontece.

## Edição e gravação

## UC-NOT-04 — Editar e salvar · R1 · T3
Status: Editor React monta o POST do modelo alterado (vitest); gravação/recarga medidas no backend; smoke de navegador pendente.
Dado que altero o assunto, quando salvo, então a linha do modelo é gravada e ao recarregar o novo assunto aparece.

## UC-NOT-05 — Salvar em lote · R1 · T6
Status: Contador e POST único com três modelos medidos em vitest; navegador pendente.
Dado que alterei 3 modelos, quando o header mostra "3 modelos alterados" e clico Salvar, então um único POST grava os 3.

## UC-NOT-06 — Descartar · —
Status: Descartar e contador zerado medidos em vitest; a lista suspensa do protótipo virou botão direto no cabeçalho.
Dado alterações não salvas, quando escolho "Descartar alterações" na lista do contador, então todos os campos voltam ao último estado salvo e o contador zera.

## UC-NOT-07 — Restaurar padrão · —
Status: Texto de fábrica aplicado sem POST e contador em 1 medidos em vitest; `padroes` vem do controller (DualTest). Modelo de módulo sem padrão deixa o botão desabilitado.
Dado um modelo editado, quando clico "Restaurar padrão desta", então os campos voltam ao texto de fábrica, o contador acusa a diferença e nada é gravado até eu salvar.

## UC-NOT-21 — Visual ↔ HTML · D2
Status: Ida e volta Visual ↔ HTML com edição nos dois lados medida em vitest (jsdom); negrito e lista via execCommand não medidos fora do navegador.
Dado o corpo em modo Visual com negrito e lista, quando alterno para HTML, então vejo o markup correspondente e editável; ao voltar para Visual o conteúdo é o mesmo.

## UC-NOT-24 — CC inválido · R8 · T-P3
Status: Erro do servidor em CC/BCC é exibido no campo pelo onError; validação provada no backend; render do erro sem teste React.
Dado `cc = "não-é-email"`, quando salvo, então o campo acusa erro de validação no servidor e o modelo não é gravado com endereço inválido.

## Tags

## UC-NOT-08 — Inserir tag no meio do texto · —
Status: Inserção no cursor do assunto medida em vitest; navegador pendente.
Dado o cursor no meio do assunto, quando clico o chip `{invoice_number}`, então a tag entra naquela posição e o cursor fica depois dela.

## UC-NOT-09 — Inserir tag no corpo · —
Status: Chip no editor Visual entra no HTML e a prévia mostra o valor de exemplo, medidos em vitest. A prévia exibe só o HTML devolvido pelo `HtmlSanitizer` (`POST /notification-templates/previa`, DualTest) e, se o servidor falhar, mostra aviso em vez do HTML cru; navegador pendente.
Dado o editor Visual focado, quando clico um chip, então a tag entra no HTML do corpo e a prévia passa a mostrar o valor de exemplo correspondente.

## UC-NOT-10 — Tag desconhecida · R7 · T12
Status: Aviso de tag desconhecida sem bloquear Salvar medido em vitest; aviso aparece ao digitar, não só ao perder o foco.
Dado que digitei `{foo_bar}` no corpo, quando o campo perde o foco, então aparece "tag não reconhecida: {foo_bar}" em âmbar — e salvar continua permitido.

## UC-NOT-11 — Campos personalizados · —
Status: Dez personalizados recolhidos e expandidos medidos em vitest.
Dado o grupo Contato colapsado, quando clico "+ 10 personalizados", então os 10 chips `{contact_custom_field_1..10}` aparecem e são inseríveis.

## Canais

## UC-NOT-12 — SMS em 2 segmentos · R11
Status: Dois segmentos para 200 caracteres medidos em vitest.
Dado um texto de 200 caracteres sem acento, quando olho o rodapé, então leio "2 SMS · 160 por segmento".

## UC-NOT-13 — SMS com acento · R11 · T14
Status: Limite 70 com acento medido em vitest.
Dado um texto com acento, quando olho o rodapé, então o limite mostrado é 70 por segmento e a UI diz que é por causa do acento.

## UC-NOT-15 — Aviso do logo · R6
Status: Aviso medido em React; pixels/contraste pendentes.
Dado um modelo do grupo Fornecedor, quando abro, então vejo a faixa avisando que `{business_logo}` só é impresso no e-mail.

## UC-NOT-22 — Teste de envio · D3 · P6
Status: Botão usa a rota existente com canal e-mail e só a versão gravada (vitest); throttle provado só no backend; mensagem de destino depende do flash global, navegador pendente.
Dado o canal E-mail, quando clico "Enviar teste pra mim", então a confirmação diz o destino (o e-mail do usuário logado) e uma segunda tentativa imediata é barrada pelo throttle.

## Envio automático

## UC-NOT-16 — Ligar automático · R4 · T5
Status: Ligar e-mail automático no new_sale e enviar auto_send=1 medidos em vitest; selo no rail após salvar depende do reload, navegador pendente.
Dado "Nova venda", quando ligo o e-mail automático e salvo, então o rail mostra o selo `auto` e `auto_send=1` é gravado.

## UC-NOT-17 — Desligar os três · R2 · T5
Status: Três automáticos desligados saem 0 no POST (vitest); backend já grava ausente=0.
Dado "Lembrete de pagamento" com os 3 canais automáticos ligados, quando desligo todos e salvo, então as 3 colunas gravam `0` (checkbox ausente = 0, nunca "manteve o anterior").

## UC-NOT-25 — Lembrete só com automático ligado · R10 · T11
Status: Pendente no recorte F3; critérios integrais preservados abaixo, sem declarar prova do editor/envio.
Dado `payment_reminder` com `auto_send=0`, quando o comando agendado roda, então nenhum e-mail é disparado.
