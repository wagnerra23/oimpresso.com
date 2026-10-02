<?php

declare(strict_types=1);
// Cobre UC-CIDX-02 (Index.casos.md) - G-2 rastreabilidade caso-teste.

/**
 * Bug Wagner (2026-06-12): "paginação não está funcionando" + "desalinhados".
 *
 * Causa: as setas/dropdown só atualizavam o state local (setPage/setPerPage) e
 * NUNCA re-buscavam — o servidor ficava preso na página 1, per_page=50 default
 * (13.432/50 ≈ 269 páginas) enquanto o dropdown mostrava 100. Setas mortas.
 * O backend (paginate() + input('per_page')) JÁ suportava — era só o frontend
 * não mandar page/per_page no router.reload.
 *
 * + FAB de atalhos (fixed bottom-4 right-4) cobria a paginação no fim da rolagem.
 *
 * Canon: structural guards (file_get_contents). Prova do clique real = smoke pós-deploy.
 */

$index = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
$controller = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';

test('paginação — onPageChange manda page + per_page pro servidor (seta deixa de ser morta)', function () use ($index) {
    $src = file_get_contents($index);
    expect($src)
        ->toContain('onPageChange={(p) => {')
        // Sem o `}` final: desde o #2625 (2026-06-12) o mesmo objeto também leva sort/dir.
        ->toContain('data: { q: search || undefined, page: p, per_page: perPage,');
});

test('paginação — onPerPageChange reseta page=1 e manda o novo per_page', function () use ($index) {
    $src = file_get_contents($index);
    expect($src)
        ->toContain('onPerPageChange={(n) => {')
        // Sem o `}` final: desde o #2625 (2026-06-12) o mesmo objeto também leva sort/dir.
        ->toContain('data: { q: search || undefined, page: 1, per_page: n,');
});

test('paginação — perPage default 50 = default do backend (dropdown não diverge do "/ N")', function () use ($index) {
    $src = file_get_contents($index);
    expect($src)->toContain('const [perPage, setPerPage] = useState(50)');
});

test('paginação — busca preserva o tamanho de página (per_page via ref, sem virar dep)', function () use ($index) {
    $src = file_get_contents($index);
    expect($src)
        ->toContain('const perPageRef = useRef(perPage)')
        // Desde o #2625 (2026-06-12) o objeto também leva sort/dir. O `sort: sortRef.current.key`
        // amarra ao efeito de BUSCA: o handler de ordenação usa o mesmo per_page com `sort: key`.
        ->toContain('data: { q: search || undefined, page: 1, per_page: perPageRef.current, sort: sortRef.current.key');
});

test('backend — buildClienteIndexCustomers já pagina via per_page (paginate lê page da query)', function () use ($controller) {
    $src = file_get_contents($controller);
    expect($src)
        ->toContain("request()->input('per_page', 50)")
        ->toContain('->paginate($perPage)');
});

// O FAB flutuante de atalhos (e o spacer que o afastava da paginação) saiu no #2778
// (2026-06-15), a pedido do [W]: "mais discreto, só encaixar no rodapé". O risco que este
// teste guardava — FAB cobrindo o pager — deixou de existir. O que fica é o atalho NO rodapé.
test('alinhamento — atalhos de teclado ficam no rodapé da paginação, sem botão flutuante', function () use ($index) {
    $src = file_get_contents($index);
    expect($src)
        ->toContain('onShowShortcuts={() => setCheatOpen(true)}')
        ->not->toContain('bottom-4 right-4');
});
