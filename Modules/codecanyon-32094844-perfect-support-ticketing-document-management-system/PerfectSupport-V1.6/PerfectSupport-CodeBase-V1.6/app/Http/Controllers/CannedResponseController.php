<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\CannedResponse;

class CannedResponseController extends Controller
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

        $canned_responses = CannedResponse::select('id', 'name',
                                'message', 'only_for_admin')
                                ->latest()
                                ->get();

        $tags = CannedResponse::getTags();

        return Inertia::render('CannedResponse/Index', compact('canned_responses', 'tags'));
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
            $input = $request->input('response');
            //update response if id exist
            $existing_id = [];
            foreach ($input as $response) {
                if (isset($response['id']) && isset($response['name']) && isset($response['message'])) {
                    $existing_id[] = $response['id'];
                    $canned = CannedResponse::find($response['id']);
                    $canned->name = $response['name'];
                    $canned->message = $response['message'];
                    $canned->only_for_admin = !empty($response['only_for_admin']) ? 1 : 0;
                    $canned->save();
                }
            }

            //delete response which is not in existing id
            CannedResponse::whereNotIn('id', $existing_id)
                    ->delete();

            //save new response
            foreach ($input as $response) {
                if (!isset($response['id']) && isset($response['name']) && isset($response['message'])) {
                   CannedResponse::create($response);
                }
            }

            return redirect()->action('CannedResponseController@index')->with('success', __('messages.success'));
        } catch (Exception $e) {
            return redirect()->action('CannedResponseController@index')->with('error', __('messages.something_went_wrong'));
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
}
