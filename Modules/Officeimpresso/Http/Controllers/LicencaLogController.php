<?php

namespace Modules\Officeimpresso\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LicencaLogController extends Controller
{
    // TODO: implementar model LicencaLog quando a tabela `licenca_log` existir.
    // Por enquanto, todas as actions retornam 501 Not Implemented.
    private function stub()
    {
        abort(501, 'LicencaLog ainda nao implementado nesta versao.');
    }

    public function index() { $this->stub(); }
    public function create() { $this->stub(); }
    public function store(Request $request) { $this->stub(); }
    public function show($id) { $this->stub(); }
    public function edit($id) { $this->stub(); }
    public function update(Request $request, $id) { $this->stub(); }
    public function destroy($id) { $this->stub(); }
}
