<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use App\Source;
use App\Product;

class SourceController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        //TODO:
        //WooCommerce: url, consumer key, consumer secret,  (Read only permission)
        //WooLicensing: url, consumer key, consumer secret,  (Read only permission)
        //CC: person api token, checkboxes:

        $sources = Source::select(['id', 'name', 'source_type', 
                        'description', 'is_enabled', 'web_url',
                        'woo_consumer_key', 'woo_consumer_secret',
                        'envato_token'])
                        ->get()
                        ->makeVisible(['id', 'name', 'source_type', 
                        'description', 'is_enabled', 'web_url',
                        'woo_consumer_key', 'woo_consumer_secret',
                        'envato_token'])
                        ->keyBy('source_type');

        $source_types = [
                'woocommerce' => 'WooCommerce', 
                'woolicensing' => 'WooCommerce Licensing', 
                'envato' => 'Envato'
            ];

        return Inertia::render('Source/Index', compact('sources', 'source_types'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('Source/Create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        return Redirect::back()->with('success', __('messages.success'));
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }
        
        if ($this->isDemo()) {
            return redirect()->action('SourceController@index')
                ->with('error', __('messages.feature_disabled_in_demo'));
        }
        
        $source = Source::find($id);
        $source->fill([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'is_enabled' => $request->input('is_enabled'),
                'web_url' => $request->input('web_url'),
                'woo_consumer_key' => $request->input('woo_consumer_key'),
                'woo_consumer_secret' => $request->input('woo_consumer_secret'),
                'envato_token' => $request->input('envato_token'),
            ]);
        $source->save();

        //As per different sources get source informations
        if($source->source_type == 'envato'){
            $extra_info = $this->getEnvatoExtraInformation($request->input('envato_token'));

            $source->source_other_info = $extra_info;
            $source->save();
        }

        return Redirect::back()->with('success', __('messages.success'));
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

    public function getProductSources($product_id)
    {   
        try {
            $product = Product::active()
                    ->with('sources:sources.id,sources.name')
                    ->findOrFail($product_id);

            return $this->respondSuccess([
                'product' => $product
            ]);

        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function getValidateLicense(Request $request)
    {
        $products = Product::getDropdown();

        $sources = Source::pluck('name', 'id')
                        ->toArray();

        return Inertia::render('LicenseValidator/Index', compact('products', 'sources'));
    }

    public function postValidateLicense(Request $request)
    {
        try {
            $params = $request->only(['license_key', 'product_id', 'source_id']);
            $response = $this->__validateLicenseKey($params);
            return $this->respondSuccess([
                'response' => $response
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }
}
