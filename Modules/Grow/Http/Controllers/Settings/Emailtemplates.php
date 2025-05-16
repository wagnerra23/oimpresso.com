<?php

/** --------------------------------------------------------------------------------
 * This controller manages all the business logic for email template settings
 *
 * @package    Grow CRM
 * @author     NextLoop
 *----------------------------------------------------------------------------------*/

namespace Modules\Grow\Http\Controllers\Settings;
use Modules\Grow\Http\Controllers\Controller;
use Modules\Grow\Http\Responses\Common\CommonResponse;
use Modules\Grow\Http\Responses\Settings\EmailTemplates\IndexResponse;
use Modules\Grow\Http\Responses\Settings\EmailTemplates\ShowResponse;
use Modules\Grow\Repositories\SettingsRepository;
use Illuminate\Http\Request;
use Validator;

class Emailtemplates extends Controller {

    /**
     * The settings repository instance.
     */
    protected $settingsrepo;

    public function __construct(SettingsRepository $settingsrepo) {

        //parent
        parent::__construct();

        //authenticated
        $this->middleware('auth');

        //settings general
        $this->middleware('settingsMiddlewareIndex');

        $this->settingsrepo = $settingsrepo;

    }

    /**
     * Display templates home page
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {

        //crumbs, page data & stats
        $page = $this->pageSettings();

        //templates by types
        $users = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'users')->get();
        $projects = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'projects')->get();
        $leads = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'leads')->get();
        $tasks = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'tasks')->get();
        $billing = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'billing')->get();
        $tickets = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'tickets')->get();
        $system = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'system')->get();
        $other = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'other')->get();
        $estimates = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'estimates')->get();
        $subscriptions = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'subscriptions')->get();
        $modules = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'modules')->get();
        $proposals = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'proposals')->get();
        $contracts = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_category', 'contracts')->get();


        //reponse payload
        $payload = [
            'page' => $page,
            'users' => $users,
            'projects' => $projects,
            'leads' => $leads,
            'tasks' => $tasks,
            'billing' => $billing,
            'estimates' => $estimates,
            'subscriptions' => $subscriptions,
            'tickets' => $tickets,
            'system' => $system,
            'other' => $other,
            'modules' => $modules,
            'proposals' =>$proposals,
            'contracts' =>$contracts
        ];

        //show the view
        return new IndexResponse($payload);
    }

    /**
     * Display email template form
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id) {

        if (!$template = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_id', $id)->first()) {
            abort(404);
        }

        //basic variables
        $variables['template'] = explode(',', $template->emailtemplate_variables);
        $variables['general'] = explode(',', config('system.settings_email_general_variables'));

        //reponse payload
        $payload = [
            'template' => $template,
            'variables' => $variables,
        ];

        //show the view
        return new ShowResponse($payload);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update($id) {

        //get the tempate
        if (!$template = \Modules\Grow\Models\EmailTemplate::Where('emailtemplate_id', $id)->first()) {
            abort(404);
        }
        //validate
        $validator = Validator::make(request()->all(), [
            'emailtemplate_body' => 'required',
            'emailtemplate_subject' => 'required',
        ]);

        //errors
        if ($validator->fails()) {
            $errors = $validator->errors();
            $messages = '';
            foreach ($errors->all() as $message) {
                $messages .= "<li>$message</li>";
            }
            
            abort(409, $messages);
        }

        //update
        $template->emailtemplate_subject = request('emailtemplate_subject');
        $template->emailtemplate_body = request('emailtemplate_body');
        $template->emailtemplate_status = (request('emailtemplate_status') == 'on') ? 'enabled' : 'disabled';

        $template->save();

        //reponse payload
        $payload = [
            'type' => 'success-notification',
        ];

        //generate a response
        return new CommonResponse($payload);
    }
    /**
     * basic page setting for this section of the app
     * @param string $section page section (optional)
     * @param array $data any other data (optional)
     * @return array
     */
    private function pageSettings($section = '', $data = []) {

        $page = [
            'crumbs' => [
                __('lang.settings'),
                __('lang.email'),
                __('lang.email_templates'),
            ],
            'crumbs_special_class' => 'main-pages-crumbs',
            'page' => 'settings',
            'meta_title' => __('lang.settings'),
            'heading' => __('lang.settings'),
            'settingsmenu_email' => 'active',
        ];
        return $page;
    }

}
