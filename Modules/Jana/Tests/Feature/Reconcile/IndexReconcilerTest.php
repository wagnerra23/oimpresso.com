<?php

declare(strict_types=1);

use Modules\Jana\Services\Reconcile\Reconcilers\IndexReconciler;
use Modules\Jana\Services\Reconcile\ReconcileDrift;

uses(Tests\TestCase::class);

/**
 * IndexReconciler — cobre o NÚCLEO PURO `analisar()` com desired/observed INJETADOS
 * (sem disco, determinístico) + a IDEMPOTÊNCIA da cura num tmpfile real.
 *
 * Mapa pra tarefa P0 (ADR 0237):
 *   - colisão-não-registrada → drift HEALABLE (cura reescreve numbering_collisions);
 *   - contagem stale         → drift HEALABLE (cura reescreve total_adrs/unique_numbers);
 *   - link-quebrado          → drift NÃO-HEALABLE (só alerta — humano decide);
 *   - tudo-em-sincronia      → synced (driftCount 0, inSync true);
 *   - cura idempotente       → rodar a cura 2× = mesmo arquivo (regex cirúrgico).
 *
 * Lógica de detecção espelha AdrNumberCollisionTest + DesignIndexSingleSourceTest;
 * aqui validamos o COMPORTAMENTO de reconciliação (drift + heal), não o conteúdo
 * real do repo (esses são os testes-gate; este é o runtime do reconciler).
 */

/**
 * Helper: encontra o 1º drift com determinado target (match exato OU por prefixo,
 * pra alvos compostos tipo "design_index.link:<path>"). Recebe a lista de drifts.
 *
 * @param array<int, ReconcileDrift> $drifts
 */
function driftPorTarget(array $drifts, string $target): ?ReconcileDrift
{
    foreach ($drifts as $d) {
        if ($d->target === $target || str_starts_with($d->target, $target)) {
            return $d;
        }
    }

    return null;
}

// ─── NÚCLEO PURO analisar() ──────────────────────────────────────────────────

it('analisar: tudo em sincronia → nenhum drift', function () {
    $rec = new IndexReconciler('/tmp/inexistente-base');

    $estado = [
        'collisions' => ['0101', '0170'],
        'total_adrs' => 238,
        'unique_numbers' => 226,
    ];
    $desired = $estado;
    $observed = $estado + ['design_links_broken' => []];

    $drifts = $rec->analisar($desired, $observed);

    expect($drifts)->toBe([]);
});

it('analisar: colisão real NÃO registrada → drift HEALABLE em numbering_collisions', function () {
    $rec = new IndexReconciler('/tmp/inexistente-base');

    $desired = [
        'collisions' => ['0101', '0170', '0235'], // 0235 colide no disco
        'total_adrs' => 238,
        'unique_numbers' => 226,
    ];
    $observed = [
        'collisions' => ['0101', '0170'],          // mas não está registrado
        'total_adrs' => 238,
        'unique_numbers' => 226,
        'design_links_broken' => [],
    ];

    $drifts = $rec->analisar($desired, $observed);
    $drift = collect($drifts)->firstWhere('target', 'lifecycle.numbering_collisions');

    expect($drift)->not->toBeNull()
        ->and($drift->healable)->toBeTrue()
        ->and($drift->healed)->toBeFalse() // analisar() só detecta, não cura
        ->and($drift->desired)->toContain('0235')
        ->and($drift->observed)->not->toContain('0235')
        ->and($drift->detail)->toContain('0235'); // surfaça a colisão faltante
});

it('analisar: entrada órfã/stale em numbering_collisions → drift HEALABLE', function () {
    $rec = new IndexReconciler('/tmp/inexistente-base');

    $desired = [
        'collisions' => ['0101'],                  // só 0101 colide de fato
        'total_adrs' => 238,
        'unique_numbers' => 226,
    ];
    $observed = [
        'collisions' => ['0101', '0999'],          // 0999 é órfã (não colide mais)
        'total_adrs' => 238,
        'unique_numbers' => 226,
        'design_links_broken' => [],
    ];

    $drift = collect($rec->analisar($desired, $observed))
        ->firstWhere('target', 'lifecycle.numbering_collisions');

    expect($drift)->not->toBeNull()
        ->and($drift->healable)->toBeTrue()
        ->and($drift->detail)->toContain('0999')
        ->and($drift->detail)->toContain('órf'); // "órfã/stale"
});

