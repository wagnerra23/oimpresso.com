<?php

namespace Modules\Repair\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;
use Modules\Repair\Concerns\LogsWithPiiRedactor;
use Modules\Repair\Entities\JobSheet;
use Spatie\Activitylog\Models\Activity;

class CustomerRepairStatusController extends Controller
{
    use LogsWithPiiRedactor; // D7.a Wave 17 — wrap Log::emergency com PiiRedactor
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return view('repair::customer_repair.index');
    }

    /**
     * Consulta pública do status do reparo (POST /post-repair-status, AJAX).
     *
     * @return array<string, mixed>|null array com success/msg (e repair_html quando acha);
     *                                   null quando a requisição não é AJAX (comportamento de sempre)
     */
    public function postRepairStatus(Request $request)
    {
        if ($request->ajax()) {
            try {
                $search_type = (string) $request->input('search_type', ''); //job_sheet/invoice/mobile
                $search_number = trim((string) $request->input('search_number', '')); //job_sheet_no/invoice_no/mobile_num

                // Tier 0 (ADR 0093): esta rota é pública e o ScopeByBusiness não age sem
                // usuário logado. Sem um tipo de busca conhecido e um número, a consulta
                // abaixo sairia SEM filtro — todas as OS de todas as empresas. Recusa antes
                // de consultar. Teste: Tests/Feature/PortalStatusReparoSemVazamentoTest.php.
                if (! in_array($search_type, ['job_sheet_no', 'invoice_no', 'mobile_num'], true)
                    || $search_number === '') {
                    return ['success' => false,
                        'msg' => __('repair::lang.invalid_repair_details'),
                    ];
                }

                $query = JobSheet::leftJoin('transactions',
                            'transactions.repair_job_sheet_id', '=', 'repair_job_sheets.id')
                            ->join('contacts', 'repair_job_sheets.contact_id', '=', 'contacts.id')
                            ->leftJoin(
                                'repair_statuses AS rs',
                                'repair_job_sheets.status_id',
                                '=',
                                'rs.id'
                            )
                            ->leftJoin(
                                'brands AS b',
                                'repair_job_sheets.brand_id',
                                '=',
                                'b.id'
                                )
                            ->leftJoin(
                                'repair_device_models as rdm',
                                'rdm.id',
                                '=',
                                'repair_job_sheets.device_model_id'
                            )
                            ->leftJoin(
                                'categories as device',
                                'device.id',
                                '=',
                                'repair_job_sheets.device_id'
                            );

                // $search_type já foi validado acima: é um dos três, nunca vazio.
                if ($search_type === 'job_sheet_no') {
                    $query->where('repair_job_sheets.job_sheet_no', $search_number);
                } elseif ($search_type === 'invoice_no') {
                    $query->where('transactions.invoice_no', $search_number);
                } else { // mobile_num
                    $query->where('contacts.mobile', $search_number);
                }

                if (! empty($request->input('serial_no'))) {
                    $query->where('repair_job_sheets.serial_no', $request->input('serial_no'));
                }

                $sells = $query->select(
                            'repair_job_sheets.*',
                            'rs.name as repair_status',
                            'rs.color as repair_status_color',
                            'rdm.name as repair_model',
                            'device.name as repair_device',
                            'b.name as manufacturer'
                        )
                        ->groupBy('repair_job_sheets.id')
                        ->get();

                if (count($sells) < 1) {
                    return ['success' => false,
                        'msg' => __('repair::lang.invalid_repair_details'),
                    ];
                }

                foreach ($sells as $key => $sell) {
                    $sells[$key]['activities'] = Activity::forSubject($sell)
                                           ->with(['causer', 'subject'])
                                           ->latest()
                                           ->get();
                }

                $repair_html = view('repair::customer_repair.repair_details')
                                ->with(compact('sells'))
                                ->render();

                $output = ['success' => true,
                    'msg' => __('lang_v1.success'),
                    'repair_html' => $repair_html,
                ];
            } catch (\Throwable $e) {
                $this->logSafeEmergency('customer_repair_status', $e); // D7.a Wave 17 LGPD

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }

        return null;
    }
}
