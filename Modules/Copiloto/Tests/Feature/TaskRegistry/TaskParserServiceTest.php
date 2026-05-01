<?php

declare(strict_types=1);

use Modules\Copiloto\Services\TaskRegistry\TaskParserService;

uses(Tests\TestCase::class)->in(__DIR__);

/**
 * TaskRegistry Fase 0 — golden tests do parser SPEC.
 * NÃO testa DB sync (precisaria migration); testa parsing puro.
 */

beforeEach(function () {
    $this->tmp = sys_get_temp_dir() . '/test-task-' . uniqid();
    mkdir($this->tmp);
    $this->svc = new TaskParserService();
});

afterEach(function () {
    if (isset($this->tmp) && is_dir($this->tmp)) {
        array_map('unlink', glob($this->tmp . '/*'));
        rmdir($this->tmp);
    }
});

function escreverSpec(string $tmp, string $conteudo): string
{
    $path = $tmp . '/SPEC.md';
    file_put_contents($path, $conteudo);
    return $path;
}

it('extrai 1 US com frontmatter completo', function () {
    $spec = escreverSpec($this->tmp, <<<MD
    # NFSe SPEC

    ### US-NFSE-001 · Pesquisa fiscal Tubarão

    > owner: eliana · sprint: A · priority: p0 · estimate: 8h · status: todo
    > blocked_by: —

    - [ ] Confirmar SN-NFSe vs ABRASF
    - [ ] Cadastrar conta provider

    MD);

    $cand = $this->svc->parseSpec($spec, 'NFSe');
    expect($cand)->toHaveCount(1);
    $t = $cand->first();
    expect($t['task_id'])->toBe('US-NFSE-001')
        ->and($t['title'])->toBe('Pesquisa fiscal Tubarão')
        ->and($t['module'])->toBe('NFSe')
        ->and($t['owner'])->toBe('eliana')
        ->and($t['sprint'])->toBe('A')
        ->and($t['priority'])->toBe('p0')
        ->and($t['estimate_h'])->toBe(8.0)
        ->and($t['status'])->toBe('todo')
        ->and($t['blocked_by'])->toBeNull()
        ->and($t['source_path'])->toBe('memory/requisitos/NFSe/SPEC.md#US-NFSE-001')
        ->and($t['description'])->toContain('Confirmar SN-NFSe');
});

it('extrai múltiplas US do mesmo arquivo', function () {
    $spec = escreverSpec($this->tmp, <<<MD
    ### US-NFSE-001 · Primeira

    > owner: eliana · status: todo

    - tarefa 1

    ### US-NFSE-002 · Segunda

    > owner: wagner · sprint: B · priority: p1 · status: doing

    - tarefa 2

    ### US-NFSE-003 · Terceira

    > owner: felipe

    - tarefa 3
    MD);

    $cand = $this->svc->parseSpec($spec, 'NFSe');
    expect($cand)->toHaveCount(3)
        ->and($cand->pluck('task_id')->all())->toBe(['US-NFSE-001', 'US-NFSE-002', 'US-NFSE-003'])
        ->and($cand[1]['priority'])->toBe('p1')
        ->and($cand[1]['sprint'])->toBe('B')
        ->and($cand[1]['status'])->toBe('doing')
        ->and($cand[2]['priority'])->toBe('p2'); // default
});

it('aceita blocked_by com múltiplas tasks', function () {
    $spec = escreverSpec($this->tmp, <<<MD
    ### US-NFSE-005 · Depende de várias

    > owner: eliana
    > blocked_by: US-NFSE-001, US-NFSE-002

    - tarefa
    MD);

    $cand = $this->svc->parseSpec($spec, 'NFSe');
    expect($cand->first()['blocked_by'])->toBe(['US-NFSE-001', 'US-NFSE-002']);
});

it('ignora valores —/- como vazios', function () {
    $spec = escreverSpec($this->tmp, <<<MD
    ### US-X-001 · Teste

    > owner: — · sprint: - · priority: p0 · status: todo

    desc
    MD);

    $cand = $this->svc->parseSpec($spec, 'X');
    expect($cand->first()['owner'])->toBeNull()
        ->and($cand->first()['sprint'])->toBeNull()
        ->and($cand->first()['priority'])->toBe('p0');
});

it('rejeita prioridade inválida e usa default', function () {
    $spec = escreverSpec($this->tmp, <<<MD
    ### US-X-002 · Teste

    > owner: eliana · priority: super-hyper-urgente

    desc
    MD);

    $cand = $this->svc->parseSpec($spec, 'X');
    expect($cand->first()['priority'])->toBe('p2'); // default
});

it('aceita status alternativo "doing"', function () {
    $spec = escreverSpec($this->tmp, <<<MD
    ### US-X-003 · Em progresso

    > owner: wagner · status: doing

    desc
    MD);

    $cand = $this->svc->parseSpec($spec, 'X');
    expect($cand->first()['status'])->toBe('doing');
});

it('retorna empty se SPEC nao tem nenhuma US', function () {
    $spec = escreverSpec($this->tmp, "# Sem tasks aqui\n\nSó documentação.");
    $cand = $this->svc->parseSpec($spec, 'X');
    expect($cand)->toHaveCount(0);
});

it('retorna empty se path nao existe', function () {
    $cand = $this->svc->parseSpec('/tmp/nao-existe-x99.md', 'X');
    expect($cand)->toHaveCount(0);
});