it('analisar: contagem stale total_adrs/unique_numbers → 2 drifts HEALABLE', function () {
    $rec = new IndexReconciler('/tmp/inexistente-base');

    $desired = [
        'collisions' => ['0101'],
        'total_adrs' => 238,
        'unique_numbers' => 226,
    ];
    $observed = [
        'collisions' => ['0101'],
        'total_adrs' => 119,    // stale
        'unique_numbers' => 116, // stale
        'design_links_broken' => [],
    ];

    $drifts = $rec->analisar($desired, $observed);

    $total = collect($drifts)->firstWhere('target', 'lifecycle.total_adrs');
    $unique = collect($drifts)->firstWhere('target', 'lifecycle.unique_numbers');

    expect($total)->not->toBeNull()
        ->and($total->healable)->toBeTrue()
        ->and($total->desired)->toBe('238')
        ->and($total->observed)->toBe('119')
        ->and($unique)->not->toBeNull()
        ->and($unique->healable)->toBeTrue()
        ->and($unique->desired)->toBe('226')
        ->and($unique->observed)->toBe('116');
});

it('analisar: frontmatter sem total_adrs (ausente) → drift HEALABLE com observed "(ausente)"', function () {
    $rec = new IndexReconciler('/tmp/inexistente-base');

    $desired = ['collisions' => [], 'total_adrs' => 238, 'unique_numbers' => 226];
    $observed = ['collisions' => [], 'total_adrs' => null, 'unique_numbers' => 226, 'design_links_broken' => []];

    $drift = collect($rec->analisar($desired, $observed))->firstWhere('target', 'lifecycle.total_adrs');

    expect($drift)->not->toBeNull()
        ->and($drift->healable)->toBeTrue()
        ->and($drift->observed)->toBe('(ausente)');
});

it('analisar: link de design quebrado → drift NÃO-HEALABLE (alerta humano)', function () {
    $rec = new IndexReconciler('/tmp/inexistente-base');

    $desired = ['collisions' => [], 'total_adrs' => 238, 'unique_numbers' => 226];
    $observed = [
        'collisions' => [],
        'total_adrs' => 238,
        'unique_numbers' => 226,
        'design_links_broken' => ['../../decisions/proposals/governanca-evolucao-doc-design.md'],
    ];

    $drift = driftPorTarget($rec->analisar($desired, $observed), 'design_index.link');

    expect($drift)->not->toBeNull()
        ->and($drift->healable)->toBeFalse() // link quebrado nunca cura sozinho
        ->and($drift->healed)->toBeFalse()
        ->and($drift->observed)->toContain('governanca-evolucao-doc-design.md');
});

it('analisar: normaliza colisões mistas (int YAML vs string zero-pad) antes de comparar', function () {
    $rec = new IndexReconciler('/tmp/inexistente-base');

    // YAML coage [101, 170] pra int; o disco tem '0101','0170' (string). Mesmo conjunto → SEM drift.
    $desired = ['collisions' => ['0101', '0170'], 'total_adrs' => 1, 'unique_numbers' => 1];
    $observed = ['collisions' => [101, 170], 'total_adrs' => 1, 'unique_numbers' => 1, 'design_links_broken' => []];

    $drift = collect($rec->analisar($desired, $observed))->firstWhere('target', 'lifecycle.numbering_collisions');

    expect($drift)->toBeNull(); // 101 ≡ '0101' após normalização
});

// ─── IDEMPOTÊNCIA + cirurgia da cura (tmpfile real) ──────────────────────────

