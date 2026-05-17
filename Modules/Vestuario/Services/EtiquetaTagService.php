<?php

declare(strict_types=1);

namespace Modules\Vestuario\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * EtiquetaTagService — geração de etiquetas térmicas TAG (Zebra ZPL) pra peças
 * de vestuário (W27 G1 P0 CAPTERRA-FICHA Vestuario).
 *
 * Por que existe:
 * - Cliente piloto ROTA LIVRE (Larissa, biz=4) precisa imprimir TAGs em
 *   impressora térmica Zebra (modelo Argox/Elgin compatível ZPL) com:
 *   nome produto + tamanho + cor + coleção + preço + EAN-13 + SKU
 * - CAPTERRA-FICHA W22 marcou como P0 gap vs Mubisys/Linx Microvix
 *   (concorrentes diretos vestuário BR).
 *
 * Stack:
 * - Saída ZPL pura (string) — compatível com Zebra GK420t, Argox OS-214, Elgin L42
 * - EAN-13 check digit calculado in-house (algoritmo GS1, sem dependência externa)
 * - Tamanho etiqueta default: 50mm × 30mm (203dpi = 400 × 240 dots)
 *
 * Multi-tenant: opera via $businessId explícito ou session().
 *
 * @see memory/requisitos/Vestuario/CAPTERRA-FICHA-2026-05-13.md §G1
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see https://developer.zebra.com/products/printers/zpl
 */
class EtiquetaTagService
{
    /**
     * Default: etiqueta TAG 50×30mm @ 203 dpi (padrão Argox/Elgin BR).
     */
    private const DEFAULT_WIDTH_DOTS = 400;
    private const DEFAULT_HEIGHT_DOTS = 240;
    private const DEFAULT_DPI = 203;

    /**
     * Gera 1 etiqueta TAG em formato ZPL pra 1 product variation.
     *
     * @param  int    $productId    ID do produto (UltimatePOS products.id)
     * @param  int    $variationId  ID da variation (UltimatePOS variations.id)
     * @param  array  $opts         {
     *     @var string  $nome         Nome do produto (default: "Produto #{id}")
     *     @var string  $tamanho      Ex: "M", "G", "GG"
     *     @var string  $cor          Ex: "Azul Marinho"
     *     @var string  $colecao      Ex: "Verão 2026"
     *     @var float   $preco        Preço em BRL (formato 99.90)
     *     @var string  $sku          Código interno (vira base do EAN-13)
     *     @var ?string $ean13        EAN-13 pré-existente (12 dígitos sem check)
     *     @var ?int    $businessId   Override business_id
     * }
     * @return array{zpl: string, ean13: string, sku: string, meta: array}
     */
    public function gerarEtiqueta(int $productId, int $variationId, array $opts = []): array
    {
        $nome    = (string) ($opts['nome'] ?? "Produto #{$productId}");
        $tamanho = (string) ($opts['tamanho'] ?? 'U');
        $cor     = (string) ($opts['cor'] ?? '-');
        $colecao = (string) ($opts['colecao'] ?? '');
        $preco   = (float) ($opts['preco'] ?? 0.0);
        $sku     = (string) ($opts['sku'] ?? sprintf('%06d%05d', $productId, $variationId));

        // EAN-13: se vem pré-existente, valida; senão deriva do SKU
        if (! empty($opts['ean13'])) {
            $ean13 = $this->normalizeEan13((string) $opts['ean13']);
        } else {
            $ean13 = $this->generateEan13FromSku($sku);
        }

        $businessId = $opts['businessId'] ?? $this->resolveBusinessId();

        $zpl = $this->buildZpl(
            nome:    $this->truncate($nome, 30),
            tamanho: strtoupper($tamanho),
            cor:     $this->truncate($cor, 20),
            colecao: $this->truncate($colecao, 25),
            preco:   $preco,
            sku:     $sku,
            ean13:   $ean13,
        );

        Log::info('vestuario.etiqueta.gerada', [
            'business_id'  => $businessId,
            'product_id'   => $productId,
            'variation_id' => $variationId,
            'sku'          => $sku,
            'ean13'        => $ean13,
        ]);

        return [
            'zpl'   => $zpl,
            'ean13' => $ean13,
            'sku'   => $sku,
            'meta'  => [
                'product_id'   => $productId,
                'variation_id' => $variationId,
                'business_id'  => $businessId,
                'width_dots'   => self::DEFAULT_WIDTH_DOTS,
                'height_dots'  => self::DEFAULT_HEIGHT_DOTS,
                'dpi'          => self::DEFAULT_DPI,
            ],
        ];
    }

    /**
     * Gera lote de etiquetas concatenando ZPL (1 envio TCP/USB pra impressora).
     *
     * @param  array<int, array> $items  Cada item: {product_id, variation_id, opts}
     * @return string                    ZPL único concatenado
     */
    public function gerarLote(array $items): string
    {
        if (empty($items)) {
            throw new InvalidArgumentException('gerarLote: items vazio');
        }

        $zplFull = '';
        foreach ($items as $idx => $item) {
            $productId   = (int) ($item['product_id']   ?? 0);
            $variationId = (int) ($item['variation_id'] ?? 0);
            $opts        = (array) ($item['opts']        ?? []);

            if ($productId <= 0) {
                throw new InvalidArgumentException("gerarLote item #{$idx}: product_id inválido");
            }

            $result = $this->gerarEtiqueta($productId, $variationId, $opts);
            $zplFull .= $result['zpl']."\n";
        }

        return rtrim($zplFull)."\n";
    }

