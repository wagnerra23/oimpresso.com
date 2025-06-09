<?php

namespace App\Http\Controllers;

use App\Product;
use App\Source;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use App\User;
use App\ProductSource;
use App\Department;
use App\ProductDepartment;

class ProductController extends Controller
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

        $products = Product::with('sources', 'supportAgents', 'productDepartments', 'productDepartments.department')
                        ->latest()
                        ->paginate(30);

        $sources = Source::enabled()->get()->makeVisible(['id', 'name', 'source_other_info', 'source_type']);
        $agents = User::getSupportAgentsDropdown();

        return Inertia::render('Product/Index', compact('products', 'sources', 'agents'));
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

        try {
            $departments = Department::getDropdown();
            return $this->respondSuccess(['departments' => $departments]);
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
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $product = new Product;
        $product->name = $request->input('name');
        $product->description = $request->input('description');
        $product->is_active = $request->input('is_active', false);
        $product->user_id = $request->user()->id;
        $product->save();

        //Add relevant product_id_in_source
        $sources = $request->input('sources');
        if (!empty($sources)) {
            foreach ($sources as $source) {
                $product_id_in_source = null;
                if($source == 1){
                    $product_id_in_source = $request->input('envato_product_id');
                } elseif($source == 2){
                    $product_id_in_source = $request->input('woolicense_product_id');
                }

                $product->sources()->attach($source, ['product_id_in_source' => $product_id_in_source]);
            }
        }

        //add department and its agents
        $departments = $request->input('departments');
        if (!empty($request->input('departments'))) {
            foreach ($departments as $key => $value) {
                $p_department = ProductDepartment::create([
                                    'product_id' => $product->id,
                                    'department_id' => $value['department_id'],
                                    'information' => $value['information'],
                                    'show_related_public_ticket' => $value['show_related_public_ticket']
                                ]);
                $p_department->supportAgents()->sync($value['agents']);
            }
        }

        $product->supportAgents()->sync($request->input('support_agents'));

        return back()->with('success', __('messages.success'));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\ProductController  $productController
     * @return \Illuminate\Http\Response
     */
    public function show(ProductController $productController)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ProductController  $productController
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {      
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $product = Product::with(['sources', 'supportAgents', 'productDepartments',
                        'productDepartments.supportAgents', 'productDepartments.department'])
                        ->findOrFail($id);

            $product_source = $product->sources->pluck('id')->toArray();
            $agent_for_product = $product->supportAgents->pluck('id')->toArray();

            $product_id_in_source = ProductSource::where('product_id', $id)
                                    ->pluck('product_id_in_source', 'source_id')
                                    ->toArray();

            $departments = Department::getDropdown();

            $envato_product_id = null;
            $woolicense_product_id = null;
            foreach ($product->sources as $key => $source) {
                if ($source->source_type == 'envato') {
                    $envato_product_id = $product_id_in_source[$source['id']];
                } elseif ($source->source_type == 'woolicensing') {
                    $woolicense_product_id = $product_id_in_source[$source['id']];
                }
            }

            $product_departments = [];
            if (!empty($product->productDepartments)) {
                foreach ($product->productDepartments as $product_department) {
                    $product_departments[] = [
                        'name' => $product_department->department->name,
                        'agents' => $product_department->supportAgents->pluck('id')->toArray(),
                        'unique_id' => rand(),
                        'department_id' => (int)$product_department->department_id,
                        'p_department_id' => (int)$product_department->id,
                        'information' => $product_department->information,
                        'show_related_public_ticket' => $product_department->show_related_public_ticket
                    ];
                }
            }

            return $this->respondSuccess([
                'product' => $product,
                'product_source' => $product_source,
                'agent_for_product' => $agent_for_product,
                'envato_product_id' => $envato_product_id,
                'woolicense_product_id' => $woolicense_product_id,
                'product_departments' => $product_departments,
                'departments' => $departments,
            ]);

        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ProductController  $productController
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $input = $request->only(['name', 'description']);
            $input['is_active'] = $request->input('is_active', false);

            $product = Product::findOrFail($id);
            $product->update($input);

            //Add & update relevant product_id_in_source
            $sources = $request->input('sources');
            $source_id = [];
            foreach ($sources as $source) {
                $source_id[] = $source;
                $product_id_in_source = null;
                if($source == 1){
                    $product_id_in_source = $request->input('envato_product_id');
                } elseif($source == 2){
                    $product_id_in_source = $request->input('woolicense_product_id');
                }

                $product_source = ProductSource::where('product_id', $id)
                                    ->where('source_id', $source)
                                    ->first();

                if (!empty($product_source)) {
                    $product_source->update(['product_id_in_source' => $product_id_in_source]);
                } else {
                    $product->sources()->attach($source, ['product_id_in_source' => $product_id_in_source]);
                }
            }

            //update department and its agents
            $existing_departments = $request->input('departments');
            if (!empty($existing_departments)) {
                $epd_ids = [];
                foreach ($existing_departments as $key => $value) {
                    $epd_ids[] = $value['p_department_id'];
                    $p_department = ProductDepartment::find($value['p_department_id']);
                    $p_department->information = $value['information'];
                    $p_department->show_related_public_ticket = $value['show_related_public_ticket'];
                    $p_department->save();
                    if (!empty($p_department)) {
                        $p_department->supportAgents()->sync($value['agents']);
                    }
                }

                if (!empty($epd_ids)) {
                    ProductDepartment::whereNotIn('id', $epd_ids)
                        ->where('product_id', $product->id)
                        ->delete();
                }
            }

            //if existing department is empty remove from product
            if (empty($existing_departments)) {
                ProductDepartment::where('product_id', $product->id)
                    ->delete();
            }

            //add new department and its agents
            $new_departments = $request->input('new_departments');
            if (!empty($new_departments)) {
                foreach ($new_departments as $key => $value) {
                    $p_department = ProductDepartment::create([
                                        'product_id' => $product->id,
                                        'department_id' => $value['department_id'],
                                        'information' => $value['information'],
                                        'show_related_public_ticket' => $value['show_related_public_ticket']
                                    ]);
                    $p_department->supportAgents()->sync($value['agents']);
                }
            }
            
            //delete non existing source for product
            ProductSource::where('product_id', $id)
                    ->whereNotIn('source_id', $source_id)
                    ->delete();

            $product->supportAgents()->sync($request->input('support_agents'));

            return back()->with('success', __('messages.success'));
        } catch (Exception $e) {
            return back()->with('error', __('messages.something_went_wrong'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\ProductController  $productController
     * @return \Illuminate\Http\Response
     */
    public function destroy(ProductController $productController)
    {
        //
    }
}
