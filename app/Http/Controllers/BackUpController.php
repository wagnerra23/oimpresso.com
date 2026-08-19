<?php

namespace App\Http\Controllers;

use App\Utils\Util;
use Illuminate\Support\Facades\Artisan;
use Log;
use Storage;

class BackUpController extends Controller
{
    /**
     * Nome de arquivo aceito em download/delete. O disco de backup é
     * `public/uploads` (config/filesystems.php: local => public_path('uploads')),
     * então qualquer `..` aqui alcança arquivos de OUTROS tenants — Tier 0.
     */
    private const NOME_VALIDO = '/^[A-Za-z0-9_\-\.]+\.zip$/';

    /**
     * All Utils instance.
     */
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('backup')) {
            abort(403, 'Unauthorized action.');
        }

        $disk = Storage::disk(config('backup.backup.destination.disks')[0]);

        $files = $disk->files(config('backup.backup.name'));

        $backups = [];
        // make an array of backup files, with their filesize and creation date
        foreach ($files as $k => $f) {
            // only take the zip files into account
            if (substr($f, -4) == '.zip' && $disk->exists($f)) {
                $backups[] = [
                    'file_path' => $f,
                    'file_name' => str_replace(str_replace('\\', '/', config('backup.backup.name')).'/', '', $f),
                    'file_size' => $disk->size($f),
                    'last_modified' => $disk->lastModified($f),
                ];
            }
        }
        // reverse the backups, so the newest one would be on top
        $backups = array_reverse($backups);

        $cron_job_command = $this->commonUtil->getCronJobCommand();
        
        // $backup_clean_cron_job_command = $this->commonUtil->getBackupCleanCronJobCommand();

        return view('backup.index')
            ->with(compact('backups', 'cron_job_command'));
    }

    /**
     * Create a resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('backup')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            //Disable in demo
            $notAllowed = $this->commonUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            // start the backup process
            Artisan::call('backup:run');
            $output = Artisan::output();

            // log the results
            Log::info("Backpack\BackupManager -- new backup started from admin interface \r\n".$output);

            $output = ['success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Throwable $e) {
            // O legado usava `catch (Exception $e)` sem barra dentro do namespace
            // App\Http\Controllers: a classe não existe, o catch nunca casava e
            // qualquer falha do backup:run virava 500 em vez de banner.
            report($e);

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        return back()->with('status', $output);
    }

    /**
     * POST /backup — registrada por Route::resource(...)->only(..., 'store').
     * O legado não implementava este método: a rota existia e estourava 500.
     */
    public function store()
    {
        return $this->create();
    }

    /**
     * Downloads a backup zip file.
     *
     * TODO: make it work no matter the flysystem driver (S3 Bucket, etc).
     */
    public function download($file_name)
    {
        if (! auth()->user()->can('backup')) {
            abort(403, 'Unauthorized action.');
        }

        //Disable in demo
        if (config('app.env') == 'demo') {
            $output = ['success' => 0,
                'msg' => 'Feature disabled in demo!!',
            ];

            return back()->with('status', $output);
        }

        [$disk, $path] = $this->resolverArquivo($file_name);

        return $disk->download($path, basename($path));
    }

    /**
     * Deletes a backup file.
     */
    public function delete($file_name)
    {
        if (! auth()->user()->can('backup')) {
            abort(403, 'Unauthorized action.');
        }

        //Disable in demo
        if (config('app.env') == 'demo') {
            $output = ['success' => 0,
                'msg' => 'Feature disabled in demo!!',
            ];

            return back()->with('status', $output);
        }

        // Valida o nome ANTES de qualquer outra coisa: nome suspeito e sempre 404,
        // independente de quantos backups existam no disco.
        [$disk, $path] = $this->resolverArquivo($file_name);

        // Nunca deixar o disco sem nenhum backup.
        if (count($this->arquivosZip()) <= 1) {
            return back()->with('status', [
                'success' => 0,
                'msg' => __('lang_v1.backup_ultimo_nao_excluir'),
            ]);
        }

        $disk->delete($path);

        return back()->with('status', [
            'success' => 1,
            'msg' => __('lang_v1.success'),
        ]);
    }

    /**
     * Lista os .zip da pasta de backup no disco configurado.
     *
     * @return array<int, string>
     */
    private function arquivosZip()
    {
        $disk = Storage::disk(config('backup.backup.destination.disks')[0]);
        $pasta = str_replace('\\', '/', (string) config('backup.backup.name'));

        return array_values(array_filter(
            $disk->files($pasta),
            function ($f) {
                return substr($f, -4) === '.zip';
            }
        ));
    }

    /**
     * Valida o nome do arquivo e devolve [disk, caminho]. 404 em qualquer suspeita.
     *
     * O legado concatenava `config('backup.backup.name').'/'.$file_name` sem
     * validar: como o disco é `public/uploads`, um `..` alcançava (e o delete
     * APAGAVA) arquivo de qualquer outro tenant.
     *
     * @param  string  $file_name
     * @return array{0: \Illuminate\Contracts\Filesystem\Filesystem, 1: string}
     */
    private function resolverArquivo($file_name)
    {
        abort_unless(is_string($file_name) && preg_match(self::NOME_VALIDO, $file_name) === 1, 404, "The backup file doesn't exist.");
        abort_if(str_contains($file_name, '..'), 404, "The backup file doesn't exist.");

        $pasta = str_replace('\\', '/', (string) config('backup.backup.name'));
        $disk = Storage::disk(config('backup.backup.destination.disks')[0]);
        $path = $pasta.'/'.$file_name;

        // Cinto e suspensório: o arquivo tem que estar na listagem real da pasta.
        $naPasta = in_array($file_name, array_map('basename', $this->arquivosZip()), true);

        abort_unless($naPasta && $disk->exists($path), 404, "The backup file doesn't exist.");

        return [$disk, $path];
    }
}
