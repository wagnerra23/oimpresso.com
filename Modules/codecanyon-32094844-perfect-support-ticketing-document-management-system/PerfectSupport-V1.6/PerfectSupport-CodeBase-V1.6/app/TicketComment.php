<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    /**
	 * The attributes that aren't mass assignable.
	 *
	 * @var array
	 */
	protected $guarded = ['id'];

    /**
     * All of the relationships to be touched.
     *
     * @var array
     */
    protected $touches = ['ticket'];

	/**
     * Get the ticket that owns the comment.
     */
    public function ticket()
    {
        return $this->belongsTo('App\Ticket');
    }

    /**
     * Get the user that owns the comment.
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