    /**
     * Calcula check digit EAN-13 (GS1 algorithm).
     *
     * Algoritmo:
     * 1. Posições ímpares (1,3,5,7,9,11) somadas × 1
     * 2. Posições pares (2,4,6,8,10,12) somadas × 3
     * 3. Check = (10 - (soma % 10)) % 10
     *
     * @param  string $first12  Primeiros 12 dígitos (sem check digit)
     * @return int              Check digit (0-9)
     */
    public function generateEan13(string $first12): int
    {
        $first12 = preg_replace('/\D+/', '', $first12) ?? '';
        if (strlen($first12) !== 12) {
            throw new InvalidArgumentException(
                "generateEan13: precisa de exatamente 12 dígitos, recebeu ".strlen($first12)
            );
        }

        $sumOdd  = 0;
        $sumEven = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $first12[$i];
            // posição 1-indexed: i=0 → pos 1 (ímpar), i=1 → pos 2 (par)
            if (($i + 1) % 2 === 1) {
                $sumOdd += $digit;
            } else {
                $sumEven += $digit;
            }
        }

        $total = $sumOdd + ($sumEven * 3);
        return (10 - ($total % 10)) % 10;
    }

    /**
     * Valida EAN-13 completo (13 dígitos com check digit).
     */
    public function validateEan13(string $ean13): bool
    {
        $ean13 = preg_replace('/\D+/', '', $ean13) ?? '';
        if (strlen($ean13) !== 13) {
            return false;
        }

        $first12   = substr($ean13, 0, 12);
        $checkReal = (int) $ean13[12];
        $checkCalc = $this->generateEan13($first12);

        return $checkReal === $checkCalc;
    }

    /**
     * Deriva EAN-13 válido a partir de SKU arbitrário.
     *
     * Estratégia: padding zeros à esquerda + prefixo BR (789) se SKU < 12 dígitos.
     * NOTA: prefixo 789 é GS1 Brasil (uso oficial requer registro). Pra uso
     * interno (etiqueta loja, não revenda), aceitável. Cliente que quer GTIN
     * oficial cadastra próprio EAN-13 via $opts['ean13'].
     */
    public function generateEan13FromSku(string $sku): string
    {
        $numeric = preg_replace('/\D+/', '', $sku) ?? '';
        if ($numeric === '') {
            // SKU 100% alfa: hash CRC32 → 10 dígitos
            $numeric = (string) sprintf('%010d', crc32($sku) % 10000000000);
        }

        // Garante 12 dígitos: prefixo 789 (Brasil) + padding zeros
        $padded = str_pad(substr($numeric, 0, 9), 9, '0', STR_PAD_LEFT);
        $first12 = '789'.$padded;

        $check = $this->generateEan13($first12);
        return $first12.$check;
    }

    /**
     * Normaliza EAN-13 vindo de input externo.
     * Aceita 12 dígitos (calcula check) ou 13 dígitos (valida).
     */
    public function normalizeEan13(string $input): string
    {
        $clean = preg_replace('/\D+/', '', $input) ?? '';

        if (strlen($clean) === 12) {
            return $clean.((string) $this->generateEan13($clean));
        }

        if (strlen($clean) === 13) {
            if (! $this->validateEan13($clean)) {
                throw new InvalidArgumentException("EAN-13 inválido (check digit): {$clean}");
            }
            return $clean;
        }

        throw new InvalidArgumentException("EAN-13 deve ter 12 ou 13 dígitos, recebeu ".strlen($clean));
    }

    /**
     * Constrói string ZPL pra 1 etiqueta TAG 50×30mm.
     *
     * Layout:
     *   [Nome produto — 30 chars]
     *   TAM: M    COR: Azul Marinho
     *   Coleção: Verão 2026
     *   R$ 99,90
     *   [BARCODE EAN-13]
     *   789000000001237
     */
    private function buildZpl(
        string $nome,
        string $tamanho,
        string $cor,
        string $colecao,
        float  $preco,
        string $sku,
        string $ean13,
    ): string {
        $w = self::DEFAULT_WIDTH_DOTS;
        $h = self::DEFAULT_HEIGHT_DOTS;

        $precoFmt = 'R$ '.number_format($preco, 2, ',', '.');

        // ZPL II — sintaxe Zebra. Validado em https://labelary.com/viewer.html
        return implode("\n", [
            '^XA',                                          // start label
            "^PW{$w}",                                      // print width
            "^LL{$h}",                                      // label length
            '^CI28',                                        // UTF-8 encoding
            '^LH0,0',                                       // label home
            // Nome produto (top, fonte 0 height 28)
            "^FO10,10^A0N,28,28^FD{$nome}^FS",
            // Tamanho + Cor (linha 2)
            "^FO10,45^A0N,22,22^FDTAM: {$tamanho}^FS",
            "^FO140,45^A0N,22,22^FDCOR: {$cor}^FS",
            // Coleção (linha 3)
            "^FO10,75^A0N,20,20^FD{$colecao}^FS",
            // Preço destaque (right-aligned-ish)
            "^FO240,100^A0N,32,32^FD{$precoFmt}^FS",
            // EAN-13 barcode (^BE = EAN-13)
            "^FO40,130^BY2,2,60^BEN,60,Y,N^FD{$ean13}^FS",
            // SKU pequeno (rodapé)
            "^FO10,215^A0N,16,16^FDSKU: {$sku}^FS",
            '^XZ',                                          // end label
        ]);
    }

    private function resolveBusinessId(): ?int
    {
        return session('user.business_id') ?? session('business.id');
    }

    private function truncate(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }
        return mb_substr($s, 0, $max - 1).'…';
    }
}
