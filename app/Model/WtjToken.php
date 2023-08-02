<?php


namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WtjToken extends Model
{
    /**
     * Assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'wtj_token',
        'wtj_return_token',
        'wtj_code',
        'wtj_marker'
    ];
}

