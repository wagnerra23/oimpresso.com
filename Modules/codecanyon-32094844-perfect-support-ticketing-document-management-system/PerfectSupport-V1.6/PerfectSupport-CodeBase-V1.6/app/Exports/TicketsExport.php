<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\Http\Util\CommonUtil;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;
use App\System;

class TicketsExport implements FromCollection, WithMapping, WithHeadings, ShouldAutoSize
{
    protected $filters;
    protected $custom_fields;
    public function __construct($filters)
    {
        $this->filters = $filters;
        $this->custom_fields = $this->__getCustomFields();
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {   
        $commonUtil = new CommonUtil();
        
        $tickets = $commonUtil->getTickets($this->filters, false);

        return $tickets;
    }

    public function map($row): array
    {
        $support_agents = "";
        if(!empty($row->supportAgents)) {
            $agents_arr = $row->supportAgents->pluck('name')->toArray();
            $support_agents = implode(', ', $agents_arr);
        }

        $department = "";
        if(!empty($row->productDepartment) && !empty($row->productDepartment->department)) {
            $department = $row->productDepartment->department->name;
        }

        $column_values = [
            $row->ticket_ref,
            $row->subject,
            optional($row->product)->name,
            __('messages.'.$row->status),
            __('messages.'.$row->priority),
            optional($row->user)->name,
            !empty($row->labels) ? implode(', ', $row->labels) : '',
            $department,
            $row->updated_at ?? '',
            $row->last_modified_by,
            $support_agents,
            $row->closed_on ?? '',
            optional($row->closedBy)->name,
        ];

        if(isset($this->custom_fields['names']) && !empty($this->custom_fields['names'])) {
            foreach($this->custom_fields['names'] as $name) {
                $value = $row[$name] ?? '';
                array_push($column_values,$value);
            }
        }

        return $column_values;
    }

    public function headings(): array
    {
        $headings_arr = [
            __('messages.ref_num'),
            __('messages.subject'),
            __('messages.product'),
            __('messages.status'),
            __('messages.priority'),
            __('messages.customer'),
            __('messages.label'),
            __('messages.department'),
            __('messages.last_updated_at'),
            __('messages.last_updated_by'),
            __('messages.support_agents'),
            __('messages.closed_on'),
            __('messages.closed_by')
        ];

        if(isset($this->custom_fields['labels']) && !empty($this->custom_fields['labels'])) {
            $headings_arr = array_merge($headings_arr, $this->custom_fields['labels']);
        }

        return $headings_arr;
    }

    private function __getCustomFields()
    {
        $custom_fields = System::getProperty('custom_fields');

        if(!empty($custom_fields)) {
            $custom_fields = json_decode($custom_fields, true);
            $labels = [];
            $names = [];
            foreach($custom_fields as $key => $custom_field) {
                if(!empty($custom_field['label'])) {
                    $labels[] = $custom_field['label'];
                    $names[] = $key;
                }
            }
            return [
                'labels' => $labels,
                'names' => $names
            ];
        }
        return [];
    }
}