it('cura: reconcile(heal) reescreve só os campos computáveis E é idempotente (2× = mesmo arquivo)', function () {
    $base = sys_get_temp_dir().'/idxrec_'.uniqid();
    $dir = $base.'/memory/decisions';
    @mkdir($dir, 0777, true);

    // ── Monta um disco fixture: 3 ADRs, com colisão real em 0170 (2 arquivos). ──
    foreach (['0101-um.md', '0170-a.md', '0170-b.md'] as $f) {
        file_put_contents($dir.'/'.$f, "# stub\n");
    }
    // total_adrs real = 3 ; unique_numbers real = 2 (0101, 0170) ; colisão real = [0170]

    // ── Frontmatter STALE de propósito + prosa curada à mão que NÃO pode mudar. ──
    $lifecycle = <<<MD
---
title: Index de Lifecycle
type: index
status: aceito
total_adrs: 119
unique_numbers: 116
numbering_collisions: [0101, 0999]  # comentário curado à mão que deve sobreviver
governance_principle: append-only
---

# Corpo curado à mão

Prosa que jamais pode ser tocada pela cura — só o frontmatter computável muda.
MD;
    file_put_contents($dir.'/_INDEX-LIFECYCLE.md', $lifecycle);

    $rec = new IndexReconciler($base);

    // 1ª cura
    $r1 = $rec->reconcile(['heal' => true]);
    $depois1 = (string) file_get_contents($dir.'/_INDEX-LIFECYCLE.md');

    // Curou os 3 healable (collisions + total + unique).
    expect($r1->healedCount)->toBe(3)
        ->and($depois1)->toContain('total_adrs: 3')
        ->and($depois1)->toContain('unique_numbers: 2')
        ->and($depois1)->toContain('numbering_collisions: [0170]')
        // comentário curado à mão PRESERVADO (cirurgia, não reescreve a linha toda):
        ->and($depois1)->toContain('# comentário curado à mão que deve sobreviver')
        // prosa do corpo intacta:
        ->and($depois1)->toContain('Prosa que jamais pode ser tocada pela cura')
        // valores stale SUMIRAM:
        ->and($depois1)->not->toContain('total_adrs: 119')
        ->and($depois1)->not->toContain('0999');

    // 2ª cura — IDEMPOTÊNCIA: já está em sincronia → arquivo byte-a-byte igual + zero drift.
    $r2 = $rec->reconcile(['heal' => true]);
    $depois2 = (string) file_get_contents($dir.'/_INDEX-LIFECYCLE.md');

    expect($depois2)->toBe($depois1)        // arquivo idêntico (idempotente)
        ->and($r2->inSync)->toBeTrue()      // nada mais a curar
        ->and($r2->driftCount)->toBe(0)
        ->and($r2->healedCount)->toBe(0);

    // cleanup
    array_map('unlink', (array) glob($dir.'/*.md'));
    @rmdir($dir);
    @rmdir($base.'/memory');
    @rmdir($base);
});

it('cura: dry_run detecta mas NÃO escreve (arquivo intacto, healed=false)', function () {
    $base = sys_get_temp_dir().'/idxrec_dry_'.uniqid();
    $dir = $base.'/memory/decisions';
    @mkdir($dir, 0777, true);

    file_put_contents($dir.'/0101-um.md', "# stub\n");

    $lifecycle = <<<MD
---
type: index
total_adrs: 119
unique_numbers: 116
numbering_collisions: [0999]
---
corpo
MD;
    file_put_contents($dir.'/_INDEX-LIFECYCLE.md', $lifecycle);
    $antes = (string) file_get_contents($dir.'/_INDEX-LIFECYCLE.md');

    $rec = new IndexReconciler($base);
    $r = $rec->reconcile(['heal' => true, 'dry_run' => true]);

    $depois = (string) file_get_contents($dir.'/_INDEX-LIFECYCLE.md');

    expect($depois)->toBe($antes)             // dry_run NÃO escreve
        ->and($r->driftCount)->toBeGreaterThan(0) // mas detecta o que curaria
        ->and($r->healedCount)->toBe(0)
        ->and(collect($r->drifts)->every(fn (ReconcileDrift $d) => $d->healed === false))->toBeTrue();

    array_map('unlink', (array) glob($dir.'/*.md'));
    @rmdir($dir);
    @rmdir($base.'/memory');
    @rmdir($base);
});

// ─── CURA HONESTA: no-op NÃO finge healed=true (chave ausente / convergência) ─

