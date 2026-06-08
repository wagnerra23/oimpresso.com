<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

/**
 * WhatsmeowState — estados canônicos do user lifecycle no daemon WuzAPI.
 *
 * Single source of truth do estado é o daemon (queries via /admin/users +
 * /session/status). Esta enum só representa o que o Reconciler observou no
 * último reconcile() — não persiste em DB. Estado durável continua em
 * `channels.status` + `channels.channel_health` ENUMs (ADR 0135).
 *
 * Transições documentadas em [ADR 0206 §2 State Machine](../../../memory/decisions/0206-state-machine-whatsmeow-reconciliacao.md).
 *
 * @see Modules/Whatsapp/Services/WhatsmeowReconciler.php
 * @see memory/decisions/0206-state-machine-whatsmeow-reconciliacao.md
 */
enum WhatsmeowState: string
{
    /**
     * User nunca foi criado no daemon. Próxima ação: POST /admin/users +
     * POST /session/connect + GET /session/qr.
     */
    case NOT_EXISTS = 'not_exists';

    /**
     * User existe no daemon mas socket não conectou ainda (Connected=false).
     * Próxima ação: POST /session/connect + GET /session/qr.
     */
    case PROVISION_PENDING = 'provision_pending';

    /**
     * Daemon conectado, QR gerado aguardando scan WhatsApp celular.
     * Connected=true, LoggedIn=false, QR disponível em /session/qr.
     */
    case QR_PENDING = 'qr_pending';

    /**
     * Sessão pareada com sucesso. Connected=true, LoggedIn=true, JID
     * atribuído. Pronto pra enviar/receber mensagens.
     */
    case PAIRED = 'paired';

    /**
     * Pareou antes (JID existiu), mas perdeu sessão.
     * Connected=true, LoggedIn=false MAS sem QR (precisa logout + reconnect).
     */
    case LOGGED_OUT = 'logged_out';

    /**
     * Número banido pela Meta. Daemon retorna 403/forbidden recorrente
     * ou status="banned". Fallback Meta Cloud recomendado.
     */
    case BANNED = 'banned';

    /**
     * Daemon CT 100 está down ou inacessível.
     * Detectado via timeout / 5xx em /health.
     * Circuit breaker pode estar aberto.
     */
    case DAEMON_UNREACHABLE = 'daemon_unreachable';

    /**
     * Erro inesperado (config faltando, exception não tratada).
     * Mensagem específica vai em WhatsmeowReconcileResult::message.
     */
    case ERROR = 'error';

    /**
     * Mensagem PT-BR pronta pra UI mostrar pro Wagner/atendente.
     */
    public function userMessage(): string
    {
        return match ($this) {
            self::NOT_EXISTS => 'Canal nunca foi conectado — gerando QR pela primeira vez.',
            self::PROVISION_PENDING => 'Inicializando sessão no daemon — aguarde.',
            self::QR_PENDING => 'Escaneie o QR no WhatsApp → Dispositivos vinculados.',
            self::PAIRED => 'Canal pareado e ativo.',
            self::LOGGED_OUT => 'Sessão expirou no celular. Re-conecte gerando novo QR.',
            self::BANNED => 'Número banido pela Meta. Use Meta Cloud (oficial) ou outro número.',
            self::DAEMON_UNREACHABLE => 'Daemon whatsmeow indisponível. Tente em alguns minutos.',
            self::ERROR => 'Erro interno. Logs detalham (storage/logs/laravel.log).',
        };
    }

    /**
     * Estado terminal sucesso — sem ação pendente.
     */
    public function isPaired(): bool
    {
        return $this === self::PAIRED;
    }

    /**
     * Estado de erro — UI deve mostrar como falha.
     */
    public function isError(): bool
    {
        return in_array($this, [self::ERROR, self::DAEMON_UNREACHABLE, self::BANNED], true);
    }

    /**
     * Estado intermediário — UI deve mostrar QR / loading e polling continua.
     */
    public function isPending(): bool
    {
        return in_array($this, [self::NOT_EXISTS, self::PROVISION_PENDING, self::QR_PENDING, self::LOGGED_OUT], true);
    }
}
