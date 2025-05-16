<?php

/** --------------------------------------------------------------------------------
 * This controller manages all the business logic for template
 *
 * @package    Grow CRM
 * @author     NextLoop
 *----------------------------------------------------------------------------------*/
          
namespace Modules\Grow\Http\Controllers;

use Modules\Grow\Http\Controllers\Controller;
use Log;

class Test extends Controller {

    public function __construct() {

        //parent
        parent::__construct();

        //authenticated
        $this->middleware('auth');

        //admin
        // $this->middleware('adminCheck');
    }

    /**
     * do something
     *
     * @return bool
     */
    public function index() {

        dd(config('modules'));
        
    }

}