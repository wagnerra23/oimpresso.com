<?php
namespace App\Http\Util;
use App\Ticket;
use Illuminate\Support\Facades\DB;
use App\Documentation;
use App\ProductSupportAgent;
use App\System;
use App\User;
use App\TicketComment;
use App\Notifications\SupportAgent\NewTicketAssigned;
use Notification;
use DateTimeZone;

class CommonUtil
{
	public function getTicketsSuggestion($search_params)
	{	
		$query_string = $this->__getTicketSearchSqlQuery($search_params);

		$tickets = Ticket::select('id', 'subject',
		            DB::raw('(select('.$query_string.')) as search_matched')
		            )
		            ->where('is_public', 1)
		            ->having('search_matched', '>', 0)
		            ->orderBy('search_matched', 'desc')
		            ->get();

		return $tickets;
	}

	private function __getTicketSearchSqlQuery($search_params)
    {   
        $query_string = '';
        if (!empty($search_params)) {
            $search_params = explode(' ', $search_params);

            $searchValues = collect($search_params)->map(function ($search_param, $key) {
                    return strtolower($search_param);
                })->filter(function ($search_param, $key) {
                    return strlen($search_param) > 2;
                });

            $counter = 0;
            foreach ($searchValues as $key => $searchValue) {
                $query_string .= 'ROUND(
                              (
                                   LENGTH(subject)- LENGTH(REPLACE(LOWER(subject),'. '"'.$searchValue.'", ""))
                              ) / LENGTH("'.$searchValue.'")
                         )';
               if($counter != count($searchValues) - 1) {
                    $query_string .= "+";
               }
               $counter ++;
            }
        }

        if (strlen($query_string) <= 0) {
            $query_string = 'ROUND((LENGTH(subject)- LENGTH(REPLACE(subject, "", "")) ) / LENGTH(""))';
        }

