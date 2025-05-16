<?php

/** --------------------------------------------------------------------------------
 * This controller manages all the business logic for ticket settings
 *
 * [category table mapping] - For IMAP email settings
 *
 *  -category_meta_4 : email
 *  -category_meta_5 : host
 *  -category_meta_6 : port
 *  -category_meta_7 : encryption
 *  -category_meta_8 : username
 *  -category_meta_9 : password
 *  -category_meta_10 : post_import_action
 *
 * @package    Grow CRM
 * @author     NextLoop
 *----------------------------------------------------------------------------------*/

namespace Modules\Grow\Http\Controllers\Settings;
use Modules\Grow\Http\Controllers\Controller;
use Modules\Grow\Http\Requests\Settings\Tickets\CategoryIMAPIntegration;
use Modules\Grow\Http\Responses\Settings\Tickets\CategoryIMAPSettingsResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\CreateStatusResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\DestroyStatusResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\EditStatusResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\IndexResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\moveResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\MoveUpdateResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\SettingsResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\StatusesResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\StoreStatusResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\TestConnectionResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\UpdateResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\UpdateSettingsResponse;
use Modules\Grow\Http\Responses\Settings\Tickets\UpdateStatusResponse;
use Modules\Grow\Repositories\SettingsRepository;
use Modules\Grow\Repositories\TicketsImapRepository;
use Modules\Grow\Repositories\TicketStatusRepository;
use DB;
use Illuminate\Http\Request;
use Validator;

class Tickets extends Controller {

    /**
     * The repository instances.
     */
    protected $settingsrepo;
    protected $statusrepo;

    public function __construct(SettingsRepository $settingsrepo, TicketStatusRepository $statusrepo) {

        //parent
        parent::__construct();

        //authenticated
        $this->middleware('auth');

        //settings general
        $this->middleware('settingsMiddlewareIndex');

        $this->settingsrepo = $settingsrepo;
        $this->statusrepo = $statusrepo;

    }

    /**
     * Display general settings
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {

        //crumbs, page data & stats
        $page = $this->pageSettings();

        //save system settings into config array
        $settings = \Modules\Grow\Models\Settings::leftJoin('settings2', 'settings2.settings2_id', '=', 'settings.settings_id')
            ->Where('settings_id', 1)
            ->first();

        //reponse payload
        $payload = [
            'page' => $page,
            'settings' => $settings,
        ];

        //show the view
        return new IndexResponse($payload);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update() {

        //custom error messages
        $messages = [];

        //update
        if (!$this->settingsrepo->updateTickets()) {
            abort(409);
        }

        //reset existing account owner
        \Modules\Grow\Models\Settings2::where('settings2_id', 1)
            ->update([
                'settings2_tickets_replying_interface' => request('settings2_tickets_replying_interface'),
            ]);

        //reponse payload
        $payload = [];

        //generate a response
        return new UpdateResponse($payload);
    }

    /**
     * Display general settings
     *
     * @return \Illuminate\Http\Response
     */
    public function statuses() {

        //crumbs, page data & stats
        $page = $this->pageSettings('statuses');

        $statuses = $this->statusrepo->search();

        //reponse payload
        $payload = [
            'page' => $page,
            'statuses' => $statuses,
        ];

        //show the view
        return new StatusesResponse($payload);
    }

    /**
     * Show the form for editing the specified resource.
     * @url baseusr/items/1/edit
     * @param int $id resource id
     * @return \Illuminate\Http\Response
     */
    public function editStatus($id) {

        //page settings
        $page = $this->pageSettings('edit');

        //client ticketsources
        $statuses = $this->statusrepo->search($id);

        //not found
        if (!$status = $statuses->first()) {
            abort(409, __('lang.error_loading_item'));
        }

        //reponse payload
        $payload = [
            'page' => $page,
            'status' => $status,
        ];

        //response
        return new EditStatusResponse($payload);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param int $id resource id
     * @return \Illuminate\Http\Response
     */
    public function updateStatus($id) {

        //custom error messages
        $messages = [];

        //validate
        $validator = Validator::make(request()->all(), [
            'ticketstatus_title' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (\Modules\Grow\Models\TicketStatus::where('ticketstatus_title', $value)
                        ->where('ticketstatus_id', '!=', request()->route('id'))
                        ->exists()) {
                        return $fail(__('lang.status_already_exists'));
                    }
                }],
        ], $messages);

