<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Department;

class DepartmentController extends Controller
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
    public function create()
    {
        //
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

        try {

            $department = Department::create(['name' => $request->input('name')]);
            return $this->respondSuccess(['department' => $department]);
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

    public function getDepartmentsForGivenIds(Request $request)
    {
        if (!auth()->user()->can('admin')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            $departments = Department::whereIn('id', $request->input('department_ids'))
                            ->get();

            return $this->respondSuccess(['departments' => $departments]);
        } catch (\Exception $e) {
            return $this->respondWithError(__('messages.something_went_wrong'));
        }
    }
}
