<?php

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IndexController — Admin Center painel principal (`GET /admin`).
 *
 * Sprint 1 MVP — placeholder shell sem widgets reais. Widgets entram em
 * Sprint 1 dia 3-4 (US-ADM-005..008): Brief, Health, Cycles, ADRs Tier 0.
 *
 * Auth gate via middleware stack: tailscale-only -> auth -> is-wagner.
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class IndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Admin/Index', [
            'placeholder' => true,
            'widgets'     => [
                'brief'      => 'pending US-ADM-005',
                'health'     => 'pending US-ADM-006',
                'cycles'     => 'pending US-ADM-007',
                'adr_tier_0' => 'pending US-ADM-008',
            ],
        ]);
    }
}
