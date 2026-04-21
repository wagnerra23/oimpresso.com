<?php

namespace Modules\PontoWr2\Console\Commands;

use Illuminate\Console\Command;
use Modules\PontoWr2\Entities\Importacao;
use Modules\PontoWr2\Services\AfdParserService;

class ImportAfdCommand extends Command
{
    protected $signature = 'ponto:import-afd
                            {arquivo : Caminho absoluto do arquivo AFD}
                            {--business= : ID do business alvo}
                            {--user=1 : ID do usuário que está executando}';

    protected $description = 'Importa arquivo AFD/AFDT (Portaria MTP 671/2021) para o módulo Ponto WR2.';

    public function handle(AfdParserService $parser): int
    {
        $arquivo = $this->argument('arquivo');
        if (!file_exists($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");
            return self::FAILURE;
        }

        $importacao = Importacao::create([
            'business_id'    => $this->option('business'),
            'tipo'           => 'AFD',
            'nome_arquivo'   => basename($arquivo),
            'arquivo_path'   => $arquivo,
            'hash_arquivo'   => hash_file('sha256', $arquivo),
            'tamanho_bytes'  => filesize($arquivo),
            'usuario_id'     => $this->option('user'),
        ]);

        $this->info("Importação #{$importacao->id} criada. Processando...");
        $parser->processar($importacao);

        $importacao->refresh();
        $this->info("Concluído: {$importacao->linhas_sucesso}/{$importacao->linhas_total} sucesso, {$importacao->linhas_erro} erros.");

        return self::SUCCESS;
    }
}
