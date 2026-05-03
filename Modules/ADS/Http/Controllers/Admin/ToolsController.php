<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\ToolRegistry;

class ToolsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(ToolRegistry $registry): Response
    {
        $tools = collect($registry->all())
            ->map(fn ($t) => [
                'name'         => $t->name(),
                'description'  => $t->description(),
                'category'     => $t->category(),
                'is_read_only' => $t->isReadOnly(),
                'input_schema' => $t->inputSchema(),
            ])
            ->groupBy('category')
            ->map(fn ($group, $cat) => [
                'category' => $cat,
                'tools'    => $group->values(),
            ])
            ->values();

        $kpis = [
            'total'      => count($registry->all()),
            'read_only'  => count($registry->readOnly()),
            'categories' => $tools->count(),
        ];

        return Inertia::render('ads/Admin/Tools', [
            'tools_by_category' => $tools,
            'kpis'              => $kpis,
        ]);
    }
}