        return $query_string;
    }

    public function getDocsSuggestion($search_params)
    {	
    	$query_string = $this->__getDocsSearchSqlQuery($search_params);

    	$docs = Documentation::select('id', 'title', 'doc_type', 
    				DB::raw('(select('.$query_string.')) as search_matched'))
		            ->having('search_matched', '>', 0)
		            ->orderBy('search_matched', 'desc')
		            ->get();

		return $docs;
    }
    private function __getDocsSearchSqlQuery($search_params)
    {
        $query_string = '';
        if (!empty($search_params)) {
            $search_params = explode(' ', $search_params);

            $searchValues = collect($search_params)->map(function ($search_param, $key) {
                    return strtolower($search_param);
                })->filter(function ($search_param, $key) {
                    return strlen($search_param) > 2;
                });

            $counter = 0;
            foreach ($searchValues as $key => $searchValue) {
                $query_string .= 'ROUND(
                              (
                                   LENGTH(title)- LENGTH(REPLACE(LOWER(title),'. '"'.$searchValue.'", ""))
                              ) / LENGTH("'.$searchValue.'")
                         )';
               if($counter != count($searchValues) - 1) {
                    $query_string .= "+";
               }
               $counter ++;
            }
        }

        if (strlen($query_string) <= 0) {
            $query_string = 'ROUND((LENGTH(title)- LENGTH(REPLACE(title, "", "")) ) / LENGTH(""))';
        }

        return $query_string;
    }

    public function geCustomerPurchasesProductIds($customer_id)
    {   
        $product_ids = Ticket::where('user_id', $customer_id)
                        ->where('status', '!=', 'closed')
                        ->pluck('product_id')
                        ->toArray();

        return array_unique($product_ids);

    }

    public function getAssignedProductIdsForAgent($user_id)
    {
        $product_ids = ProductSupportAgent::where('user_id')
                        ->pluck('product_id')
                        ->toArray();

        return array_unique($product_ids);
    }

    public function getCronJobCommand()
    {
        $php_binary_path = empty(PHP_BINARY) ? "php" : PHP_BINARY;

        $command = "* * * * * " . $php_binary_path . " " . base_path('artisan') . " schedule:run >> /dev/null 2>&1";

        if (config('app.env') == 'demo') {
            $command = '';
        }

        return $command;
    }
    
    public function checkIfLabelExistAndAddLabel($new_label)
    {
        $labels = System::getProperty('labels');
        
        if (empty(json_decode($labels, true)) || !in_array($new_label, json_decode($labels, true))) {
            $labels = !empty($labels) ? json_decode($labels, true) : [];
            array_push($labels, $new_label);
            System::createOrUpdateProperty('labels', json_encode($labels));
            return true;
        }
        return false;
    }

    public function getTicketLabels()
    {
        $labels = !empty(json_decode(System::getProperty('labels'), true)) ? json_decode(System::getProperty('labels'), true) : [];
        
        return $labels;
    }

    public function convertLabelsObjectIntoSingleArray($labels)
    {   
        $flattened = [];
        $labels_arr = json_decode($labels, true);
        if (!empty($labels_arr) && is_array($labels_arr)) {
            $flattened =  \Arr::shuffle(array_unique(\Arr::flatten($labels_arr)));
        }

        return $flattened;
    }

    public function updateNewLabelsInSystem($labels)
    {
        $arr_of_labels = $this->convertLabelsObjectIntoSingleArray($labels);        

        if (!empty($arr_of_labels)) {
            $labels = System::getProperty('labels');
            $arr_of_existing_labels = !empty($labels) ? json_decode($labels, true) : [];
            $new_labels = \Arr::shuffle(array_unique(array_merge($arr_of_labels, $arr_of_existing_labels)));
            System::createOrUpdateProperty('labels', json_encode($new_labels));
        }

        return $arr_of_labels;
    }

    /**
     * send notification to support agent
     * about ticket assigned
     *
     * @param  int  $user_id, obj $ticket
     *
     */
    public function sendTicketAssignedNotificationToAgents($ticket, $user_id)
    {
        $system = System::whereIn('key', ['agent_assigned_ticket_app_notif',
                        'agent_assigned_ticket_mail_notif'])
                        ->pluck('value', 'key');

        if (!empty($system) && ($system['agent_assigned_ticket_app_notif'] || $system['agent_assigned_ticket_mail_notif']) && !empty($user_id)) {
            $users = User::find($user_id);
            Notification::send($users, new NewTicketAssigned($ticket, $system));
        }
    }

    /**
     * Return All Docs
     */
    public function getAllDocs()
    {
        $documentations = Documentation::with(['sections' => function($q) {
                                $q->orderBy('sort_order', 'asc');
                            }, 'sections.articles' => function($q) {
                                $q->orderBy('sort_order', 'asc');
                            }])
                            ->where('doc_type', 'doc')
                            ->latest()
                            ->get();
                            
        return $documentations;
    }

    /**
     * Gives a list of all timezone
     *
     * @return array
     */
    public function allTimeZones()
    {
        $timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        $timezone_list = [];
        foreach ($timezones as $timezone) {
            $timezone_list[$timezone] = $timezone;
        }
        
        return $timezone_list;
    }

    /**
     * Return tickets based on
     * given filter parameter
     *
     * @return \Illuminate\Http\Response
     */
    public function getTickets($filters, $paginate = true)
    {
        $query = Ticket::with(['product', 'user', 'supportAgents', 'comments', 
                'productDepartment', 'productDepartment.department', 'closedBy'])
                ->leftJoin('users', 'tickets.last_updated_by', '=', 'users.id')
                ->leftJoin('licenses', 'tickets.license_id', '=', 'licenses.id')
                ->select('users.name as last_modified_by', 'users.role as role', 'tickets.*')
                ->withCount('comments');

        //search ticket
        $search = $filters['search'];
        $search_fields = $filters['search_fields'];
        if (!empty($search)) {
            $query->where(function($q) use($search, $search_fields) {
            
                if ($search_fields['subject']) {
                    $q->where('subject', 'like', '%'.$search.'%');
                }

                if ($search_fields['ref_num']) {
                    $q->orWhere('ticket_ref', 'like', '%'.$search.'%');
                }
                
                if ($search_fields['body']) {
                    $q->orWhere('message', 'like', '%'.$search.'%')
                        ->orWhere('other_info', 'like', '%'.$search.'%');
                }
                
                if ($search_fields['customer']) {
                    $q->orWhere('users.name', 'like', '%'.$search.'%')
                        ->orWhere('users.email', 'like', '%'.$search.'%')
                        ->orWhere('licenses.additional_info', 'like', '%'.$search.'%');
                }

                if ($search_fields['comments']) {
                    $q->orWhereHas('comments', function($query) use($search) {
                        $query->where('comment', 'like', '%'.$search.'%');
                    });
                }
            });
        }

        //filter ticket by status, defualt exclude close ticket
        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        } else {
            $query->whereNotIn('status', ['closed']);
        }

        //filter if ticket is public or not
        if (strlen($filters['is_public'])) {
            $query->where('is_public', $filters['is_public']);
        }

        //filter by priority
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['product'])) {
            $query->where('tickets.product_id', $filters['product']);
        }

        //filter by last update by
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereDate('tickets.updated_at', '>=', $filters['start_date'])
                ->whereDate('tickets.updated_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['label'])) {
            $query->where('labels', 'like', '%'.$filters['label'].'%');
        }

        //filter by role
        $role = $filters['last_replied_by'];
        if (!empty($role)) {
            $query->where(function($q) use($role){
                $q->where('role', $role);
                if ($role == 'support_agent') {
                    $q->orWhere('role', 'admin');
                }
            });
        }

        //filter by department
        if (!empty($filters['p_department'])) {
            if ($filters['p_department'] != 'none') {
                $query->where('product_department_id', $filters['p_department']);
            } else {
                $query ->whereNull('product_department_id');
            }
        }

        //filter by agent
        if (!empty($filters['support_agent'])) {
            $agent_id = $filters['support_agent'];
            $query->whereHas('supportAgents', function ($query) use ($agent_id) {
                $query->where('user_id', $agent_id);
            });
        }

        //filter by closed by
        if (!empty($filters['closed_by'])) {
            $query->where('tickets.closed_by', $filters['closed_by']);
        }

        //filter by closed on
        if (!empty($filters['closed_on_start_date']) && !empty($filters['closed_on_end_date'])) {
            $query->whereDate('tickets.closed_on', '>=', $filters['closed_on_start_date'])
                ->whereDate('tickets.closed_on', '<=', $filters['closed_on_end_date']);
        }

        $user_id = auth()->user()->id;
        if (!auth()->user()->can('admin')) {
            $query->whereHas('supportAgents', function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            });
        }

        $query->orderBy('updated_at', 'desc');

        if($paginate) {
            $tickets = $query->paginate(30);
        } else {
            $tickets = $query->get();
        }
        
        return $tickets;
    }

    public function getUsersByRole($role)
    {
        $users = User::where('role', $role)
                ->get();
                
        return $users;
    }

    /**
     * Return comments based on
     * given filter parameter
     *
     * @return \Illuminate\Http\Response
     */
    public function getComments($filters, $paginate = true)
    {
        $query = TicketComment::join('tickets', 'ticket_comments.ticket_id', '=', 'tickets.id')
                    ->join('users', 'ticket_comments.user_id', '=', 'users.id')
                    ->with(['user', 'ticket'])
                    ->select('ticket_comments.id as id', 'ticket_comments.ticket_id as ticket_id', 
                        'ticket_comments.created_at as last_updated_at', 'tickets.ticket_ref as ticket_ref',
                        'tickets.subject as subject', 'users.name as replied_by')
                    ->groupBy('ticket_comments.id');

        //filter by product
        if (!empty($filters['product'])) {
            $query->where('tickets.product_id', $filters['product']);
        }

        //filter by last updated date range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereDate('ticket_comments.created_at', '>=', $filters['start_date'])
                ->whereDate('ticket_comments.created_at', '<=', $filters['end_date']);
        }

        //filter by agent
        if (!empty($filters['support_agent'])) {
            $query->where('ticket_comments.user_id', $filters['support_agent']);
        }

        $query->orderBy('ticket_comments.created_at', 'desc');

        if($paginate) {
            $comments = $query->paginate(30);
        } else {
            $comments = $query->get();
        }
        
        return $comments;
    }
}