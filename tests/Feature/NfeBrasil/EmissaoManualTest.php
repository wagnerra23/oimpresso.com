<?php

declare(strict_types=1);

/**
 * @group legacy-quarantine
 * quarantine-reason: assert estático de canon-source (.tsx/controller fiscal + rotas) contra fonte-da-verdade móvel — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

/**
 * Pest test estrutural — US-NFE-MANUAL endpoints + UI fiscal.
 *
 * Cobre:
 *   1. NfeEmissaoController existe + 4 métodos (emitir, reenviar, danfePdf, listar)
 *   2. Rotas registradas em Modules/NfeBrasil/Routes/web.php
 *   3. SellController.inertiaList retorna fiscal_status + fiscal_modelo
 *   4. Hook useEmissoesPorTransaction tipa Emissao corretamente
 *   5. FiscalSection.tsx pattern Cockpit V2 (cores semânticas, text-[10px] uppercase)
 *   6. Sells/Index.tsx tem coluna Fiscal + FiscalCell component
 */

const NFE_CONTROLLER_PATH = 'Modules/NfeBrasil/Http/Controllers/NfeEmissaoController.php';
const NFE_ROUTES_PATH = 'Modules/NfeBrasil/Routes/web.php';
const FISCAL_SECTION_PATH = 'resources/js/Pages/Sells/_components/FiscalSection.tsx';
const FISCAL_HOOK_PATH = 'resources/js/Hooks/useEmissoesPorTransaction.ts';

// ─── Backend NfeEmissaoController ────────────────────────────────────────────

it('NfeEmissaoController existe', function () {
    expect(file_exists(base_path(NFE_CONTROLLER_PATH)))->toBeTrue();
});

it('NfeEmissaoController tem 4 métodos canônicos (emitir/reenviarEmail/danfePdf/listar)', function () {
    $source = file_get_contents(base_path(NFE_CONTROLLER_PATH));
    expect($source)->toMatch('/public function emitir\\(/');
    expect($source)->toMatch('/public function reenviarEmail\\(/');
    expect($source)->toMatch('/public function danfePdf\\(/');
    expect($source)->toMatch('/public function listar\\(/');
});

it('NfeEmissaoController emitir aceita modelo 55 OR 65 via body', function () {
    $source = file_get_contents(base_path(NFE_CONTROLLER_PATH));
    expect($source)->toContain("input('modelo'");
    expect($source)->toContain("['55', '65']");
    expect($source)->toContain('modelo_invalido');
});

it('NfeEmissaoController emitir tem multi-tenant scope (business_id) — Tier 0 ADR 0093', function () {
    $source = file_get_contents(base_path(NFE_CONTROLLER_PATH));
    // Multiple where('business_id', ...) — pelo menos 3 (emitir + listar + reenviar + danfePdf).
    $matches = preg_match_all("/where\\(['\"]business_id['\"],\\s*\\\$businessId/", $source);
    expect($matches)->toBeGreaterThanOrEqual(3);
    expect($source)->toContain("session()->get('business.id'");
});

it('NfeEmissaoController emitir tem idempotência (já autorizada → retorna a existente)', function () {
    $source = file_get_contents(base_path(NFE_CONTROLLER_PATH));
    expect($source)->toContain('Idempotência respeitada');
});

it('NfeEmissaoController reenviarEmail valida status=autorizada antes de dispatch', function () {
    $source = file_get_contents(base_path(NFE_CONTROLLER_PATH));
    expect($source)->toContain('emissao_nao_autorizada');
});

it('NfeEmissaoController reenviarEmail dispatcha listener correto por modelo (NFCe vs NFe)', function () {
    $source = file_get_contents(base_path(NFE_CONTROLLER_PATH));
    expect($source)->toContain('EnviarDanfeNFCePorEmail');
    expect($source)->toContain('EnviarDanfePorEmail');
    expect($source)->toContain('NFCeAutorizada');
    expect($source)->toContain('NFeAutorizada');
});

it('NfeEmissaoController danfePdf usa lazy generation (DanfeService::lerOuGerar)', function () {
    $source = file_get_contents(base_path(NFE_CONTROLLER_PATH));
    expect($source)->toContain('lerOuGerar');
    expect($source)->toContain('Content-Type');
    expect($source)->toContain('application/pdf');
});

