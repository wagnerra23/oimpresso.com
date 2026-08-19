<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Retrives notification template from database
     *
     * @param  int  $business_id
     * @param  string  $template_for
     * @return array $template
     */
    public static function getTemplate($business_id, $template_for)
    {
        $notif_template = NotificationTemplate::where('business_id', $business_id)
                                                        ->where('template_for', $template_for)
                                                        ->first();
        $template = [
            'subject' => ! empty($notif_template->subject) ? $notif_template->subject : '',
            'sms_body' => ! empty($notif_template->sms_body) ? $notif_template->sms_body : '',
            'whatsapp_text' => ! empty($notif_template->whatsapp_text) ? $notif_template->whatsapp_text : '',
            'email_body' => ! empty($notif_template->email_body) ? $notif_template->email_body
                             : '',
            'template_for' => $template_for,
            'cc' => ! empty($notif_template->cc) ? $notif_template->cc : '',
            'bcc' => ! empty($notif_template->bcc) ? $notif_template->bcc : '',
            'auto_send' => ! empty($notif_template->auto_send) ? 1 : 0,
            'auto_send_sms' => ! empty($notif_template->auto_send_sms) ? 1 : 0,
            'auto_send_wa_notif' => ! empty($notif_template->auto_send_wa_notif)
             ? 1 : 0,
        ];

        return $template;
    }

    public static function customerNotifications()
    {
        return [
            'new_sale' => [
                'name' => __('lang_v1.new_sale'),
                'extra_tags' => [
                    ['{business_name}', '{business_logo}'],
                    ['{invoice_number}', '{invoice_url}', '{total_amount}', '{paid_amount}', '{due_amount}', '{cumulative_due_amount}', '{due_date}'],
                    ['{location_name}', '{location_address}', '{location_email}', '{location_phone}', '{location_custom_field_1}', '{location_custom_field_2}', '{location_custom_field_3}', '{location_custom_field_4}'],
                    ['{contact_name}', '{contact_custom_field_1}', '{contact_custom_field_2}', '{contact_custom_field_3}', '{contact_custom_field_4}', '{contact_custom_field_5}', '{contact_custom_field_6}', '{contact_custom_field_7}', '{contact_custom_field_8}', '{contact_custom_field_9}', '{contact_custom_field_10}'],
                    ['{sell_custom_field_1}', '{sell_custom_field_2}', '{sell_custom_field_3}', '{sell_custom_field_4}'],
                    ['{shipping_custom_field_1}', '{shipping_custom_field_2}', '{shipping_custom_field_3}', '{shipping_custom_field_4}', '{shipping_custom_field_5}'],
                ],
            ],
            'payment_received' => [
                'name' => __('lang_v1.payment_received'),
                'extra_tags' => [
                    ['{business_name}', '{business_logo}'],
                    ['{invoice_number}', '{payment_ref_number}', '{received_amount}'],
                    ['{contact_name}', '{contact_custom_field_1}', '{contact_custom_field_2}', '{contact_custom_field_3}', '{contact_custom_field_4}', '{contact_custom_field_5}', '{contact_custom_field_6}', '{contact_custom_field_7}', '{contact_custom_field_8}', '{contact_custom_field_9}', '{contact_custom_field_10}'],
                ],
            ],
            'payment_reminder' => [
                'name' => __('lang_v1.payment_reminder'),
                'extra_tags' => [
                    ['{business_name}', '{business_logo}'],
                    ['{invoice_number}', '{due_amount}', '{cumulative_due_amount}', '{due_date}'],
                    ['{contact_name}', '{contact_custom_field_1}', '{contact_custom_field_2}', '{contact_custom_field_3}', '{contact_custom_field_4}', '{contact_custom_field_5}', '{contact_custom_field_6}', '{contact_custom_field_7}', '{contact_custom_field_8}', '{contact_custom_field_9}', '{contact_custom_field_10}'],

                ],
            ],
            'new_booking' => [
                'name' => __('lang_v1.new_booking'),
                'extra_tags' => self::bookingNotificationTags(),
            ],
            'new_quotation' => [
                'name' => __('lang_v1.new_quotation'),
                'extra_tags' => [
                    ['{business_name}', '{business_logo}'],
                    ['{invoice_number}', '{total_amount}', '{quote_url}'],
                    ['{location_name}', '{location_address}', '{location_email}', '{location_phone}', '{location_custom_field_1}', '{location_custom_field_2}', '{location_custom_field_3}', '{location_custom_field_4}'],
                    ['{contact_name}', '{contact_custom_field_1}', '{contact_custom_field_2}', '{contact_custom_field_3}', '{contact_custom_field_4}', '{contact_custom_field_5}', '{contact_custom_field_6}', '{contact_custom_field_7}', '{contact_custom_field_8}', '{contact_custom_field_9}', '{contact_custom_field_10}'],

                ],
            ],
        ];
    }

    public static function generalNotifications()
    {
        return [
            'send_ledger' => [
                'name' => __('lang_v1.send_ledger'),
                'extra_tags' => [
                    ['{business_name}', '{business_logo}'],
                    ['{balance_due}'],
                    ['{contact_name}', '{contact_custom_field_1}', '{contact_custom_field_2}', '{contact_custom_field_3}', '{contact_custom_field_4}', '{contact_custom_field_5}', '{contact_custom_field_6}', '{contact_custom_field_7}', '{contact_custom_field_8}', '{contact_custom_field_9}', '{contact_custom_field_10}'],
                ],
            ],
        ];
    }

    public static function supplierNotifications()
    {
        return [
            'new_order' => [
                'name' => __('lang_v1.new_order'),
                'extra_tags' => [
                    ['{business_name}', '{business_logo}'],
                    ['{order_ref_number}', '{total_amount}', '{received_amount}', '{due_amount}'],
                    ['{location_name}', '{location_address}', '{location_email}', '{location_phone}', '{location_custom_field_1}', '{location_custom_field_2}', '{location_custom_field_3}', '{location_custom_field_4}'],
                    ['{purchase_custom_field_1}', '{purchase_custom_field_2}', '{purchase_custom_field_3}', '{purchase_custom_field_4}', '{contact_business_name}'],
                    ['{contact_name}', '{contact_custom_field_1}', '{contact_custom_field_2}', '{contact_custom_field_3}', '{contact_custom_field_4}', '{contact_custom_field_5}', '{contact_custom_field_6}', '{contact_custom_field_7}', '{contact_custom_field_8}', '{contact_custom_field_9}', '{contact_custom_field_10}'],
                    ['{shipping_custom_field_1}', '{shipping_custom_field_2}', '{shipping_custom_field_3}', '{shipping_custom_field_4}', '{shipping_custom_field_5}'],
                ],
            ],
            'payment_paid' => [
                'name' => __('lang_v1.payment_paid'),
                'extra_tags' => [
                    ['{business_name}', '{business_logo}'],
                    ['{order_ref_number}', '{payment_ref_number}', '{paid_amount}'],
                    ['{contact_name}', '{contact_business_name}', '{contact_custom_field_1}', '{contact_custom_field_2}', '{contact_custom_field_3}', '{contact_custom_field_4}', '{contact_custom_field_5}', '{contact_custom_field_6}', '{contact_custom_field_7}', '{contact_custom_field_8}', '{contact_custom_field_9}', '{contact_custom_field_10}'],
                ],
            ],
            'items_received' => [
                'name' => __('lang_v1.items_received'),
                'extra_tags' => [
                    ['{business_name}', '{business_logo}'],
                    ['{order_ref_number}'],
                    ['{contact_business_name}', '{contact_name}', '{contact_custom_field_1}', '{contact_custom_field_2}', '{contact_custom_field_3}', '{contact_custom_field_4}', '{contact_custom_field_5}', '{contact_custom_field_6}', '{contact_custom_field_7}', '{contact_custom_field_8}', '{contact_custom_field_9}', '{contact_custom_field_10}'],
                ],
            ],
            'items_pending' => [
                'name' => __('lang_v1.items_pending'),
                'extra_tags' => [
                    ['{business_name}', '{business_logo}'],
                    ['{order_ref_number}'],
                    ['{contact_business_name}', '{contact_name}', '{contact_custom_field_1}', '{contact_custom_field_2}', '{contact_custom_field_3}', '{contact_custom_field_4}', '{contact_custom_field_5}', '{contact_custom_field_6}', '{contact_custom_field_7}', '{contact_custom_field_8}', '{contact_custom_field_9}', '{contact_custom_field_10}'],
                ],
            ],

            'purchase_order' => [
                'name' => __('lang_v1.purchase_order'),
                'extra_tags' => [
                    ['{business_name}', '{business_logo}'],
                    ['{order_ref_number}'],
                    ['{contact_business_name}', '{contact_name}', '{contact_custom_field_1}', '{contact_custom_field_2}', '{contact_custom_field_3}', '{contact_custom_field_4}', '{contact_custom_field_5}', '{contact_custom_field_6}', '{contact_custom_field_7}', '{contact_custom_field_8}', '{contact_custom_field_9}', '{contact_custom_field_10}'],
                ],
            ],
        ];
    }

    public static function notificationTags()
    {
        return ['{contact_name}', '{invoice_number}', '{total_amount}',
            '{paid_amount}', '{due_amount}', '{business_name}', '{business_logo}', '{cumulative_due_amount}', '{due_date}', '{contact_business_name}', ];
    }

    public static function bookingNotificationTags()
    {
        return [
            ['{business_name}', '{business_logo}'],
            ['{table}', '{start_time}', '{end_time}', '{service_staff}', '{correspondent}'],
            ['{location}', '{location_name}', '{location_address}', '{location_email}', '{location_phone}', '{location_custom_field_1}', '{location_custom_field_2}', '{location_custom_field_3}', '{location_custom_field_4}'],
            ['{contact_name}', '{contact_custom_field_1}', '{contact_custom_field_2}', '{contact_custom_field_3}', '{contact_custom_field_4}', '{contact_custom_field_5}', '{contact_custom_field_6}', '{contact_custom_field_7}', '{contact_custom_field_8}', '{contact_custom_field_9}', '{contact_custom_field_10}'],
        ];
    }

    public static function defaultNotificationTemplates($business_id = null)
    {
        $notification_template_data = [
            [
                'business_id' => $business_id,
                'template_for' => 'new_sale',
                'email_body' => '<p>Olá {contact_name},</p><p>Sua venda <strong>{invoice_number}</strong> foi registrada.</p><ul><li>Total: {total_amount}</li><li>Pago: {paid_amount}</li><li>Em aberto: {due_amount}</li></ul><p>Documento: <a href="{invoice_url}">{invoice_url}</a></p><p>Obrigado por comprar com a gente.</p><p>{business_name}</p>',
                'sms_body' => '{contact_name}, sua venda {invoice_number} foi registrada. Total {total_amount}. Obrigado! {business_name}',
                'whatsapp_text' => 'Olá {contact_name}! Sua venda {invoice_number} foi registrada — total {total_amount}, em aberto {due_amount}. Documento: {invoice_url}',
                'subject' => 'Obrigado pela compra — {business_name}',
                'auto_send' => '0',
            ],

            [
                'business_id' => $business_id,
                'template_for' => 'payment_received',
                'email_body' => '<p>Olá {contact_name},</p><p>Recebemos seu pagamento de <strong>{received_amount}</strong> (ref. {payment_ref_number}) referente ao documento {invoice_number}.</p><p>Obrigado.</p><p>{business_name}</p>',
                'sms_body' => '{contact_name}, recebemos seu pagamento de {received_amount}. Obrigado! {business_name}',
                'whatsapp_text' => 'Olá {contact_name}! Recebemos seu pagamento de {received_amount} (ref. {payment_ref_number}). Obrigado!',
                'subject' => 'Pagamento recebido — {business_name}',
                'auto_send' => '0',
            ],

            [
                'business_id' => $business_id,
                'template_for' => 'payment_reminder',
                'email_body' => '<p>Olá {contact_name},</p><p>Consta em aberto o valor de <strong>{due_amount}</strong> do documento {invoice_number}, com vencimento em {due_date}.</p><p>Total em aberto na sua conta: {cumulative_due_amount}.</p><p>Se já pagou, desconsidere e nos avise.</p><p>{business_name}</p>',
                'sms_body' => '{contact_name}, consta em aberto {due_amount} (venc. {due_date}). {business_name}',
                'whatsapp_text' => 'Olá {contact_name}! Consta em aberto {due_amount} do documento {invoice_number}, venc. {due_date}. Se já pagou, nos avise.',
                'subject' => 'Lembrete de pagamento — {business_name}',
                'auto_send' => '0',
            ],

            [
                'business_id' => $business_id,
                'template_for' => 'new_booking',
                'email_body' => '<p>Olá {contact_name},</p><p>Sua reserva está confirmada.</p><ul><li>Quando: {start_time} até {end_time}</li><li>Onde: {location_name}</li><li>Atendimento: {service_staff}</li></ul><p>{business_name}</p>',
                'sms_body' => '{contact_name}, reserva confirmada: {start_time} até {end_time} em {location_name}.',
                'whatsapp_text' => 'Olá {contact_name}! Reserva confirmada: {start_time} até {end_time} em {location_name}.',
                'subject' => 'Reserva confirmada — {business_name}',
                'auto_send' => '0',
            ],

            [
                'business_id' => $business_id,
                'template_for' => 'new_order',
                'email_body' => '<p>Olá {contact_name},</p><p>Temos um novo pedido, referência <strong>{order_ref_number}</strong>, no valor de {total_amount}.</p><p>Por favor, confirme o prazo de entrega.</p><p>{business_name}</p>',
                'sms_body' => 'Novo pedido {order_ref_number} — {total_amount}. Confirme o prazo. {business_name}',
                'whatsapp_text' => 'Olá {contact_name}! Novo pedido {order_ref_number} no valor de {total_amount}. Consegue confirmar o prazo?',
                'subject' => 'Novo pedido — {business_name}',
                'auto_send' => '0',
            ],

            [
                'business_id' => $business_id,
                'template_for' => 'payment_paid',
                'email_body' => '<p>Olá {contact_name},</p><p>Pagamos <strong>{paid_amount}</strong> referente ao pedido {order_ref_number} (ref. {payment_ref_number}).</p><p>{business_name}</p>',
                'sms_body' => 'Pagamos {paid_amount} do pedido {order_ref_number}. {business_name}',
                'whatsapp_text' => 'Olá {contact_name}! Pagamos {paid_amount} referente ao pedido {order_ref_number}.',
                'subject' => 'Pagamento efetuado — {business_name}',
                'auto_send' => '0',
            ],

            [
                'business_id' => $business_id,
                'template_for' => 'items_received',
                'email_body' => '<p>Olá {contact_name},</p><p>Recebemos todos os itens do pedido {order_ref_number}. Obrigado pelo atendimento.</p><p>{business_name}</p>',
                'sms_body' => 'Recebemos todos os itens do pedido {order_ref_number}. Obrigado! {business_name}',
                'whatsapp_text' => 'Olá {contact_name}! Recebemos todos os itens do pedido {order_ref_number}. Obrigado!',
                'subject' => 'Itens recebidos — {business_name}',
                'auto_send' => '0',
            ],

            [
                'business_id' => $business_id,
                'template_for' => 'items_pending',
                'email_body' => '<p>Olá {contact_name},</p><p>Ainda faltam itens do pedido {order_ref_number}. Pode nos dar uma previsão?</p><p>{business_name}</p>',
                'sms_body' => 'Faltam itens do pedido {order_ref_number}. Pode dar uma previsão? {business_name}',
                'whatsapp_text' => 'Olá {contact_name}! Ainda faltam itens do pedido {order_ref_number}. Consegue nos dar uma previsão?',
                'subject' => 'Itens pendentes — {business_name}',
                'auto_send' => '0',
            ],

            [
                'business_id' => $business_id,
                'template_for' => 'new_quotation',
                'email_body' => '<p>Olá {contact_name},</p><p>Seu orçamento <strong>{invoice_number}</strong> está pronto. Total: {total_amount}</p><p>Ver o orçamento: <a href="{quote_url}">{quote_url}</a></p><p>Qualquer ajuste, é só responder.</p><p>{business_name}</p>',
                'sms_body' => '{contact_name}, seu orçamento {invoice_number} está pronto. Total {total_amount}. {business_name}',
                'whatsapp_text' => 'Olá {contact_name}! Orçamento {invoice_number} pronto — total {total_amount}. Ver: {quote_url}',
                'subject' => 'Seu orçamento — {business_name}',
                'auto_send' => '0',
            ],

            [
                'business_id' => $business_id,
                'template_for' => 'purchase_order',
                'email_body' => '<p>Olá {contact_name},</p><p>Segue a ordem de compra <strong>{order_ref_number}</strong> em anexo.</p><p>{business_name}</p>',
                'sms_body' => 'Nova ordem de compra {order_ref_number}. {business_name}',
                'whatsapp_text' => 'Olá {contact_name}! Segue a ordem de compra {order_ref_number}.',
                'subject' => 'Nova ordem de compra — {business_name}',
                'auto_send' => '0',
            ],
        ];

        return $notification_template_data;
    }
}
