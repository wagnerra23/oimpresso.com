<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use App\System;

class License extends Model
{
    /**
	 * The attributes that aren't mass assignable.
	 *
	 * @var array
	 */
	protected $guarded = ['id'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['product_license_key'];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'additional_info' => 'array',
    ];
    
	/**
     * The source that belongs to product.
     */
    public function source()
    {
        return $this->belongsTo('App\Source');
    }

    /**
     * encrypte the license key
     *
     * @param $license_key
     */
    public function setLicenseKeyAttribute($license_key)
    {   
        $encrypter = new \Illuminate\Encryption\Encrypter(System::getProperty('encryption_key'), config('app.cipher'));
        $this->attributes['license_key'] = $encrypter->encrypt($license_key);
    }

    /**
     * decrypt the license key
     *
     * @return license_key
     */
    public function getProductLicenseKeyAttribute()
    {   
        $encryption_key = System::getProperty('encryption_key');
        $product_license_key = License::decryptGivenLicenseKey($this->license_key, $encryption_key);
        
        return $product_license_key;
    }

    public static function decryptGivenLicenseKey($license_key, $encryption_key)
    {
        try {
            $decrypter = new \Illuminate\Encryption\Encrypter($encryption_key, config('app.cipher'));
            return $decrypter->decrypt($license_key);
        } catch (\Exception $e) {
            return 'INVALID_LICENSE';
        }
    }

    /**
     * validate license key.
     *
     * @param  string  $license_key
     * @return boolean
     */
    public static function checkLicenseExist($license_key){
        $licenses = License::all();
        $is_exist = false;
        $encryption_key = System::getProperty('encryption_key');
        foreach ($licenses as $key => $value) {
            if(License::decryptGivenLicenseKey($value->license_key, $encryption_key) == $license_key){
                $is_exist = true;
                break;
            }
        }

        return $is_exist;
    }
}
