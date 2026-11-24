<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda as linhas conferidas no preview do Import CSV FORA da sessão do usuário (UC-NFIM-15).
 *
 * Por que existe: o `preview` guardava todas as linhas válidas em `session()`. Medido no CT 100 em
 * 2026-11-24: um CSV de 5 MB (o teto do upload, 119.153 linhas) virava 30,9 MB de sessão
 * serializada — e produção usa `SESSION_DRIVER=file`, então esse arquivo era lido e regravado a
 * cada request do usuário enquanto a conferência estivesse pendente.
 *
 * Agora as linhas vão para um arquivo JSON em `storage/app/nfe-import-csv/{business}/{user}/` e a
 * sessão guarda só a referência (um uuid) e o carimbo do business (UC-NFIM-04).
 *
 * Isolamento (ADR 0093): o caminho é montado com o business e o usuário de QUEM APLICA, não com o
 * que veio na sessão. Lote conferido noutra empresa cai noutra pasta e simplesmente não é achado —
 * é a segunda barreira, atrás do carimbo do Controller.
 *
 * Fora do disco `local` de propósito: a raiz dele é `public/uploads`, servida pela web.
 *
 * Limpeza: cada preview substitui o pendente do mesmo usuário na mesma empresa; aplicar ou recusar
 * apaga o arquivo; e todo preview poda arquivos com mais de VALIDADE_HORAS (o lote abandonado,
 * cuja sessão expirou sem aplicar).
 */
final class ImportRegrasCsvConferencia
{
    /** Referência (uuid) do arquivo conferido. Única coisa do lote que fica na sessão. */
    public const SESSAO_REF = 'nfe_import_csv_conferencia';

    /** Business em que o preview foi conferido (UC-NFIM-04). */
    public const SESSAO_BUSINESS = 'nfe_import_csv_business_id';

    /** Lote pendente mais velho que isto é abandonado e some no próximo preview de qualquer um. */
    public const VALIDADE_HORAS = 24;

    private const RAIZ = 'app/nfe-import-csv';

    private ?Filesystem $disco = null;

    /**
     * Grava as linhas conferidas e devolve a referência que vai para a sessão. Antes, apaga o lote
     * pendente anterior deste usuário nesta empresa e poda os abandonados.
     *
     * @param  list<array<string,mixed>>  $linhas
     */
    public function guardar(int $businessId, int $userId, array $linhas): string
    {
        $this->podarVencidos();
        $this->disco()->deleteDirectory($this->pasta($businessId, $userId));

        $ref = (string) Str::uuid();
        $this->disco()->put(
            $this->caminho($businessId, $userId, $ref),
            // PRESERVE_ZERO_FRACTION: sem ele a alíquota 0.0 volta do disco como int 0 (medido no
            // teste de ida-e-volta) e o lote aplicado deixa de ser idêntico ao conferido.
            json_encode($linhas, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
        );

        return $ref;
    }

    /**
     * Linhas do lote conferido por este usuário nesta empresa. Null quando a referência é inválida,
     * o arquivo não existe (outra empresa, já aplicado, podado) ou não é JSON de lista.
     *
     * @return list<array<string,mixed>>|null
     */
    public function ler(int $businessId, int $userId, ?string $ref): ?array
    {
        if (! $this->refValida($ref)) {
            return null;
        }

        $conteudo = $this->disco()->get($this->caminho($businessId, $userId, (string) $ref));
        if ($conteudo === null) {
            return null;
        }

        $linhas = json_decode($conteudo, true);

        return is_array($linhas) && array_is_list($linhas) ? $linhas : null;
    }

    public function descartar(int $businessId, int $userId, ?string $ref): void
    {
        if ($this->refValida($ref)) {
            $this->disco()->delete($this->caminho($businessId, $userId, (string) $ref));
        }
    }

    /** Apaga lotes com mais de VALIDADE_HORAS. Devolve quantos apagou. */
    public function podarVencidos(): int
    {
        $limite = now()->subHours(self::VALIDADE_HORAS)->getTimestamp();
        $apagados = 0;

        foreach ($this->disco()->allFiles() as $arquivo) {
            if ($this->disco()->lastModified($arquivo) < $limite) {
                $this->disco()->delete($arquivo);
                $apagados++;
            }
        }

        return $apagados;
    }

    /** Caminho relativo à raiz do disco. Público para os testes conferirem a limpeza. */
    public function caminho(int $businessId, int $userId, string $ref): string
    {
        return $this->pasta($businessId, $userId) . '/' . $ref . '.json';
    }

    /** Caminho absoluto da raiz, para os testes. */
    public static function raizAbsoluta(): string
    {
        return storage_path(self::RAIZ);
    }

    private function pasta(int $businessId, int $userId): string
    {
        return $businessId . '/' . $userId;
    }

    /** A referência vem da sessão, mas vira nome de arquivo: só uuid passa. */
    private function refValida(?string $ref): bool
    {
        return is_string($ref) && Str::isUuid($ref);
    }

    private function disco(): Filesystem
    {
        return $this->disco ??= Storage::build([
            'driver' => 'local',
            'root'   => self::raizAbsoluta(),
            'throw'  => false,
        ]);
    }
}
