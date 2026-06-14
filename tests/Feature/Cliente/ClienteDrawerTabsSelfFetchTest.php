<?php

declare(strict_types=1);

/**
 * @group legacy-quarantine
 * quarantine-reason: assert estático de canon-source (rotas/ContactController/.tsx do drawer) contra fonte-da-verdade móvel — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

/**
 * Fix 2026-06-08 — self-fetch das abas Pagamentos/Pontos/Assinaturas do drawer.
 *
 * Mesma classe de bug do SalesTab (#2437): no drawer 760px (ADR 0179) essas abas
 * recebiam prop undefined e ficavam presas no skeleton ("Carregando…") ou em
 * "Aguardando wiring" (PaymentsTab apontava pro legado /contacts/payments/{id}
 * que devolve Blade HTML). Endpoints JSON dedicados + self-fetch no mount resolvem.
 *
 * GUARDs estruturais (file_get_contents) + auth-gate graceful-skip por endpoint.
 * Refs: ADR 0093 (multi-tenant Tier 0) · ADR 0179 (drawer 760) · companheiro de
 * ClienteSalesJsonEndpointTest.
 */

// ─── GUARD 1: rotas registradas ───────────────────────────────────────────

test('GUARD 1 — routes/web.php registra payments/rewards/subscriptions-json', function () {
    $contents = file_get_contents(__DIR__ . '/../../../routes/web.php');

    expect($contents)
        ->toContain("Route::get('/cliente/{id}/payments-json', [ContactController::class, 'paymentsJson'])")
        ->toContain("Route::get('/cliente/{id}/rewards-json', [ContactController::class, 'rewardsJson'])")
        ->toContain("Route::get('/cliente/{id}/subscriptions-json', [ContactController::class, 'subscriptionsJson'])");
});

// ─── GUARD 2: métodos com tenant scope + permissão + PII ──────────────────

test('GUARD 2 — paymentsJson/rewardsJson/subscriptionsJson com business_id scope + permissão', function () {
    $contents = file_get_contents(__DIR__ . '/../../../app/Http/Controllers/ContactController.php');

    expect($contents)
        ->toContain('public function paymentsJson($id)')
        ->toContain('public function rewardsJson($id)')
        ->toContain('public function subscriptionsJson($id)')
        // Tier 0 (ADR 0093): 404 cross-tenant nos três antes de qualquer dado.
        ->toContain("Contact::where('business_id', \$business_id)->findOrFail(\$id)")
        ->toContain("auth()->user()->can('customer.view')")
        // PII §LGPD: número de conta NUNCA em claro.
        ->toContain("'bank_account_number' => \$p->bank_account_number")
        ->toContain("'****' . substr((string) \$p->bank_account_number, -4)")
        // Shapes esperados pelos componentes.
        ->toContain("response()->json(['payments' => \$payments])")
        ->toContain("'enabled' => true,")
        ->toContain("'is_recurring', 1");
});

// ─── GUARD 3: componentes fazem self-fetch via contactId ──────────────────

test('GUARD 3 — RewardPointsTab/SubscriptionsTab self-fetch + PaymentsTab aponta pro JSON', function () {
    $rewards = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/_show/RewardPointsTab.tsx');
    $subs = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/_show/SubscriptionsTab.tsx');
    $payments = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/_show/PaymentsTab.tsx');

    expect($rewards)
        ->toContain('contactId?: number')
        ->toContain('/cliente/${contactId}/rewards-json')
        ->toContain('useEffect(');

    expect($subs)
        ->toContain('contactId?: number')
        ->toContain('/cliente/${contactId}/subscriptions-json')
        ->toContain('useEffect(');

    expect($payments)
        // Não usa mais o legado que devolvia HTML ("Aguardando wiring").
        ->toContain('/cliente/${contactId}/payments-json')
        ->not->toContain('/contacts/payments/${contactId}');
});

// ─── GUARD 4: OssTab passa contactId pras abas (dispara self-fetch) ───────

test('GUARD 4 — OssTab passa contactId pro Rewards/Subscriptions (não mais undefined)', function () {
    $contents = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/OssTab.tsx');

    expect($contents)
        ->toContain('<SubscriptionsTab contactId={contact.id} />')
        ->toContain('<RewardPointsTab contactId={contact.id} />')
        ->not->toContain('subscriptions={undefined}')
        ->not->toContain('reward_points={undefined}');
});

// ─── GUARD 5: endpoints não vazam sem auth (integração graceful-skip) ─────

test('GUARD 5 — endpoints JSON não vazam sem auth', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    foreach (['payments-json', 'rewards-json', 'subscriptions-json'] as $ep) {
        $response = $this->get("/cliente/1/{$ep}");
        expect(in_array($response->status(), [302, 401, 403, 404], true))->toBeTrue();
    }
});
