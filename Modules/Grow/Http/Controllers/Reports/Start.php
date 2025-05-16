<?php

/** --------------------------------------------------------------------------------
 * This controller manages all the business logic for template
 *
 * @package    Grow CRM
 * @author     NextLoop
 *----------------------------------------------------------------------------------*/

namespace Modules\Grow\Http\Controllers\Reports;

use Modules\Grow\Http\Controllers\Controller;
use Modules\Grow\Http\Responses\Reports\StartResponse;

class Start extends Controller {

    public function __construct() {

        //parent
        parent::__construct();

        //authenticated
        $this->middleware('auth');

        //route middleware
        $this->middleware('reportsMiddlewareShow')->only([
            'start',
        ]);

    }

    /**
     * Update a resource
     * @return \Illuminate\Http\Response
     */
    public function showStart() {

        //reponse payload
        $payload = [

        ];

        //process reponse
        return new StartResponse($payload);

    }

}