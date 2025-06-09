<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Connector\Transformers\CommonResource;
use Carbon\Carbon;
use App\Models\PRODUTO_TIPO;

/**
 * @group PRODUTO_TIPO Management
 * @authenticated
 *
 * APIs for managing PRODUTO_TIPO.
 */
class PRODUTO_TIPOController extends ApiController
{
    /**
     * List items of PRODUTO_TIPO
     */
    public function index()
    {
        $user = Auth::user();
        $business_id = $user->business_id;

        $items = PRODUTO_TIPO::where('business_id', $business_id)->get();

        return CommonResource::collection($items);
    }

    /**
     * Synchronize PRODUTO_TIPO
     */
    public function syncPRODUTO_TIPO(Request $request)
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
                    $record = PRODUTO_TIPO::where('business_id', $business_id)
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
                        PRODUTO_TIPO::create([
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
            Log::error('Error synchronizing PRODUTO_TIPO: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error processing synchronization.',
            ], 500);
        }
    }
}
