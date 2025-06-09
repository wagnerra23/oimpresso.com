<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Ticket;
use Illuminate\Support\Facades\DB;
use App\User;
use App\Product;
use App\Http\Util\CommonUtil;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    /**
     * Create a new controller instance.
     *
     * @param CommonUtil
     */
    public function __construct(CommonUtil $commonUtil)
    {
        $this->CommonUtil = $commonUtil;
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {   
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        $total_tickets = $this->__getTotalAssignedTicketsForAgents();
        $tickets_by_status = $this->__getTotalAssignedTicketsToAgentsByStatus();
        $tickets_by_agents = $this->__getTotalTicketsByAgents();
        $tickets_by_products = $this->__getTotalTicketsByProduct();
        return Inertia::render('Home/Index', compact('total_tickets', 'tickets_by_status', 'tickets_by_agents', 'tickets_by_products'));
    }

    private function __getTotalAssignedTicketsForAgents()
    {
        $query = Ticket::with('supportAgents');

        $user_id = \Auth::id();
        if (!auth()->user()->can('admin')) {
            $query->whereHas('supportAgents', function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            });
        }

        $tickets = $query->select(DB::raw("COUNT(id) as total_tickets"))
                        ->first();

        return $tickets->total_tickets;
    }

    private function __getTotalAssignedTicketsToAgentsByStatus()
    {
        $query = Ticket::with('supportAgents');

        $user_id = \Auth::id();
        if (!auth()->user()->can('admin')) {
            $query->whereHas('supportAgents', function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            });
        }

        $tickets = $query->select('status', DB::raw("COUNT(id) as total_tickets"))
                        ->groupBy('status')
                        ->get();

        return $tickets;
    }

    private function __getTotalTicketsByAgents()
    {
        $users = User::whereIn('role', ['admin', 'support_agent'])
                    ->get();

        foreach ($users as $key => $user) {
            $users[$key]['ticket'] = Ticket::whereHas('supportAgents', function($query) use ($user)
                                        {
                                            $query->where('user_id', $user->id);
                                        })
                                        ->select(DB::raw('SUM(IF(tickets.status = "new",1,0)) as new'),
                                        DB::raw('SUM(IF(tickets.status = "waiting",1,0)) as waiting'),
                                        DB::raw('SUM(IF(tickets.status = "pending",1,0)) as pending'),
                                        DB::raw('SUM(IF(tickets.status = "closed",1,0)) as closed'),
                                        DB::raw('SUM(IF(tickets.status = "open",1,0)) as open'))
                                        ->first();

        }
        
        return $users;
    }

    private function __getTotalTicketsByProduct()
    {   
        $products = Product::leftJoin('tickets as T', 'products.id', '=', 'T.product_id')
                        ->select('name', DB::raw('SUM(IF(T.status = "new",1,0)) as new'),
                            DB::raw('SUM(IF(T.status = "waiting",1,0)) as waiting'),
                            DB::raw('SUM(IF(T.status = "pending",1,0)) as pending'),
                            DB::raw('SUM(IF(T.status = "closed",1,0)) as closed'),
                            DB::raw('SUM(IF(T.status = "open",1,0)) as open')
                        )
                        ->groupBy('products.id')
                        ->get();

        return $products;
                        
    }

    /**
     * redirect users to the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectUserBasedOnRole()
    {
        if (config('constants.landing_page') == 'documentation') {
            $documentations = $this->CommonUtil->getAllDocs();
            return view('documentation.index')
                ->with(compact('documentations'));
        } else if(config('constants.landing_page') == 'login') {
            if (Auth::check()) {
                $user = Auth::user();
                if(in_array($user->role, ['admin', 'support_agent'])){
                    return redirect()->action('HomeController@index');
                } elseif ($user->role == 'customer') {
                    return redirect()->action('Customer\TicketController@index');
                }
            }
            return Inertia::render('Auth/Login');
        }
    }
}
