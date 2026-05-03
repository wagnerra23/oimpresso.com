<?php

namespace Modules\ADS\Tools;

use Modules\ADS\Contracts\Tool;
use Laravel\Boost\Mcp\Tools\ApplicationInfo;
use Laravel\Boost\Mcp\Tools\BrowserLogs;
use Laravel\Boost\Mcp\Tools\DatabaseConnections;
use Laravel\Boost\Mcp\Tools\DatabaseQuery;
use Laravel\Boost\Mcp\Tools\DatabaseSchema;
use Laravel\Boost\Mcp\Tools\GetAbsoluteUrl;
use Laravel\Boost\Mcp\Tools\LastError;
use Laravel\Boost\Mcp\Tools\ReadLogEntries;
use Laravel\Boost\Mcp\Tools\SearchDocs;

/**
 * Adapter que expõe Laravel Boost tools nativas via nossa interface Tool.
 *
 * Wagner pediu pra usar funções nativas do Boost. Em vez de reinventar
 * LogReaderTool / DatabaseQueryTool / etc, delegamos pra Boost que já
 * tem versões testadas e documentadas.
 *
 * Boost expõe 8 tools READ-ONLY (todas com #[IsReadOnly] na Boost).
 * Para escrita, mantemos as customizadas: WriteFileTool, RunTestTool, GitCommitWipTool.
 */
class BoostToolAdapter implements Tool
{
    /** @var array<string, array{class:string, label:string, description:string, schema:array}> */
    private const BOOST_TOOLS = [
        'application-info' => [
            'class'       => ApplicationInfo::class,
            'label'       => 'application-info',
            'description' => '[Boost] Info do app: PHP version, Laravel version, packages instalados, models, DB engine.',
        ],
        'database-connections' => [
            'class'       => DatabaseConnections::class,
            'label'       => 'database-connections',
            'description' => '[Boost] Lista todas as connections de banco configuradas no projeto.',
        ],
        'database-query' => [
            'class'       => DatabaseQuery::class,
            'label'       => 'database-query',
            'description' => '[Boost] Executa SELECT no banco (read-only no driver Boost). Use para inspecionar dados.',
        ],
        'database-schema' => [
            'class'       => DatabaseSchema::class,
            'label'       => 'database-schema',
            'description' => '[Boost] Inspeciona schema: tabelas, colunas, indexes, FKs. Modo summary disponível.',
        ],
        'last-error' => [
            'class'       => LastError::class,
            'label'       => 'last-error',
            'description' => '[Boost] Última exceção/erro registrado no laravel.log.',
        ],
        'read-log-entries' => [
            'class'       => ReadLogEntries::class,
            'label'       => 'read-log-entries',
            'description' => '[Boost] Lê últimas N entradas estruturadas do laravel.log.',
        ],
        'search-docs' => [
            'class'       => SearchDocs::class,
            'label'       => 'search-docs',
            'description' => '[Boost] Busca na documentação Laravel oficial via embeddings.',
        ],
        'get-absolute-url' => [
            'class'       => GetAbsoluteUrl::class,
            'label'       => 'get-absolute-url',
            'description' => '[Boost] Constrói URL absoluta do app (helper).',
        ],
    ];

    public function __construct(
        private readonly string $boostToolKey,
    ) {
        if (! isset(self::BOOST_TOOLS[$this->boostToolKey])) {
            throw new \InvalidArgumentException("Boost tool desconhecido: {$boostToolKey}");
        }
    }

    public static function listKeys(): array
    {
        return array_keys(self::BOOST_TOOLS);
    }

    public function name(): string
    {
        return 'boost.' . $this->boostToolKey;
    }

    public function category(): string
    {
        return 'leitura (Laravel Boost)';
    }

    public function isReadOnly(): bool
    {
        // Todas as Boost tools registradas aqui são read-only
        return true;
    }

    public function description(): string
    {
        return self::BOOST_TOOLS[$this->boostToolKey]['description'];
    }

    public function inputSchema(): array
    {
        // Schema simplificado — Boost tem schema próprio em handle($request).
        // Aqui retornamos object livre; o usuário passa params via UI.
        return [
            'type'       => 'object',
            'description' => 'Params específicos da Boost tool (ver docs Boost). Vazio retorna default.',
            'properties' => new \stdClass(),
        ];
    }

    /**
     * Boost tools usam Laravel\Mcp\Server\Tool com handle(Laravel\Mcp\Request).
     * Nossa Tool interface é mais simples — vamos chamar handle direto via reflection.
     */
    public function execute(array $input): array
    {
        $toolClass = self::BOOST_TOOLS[$this->boostToolKey]['class'];

        try {
            // Instancia tool via container (resolve dependencies)
            $tool = app($toolClass);

            // Cria Request com inputs (Boost usa Laravel\Mcp\Request)
            $requestClass = '\Laravel\Mcp\Request';
            if (! class_exists($requestClass)) {
                return ['ok' => false, 'output' => null, 'error' => 'laravel_mcp_request_not_found'];
            }
            $request = new $requestClass($input);

            // Chama handle
            $response = $tool->handle($request);

            // Converte Response Laravel\Mcp para array
            $data = method_exists($response, 'toArray')
                ? $response->toArray()
                : (array) $response;

            return [
                'ok'     => true,
                'output' => $data,
                'error'  => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false, 'output' => null,
                'error' => 'boost_tool_failed: ' . $e->getMessage(),
            ];
        }
    }
}
