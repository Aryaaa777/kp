<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan aplikasi key/value (FR-191).
 *
 * Nilai disimpan sebagai teks/JSON dan tetap divalidasi oleh service pemakai.
 * Jangan menyimpan secret, password, atau data pribadi pada tabel ini.
 */
class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'value'];
}
