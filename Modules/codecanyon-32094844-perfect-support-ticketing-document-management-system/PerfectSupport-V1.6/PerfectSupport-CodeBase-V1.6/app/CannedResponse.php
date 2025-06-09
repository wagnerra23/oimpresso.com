<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CannedResponse extends Model
{
    /**
	 * The attributes that aren't mass assignable.
	 *
	 * @var array
	 */
	protected $guarded = ['id'];

	public static function getCannedResponses($role)
	{
		$query = CannedResponse::select('name', 'message', 'only_for_admin');

		if (in_array($role, ['support_agent'])) {
			$query->where('only_for_admin', 0);
		}

		$canned_responses = [];
		if (in_array($role, ['support_agent', 'admin'])) {
			$canned_responses = $query->pluck('message', 'name');
		}

		return $canned_responses;
	}

	public static function getTags()
	{
		return [
			'{customer_name}', '{product_name}', '{ticket_ref}', '{status}',
			'{purchased_date}','{support_expiry_date}', '{expiry_date}'
		];
	}
}
