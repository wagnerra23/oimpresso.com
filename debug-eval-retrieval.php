<?php
// Debug script — reproduz exatamente o que retrieveKbContext faz no eval
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;

$queries = [
    'Como funciona o Permission Registry?',
    'Onde mora a tela Usuário 360°?',
    'Por que TeamMcp foi separado do Copiloto?',
    'Posso corrigir o shift +3h em format_date?',
];

foreach ($queries as $query) {
    echo "=== query: {$query} ===\n";
    $docs = McpMemoryDocument::search($query, function ($idx, $q, $params) {
        $params['hybrid'] = ['embedder' => 'qwen3_local', 'semanticRatio' => 0.6];
        $params['filter'] = "status NOT IN ['superseded', 'deprecated', 'rascunho']";
        $params['limit'] = 3;
        return $idx->search($q, $params);
    })->take(3)->get();
    foreach ($docs as $d) {
        echo "  {$d->slug} | " . substr($d->title ?? '', 0, 50) . "\n";
    }
    echo "\n";
}
