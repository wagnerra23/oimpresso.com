<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\License;
use App\Product;
use App\Source;
use Carbon\Carbon;
use App\Exports\PurchasesExport;
use Maatwebsite\Excel\Facades\Excel;

class LicenseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {   
        if (!auth()->user()->can('customer')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $product = Product::active()
                        ->with('sources:sources.id,sources.name')
                        ->findOrFail($request->input('product_id'));
            $user_id = \Auth::id();

            $licenses = License::where('user_id', $user_id)
                            ->where('product_id', $request->get('product_id'))
                            ->get();

            return $this->respondSuccess([
                'product' => $product,
                'licenses' => $licenses
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {   
        if (!auth()->user()->can('customer')) {
            abort(403, 'Unauthorized action.');
        }
        
        try {

            $input = $request->only('license_key', 'product_id', 'source_id', 'user_id');

            $response = [];
            if(!empty($input['license_key']) && !empty($input['product_id']) && !empty($input['source_id'])) {

                //Check for license already exist for this or other user.
                if(License::checkLicenseExist($input['license_key'])){
                    return $this->respondWithError(__('messages.license_key_exist'));
                }

                //validate license.
                $response = $this->__validateLicenseKey($input);
            }

            //check if response is validated/null and save if validated
            if(!empty($response) && $response['success']){
                $input['purchased_on'] = $response['purchased_on'] ?? null;
                $input['support_expires_on'] = $response['support_expires_on'] ?? null;
                $input['expires_on'] = $response['expires_on'] ?? null;
                $input['additional_info'] = $response['additional_info'] ?? null;
                
                $license = License::create($input);

                return $this->respondSuccess(['license' => $license]);
            } else {
                return $this->respondWithError($response['msg']);
            }
            
        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function updateLicenseKeyExpiry(Request $request)
    {   
        try {
            
            $params = $request->only(['product_id', 'source_id', 'license_key', 'license_id']);

            $response = $this->__validateLicenseKey($params);

            //check if response is validated/null and save if validated
            if(!empty($response) && $response['success']){

                $support_expires_on = $response['support_expires_on'] ?? null;
                $expires_on = $response['expires_on'] ?? null;
                $additional_info = $response['additional_info'] ?? null;

                $license = License::find($params['license_id']);

                $license->support_expires_on = $support_expires_on;
                $license->expires_on = $expires_on;
                $license->additional_info = $additional_info;
                $license->license_key = $params['license_key'];
                $license->save();
                
                return $this->respondSuccess(['license' => $license]);
            } else {
                return $this->respondWithError($response['msg']);
            }

        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function export()
    {
        return Excel::download(new PurchasesExport, 'purchases.xlsx');
    }

}
