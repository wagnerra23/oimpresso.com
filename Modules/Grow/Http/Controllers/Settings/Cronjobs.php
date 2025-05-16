<?php

/** --------------------------------------------------------------------------------
 * This controller manages all the business logic for cronjobs settings
 *
 * @package    Grow CRM
 * @author     NextLoop
 *----------------------------------------------------------------------------------*/

namespace Modules\Grow\Http\Controllers\Settings;
use Modules\Grow\Http\Controllers\Controller;
use Modules\Grow\Http\Responses\Settings\Cronjobs\IndexResponse;
use Modules\Grow\Repositories\SettingsRepository;

class Cronjobs extends Controller {

    /**
     * The settings repository instance.
     */
    protected $settingsrepo;

    public function __construct(SettingsRepository $settingsrepo) {

        //parent
        parent::__construct();

        //authenticated
        $this->middleware('auth');

        //settings general
        $this->middleware('settingsMiddlewareIndex');

        $this->settingsrepo = $settingsrepo;

    }

    /**
     * Display general settings
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {

        //[MT]
        if (config('system.settings_type') != 'standalone') {
            abort(404);
        }

        //crumbs, page data & stats
        $page = $this->pageSettings();

        $settings = \Modules\Grow\Models\Settings::find(1);

        //reponse payload
        $payload = [
            'page' => $page,
            'settings' => $settings,
        ];

        //show the view
        return new IndexResponse($payload);
    }

    /**
     * basic settings for the projects list page
     * @return array
     */
    private function pageSettings($section = '', $data = array()) {

        $page = [
            'crumbs' => [
                __('lang.settings'),
                __('lang.cronjob_settings'),
            ],
            'crumbs_special_class' => 'main-pages-crumbs',
            'page' => 'settings',
            'meta_title' => ' - ' . __('lang.settings'),
            'heading' => __('lang.settings'),
            'settingsmenu_general' => 'active',
        ];
        return $page;
    }

}
