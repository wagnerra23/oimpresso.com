<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Artisan;
use Storage;
use Carbon\Carbon;
use App\Http\Util\CommonUtil;

class BackupController extends Controller
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
        if (!(auth()->user()->can('admin'))) {
            abort(403, 'Unauthorized action.');
        }

        $backups = [];
        $disk = Storage::disk(config('backup.backup.destination.disks')[0]);
        $files = $disk->files(str_replace(" ", "-", config('backup.backup.name')));
        // make an array of backup files, with their filesize and creation date
        foreach ($files as $k => $f) {
            // only take the zip files into account
            if (substr($f, -4) == '.zip' && $disk->exists($f)) {
                $file_name = str_replace(str_replace(" ", "-", config('backup.backup.name')) . '/', '', $f);
                $backups[] = [
                    'file_name' => $file_name,
                    'last_modified' => Carbon::createFromTimestamp($disk->lastModified($f))->format('d/m/Y h:i A'),
                    'download_link' => action('BackupController@download', [$file_name]),
                ];
            }
        }

        // reverse the backups, so the newest one would be on top
        $backups = array_reverse($backups);

        $cron_job_command = $this->CommonUtil->getCronJobCommand();
        
        return Inertia::render('Backup/Index', compact('backups', 'cron_job_command'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!(auth()->user()->can('admin'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            if ($this->isDemo()) {
                return redirect()->action('BackupController@index')
                    ->with('error', __('messages.feature_disabled_in_demo'));
            }
            
            // start the backup process
            Artisan::call('backup:run');

            $output = __('messages.backed_up_successfully');
        } catch (\Exception $e) {
            $output = __('messages.something_went_wrong');
        }
        
        return redirect()->action('BackupController@index')->with('success', $output);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
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
    public function destroy($file_name)
    {
        if (!(auth()->user()->can('admin'))) {
            abort(403, 'Unauthorized action.');
        }

        if ($this->isDemo()) {
            return redirect()->action('BackupController@index')
                ->with('error', __('messages.feature_disabled_in_demo'));
        }
        
        try {
            $disk = Storage::disk(config('backup.backup.destination.disks')[0]);

            if ($disk->exists(str_replace(" ", "-", config('backup.backup.name')) . '/' . $file_name)) {
                $disk->delete(str_replace(" ", "-", config('backup.backup.name')) . '/' . $file_name);
            }
            
            return redirect()->action('BackupController@index')->with('success',__('messages.success'));
        } catch (\Exception $e) {
            return redirect()->action('BackupController@index')->with('error', __('messages.something_went_wrong'));
        }
        
    }

    public function download($file_name)
    {
        if (!(auth()->user()->can('admin'))) {
            abort(403, 'Unauthorized action.');
        }

        if ($this->isDemo()) {
            return redirect()->action('BackupController@index')
                ->with('error', __('messages.feature_disabled_in_demo'));
        }

        $file = str_replace(" ", "-", config('backup.backup.name')) . '/' . $file_name;
        $disk = Storage::disk(config('backup.backup.destination.disks')[0]);
        if ($disk->exists($file)) {
            $fs = Storage::disk(config('backup.backup.destination.disks')[0])->getDriver();
            $stream = $fs->readStream($file);
            return \Response::stream(function () use ($stream) {
                fpassthru($stream);
            }, 200, [
                "Content-Type" => $fs->getMimetype($file),
                "Content-Length" => $fs->getSize($file),
                "Content-disposition" => "attachment; filename=\"" . basename($file) . "\"",
            ]);
        }
    }
}
