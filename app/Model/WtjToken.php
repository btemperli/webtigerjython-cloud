<?php


namespace App\Model;

use App\Model\Visits;
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

    public function token_visits()
    {
        return $this->hasMany(Visits::class, 'visit_token', 'wtj_token');
    }
}

