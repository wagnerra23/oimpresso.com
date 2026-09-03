<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Jana\Console\Commands\HealthCheckCommand;

uses(Tests\TestCase::class);

/**
 * GUARD — todo comando de `Modules/Jana/Console/Commands/` existe no artisan.
 *
 * Origem, medida em 2026-09-02 sobre `origin/main`: a pasta tinha **46** arquivos e o
 * `JanaServiceProvider::commands([...])` registrava **41**. Os 5 do diff falhavam com
 * `Command "x" is not defined` em QUALQUER host — não é "não roda em prod": o artisan
 * nunca os conheceu. `app/Console/Kernel.php` só faz `$this->load()` de
 * `app/Console/Commands`, nunca de `Modules/` (medido), então não há rota alternativa.
 *
 * ORÁCULO: `Artisan::all()` — o registry VIVO. Deliberadamente NÃO usa `class_exists()`
 * nem `file_exists()`: esses medem o DISCO, e disco não responde "está registrado"
 * (proibicoes §5 2026-07-28 — 2 comandos ficaram mortos 2,4 meses atrás de um teste que
 * media `app(Class::class)`).
 *
 * O guard é CEGO a quais comandos existem: deriva o universo da própria pasta, então
 * cobre a classe inteira — comando novo que nasça sem registro cai aqui, sem lista pra
 * alguém esquecer de atualizar.
 *
 * Não usa DB nem auth (só o container), logo é lógica pura e roda na lane
 * `jana-logica-pura-pest.yml` (SQLite :memory:), onde entrou no `paths:` E na lista de
 * execução no mesmo PR — senão seria verde por não-execução (LC-13).
 */
it('registra no artisan todo comando de Modules/Jana/Console/Commands', function () {
    // O nome do comando é o 1º token do $signature (o resto são args/opções).
    $nomeDe = static fn (string $sig): string => (string) preg_split('/\s+/', trim($sig), 2)[0];

    // ── Controle positivo 1: o EXTRATOR funciona (independe do corpus real).
    expect($nomeDe("jana:exemplo\n  {--flag : desc}"))->toBe('jana:exemplo');

    $registrados = array_keys(Artisan::all());

    // ── Controle positivo 2: o registry do artisan está populado. Sem isto, um
    //    Artisan::all() vazio faria TODO comando virar violação (ruído inútil).
    expect($registrados)->toContain('schedule:list');

    $dir = base_path('Modules/Jana/Console/Commands');
    $arquivos = glob($dir.'/*.php') ?: [];

    // ── Controle positivo 3: o corpus existe. Glob vazio = guard vacuamente verde.
    expect(count($arquivos))->toBeGreaterThan(40);

    // Prefixo do namespace derivado de uma classe REAL — evita literal com par de
    // barra invertida, que colapsa no transporte da escrita (proibicoes §5 2026-08-19).
    $barra = chr(92);
    $prefixo = substr(HealthCheckCommand::class, 0, (int) strrpos(HealthCheckCommand::class, $barra) + 1);

    $faltando = [];

    foreach ($arquivos as $arquivo) {
        $fqcn = $prefixo.basename($arquivo, '.php');

        if (! class_exists($fqcn)) {
            $faltando[] = basename($arquivo).' — classe não carrega ('.$fqcn.')';
            continue;
        }

        $ref = new ReflectionClass($fqcn);

        // Base abstrata / trait-holder não é comando invocável: fora do universo.
        if ($ref->isAbstract()) {
            continue;
        }

        $sig = $ref->getDefaultProperties()['signature'] ?? null;

        if (! is_string($sig) || $sig === '') {
            $faltando[] = basename($arquivo).' — sem $signature';
            continue;
        }

        $nome = $nomeDe($sig);

        if (! in_array($nome, $registrados, true)) {
            $faltando[] = basename($arquivo)." — `{$nome}` não está no Artisan::all()";
        }
    }

    // ⚠️ NÃO usar toContain(x, "mensagem"): toContain é VARIÁDICO no Pest, a mensagem
    // vira 2º needle e o assert falha sempre (§5 2026-07-28). O diagnóstico sai do array.
    expect($faltando)->toBe([]);
});
