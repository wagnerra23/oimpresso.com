<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda 9 KB-9.75 — FinMonthResume (narrativa executiva do mês).
 *
 * Substitui alert() do botão "✦ Resumir mês" por dialog modal com narrativa
 * compute-based + botão "Copiar pro WhatsApp". Fase 2 (futura) plugará
 * JanaService LLM quando disponível na main.
 *
 * Cobre:
 *   - FinMonthResume.tsx: buildMonthResume + resumeToPlainText exports
 *   - 5 blocks canônicos: Visão geral / Top in / Top out / Categorias / Alertas / Recomendação
 *   - Dialog modal: header + body + footer com botões copiar/fechar
 *   - CSS .fin-resume-* (backdrop, dialog, blocks, footer)
 *   - Wire-up Index.tsx: state resumoOpen + alert removido + palette entry
 *
 * Multi-tenant Tier 0 preservado (zero backend, zero LLM, pure compute).
 */

const FIN_BASE_9 = __DIR__ . '/../../../../resources/js/Pages/Financeiro/Unificado';
const FIN_CSS_9 = __DIR__ . '/../../../../resources/css/fin-output.css';

describe('Onda 9 — FinMonthResume compute helpers', function () {
    it('FinMonthResume exporta buildMonthResume + resumeToPlainText', function () {
        $src = file_get_contents(FIN_BASE_9 . '/_components/FinMonthResume.tsx');
        expect($src)->toContain('export function buildMonthResume');
        expect($src)->toContain('export function resumeToPlainText');
        expect($src)->toContain('export function FinMonthResumeDialog');
    });

    it('5+ blocks canônicos com títulos exec (Visão geral / Top in / Top out / Categorias / Alertas / Recomendação)', function () {
        $src = file_get_contents(FIN_BASE_9 . '/_components/FinMonthResume.tsx');
        expect($src)->toContain('Visão geral');
        expect($src)->toContain('Top contrapartes (entradas)');
        expect($src)->toContain('Top contrapartes (saídas)');
        expect($src)->toContain('Categorias com maior peso');
        expect($src)->toContain('Alertas');
        expect($src)->toContain('Recomendação');
    });

    it('Computa health icone + tendencia + atrasados + vencendo', function () {
        $src = file_get_contents(FIN_BASE_9 . '/_components/FinMonthResume.tsx');
        expect($src)->toContain('🟢');
        expect($src)->toContain('🔴');
        expect($src)->toContain("status === 'atrasado'");
        expect($src)->toContain("status === 'vencendo'");
    });
});

describe('Onda 9 — FinMonthResumeDialog rendering', function () {
    it('Dialog inclui header + body + footer canon', function () {
        $src = file_get_contents(FIN_BASE_9 . '/_components/FinMonthResume.tsx');
        expect($src)->toContain('fin-resume-dialog');
        expect($src)->toContain('fin-resume-h');
        expect($src)->toContain('fin-resume-body');
        expect($src)->toContain('fin-resume-f');
    });

    it('Atalho Esc fecha + clipboard.writeText pra copiar', function () {
        $src = file_get_contents(FIN_BASE_9 . '/_components/FinMonthResume.tsx');
        expect($src)->toContain("e.key === 'Escape'");
        expect($src)->toContain('navigator.clipboard.writeText');
    });

    it('Botão Copiar formata header em bold WhatsApp + texto plain', function () {
        $src = file_get_contents(FIN_BASE_9 . '/_components/FinMonthResume.tsx');
        expect($src)->toContain('📋 Copiar pro WhatsApp');
        expect($src)->toContain('*Resumo financeiro');
    });

    it('Fase 1 note: JanaService LLM plugará Fase 2', function () {
        $src = file_get_contents(FIN_BASE_9 . '/_components/FinMonthResume.tsx');
        // Comentário canon sinalizando o roadmap
        expect($src)->toContain('Fase 2');
        expect($src)->toContain('JanaService');
    });
});

describe('Onda 9 — CSS escopado', function () {
    it('Tokens .fin-resume-* mounted em fin-output.css', function () {
        $css = file_get_contents(FIN_CSS_9);
        expect($css)->toContain('.fin-resume-backdrop');
        expect($css)->toContain('.fin-resume-dialog');
        expect($css)->toContain('.fin-resume-h');
        expect($css)->toContain('.fin-resume-body');
        expect($css)->toContain('.fin-resume-block');
        expect($css)->toContain('.fin-resume-f');
        expect($css)->toContain('.fin-resume-btn');
    });

    it('Tom IA roxo (oklch hue 280) + estado "copied" verde', function () {
        $css = file_get_contents(FIN_CSS_9);
        // Hue 280 IA accent (consistent com .fin-btn-ai)
        expect($css)->toContain('oklch(0.30 0.10 280)');
        // Estado copied verde (feedback ok)
        expect($css)->toContain('.fin-resume-btn.primary.copied');
        expect($css)->toContain('oklch(0.42 0.18 145)');
    });
});

describe('Onda 9 — wire-up Index.tsx', function () {
    it('Index.tsx importa FinMonthResumeDialog', function () {
        $src = file_get_contents(FIN_BASE_9 . '/Index.tsx');
        expect($src)->toContain("from './_components/FinMonthResume'");
        expect($src)->toContain('FinMonthResumeDialog');
    });

    it('Index.tsx instancia resumoOpen state', function () {
        $src = file_get_contents(FIN_BASE_9 . '/Index.tsx');
        expect($src)->toContain('const [resumoOpen, setResumoOpen]');
    });

    it('Botão ✦ Resumir mês agora abre dialog (alert removido)', function () {
        $src = file_get_contents(FIN_BASE_9 . '/Index.tsx');
        expect($src)->toContain('onClick={() => setResumoOpen(true)}');
        // alert original removido
        expect($src)->not->toContain("alert('Resumir mês:");
    });

    it('Dialog mount com props canônicas', function () {
        $src = file_get_contents(FIN_BASE_9 . '/Index.tsx');
        expect($src)->toContain('<FinMonthResumeDialog');
        expect($src)->toContain('open={resumoOpen}');
        expect($src)->toContain('kpis={kpis}');
        expect($src)->toContain('lancamentos={lancamentos}');
    });

    it('Command palette inclui entrada "Resumir mês"', function () {
        $src = file_get_contents(FIN_BASE_9 . '/Index.tsx');
        expect($src)->toContain('Resumir mês (narrativa exec)');
    });
});
