<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Ticket;
use App\User;
use App\TicketSupportAgent;
use Inertia\Inertia;
use App\Product;
use App\Http\Util\CommonUtil;
use App\System;
use App\TicketNote;
use App\ProductDepartment;
use Carbon\Carbon;
use App\Exports\TicketsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
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
    public function index(Request $request)
    {   
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        $filters = $this->__getTicketFilters($request);
        $tickets = $this->CommonUtil->getTickets($filters);

        $filterStatuses = Ticket::statusForSelect2();
        $filterPriorities = Ticket::priorities();
        $filterProducts = Product::getDropdown();
        $filterLabels = $this->CommonUtil->getTicketLabels();
        $productDepartments = ProductDepartment::getDepartmentForProduct();
        $agents = User::getSupportAgentsDropdown();

        return Inertia::render('Ticket/Index', compact('tickets', 'filters', 'filterStatuses',
            'filterPriorities', 'filterProducts', 'filterLabels', 'productDepartments',
            'agents'));
    }

    private function __getTicketFilters($request)
    {
        $filters['search'] = $request->get('search', '');
        $filters['status'] = $request->get('status', ['new', 'waiting', 'pending', 'open']);
        $filters['is_public'] = $request->get('is_public', '');
        $filters['start_date'] = $request->get('start_date', null);
        $filters['end_date'] = $request->get('end_date', null);
        $filters['priority'] = $request->get('priority', '');
        $filters['product'] = $request->get('product', '');
        $filters['last_replied_by'] = $request->get('last_replied_by', '');
        $filters['label'] = $request->get('label', '');
        $filters['p_department'] = $request->get('p_department', '');
        $filters['support_agent'] = $request->get('support_agent', '');
        $filters['closed_by'] = $request->get('closed_by', '');
        $filters['closed_on_start_date'] = $request->get('closed_on_start_date', null);
        $filters['closed_on_end_date'] = $request->get('closed_on_end_date', null);

        //search fields config
        if (!empty($request->input('search_fields'))) {
            $search_fields = $request->input('search_fields');
            $filters['search_fields'] = !is_array($search_fields) ? json_decode($search_fields, true) : $search_fields;
        } else {
            $filters['search_fields'] = [
                'ref_num' => true,
                'subject' => true,
                'body' => true,
                'customer' => true,
                'comments' => true,
            ];
        }

        return $filters;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
    public function massDestroy(Request $request)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $ticket_ids = $request->input('ticket_ids');
            $filters = json_decode($request->input('filters'), true);

            if (!empty($ticket_ids)) {
                Ticket::whereIn('id', $ticket_ids)
                    ->delete();
            }
            return redirect()->action('TicketController@index', $filters)->with('success', __('messages.success'));
        } catch (\Exception $e) {
            return redirect()->action('TicketController@index')->with('error', __('messages.something_went_wrong'));
        }
    }

    /**
     * Show the form for assigning support agents.
     *
     * @return \Illuminate\Http\Response
     */
    public function getEditableTickets(Request $request)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $ticket_ids = json_decode($request->get('ticket_ids'));
            $support_agents = User::getSupportAgentsDropdown();
            $tickets = Ticket::whereIn('id', $ticket_ids)
                        ->get();
            $statuses = Ticket::statuses();
            $priorities = Ticket::priorities();
            $labels = $this->CommonUtil->getTicketLabels();
            $productDepartments = ProductDepartment::getDepartmentForProduct();
            return $this->respondSuccess([
                'support_agents' => $support_agents,
                'tickets' => $tickets,
                'statuses' => $statuses,
                'priorities' => $priorities,
                'labels' => $labels,
                'product_departments' => $productDepartments
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    /**
     * assign support agents to tickets
     *
     * @return \Illuminate\Http\Response
     */
    public function postEditableTickets(Request $request)
    {   
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $agent_ids = $request->input('agent_id');
            $ticket_ids = $request->input('ticket_ids');

            $ticket_data = [];

            if (!empty($request->input('status'))) {
                $ticket_data['status'] = $request->input('status');
            }

            if (!empty($request->input('priority'))) {
                $ticket_data['priority'] = $request->input('priority');
            }
            
            if (!empty($request->input('product_department_id'))) {
                $ticket_data['product_department_id'] = $request->input('product_department_id');
            }
            
            if (!empty($request->input('labels')) || !empty($request->input('new_label'))) {
                $ticket_data['labels'] = $request->input('labels', []);
                if (!empty($request->input('new_label')) &&
                    $this->CommonUtil->checkIfLabelExistAndAddLabel($request->input('new_label'))) {
                    array_push($ticket_data['labels'], $request->input('new_label'));
                }
            }

            foreach ($ticket_ids as $key => $ticket_id) {

                //update ticket status if status is available
                if (!empty($ticket_data)) {

                    $ticket = Ticket::find($ticket_id);

                    if (!empty($ticket_data['labels']) && !empty($ticket->labels)) {
                        $ticket_data['labels'] = \Arr::shuffle(array_unique(array_merge($ticket_data['labels'], $ticket->labels)));
                    }

                    //if status is closed set date & id of user
                    if (
                      !empty($request->input('status')) &&
                      (
                        $request->input('status') == 'closed'
                     )
                    ) {
                        $ticket_data['closed_by'] = auth()->user()->id;
                        $ticket_data['closed_on'] = Carbon::now();
                    } else {
                        $ticket_data['closed_by'] = null;
                        $ticket_data['closed_on'] = null;
                    }
                    
                    $ticket->update($ticket_data);
                }

                //assign agent to ticket if aagent id are not empty
                if (!empty($agent_ids)) {
                    foreach ($agent_ids as $key => $agent_id) {
                        $assigned_agent  = TicketSupportAgent::where('ticket_id', $ticket_id)
                            ->where('user_id', $agent_id)
                            ->first();

                        if (empty($assigned_agent)) {
                            TicketSupportAgent::create([
                                'ticket_id' => $ticket_id,
                                'user_id' => $agent_id
                            ]);
                        }
                    }
                }
            }

            return redirect()->action('TicketController@index')->with('success', __('messages.success'));
        } catch (Exception $e) {
            return redirect()->action('TicketController@index')->with('error', __('messages.something_went_wrong'));
        }
    }

    public function updateTicket(Request $request)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $ticket_id = $request->input('ticket_id');
            $is_public = $request->input('is_public');
            $is_commentable = $request->input('is_commentable');
            $labels = $this->CommonUtil->updateNewLabelsInSystem($request->input('ticket_labels'));

            $ticket = Ticket::findOrFail($ticket_id);
            $ticket->is_public = !empty($is_public) ? 1 : 0;
            $ticket->is_commentable = !empty($is_commentable) ? 1 : 0;
            $ticket->labels = $labels;
            $ticket->save();

            return $this->respond($ticket);
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function updateSupportAgentsForTicket(Request $request)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $ticket_id = $request->input('ticket_id');
            $agent_ids = $request->input('agent_ids');

            $ticket = Ticket::findOrFail($ticket_id);

            $existing_agent = $ticket->supportAgents->pluck('id')->toArray();

            $ticket->supportAgents()->sync($agent_ids);

            $notifiable = array_diff($agent_ids,$existing_agent);

            if (!empty($notifiable)) {
                $this->CommonUtil->sendTicketAssignedNotificationToAgents($ticket, $notifiable);
            }
            
            return $this->respondSuccess();
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function storeNote(Request $request)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['ticket_id', 'note', 'customer_id']);
            $input['added_by'] = \Auth::id();

            $ticket_note = TicketNote::create($input);
            $ticket_note->load('addedBy');
            return $this->respondSuccess(['note' => $ticket_note]);
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function deleteNote(Request $request, $id)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $ticket_note = TicketNote::findOrFail($id);
            $ticket_note->delete();

            return $this->respondSuccess();
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function getCustomerNotes($customer_id, $ticket_id)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $ticket_notes = TicketNote::with('addedBy')
                            ->where('customer_id', $customer_id)
                            ->orWhere('ticket_id', $ticket_id)
                            ->get();

            return $this->respondSuccess(['notes' => $ticket_notes]);
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function export(Request $request)
    {
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $filters = $this->__getTicketFilters($request);
        return Excel::download(new TicketsExport($filters), 'Tickets.xlsx');
    }

    public function updateTicketCustomFields(Request $request, $id)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

                Ticket::where('id', $id)
                    ->update($request->all());

            DB::commit();
            return $this->respondSuccess([], __('messages.updated_successfully'));
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function getCompletedTickets(Request $request)
    {
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $filters = $this->__getTicketFilters($request);
        $filters['status'] = ['closed'];
        $tickets = $this->CommonUtil->getTickets($filters);

        $filterProducts = Product::getDropdown();
        $filterLabels = $this->CommonUtil->getTicketLabels();
        $productDepartments = ProductDepartment::getDepartmentForProduct();
        $agents = User::getSupportAgentsDropdown();

        return Inertia::render('Reports/Index', compact('tickets', 'filters', 'filterProducts',
        'filterLabels', 'productDepartments', 'agents'));
    }

    public function getTicketComments(Request $request)
    {
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $filters = $this->__getTicketFilters($request);

        if(empty($filters['start_date']) || empty($filters['end_date'])) {
            $filters['start_date'] = Carbon::now()->subDays(6)->toDateString();
            $filters['end_date'] = Carbon::now()->toDateString();
        }

        $comments = $this->CommonUtil->getComments($filters);

        $filterProducts = Product::getDropdown();
        $agents = User::getSupportAgentsDropdown();

        return Inertia::render('Reports/Comments', compact('comments', 'filters', 'filterProducts', 'agents'));
    }
}
