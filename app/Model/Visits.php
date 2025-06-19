<?php


namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Visits extends Model
{
    /**
     * Assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'visit_token',
        'visit_uuid',
        'is_creator'
    ];

    public static function addCreator($token, $uuid)
    {
        return self::create([
            'visit_token' => $token,
            'visit_uuid'   => $uuid,
            'is_creator' => true,
        ]);
    }

    public static function addVisit($token, $uuid)
    {
        return self::create([
            'visit_token' => $token,
            'visit_uuid'   => $uuid,
            'is_creator' => false,
        ]);
    }
}

