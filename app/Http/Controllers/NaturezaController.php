<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\NaturezaOperacao;
use App\System;
use App\BusinessLocation;
use App\Contact;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;
use App\Utils\ModuleUtil;

class NaturezaController extends Controller
{

	public function __construct(ModuleUtil $moduleUtil)
	{
		$this->moduleUtil = $moduleUtil;
	}

	public function index(){
		if (!auth()->user()->can('user.view') && !auth()->user()->can('user.create')) {
			abort(403, 'Unauthorized action.');
		}

		if (request()->ajax()) {
			$business_id = request()->session()->get('user.business_id');
			$user_id = request()->session()->get('user.id');
			$naturezas = NaturezaOperacao::where('business_id', $business_id)
			->select(['id', 'natureza', 'cfop_entrada_estadual', 'cfop_entrada_inter_estadual', 'cfop_saida_estadual', 
				'cfop_saida_inter_estadual']);


			return Datatables::of($naturezas)

			->addColumn(
				'action',
				'<a href="/naturezas/edit/{{$id}}" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</a>
				&nbsp;<a href="/naturezas/delete/{{$id}}" class="btn btn-xs btn-danger delete_user_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</a>'
			)

			->removeColumn('id')
			->rawColumns(['action'])
			->make(true);

		}
		return view('naturezas.list');

	}

	public function new(){
		// return view('naturezas.register');

		if (!auth()->user()->can('user.create')) {
			abort(403, 'Unauthorized action.');
		}

		$business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not, then check for users quota
		if (!$this->moduleUtil->isSubscribed($business_id)) {
			return $this->moduleUtil->expiredResponse();
		} elseif (!$this->moduleUtil->isQuotaAvailable('naturezas', $business_id)) {
			return $this->moduleUtil->quotaExpiredResponse('naturezas', $business_id, action('NaturezaController@index'));
		}

		$roles  = $this->getRolesArray($business_id);
		$username_ext = $this->getUsernameExtension();
		$contacts = Contact::contactDropdown($business_id, true, false);
		$locations = BusinessLocation::where('business_id', $business_id)
		->Active()
		->get();

        //Get user form part from modules
		$form_partials = $this->moduleUtil->getModuleData('moduleViewPartials', ['view' => 'naturezas.register']);

		return view('naturezas.register')
		->with(compact('roles', 'username_ext', 'contacts', 'locations', 'form_partials'));
	}

	public function save(Request $request){
		if (!auth()->user()->can('user.create')) {
			abort(403, 'Unauthorized action.');
		}

		try {
			$natureza = $request->only(['natureza', 'cfop_entrada_estadual', 'cfop_entrada_inter_estadual',
				'cfop_saida_estadual', 'cfop_saida_inter_estadual']);


			$business_id = $request->session()->get('user.business_id');
			$natureza['business_id'] = $business_id;

			$nat = NaturezaOperacao::create($natureza);

			$output = [
				'success' => 1,
				'msg' => 'Sucesso'
			];
		} catch (\Exception $e) {
			\Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

			$output = [
				'success' => 0,
				'msg' => __("messages.something_went_wrong")
			];

		}

		return redirect('naturezas')->with('status', $output);
	}

	public function delete($id){

		if (!auth()->user()->can('user.delete')) {
			abort(403, 'Unauthorized action.');
		}


		try {
			$business_id = request()->session()->get('user.business_id');

			NaturezaOperacao::where('business_id', $business_id)
			->where('id', $id)->delete();

			$output = [
				'success' => true,
				'msg' => 'Registro removido'
			];
		} catch (\Exception $e) {
			\Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

			$output = [
				'success' => false,
				'msg' => __("messages.something_went_wrong")
			];
		}

		return redirect('naturezas')->with('status', $output);

	}

	private function getRolesArray($business_id)
	{
		$roles_array = Role::where('business_id', $business_id)->get()->pluck('name', 'id');
		$roles = [];

		$is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

		foreach ($roles_array as $key => $value) {
			if (!$is_admin && $value == 'Admin#' . $business_id) {
				continue;
			}
			$roles[$key] = str_replace('#' . $business_id, '', $value);
		}
		return $roles;
	}

	private function getUsernameExtension()
	{
		$extension = !empty(System::getProperty('enable_business_based_username')) ? '-' .str_pad(session()->get('business.id'), 2, 0, STR_PAD_LEFT) : null;
		return $extension;
	}

	public function edit($id){
		// return view('naturezas.register');

		if (!auth()->user()->can('user.create')) {
			abort(403, 'Unauthorized action.');
		}

		$business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not, then check for users quota
		if (!$this->moduleUtil->isSubscribed($business_id)) {
			return $this->moduleUtil->expiredResponse();
		} elseif (!$this->moduleUtil->isQuotaAvailable('naturezas', $business_id)) {
			return $this->moduleUtil->quotaExpiredResponse('naturezas', $business_id, action('NaturezaController@index'));
		}

		$roles  = $this->getRolesArray($business_id);
		$username_ext = $this->getUsernameExtension();
		$contacts = Contact::contactDropdown($business_id, true, false);
		$locations = BusinessLocation::where('business_id', $business_id)
		->Active()
		->get();

		$natureza = NaturezaOperacao::find($id);

        //Get user form part from modules
		$form_partials = $this->moduleUtil->getModuleData('moduleViewPartials', ['view' => 'naturezas.register']);

		return view('naturezas.edit')
		->with(compact('roles', 'natureza', 'username_ext', 'contacts', 'locations', 'form_partials'));
	}

	public function update(Request $request){
		if (!auth()->user()->can('user.create')) {
			abort(403, 'Unauthorized action.');
		}

		try {
			$natureza = $request->only(['natureza', 'cfop_entrada_estadual', 'cfop_entrada_inter_estadual',
				'cfop_saida_estadual', 'cfop_saida_inter_estadual']);


			$nat = NaturezaOperacao::find($request->id);
			

			$nat->update($natureza);

			$output = [
				'success' => 1,
				'msg' => 'Editado com sucesso!'
			];
		} catch (\Exception $e) {
			\Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

			$output = [
				'success' => 0,
				'msg' => __("messages.something_went_wrong")
			];

			print_r($output);

		}

		return redirect('naturezas')->with('status', $output);
	}
}
