<?php

namespace App\Exports;
use App\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use DB;
class UsersExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize
{	
	use Exportable;

	public function query()
    {
        $user =  User::query()
                    ->select('users.id as id', 'users.name as name', 'users.email as email',
                    'users.role as role', 'users.created_at as created_at',
                    DB::raw('(SELECT GROUP_CONCAT(DISTINCT products.name) FROM licenses join products on licenses.product_id = products.id where licenses.user_id = users.id) AS all_purchases'),
                    DB::raw('(SELECT GROUP_CONCAT(DISTINCT products.name) FROM products where products.id NOT IN (SELECT (products.id) FROM licenses join products on licenses.product_id = products.id where licenses.user_id = users.id group by users.id)) AS not_purchases'))
                    ->groupBy('users.id');

        return $user;
    }

    /**
    * @var User $user
    */
    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            __('messages.'.$user->role),
            $user->all_purchases,
            $user->not_purchases,
            $user->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            __('messages.name'),
            __('messages.email'),
            __('messages.role'),
            __('messages.all_purchases'),
            __('messages.not_purchases'),
            __('messages.added_on'),
        ];
    }
}
