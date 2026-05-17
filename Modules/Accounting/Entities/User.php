<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope herdada do core (esta Entity é proxy de App\<X> Eloquent; scope live no parent UltimatePOS, ADR 0093).


use App\User as AppUser;
use Modules\EmployeeLoan\Entities\EmployeeLoan;
use Modules\Loan\Entities\Loan;

class User extends AppUser
{
    protected $appends = ['user_full_name'];

    public function loans()
    {
        return $this->hasMany(Loan::class, 'contact_id', 'id');
    }
    
    public function employeeloans()
    {
        return $this->hasMany(EmployeeLoan::class, 'user_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('users.status', 'active');
    }

    public function scopeLoanOfficer($query)
    {
        return $query->where('users.user_type', '!=', 'user_customer');
    }

    public function scopeEmployeeLoanOfficer($query)
    {
        return $query->where('users.user_type', '!=', 'user_customer');
    }

    public function getIsAdminAttribute()
    {
        $roles = $this->getRoleNames();

        return $roles->contains(function ($value) {
            return str_contains($value, 'Admin');
        });
    }

    public function getIsEmployeeAttribute()
    {
        $roles = $this->getRoleNames();

        return $roles->contains(function ($value) {
            return str_contains($value, 'Employee');
        });
    }

    public function scopeForBusiness($query)
    {
        return $query->where('users.business_id', session('business.id'));
    } 

    public static function forLoanOfficerDropdown($business_id)
    {
        return User::forBusiness()->loanOfficer()->where('business_id', $business_id)->get()->pluck('user_full_name', 'id');
    }
}