it('cura HONESTA: chave AUSENTE no frontmatter NÃO marca healed=true (detecta mas healed=false)', function () {
    // Bug-guard [ALTO]: a cura marcava healed=true pra todo drift healable ANTES de saber
    // se a reescrita casou. Regex-replace NÃO consegue inserir uma chave que não existe →
    // o drift tem de ficar healed=false (honesto: detectou mas não curou) e REAPARECER.
    $base = sys_get_temp_dir().'/idxrec_nokey_'.uniqid();
    $dir = $base.'/memory/decisions';
    @mkdir($dir, 0777, true);

    // Disco: 1 ADR → total real=1, unique real=1, zero colisão.
    file_put_contents($dir.'/0101-um.md', "# stub\n");

    // Frontmatter SEM total_adrs / unique_numbers / numbering_collisions (chaves ausentes).
    // Não há linha pra regex casar — a cura tem de no-opar honestamente.
    $lifecycle = <<<MD
---
type: index
status: aceito
governance_principle: append-only
---

# Corpo

Sem os escalares computáveis no frontmatter.
MD;
    file_put_contents($dir.'/_INDEX-LIFECYCLE.md', $lifecycle);
    $antes = (string) file_get_contents($dir.'/_INDEX-LIFECYCLE.md');

    $rec = new IndexReconciler($base);
    $r = $rec->reconcile(['heal' => true]);

    $depois = (string) file_get_contents($dir.'/_INDEX-LIFECYCLE.md');
    $totalDrift = collect($r->drifts)->firstWhere('target', 'lifecycle.total_adrs');
    $uniqueDrift = collect($r->drifts)->firstWhere('target', 'lifecycle.unique_numbers');

    expect($depois)->toBe($antes)               // nada a inserir → arquivo intacto
        ->and($r->healedCount)->toBe(0)         // NÃO finge cura
        ->and($totalDrift)->not->toBeNull()
        ->and($totalDrift->healable)->toBeTrue()
        ->and($totalDrift->healed)->toBeFalse() // detectou (ausente) mas NÃO curou — honesto
        ->and($uniqueDrift)->not->toBeNull()
        ->and($uniqueDrift->healed)->toBeFalse();

    // Convergência: drift reaparece na 2ª run (não some — pois nunca foi curado de verdade).
    $r2 = $rec->reconcile(['heal' => true]);
    expect($r2->driftCount)->toBe($r->driftCount) // mesmo drift persiste
        ->and($r2->healedCount)->toBe(0);

    array_map('unlink', (array) glob($dir.'/*.md'));
    @rmdir($dir);
    @rmdir($base.'/memory');
    @rmdir($base);
});

it('cura HONESTA: numbering_collisions em BLOCK list YAML NÃO marca healed=true (regex inline no-opa)', function () {
    // Bug-guard [ALTO]: numbering_collisions escrito como BLOCK list (- 0999) não casa o
    // regex inline `[...]` → reescreverColisoes no-opa. Antes virava healed=true mentiroso.
    $base = sys_get_temp_dir().'/idxrec_block_'.uniqid();
    $dir = $base.'/memory/decisions';
    @mkdir($dir, 0777, true);

    // Disco: colisão real em 0170 (2 arquivos).
    foreach (['0170-a.md', '0170-b.md'] as $f) {
        file_put_contents($dir.'/'.$f, "# stub\n");
    }

    // numbering_collisions como BLOCK list (não inline [...]) + escalares ausentes.
    $lifecycle = <<<MD
---
type: index
numbering_collisions:
  - 0999
---
corpo
MD;
    file_put_contents($dir.'/_INDEX-LIFECYCLE.md', $lifecycle);
    $antes = (string) file_get_contents($dir.'/_INDEX-LIFECYCLE.md');

    $rec = new IndexReconciler($base);
    $r = $rec->reconcile(['heal' => true]);

    $depois = (string) file_get_contents($dir.'/_INDEX-LIFECYCLE.md');
    $colDrift = collect($r->drifts)->firstWhere('target', 'lifecycle.numbering_collisions');

    expect($depois)->toBe($antes)                // BLOCK list intocada (regex inline não casa)
        ->and($colDrift)->not->toBeNull()
        ->and($colDrift->healable)->toBeTrue()
        ->and($colDrift->healed)->toBeFalse();   // detectou divergência mas NÃO curou — honesto

    array_map('unlink', (array) glob($dir.'/*.md'));
    @rmdir($dir);
    @rmdir($base.'/memory');
    @rmdir($base);
});

