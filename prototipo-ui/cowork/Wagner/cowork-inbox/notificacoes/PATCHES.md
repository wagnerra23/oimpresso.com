# PATCHES.md — Modelos de notificação (`/notification-templates`)

> Aplicar na ordem. Cada patch é independente e tem seu teste no `NotificationTemplateTest.php` deste pacote.
> Origem: `cowork-inbox/NOTIFICACOES-F1-2026-08-19.md` (achados A1–A7). Autorizado por [W] 2026-08-19.

---

## P1 — Seed em PT-BR (achado A1 · decisão D1)

**Arquivo:** `app/NotificationTemplate.php` → `defaultNotificationTemplates()`

Trocar os 10 blocos em inglês pelos textos PT-BR da migration deste pacote (mesma copy, para que negócio novo e negócio migrado fiquem idênticos) e adicionar `whatsapp_text`, hoje ausente do seed.

```diff
             [
                 'business_id' => $business_id,
                 'template_for' => 'new_sale',
-                'email_body' => '<p>Dear {contact_name},</p>
-
-                    <p>Your invoice number is {invoice_number}<br />
-                    Total amount: {total_amount}<br />
-                    Paid amount: {received_amount}</p>
-
-                    <p>Thank you for shopping with us.</p>
-
-                    <p>{business_logo}</p>
-
-                    <p>&nbsp;</p>',
-                'sms_body' => 'Dear {contact_name}, Thank you for shopping with us. {business_name}',
-                'subject' => 'Thank you from {business_name}',
+                'email_body' => '<p>Olá {contact_name},</p><p>Sua venda <strong>{invoice_number}</strong> foi registrada.</p><ul><li>Total: {total_amount}</li><li>Pago: {paid_amount}</li><li>Em aberto: {due_amount}</li></ul><p>Documento: <a href="{invoice_url}">{invoice_url}</a></p><p>Obrigado por comprar com a gente.</p><p>{business_name}</p>',
+                'sms_body' => '{contact_name}, sua venda {invoice_number} foi registrada. Total {total_amount}. Obrigado! {business_name}',
+                'whatsapp_text' => 'Olá {contact_name}! Sua venda {invoice_number} foi registrada — total {total_amount}, em aberto {due_amount}. Documento: {invoice_url}',
+                'subject' => 'Obrigado pela compra — {business_name}',
                 'auto_send' => '0',
             ],
```

Idem para `payment_received · payment_reminder · new_booking · new_quotation · new_order · payment_paid · items_received · items_pending · purchase_order` — a copy canônica está no `mapa()` da migration (colunas PT). **Não** inventar variação: os dois lugares têm de bater, senão o teste 9 falha.

**Base migrada:** `2026_08_19_000000_traduzir_notification_templates_pt_br.php` (neste pacote). Ela só reescreve a linha que **ainda é o seed inglês**; modelo já editado pelo negócio fica intacto.

---

## P2 — `store()` sem whitelist de `template_for` (achado A3 — mesma família do D1 de Acessos)

`template_data[qualquer_coisa]` cria linha com `template_for` arbitrário e polui a tabela do negócio.

```diff
     public function store(Request $request)
     {
         if (! auth()->user()->can('send_notification')) {
             abort(403, 'Unauthorized action.');
         }
 
         $template_data = $request->input('template_data');
         $business_id = request()->session()->get('user.business_id');
 
+        // Só as chaves que a própria tela oferece (core + injetadas por módulo via notification_list).
+        $permitidas = array_keys(array_merge(
+            NotificationTemplate::generalNotifications(),
+            NotificationTemplate::customerNotifications(),
+            NotificationTemplate::supplierNotifications(),
+            ...array_values($this->moduleUtil->getModuleData('notification_list', ['notification_for' => 'customer'])),
+            ...array_values($this->moduleUtil->getModuleData('notification_list', ['notification_for' => 'supplier'])),
+        ));
+
         foreach ($template_data as $key => $value) {
+            if (! in_array($key, $permitidas, true)) {
+                continue; // chave desconhecida: ignora em silêncio (não é erro do operador)
+            }
             NotificationTemplate::updateOrCreate(
```

---

## P3 — Validação server-side de `cc`/`bcc` (achado A2)

Hoje só o `type="email"` do HTML protege — qualquer POST grava o que chegar.

```diff
+        $request->validate(
+            collect($template_data)->mapWithKeys(fn ($v, $k) => [
+                "template_data.$k.cc" => 'nullable|email',
+                "template_data.$k.bcc" => 'nullable|email',
+            ])->all()
+        );
```

> Se um dia CC virar lista, trocar por `nullable|string` + regra própria de e-mails separados por vírgula. Hoje o legado é **um** endereço por campo (R8 do charter) e a tela reflete isso.

---

## P4 — `email_body` sanitizado na renderização (achado A4)

O corpo é HTML livre e vai pro e-mail sem passar por sanitizador. Não sanitizar na gravação (perde conteúdo legítimo do operador) — sanitizar na **saída**, em `NotificationUtil`, com whitelist de tags:

```diff
-        $body = $notification_template['email_body'];
+        $body = clean($notification_template['email_body']); // mewebstudio/purifier já está no projeto
```

Whitelist mínima: `p br strong em b i u ul ol li a[href] table tr td th img[src|alt] h1..h4`. Bloqueia `script`, `iframe`, `on*`, `style` inline com `expression`.

---

## P5 — Confirmação de salvamento (achado A5)

`redirect()->back()` sem mensagem: o operador não sabe se gravou.

```diff
-        return redirect()->back();
+        return redirect()->back()->with('status', [
+            'success' => 1,
+            'msg' => __('lang_v1.updated_success'),
+        ]);
```

---

## P6 — Rota de teste de envio (decisão D3)

Rota nova, throttle e destino fixo no usuário logado (nunca campo livre — seria relay aberto).

```php
// routes/web.php (grupo autenticado)
Route::post('notification-templates/test', [NotificationTemplateController::class, 'test'])
    ->middleware('throttle:6,1')
    ->name('notification-templates.test');
```

```php
// NotificationTemplateController
public function test(Request $request)
{
    if (! auth()->user()->can('send_notification')) {
        abort(403, 'Unauthorized action.');
    }
    $request->validate([
        'template_for' => 'required|string',
        'canal' => 'required|in:email,sms,whatsapp',
    ]);
    // Renderiza o modelo com dados de exemplo do próprio negócio e manda pro usuário logado.
    // Nunca aceita destinatário do request.
}
```

---

## P7 — Menu no lugar certo (achado A6)

`AdminSidebarMenu.php:843-852` põe "Modelos de notificação" solto com `order(80)`. No protótipo ele vive no grupo **SISTEMA**, junto de Preferências/Backup/Módulos. Mover o item para o mesmo grupo do `/prefs` mantém uma lei só de navegação.

---

## P8 — `new_booking` fora do ar quando o módulo de agenda está desativado (achado A7)

O modelo continua listado mesmo sem o módulo. Filtrar em `customerNotifications()` (ou no controller) por `moduleUtil->isModuleInstalled('Booking')`, com o mesmo critério que o resto do sistema usa — não esconder à mão na view.
