<?php

namespace Modules\NFSe\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Modules\NFSe\Models\Concerns\NfseBusinessScope;

class NfseCertificado extends Model
{
    use SoftDeletes, NfseBusinessScope;

    protected $table = 'nfe_certificados';

    protected $fillable = [
        'business_id', 'cert_pfx_encrypted', 'senha_encrypted',
        'valido_ate', 'titular_cnpj', 'titular_nome', 'ativo',
    ];

    protected $casts = [
        'valido_ate' => 'date',
        'ativo'      => 'boolean',
    ];

    protected $hidden = ['cert_pfx_encrypted', 'senha_encrypted'];

    public function isExpirado(): bool
    {
        return $this->valido_ate->isPast();
    }

    public function diasParaExpirar(): int
    {
        return (int) now()->diffInDays($this->valido_ate, false);
    }

    public function pfxDecriptado(): string
    {
        return Crypt::decryptString($this->cert_pfx_encrypted);
    }

    public function senhaDecriptada(): string
    {
        return Crypt::decryptString($this->senha_encrypted);
    }

    public static function uploadCert(int $businessId, string $pfxBase64, string $senha): self
    {
        $pfxBin = base64_decode($pfxBase64);

        // Lê validade e titular do cert antes de salvar
        $cert = openssl_pkcs12_read($pfxBin, $certs, $senha);
        if (! $cert) {
            throw new \Modules\NFSe\Exceptions\CertificadoInvalidoException('Senha incorreta ou arquivo corrompido');
        }

        $info       = openssl_x509_parse($certs['cert']);
        $validoAte  = Carbon::createFromTimestamp($info['validTo_time_t']);
        $titular    = $info['subject']['CN'] ?? null;
        $cnpj       = preg_replace('/\D/', '', $info['subject']['serialNumber'] ?? '');

        return self::create([
            'business_id'        => $businessId,
            'cert_pfx_encrypted' => Crypt::encryptString(base64_encode($pfxBin)),
            'senha_encrypted'    => Crypt::encryptString($senha),
            'valido_ate'         => $validoAte,
            'titular_nome'       => $titular,
            'titular_cnpj'       => $cnpj ?: null,
            'ativo'              => true,
        ]);
    }
}
