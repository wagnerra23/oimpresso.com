<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class DifyController extends ApiController
{
    /**
     * Retorna uma resposta de sucesso padronizada.
     *
     * @param array $data
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondSuccess($data = [], $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Retorna uma resposta de erro padronizada.
     *
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondError($message = 'Erro', $statusCode = 400)
    {
        return response()->json([
            'success' => false,
            'error' => $message,
        ], $statusCode);
    }

    /**
     * Recebe e processa requisições do Dify.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function receive(Request $request)
    {
        try {
            // Valida os dados de entrada
            $request->validate([
                'point' => 'required|string', // Garante que 'point' está presente e é uma string
            ]);

            // Processa o corpo da requisição
            $data = $request->json()->all();
            $point = $data['point'];

            Log::info('Log chegada no DifyController: ' . json_encode($data));

            // Verifica o ponto de extensão e executa a lógica correspondente
            switch ($point) {
                case 'ping':
                    return $this->handlePing();
                case 'app.external_data_tool.query':
                    return $this->handleExternalDataToolQuery($data);
                default:
                    return $this->respondError('Ponto de extensão não implementado.', 400);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Captura erros de validação
            Log::error('Dados inválidos no DifyController: ' . $e->getMessage());
            return $this->respondError('Dados inválidos: ' . $e->getMessage(), 400);
        } catch (Exception $e) {
            // Captura outros erros inesperados
            Log::error('Erro no DifyController: ' . $e->getMessage());
            return $this->respondError('Erro interno no servidor.', 500);
        }
    }

    /**
     * Lida com o ponto de extensão 'ping'.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handlePing()
    {
        return $this->respondSuccess(['result' => 'pong']);
    }

    /**
     * Lida com o ponto de extensão 'app.external_data_tool.query'.
     *
     * @param array $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleExternalDataToolQuery(array $data)
    {
        try {
            // Exemplo de parâmetros recebidos do Dify
            $tool_variable = $data['params']['tool_variable'] ?? ''; // Aqui é uma string
            $query = $data['params']['query'] ?? ''; // Consulta original
    
            // Verifica se a consulta é sobre marcas ou cidade
            if ($tool_variable === 'location') {
                Log::info('location DifyController: ' . $query);
                // Consulta informações sobre a cidade (exemplo padrão do Dify)
                $result = $this->getCityInfo($query); // Usa a query como localização
            } elseif ($tool_variable === 'brands') {
                // Consulta as marcas (brands)
                $result = $this->getBrands();
            } elseif ($tool_variable === 'listamaiara') {
                // Consulta as marcas (brands)
                $result = $this->getListaMaiara();
            } elseif ($tool_variable === 'nome') {
                // Consulta as marcas (brands)
                $result = $this->getnome();
            } else {
                return $this->respondError('Parâmetros inválidos. Esperado: "location" ou "brands".', 400);
            }
    
            // Garante que a resposta contenha o campo 'result'
            return response()->json(['result' => $result]);
        } catch (Exception $e) {
            Log::error('Erro ao processar external_data_tool.query: ' . $e->getMessage());
            return $this->respondError('Erro ao processar a requisição.', 500);
        }
    }

    /**
     * Retorna informações sobre uma cidade (exemplo padrão do Dify).
     *
     * @param string $location
     * @return string
     */
    protected function getCityInfo(string $location)
    {
        Log::info('getCityInfo DifyController: ' . $location);
        // Simula a consulta de informações sobre a cidade
        $cityInfo = [
            'London' => "City: London\nTemperature: 10°C\nRealFeel®: 8°C\nAir Quality: Poor\nWind Direction: ENE\nWind Speed: 8 km/h\nWind Gusts: 14 km/h\nPrecipitation: Light rain",
            'New York' => "City: New York\nTemperature: 15°C\nRealFeel®: 12°C\nAir Quality: Moderate\nWind Direction: NW\nWind Speed: 10 km/h\nWind Gusts: 16 km/h\nPrecipitation: Clear",
            'Tokyo' => "City: Tokyo\nTemperature: 20°C\nRealFeel®: 18°C\nAir Quality: Good\nWind Direction: SE\nWind Speed: 5 km/h\nWind Gusts: 10 km/h\nPrecipitation: Cloudy",
        ];
        $mostra = $cityInfo[$location] ?? "Informações sobre a cidade '$location' não disponíveis.";
        Log::info('RetonoCity DifyController: ' . $mostra); // Corrigido: $mostra (minúsculo)
        return $mostra;
    }

    /**
     * Retorna a lista de marcas (brands).
     *
     * @return string
     */
    protected function getBrands()
    {
        // Simula a consulta ao modelo de marcas (substitua pela lógica real do seu sistema)
        $brands = [
            'Nike',
            'Adidas',
            'Puma',
            'Reebok',
            'Under Armour',
        ];
    
        $result = "Marcas disponíveis:\n" . implode("\n", $brands);
        Log::info('RetonoBrands DifyController: ' . $result); // Log do resultado final
        return $result;
    }

    protected function getNome()
    {
        // Simula a consulta ao modelo de marcas (substitua pela lógica real do seu sistema)
        $brands = [
            'Wagner',
            'Adidas',
            'Puma',
            'Reebok',
            'Under Armour',
        ];
    
        $result = "Nomes disponíveis:\n" . implode("\n", $brands);
        return $result;
    }

    protected function getListaMaiara()
    {
        // Simula a consulta ao modelo de marcas (substitua pela lógica real do seu sistema)
        $brands = [
            'Maiara',
            'ta vendo',
            'Puma',
            'Reebok',
            'Under Armour',
        ];
    
        $result = "Lista da Maiara disponíveis:\n" . implode("\n", $brands);
        return $result;
    }

    
}