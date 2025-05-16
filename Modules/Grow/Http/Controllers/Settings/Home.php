<?php

/** --------------------------------------------------------------------------------
 * This controller manages all the business logic for expenses settings
 *
 * @package    Grow CRM
 * @author     NextLoop
 *----------------------------------------------------------------------------------*/

namespace Modules\Grow\Http\Controllers\Settings;
use Modules\Grow\Http\Controllers\Controller;
use Modules\Grow\Http\Responses\Settings\Home\IndexResponse;
use Modules\Grow\Repositories\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class Home extends Controller {

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
     * basic page setting for this section of the app
     * @param string $section page section (optional)
     * @param array $data any other data (optional)
     * @return array
     */
    private function pageSettings($section = '', $data = []) {

        $page = [
            'crumbs' => [
                __('lang.settings'),
            ],
            'crumbs_special_class' => 'main-pages-crumbs',
            'page' => 'settings settings-home',
            'meta_title' => __('lang.settings'),
            'heading' => __('lang.settings'),
            'settingsmenu_general' => 'active',
        ];
        return $page;
    }

}
