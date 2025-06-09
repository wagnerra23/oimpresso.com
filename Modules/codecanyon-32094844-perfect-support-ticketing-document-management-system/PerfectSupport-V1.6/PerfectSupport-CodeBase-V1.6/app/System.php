<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    /**
	 * The attributes that aren't mass assignable.
	 *
	 * @var array
	 */
	protected $guarded = ['id'];

	/**
	* set and get count of ref_type,
	*
	* @param  $ref_type
	*/
	public static function setAndGetReferenceCount($ref_type)
	{
	    $system = System::where('key', $ref_type)
	                ->first();

	    if (empty($system)) {
	    	$system = System::create(['key' => $ref_type, 'value' => 1]);
	    } else {
	    	$system->value += 1;
	    	$system->save();
	    }

	    return $system->value;
	}

	/**
	* generate ref no. for $ref_type,
	*
	* @param  $ref_count, $ref_prefix
	*/
	public static function generateReferenceNumber($ref_count, $ref_prefix = null)
	{
	    $ref_digits =  str_pad($ref_count, 4, 0, STR_PAD_LEFT);
	    $ref_number = $ref_prefix . $ref_digits;
	    
	    return $ref_number;
	}

	public static function getProperty($key)
	{
		$system = System::where('key', $key)
	                ->first();

	    if (!empty($system->value)) {
	    	return $system->value;
	    } else {
	    	return '';
	    }
	}

	public static function setProperty($key, $value)
	{
		$system = System::where('key', $key)
	                ->first();

	    if(!empty($system)){
	    	$system->value = $value;
	    	$system->update();
	    }
	}

	public static function createOrUpdateProperty($key, $value)
	{
		System::updateOrCreate(['key' => $key], ['value' => $value]);
	}

	public static function getSignatureTags()
    {
        return  [
            'tags' => ['{name}', '{email}', '{role}', '{customer_name}', '{product_name}'],
            'help_text' => __('messages.available_tags')
        ];
    }

	public static function getTicketReminderEmailTags()
    {
        return  [
            'tags' => ['{agent_name}', '{ticket_subject}', '{ticket_id}'],
            'help_text' => __('messages.available_tags')
        ];
    }
}
