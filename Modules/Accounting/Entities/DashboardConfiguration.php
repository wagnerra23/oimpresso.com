<?php

namespace Modules\Accounting\Entities;

// WAVE 18 RETRY D1 MULTI-TENANT — Tier 0 IRREVOGÁVEL (ADR 0093)
// Tabela `dashboard_configurations` tem business_id direto — trait HasBusinessScope aplica
// ScopeByBusiness global. Config dashboard é preferências per business (widgets/KPIs).

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

class DashboardConfiguration extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 RETRY D1 MT saturation)
}
