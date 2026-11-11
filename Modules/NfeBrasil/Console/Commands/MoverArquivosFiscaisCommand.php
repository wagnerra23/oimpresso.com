<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Support\ArquivoFiscal;
use Throwable;

/**
 * Tira da pasta pública os arquivos fiscais gravados no disco `local` (public/uploads).
 *
 * Sem `--executar` só conta. A saída nunca mostra caminho: o nome do XML traz a chave da nota,
 * e a chave traz o CNPJ do emitente. Apaga a origem só depois de conferir o conteúdo no destino,
 * e não sobrescreve arquivo diferente que já esteja lá.
 *
 * Certificado vai para `nfe_certs`, no caminho que o CertificadoService lê.
 */
class MoverArquivosFiscaisCommand extends Command
{
    protected $signature = 'nfe:mover-arquivos-fiscais
        {--executar : Move de verdade. Sem esta opção só conta}
        {--business-id= : Limita a uma empresa}';

    protected $description = 'Move XML, DANFE, DF-e e certificado de public/uploads para discos privados (dry-run por padrão)';

    public function handle(): int
    {
        $business = $this->option('business-id');
        if ($business !== null && ! ctype_digit((string) $business)) {
            $this->error('--business-id precisa ser o número da empresa.');

            return self::INVALID;
        }

        $executar = (bool) $this->option('executar');
        $raiz = 'nfe-brasil' . ($business !== null ? '/' . (int) $business : '');
        $origem = Storage::disk(ArquivoFiscal::DISCO_ANTIGO);

        $linhas = [];
        $falhas = 0;
        foreach ($origem->allFiles($raiz) as $caminho) {
            [$disco, $destino, $tipo] = $this->destinoDe($caminho);
            $linhas[$tipo] ??= ['tipo' => $tipo, 'a_mover' => 0, 'ja_no_destino' => 0, 'conflitos' => 0, 'bytes' => 0];

            $conteudo = (string) $origem->get($caminho);
            $alvo = Storage::disk($disco);
            $jaExiste = $alvo->exists($destino);

            if ($jaExiste && md5((string) $alvo->get($destino)) !== md5($conteudo)) {
                $linhas[$tipo]['conflitos']++;
                continue;
            }

            $linhas[$tipo][$jaExiste ? 'ja_no_destino' : 'a_mover']++;
            $linhas[$tipo]['bytes'] += strlen($conteudo);

            if ($executar && ! $this->mover($caminho, $disco, $destino, $conteudo, $jaExiste)) {
                $falhas++;
            }
        }

        $this->table(['Tipo', 'A mover', 'Já no destino', 'Conflitos', 'Bytes'], array_values($linhas));
        $this->line('Registros em `arquivos` apontando para o disco antigo: ' . $this->contarRegistros($raiz));
        $this->info($executar ? "Concluído. Falhas: {$falhas}." : 'Dry-run. Nenhum arquivo foi movido. Use --executar para mover.');

        $conflitos = array_sum(array_column($linhas, 'conflitos'));

        return $executar && ($falhas > 0 || $conflitos > 0) ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{0:string,1:string,2:string} disco, caminho no destino, tipo */
    private function destinoDe(string $caminho): array
    {
        $partes = explode('/', $caminho);
        $tipo = $partes[2] ?? 'outros';

        if ($tipo === 'cert') {
            return ['nfe_certs', substr($caminho, strlen('nfe-brasil/')), $tipo];
        }

        return [ArquivoFiscal::DISCO, $caminho, $tipo];
    }

    private function mover(string $caminho, string $disco, string $destino, string $conteudo, bool $jaExiste): bool
    {
        try {
            $alvo = Storage::disk($disco);
            if (! $jaExiste) {
                $alvo->put($destino, $conteudo);
            }
            if (md5((string) $alvo->get($destino)) !== md5($conteudo)) {
                return false;
            }

            if (Schema::hasTable('arquivos')) {
                DB::table('arquivos')
                    ->where('disk', ArquivoFiscal::DISCO_ANTIGO)
                    ->where('storage_path', $caminho)
                    ->update(['disk' => $disco, 'storage_path' => $destino]);
            }

            return (bool) Storage::disk(ArquivoFiscal::DISCO_ANTIGO)->delete($caminho);
        } catch (Throwable $e) {
            // Só a classe do erro: a mensagem do disco costuma trazer o caminho.
            $this->error('Falha ao mover um arquivo: ' . $e::class);

            return false;
        }
    }

    private function contarRegistros(string $raiz): int
    {
        if (! Schema::hasTable('arquivos')) {
            return 0;
        }

        return DB::table('arquivos')
            ->where('disk', ArquivoFiscal::DISCO_ANTIGO)
            ->where('storage_path', 'like', $raiz . '/%')
            ->count();
    }
}
