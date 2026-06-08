<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * US-NFE-041 security fix: move cert files from public/uploads (webroot) to
 * storage/app/nfe-certs (outside webroot).
 *
 * Antes: Storage::put() usava disco 'local' → public/uploads/nfe-brasil/{bid}/cert/{uuid}.pfx.enc
 * Depois: Storage::disk('nfe_certs') → storage/app/nfe-certs/{bid}/cert/{uuid}.pfx.enc
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('nfe_certificados')
            ->whereNotNull('uuid')
            ->get(['id', 'business_id', 'uuid']);

        foreach ($rows as $row) {
            $oldPath = public_path(sprintf('uploads/nfe-brasil/%d/cert/%s.pfx.enc', $row->business_id, $row->uuid));
            $newRelPath = sprintf('%d/cert/%s.pfx.enc', $row->business_id, $row->uuid);

            if (! file_exists($oldPath)) {
                // já movido ou nunca existiu no webroot (CT 100 usa storage_path correto)
                continue;
            }

            $encrypted = file_get_contents($oldPath);
            Storage::disk('nfe_certs')->put($newRelPath, $encrypted);

            if (Storage::disk('nfe_certs')->exists($newRelPath)) {
                @unlink($oldPath);
                Log::info('nfe_cert_moved_outside_webroot', [
                    'cert_id'    => $row->id,
                    'business_id'=> $row->business_id,
                    'new_path'   => $newRelPath,
                ]);
            } else {
                Log::error('nfe_cert_move_failed', [
                    'cert_id'    => $row->id,
                    'old_path'   => $oldPath,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Não reverter movimentação de arquivos — operação irreversível intencional.
    }
};
