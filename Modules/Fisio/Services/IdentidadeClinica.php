<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Quem emite o impresso: o cabeçalho comum dos PDFs do Fisio (pdf/_base.blade.php).
 *
 * Fonte: o cadastro da empresa que o UltimatePOS já tem — `business` (nome — o mesmo que o
 * rodapé sempre mostrou —, logo, documento fiscal) e a primeira unidade ativa em
 * `business_locations` (endereço e contato). Nada é digitado de novo para o papel.
 *
 * Tier 0: as duas consultas filtram por `business_id` da sessão; unidade de outra empresa
 * nunca aparece no cabeçalho (FisioProntuarioPdfTest, 98 contra 99).
 *
 * Logo: só JPG ou PNG, embutido como data URI — o dompdf não busca nada pela rede e não lê
 * WebP. Logo ausente, grande demais ou ilegível sai sem logo, sem erro: o documento não pode
 * deixar de sair por causa da marca.
 */
class IdentidadeClinica
{
    private const LOGO_TIPOS = ['image/jpeg', 'image/png'];

    private const LOGO_MAX_BYTES = 600 * 1024;

    /** @return array{nome: string, documento: ?string, endereco: ?string, contato: ?string, logo: ?string} */
    public function montar(int $businessId): array
    {
        $empresa = DB::table('business')->where('id', $businessId)->first();
        $unidade = DB::table('business_locations')
            ->where('business_id', $businessId)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        return [
            'nome' => (string) ($empresa?->name ?? ''),
            'documento' => $this->documento($empresa, $unidade),
            'endereco' => $this->endereco($unidade),
            'contato' => $this->juntar([$unidade?->mobile ?? null, $unidade?->alternate_number ?? null, $unidade?->email ?? null, $unidade?->website ?? null]),
            'logo' => $this->logo($empresa?->logo ?? null),
        ];
    }

    private function documento(?object $empresa, ?object $unidade): ?string
    {
        if (! empty($unidade?->cnpj)) {
            return 'CNPJ '.$unidade->cnpj;
        }
        if (! empty($empresa?->tax_number_1)) {
            return trim(($empresa->tax_label_1 ?? '').' '.$empresa->tax_number_1);
        }

        return null;
    }

    private function endereco(?object $unidade): ?string
    {
        if (! $unidade) {
            return null;
        }
        $cidade = $this->juntar([$unidade->city ?? null, $unidade->state ?? null], ' – ');
        $cep = trim((string) ($unidade->zip_code ?? ''));

        return $this->juntar([
            $unidade->landmark ?? null,
            $cidade,
            strlen(preg_replace('/\D/', '', $cep)) >= 5 ? 'CEP '.$cep : null,
        ]);
    }

    /** @param list<?string> $partes */
    private function juntar(array $partes, string $sep = ' · '): ?string
    {
        $limpas = array_values(array_filter(array_map(fn ($p) => trim((string) $p), $partes), fn ($p) => $p !== ''));

        return $limpas ? implode($sep, $limpas) : null;
    }

    private function logo(?string $arquivo): ?string
    {
        if (! $arquivo || str_contains($arquivo, '..') || str_contains($arquivo, '/') || str_contains($arquivo, '\\')) {
            return null;
        }

        try {
            foreach ([Storage::path('business_logos/'.$arquivo), public_path('uploads/business_logos/'.$arquivo)] as $caminho) {
                if (! is_file($caminho) || filesize($caminho) > self::LOGO_MAX_BYTES) {
                    continue;
                }
                $tipo = mime_content_type($caminho) ?: '';
                if (! in_array($tipo, self::LOGO_TIPOS, true)) {
                    return null;
                }

                return 'data:'.$tipo.';base64,'.base64_encode((string) file_get_contents($caminho));
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
