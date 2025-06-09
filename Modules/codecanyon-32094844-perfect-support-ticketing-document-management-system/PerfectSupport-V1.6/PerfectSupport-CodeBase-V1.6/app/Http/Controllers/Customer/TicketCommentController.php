<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Ticket;
use App\TicketComment;
use App\User;
use App\System;
use Notification;
use App\Notifications\Customer\AgentRepliedToTicket;
use App\Notifications\SupportAgent\CustomerRepliedToTicket;
use App\Notifications\SupportAgent\OtherAgentsRepliedToTicket;
use Carbon\Carbon;

class TicketCommentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   
        $ticket_id = $request->get('ticket_id');

        $ticket =  Ticket::with('supportAgents')->findOrFail($ticket_id);
        
        //if user is agent, check ticket is assigned or not
        $agents = $ticket->supportAgents()->pluck('user_id')->toArray();
        if(auth()->user()->can('support_agent') && !$ticket->is_public && !in_array(\Auth::id(), $agents)) {
            abort(403, 'Unauthorized action.');
        }

        //if ticket is not public check user is admin or not
        if (!$ticket->is_public && !(auth()->user()->can('admin'))) {
            //if user is not creator abort
            if (auth()->user()->can('customer') && $ticket->user_id !== \Auth::id()) {
                abort(403, 'Unauthorized action.');
            }
        }

        //get comments for given ticket_id
        $comments = TicketComment::where('ticket_id', $ticket_id)
                    ->join('users as U', 'ticket_comments.user_id', '=', 'U.id')
                    ->select('ticket_comments.*', 'U.name as commenter', 'U.role as commenter_role')
                    ->orderBy('ticket_comments.created_at', 'desc')
                    ->get();

        return $this->respond($comments);
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
        
        try {
            
            $input = $request->only('ticket_id', 'comment');

            $ticket =  Ticket::with('supportAgents')->findOrFail($input['ticket_id']);

            //if user is agent, check ticket is assigned or not
            $agents = $ticket->supportAgents()->pluck('user_id')->toArray();
            if(auth()->user()->can('support_agent') && !$ticket->is_public && !in_array(\Auth::id(), $agents)) {
                abort(403, 'Unauthorized action.');
            }

            //if ticket is not public check commentator is admin or not
            if (!$ticket->is_public && !(auth()->user()->can('admin'))) {
                //if commentator is not creator abort
                if (auth()->user()->can('customer') && $ticket->user_id !== \Auth::id()) {
                    abort(403, 'Unauthorized action.');
                }
            }

            //if ticket is public check comment enabled or not if user is not creator
            if ($ticket->is_public && auth()->user()->can('customer') && $ticket->user_id !== \Auth::id() && !$ticket->is_commentable) {
                abort(403, 'Unauthorized action.');
            }

            //update last update by for ticket
            $ticket_data['last_updated_by'] = \Auth::id();
            if ((auth()->user()->role == 'customer') && ($ticket->status == 'closed')) {
                $ticket_data['status'] = 'open';
            }
            if (!empty($request->input('status'))) {
                $ticket_data['status'] = $request->input('status');
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

            //add comment to ticket
            $input['user_id'] = \Auth::id();
            $comment = TicketComment::create($input);

            //Send notification to customer & agents about comment
            if (in_array(\Auth::user()->role, ['customer'])) {
                $this->__sendNewReplyNotificationToAgents($comment);
            } elseif (in_array(\Auth::user()->role, ['admin', 'support_agent'])) {
                $this->__sendNewReplyNotificationToCustomer($comment, $request->input('send_mail_notif_to_customer'));
                $this->__sendNewReplyNotificationToOtherAgents($comment, $request->get('send_mail_notif_to_other_agents', 0));
            }

            //redirect to admin ticket list
            if (in_array($request->input('submit_action'), ['redirect_back', 'status_changed'])) {
                $filetr_params = $request->input('filetr_params');
                return redirect()->action('TicketController@index', $filetr_params);
            }

            return $this->respondSuccess();
        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
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
        try {
            $comment = TicketComment::findOrFail($id);
            return $this->respondSuccess(['comment' => $comment->comment], __('messages.success'));
        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
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
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {

            //if user is agent, check ticket is assigned or not
            $ticket =  Ticket::with('supportAgents')->findOrFail(request()->get('ticket_id'));
            $agents = $ticket->supportAgents()->pluck('user_id')->toArray();
            if(auth()->user()->can('support_agent') && !$ticket->is_public && !in_array(\Auth::id(), $agents)) {
                abort(403, 'Unauthorized action.');
            }
            
            TicketComment::where('id', $id)
            ->update(['comment' => $request->input('comment')]);

            return $this->respondSuccess();
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent'))) {
            abort(403, 'Unauthorized action.');
        }

        try {

            //if user is agent, check ticket is assigned or not
            $ticket =  Ticket::with('supportAgents')->findOrFail(request()->get('ticket_id'));
            $agents = $ticket->supportAgents()->pluck('user_id')->toArray();
            if(auth()->user()->can('support_agent') && !$ticket->is_public && !in_array(\Auth::id(), $agents)) {
                abort(403, 'Unauthorized action.');
            }

            $ticket_comment = TicketComment::findOrFail($id);
            $ticket_comment->delete();
            
            return $this->respondSuccess();
        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    /**
     * send notification to customer
     * when support agents reply to the comment
     *
     * @param obj $comment
     *
     * @return \Illuminate\Http\Response
     */
    private function __sendNewReplyNotificationToCustomer($comment, $send_mail_notif_to_customer)
    {
        $system = System::whereIn('key', ['agent_replied_to_ticket_app_notif',
                        'agent_replied_to_ticket_mail_notif'])
                        ->pluck('value', 'key');

        $system['agent_replied_to_ticket_mail_notif'] = !empty($send_mail_notif_to_customer) ? true : false;

        if (!empty($system) && ($system['agent_replied_to_ticket_app_notif'] || $system['agent_replied_to_ticket_mail_notif']) && !empty($comment)) {
            $ticket = Ticket::with('user', 'supportAgents')
                        ->find($comment['ticket_id']);

            $comment = TicketComment::with('user')
                        ->find($comment['id']);

            $ticket->user->notify(new AgentRepliedToTicket($ticket, $comment, $system));
        }
    }

    /**
     * send notification to support agents
     * when customer reply to the comment
     *
     * @param obj $comment
     *
     * @return \Illuminate\Http\Response
     */
    private function __sendNewReplyNotificationToAgents($comment)
    {
        $system = System::whereIn('key', ['cust_replied_to_ticket_app_notif',
                        'cust_replied_to_ticket_mail_notif'])
                        ->pluck('value', 'key');

        if (!empty($system) && ($system['cust_replied_to_ticket_app_notif'] || $system['cust_replied_to_ticket_mail_notif']) && !empty($comment)) {
            $ticket = Ticket::with('user', 'supportAgents')
                        ->find($comment['ticket_id']);

            Notification::send($ticket->supportAgents, new CustomerRepliedToTicket($ticket, $comment, $system));
        }
    }

    private function __sendNewReplyNotificationToOtherAgents($comment, $send_mail_notif_to_other_agents)
    {
        $system = System::whereIn('key', ['other_agents_replied_to_ticket_app_notif',
                        'other_agents_replied_to_ticket_mail_notif'])
                        ->pluck('value', 'key')
                        ->toArray();

        $system['other_agents_replied_to_ticket_mail_notif'] = !empty($send_mail_notif_to_other_agents) ? true : false;

        if (
            !empty($system) && 
            (
                $system['other_agents_replied_to_ticket_app_notif'] || 
                $system['other_agents_replied_to_ticket_mail_notif']
            ) && 
            !empty($comment)
        ) {
            $ticket = Ticket::with('user', 'supportAgents')
                        ->findOrFail($comment['ticket_id']);

            $comment = TicketComment::with('user')
                        ->findOrFail($comment['id']);

            $agent_ids = $ticket->supportAgents->pluck('id')->toArray();
            $recipient_ids = !empty($agent_ids) ? array_diff($agent_ids, [auth()->user()->id]) : []; //exclude commenter id

            if(!empty($recipient_ids)) {
                $recipients = User::whereIn('id', $recipient_ids)
                            ->get();

                Notification::send($recipients, new OtherAgentsRepliedToTicket($ticket, $comment, $system));
            }
        }
    }
}
