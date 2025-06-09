<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Source;
use App\Product;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function respondWithError($message = null)
	{
	    return response()->json(
	        ['success' => false, 'msg' => $message]
	    );
	}

	/**
	 * Returns a 200 response.
	 *
	 * @param  array  $data
	 * @return \Illuminate\Http\Response
	 */
	public function respond($data)
	{
	    return response()->json($data);
	}

	/**
    * Checks if the feature is allowed in demo
    *
    * @return mixed
    */
    public function notAllowedInDemo()
    {
        //Disable in demo
        if ($this->isDemo()) {
            $output = [
        		'success' => 0,
                'msg' => __('messages.feature_disabled_in_demo')
            ];

            if (request()->ajax()) {
                return $output;
            } else {
                return back()->with('status', $output);
            }
        }
    }


	/**
    * Checks if the env demo/live
    *
    * @return mixed
    */
    public function isDemo()
    {
        if (config('app.env') == 'demo') {
            return true;
        }
    }

	/**
	 * Returns a 200 response.
	 *
	 * @param  object  $message = null
	 * @return \Illuminate\Http\Response
	 */
	public function respondSuccess($additional_data = [], $message = null)
	{
	    $message = is_null($message) ? __('messages.success') : $message;
	    $data = ['success' => true, 'msg' => $message];

	    if (!empty($additional_data)) {
	        $data = array_merge($data, $additional_data);
	    }

	    return $this->respond($data);
	}

	public function getEnvatoExtraInformation($token){
		$author_username = $this->__getEnvatoUsername($token);

		$items = $this->__getEnvatoItems($token, $author_username);

		return ['author_username' => $author_username, 'items' => $items];
	}

	private function __getEnvatoHeader($token){
		$headers = ['Authorization' => 'Bearer ' . $token, 
					'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:41.0) Gecko/20100101 Firefox/41.0',
						//'verify' => false,
					'timeout' => 60,
					'Content-Type' => 'application/json; charset=utf-8',
					'Accept' => 'application/json',
				];
		return $headers;
	}

	private function __getEnvatoUsername($token){
		$client = new Client();
		$url = 'https://api.envato.com/v3/market/author/sales?page=1';
		$response = $client->get($url, ['headers' => $this->__getEnvatoHeader($token), 'verify' => false]);
		$body = json_decode($response->getBody()->getContents(), true);

		$author_username = '';

		if(!empty($body[0]) && !empty($body[0]['item']['author_username'])){
			$author_username = $body[0]['item']['author_username'];
		}

		return $author_username;
	}

	private function __getEnvatoItems($token, $username){

		//'videohive', 'graphicriver', 'audiojungle', 'photodune', '3docean'
		$sites = ['codecanyon', 'themeforest'];

		$items = [];

		foreach ($sites as $site) {
			
			$client = new Client();
			$url = 'https://api.envato.com/v1/market/new-files-from-user:' . $username . ',' . $site . '.json';
			$response = $client->get($url, ['headers' => $this->__getEnvatoHeader($token), 'verify' => false]);
			$body = json_decode($response->getBody()->getContents(), true);

			if(!empty($body['new-files-from-user'])){
				foreach ($body['new-files-from-user'] as $key => $value) {
					$items[$value['id']] = $value['item'];
				}
			}
		}

		return $items;
	}

	//Validates the envato purchase code.
	protected function __validateEnvato($source, $license_key, $source_product_id){

		$response = [];

		try {

			$client = new Client();
			$url = 'https://api.envato.com/v3/market/author/sale?code=' . $license_key;
			$curlResponse = $client->get($url, ['headers' => $this->__getEnvatoHeader($source->envato_token), 'verify' => false]);
			$envatoRes = json_decode($curlResponse->getBody()->getContents());

			if (isset($envatoRes->item->id) 
        	&& $envatoRes->item->id == $source_product_id
                ) {

	        	$response['success'] = true;
	            $response['buyer'] = $envatoRes->buyer;
	            $response['license_type'] = $envatoRes->license;
	            $response['purchased_on'] = !empty($envatoRes->sold_at) ? Carbon::createFromFormat('Y-m-d\TH:i:sP', $envatoRes->sold_at)->toDateTimeString() : null;
	            $response['support_expires_on'] = !empty($envatoRes->supported_until) ?Carbon::createFromFormat('Y-m-d\TH:i:sP', $envatoRes->supported_until)->toDateTimeString() : null;
	            $response['additional_info'] = ['buyer' => $envatoRes->buyer, 'license_type' => $envatoRes->license];
	            //$response['purchase_source'] = 'envato';
        	} else {
        		$response['success'] = false;
        		$response['msg'] = "Invalid license";
        	}

		} catch (\GuzzleHttp\Exception\ClientException $e) {
    		$response['success'] = false;
        	//$response['msg'] = $e->getMessage();
        	$response['msg'] = __('messages.invalid_license');
		}

        return $response;
    }

    protected function __validateWooLicensing($source, $license_key, $source_product_id){
		$args = [
                    'woo_sl_action' => 'status-check',
                    'licence_key' => $license_key,
                    'product_unique_id' => $source_product_id,
                ];


        $request_uri = $source->web_url . '?' . http_build_query( $args );

        $curl = curl_init($request_uri);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        $result = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($result, true);
        $response = [];

        //$data_body = json_decode($data['body']);
        //if(!empty($result) && $result['status'] == 'success' && $result['buyer'] == $username)
        if(!empty($result) && !empty($result['buyer']))
        {
        	$response['success'] = true;
            $response['buyer'] = $result['buyer'];
            $response['license_type'] = !empty($result['license_type']) ? $result['license_type'] : '';
            $response['purchased_on'] = !empty($result['sold_at']) ? $result['sold_at'] : null;
            $response['support_expires_on'] = !empty($result['supported_until']) ? $result['supported_until'] : null;
            $response['additional_info'] = ['buyer' => $result['buyer'], 'license_type' => $response['license_type']];
        } else {
        	$response['success'] = false;
        	$response['msg'] = __('messages.invalid_license');
        }

        return $response;
    }

    /**
     * validate license key.
     *
     * @param  array  $params
     * @return array
     */
    protected function __validateLicenseKey($params)
    {
        //Get product source
        $product_details = Product::join('product_sources AS PS', 'products.id', 'PS.product_id')
                ->where('products.id', $params['product_id'])
                ->where('PS.source_id', $params['source_id'])
                ->first();

        $source = Source::find($params['source_id']);

        if($source->source_type == 'envato'){
            return $this->__validateEnvato($source, $params['license_key'], $product_details->product_id_in_source);
        } elseif ($source->source_type == 'woolicensing') {

            return $this->__validateWooLicensing($source, $params['license_key'], $product_details->product_id_in_source);
        }
    }
}
