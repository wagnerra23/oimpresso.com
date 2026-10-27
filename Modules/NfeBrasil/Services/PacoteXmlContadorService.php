<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\NfeBrasil\Mail\PacoteXmlContadorMail;
use Modules\NfeBrasil\Models\NfeEmissao;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * US-NFE-073 · pacote mensal de XML para o contador (R-NFE-038).
 *
 * Junta num ZIP os XML das notas AUTORIZADAS e CANCELADAS da competência (a cancelada continua
 * escriturada — CONFAZ SINIEF 07/2005), lidos pela mesma porta do DANFE
 * (`DanfeService::obterXmlContents`). Nota cujo XML não é achado entra em `sem_xml` e no corpo do
 * e-mail — nunca some em silêncio.
 *
 * Multi-tenant Tier 0 (ADR 0093): sem session(); tudo filtra por `$businessId` explícito.
 */
class PacoteXmlContadorService
{
    public function __construct(private readonly DanfeService $danfe) {}

    /**
     * @return array{zip: string|null, qtd: int, sem_xml: list<int>, bytes: int}
     */
    public function montar(int $businessId, string $competencia): array
    {
        [$de, $ate] = $this->janela($competencia);

        $emissoes = NfeEmissao::withoutGlobalScopes() // SUPERADMIN: roda em comando sem sessão; escopo no where.
            ->where('business_id', $businessId)
            ->whereIn('status', ['autorizada', 'cancelada'])
            ->whereBetween('emitido_em', [$de, $ate])
            ->orderBy('modelo')->orderBy('serie')->orderBy('numero')
            ->get();

        if ($emissoes->isEmpty()) {
            return ['zip' => null, 'qtd' => 0, 'sem_xml' => [], 'bytes' => 0];
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'xmlcont') ?: throw new RuntimeException('Sem pasta temporária para o ZIP.');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não abriu o ZIP do pacote do contador.');
        }

        $qtd = 0;
        $semXml = [];
        foreach ($emissoes as $e) {
            try {
                $xml = $this->danfe->obterXmlContents($e);
            } catch (Throwable) {
                $semXml[] = (int) $e->id;
                continue;
            }
            $nome = sprintf('%s/%s-%s-%s%s.xml',
                (int) $e->modelo === 65 ? 'nfce' : 'nfe',
                $e->chave_44 ?: ('serie' . $e->serie),
                $e->serie,
                $e->numero,
                $e->status === 'cancelada' ? '-cancelada' : '',
            );
            $zip->addFromString($nome, $xml);
            $qtd++;
        }
        $zip->close();

        if ($qtd === 0) {
            @unlink($zipPath);

            return ['zip' => null, 'qtd' => 0, 'sem_xml' => $semXml, 'bytes' => 0];
        }

        return ['zip' => $zipPath, 'qtd' => $qtd, 'sem_xml' => $semXml, 'bytes' => (int) filesize($zipPath)];
    }

    /**
     * Envia o pacote ao contador cadastrado e registra. Já enviado na competência = não reenvia.
     *
     * @return string `enviado` · `ja_enviado` · `sem_contador` · `sem_notas` · `falha`
     */
    public function enviar(int $businessId, string $competencia, bool $dryRun = false): string
    {
        $contador = DB::table('nfe_contadores')->where('business_id', $businessId)->first();
        if ($contador === null || trim((string) $contador->email) === '') {
            return 'sem_contador';
        }

        $jaEnviado = DB::table('nfe_envios_contador')
            ->where('business_id', $businessId)
            ->where('competencia', $competencia)
            ->where('status', 'enviado')
            ->exists();
        if ($jaEnviado) {
            return 'ja_enviado';
        }

        $pacote = $this->montar($businessId, $competencia);
        if ($pacote['zip'] === null && $pacote['sem_xml'] === []) {
            return 'sem_notas';
        }
        if ($dryRun) {
            if ($pacote['zip'] !== null) {
                @unlink($pacote['zip']);
            }

            return 'enviado';
        }

        $empresa = (string) (DB::table('business')->where('id', $businessId)->value('name') ?? "Empresa {$businessId}");
        $status = 'enviado';
        $erro = null;
        try {
            Mail::to((string) $contador->email)->send(new PacoteXmlContadorMail(
                empresa: $empresa,
                competencia: $competencia,
                qtd: $pacote['qtd'],
                semXml: count($pacote['sem_xml']),
                zipPath: $pacote['zip'],
            ));
        } catch (Throwable $t) {
            $status = 'falha';
            $erro = mb_substr($t->getMessage(), 0, 500);
        } finally {
            if ($pacote['zip'] !== null) {
                @unlink($pacote['zip']);
            }
        }

        DB::table('nfe_envios_contador')->updateOrInsert(
            ['business_id' => $businessId, 'competencia' => $competencia],
            [
                'email' => (string) $contador->email, 'qtd_xml' => $pacote['qtd'],
                'qtd_sem_xml' => count($pacote['sem_xml']), 'bytes' => $pacote['bytes'],
                'status' => $status, 'erro' => $erro,
                'enviado_em' => $status === 'enviado' ? now() : null,
                'updated_at' => now(), 'created_at' => now(),
            ],
        );

        return $status;
    }

    /** Competência AAAA-MM → [primeiro instante, último instante] do mês. */
    private function janela(string $competencia): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $competencia)) {
            throw new \InvalidArgumentException("Competência inválida: {$competencia} (use AAAA-MM).");
        }
        $ini = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $competencia . '-01 00:00:00');

        return [$ini, $ini->endOfMonth()];
    }
}
