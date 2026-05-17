<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope herdada do core (esta Entity é proxy de App\<X> Eloquent; scope live no parent UltimatePOS, ADR 0093).


use App\BusinessLocation as AppBusinessLocation;

class BusinessLocation extends AppBusinessLocation
{
    public static function getDropdownCollection($business_id)
    {
        $locations = collect([]);

        foreach (BusinessLocation::forDropdown($business_id) as $id => $name) {
            $location = (object)['id' => $id, 'name' => $name];
            $locations->push($location);
        }

        return $locations;
    }
}
