<?php

namespace Modules\Crm\Http\Controllers;

use App\BusinessLocation;
use App\Restaurant\Booking;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Crm\Services\ContactBookingService;
use Yajra\DataTables\Facades\DataTables;

class ContactBookingController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    protected ContactBookingService $bookingService;

    public function __construct(Util $commonUtil, ContactBookingService $bookingService)
    {
        $this->commonUtil = $commonUtil;
        $this->bookingService = $bookingService;
        $this->bookingStatuses = [
            'waiting' => [
                'label' => __('lang_v1.waiting'),
                'class' => 'bg-yellow',
            ],
            'booked' => [
                'label' => __('restaurant.booked'),
                'class' => 'bg-light-blue',
            ],
            'completed' => [
                'label' => __('restaurant.completed'),
                'class' => 'bg-green',
            ],
            'cancelled' => [
                'label' => __('restaurant.cancelled'),
                'class' => 'bg-red',
            ],
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        $user_id = request()->session()->get('user.id');

        if (request()->ajax()) {
            $start_date = request()->start;
            $end_date = request()->end;
            $query = Booking::where('bookings.business_id', $business_id)
                            ->where('bookings.created_by', $user_id)
                            ->leftjoin('business_locations as bl', 'bl.id', '=', 'bookings.location_id')
                            ->select('bookings.*', 'bl.name as location_name');

            if (! empty(request()->location_id)) {
                $query->where('bookings.location_id', request()->location_id);
            }

            return Datatables::of($query)
                ->editColumn('booking_start', function ($row) {
                    return $this->commonUtil->format_date($row->booking_start, true);
                })
                ->editColumn('booking_end', function ($row) {
                    return $this->commonUtil->format_date($row->booking_end, true);
                })
                ->editColumn('booking_status', function ($row) {
                    $statuses = $this->bookingStatuses;

                    return '<span class="label '.$statuses[$row->booking_status]['class'].'" >'.$statuses[$row->booking_status]['label'].'</span>';
                })
                ->rawColumns(['booking_status'])
               ->removeColumn('id')
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false, false, true, false);

        return view('crm::booking.index', compact('business_locations'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('crm::create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        try {
            if ($request->ajax()) {
                $business_id = request()->session()->get('user.business_id');
                $user_id = request()->session()->get('user.id');

                // Service thin: checagem de conflito + create. Response shape preservada.
                [, $output] = $this->bookingService->attemptBooking(
                    $request->input(),
                    (int) $business_id,
                    (int) $user_id,
                );
            } else {
                exit(__('messages.something_went_wrong'));
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        return view('crm::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('crm::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