it('cura HONESTA: convergência REAL — 2ª run não tem mais drift nos campos curados', function () {
    // Idempotência verdadeira (não só "arquivo igual"): o que foi curado SOME da detecção
    // na 2ª run. Antes o loop podia reaparecer (healed mentiroso) — aqui provamos convergência.
    $base = sys_get_temp_dir().'/idxrec_conv_'.uniqid();
    $dir = $base.'/memory/decisions';
    @mkdir($dir, 0777, true);

    foreach (['0101-um.md', '0170-a.md', '0170-b.md'] as $f) {
        file_put_contents($dir.'/'.$f, "# stub\n");
    }
    // real: total=3, unique=2, colisão=[0170]

    $lifecycle = <<<MD
---
type: index
total_adrs: 119
unique_numbers: 116
numbering_collisions: [0999]
---
corpo
MD;
    file_put_contents($dir.'/_INDEX-LIFECYCLE.md', $lifecycle);

    $rec = new IndexReconciler($base);

    $r1 = $rec->reconcile(['heal' => true]);
    expect($r1->healedCount)->toBe(3); // 3 campos realmente reescritos

    // 2ª run: tudo convergiu → ZERO drift (não reaparece).
    $r2 = $rec->reconcile(['heal' => true]);
    expect($r2->inSync)->toBeTrue()
        ->and($r2->driftCount)->toBe(0)
        ->and($r2->healedCount)->toBe(0);

    array_map('unlink', (array) glob($dir.'/*.md'));
    @rmdir($dir);
    @rmdir($base.'/memory');
    @rmdir($base);
});

it('cura CRLF: arquivo CRLF cura os escalares total_adrs/unique_numbers (regex CRLF-tolerante)', function () {
    // Bug-guard [ALTO]: o regex antigo terminava em `\d+(\h*(?:#.*)?)$` e `\r` não está em
    // `\h` → em arquivos CRLF os escalares NUNCA curavam (mas eram contados como healed).
    // Aqui o tmpfile é CRLF: a cura tem de (a) reescrever de fato e (b) preservar o `\r`.
    $base = sys_get_temp_dir().'/idxrec_crlf_'.uniqid();
    $dir = $base.'/memory/decisions';
    @mkdir($dir, 0777, true);

    foreach (['0101-um.md', '0170-a.md', '0170-b.md'] as $f) {
        file_put_contents($dir.'/'.$f, "# stub\n");
    }
    // real: total=3, unique=2, colisão=[0170]

    // Frontmatter com terminadores CRLF (\r\n) em TODAS as linhas + comentário inline.
    $linhas = [
        '---',
        'type: index',
        'total_adrs: 119',
        'unique_numbers: 116  # nota curada à mão',
        'numbering_collisions: [0999]',
        '---',
        'corpo',
    ];
    $lifecycle = implode("\r\n", $linhas)."\r\n";
    file_put_contents($dir.'/_INDEX-LIFECYCLE.md', $lifecycle);

    $rec = new IndexReconciler($base);
    $r = $rec->reconcile(['heal' => true]);

    $depois = (string) file_get_contents($dir.'/_INDEX-LIFECYCLE.md');

    expect($r->healedCount)->toBe(3)                              // os 3 healable curaram DE VERDADE em CRLF
        ->and($depois)->toContain("total_adrs: 3\r\n")           // curou E preservou o CRLF
        ->and($depois)->toContain('unique_numbers: 2')
        ->and($depois)->toContain("# nota curada à mão\r\n")     // comentário + CRLF preservados
        ->and($depois)->toContain('numbering_collisions: [0170]')
        ->and($depois)->not->toContain('total_adrs: 119')
        ->and($depois)->not->toContain("\r\r")                    // não duplicou CR
        ->and(substr_count($depois, "\n"))->toBe(substr_count($depois, "\r")); // segue 100% CRLF (não virou LF)

    // Convergência REAL em CRLF: 2ª run sem drift.
    $r2 = $rec->reconcile(['heal' => true]);
    $depois2 = (string) file_get_contents($dir.'/_INDEX-LIFECYCLE.md');
    expect($depois2)->toBe($depois)        // idempotente byte-a-byte
        ->and($r2->inSync)->toBeTrue()
        ->and($r2->driftCount)->toBe(0);

    array_map('unlink', (array) glob($dir.'/*.md'));
    @rmdir($dir);
    @rmdir($base.'/memory');
    @rmdir($base);
});

it('reconcile: name e tags canônicos', function () {
    $rec = new IndexReconciler('/tmp/x');

    expect($rec->name())->toBe('index')
        ->and($rec->tags())->toContain('index')
        ->and($rec->tags())->toContain('tier_0');
});