it('NfeEmissaoController listar retorna NFC-e + NFe ordenados por modelo', function () {
    $source = file_get_contents(base_path(NFE_CONTROLLER_PATH));
    expect($source)->toMatch('/orderBy\\([\'"]modelo[\'"]/');
    expect($source)->toContain('emissoes');
});

// ─── Rotas registradas ───────────────────────────────────────────────────────

it('Rota POST /nfe-brasil/transactions/{tx}/emitir registrada', function () {
    $routes = file_get_contents(base_path(NFE_ROUTES_PATH));
    expect($routes)->toMatch("/Route::post\\([\\s\\S]*?transactions\\/\\{tx\\}\\/emitir[\\s\\S]*?NfeEmissaoController/");
});

it('Rota POST /nfe-brasil/emissoes/{id}/reenviar-email registrada', function () {
    $routes = file_get_contents(base_path(NFE_ROUTES_PATH));
    expect($routes)->toContain('reenviar-email');
    expect($routes)->toContain("'reenviarEmail'");
});

it('Rota GET /nfe-brasil/emissoes/{id}/danfe-pdf registrada', function () {
    $routes = file_get_contents(base_path(NFE_ROUTES_PATH));
    expect($routes)->toContain('danfe-pdf');
    expect($routes)->toContain("'danfePdf'");
});

it('Rota GET /nfe-brasil/api/transactions/{tx}/emissoes registrada (lista)', function () {
    $routes = file_get_contents(base_path(NFE_ROUTES_PATH));
    expect($routes)->toContain('transactions/{tx}/emissoes');
});

// ─── SellController.inertiaList retorna fiscal status ───────────────────────

it('SellController.inertiaList retorna fiscal_status + fiscal_modelo na linha', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellController.php'));
    expect($source)->toContain("'fiscal_status'");
    expect($source)->toContain("'fiscal_modelo'");
});

it('SellController.inertiaList faz lookup NfeEmissao agrupado por transaction_id', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellController.php'));
    expect($source)->toContain('Modules\\NfeBrasil\\Models\\NfeEmissao');
    expect($source)->toContain('whereIn(\'transaction_id\', $txIds)');
    expect($source)->toContain('groupBy(\'transaction_id\')');
});

// ─── Hook useEmissoesPorTransaction ──────────────────────────────────────────

it('Hook useEmissoesPorTransaction existe + tipa Emissao + EmissaoStatus', function () {
    $source = file_get_contents(base_path(FISCAL_HOOK_PATH));
    expect($source)->toContain('export type EmissaoStatus');
    expect($source)->toContain('export interface Emissao');
    expect($source)->toContain("modelo: '55' | '65'");
    expect($source)->toContain("modelo_label: 'NFC-e' | 'NFe'");
});

it('Hook useEmissoesPorTransaction faz polling enquanto há pendente', function () {
    $source = file_get_contents(base_path(FISCAL_HOOK_PATH));
    expect($source)->toContain('hasPending');
    expect($source)->toContain('intervalMs');
    expect($source)->toContain('maxPolls');
});

it('Hook fetcha endpoint canon /nfe-brasil/api/transactions/{tx}/emissoes', function () {
    $source = file_get_contents(base_path(FISCAL_HOOK_PATH));
    expect($source)->toMatch('/`\\/nfe-brasil\\/api\\/transactions\\/\\$\\{transactionId\\}\\/emissoes`/');
});

// ─── FiscalSection.tsx (drawer SaleSheet) ────────────────────────────────────

it('FiscalSection existe + usa cores semânticas Cockpit V2 (rose/emerald/amber)', function () {
    $source = file_get_contents(base_path(FISCAL_SECTION_PATH));
    expect($source)->toMatch('/bg-emerald-50[^\'"]*text-emerald-700/');
    expect($source)->toMatch('/bg-amber-50[^\'"]*text-amber-700/');
    expect($source)->toMatch('/bg-rose-50[^\'"]*text-rose-700/');
});

it('FiscalSection tem botões NFC-e + NFe (manual emission)', function () {
    $source = file_get_contents(base_path(FISCAL_SECTION_PATH));
    expect($source)->toContain("emitir('65')");
    expect($source)->toContain("emitir('55')");
    expect($source)->toContain('Emitir NFC-e');
    expect($source)->toContain('Emitir NFe');
});

