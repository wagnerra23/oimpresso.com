<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Crm\Console\Commands\CrmHealthCommand;
use Modules\Crm\Http\Requests\IndexProposalRequest;
use Modules\Crm\Http\Requests\MassDestroyLeadRequest;
use Modules\Crm\Http\Requests\UpdateCampaignRequest;

uses(Tests\TestCase::class);

/**
 * Wave 25 Crm POLISH — D8 (FormRequests +3) + D6 (Health 7+8).
 *
 * Cobre:
 *   - IndexProposalRequest (rules anti-SQLi + bounds anti-DoS)
 *   - UpdateCampaignRequest (irmão de StoreCampaignRequest)
 *   - MassDestroyLeadRequest (LGPD motivo + cap 200 anti-DoS)
 *   - crm:health total 8 checks (6 Wave 23 + 2 Wave 25: call_log_table + leads_sem_owner)
 *
 * Multi-tenant Tier 0 (ADR 0093): NÃO toca DB nem session.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 25 Crm Polish', function () {
    it('D8 — IndexProposalRequest rules whitelist seguros (anti-SQLi)', function () {
        $req = new IndexProposalRequest();
        $rules = $req->rules();

        // status whitelist
        expect($rules['status'])->toContain('nullable', 'string');
        $statusRule = collect($rules['status'])->first(fn ($r) => is_string($r) && str_starts_with($r, 'in:'));
        expect($statusRule)->toContain('draft', 'sent', 'accepted');

        // order_by/dir whitelist
        $orderBy = collect($rules['order_by'])->first(fn ($r) => is_string($r) && str_starts_with($r, 'in:'));
        expect($orderBy)->toContain('id', 'created_at', 'status');
        expect($rules['order_dir'])->toContain('in:asc,desc');

        // Bounds page/per_page
        expect($rules['per_page'])->toContain('min:5', 'max:200');
        expect($rules['page'])->toContain('min:1', 'max:10000');
    });

    it('D8 — UpdateCampaignRequest rules de update parcial', function () {
        $req = new UpdateCampaignRequest();
        $rules = $req->rules();

        expect($rules['name'])->toContain('sometimes');
        expect($rules['campaign_type'])->toContain('sometimes', 'in:email,sms,birthday_wishes');
        expect($rules['in_days'])->toContain('min:0', 'max:365');
    });

    it('D8 — MassDestroyLeadRequest cap 200 + LGPD motivo', function () {
        $req = new MassDestroyLeadRequest();
        $rules = $req->rules();

        expect($rules['ids'])->toContain('required', 'array', 'min:1', 'max:200');
        expect($rules['ids.*'])->toContain('integer', 'min:1');
        expect($rules['motivo'])->toContain('nullable', 'string', 'max:500');

        $messages = $req->messages();
        expect($messages)->toHaveKey('ids.max');
        expect($messages['ids.max'])->toContain('anti-DoS');
    });

    it('Health command total agora 8 checks (6 Wave 23 + 2 Wave 25)', function () {
        $exitCode = Artisan::call('crm:health', ['--json' => true]);
        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        $decoded = json_decode($output, true);

        expect($decoded)->toBeArray();
        expect(count($decoded['checks']))->toBe(8);

        $names = collect($decoded['checks'])->pluck('name')->toArray();
        expect($names)->toContain('call_log_table', 'leads_sem_owner');
    });

    it('Health command Wave 25 checks aparecem em --detail sem crash', function () {
        $exitCode = Artisan::call('crm:health', ['--detail' => true]);
        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('call_log_table');
        expect($output)->toContain('leads_sem_owner');
    });

    it('CrmHealthCommand classe registrada', function () {
        expect(Artisan::all())->toHaveKey('crm:health');
        expect(Artisan::all()['crm:health'])->toBeInstanceOf(CrmHealthCommand::class);
    });
});
