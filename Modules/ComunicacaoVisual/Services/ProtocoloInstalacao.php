<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Services;

use App\Contracts\Arquivos\AnexosDoApp;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as Documento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Protocolo da instalação em PDF — US-COMVIS-007 · UC-PCV-25 (1c: gerar; o envio ao cliente vem depois).
 *
 * Só sai de instalação CONCLUÍDA, que por regra do ComprovanteInstalacao já tem foto antes, foto depois e
 * assinatura. Leva empresa, OS, cliente, endereço, horário agendado e de conclusão, equipe, veículo, as duas
 * fotos e a assinatura com o nome de quem assinou e a hora do aceite.
 *
 * As imagens vêm do módulo Arquivos pelo contrato do núcleo (`AnexosDoApp::conteudo`, que registra a leitura
 * na trilha) e entram no HTML como data URI — o dompdf não busca nada na rede. Foto grande é reduzida antes
 * (GD), para o PDF não pesar o tamanho das fotos do celular. Formato que o dompdf não desenha (HEIC) vira
 * uma nota no lugar da foto, sem quebrar o documento.
 *
 * Tier 0 (ADR 0093): tudo pelo business_id recebido; instalação de outra empresa → ModelNotFoundException.
 */
class ProtocoloInstalacao
{
    public const VIEW = 'comunicacao-visual::pdf.protocolo-instalacao';

    private const LADO_MAX = 1200;

    private const DESENHAVEIS = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private readonly ComprovanteInstalacao $comprovante,
        private readonly AnexosDoApp $anexos,
    ) {}

    /** @return array<string, mixed> o que a view recebe */
    public function montar(int $businessId, int $instalacaoId): array
    {
        $instalacao = $this->comprovante->cabecalho($businessId, $instalacaoId);
        if ($instalacao['status'] !== 'concluida') {
            throw new \DomainException('O protocolo sai depois de concluir a instalação.');
        }
        $ultimos = $this->comprovante->ultimos($businessId, $instalacaoId);
        $ass = $ultimos['assinatura'] ?? null;

        return [
            'empresa' => (string) (DB::table('business')->where('id', $businessId)->value('name') ?? ''),
            'instalacao' => $instalacao,
            'agendada' => self::periodo($instalacao['inicio'], $instalacao['fim']),
            'concluida' => self::dataHora($instalacao['realizada']),
            'fotos' => [
                'antes' => $this->imagem($businessId, $ultimos['foto_antes'] ?? null),
                'depois' => $this->imagem($businessId, $ultimos['foto_depois'] ?? null),
            ],
            'assinatura' => [
                'imagem' => $this->imagem($businessId, $ass),
                'assinado_por' => $ass?->assinado_por,
                'aceite_em' => self::dataHora($ass?->consentimento_em),
            ],
            'gerado_em' => Carbon::now()->format('d/m/Y H:i'),
        ];
    }

    public function pdf(int $businessId, int $instalacaoId): Documento
    {
        return Pdf::loadView(self::VIEW, $this->montar($businessId, $instalacaoId))->setPaper('a4', 'portrait');
    }

    /** @return array{src: ?string, nota: ?string, em: ?string} */
    private function imagem(int $businessId, ?object $comprovante): array
    {
        if ($comprovante === null) {
            return ['src' => null, 'nota' => 'Não registrada.', 'em' => null];
        }
        $em = self::dataHora($comprovante->created_at);
        $arquivo = $this->anexos->conteudo($businessId, (int) $comprovante->arquivo_id);
        if ($arquivo === null) {
            return ['src' => null, 'nota' => 'Arquivo indisponível.', 'em' => $em];
        }
        if (! in_array($arquivo['tipo'], self::DESENHAVEIS, true)) {
            return ['src' => null, 'nota' => 'Foto em formato que o PDF não mostra ('.$arquivo['nome'].'); está guardada no sistema.', 'em' => $em];
        }
        [$corpo, $tipo] = self::reduzir($arquivo['corpo'], $arquivo['tipo']);

        return ['src' => 'data:'.$tipo.';base64,'.base64_encode($corpo), 'nota' => null, 'em' => $em];
    }

    /** Reduz para no máximo LADO_MAX px no maior lado (JPEG); sem GD ou se falhar, devolve o original. */
    private static function reduzir(string $corpo, string $tipo): array
    {
        if (! function_exists('imagecreatefromstring') || $tipo === 'image/png') {
            return [$corpo, $tipo]; // PNG = assinatura: pequena e precisa da transparência/traço intactos
        }
        $img = @imagecreatefromstring($corpo);
        if ($img === false) {
            return [$corpo, $tipo];
        }
        $l = imagesx($img);
        $a = imagesy($img);
        if (max($l, $a) <= self::LADO_MAX) {
            return [$corpo, $tipo];
        }
        $fator = self::LADO_MAX / max($l, $a);
        $nova = imagescale($img, (int) round($l * $fator), (int) round($a * $fator));
        if ($nova === false) {
            return [$corpo, $tipo];
        }
        ob_start();
        imagejpeg($nova, null, 80);

        return [(string) ob_get_clean(), 'image/jpeg'];
    }

    private static function dataHora(?string $valor): ?string
    {
        return $valor !== null ? Carbon::parse($valor)->format('d/m/Y H:i') : null;
    }

    private static function periodo(?string $inicio, ?string $fim): ?string
    {
        if ($inicio === null) {
            return null;
        }
        $de = Carbon::parse($inicio);

        return $de->format('d/m/Y H:i').($fim !== null ? ' às '.Carbon::parse($fim)->format('H:i') : '');
    }
}
