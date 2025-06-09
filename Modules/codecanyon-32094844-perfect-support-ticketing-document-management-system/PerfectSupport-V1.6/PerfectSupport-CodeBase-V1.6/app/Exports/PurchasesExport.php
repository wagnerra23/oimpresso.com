<?php

namespace App\Exports;

use App\License;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PurchasesExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize
{
    use Exportable;

	public function query()
    {
        return License::query()
        		->join('users', 'licenses.user_id', '=', 'users.id')
                ->join('products', 'licenses.product_id', '=', 'products.id')
                ->join('sources', 'licenses.source_id', '=', 'sources.id')
                ->select('users.name as name', 'users.email as email', 
                	'products.name as product', 'license_key', 'purchased_on',
                	'sources.name as source', 'support_expires_on', 'expires_on');
    }

    /**
    * @var License $license
    */
    public function map($license): array
    {
        return [
            $license->name,
            $license->email,
            $license->product,
            $license->source,
            $license->product_license_key,
            $license->purchased_on,
            $license->support_expires_on,
            $license->expires_on,
        ];
    }

    public function headings(): array
    {
        return [
            __('messages.name'),
            __('messages.email'),
            __('messages.product'),
            __('messages.source'),
            __('messages.license_key'),
            __('messages.purchased_on'),
            __('messages.support_expires_on'),
            __('messages.license_expires_on'),
        ];
    }
}