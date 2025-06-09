<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Announcement;
use Inertia\Inertia;
use App\Product;
use App\User;
use Carbon\Carbon;
use JamesDordoy\LaravelVueDatatable\Http\Resources\DataTableCollectionResource;
use App\Http\Util\CommonUtil;
class AnnouncementController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    /**
     * Constructor.
     *
     * @param CommonUtil
     */
    public function __construct(CommonUtil $commonUtil)
    {
        $this->CommonUtil = $commonUtil;
    }

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

        return Inertia::render('Announcement/Index');
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

        $products = Product::getDropdown(true);
        $roles = User::getRolesDropdown();

        return Inertia::render('Announcement/Create', compact('products', 'roles'));
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
            'body' => 'required',
            'role' => 'required',
            'end_datetime' => 'required',
            'start_datetime' => 'required',
        ]);

        try {
            $input = $request->only('body', 'product_id', 'role');

            $input['created_by'] = auth()->user()->id;

            if (!empty($request->input('start_datetime'))) {
                $input['start_datetime'] = Carbon::parse($request->input('start_datetime'));
            }
            
            if (!empty($request->input('end_datetime'))) {
                $input['end_datetime'] = Carbon::parse($request->input('end_datetime'));
            }

            Announcement::create($input);

            return redirect()->action('AnnouncementController@index')->with('success', __('messages.success'));
        } catch (Exception $e) {
            return redirect()->action('AnnouncementController@index')->with('error', __('messages.something_went_wrong'));
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
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }
        
        try {

            if (!empty($this->notAllowedInDemo())) {
                return $this->notAllowedInDemo();
            }

            Announcement::where('id', $id)
                ->delete();

            return $this->respondSuccess();
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }

     public function getAnnouncementDatatableData(Request $request)
    {   
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        $searchValue = $request->input('search');
        $orderBydir = $request->input("dir");
        $orderBy = $request->input('column');
        $length = $request->input('length');

        $query = Announcement::leftJoin('products', 'announcements.product_id', '=', 'products.id')
                    ->select('announcements.id as id', 'body', 'start_datetime', 'end_datetime',
                        'products.name as product', 'role');

        if (!empty($searchValue)) {
            $query->where("body", "LIKE", "%$searchValue%")
                ->orWhere("role", "LIKE", "%$searchValue%")
                ->orWhere("products.name", "LIKE", "%$searchValue%");
        }

        if (!empty($orderBy) && !empty($orderBydir)) {
            $query->orderBy($orderBy, $orderBydir);
        }

        $data = $query->paginate($length);
        
        return new DataTableCollectionResource($data);
    }

    public function getAnnouncements(Request $request)
    {   
        try {

            if (auth()->user()->role == 'customer') {
                $user_products = $this->CommonUtil->geCustomerPurchasesProductIds(auth()->user()->id);
            } elseif (in_array(auth()->user()->role, ['support_agent', 'admin'])) {
                $user_products = $this->CommonUtil->getAssignedProductIdsForAgent(auth()->user()->id);
            }

            $query = Announcement::select('body');
                                
            if (!empty($user_products)) {
                $query->where(function ($query) use ($user_products){
                        $query->whereIn('product_id', $user_products)
                        ->orWhereNull('product_id');
                    });
            } else {
                $query->whereNull('product_id');
            }

            $announcements = $query->where('start_datetime', '<=', Carbon::now()->toDateTimeString())
                                ->where('end_datetime', '>=', Carbon::now()->toDateTimeString())
                                ->where('created_by', '!=', auth()->user()->id)
                                ->where('role', auth()->user()->role)
                                ->get();
                                        
            return $this->respondSuccess([
                'announcements' => $announcements,
            ]);
        } catch (Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }
}
