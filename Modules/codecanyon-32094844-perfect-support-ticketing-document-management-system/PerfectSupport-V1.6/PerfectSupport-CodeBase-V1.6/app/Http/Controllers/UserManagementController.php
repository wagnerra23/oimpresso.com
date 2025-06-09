<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use Inertia\Inertia;
use JamesDordoy\LaravelVueDatatable\Http\Resources\DataTableCollectionResource;
use App\License;
use App\Product;
use App\Notifications\User\SendLoginCredentialToUser;
use App\Exports\UsersExport;
use Maatwebsite\Excel\Facades\Excel;
use DB;

class UserManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('UserManagement/Index');
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

        $roles = User::getRolesDropdown();
        return Inertia::render('UserManagement/Create', compact('roles'));
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

        $validatedData = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            'role' => 'required',
        ]);

        try {
            $input = $request->only('name', 'email', 'role');
            $input['password'] = bcrypt($request->input('password'));
            $user = User::create($input);

            if (!empty($request->input('notify_user'))) {
                $input['password'] = $request->input('password');
                $user->notify(new SendLoginCredentialToUser($input));
            }

            return redirect()->action('UserManagementController@index')->with('success', __('messages.success'));
        } catch (Exception $e) {
            return redirect()->action('UserManagementController@index')->with('error', __('messages.something_went_wrong'));
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
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $user = User::findOrFail($id) ;
        $roles = User::getRolesDropdown();
        return Inertia::render('UserManagement/Edit', compact('user', 'roles'));
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
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }
        
        if ($this->isDemo()) {
            return redirect()->action('UserManagementController@index')
                ->with('error', __('messages.feature_disabled_in_demo'));
        }

        $validatedData = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,'.$id,
            'role' => 'required',
        ]);

        try {
            $input = $request->only('name', 'email', 'role');
            
            if (!empty($request->input('password'))) {
                $input['password'] = bcrypt($request->input('password'));
            }

            $user = User::findOrFail($id);
            $user->update($input);

            if (!empty($request->input('password')) && !empty($request->input('notify_user'))) {
                $input['password'] = $request->input('password');
                $user->notify(new SendLoginCredentialToUser($input));
            }

            return redirect()->action('UserManagementController@index')->with('success', __('messages.success'));
        } catch (Exception $e) {
            return redirect()->action('UserManagementController@index')->with('error', __('messages.something_went_wrong'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }
        
        try {

            if (!empty($this->notAllowedInDemo())) {
                return $this->notAllowedInDemo();
            }

            $user = User::findOrFail($id);

            if ($user->id != \Auth::id()) {
                $user->delete();
            } else {
                return $this->respondWithError(__('messages.something_went_wrong'));
            }

            return $this->respondSuccess();
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function getUsersDatatableData(Request $request)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $searchValue = $request->input('search');
        $orderBydir = $request->input("dir");
        $orderBy = $request->input('column');
        $length = $request->input('length');

        $query = User::select('users.id as id', 'users.name as name', 'users.email as email',
                        'users.role as role', 'users.created_at as created_at',
                        DB::raw('(SELECT GROUP_CONCAT(DISTINCT products.name) FROM licenses join products on licenses.product_id = products.id where licenses.user_id = users.id) AS all_purchases'),
                        DB::raw('(SELECT GROUP_CONCAT(DISTINCT products.name) FROM products where products.id NOT IN (SELECT (products.id) FROM licenses join products on licenses.product_id = products.id where licenses.user_id = users.id group by users.id)) AS not_purchases')
                    );

        if (!empty($searchValue)) {
            $query->where("users.name", "LIKE", "%$searchValue%")
                ->orWhere('users.email', "LIKE", "%$searchValue%")
                ->orWhere('users.role', "LIKE", "%$searchValue%");
        }

        if (!empty($orderBy) && !empty($orderBydir)) {
            $query->orderBy($orderBy, $orderBydir);
        }

        $data = $query->groupBy('users.id')->paginate($length);

        return new DataTableCollectionResource($data);
    }

    public function getUserPurchaseLists(Request $request)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('Purchase/Index');
    }

    public function getPurchaseDatatableData(Request $request)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $searchValue = $request->input('search');
        $orderBydir = $request->input("dir");
        $orderBy = $request->input('column');
        $length = $request->input('length');

        $query = License::join('users', 'licenses.user_id', '=', 'users.id')
                        ->join('products', 'licenses.product_id', '=', 'products.id')
                        ->join('sources', 'licenses.source_id', '=', 'sources.id')
                        ->select('users.name as name', 'users.email as email',
                            'products.name as product', 'license_key', 'purchased_on',
                            'sources.name as source', 'licenses.id as id');

        if (!empty($searchValue)) {
            $query->where("users.name", "LIKE", "%$searchValue%")
                ->orWhere('users.email', "LIKE", "%$searchValue%")
                ->orWhere('sources.name', "LIKE", "%$searchValue%")
                ->orWhere('products.name', "LIKE", "%$searchValue%");
        }

        if (!empty($orderBy) && !empty($orderBydir)) {
            $query->orderBy($orderBy, $orderBydir);
        }

        $licenses = $query->paginate($length);
        
        return new DataTableCollectionResource($licenses);
    }

    public function createPurchaseForUser(Request $request)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $customers = User::getCustomersDropdown();
        $products = Product::getDropdown();

        return Inertia::render('Purchase/Create', compact('customers', 'products'));
    }

    public function storePurchaseForUser(Request $request)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $validatedData = $request->validate([
            'user_id' => 'required',
            'product_id' => 'required',
            'license_key' => 'required',
            'source_id' => 'required',
        ]);

        try {

            $input = $request->only('license_key', 'product_id', 'source_id', 'user_id', 'purchased_on', 'support_expires_on', 'expires_on');

            License::create($input);

            return redirect()->action('UserManagementController@getUserPurchaseLists')->with('success', __('messages.success'));
        } catch (\Exception $e) {
            return redirect()->action('UserManagementController@getUserPurchaseLists')->with('error', __('messages.something_went_wrong'));
        }
    }

    public function deletePurchase($license_id)
    {
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }
        
        try {

            $license = License::findOrFail($license_id);
            $license->delete();
            
            return $this->respondSuccess();
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

    public function editPurchaseForUser(Request $request, $id)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $license = License::findOrFail($id);

        $customers = User::getCustomersDropdown();
        $products = Product::getDropdown();

        return Inertia::render('Purchase/Edit', compact('customers', 'products', 'license'));
    }

    public function updatePurchaseForUser(Request $request, $id)
    {
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $validatedData = $request->validate([
            'user_id' => 'required',
            'product_id' => 'required',
            'license_key' => 'required',
            'source_id' => 'required',
        ]);

        try {

            $input = $request->only('license_key', 'product_id', 'source_id', 'user_id', 'purchased_on', 'support_expires_on', 'expires_on');

            if (!empty($input['license_key'])) {
                $input['license_key'] = \Crypt::encryptString($input['license_key']);
            }

            License::where('id', $id)
                ->update($input);

            return redirect()->action('UserManagementController@getUserPurchaseLists')->with('success', __('messages.success'));
        } catch (\Exception $e) {
            return redirect()->action('UserManagementController@getUserPurchaseLists')->with('error', __('messages.something_went_wrong'));
        }
    }

    public function export()
    {
        return Excel::download(new UsersExport, 'users.xlsx');
    }
}
