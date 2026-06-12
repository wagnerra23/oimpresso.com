<?php

declare(strict_types=1);

/**
 * Bug Wagner (P1b): a lista vinha alfabética (server hardcoded `name asc`) → lixo de
 * símbolo no topo (".COM", "@", "&", "+"), enterrando os clientes recorrentes. E o
 * sort de coluna estava meio-ligado (state local, backend ignorava, header morto).
 *
 * Fix: sort SERVER-SIDE com whitelist. Default job-aligned = RECENTES (id desc).
 * Colunas agregadas (total_os/valor_aberto/last_os_at) via leftJoinSub 1:1.
 * Frontend manda sort/dir em todo reload e o cabeçalho re-busca de verdade.
 *
 * Canon: structural guards. Prova da ordem real + perf = smoke pós-deploy.
 */

$controller = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
$index = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';

test('backend — sort lido com whitelist + default RECENTES (id desc), não mais name asc', function () use ($controller) {
    $src = file_get_contents($controller);
    expect($src)
        ->toContain("request()->input('sort', 'recent')")
        ->toContain("'recent' => 'contacts.id'")
        ->toContain("'name' => 'contacts.name'")
        // o hardcoded antigo (name asc na paginação) saiu
        ->not->toContain("->orderBy('contacts.name', 'asc')\n            ->paginate");
});

test('backend — colunas agregadas via leftJoinSub (whitelist anti-injeção) + NULLs por último', function () use ($controller) {
    $src = file_get_contents($controller);
    expect($src)
        ->toContain("\$AGG = ['total_os', 'valor_aberto', 'last_os_at']")
        ->toContain('leftJoinSub($aggSub, \'cli_agg\'')
        ->toContain('orderByRaw("cli_agg.{$sortInput} IS NULL")')
        // tie-breaker determinístico pra paginação estável
        ->toContain("->orderBy('contacts.id', 'desc')   // tie-breaker");
});

test('frontend — SortKey ganha "recent" e é o default (dir desc)', function () use ($index) {
    $src = file_get_contents($index);
    expect($src)
        ->toContain("type SortKey = 'recent' | 'name'")
        ->toContain("useState<SortKey>('recent')")
        ->toContain("useState<SortDir>('desc')");
});

test('frontend — handleSort re-busca server-side (cabeçalho deixa de ser morto)', function () use ($index) {
    $src = file_get_contents($index);
    expect($src)
        ->toContain('sort: key, dir: newDir')
        // os reloads de busca/página carregam o sort atual via ref
        ->toContain('sort: sortRef.current.key, dir: sortRef.current.dir');
});
