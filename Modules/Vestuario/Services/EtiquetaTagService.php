<?php

declare(strict_types=1);

namespace Modules\Vestuario\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Vestuario\Services\VestuarioSettingsResolver;

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
     * Sobrescrevíveis via vestuario_settings.etiqueta.{width_dots,height_dots,dpi,margin_dots,qr_enabled}.
     */
    private const DEFAULT_WIDTH_DOTS = 400;
    private const DEFAULT_HEIGHT_DOTS = 240;
    private const DEFAULT_DPI = 203;
    private const DEFAULT_MARGIN_DOTS = 10;
    private const DEFAULT_QR_ENABLED = false;
    private const DEFAULT_QR_DATA_TEMPLATE = 'https://oimpresso.com/p/{ean13}';

    public function __construct(
        private ?VestuarioSettingsResolver $settings = null,
    ) {
        // Resolver opcional — testes existentes passam sem injetar (backward-compat Wave 27).
    }

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
        $cfg = $this->resolveConfig($businessId);

        $zpl = $this->buildZpl(
            nome:    $this->truncate($nome, 30),
            tamanho: strtoupper($tamanho),
            cor:     $this->truncate($cor, 20),
            colecao: $this->truncate($colecao, 25),
            preco:   $preco,
            sku:     $sku,
            ean13:   $ean13,
            cfg:     $cfg,
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
                'width_dots'   => $cfg['width_dots'],
                'height_dots'  => $cfg['height_dots'],
                'dpi'          => $cfg['dpi'],
                'qr_enabled'   => $cfg['qr_enabled'],
                'nome'         => $nome,
                'tamanho'      => $tamanho,
                'cor'          => $cor,
                'colecao'      => $colecao,
                'preco'        => $preco,
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
        ?array $cfg = null,
    ): string {
        $cfg = $cfg ?? $this->defaultConfig();
        $w = $cfg['width_dots'];
        $h = $cfg['height_dots'];
        $m = $cfg['margin_dots'];

        $precoFmt = 'R$ '.number_format($preco, 2, ',', '.');

        $lines = [
            '^XA',                                          // start label
            "^PW{$w}",                                      // print width
            "^LL{$h}",                                      // label length
            '^CI28',                                        // UTF-8 encoding
            "^LH{$m},{$m}",                                 // label home com margin
            // Nome produto (top, fonte 0 height 28)
            "^FO0,0^A0N,28,28^FD{$nome}^FS",
            // Tamanho + Cor (linha 2)
            "^FO0,35^A0N,22,22^FDTAM: {$tamanho}^FS",
            "^FO130,35^A0N,22,22^FDCOR: {$cor}^FS",
            // Coleção (linha 3)
            "^FO0,65^A0N,20,20^FD{$colecao}^FS",
            // Preço destaque (right-aligned-ish)
            "^FO230,90^A0N,32,32^FD{$precoFmt}^FS",
            // EAN-13 barcode (^BE = EAN-13)
            "^FO30,120^BY2,2,60^BEN,60,Y,N^FD{$ean13}^FS",
        ];

        // QR Code opcional (^BQ — Zebra ZPL II QR Code)
        if ($cfg['qr_enabled']) {
            $qrData = str_replace('{ean13}', $ean13, $cfg['qr_data_template']);
            // Posição direita do EAN-13. Model 2, magnification 4 (compacto pra 50×30mm)
            $lines[] = "^FO250,120^BQN,2,4^FDLA,{$qrData}^FS";
        }

        // SKU pequeno (rodapé)
        $lines[] = "^FO0,205^A0N,16,16^FDSKU: {$sku}^FS";
        $lines[] = '^XZ';                                   // end label

        // ZPL II — sintaxe Zebra. Validado em https://labelary.com/viewer.html
        return implode("\n", $lines);
    }

    /**
     * Expõe config atual (defaults + override per business) pro frontend/preview.
     * Não inclui qr_data_template (pode conter URL custom).
     *
     * @return array{width_dots:int, height_dots:int, dpi:int, margin_dots:int, qr_enabled:bool}
     */
    public function getPublicConfig(?int $businessId = null): array
    {
        $cfg = $this->resolveConfig($businessId);
        return [
            'width_dots'  => $cfg['width_dots'],
            'height_dots' => $cfg['height_dots'],
            'dpi'         => $cfg['dpi'],
            'margin_dots' => $cfg['margin_dots'],
            'qr_enabled'  => $cfg['qr_enabled'],
        ];
    }

    /**
     * Resolve config etiqueta combinando defaults + vestuario_settings per business.
     *
     * @return array{width_dots:int, height_dots:int, dpi:int, margin_dots:int, qr_enabled:bool, qr_data_template:string}
     */
    private function resolveConfig(?int $businessId = null): array
    {
        $defaults = $this->defaultConfig();

        // Sem resolver injetado OU sem business → defaults
        if ($this->settings === null || $businessId === null) {
            return $defaults;
        }

        $resolver = $this->settings->forBusiness($businessId);

        return [
            'width_dots'       => $resolver->getInt('etiqueta.width_dots', $defaults['width_dots'], 100, 2000),
            'height_dots'      => $resolver->getInt('etiqueta.height_dots', $defaults['height_dots'], 100, 2000),
            'dpi'              => $resolver->getInt('etiqueta.dpi', $defaults['dpi'], 100, 600),
            'margin_dots'      => $resolver->getInt('etiqueta.margin_dots', $defaults['margin_dots'], 0, 100),
            'qr_enabled'       => $resolver->getBool('etiqueta.qr_enabled', $defaults['qr_enabled']),
            'qr_data_template' => (string) $resolver->get('etiqueta.qr_data_template', $defaults['qr_data_template']),
        ];
    }

    /**
     * @return array{width_dots:int, height_dots:int, dpi:int, margin_dots:int, qr_enabled:bool, qr_data_template:string}
     */
    private function defaultConfig(): array
    {
        return [
            'width_dots'       => self::DEFAULT_WIDTH_DOTS,
            'height_dots'      => self::DEFAULT_HEIGHT_DOTS,
            'dpi'              => self::DEFAULT_DPI,
            'margin_dots'      => self::DEFAULT_MARGIN_DOTS,
            'qr_enabled'       => self::DEFAULT_QR_ENABLED,
            'qr_data_template' => self::DEFAULT_QR_DATA_TEMPLATE,
        ];
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