it('FiscalSection tem ações DANFE PDF + Reenviar email + Detalhes', function () {
    $source = file_get_contents(base_path(FISCAL_SECTION_PATH));
    expect($source)->toContain('DANFE PDF');
    expect($source)->toContain('Reenviar email');
    expect($source)->toContain('danfe-pdf');
    expect($source)->toContain('reenviar-email');
});

it('FiscalSection idempotência UI: botão Emitir somente se !hasNfce/!hasNfe', function () {
    $source = file_get_contents(base_path(FISCAL_SECTION_PATH));
    expect($source)->toContain('hasNfce');
    expect($source)->toContain('hasNfe');
    expect($source)->toMatch('/!hasNfce/');
    expect($source)->toMatch('/!hasNfe/');
});

it('FiscalSection segue Cockpit canon: text-[10px] uppercase tracking-widest pra heading', function () {
    $source = file_get_contents(base_path(FISCAL_SECTION_PATH));
    expect($source)->toContain('text-[10px]');
    expect($source)->toContain('uppercase');
    expect($source)->toContain('tracking-widest');
});

// ─── Sells/Index.tsx — coluna Fiscal + FiscalCell ────────────────────────────

it('Sells/Index.tsx tem coluna Fiscal + FiscalCell component', function () {
    $source = file_get_contents(base_path('resources/js/Pages/Sells/Index.tsx'));
    expect($source)->toContain('<Th className="w-32">Fiscal</Th>');
    expect($source)->toContain('<FiscalCell');
    expect($source)->toContain('function FiscalCell');
});

it('Sells/Index.tsx FiscalCell usa DropdownMenu shadcn com NFC-e + NFe', function () {
    $source = file_get_contents(base_path('resources/js/Pages/Sells/Index.tsx'));
    expect($source)->toContain('DropdownMenu');
    expect($source)->toContain("emitir('65')");
    expect($source)->toContain("emitir('55')");
    expect($source)->toContain('NFC-e (modelo 65)');
    expect($source)->toContain('NFe (modelo 55)');
});

it('Sells/Index.tsx FiscalCell badges canon (emerald=autorizada, amber=pendente, rose=erro)', function () {
    $source = file_get_contents(base_path('resources/js/Pages/Sells/Index.tsx'));
    // Autorizada → emerald
    expect($source)->toMatch('/border-emerald-200[^\'"]*bg-emerald-50/');
    // Pendente → amber
    expect($source)->toMatch('/border-amber-200[^\'"]*bg-amber-50/');
});

it('Sells/Index.tsx interface SaleRow inclui fiscal_status + fiscal_modelo', function () {
    $source = file_get_contents(base_path('resources/js/Pages/Sells/Index.tsx'));
    expect($source)->toContain('fiscal_status');
    expect($source)->toContain('fiscal_modelo');
});

// ─── SaleSheet drawer integração ─────────────────────────────────────────────

it('SaleSheet drawer importa + renderiza FiscalSection', function () {
    $source = file_get_contents(base_path('resources/js/Pages/Sells/_components/SaleSheet.tsx'));
    expect($source)->toContain("import FiscalSection from './FiscalSection'");
    expect($source)->toContain('<FiscalSection saleId={data.id}');
});

// ─── BUG FIX 2026-05-08: XSD ordem CPF/CNPJ antes de xNome + anônimo NFC-e ───

it('BUG FIX: NfeService NFC-e consumidor anônimo (sem CPF) NÃO seta CPF=\'\' (omite <dest>)', function () {
    $source = file_get_contents(base_path('Modules/NfeBrasil/Services/NfeService.php'));
    // Regression do bug Wagner reportou 2026-05-08:
    //   "Element xNome: This element is not expected. Expected is one of (CNPJ, CPF, idEstrangeiro)"
    // Causa antiga: $stdDest->CPF = '' quando doc vazio + xNome setado antes.
    // Fix: NFC-e (modelo 65) + sem doc válido → pula tagdest() inteiro.
    expect($source)->toContain('NFC-e consumidor anônimo');
    expect($source)->toContain('omitindo <dest>');
    expect($source)->toContain('$isNfce && !$hasDoc');
    // Garante que NÃO existe pattern antigo do bug:
    // else { $stdDest->CPF = $doc; }  ← seria regression (cai aqui se doc vazio).
    expect($source)->not->toMatch('/}\\s*else\\s*\\{\\s*\\$stdDest->CPF\\s*=\\s*\\$doc;\\s*\\}/');
});

