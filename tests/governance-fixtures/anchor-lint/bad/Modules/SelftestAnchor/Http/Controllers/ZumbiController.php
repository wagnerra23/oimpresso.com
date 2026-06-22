<?php

namespace Modules\SelftestAnchor\Http\Controllers;

class ZumbiController
{
    public function index()
    {
        return Inertia::render('SelftestAnchor/Zumbi', []);
    }
}
