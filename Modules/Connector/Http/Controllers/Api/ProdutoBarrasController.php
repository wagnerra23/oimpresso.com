<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Connector\Transformers\CommonResource;
use Carbon\Carbon;
use App\Models\PRODUTO_BARRAS;

/**
 * @group PRODUTO_BARRAS Management
 * @authenticated
 *
 * APIs for managing PRODUTO_BARRAS.
 */
class PRODUTO_BARRASController extends ApiController
{
    /**
     * List items of PRODUTO_BARRAS
     */
    public function index()
    {
        $user = Auth::user();
        $business_id = $user->business_id;

        $items = PRODUTO_BARRAS::where('business_id', $business_id)->get();

        return CommonResource::collection($items);
    }

    /**
     * Synchronize PRODUTO_BARRAS
     */
    public function syncPRODUTO_BARRAS(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'data' => 'required|array',
                'data.*.codigo' => 'required|integer',
                'data.*.descricao' => 'required|string|max:255',
                'data.*.dt_alteracao' => 'nullable|date_format:Y-m-d H:i:s',
                'data.*.oimpresso_id' => 'nullable|integer',
            ]);

            $data = $request->input('data');
            $response = [];
            $user = Auth::user();
            $business_id = $user->business_id;

            foreach ($data as $item) {
                try {
                    $record = PRODUTO_BARRAS::where('business_id', $business_id)
                        ->where(function ($query) use ($item) {
                            $query->where('id', $item['oimpresso_id'])
                                  ->orWhere('officeimpresso_codigo', $item['codigo']);
                        })
                        ->first();

                    if ($record) {
                        $record->update([
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'updated',
                            'officeimpresso_message' => 'The record was successfully updated.',
                            'id' => $record->id,
                        ];
                    } else {
                        PRODUTO_BARRAS::create([
                            'business_id' => $business_id,
                            'descricao' => $item['descricao'],
                            'officeimpresso_codigo' => $item['codigo'],
                            'officeimpresso_dt_alteracao' => $item['dt_alteracao'] ?? null,
                        ]);

                        $response[] = [
                            'officeimpresso_action' => 'created',
                            'officeimpresso_message' => 'The record was successfully created.',
                        ];
                    }
                } catch (\Exception $e) {
                    $response[] = [
                        'officeimpresso_action' => 'error',
                        'officeimpresso_message' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'status' => 'completed',
                'message' => 'Synchronization completed successfully.',
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('Error synchronizing PRODUTO_BARRAS: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error processing synchronization.',
            ], 500);
        }
    }
}
