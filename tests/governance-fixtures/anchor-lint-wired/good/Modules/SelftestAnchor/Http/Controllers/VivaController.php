<?php

namespace Modules\SelftestAnchor\Http\Controllers;

class VivaController
{
    public function index()
    {
        return Inertia::render('SelftestAnchor/Viva', []);
    }
}
