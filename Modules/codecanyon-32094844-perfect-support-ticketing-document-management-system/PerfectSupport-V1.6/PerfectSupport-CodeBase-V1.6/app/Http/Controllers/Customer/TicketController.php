<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Product;
use App\Ticket;
use App\System;
use App\CannedResponse;
use Illuminate\Support\Facades\DB;
use App\TicketSupportAgent;
use Carbon\Carbon;
use App\User;
use App\Notifications\Customer\NewTicketAdded;
use App\License;
use App\Http\Util\CommonUtil;
use App\ProductSource;
use App\ProductDepartment;

class TicketController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    /**
     * Constructor.
     *
     * @param CommonUtil
     */
    public function __construct(CommonUtil $commonUtil)
    {
        $this->CommonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {   
        if (!auth()->user()->can('customer')) {
            abort(403, 'Unauthorized action.');
        }

        $tickets = Ticket::with('product', 'supportAgents', 'productDepartment', 'productDepartment.department')
                    ->where('user_id', \Auth::id())
                    ->orderBy('updated_at', 'desc')
                    ->paginate(30);

        return Inertia::render('Customer/Ticket/Index', compact('tickets'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!auth()->user()->can('customer')) {
            abort(403, 'Unauthorized action.');
        }

        //All active products
        $products = Product::active()->get();
        $user = \Auth::user();
        $instruction = System::getProperty('ticket_instruction');
        $is_public_ticket_enabled = System::getProperty('is_public_ticket_enabled');
        $default_ticket_type = System::getProperty('default_ticket_type');
        $priorities = Ticket::priorities();

        //used in front-end to check whether to call server for licence check
        $source_count_by_product = ProductSource::select(\DB::raw('count(source_id) as source_count, product_id'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        return Inertia::render('Customer/Ticket/Create', compact('products', 'instruction', 'user', 'is_public_ticket_enabled', 'default_ticket_type', 'priorities', 'source_count_by_product'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {   
        if (!auth()->user()->can('customer')) {
            abort(403, 'Unauthorized action.');
        }

        //check if ticket with same subject exist in db
        if (
            $this->__isTicketWithSameSubject($request->input('subject'), $request->user()->id)
        ) {
            return redirect()->route('customer.tickets.index')
                ->with('error', __('messages.ticket_with_same_subject'));
        }

        //if license available check if support expires or not
        if (
            $this->__isSupportExpired($request->input('license_id'))
        ) {
            return redirect()->route('customer.tickets.create')
                ->with('error', __('messages.support_expired_plz_renew'));
        }
        
        try {
            $input = $request->only(['product_id', 'license_id', 'subject', 'message',
                        'other_info', 'is_public', 'product_department_id', 'custom_field_1',
                        'custom_field_2', 'custom_field_3', 'custom_field_4', 'custom_field_5',
                        'custom_field_6', 'custom_field_7', 'custom_field_8', 'custom_field_9',
                        'custom_field_10']);

            $input['user_id'] = $request->user()->id;
            $input['ticket_ref'] = $this->__generateRefNumber('ticket');
            $input['last_updated_by'] = $request->user()->id;
            $input['priority'] = !empty($request->input('priority')) ? $request->input('priority') : 'medium';
            $input['status'] = Ticket::defaultStatus();

            $ticket = Ticket::create($input);

            //assign support agent
            $agent_id = $this->__getLastAssignedSupportAgent($request->input('product_id'), $request->input('product_department_id'));
            TicketSupportAgent::create([
                'ticket_id' => $ticket->id,
                'user_id' => $agent_id
            ]);

            //Send notification to customer & agent about ticket
            $this->__sendNewTicketAddedNotificationToCustomer($ticket, $ticket->user_id);
            $this->CommonUtil->sendTicketAssignedNotificationToAgents($ticket, $agent_id);

            return redirect()->route('customer.tickets.index')->with('success', __('messages.success'));
        } catch (\Exception $e) {
            return redirect()->route('customer.tickets.index')->with('error', __('messages.something_went_wrong'));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {   
        $user_id = \Auth::id();
        $is_supportagent = auth()->user()->can('support_agent');
        $query = Ticket::with(['product', 'license', 'license.source', 'user', 'productDepartment', 'productDepartment.department']);
        
        //if user is agent, check ticket is assigned or not
        if ($is_supportagent) {
            $query->whereHas('supportAgents', function ($q) use ($user_id) {
                $q->where('user_id', $user_id);
            });
        }

        $ticket = $query->findOrFail($id);

        //if ticket is not public check viewer is admin or not
        if (!$ticket->is_public && !(auth()->user()->can('admin'))) {
            //if viewer is not creator abort viewing ticket
            if (auth()->user()->can('customer') && $ticket->user_id !== \Auth::id()) {
                abort(403, 'Unauthorized action.');
            }
        }

        //Send license info only to the customers or superadmin. Remove for support agent & public viewing.
        if($is_supportagent || (!auth()->user()->can('admin') && $user_id != $ticket->user_id)){
            if(!empty($ticket['license'])) {
                
                $source_info = $ticket['license']['source'];
                unset($ticket['license']);

                $ticket['license'] = ['source' => $source_info];

                unset($ticket['user']);
            }
        }

        //get & replace tag from canned responses & send only if agent/admin
        $canned_responses = [];
        if (auth()->user()->can('admin') || auth()->user()->can('support_agent')) {
            $responses = CannedResponse::getCannedResponses(auth()->user()->role);
            $canned_responses = $this->__replaceCannedResponseTags($responses, $ticket);
        }

        //other required resources
        $ticket['queue_num'] = $this->__generateQueueNumber($ticket);
        $statuses = Ticket::statuses();
        $user = \Auth::user();
        $mail_notif_to_customer = System::getProperty('agent_replied_to_ticket_mail_notif');
        $mail_notif_to_other_agents = System::getProperty('other_agents_replied_to_ticket_mail_notif');
        $labels = $this->CommonUtil->getTicketLabels();
        $support_agents = User::getSupportAgentsDropdown();
        $ticket_agents = $ticket->supportAgents->pluck('id')->toArray();
        $default_reply = $this->_getDefaultReply($ticket);

        return Inertia::render('Customer/Ticket/Show', compact('ticket', 'user', 'statuses',
            'canned_responses', 'mail_notif_to_customer', 'labels', 'support_agents',
            'ticket_agents', 'default_reply', 'mail_notif_to_other_agents'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function _getDefaultReply($ticket)
    {
        $signature = '';
        if (\Auth::check()) {
            $signature = System::getProperty('signature');
            if (strpos($signature, '{name}') !== false) {
                $signature = str_replace('{name}', auth()->user()->name, $signature);
            }
            if (strpos($signature, '{email}') !== false) {
                $signature = str_replace('{email}', auth()->user()->email, $signature);
            }
            if (strpos($signature, '{role}') !== false) {
                $signature = str_replace('{role}',__('messages.'.auth()->user()->role), $signature);
            }
            if (strpos($signature, '{customer_name}') !== false && isset($ticket->user)) {
                $signature = str_replace('{customer_name}', $ticket->user->name, $signature);
            }
            if (strpos($signature, '{product_name}') !== false && isset($ticket->product)) {
                $signature = str_replace('{product_name}', $ticket->product->name, $signature);
            }
        }
        return $signature;
    }

    private function __generateRefNumber($ref_type)
    {
        $ref_prefix = System::getProperty('ticket_prefix');
        $ref_count = System::setAndGetReferenceCount($ref_type);
        $ref_num = System::generateReferenceNumber($ref_count, $ref_prefix);
        
        return $ref_num;
    }

    private function __replaceCannedResponseTags($canned_responses, $ticket)
    {
        foreach ($canned_responses as $key => $value) {
            //inserts HTML line breaks in front of each newline in a string
            $canned_responses[$key] = nl2br($canned_responses[$key]);
            if (strpos($value, '{customer_name}') !== false) {
                $customer_name = optional($ticket->user)->name;
                $canned_responses[$key] = str_replace('{customer_name}', $customer_name, $canned_responses[$key]);
            }

            if (strpos($value, '{product_name}') !== false) {
                $product = optional($ticket->product)->name;
                $canned_responses[$key] = str_replace('{product_name}', $product, $canned_responses[$key]);
            }

            if (strpos($value, '{ticket_ref}') !== false) {
                $canned_responses[$key] = str_replace('{ticket_ref}', $ticket->ticket_ref, $canned_responses[$key]);
            }

            if (strpos($value, '{status}') !== false) {
                $canned_responses[$key] = str_replace('{status}', __('messages.'.$ticket->status), $canned_responses[$key]);
            }

            if (strpos($value, '{purchased_date}') !== false) {
                $purchased_date = optional($ticket->license)->purchased_on;
                $canned_responses[$key] = str_replace('{purchased_date}', $purchased_date, $canned_responses[$key]);
            }

            if (strpos($value, '{support_expiry_date}') !== false) {
                $support_expiry_date = optional($ticket->license)->support_expires_on;
                $canned_responses[$key] = str_replace('{support_expiry_date}', $support_expiry_date, $canned_responses[$key]);
            }

            if (strpos($value, '{expiry_date}') !== false) {
                $expiry_date = optional($ticket->license)->expires_on;
                $canned_responses[$key] = str_replace('{expiry_date}', $expiry_date, $canned_responses[$key]);
            }
        }

        return $canned_responses;
    }

    private function __getLastAssignedSupportAgent($product_id, $product_department_id = null)
    {
        
        //assign department agent to ticket if department exist else assign product support agent
        $department = ProductDepartment::with('supportAgents')
                        ->find($product_department_id);
        $agents = !empty($department) ? $department->supportAgents->pluck('id')->toArray() : [];
        if (empty($agents)) {

            $product = Product::with('supportAgents')
                    ->findOrFail($product_id);

            $agents = $product->supportAgents->pluck('id')->toArray();
        }

        $assigned_agents = TicketSupportAgent::whereIn('user_id', $agents)
                            ->select(DB::raw('MAX(created_at) as created_at'),
                            'user_id')
                            ->groupBy('user_id')
                            ->get();

        //select the least assigned date of agents
        $user_id = null;
        $created_at = null;
        foreach ($assigned_agents as $agent) {
            if (is_null($created_at)) {
                $created_at = $agent->created_at;
                $user_id = $agent->user_id;
            }
            if (!empty($created_at) && $agent->created_at->lessThanOrEqualTo($created_at)) {
                $created_at = $agent->created_at;
                $user_id = $agent->user_id;
            }
        }

        //check if any agent is not assigned any ticket and assigned them
        $assigned = $assigned_agents->pluck('user_id')->toArray();
        $not_assigned_agents = array_diff($agents, $assigned);

        if (empty($assigned_agents)) {
            return $agents[0];
        } else if (!empty($not_assigned_agents)) {
            return head($not_assigned_agents);;
        } elseif (!empty($user_id)) {
            return $user_id;
        }
    }

    /**
     * send notification to customer
     * about new ticket
     *
     * @param  int  $user_id, obj $ticket
     *
     * @return \Illuminate\Http\Response
     */
    private function __sendNewTicketAddedNotificationToCustomer($ticket, $user_id)
    {   
        $system = System::whereIn('key', ['cust_new_ticket_app_notif',
                        'cust_new_ticket_mail_notif'])
                        ->pluck('value', 'key');

        if (!empty($system) && ($system['cust_new_ticket_app_notif'] || $system['cust_new_ticket_mail_notif']) && !empty($user_id)) {
            $user = User::find($user_id);
            $user->notify(new NewTicketAdded($ticket, $system));
        }
    }

    public function getPublicTickets()
    {
        $is_public_ticket_enabled = System::getProperty('is_public_ticket_enabled');

        //if public ticket disabled abort view
        if (!$is_public_ticket_enabled) {
            abort(403, 'Unauthorized action.');
        }

        $filters['search'] = request()->get('search', '');

        $query = Ticket::with(['product', 'supportAgents', 'comments', 'productDepartment', 'productDepartment.department'])
                    ->where('is_public', 1);

        //search ticket
        $search = $filters['search'];
        if (!empty($search)) {
            $query->where(function($q) use($search) {
                $q->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('message', 'like', '%'.$search.'%')
                    ->orWhere('other_info', 'like', '%'.$search.'%')
                    ->orWhereHas('comments', function($q) use($search) {
                        $q->where('comment', 'like', '%'.$search.'%');
                    });
            });
        }

        $tickets = $query->orderBy('updated_at', 'desc')
                    ->paginate(30);

        return Inertia::render('Customer/Ticket/Public/Index', compact('tickets', 'filters'));
    }

    public function viewPublicTicket($id)
    {
        $is_public_ticket_enabled = System::getProperty('is_public_ticket_enabled');

        //if public ticket disabled abort view
        if (!$is_public_ticket_enabled) {
            abort(403, 'Unauthorized action.');
        }

        $ticket = Ticket::where('is_public', 1)
                    ->where('id', $id)
                    ->select('id', 'subject', 'message', 'other_info', 'status', 'product_id',
                        'is_commentable', 'user_id', 'updated_at')
                    ->firstOrFail();

        $ticket['queue_num'] = $this->__generateQueueNumber($ticket);
        $default_reply = $this->_getDefaultReply($ticket);
        return Inertia::render('Customer/Ticket/Public/Show', compact('ticket', 'default_reply'));
    }

    public function getTicketsSuggestion(Request $request)
    {   
        if (!auth()->user()->can('customer')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            //get ticket suggestions based on search params
            $search_params = $request->input('search_params');
            $tickets = $this->CommonUtil->getTicketsSuggestion($search_params);
            $documentations = $this->CommonUtil->getDocsSuggestion($search_params);

            //store tickets links
            $ticket_links = [];
            foreach ($tickets as $key => $ticket) {
                $ticket_links[] = [
                    'url' => route('customer.view-public-ticket', $ticket->id),
                    'title' => $ticket->subject,
                    'type' => 'ticket'
                ];
            }

            //store docs links
            $doc_links = [];
            foreach ($documentations as $key => $documentation) {
                if ($documentation->doc_type == 'doc') {
                    $url = route('view.documentation', ['slug' => \Str::slug($documentation->title, '-'), 'documentation' => $documentation->id]);
                } elseif ($documentation->doc_type == 'section') {
                    $url = route('view.documentation.section', ['slug' => \Str::slug($documentation->title, '-'), 'documentation' => $documentation->id]);
                } elseif ($documentation->doc_type == 'article') {
                    $url = route('view.section.article', ['slug' => \Str::slug($documentation->title, '-'), 'documentation' => $documentation->id]);
                }

                $doc_links[] = [
                    'url' => $url,
                    'title' => $documentation->title,
                    'type' => 'doc'
                ];
            }

            //merge doc & ticket links
            $suggestion_links = array_merge($doc_links, $ticket_links);

            return $this->respondSuccess([
                'suggestions' => $suggestion_links,
            ]);
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function getTicketListForTicketView($customer_id)
    {   
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }
        
        try {
            $tickets = Ticket::where('user_id', $customer_id)
                        ->whereNotIn('id', [request()->get('ticket_id')])
                        ->select('id', 'ticket_ref', 'status', 'subject', 'created_at', 'priority')
                        ->latest()
                        ->get();

            return $this->respondSuccess(['tickets' => $tickets]);
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function getPurchaseListForTicketView($customer_id)
    {   
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $purchases = License::where('licenses.user_id', $customer_id)
                            ->join('products', 'licenses.product_id', '=', 'products.id')
                            ->select('products.name as product', 'license_key', 'additional_info',
                            'purchased_on', 'support_expires_on', 'expires_on')
                            ->get();

            return $this->respondSuccess(['purchases' => $purchases]);
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));   
        }
    }

    private function __generateQueueNumber($ticket)
    {
        $queue_num = Ticket::where('status', '!=', 'closed')
                    ->where('updated_at', '<=', $ticket->updated_at)
                    ->count();

        return $queue_num;
    }

    public function departmentDropdown($product_id)
    {
        if (!auth()->user()->can('customer')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            
            $departments = ProductDepartment::getDepartmentForProduct($product_id);
            
            return $this->respondSuccess(['departments' => $departments]);
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));   
        }
    }

    public function getProductDepartmentInfo($department_id)
    {
        if (!auth()->user()->can('customer')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $department = ProductDepartment::findOrFail($department_id);

            $tickets = [];
            if (!empty($department) && $department->show_related_public_ticket) {
                $tickets = Ticket::where('product_department_id', $department->id)
                            ->where('is_public', 1)
                            ->select(['id', 'subject'])
                            ->get()
                            ->toArray();
            }
            return $this->respondSuccess([
                'information' => $department->information,
                'tickets' => $tickets,
                'department_id' => $department->department_id,
                'pd_id' => $department->id
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));   
        }
    }

    private function __isTicketWithSameSubject($subject, $user_id)
    {
        $ticket = Ticket::where('subject', $subject)
                    ->where('user_id', $user_id)
                    ->first();

        return !empty($ticket);
    }

    private function __isSupportExpired($license_id)
    {
        $license = License::find($license_id);
        
        return !empty($license) && Carbon::now()->greaterThanOrEqualTo(Carbon::parse($license->support_expires_on));
    }
}