        //errors
        if ($validator->fails()) {
            $errors = $validator->errors();
            $messages = '';
            foreach ($errors->all() as $message) {
                $messages .= "<li>$message</li>";
            }

            abort(409, $messages);
        }

        //update the resource
        if (!$this->statusrepo->update($id)) {
            abort(409);
        }

        //get the category object (friendly for rendering in blade template)
        $statuses = $this->statusrepo->search($id);

        //reponse payload
        $payload = [
            'statuses' => $statuses,
        ];

        //process reponse
        return new UpdateStatusResponse($payload);

    }

    /**
     * Show the form for creating a new resource.
     * @return \Illuminate\Http\Response
     */
    public function createStatus() {

        //page settings
        $page = $this->pageSettings();
        $page['default_color'] = 'checked';

        //reponse payload
        $payload = [
            'page' => $page,
        ];

        //show the form
        return new CreateStatusResponse($payload);
    }

    /**
     * Store a newly created resource in storage.
     * @return \Illuminate\Http\Response
     */
    public function storeStatus() {

        //custom error messages
        $messages = [];

        //validate
        $validator = Validator::make(request()->all(), [
            'ticketstatus_title' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (\Modules\Grow\Models\TicketStatus::where('ticketstatus_title', $value)
                        ->exists()) {
                        return $fail(__('lang.status_already_exists'));
                    }
                }],
        ], $messages);

        //errors
        if ($validator->fails()) {
            $errors = $validator->errors();
            $messages = '';
            foreach ($errors->all() as $message) {
                $messages .= "<li>$message</li>";
            }

            abort(409, $messages);
        }

        //get the last row (order by position - desc)
        if ($last = \Modules\Grow\Models\TicketStatus::orderBy('ticketstatus_position', 'desc')->first()) {
            $position = $last->ticketstatus_position + 1;
        } else {
            //default position
            $position = 2;
        }

        //create the source
        if (!$ticketstatus_id = $this->statusrepo->create($position)) {
            abort(409, __('lang.error_request_could_not_be_completed'));
        }

        //get the source object (friendly for rendering in blade template)
        $statuses = $this->statusrepo->search($ticketstatus_id);

        //reponse payload
        $payload = [
            'statuses' => $statuses,
        ];

        //process reponse
        return new StoreStatusResponse($payload);

    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id resource id
     * @return \Illuminate\Http\Response
     */
    public function move($id) {

        //page settings
        $page = $this->pageSettings();

        //client ticketsources
        $statuses = \Modules\Grow\Models\TicketStatus::get();

        //reponse payload
        $payload = [
            'page' => $page,
            'statuses' => $statuses,
        ];

        //response
        return new moveResponse($payload);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id resource id
     * @return \Illuminate\Http\Response
     */
    public function statusSettings($id) {

        //page settings
        $page = $this->pageSettings();

        //get the status
        if (!$status = \Modules\Grow\Models\TicketStatus::Where('ticketstatus_id', $id)->first()) {
            abort(404);
        }

        //reponse payload
        $payload = [
            'page' => $page,
            'status' => $status,
        ];

        //response
        return new SettingsResponse($payload);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id resource id
     * @return \Illuminate\Http\Response
     */
    public function statusSettingsUpdate($id) {

        //page settings
        $page = $this->pageSettings();

        //get the status
        if (!$status = \Modules\Grow\Models\TicketStatus::Where('ticketstatus_id', $id)->first()) {
            abort(404);
        }

        //reset all other statuses
        if (request('ticketstatus_use_for_client_replied') == 'on') {
            DB::table('tickets_status')->update([
                'ticketstatus_use_for_client_replied' => 'no',
            ]);
        }

        //reset all other statuses
        if (request('ticketstatus_use_for_team_replied') == 'on') {
            DB::table('tickets_status')->update([
                'ticketstatus_use_for_team_replied' => 'no',
            ]);
        }

        //update the status
        $status = \Modules\Grow\Models\TicketStatus::Where('ticketstatus_id', $id)->first();
        $status->ticketstatus_use_for_client_replied = (request('ticketstatus_use_for_client_replied') == 'on') ? 'yes' : 'no';
        $status->ticketstatus_use_for_team_replied = (request('ticketstatus_use_for_team_replied') == 'on') ? 'yes' : 'no';
        $status->save();

        //get all statuses
        $statuses = $this->statusrepo->search();

        //reponse payload
        $payload = [
            'page' => $page,
            'statuses' => $statuses,
        ];

        //response
        return new UpdateSettingsResponse($payload);
    }

    /**
     * Move tickets from one category to another
     * @param int $id resource id
     * @return \Illuminate\Http\Response
     */
    public function updateMove($id) {

        //page settings
        $page = $this->pageSettings();

        //move the tickets
        \Modules\Grow\Models\Ticket::where('ticket_status', $id)->update(['ticket_status' => request('tickets_status')]);

        //client ticketsources
        $statuses = $this->statusrepo->search();

        //reponse payload
        $payload = [
            'page' => $page,
            'statuses' => $statuses,
        ];

        //response
        return new MoveUpdateResponse($payload);
    }

    /**
     * Update a stages position
     * @param int $id resource id
     * @return \Illuminate\Http\Response
     */
    public function updateStagePositions() {

        //reposition each ticket status
        $i = 1;
        foreach (request('sort-stages') as $key => $id) {
            if (is_numeric($id)) {
                \Modules\Grow\Models\TicketStatus::where('ticketstatus_id', $id)->update(['ticketstatus_position' => $i]);
            }
            $i++;
        }

        //retun simple success json
        return response()->json('success', 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id resource id
     * @return \Illuminate\Http\Response
     */
    public function destroyStatus($id) {

        //get record
        if (!\Modules\Grow\Models\TicketStatus::find($id)) {
            abort(409, __('lang.error_request_could_not_be_completed'));
        }

        //get it in useful format
        $statuses = $this->statusrepo->search($id);
        $status = $statuses->first();

        //validation: default
        if ($status->ticketstatus_system_default == 'yes') {
            abort(409, __('lang.you_cannot_delete_system_default_item'));
        }

        //validation: default
        if ($status->count_tickets != 0) {
            abort(409, __('lang.ticket_status_not_empty'));
        }

        //delete the category
        $status->delete();

        //reponse payload
        $payload = [
            'status_id' => $id,
        ];

        //process reponse
        return new DestroyStatusResponse($payload);
    }

    /**
     * show the form to edit a resource
     *
     * @return \Illuminate\Http\Response
     */
    public function categoryIMAPSettings($id) {

        //get the categor
        if (!$category = \Modules\Grow\Models\Category::Where('category_id', $id)->Where('category_type', 'ticket')->first()) {
            abort(404);
        }

        $settings = \Modules\Grow\Models\Settings2::find(1);

        //reponse payload
        $payload = [
            'response' => 'show',
            'category' => $category,
            'settings' => $settings,
        ];

        //response
        return new CategoryIMAPSettingsResponse($payload);
    }

    /**
     * show the form to edit a resource
     *
     *    -------------------------------------------
     *    imap_status              - category_meta_4
     *    email                    - category_meta_5
     *    username                 - category_meta_6
     *    password                 - category_meta_7
     *    host                     - category_meta_8
     *    port                     - category_meta_9
     *    encryption               - category_meta_10
     *    post_action              - category_meta_11
     *    mailbox_id               - category_meta_12
     *    last_fetched_mail_id     - category_meta_13
     *    fecthing_status          - category_meta_14
     *    last_fetched_timestamp   - category_meta_22
     *    last_fetched_date        - category_meta_2
     *    last_fetched_count       - category_meta_23
     *    email_total_count        - category_meta_24
     *    -------------------------------------------
     *
     * @return \Illuminate\Http\Response
     */
    public function updateCategoryIMAPSettings(CategoryIMAPIntegration $request, $id) {

        //get the categor
        if (!$category = \Modules\Grow\Models\Category::Where('category_id', $id)->Where('category_type', 'ticket')->first()) {
            abort(404);
        }

        if (!request()->filled('category_email_integration')) {
            abort(409, __('lang.email_integration_status') . ' - ' . __('lang.is_required'));
        }

        //update imap settings
        if (request('category_email_integration') == 'disabled') {
            $category->category_meta_4 = 'disabled';
            $category->category_meta_5 = '';
            $category->category_meta_6 = '';
            $category->category_meta_7 = '';
            $category->category_meta_8 = '';
            $category->category_meta_9 = '';
            $category->category_meta_10 = '';
            $category->category_meta_11 = '';
            $category->save();
        }

        //update imap settings
        if (request('category_email_integration') == 'enabled') {

            //validate duplicate email address
            if (\Modules\Grow\Models\Category::Where('category_meta_5', request('category_meta_5'))->Where('category_id', '!=', $category->category_id)->exists()) {
                abort(409, __('lang.email_already_linked_to_department'));
            }

            //save
            $category->category_meta_4 = 'enabled';
            $category->category_meta_5 = request('category_meta_5');
            $category->category_meta_6 = request('category_meta_5');
            $category->category_meta_7 = request('category_meta_7');
            $category->category_meta_8 = request('category_meta_8');
            $category->category_meta_9 = request('category_meta_9');
            $category->category_meta_10 = request('category_meta_10');
            $category->category_meta_11 = request('category_meta_11');
            $category->save();
        }

        //reponse payload
        $payload = [
            'response' => 'update',
        ];

        //response
        return new CategoryIMAPSettingsResponse($payload);
    }

    /**
     * test an iamp connection
     *
     * Category Table Column Mapping
     * These are additional columns in the category table that are used for tickets
     *    -------------------------------------------
     *    imap_status              - category_meta_4
     *    email                    - category_meta_5
     *    username                 - category_meta_6
     *    password                 - category_meta_7
     *    host                     - category_meta_8
     *    port                     - category_meta_9
     *    encryption               - category_meta_10
     *    post_action              - category_meta_11
     *    mailbox_id               - category_meta_12
     *    last_fetched_mail_id     - category_meta_13
     *    fecthing_status          - category_meta_14
     *    last_fetched_timestamp   - category_meta_22
     *    last_fetched_date        - category_meta_2
     *    last_fetched_count       - category_meta_23
     *    email_total_count        - category_meta_24
     *    -------------------------------------------
     *
     * @return \Illuminate\Http\Response
     */
    public function testImapConnection(CategoryIMAPIntegration $request, TicketsImapRepository $imaprepo) {

        //connection data
        $data = [
            'host' => request('category_meta_8'),
            'port' => request('category_meta_9'),
            'username' => request('category_meta_6'),
            'password' => request('category_meta_7'),
            'encryption' => request('category_meta_10'),
        ];

        //test connection
        $result = $imaprepo->testConnection($data);

        //reponse payload
        $payload = [
            'result' => $result,
        ];

        //response
        return new TestConnectionResponse($payload);
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
                __('lang.tickets'),
            ],
            'crumbs_special_class' => 'main-pages-crumbs',
            'page' => 'settings',
            'meta_title' => __('lang.settings'),
            'heading' => __('lang.settings'),
            'settingsmenu_general' => 'active',
        ];

        //default modal settings (modify for sepecif sections)
        $page += [
            'add_modal_title' => __('lang.add_new_ticket_status'),
            'add_modal_create_url' => url('settings/tickets/statuses/create'),
            'add_modal_action_url' => url('settings/tickets/statuses/create'),
            'add_modal_action_ajax_class' => '',
            'add_modal_action_ajax_loading_target' => 'commonModalBody',
            'add_modal_action_method' => 'POST',
        ];

        config([
            //visibility - add project buttton
            'visibility.list_page_actions_add_button' => true,
        ]);

        //create new resource
        if ($section == 'statuses') {
            $page['crumbs'] = [
                __('lang.settings'),
                __('lang.tickets'),
                __('lang.statuses'),
            ];
        }

        return $page;
    }

}
