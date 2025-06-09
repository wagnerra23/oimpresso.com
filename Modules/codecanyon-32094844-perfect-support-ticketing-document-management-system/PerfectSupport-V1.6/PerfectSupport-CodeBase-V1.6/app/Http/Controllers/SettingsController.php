<?php

namespace App\Http\Controllers;

use App\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use App\System;
use App\Http\Util\CommonUtil;
use App\Product;

class SettingsController extends Controller
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

        $settings = System::whereIn('key', ['ticket_prefix', 'ticket_instruction', 
                        'cust_new_ticket_app_notif', 'cust_new_ticket_mail_notif',
                        'agent_replied_to_ticket_app_notif', 'agent_replied_to_ticket_mail_notif',
                        'agent_assigned_ticket_app_notif', 'agent_assigned_ticket_mail_notif',
                        'cust_replied_to_ticket_app_notif', 'cust_replied_to_ticket_mail_notif',
                        'is_public_ticket_enabled', 'default_ticket_type', 'auto_close_ticket_in_days',
                        'gcse_html', 'gcse_js', 'signature', 'support_timing', 'enable_support_timing',
                        'other_agents_replied_to_ticket_app_notif', 'other_agents_replied_to_ticket_mail_notif',
                        'custom_fields', 'remind_ticket_in_days', 'ticket_reminder_mail_template'])
                    ->pluck('value', 'key')
                    ->toArray();

        $settings['BACKUP_DISK'] = env('BACKUP_DISK');
        $settings['DEFAULT_LANDING_PAGE'] = config('constants.landing_page');
        $settings['APP_TIMEZONE'] = config('app.timezone');
        $settings['support_timing'] = !empty($settings['support_timing']) ? json_decode($settings['support_timing'], true) : [];
        $settings['custom_fields'] = !empty($settings['custom_fields']) ? json_decode($settings['custom_fields'], true) : [];
        $settings['ticket_reminder_mail_template'] = !empty($settings['ticket_reminder_mail_template']) ? json_decode($settings['ticket_reminder_mail_template'], true) : [];
        $timezone_list = $this->CommonUtil->allTimeZones();
        if (config('app.env') != 'demo') {
            $settings['DROPBOX_ACCESS_TOKEN'] = env('DROPBOX_ACCESS_TOKEN');
        } else {
            $settings['DROPBOX_ACCESS_TOKEN'] = '';
        }

        $settings['signature_tags'] = System::getSignatureTags();
        $settings['ticket_reminder_tags'] = System::getTicketReminderEmailTags();

        $products = Product::getDropdown(false, true);
        $departments = Department::getDropdown();

        return Inertia::render('Settings/Index', compact('settings', 'timezone_list', 'products', 'departments'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
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
        
        if ($this->isDemo()) {
            return redirect()->action('SettingsController@index')
                ->with('error', __('messages.feature_disabled_in_demo'));
        }
        
        try {
            $systems = $request->only(['ticket_prefix', 'ticket_instruction',
                        'default_ticket_type', 'gcse_html', 'gcse_js', 'signature',
                        'auto_close_ticket_in_days', 'remind_ticket_in_days']);

            $checkboxes = $request->only(['cust_new_ticket_app_notif', 'cust_new_ticket_mail_notif',
                            'agent_replied_to_ticket_app_notif', 'agent_replied_to_ticket_mail_notif',
                            'agent_assigned_ticket_app_notif', 'agent_assigned_ticket_mail_notif',
                            'cust_replied_to_ticket_app_notif', 'cust_replied_to_ticket_mail_notif',
                            'is_public_ticket_enabled', 'enable_support_timing', 'other_agents_replied_to_ticket_app_notif', 'other_agents_replied_to_ticket_mail_notif']);

            foreach($checkboxes as $key => $value) {
                $systems[$key] = !empty($value) ? 1: 0;
            }

            $systems['support_timing'] = json_encode($request->input('support_timing'));
            $systems['custom_fields'] = json_encode($request->input('custom_fields'));
            $systems['ticket_reminder_mail_template'] = json_encode($request->input('ticket_reminder_mail_template'));
            $systems['auto_close_ticket_in_days'] = !empty($systems['auto_close_ticket_in_days']) ? $systems['auto_close_ticket_in_days'] : 0;
            $systems['remind_ticket_in_days'] = !empty($systems['remind_ticket_in_days']) ? $systems['remind_ticket_in_days'] : 0;

            foreach ($systems as $key => $value) {
                System::updateOrCreate(['key' => $key], ['value' => $value]);
            }

            $env_settings = $request->only('BACKUP_DISK', 'DROPBOX_ACCESS_TOKEN', 'DEFAULT_LANDING_PAGE', 'APP_TIMEZONE');

            $env_settings['BACKUP_DISK'] = !empty($env_settings['BACKUP_DISK']) ? $env_settings['BACKUP_DISK'] : 'local';

            $found_envs = [];
            $env_path = base_path('.env');
            $env_lines = file($env_path);
            foreach ($env_settings as $index => $value) {
                foreach ($env_lines as $key => $line) {
                    //Check if present then replace it.
                    if (strpos($line, $index) !== false) {
                        $env_lines[$key] = is_string($value) ? $index . '="' . $value . '"' . PHP_EOL : "$index=$value" . PHP_EOL;

                        $found_envs[] = $index;
                    }
                }
            }

            //Add the missing env settings
            $missing_envs = array_diff(array_keys($env_settings), $found_envs);
            if (!empty($missing_envs)) {
                $missing_envs = array_values($missing_envs);
                foreach ($missing_envs as $k => $key) {
                    if ($k == 0) {
                        $env_lines[] = is_string($env_settings[$key]) ? PHP_EOL . $key . '="' . $env_settings[$key] . '"' . PHP_EOL : PHP_EOL . "$key=$env_settings[$key]". PHP_EOL;
                    } else {
                        $env_lines[] = is_string($env_settings[$key]) ? $key . '="' . $env_settings[$key] . '"' . PHP_EOL : "$key=$env_settings[$key]" . PHP_EOL;
                    }
                }
            }

            $env_content = implode('', $env_lines);

            if (is_writable($env_path) && file_put_contents($env_path, $env_content)) {
                $msg = __('messages.success');
            } else {
                $msg = __('messages.env_permission');
            }

            return redirect()->action('SettingsController@index')->with('success', $msg);
        } catch (\Exception $e) {
            return redirect()->action('SettingsController@index')->with('error', __('messages.something_went_wrong'));
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
