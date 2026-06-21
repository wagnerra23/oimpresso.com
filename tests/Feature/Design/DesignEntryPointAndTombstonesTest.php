<?php

declare(strict_types=1);

/**
 * @group legacy-quarantine
 * quarantine-reason: assert estático de canon-source (links DESIGN.md + lápides frontmatter) contra fonte-da-verdade móvel — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

/**
 * Garante que o sistema de design REVITALIZADO (2026-06-06) fica HONESTO pra sempre.
 *
 * Origem: a porta da frente (`DESIGN.md`) apontava pro CEMITÉRIO — `ui_kits/cowork-2026-04-27/`
 * (virou `_BACKUP-NAO-USAR-`) + UI-0010 (morta). Wagner pegou no olho ("isso vai estar no
 * DESIGN.md?"). Este teste TERIA pego antes do merge. *"Garantir sempre estar funcionando."*
 *
 *   (a) HARD — todo link markdown LOCAL do DESIGN.md (a PORTA) resolve (arquivo existe).
 *               Pega pointer pra arquivo movido/renomeado/inexistente (o bug do _BACKUP).
 *   (b) HARD — toda LÁPIDE de design (frontmatter `status: superseded|deprecated`) tem
 *               `superseded_by` — não morre sem apontar o sucessor (append-only honesto).
 *
 * Skip gracioso quando filesystem do repo não está acessível (CI ephemeral) — padrão do
 * DesignIndexSingleSourceTest irmão.
 *
 * @see DESIGN.md · memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md (§3e Lápides)
 * @see memory/decisions/_INDEX-LIFECYCLE.md (estados de lápide · append-only)
 */

const DESIGN_EP_ROOT = __DIR__ . '/../../..';
const DESIGN_EP_MD = 'DESIGN.md';
const DESIGN_EP_DS_DIR = 'memory/requisitos/_DesignSystem';

beforeEach(function () {
    if (! is_dir(DESIGN_EP_ROOT) || ! is_file(DESIGN_EP_ROOT . '/' . DESIGN_EP_MD)) {
        $this->markTestSkipped('Filesystem do repo / DESIGN.md não acessível (CI ephemeral).');
    }
});

it('(a) HARD: todo link LOCAL do DESIGN.md (porta da frente) resolve — não aponta pro cemitério', function () {
    $src = file_get_contents(DESIGN_EP_ROOT . '/' . DESIGN_EP_MD);
    preg_match_all('/\]\(([^)]+)\)/', $src, $m);

    $quebrados = [];
    foreach ($m[1] as $link) {
        if (str_starts_with($link, 'http') || str_starts_with($link, '#') || str_starts_with($link, 'mailto:')) {
            continue;
        }
        $rel = explode(' ', explode('#', $link)[0])[0];
        if ($rel === '') {
            continue;
        }
        if (! file_exists(DESIGN_EP_ROOT . '/' . $rel)) {
            $quebrados[] = $link;
        }
    }

    expect($quebrados)->toBe([], 'DESIGN.md aponta pra arquivo inexistente (movido/renomeado/cemitério): ' . implode(' · ', $quebrados));
});

it('(b) HARD: toda lápide de design (superseded/deprecated) aponta superseded_by — não morre órfã', function () {
    $dir = DESIGN_EP_ROOT . '/' . DESIGN_EP_DS_DIR;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    $orfas = [];
    foreach ($iter as $file) {
        if ($file->getExtension() !== 'md') {
            continue;
        }
        $src = file_get_contents($file->getPathname());
        // só docs com frontmatter YAML (--- ... ---); ADRs UI usam prosa (convenção à parte)
        if (! preg_match('/^---\s*\n(.*?)\n---/s', $src, $fm)) {
            continue;
        }
        $front = $fm[1];
        if (! preg_match('/^\s*status:\s*["\']?(superseded|deprecated)\b/mi', $front)) {
            continue;
        }
        if (! preg_match('/^\s*superseded_by:/mi', $front)) {
            $orfas[] = str_replace($dir . '/', '', $file->getPathname());
        }
    }

    expect($orfas)->toBe([], 'Lápide sem superseded_by (morreu sem apontar sucessor — viola append-only honesto): ' . implode(' · ', $orfas));
});
