<?php

namespace App\Http\Controllers\Api;

use App\Condicaopagto;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class CondicaopagtoController extends Controller
{
    /**
     * Sincroniza registros recebidos do cliente.
     *
     * @bodyParam data array required Lista de registros para sincronização.
     */
    public function sync(Request $request)
    {
        $request->validate([
            'data' => 'required|array',
            'data.*.codigo' => 'required|integer',
            'data.*.descricao' => 'required|string|max:30',
            'data.*.dt_alteracao' => 'nullable|date',
        ]);

        $data = $request->input('data');
        $response = [];

        foreach ($data as $item) {
            $registro = Condicaopagto::updateOrCreate(
                ['codigo' => $item['codigo']],
                [
                    'descricao' => $item['descricao'],
                    'officeimpresso_dt_alteracao' => Carbon::parse($item['dt_alteracao']),
                ]
            );

            $response[] = [
                'codigo' => $registro->codigo,
                'updated_at' => $registro->updated_at->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sincronização concluída.',
            'data' => $response,
        ]);
    }

    /**
     * Retorna registros atualizados após uma data específica.
     *
     * @queryParam date required Data de referência para atualização. Formato: Y-m-d H:i:s
     */
    public function getUpdatedUntilDate(Request $request)
    {
        $date = $request->query('date');
        if (!$date) {
            return response()->json(['error' => 'O parâmetro "date" é obrigatório.'], 422);
        }

        $updatedRecords = Condicaopagto::where('updated_at', '>', $date)->get();

        return response()->json([
            'status' => 'success',
            'data' => $updatedRecords,
        ]);
    }
}
