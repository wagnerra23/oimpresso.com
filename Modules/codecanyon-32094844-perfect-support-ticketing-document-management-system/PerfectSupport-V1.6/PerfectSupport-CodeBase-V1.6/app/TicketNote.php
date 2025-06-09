<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketNote extends Model
{
    /**
	 * The attributes that aren't mass assignable.
	 *
	 * @var array
	 */
	protected $guarded = ['id'];

	/**
     * Get the ticket that owns the note.
     */
    public function ticket()
    {
        return $this->belongsTo('App\Ticket');
    }

    /**
     * The user who added the note.
     */
    public function addedBy()
    {
        return $this->belongsTo('App\User', 'added_by');
    }
}
