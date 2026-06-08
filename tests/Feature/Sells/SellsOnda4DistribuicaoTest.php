<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-COWORK-R4-DISTRIBUICAO Onda 4 — estrutura dos 3 componentes
 * (SaleTranscriptPDF + SalePresentationMode + SaleMessagePreview) integrados no
 * SaleSheet drawer + CSS @media print.
 *
 * Cobertura estrutural via file_get_contents (Pest browser cobre interativo
 * quando estabilizar). Foca em garantir que:
 *  - 3 componentes existem nos paths canônicos
 *  - SaleSheet importa todos 3 + plug-points (botões footer + overlays + msg)
 *  - CSS .sells-cowork-distribuicao scoped + @media print + importado em inertia.css
 *
 * Refs:
 *  - resources/js/Pages/Sells/_components/{SaleTranscriptPDF,SalePresentationMode,SaleMessagePreview}.tsx
 *  - resources/css/sells-cowork-distribuicao.css
 *  - memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md F3 R4
 */

const R4_SHEET_PATH = 'resources/js/Pages/Sells/_components/SaleSheet.tsx';
const R4_TRANSCRIPT_PATH = 'resources/js/Pages/Sells/_components/SaleTranscriptPDF.tsx';
const R4_PRESENT_PATH = 'resources/js/Pages/Sells/_components/SalePresentationMode.tsx';
const R4_MESSAGE_PATH = 'resources/js/Pages/Sells/_components/SaleMessagePreview.tsx';
const R4_CSS_PATH = 'resources/css/sells-cowork-distribuicao.css';
const R4_INERTIA_CSS_PATH = 'resources/css/inertia.css';

