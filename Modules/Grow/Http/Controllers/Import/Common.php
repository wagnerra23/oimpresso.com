<?php

/** --------------------------------------------------------------------------------
 * This controller manages all the business logic for template
 *
 * @package    Grow CRM
 * @author     NextLoop
 *----------------------------------------------------------------------------------*/

namespace Modules\Grow\Http\Controllers\Import;

use Modules\Grow\Http\Controllers\Controller;

use Modules\Grow\Http\Responses\Import\Common\LogResponse;

class Common extends Controller {

    public function __construct() {

        //parent
        parent::__construct();

        //authenticated
        $this->middleware('auth');

        $this->middleware('teamCheck');

    }

    /**
     * Display a listing of foos
     * @return blade view | ajax view
     */
    public function showErrorLog() {

        //validate
        if (!request()->filled('ref')) {
            abort(409, __('lang.error_request_could_not_be_completed'));
        }

        //get logs
        $log = \Modules\Grow\Models\Log::Where('log_uniqueid', request('ref'))->first();

        //reponse payload
        $payload = [
            'log' => $log,
        ];

        //show the view
        return new LogResponse($payload);
    }

}