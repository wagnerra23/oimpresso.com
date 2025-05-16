<?php

/** --------------------------------------------------------------------------------
 * This middleware class handles [edit checklist] precheck processes for tasks
 *
 * @package    Grow CRM
 * @author     NextLoop
 *----------------------------------------------------------------------------------*/

namespace Modules\Grow\Http\Middleware\Tasks;
use Modules\Grow\Models\Task;
use Modules\Grow\Http\Permissions\ChecklistPermissions;
use Closure;
use Log;

class EditDeleteChecklist {

    /**
     * The permisson repository instance.
     */
    protected $checklistpermissions;

    /**
     * Inject any dependencies here
     *
     */
    public function __construct(ChecklistPermissions $checklistpermissions) {

        //permissions
        $this->checklistpermissions = $checklistpermissions;

    }

    /**
     * Check user permissions to edit a task
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {

        //attachement id
        $checklist_id = $request->route('checklistid');

        //does the task exist
        if (!$checklist = \Modules\Grow\Models\Checklist::Where('checklist_id', $checklist_id)->first()) {
            Log::error("checklist could not be found", ['process' => '[permissions][tasks][delete-checklist]', 'ref' => config('app.debug_ref'), 'function' => __function__, 'file' => basename(__FILE__), 'line' => __line__, 'path' => __file__, 'checklist id' => $checklist_id ?? '']);
            
            //error - remove item with message
            $jsondata['dom_visibility'][] = array(
                'selector' => "#task_checklist_container_$checklist_id",
                'action' => 'slideup-slow-remove',
            );
            $jsondata['notification'] = [
                'type' => 'force-error',
                'value' => __('lang.item_nolonger_exists_or_removed'),
            ];
            return response()->json($jsondata);
        }

        //check permissions
        if ($checklist->checklistresource_type == 'task') {
                if ($this->checklistpermissions->check('edit-delete', $checklist_id)) {
                    return $next($request);
                }
        }

        //no items were passed with this request
        Log::error("permission denied", ['process' => '[permissions][tasks][delete-checklist]', 'ref' => config('app.debug_ref'), 'function' => __function__, 'file' => basename(__FILE__), 'line' => __line__, 'path' => __file__, 'checklist id' => $checklist_id ?? '']);
        abort(403);
    }
}