function r4Read(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Componentes existem ──────────────────────────────────────────────

it('SaleTranscriptPDF component existe', function () {
    expect(file_exists(base_path(R4_TRANSCRIPT_PATH)))->toBeTrue();
});

it('SalePresentationMode component existe', function () {
    expect(file_exists(base_path(R4_PRESENT_PATH)))->toBeTrue();
});

it('SaleMessagePreview component existe', function () {
    expect(file_exists(base_path(R4_MESSAGE_PATH)))->toBeTrue();
});

it('CSS sells-cowork-distribuicao.css existe', function () {
    expect(file_exists(base_path(R4_CSS_PATH)))->toBeTrue();
});

// ─── SaleTranscriptPDF ────────────────────────────────────────────────

it('SaleTranscriptPDF exporta default + tipo TranscriptVenda', function () {
    $source = r4Read(R4_TRANSCRIPT_PATH);
    expect($source)
        ->toContain('export default function SaleTranscriptPDF(')
        ->toContain('interface TranscriptVenda')
        ->toContain('export type { TranscriptVenda }');
});

it('SaleTranscriptPDF dispara window.print() + Ctrl+P shortcut + Esc', function () {
    $source = r4Read(R4_TRANSCRIPT_PATH);
    expect($source)
        ->toContain('window.print()')
        ->toContain("e.key === 'p'")
        ->toContain("e.key === 'Escape'")
        ->toContain('e.metaKey || e.ctrlKey');
});

it('SaleTranscriptPDF formata chave NFe 4-em-4 + status label paid/partial/due', function () {
    $source = r4Read(R4_TRANSCRIPT_PATH);
    expect($source)
        ->toContain("paid: 'PAGA'")
        ->toContain("partial: 'PARCIAL'")
        ->toContain("due: 'PENDENTE'")
        ->toContain("k.replace(/(\\d{4})/g, '\$1 ')");
});

it('SaleTranscriptPDF renderiza assinaturas cliente + atendente', function () {
    $source = r4Read(R4_TRANSCRIPT_PATH);
    expect($source)
        ->toContain('vd-tr-sigs')
        ->toContain('vd-tr-sig-line')
        ->toContain('Cliente')
        ->toContain('Atendente');
});

// ─── SalePresentationMode ────────────────────────────────────────────

it('SalePresentationMode exporta default + tipo PresentationVenda', function () {
    $source = r4Read(R4_PRESENT_PATH);
    expect($source)
        ->toContain('export default function SalePresentationMode(')
        ->toContain('interface PresentationVenda')
        ->toContain('export type { PresentationVenda }');
});

it('SalePresentationMode tem 4 slides (intro/itens/valor/next)', function () {
    $source = r4Read(R4_PRESENT_PATH);
    expect($source)
        ->toContain('vd-slide-intro')
        ->toContain('vd-slide-items')
        ->toContain('vd-slide-value')
        ->toContain('vd-slide-next')
        ->toContain('const total = 4');
});

it('SalePresentationMode keyboard nav: ArrowLeft/Right + Esc + Space', function () {
    $source = r4Read(R4_PRESENT_PATH);
    expect($source)
        ->toContain("e.key === 'Escape'")
        ->toContain("e.key === 'ArrowRight'")
        ->toContain("e.key === 'ArrowLeft'")
        ->toContain("e.key === ' '");
});

it('SalePresentationMode renderiza dots + setas com disabled nas extremidades', function () {
    $source = r4Read(R4_PRESENT_PATH);
    expect($source)
        ->toContain('vd-presentation-dot')
        ->toContain('vd-presentation-arr')
        ->toContain('disabled={slide === 0}')
        ->toContain('disabled={slide === total - 1}');
});

// ─── SaleMessagePreview ──────────────────────────────────────────────

it('SaleMessagePreview tem 3 templates (confirm/pickup/overdue)', function () {
    $source = r4Read(R4_MESSAGE_PATH);
    expect($source)
        ->toContain("id: 'confirm'")
        ->toContain("id: 'pickup'")
        ->toContain("id: 'overdue'")
        ->toContain("'Confirmação'")
        ->toContain('Retirada / Entrega')
        ->toContain('Cobrança amigável');
});

it('SaleMessagePreview substitui 9 variáveis canônicas', function () {
    $source = r4Read(R4_MESSAGE_PATH);
    expect($source)
        ->toContain('cliente: venda.customer_name')
        ->toContain('id: venda.invoice_no')
        ->toContain('total: fmtBRL(venda.final_total)')
        ->toContain('forma: venda.payment_method')
        ->toContain('seller: venda.seller_name')
        ->toContain('prazo: venda.pay_term_days')
        ->toContain('vencimento: formatDateBR(venda.due_date)')
        ->toContain('status: statusLabel[venda.payment_status]')
        ->toContain('data: formatDateBR(venda.transaction_date)');
});

it('SaleMessagePreview tem botão copiar (navigator.clipboard) + WhatsApp deep-link', function () {
    $source = r4Read(R4_MESSAGE_PATH);
    expect($source)
        ->toContain('navigator.clipboard.writeText(body)')
        ->toContain('https://wa.me/55')
        ->toContain('encodeURIComponent(text)');
});

it('SaleMessagePreview status label PT-BR (paid/partial/due)', function () {
    $source = r4Read(R4_MESSAGE_PATH);
    expect($source)
        ->toContain("paid: 'pagamento confirmado'")
        ->toContain("partial: 'pagamento parcial'")
        ->toContain("due: 'pagamento pendente'");
});

it('SaleMessagePreview exporta tipo MessageVenda', function () {
    $source = r4Read(R4_MESSAGE_PATH);
    expect($source)
        ->toContain('interface MessageVenda')
        ->toContain('export type { MessageVenda }');
});

// ─── Integração no SaleSheet ─────────────────────────────────────────

it('SaleSheet importa os 3 componentes R4', function () {
    $source = r4Read(R4_SHEET_PATH);
    expect($source)
        ->toContain("import SaleTranscriptPDF from './SaleTranscriptPDF'")
        ->toContain("import SalePresentationMode from './SalePresentationMode'")
        ->toContain("import SaleMessagePreview from './SaleMessagePreview'");
});

it('SaleSheet tem state transcriptOpen + presentationOpen', function () {
    $source = r4Read(R4_SHEET_PATH);
    expect($source)
        ->toContain('const [transcriptOpen, setTranscriptOpen] = useState(false)')
        ->toContain('const [presentationOpen, setPresentationOpen] = useState(false)');
});

it('SaleSheet renderiza botões Transcript + Apresentar no footer', function () {
    $source = r4Read(R4_SHEET_PATH);
    expect($source)
        ->toContain('onClick={() => setTranscriptOpen(true)}')
        ->toContain('onClick={() => setPresentationOpen(true)}')
        ->toContain('Transcript')
        ->toContain('Apresentar');
});

it('SaleSheet renderiza SaleMessagePreview dentro do drawer (Section Mensagem WhatsApp)', function () {
    $source = r4Read(R4_SHEET_PATH);
    expect($source)
        ->toContain('<SaleMessagePreview')
        ->toContain('Mensagem WhatsApp')
        ->toContain('customer_mobile: data.customer?.mobile');
});

it('SaleSheet renderiza overlays SaleTranscriptPDF + SalePresentationMode', function () {
    $source = r4Read(R4_SHEET_PATH);
    expect($source)
        ->toContain('<SaleTranscriptPDF')
        ->toContain('<SalePresentationMode')
        ->toContain('open={transcriptOpen}')
        ->toContain('open={presentationOpen}')
        ->toContain('sells-cowork-distribuicao');
});

// ─── CSS scoped + @media print + import ──────────────────────────────

it('CSS distribuicao define classes principais transcript + presentation + message', function () {
    $source = r4Read(R4_CSS_PATH);
    expect($source)
        ->toContain('.vd-transcript-bd')
        ->toContain('.vd-transcript-page')
        ->toContain('.vd-presentation-bd')
        ->toContain('.vd-presentation-stage')
        ->toContain('.vd-msg')
        ->toContain('.vd-msg-bubble');
});

it('CSS distribuicao tem @media print rules pra A4 imprimível', function () {
    $source = r4Read(R4_CSS_PATH);
    expect($source)
        ->toContain('@media print')
        ->toContain('.vd-transcript-toolbar { display: none')
        ->toContain('body > *:not(.vd-transcript-bd) { display: none');
});

it('CSS distribuicao cobre 3 templates msg tabs (confirm/pickup/overdue)', function () {
    $source = r4Read(R4_CSS_PATH);
    expect($source)
        ->toContain('.vd-msg-tabs')
        ->toContain('.vd-msg-tab')
        ->toContain('.vd-msg-vars')
        ->toContain('.vd-msg-actions');
});

it('inertia.css importa sells-cowork-distribuicao.css', function () {
    $source = r4Read(R4_INERTIA_CSS_PATH);
    expect($source)
        ->toContain('@import "./sells-cowork-distribuicao.css"');
});
