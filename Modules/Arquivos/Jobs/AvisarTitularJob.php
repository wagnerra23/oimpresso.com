<?php

declare(strict_types=1);

namespace Modules\Arquivos\Jobs;

use App\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Arquivos\Mail\AvisoTitularMail;
use Modules\Arquivos\Services\AvisoTitularCanais;
use Modules\Arquivos\Services\AvisoTitularService;

/**
 * AvisarTitularJob — envia o aviso ao titular (LGPD Art. 18) por e-mail e/ou WhatsApp e só
 * então registra o aviso na trilha.
 *
 * Decisão: ADR 0421 (registro + janela) + ADR 0422 (canal: [W] 2026-10-01 *"e-mail e whatsapp.
 * pode ter configuração"*).
 *
 * Fluxo, por arquivo:
 *   1. re-checa a janela (`AvisoTitularService::elegivel`) — pode ter mudado desde a varredura;
 *   2. carrega o titular (`App\Contact`) **do mesmo business** — dono de outro tenant = aborta;
 *   3. para cada canal LIGADO no negócio (`AvisoTitularCanais`), respeitando o opt-in LGPD do
 *      contato (`canReceiveEmailNotification` / `canReceiveWhatsappNotification`):
 *        - e-mail: `Mail::send` síncrono — se não lançar, saiu;
 *        - WhatsApp: `SendWhatsappMessageJob::dispatchSync` — lança se o provedor recusar;
 *   4. se ao menos um canal saiu → `registrarAviso(canal = "email,whatsapp"…)`.
 *      Nenhum saiu → não registra (o arquivo segue na janela e a próxima varredura tenta de novo).
 *
 * "Saiu" = entregue ao provedor (SMTP / API do WhatsApp). Não é confirmação de leitura.
 *
 * Multi-tenant Tier 0 (ADR 0093): `$businessId` no construtor; nada aqui lê sessão.
 * Logs só com ids — nunca e-mail, telefone ou nome (LGPD).
 *
 * É `ShouldQueue` pra poder ir pra fila no futuro, mas o comando `arquivos:avisar-titulares`
 * o roda com `dispatchSync`: a fila `default` só é drenada atrás de flag (ver o docblock do
 * `SimularRetencaoJob`), e um aviso parado na fila é um aviso que não aconteceu.
 */
class AvisarTitularJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $businessId,
        public readonly int $arquivoId,
    ) {
    }

    /**
     * @return list<string> canais que saíram (vazio = nada enviado, nada registrado)
     */
    public function handle(AvisoTitularService $avisos, AvisoTitularCanais $config): array
    {
        $ctx = ['business_id' => $this->businessId, 'arquivo_id' => $this->arquivoId];

        $canaisLigados = array_keys(array_filter($config->ativos($this->businessId)));
        if ($canaisLigados === []) {
            Log::info('[arquivos.aviso_titular] nenhum canal ligado no negócio — skip', $ctx);

            return [];
        }

        $janela = $avisos->elegivel($this->businessId, $this->arquivoId);
        if ($janela === null) {
            Log::info('[arquivos.aviso_titular] fora da janela ou já avisado — skip', $ctx);

            return [];
        }

        // Tier 0: o titular TEM de ser do mesmo business do arquivo. Contact não tem global
        // scope — o filtro explícito é a defesa.
        $contato = Contact::query()
            ->where('business_id', $this->businessId)
            ->whereKey($janela['contact_id'])
            ->first();

        if ($contato === null) {
            Log::warning('[arquivos.aviso_titular] titular não encontrado no business — abort', $ctx);

            return [];
        }

        $empresa = (string) (DB::table('business')->where('id', $this->businessId)->value('name') ?? '');
        $venceEm = Carbon::parse($janela['vence_em'])->format('d/m/Y');

        $saiu = [];
        foreach ($canaisLigados as $canal) {
            $ok = match ($canal) {
                'email'    => $this->enviarEmail($contato, $empresa, $venceEm, $ctx),
                'whatsapp' => $this->enviarWhatsapp($contato, $empresa, $venceEm, $ctx),
                default    => false,
            };
            if ($ok) {
                $saiu[] = $canal;
            }
        }

        if ($saiu === []) {
            Log::info('[arquivos.aviso_titular] nenhum canal saiu — não registra', $ctx);

            return [];
        }

        $avisos->registrarAviso($this->businessId, $this->arquivoId, implode(',', $saiu));

        return $saiu;
    }

    /** @param array<string, int> $ctx */
    private function enviarEmail(Contact $contato, string $empresa, string $venceEm, array $ctx): bool
    {
        if (! $contato->canReceiveEmailNotification()) {
            Log::info('[arquivos.aviso_titular] email_consent=false — skip e-mail', $ctx);

            return false;
        }

        $email = trim((string) ($contato->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            Mail::to($email)->send(new AvisoTitularMail($empresa, (string) ($contato->name ?? ''), $venceEm));

            return true;
        } catch (\Throwable $e) {
            Log::error('[arquivos.aviso_titular] falha no e-mail', $ctx + ['erro' => $e::class]);

            return false;
        }
    }

    /** @param array<string, int> $ctx */
    private function enviarWhatsapp(Contact $contato, string $empresa, string $venceEm, array $ctx): bool
    {
        $jobWhatsapp = 'Modules\\Whatsapp\\Jobs\\SendWhatsappMessageJob';
        $modeloPhone = 'Modules\\Whatsapp\\Entities\\WhatsappBusinessPhone';
        if (! class_exists($jobWhatsapp) || ! class_exists($modeloPhone)) {
            return false;
        }

        if (! $contato->canReceiveWhatsappNotification()) {
            Log::info('[arquivos.aviso_titular] whatsapp_consent=false — skip WhatsApp', $ctx);

            return false;
        }

        $numero = trim((string) ($contato->mobile ?? ''));
        if ($numero === '') {
            return false;
        }

        // SUPERADMIN: job sem session() — global scope fora, business_id explícito (Tier 0).
        $phone = $modeloPhone::withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('handles_outbound_default', true)
            ->orderBy('id')
            ->first();

        if ($phone === null) {
            Log::info('[arquivos.aviso_titular] business sem número WhatsApp outbound_default — skip', $ctx);

            return false;
        }

        try {
            $jobWhatsapp::dispatchSync(
                $this->businessId,
                (int) $phone->id,
                $numero,
                'freeform',
                ['body' => AvisoTitularMail::texto($empresa, (string) ($contato->name ?? ''), $venceEm)],
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('[arquivos.aviso_titular] falha no WhatsApp', $ctx + ['erro' => $e::class]);

            return false;
        }
    }
}
