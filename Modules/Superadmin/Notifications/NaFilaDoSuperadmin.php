<?php

namespace Modules\Superadmin\Notifications;

/**
 * Põe a notificação na fila própria do Superadmin.
 *
 * POR QUE FILA PRÓPRIA, e não `default`: em produção quem drena `default` é o worker de
 * backlog de `app/Console/Kernel.php`, que fica atrás de `queue.backlog_worker_enabled`
 * (default false). Um e-mail mandado pra `default` ficaria parado na tabela `jobs` sem
 * ninguém perceber — o mesmo motivo das filas `backups`, `attendance-import` e
 * `sales-import`. O worker desta fila é agendado em
 * `SuperadminServiceProvider::registerScheduleCommands()`.
 *
 * Toda classe que usa este trait também implementa `ShouldBeEncrypted`: o corpo do e-mail
 * (texto do Comunicador, senha nova, dados do dono da empresa) não pode ficar legível na
 * tabela `jobs` nem na `failed_jobs`.
 */
trait NaFilaDoSuperadmin
{
    public const FILA = 'superadmin-mail';

    protected function irParaFilaDoSuperadmin(): void
    {
        // PHP 8.4 + Laravel 13: o trait Queueable declara `public $queue;`, que não pode
        // ser redeclarada com default — por isso onQueue() no construtor (ver RunBackupJob).
        $this->onQueue(self::FILA);
    }
}
