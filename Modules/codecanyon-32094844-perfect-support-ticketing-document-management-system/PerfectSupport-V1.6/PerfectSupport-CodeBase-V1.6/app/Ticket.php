<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{   
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'labels' => 'array',
    ];
    
	public static function defaultStatus()
    {
        return 'new';
    }

    /**
     * The ticket that belong to the product.
     */
    public function product()
    {
        return $this->belongsTo('App\Product');
    }
    
    /**
     * The user that owns the ticket.
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }
    
    /**
     * The user who closed the ticket.
     */
    public function closedBy()
    {
        return $this->belongsTo('App\User', 'closed_by');
    }
    
    /**
     * The user that owns the ticket.
     */
    public function license()
    {
        return $this->belongsTo('App\License');
    }

    /**
     * Get the comments for the ticket.
     */
    public function comments()
    {
        return $this->hasMany('App\TicketComment');
    }

    /**
     * Get the notes for the ticket.
     */
    public function notes()
    {
        return $this->hasMany('App\TicketNote');
    }
    
    /**
     * The support agents that belong to the ticket.
     */
    public function supportAgents()
    {
        return $this->belongsToMany('App\User', 'ticket_support_agents');
    }

    /**
     * Get the product department for the  ticket
     */
    public function productDepartment()
    {
        return $this->belongsTo('App\ProductDepartment');
    }
    
    public static function statuses()
    {   
        return [
            'new' => __('messages.new'),
            'waiting' => __('messages.waiting'),
    		'pending' => __('messages.pending'),
    		'closed' => __('messages.closed'),
            'open' => __('messages.open'),
        ];
    }

    public static function statusForSelect2()
    {
        return [
            ['id' => 'new', 'text' => __('messages.new')],
            ['id' => 'waiting', 'text' => __('messages.waiting')],
            ['id' => 'pending', 'text' => __('messages.pending')],
            ['id' => 'closed', 'text' => __('messages.closed')],
            ['id' => 'open', 'text' => __('messages.open')]
        ];
    }

    public static function priorities()
    {
        return [
            'low' => __('messages.low'),
            'medium' => __('messages.medium'),
            'high' => __('messages.high'),
            'urgent' => __('messages.urgent')
        ];
    }
}
