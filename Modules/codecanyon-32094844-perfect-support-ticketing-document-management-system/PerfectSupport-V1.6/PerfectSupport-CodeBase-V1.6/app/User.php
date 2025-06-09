<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The roles that belong to the user.
     */
    public function tickets()
    {
        return $this->belongsToMany('App\Ticket', 'ticket_support_agents', 'user_id', 'ticket_id');
    }

    public static function getRolesDropdown()
    {
        return [
            'admin' => __('messages.admin'),
            'support_agent' => __('messages.support_agent'),
            'customer' => __('messages.customer')
        ];
    }

    /**
     * get the user with role 
     * admin, support_agent
     * @return Object
     */
    public static function getSupportAgentsDropdown()
    {
        $users = User::whereIn('role', ['admin', 'support_agent'])
                    ->pluck('name', 'id');
                    
        return $users;
    }

    /**
     * Get customers
     *
     * @return Object
     */
    public static function getCustomersDropdown()
    {
        $users = User::where('role', 'customer')
                    ->pluck('name', 'id');
                    
        return $users;
    }
}
