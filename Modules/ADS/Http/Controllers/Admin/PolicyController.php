<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\PolicyEngine;
use Modules\ADS\Services\DecisionPresenter;

class PolicyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(PolicyEngine $policy): Response
    {
        $rules = $policy->getAllRules();

        // Anota cada regra com o "label humano" do event_type via DecisionPresenter
        $annotated = [];
        foreach ($rules as $category => $eventTypes) {
            $items = [];
            foreach ($eventTypes as $eventType) {
                $items[] = [
                    'event_type' => $eventType,
                    'label'      => $this->humanLabel($eventType),
                ];
            }
            $annotated[] = [
                'category'    => $category,
                'description' => $policy->categoryDescription($category),
                'count'       => count($items),
                'items'       => $items,
            ];
        }

        return Inertia::render('ads/Admin/Policy', [
            'rules' => $annotated,
        ]);
    }

    private function humanLabel(string $eventType): string
    {
        // Reusa o eventLabel do Presenter via shim — chama explain com objeto fake
        $fake = (object) [
            'event_type' => $eventType, 'domain' => '', 'destination' => 'blocked',
            'policy_applied' => 'BLOCK_ALWAYS', 'outcome' => 'cancelled',
            'brain_used' => 'none', 'risk_score' => 0,
        ];
        $explained = DecisionPresenter::explain($fake);
        // explain retorna "{label} — {status} ({domain})", queremos só o label
        $parts = explode(' — ', $explained['one_line']);
        return $parts[0] ?? $eventType;
    }
}
