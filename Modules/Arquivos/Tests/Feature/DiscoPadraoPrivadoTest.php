<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Os discos do Arquivos não podem ficar dentro da pasta servida pela web.
 *
 * Contrato:
 * - ADR 0123 §3 e ADR 0214: anexo é privado e só sai pelo DownloadController (link assinado,
 *   com trilha). Arquivo dentro de public/ é baixável por quem souber o caminho.
 *
 * Por que existe: até 2026-11-18 o padrão de `arquivos.disk_default` era 'local', cuja raiz
 * neste app é public/uploads. A produção não define ARQUIVOS_DISK_DEFAULT (medido em
 * 2026-11-17), então o próximo upload não-sensível iria para a pasta pública. Este teste lê o
 * mesmo `config()` que o ArquivosService lê e cai se o padrão voltar para um disco local com
 * raiz em public/.
 *
 * Disco que não é local (ex.: s3/MinIO da ADR 0214) não tem raiz no filesystem e passa.
 */
function arquivosRaizDentroDoPublic(string $nomeDisco): ?string
{
    $disco = config("filesystems.disks.{$nomeDisco}");
    expect($disco)->toBeArray();

    if (($disco['driver'] ?? null) !== 'local') {
        return null;
    }

    $raiz = str_replace(DIRECTORY_SEPARATOR, '/', (string) ($disco['root'] ?? ''));
    $public = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', public_path()), '/');

    return ($raiz === $public || str_starts_with($raiz, $public.'/')) ? $raiz : null;
}

it('o disco padrão dos anexos fica fora da pasta pública', function () {
    $nome = (string) config('arquivos.disk_default');

    expect($nome)->not->toBe('');
    expect(arquivosRaizDentroDoPublic($nome))->toBeNull();
});

it('o disco do cofre fica fora da pasta pública', function () {
    $nome = (string) config('arquivos.disk_vault');

    expect($nome)->not->toBe('');
    expect(arquivosRaizDentroDoPublic($nome))->toBeNull();
});

it('controle: o disco local deste app tem raiz em public, então o teste discrimina', function () {
    // Sem este controle, um public_path() que nunca casasse deixaria os dois testes acima
    // verdes por construção. 'local' é o disco que motivou a troca.
    expect(arquivosRaizDentroDoPublic('local'))->not->toBeNull();
});