it('BUG FIX: idempotência só retém emissão se status positivo (autorizada/pendente) — terminal negativo = force delete', function () {
    $source = file_get_contents(base_path('Modules/NfeBrasil/Services/NfeService.php'));
    // Antes: idempotência retornava QUALQUER status (rejeitada incluída) → bloqueava retry.
    // Depois: só autorizada/pendente bloqueia retry. Negativo (rejeitada/denegada/cancelada)
    // hard delete pra permitir nova tentativa.
    expect($source)->toContain("in_array(\$existente->status, ['autorizada', 'pendente'], true)");
    expect($source)->toContain('forceDelete()');
    expect($source)->toContain('terminal negativa');
    // Pattern antigo proibido (return existente sem checar status).
    expect($source)->not->toMatch("/if\\s*\\(\\s*\\\$existente\\s*\\)\\s*\\{\\s*Log::info\\([^\\n]+\\n[^\\n]+\\n[^\\n]+\\n[^\\n]+\\n[^\\n]+return\\s+\\\$existente;\\s*\\}/s");
});

it('BUG FIX: indFinal=1 sempre em NFC-e (modelo 65) — rejeição "operacao nao destinada a consumidor final"', function () {
    $source = file_get_contents(base_path('Modules/NfeBrasil/Services/NfeService.php'));
    // NFC-e é SEMPRE consumidor final independente de CPF.
    expect($source)->toContain("(int) \$emissao->modelo === 65 ? 1 : (isset(\$dadosNfe['dest']['cpf']) ? 1 : 0)");
    // Pattern antigo proibido: indFinal só checava CPF (errado pra NFC-e).
    expect($source)->not->toMatch("/\\\$stdIde->indFinal\\s*=\\s*isset\\(\\\$dadosNfe\\['dest'\\]\\['cpf'\\]\\)\\s*\\?\\s*1\\s*:\\s*0;/");
});

it('BUG FIX: tpImp=4 pra NFC-e (modelo 65) — rejeição SEFAZ "DANFE invalido" se hardcoded 1', function () {
    $source = file_get_contents(base_path('Modules/NfeBrasil/Services/NfeService.php'));
    // tpImp não pode ser hardcoded 1 (default NFe Retrato) — rejeita NFC-e.
    // NFC-e (modelo 65) deve ser tpImp=4 (DANFE NFC-e bobina) ou 5 (msg eletrônica).
    expect($source)->toContain('(int) $emissao->modelo === 65 ? 4');
    // Garante que não existe pattern antigo `$stdIde->tpImp = 1;` standalone.
    expect($source)->not->toMatch('/\\$stdIde->tpImp\\s*=\\s*1;\\s*\\n/');
});

it('BUG FIX: NfeService XSD ordem — CPF/CNPJ ANTES de xNome (canon SEFAZ)', function () {
    $source = file_get_contents(base_path('Modules/NfeBrasil/Services/NfeService.php'));
    // Comentário documenta a regra (defesa em profundidade vs futura regressão).
    expect($source)->toContain('XSD SEFAZ ORDEM');
    expect($source)->toContain('ANTES de xNome');
    // Verificação estrutural: no bloco else (com_doc), CNPJ/CPF aparecem ANTES de xNome.
    // Pega o trecho do else { ... tagdest($stdDest); }.
    if (preg_match('/\\$stdDest = new \\\\stdClass\\(\\);(.*?)tagdest\\(\\$stdDest\\)/s', $source, $m)) {
        $bloco = $m[1];
        $cnpjPos = strpos($bloco, '$stdDest->CNPJ');
        $cpfPos  = strpos($bloco, '$stdDest->CPF');
        $xNomePos = strpos($bloco, '$stdDest->xNome');
        // CNPJ ou CPF deve existir e vir ANTES de xNome.
        expect($cnpjPos !== false || $cpfPos !== false)->toBeTrue('Bloco dest sem CNPJ ou CPF');
        $docPos = $cnpjPos !== false ? $cnpjPos : $cpfPos;
        expect($xNomePos)->toBeGreaterThan($docPos);
    } else {
        $this->fail('Bloco $stdDest = new \\stdClass até tagdest() não encontrado');
    }
});
