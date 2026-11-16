<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Superadmin\Notifications\NewBusinessNotification;
use Modules\Superadmin\Notifications\NewBusinessWelcomNotification;
use Modules\Superadmin\Notifications\NewSubscriptionNotification;
use Modules\Superadmin\Notifications\PasswordUpdateNotification;
use Modules\Superadmin\Notifications\SendSubscriptionExpiryAlert;
use Modules\Superadmin\Notifications\SubscriptionOfflinePaymentActivationConfirmation;
use Modules\Superadmin\Notifications\SuperadminCommunicator;

uses(Tests\TestCase::class);

/**
 * As notificações do Superadmin saem do request HTTP e vão pra fila própria (lote L4, 2026-11-16).
 *
 * Antes: nenhuma das 7 era ShouldQueue, e o Comunicador mandava um e-mail por dono de empresa
 * dentro da requisição. Agora cada uma vai pra fila `superadmin-mail`, com payload criptografado
 * (a senha nova e o texto dos e-mails não podem ficar legíveis na tabela `jobs`).
 *
 * Não toca banco: o notifiable é anônimo (`Notification::route`) e o Queue é fake. O que se
 * mede é o JOB que o Laravel empurra — fila, classe e criptografia —, não a classe declarada.
 */
function notificacoesDoSuperadmin(): array
{
    $assinatura = (object) ['end_date' => now()->addDays(5)->toDateString()];

    return [
        'NewBusinessNotification' => new NewBusinessNotification((object) []),
        'NewBusinessWelcomNotification' => new NewBusinessWelcomNotification(['subject' => 's', 'body' => 'b']),
        'NewSubscriptionNotification' => new NewSubscriptionNotification((object) []),
        'PasswordUpdateNotification' => new PasswordUpdateNotification('senha-de-teste'),
        'SendSubscriptionExpiryAlert' => new SendSubscriptionExpiryAlert($assinatura),
        'SubscriptionOfflinePaymentActivationConfirmation' => new SubscriptionOfflinePaymentActivationConfirmation((object) [], (object) []),
        'SuperadminCommunicator' => new SuperadminCommunicator(['subject' => 's', 'message' => 'm']),
    ];
}

test('cada notificação do Superadmin é empurrada para a fila superadmin-mail, criptografada', function (string $nome) {
    Queue::fake();

    $notificacao = notificacoesDoSuperadmin()[$nome];
    Notification::route('mail', 'destino@exemplo.test')->notify($notificacao);

    Queue::assertPushedOn('superadmin-mail', SendQueuedNotifications::class, function ($job) use ($notificacao) {
        // O NotificationSender CLONA a notificação por canal: compara a classe, não a instância.
        return $job->notification instanceof $notificacao && $job->shouldBeEncrypted === true;
    });
    Queue::assertNotPushedOn('default', SendQueuedNotifications::class);
})->with([
    // Lista literal, não array_keys(notificacoesDoSuperadmin()): o dataset é avaliado antes de o
    // app subir, e o construtor do alerta de expiração chama Carbon::now().
    'NewBusinessNotification',
    'NewBusinessWelcomNotification',
    'NewSubscriptionNotification',
    'PasswordUpdateNotification',
    'SendSubscriptionExpiryAlert',
    'SubscriptionOfflinePaymentActivationConfirmation',
    'SuperadminCommunicator',
]);

test('o Comunicador vira um job por destinatário, não um envio dentro do request', function () {
    Queue::fake();

    Notification::send(
        [
            Notification::route('mail', 'dono-a@exemplo.test'),
            Notification::route('mail', 'dono-b@exemplo.test'),
        ],
        new SuperadminCommunicator(['subject' => 's', 'message' => 'm'])
    );

    // 2 destinatários × 2 canais (mail + database) = 4 jobs, todos na fila do Superadmin.
    Queue::assertPushedOn('superadmin-mail', SendQueuedNotifications::class);
    Queue::assertPushed(SendQueuedNotifications::class, 4);
});

/**
 * Trava estrutural: notificação nova no diretório nasce na fila. Sem isto, a 8ª classe
 * entraria síncrona e ninguém veria.
 */
test('toda classe em Modules/Superadmin/Notifications é ShouldQueue e ShouldBeEncrypted', function () {
    $dir = base_path('Modules/Superadmin/Notifications');
    $classes = [];
    foreach (glob($dir.'/*.php') as $arquivo) {
        $classe = 'Modules\\Superadmin\\Notifications\\'.basename($arquivo, '.php');
        if (class_exists($classe)) {
            $classes[] = $classe;
        }
    }

    // Controle positivo: se o glob voltar vazio, o laço abaixo passaria sem medir nada.
    expect(count($classes))->toBeGreaterThanOrEqual(7);

    foreach ($classes as $classe) {
        $this->assertTrue(is_subclass_of($classe, ShouldQueue::class), "{$classe} não implementa ShouldQueue");
        $this->assertTrue(is_subclass_of($classe, ShouldBeEncrypted::class), "{$classe} não implementa ShouldBeEncrypted");
    }
});

/**
 * CONTROLE NEGATIVO — prova que o assert de fila sabe reprovar: uma notificação síncrona
 * qualquer não é empurrada pra fila nenhuma.
 */
test('CONTROLE NEGATIVO — notificação sem ShouldQueue não empurra job', function () {
    Queue::fake();

    $sincrona = new class extends \Illuminate\Notifications\Notification {
        public function via($notifiable): array
        {
            return [];
        }
    };
    Notification::route('mail', 'destino@exemplo.test')->notify($sincrona);

    Queue::assertNothingPushed();
});
