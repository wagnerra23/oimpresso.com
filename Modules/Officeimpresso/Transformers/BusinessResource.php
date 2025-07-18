<?php

namespace Modules\Officeimpresso\Transformers;

use Illuminate\Http\Resources\Json\Resource;

class BusinessResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        $array = parent::toArray($request);
        $array['keyboard_shortcuts'] = !empty($array['keyboard_shortcuts']) ? json_decode($array['keyboard_shortcuts'], true) : null;
        $array['custom_labels'] = !empty($array['custom_labels']) ? json_decode($array['custom_labels'], true) : null;
        $array['pos_settings'] = !empty($array['pos_settings']) ? json_decode($array['pos_settings'], true) : null;
        $array['logo'] = !empty($array['logo']) ? url('uploads/business_logos/' . $array['logo']) : null;

        foreach ($array['locations'] as $key => $value) {
            $array['locations'][$key]['default_payment_accounts'] = !empty($value['default_payment_accounts']) ? json_decode($value['default_payment_accounts'], true) : null;
        }
        
        return $array;
    }
}
