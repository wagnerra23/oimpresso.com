<?php

/** --------------------------------------------------------------------------------
 * This controller manages all the business logic for system settings
 *
 * @package    Grow CRM
 * @author     NextLoop
 *----------------------------------------------------------------------------------*/

namespace Modules\Grow\Http\Controllers\Settings;
use Modules\Grow\Http\Controllers\Controller;
use Modules\Grow\Http\Responses\Common\CommonResponse;
use Modules\Grow\Repositories\SettingsRepository;

class System extends Controller {

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
     * Clear system cache
     *
     * @return \Illuminate\Http\Response
     */
    public function clearLaravelCache() {

        $settings = \Modules\Grow\Models\Settings::find(1);

        //clear cache
        \Artisan::call('cache:clear');
        \Artisan::call('route:clear');
        \Artisan::call('config:clear');

        //reponse payload
        $payload = [
            'type' => 'success-notification',
        ];

        //show the view
        return new CommonResponse($payload);
    }

}
