<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Ticket;
use App\TicketComment;
use App\User;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent') || auth()->user()->can('customer'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $user = \Auth::user();
            $notifications_count = $user->unreadNotifications->count();
            return $this->respond($notifications_count);   
        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    /**
     * Read all the notification
     * of logedin user
     *
     * @return \Illuminate\Http\Response
     */
    public function readNotifications(Request $request)
    {
        if (!(auth()->user()->can('admin') || auth()->user()->can('support_agent') || auth()->user()->can('customer'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $user = \Auth::user();
            $notifications = $user->notifications()
                            ->latest()
                            ->simplePaginate(10);

            //mark notifications as read
            $user->notifications()
                ->latest()
                ->simplePaginate(10)->markAsRead();

            foreach ($notifications as $notification) {
                if ('App\Notifications\SupportAgent\CustomerRepliedToTicket' ==
                    $notification->type) {
                    $notification['ticket'] = Ticket::with('user')
                                                        ->find($notification->data['ticket_id']);
                    $notification['comment'] = TicketComment::
                                                find($notification->data['comment_id']);
                } elseif ('App\Notifications\SupportAgent\NewTicketAssigned'  ==
                    $notification->type) {
                    $notification['assigned'] = \Auth::user();
                    $notification['ticket'] = Ticket::find($notification->data['ticket_id']);
                } elseif ("App\Notifications\Customer\AgentRepliedToTicket"  ==
                    $notification->type) {
                    $notification['comment'] = TicketComment::with('user', 'ticket')
                                                ->find($notification->data['comment_id']);
                } elseif ("App\Notifications\Customer\NewTicketAdded"  ==
                    $notification->type) {
                    $notification['ticket'] = Ticket::with('user')
                                                ->find($notification->data['ticket_id']);
                } elseif('App\Notifications\SupportAgent\OtherAgentsRepliedToTicket' == $notification->type) {
                    $notification['comment'] = TicketComment::with('user', 'ticket')
                                                ->find($notification->data['comment_id']);
                }
            }

            return $this->respond($notifications);   
        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
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
    public function destroy($id)
    {
        //
    }
}
