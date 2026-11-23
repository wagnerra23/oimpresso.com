<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Listeners\EnviarDanfeNFCePorEmail;
use Modules\NfeBrasil\Listeners\EnviarDanfePorEmail;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ações em lote da barra do Cockpit Fiscal (UC-FCKP-21..23).
 *
 * Antes deste controller os três botões da barra apareciam ao marcar notas e não faziam nada
 * (débito "Os botões da barra de lote não fazem nada", Cockpit.casos.md). As três ações
 * reusam as portas que já existiam para UMA nota: `DanfeService::obterXmlContents`,
 * `DanfeService::lerOuGerar` e os listeners de e-mail do `reenviarEmail`.
 *
 * Tier 0: toda leitura é `where('business_id', <sessão>)`. Id de outra empresa não falha —
 * simplesmente não entra no lote, e a contagem de "fora do lote" diz isso a quem pediu.
 * Só nota AUTORIZADA entra: é o mesmo corte do `danfePdf`/`reenviarEmail` de uma nota.
 */
class NfeLoteController extends Controller
{
    /** Teto do lote — a página do cockpit serve 50 linhas; 200 cobre "selecionar tudo" com folga. */
    public const MAX_IDS = 200;

    public function __construct(private readonly DanfeService $danfeService)
    {
    }

    /** POST /nfe-brasil/emissoes/lote/xml — ZIP com o XML autorizado de cada nota. */
    public function xml(Request $request): Response
    {
        [$emissoes, $fora] = $this->carregar($request);
        if ($emissoes->isEmpty()) {
            return $this->nadaAFazer($fora);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'nfe-xml-');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Não foi possível montar o ZIP.');
        }

        $semArquivo = 0;
        foreach ($emissoes as $e) {
            try {
                $zip->addFromString($this->nomeBase($e).'.xml', $this->danfeService->obterXmlContents($e));
            } catch (\Throwable $t) {
                $semArquivo++;
                Log::warning('[NfeLote] XML ausente', ['emissao_id' => $e->id, 'erro' => $t->getMessage()]);
            }
        }
        $zip->close();

        if ($semArquivo === $emissoes->count()) {
            @unlink($tmp);

            return $this->nadaAFazer($fora + $semArquivo);
        }

        return $this->download($tmp, 'xmls-'.now()->format('Ymd-His').'.zip', 'application/zip', $fora + $semArquivo);
    }

    /** POST /nfe-brasil/emissoes/lote/danfe — um PDF só, com as DANFEs na ordem do lote. */
    public function danfe(Request $request): Response
    {
        [$emissoes, $fora] = $this->carregar($request);
        if ($emissoes->isEmpty()) {
            return $this->nadaAFazer($fora);
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
        $paginas = 0;
        $semArquivo = 0;
        foreach ($emissoes as $e) {
            $pdf = null;
            try {
                $pdf = $this->danfeService->lerOuGerar($e);
            } catch (\Throwable $t) {
                Log::warning('[NfeLote] DANFE indisponível', ['emissao_id' => $e->id, 'erro' => $t->getMessage()]);
            }
            if (! is_string($pdf) || $pdf === '') {
                $semArquivo++;

                continue;
            }

            $origem = tempnam(sys_get_temp_dir(), 'danfe-');
            file_put_contents($origem, $pdf);
            try {
                $total = $mpdf->setSourceFile($origem);
                for ($p = 1; $p <= $total; $p++) {
                    $tpl = $mpdf->importPage($p);
                    $tam = $mpdf->getTemplateSize($tpl);
                    $mpdf->AddPageByArray([
                        'orientation' => $tam['width'] > $tam['height'] ? 'L' : 'P',
                        'sheet-size'  => [$tam['width'], $tam['height']],
                    ]);
                    $mpdf->useTemplate($tpl);
                    $paginas++;
                }
            } catch (\Throwable $t) {
                $semArquivo++;
                Log::warning('[NfeLote] DANFE ilegível', ['emissao_id' => $e->id, 'erro' => $t->getMessage()]);
            } finally {
                @unlink($origem);
            }
        }

        if ($paginas === 0) {
            return $this->nadaAFazer($fora + $semArquivo);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'danfes-');
        $mpdf->Output($tmp, \Mpdf\Output\Destination::FILE);

        return $this->download($tmp, 'danfes-'.now()->format('Ymd-His').'.pdf', 'application/pdf', $fora + $semArquivo);
    }

    /** POST /nfe-brasil/emissoes/lote/reenviar-email — dispara o e-mail de cada nota ao próprio destinatário. */
    public function reenviarEmail(Request $request): JsonResponse
    {
        [$emissoes, $fora] = $this->carregar($request);

        $enviadas = 0;
        $falhas = 0;
        foreach ($emissoes as $e) {
            try {
                if ((int) $e->modelo === 65) {
                    app(EnviarDanfeNFCePorEmail::class)->handle(new NFCeAutorizada($e));
                } else {
                    app(EnviarDanfePorEmail::class)->handle(new NFeAutorizada($e));
                }
                $enviadas++;
            } catch (\Throwable $t) {
                $falhas++;
                Log::error('[NfeLote] reenvio falhou', ['emissao_id' => $e->id, 'erro' => $t->getMessage()]);
            }
        }

        return response()->json([
            'enviadas' => $enviadas,
            'falhas'   => $falhas,
            'fora'     => $fora,
        ]);
    }

    /**
     * Lê os ids do lote e devolve [emissões autorizadas da empresa, quantos ids ficaram fora].
     *
     * @return array{0: Collection<int, NfeEmissao>, 1: int}
     */
    private function carregar(Request $request): array
    {
        $dados = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $businessId = (int) $request->session()->get('business.id', 0);
        abort_if($businessId === 0, 400);

        $ids = array_values(array_unique(array_map('intval', $dados['ids'])));
        $emissoes = NfeEmissao::where('business_id', $businessId)
            ->whereIn('id', $ids)
            ->where('status', 'autorizada')
            ->orderBy('id')
            ->get();

        return [$emissoes, count($ids) - $emissoes->count()];
    }

    private function nomeBase(NfeEmissao $e): string
    {
        return $e->chave_44 ?: sprintf('nota-%s-%s', $e->serie ?? '1', $e->numero ?? $e->id);
    }

    private function nadaAFazer(int $fora): JsonResponse
    {
        return response()->json([
            'error'   => 'lote_vazio',
            'message' => 'Nenhuma das notas marcadas é uma NF-e/NFC-e autorizada com arquivo disponível.',
            'fora'    => $fora,
        ], 422);
    }

    private function download(string $caminho, string $nome, string $tipo, int $fora): BinaryFileResponse
    {
        return response()
            ->download($caminho, $nome, [
                'Content-Type'    => $tipo,
                'Cache-Control'   => 'private, no-store',
                'X-Lote-Fora'     => (string) $fora,
            ])
            ->deleteFileAfterSend(true);
    }
}
