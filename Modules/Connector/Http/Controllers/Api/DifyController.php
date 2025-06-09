<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;

class DifyController extends ApiController
{
    public function receive(Request $request)
    {
        // Verifica a autenticação
        $apiKey = $request->header('Authorization');
        $expectedApiKey = 'Bearer 123456'; // Substitua pela sua chave de API

        if ($apiKey !== $expectedApiKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Processa o corpo da requisição
        $data = $request->json()->all();
        $point = $data['point'];

        // Verifica o ponto de extensão
        if ($point === 'ping') {
            return response()->json(['result' => 'pong']);
        }

        if ($point === 'app.external_data_tool.query') {
            return $this->handleExternalDataToolQuery($data['params']);
        }

        // Retorna erro se o ponto de extensão não for reconhecido
        return response()->json(['error' => 'Not implemented'], 400);
    }

    private function handleExternalDataToolQuery($params)
    {
        // Exemplo de implementação para a ferramenta de dados externos
        $location = $params['inputs']['location'];

        if ($location === 'London') {
            return response()->json([
                'result' => "City: London\nTemperature: 10°C\nRealFeel®: 8°C\nAir Quality: Poor\nWind Direction: ENE\nWind Speed: 8 km/h\nWind Gusts: 14 km/h\nPrecipitation: Light rain"
            ]);
        }

        return response()->json(['result' => 'Unknown city']);
    }
}