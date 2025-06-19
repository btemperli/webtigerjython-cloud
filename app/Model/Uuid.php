<?php


namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class Uuid extends Model
{
    /**
     * Assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'uuid_uuid',
        'uuid_ip',
        'uuid_user_agent',
    ];

    public static function createFromRequest(Request $request)
    {
        $attempts = 0;
        while ($attempts < 25) {
            try {
                $uuid = Str::uuid()->toString();
                self::create([
                    'uuid_uuid' => $uuid,
                    'uuid_ip'   => $request->server('REMOTE_ADDR'),
                    'uuid_user_agent' => $request->server('HTTP_USER_AGENT'),
                ]);
                return $uuid;
            } catch (QueryException $e) {
                if ($e->getCode() == '23000') {
                    $attempts++;
                    continue;
                }
                throw $e;
            }
        }

        throw new \RuntimeException('error: could not create a new id');
    }

    public static function checkUuid($uuid)
    {
        $db_uuid =  self::where('uuid_uuid', $uuid)->first();

        if (!$db_uuid) {
            return false;
        }

        return true;
    }
}

