<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

include_once('install_r.php');

//Routes related to authentication
Auth::routes(['verify' => true]);

Route::get('/', 'HomeController@redirectUserBasedOnRole');

Route::get('register')->name('register')
	->uses('Auth\RegisterController@showRegistrationForm')
	->middleware('guest');
Route::post('register-attempt')->name('register.attempt')
	->uses('Auth\RegisterController@register')
	->middleware('guest');

Route::get('login')->name('login')
	->uses('Auth\LoginController@showLoginForm')
	->middleware('guest');
Route::post('login-attempt')->name('login.attempt')
	->uses('Auth\LoginController@login')
	->middleware('guest');
Route::post('logout')->name('logout')
	->uses('Auth\LoginController@logout');

//documentation public view
Route::get('docs', 'DocumentationController@getDocumentationIndex')
	->name('documentation-index');

Route::get('doc-{slug}-{documentation}', 'DocumentationController@getDocumentation')
	->name('view.documentation');

Route::get('section-{slug}-{documentation}', 'DocumentationController@getSection')
	->name('view.documentation.section');

Route::get('article-{slug}-{documentation}', 'DocumentationController@getArticle')
	->name('view.section.article');

Route::get('docs-search', 'DocumentationController@getDocsSuggestion')
	->name('search.docs');

Route::post('doc-feedback', 'DocumentationController@storeFeedbackForDoc')
	->name('doc.feedback');

//All routes related to admin/agents
Route::middleware(['auth', 'verified'])
	->group(function () {
		Route::get('/home', 'HomeController@index')
			->name('home');
		Route::resource('sources', 'SourceController');
		Route::get('get-product/{id}/sources', 'SourceController@getProductSources')
			->name('product.sources');
			
		Route::get('validate-license', 'SourceController@getValidateLicense')
			->name('get.validate.license');

		Route::post('post-validate-license', 'SourceController@postValidateLicense')
			->name('post.validate.license');

		Route::resource('products', 'ProductController');
		Route::resource('tickets', 'TicketController');

		Route::get('tickets-mass-destroy', 'TicketController@massDestroy')
			->name('tickets-destroy');

		Route::get('edit-tickets', 'TicketController@getEditableTickets')
			->name('edit-tickets');

		Route::post('update-tickets', 'TicketController@postEditableTickets')
			->name('update-tickets');

		Route::post('ticket-update', 'TicketController@updateTicket')
			->name('upate-ticket');

		Route::get('customer/{id}/ticket/{ticket_id}/notes', 'TicketController@getCustomerNotes')
			->name('customer.ticket.notes');
			
		Route::post('store-ticket-note', 'TicketController@storeNote')
			->name('store-ticket-note');
			
		Route::delete('delete/{id}/note', 'TicketController@deleteNote')
			->name('delete-ticket-note');
			
		Route::post('update-ticket-agents', 'TicketController@updateSupportAgentsForTicket')
			->name('upate.ticket.agents');
			
		Route::get('completed-tickets-reports', 'TicketController@getCompletedTickets')
			->name('reports.completed.tickets');

		Route::get('tickets-comments-reports', 'TicketController@getTicketComments')
			->name('reports.comments');
			
		Route::get('export-tickets', 'TicketController@export')->name('export.tickets');
		Route::put('update-ticket/{id}/custom-fields', 'TicketController@updateTicketCustomFields')
			->name('ticket.customFields.update');
		Route::resource('settings', 'SettingsController');
		Route::resource('canned-responses', 'CannedResponseController');

		Route::resource('user-management', 'UserManagementController');
		Route::get('users-data-table', 'UserManagementController@getUsersDatatableData')->name('users-data-table');
		Route::get('users-puchases', 'UserManagementController@getUserPurchaseLists')->name('users-purchase-list');
		Route::get('purchases-data-table', 'UserManagementController@getPurchaseDatatableData')->name('purchase-data-table');

		Route::get('create-purchase', 'UserManagementController@createPurchaseForUser')->name('create.purchase');
		Route::post('store-purchase', 'UserManagementController@storePurchaseForUser')->name('store.purchase');
		Route::delete('delete-purchase/{id}', 'UserManagementController@deletePurchase')->name('delete.purchase');
		Route::get('edit-purchase/{id}', 'UserManagementController@editPurchaseForUser')->name('edit.purchase');
		Route::put('update-purchase/{id}', 'UserManagementController@updatePurchaseForUser')->name('update.purchase');
		Route::get('export-users', 'UserManagementController@export')->name('export.users');
		
		Route::resource('notifications', 'NotificationController');
		Route::get('read-notifications', 'NotificationController@readNotifications')
			->name('read-notifications');

		Route::post('update-doc-sort-order', 'DocumentationController@updateDocSortOrder')
			->name('update.doc.sortOrder');
			
		Route::post('doc-img-upload', 'DocumentationController@upload')->name('doc.img.upload');
		Route::resource('documentation', 'DocumentationController');

		Route::resource('announcements', 'AnnouncementController');
		Route::get('announcements-data-table', 'AnnouncementController@getAnnouncementDatatableData')
			->name('announcements-data-table');
		Route::get('announcements-view', 'AnnouncementController@getAnnouncements')
			->name('announcements-view');

		Route::resource('backups', 'BackupController')->only(['index', 'create', 'destroy']);
		Route::get('backup/{file_name}/download', 'BackupController@download');

		Route::resource('departments', 'DepartmentController')->only(['store']);
		Route::get('get-departments-for-ids', 'DepartmentController@getDepartmentsForGivenIds')->name('departments.for.ids');
});

//All routes related to customer
Route::prefix('customer')
	->namespace('Customer')
	->middleware(['auth', 'verified'])
	->name('customer.')
	->group(function () {
		
		Route::resource('tickets', 'TicketController');

		Route::get('public-tickets', 'TicketController@getPublicTickets')
			->name('public-tickets');
		
		Route::get('product/{product_id}/departments', 'TicketController@departmentDropdown')
			->name('product.departments');
		
		Route::get('/department/{department_id}/info', 'TicketController@getProductDepartmentInfo')
			->name('department.info');
						
		Route::get('public-ticket/{id}', 'TicketController@viewPublicTicket')
			->name('view-public-ticket');

		Route::get('customer/{customer_id}/tickets', 'TicketController@getTicketListForTicketView')
			->name('customer-tickets');

		Route::get('customer/{customer_id}/purchases', 'TicketController@getPurchaseListForTicketView')
			->name('customer-purchases');

		Route::get('ticket/{id}', 'TicketController@show')
		->name('view-ticket');

		Route::get('public-tickets-suggestion', 'TicketController@getTicketsSuggestion')
			->name('tickets-suggestion');

		Route::post('update-license-expiry', 'LicenseController@updateLicenseKeyExpiry')
			->name('update-license-expiry');
		Route::resource('licenses', 'LicenseController');
		Route::get('export-purchases', 'LicenseController@export')->name('export.purchases');
		
		Route::resource('ticket-comments', 'TicketCommentController');
});