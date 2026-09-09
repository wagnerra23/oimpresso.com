<?php
// database/migrations/2026_08_19_000000_traduzir_notification_templates_pt_br.php
//
// Traduz para PT-BR os modelos de notificação semeados em inglês por
// NotificationTemplate::defaultNotificationTemplates() (assunto · corpo do e-mail · SMS).
// Decisão D1 do handoff cowork-inbox/NOTIFICACOES-F1-2026-08-19.md, autorizada por [W] 2026-08-19.
//
// SEGURANÇA: só reescreve a linha cujo conteúdo AINDA É o seed inglês (comparação normalizada:
// tags HTML fora, espaços colapsados). Modelo que o negócio já editou fica intacto — mesmo que
// esteja em inglês por escolha dele. Nada é apagado; `whatsapp_text` só é preenchido se estiver vazio.
//
// down(): devolve o texto inglês nas linhas que continuarem idênticas à tradução desta migration.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Modelos: chave => [assunto_en, corpo_en, sms_en, assunto_pt, corpo_pt, sms_pt, wa_pt] */
    private function mapa(): array
    {
        return [
            'new_sale' => [
                'Thank you from {business_name}',
                'Dear {contact_name}, Your invoice number is {invoice_number} Total amount: {total_amount} Paid amount: {received_amount} Thank you for shopping with us. {business_logo}',
                'Dear {contact_name}, Thank you for shopping with us. {business_name}',
                'Obrigado pela compra — {business_name}',
                '<p>Olá {contact_name},</p><p>Sua venda <strong>{invoice_number}</strong> foi registrada.</p><ul><li>Total: {total_amount}</li><li>Pago: {paid_amount}</li><li>Em aberto: {due_amount}</li></ul><p>Documento: <a href="{invoice_url}">{invoice_url}</a></p><p>Obrigado por comprar com a gente.</p><p>{business_name}</p>',
                '{contact_name}, sua venda {invoice_number} foi registrada. Total {total_amount}. Obrigado! {business_name}',
                'Olá {contact_name}! Sua venda {invoice_number} foi registrada — total {total_amount}, em aberto {due_amount}. Documento: {invoice_url}',
            ],
            'payment_received' => [
                'Payment Received, from {business_name}',
                'Dear {contact_name}, We have received a payment of {received_amount} {business_logo}',
                'Dear {contact_name}, We have received a payment of {received_amount}. {business_name}',
                'Pagamento recebido — {business_name}',
                '<p>Olá {contact_name},</p><p>Recebemos seu pagamento de <strong>{received_amount}</strong> (ref. {payment_ref_number}) referente ao documento {invoice_number}.</p><p>Obrigado.</p><p>{business_name}</p>',
                '{contact_name}, recebemos seu pagamento de {received_amount}. Obrigado! {business_name}',
                'Olá {contact_name}! Recebemos seu pagamento de {received_amount} (ref. {payment_ref_number}). Obrigado!',
            ],
            'payment_reminder' => [
                'Payment Reminder, from {business_name}',
                'Dear {contact_name}, This is to remind you that you have pending payment of {due_amount}. Kindly pay it as soon as possible. {business_logo}',
                'Dear {contact_name}, You have pending payment of {due_amount}. Kindly pay it as soon as possible. {business_name}',
                'Lembrete de pagamento — {business_name}',
                '<p>Olá {contact_name},</p><p>Consta em aberto o valor de <strong>{due_amount}</strong> do documento {invoice_number}, com vencimento em {due_date}.</p><p>Total em aberto na sua conta: {cumulative_due_amount}.</p><p>Se já pagou, desconsidere e nos avise.</p><p>{business_name}</p>',
                '{contact_name}, consta em aberto {due_amount} (venc. {due_date}). {business_name}',
                'Olá {contact_name}! Consta em aberto {due_amount} do documento {invoice_number}, venc. {due_date}. Se já pagou, nos avise.',
            ],
            'new_booking' => [
                'Booking Confirmed - {business_name}',
                'Dear {contact_name}, Your booking is confirmed Date: {start_time} to {end_time} Table: {table} Location: {location} {business_logo}',
                'Dear {contact_name}, Your booking is confirmed. Date: {start_time} to {end_time}, Table: {table}, Location: {location}',
                'Reserva confirmada — {business_name}',
                '<p>Olá {contact_name},</p><p>Sua reserva está confirmada.</p><ul><li>Quando: {start_time} até {end_time}</li><li>Onde: {location_name}</li><li>Atendimento: {service_staff}</li></ul><p>{business_name}</p>',
                '{contact_name}, reserva confirmada: {start_time} até {end_time} em {location_name}.',
                'Olá {contact_name}! Reserva confirmada: {start_time} até {end_time} em {location_name}.',
            ],
            'new_quotation' => [
                'Thank you from {business_name}',
                'Dear {contact_name}, Your quotation number is {invoice_number} Total amount: {total_amount} Thank you for shopping with us. {business_logo}',
                'Dear {contact_name}, Thank you for shopping with us. {business_name}',
                'Seu orçamento — {business_name}',
                '<p>Olá {contact_name},</p><p>Seu orçamento <strong>{invoice_number}</strong> está pronto. Total: {total_amount}</p><p>Ver o orçamento: <a href="{quote_url}">{quote_url}</a></p><p>Qualquer ajuste, é só responder.</p><p>{business_name}</p>',
                '{contact_name}, seu orçamento {invoice_number} está pronto. Total {total_amount}. {business_name}',
                'Olá {contact_name}! Orçamento {invoice_number} pronto — total {total_amount}. Ver: {quote_url}',
            ],
            'new_order' => [
                'New Order, from {business_name}',
                'Dear {contact_name}, We have a new order with reference number {order_ref_number}. Kindly process the products as soon as possible. {business_name} {business_logo}',
                'Dear {contact_name}, We have a new order with reference number {order_ref_number}. Kindly process the products as soon as possible. {business_name}',
                'Novo pedido — {business_name}',
                '<p>Olá {contact_name},</p><p>Temos um novo pedido, referência <strong>{order_ref_number}</strong>, no valor de {total_amount}.</p><p>Por favor, confirme o prazo de entrega.</p><p>{business_name}</p>',
                'Novo pedido {order_ref_number} — {total_amount}. Confirme o prazo. {business_name}',
                'Olá {contact_name}! Novo pedido {order_ref_number} no valor de {total_amount}. Consegue confirmar o prazo?',
            ],
            'payment_paid' => [
                'Payment Paid, from {business_name}',
                'Dear {contact_name}, We have paid amount {paid_amount} again invoice number {order_ref_number}. Kindly note it down. {business_name} {business_logo}',
                'We have paid amount {paid_amount} again invoice number {order_ref_number}. Kindly note it down. {business_name}',
                'Pagamento efetuado — {business_name}',
                '<p>Olá {contact_name},</p><p>Pagamos <strong>{paid_amount}</strong> referente ao pedido {order_ref_number} (ref. {payment_ref_number}).</p><p>{business_name}</p>',
                'Pagamos {paid_amount} do pedido {order_ref_number}. {business_name}',
                'Olá {contact_name}! Pagamos {paid_amount} referente ao pedido {order_ref_number}.',
            ],
            'items_received' => [
                'Items received, from {business_name}',
                'Dear {contact_name}, We have received all items from invoice reference number {order_ref_number}. Thank you for processing it. {business_name} {business_logo}',
                'We have received all items from invoice reference number {order_ref_number}. Thank you for processing it. {business_name}',
                'Itens recebidos — {business_name}',
                '<p>Olá {contact_name},</p><p>Recebemos todos os itens do pedido {order_ref_number}. Obrigado pelo atendimento.</p><p>{business_name}</p>',
                'Recebemos todos os itens do pedido {order_ref_number}. Obrigado! {business_name}',
                'Olá {contact_name}! Recebemos todos os itens do pedido {order_ref_number}. Obrigado!',
            ],
            'items_pending' => [
                'Items Pending, from {business_name}',
                'Dear {contact_name}, This is to remind you that we have not yet received some items from invoice reference number {order_ref_number}. Please process it as soon as possible. {business_name} {business_logo}',
                'This is to remind you that we have not yet received some items from invoice reference number {order_ref_number} . Please process it as soon as possible.{business_name}',
                'Itens pendentes — {business_name}',
                '<p>Olá {contact_name},</p><p>Ainda faltam itens do pedido {order_ref_number}. Pode nos dar uma previsão?</p><p>{business_name}</p>',
                'Faltam itens do pedido {order_ref_number}. Pode dar uma previsão? {business_name}',
                'Olá {contact_name}! Ainda faltam itens do pedido {order_ref_number}. Consegue nos dar uma previsão?',
            ],
            'purchase_order' => [
                'New Purchase Order, from {business_name}',
                'Dear {contact_name}, We have a new purchase order with reference number {order_ref_number}. The respective invoice is attached here with. {business_logo}',
                'We have a new purchase order with reference number {order_ref_number}. {business_name}',
                'Nova ordem de compra — {business_name}',
                '<p>Olá {contact_name},</p><p>Segue a ordem de compra <strong>{order_ref_number}</strong> em anexo.</p><p>{business_name}</p>',
                'Nova ordem de compra {order_ref_number}. {business_name}',
                'Olá {contact_name}! Segue a ordem de compra {order_ref_number}.',
            ],
            // send_ledger nunca foi semeado pelo defaultNotificationTemplates() — entra só se existir vazio.
            'send_ledger' => [
                null, null, null,
                'Seu extrato — {business_name}',
                '<p>Olá {contact_name},</p><p>Segue o seu extrato. Saldo em aberto: <strong>{balance_due}</strong>.</p><p>Qualquer dúvida, é só responder este e-mail.</p><p>{business_name}</p>',
                '', '',
            ],
        ];
    }

    /** Normaliza pra comparar seed × banco: sem HTML, espaços colapsados, minúsculo. */
    private function norm(?string $s): string
    {
        $s = strip_tags((string) $s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
    }

    public function up(): void
    {
        $mapa = $this->mapa();
        $traduzidos = 0;
        $preservados = 0;

        DB::table('notification_templates')->orderBy('id')->chunkById(200, function ($linhas) use ($mapa, &$traduzidos, &$preservados) {
            foreach ($linhas as $linha) {
                if (! isset($mapa[$linha->template_for])) {
                    continue;
                }
                [$assuntoEn, $corpoEn, $smsEn, $assuntoPt, $corpoPt, $smsPt, $waPt] = $mapa[$linha->template_for];

                $dados = [];

                // Corpo: só troca se ainda for o seed inglês (ou estiver vazio).
                if ($this->norm($linha->email_body) === '' || ($corpoEn !== null && $this->norm($linha->email_body) === $this->norm($corpoEn))) {
                    $dados['email_body'] = $corpoPt;
                }
                if ($this->norm($linha->subject) === '' || ($assuntoEn !== null && $this->norm($linha->subject) === $this->norm($assuntoEn))) {
                    $dados['subject'] = $assuntoPt;
                }
                if ($smsPt !== '' && ($this->norm($linha->sms_body) === '' || ($smsEn !== null && $this->norm($linha->sms_body) === $this->norm($smsEn)))) {
                    $dados['sms_body'] = $smsPt;
                }
                // WhatsApp: o seed nunca preencheu — só completa o que está vazio.
                if ($waPt !== '' && $this->norm($linha->whatsapp_text) === '') {
                    $dados['whatsapp_text'] = $waPt;
                }

                if ($dados === []) {
                    $preservados++;
                    continue;
                }

                DB::table('notification_templates')->where('id', $linha->id)->update($dados);
                $traduzidos++;
            }
        });

        // Rastro pro handoff: quantos modelos foram traduzidos e quantos ficaram como o negócio deixou.
        if (app()->runningInConsole()) {
            echo "notification_templates: {$traduzidos} traduzido(s), {$preservados} preservado(s) (editados pelo negócio).\n";
        }
    }

    public function down(): void
    {
        $mapa = $this->mapa();

        DB::table('notification_templates')->orderBy('id')->chunkById(200, function ($linhas) use ($mapa) {
            foreach ($linhas as $linha) {
                if (! isset($mapa[$linha->template_for])) {
                    continue;
                }
                [$assuntoEn, $corpoEn, $smsEn, $assuntoPt, $corpoPt, $smsPt] = $mapa[$linha->template_for];
                if ($corpoEn === null) {
                    continue; // send_ledger não tem original inglês
                }

                $dados = [];
                if ($this->norm($linha->email_body) === $this->norm($corpoPt)) {
                    $dados['email_body'] = $corpoEn;
                }
                if ($this->norm($linha->subject) === $this->norm($assuntoPt)) {
                    $dados['subject'] = $assuntoEn;
                }
                if ($smsPt !== '' && $this->norm($linha->sms_body) === $this->norm($smsPt)) {
                    $dados['sms_body'] = $smsEn;
                }

                if ($dados !== []) {
                    DB::table('notification_templates')->where('id', $linha->id)->update($dados);
                }
            }
        });
    }
};
