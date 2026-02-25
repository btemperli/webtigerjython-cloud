<?php

namespace App\Http\Controllers;

use App\Model\Uuid;
use App\Model\Visits;
use App\Model\WtjToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WtjController extends Controller
{
    private function generateRandomHashedString() {
        $currentTime = time();
        $randomString = substr(md5($currentTime), 0, 6);
        $filteredString = preg_replace("/[^A-Za-z0-9]/", "", $randomString);
        return $filteredString;
    }

    private function getRandomToken($whichToken) {
        // generate new token
        $randomTokenIsUnique = false;
        while (!$randomTokenIsUnique) {
            $randomToken = $this->generateRandomHashedString();
            $existingToken = WtjToken::where($whichToken, $randomToken)->first();
            if (!$existingToken) {
                $randomTokenIsUnique = true;
            }
        }
        return $randomToken;
    }

    private function arrayToString($array) {
        return serialize($array);
    }

    private function stringToArray($string) {
        return unserialize($string);
    }

    /**
     * Generate a Hashed Access Code to check, if the requests are valid
     *
     * @return string hashed Value in the form
     * xxxxxxxxxxxxxx==.123456789abcdef123456789abcdef123456789abcdef123456789abcdef1234
     */
    private function generateHashedAccessCode(): string
    {
        $secret = $_ENV['ACCESS_CODE'];
        $timestamp = time();
        $signature = hash_hmac('sha256', (string)$timestamp, $secret);
        $code = base64_encode($timestamp) . '.' . $signature;
        return $code;
    }

    private function validateHashedAccessCode(string $code): bool
    {
        $secret = $_ENV['ACCESS_CODE'];

        [$encodedTime, $providedHash] = explode('.', $code);
        $timestamp = (int)base64_decode($encodedTime);
        $expectedHash = hash_hmac('sha256', (string)$timestamp, $secret);

        if (!hash_equals($expectedHash, $providedHash)) {
            return false;
        }

        // check time: 48h = 172800s
        if ((time() - $timestamp) > 172800) {
            return false;
        }

        return true;
    }

    private function loadCode($share_id, $return_id = null) {
        $entry = WtjToken::where('wtj_token', $share_id)->where('wtj_return_token', $return_id)->first();
        if (!$entry) {
            return 404;
        }
        $entry_raw = $entry->toArray();
        $code = $entry_raw['wtj_code'];

        // remove unused double-\.
        $replacements = array(
            '\\\\n' => '\n',
            '\\\\r' => '\r',
            '\\\\\"' => '\\"',
        );
        $code = str_replace(array_keys($replacements), array_values($replacements), $code);

        return [
            'code' => $code,
            'entry' => $entry,
            'entry_raw' => $entry_raw,
        ];
    }

    private function validateRequestFields(Request $request, array $requiredFields)
    {
        foreach ($requiredFields as $field) {
            if (!$request->has($field)) {
                return response()->json([
                    'error' => "error: $field"
                ], 500);
            }
        }

        return null; // alles ok
    }

    private function generateDynamicColorPalette(int $count): array
    {
        $maxColors = min($count, 30); // ab 30 Wiederholung
        $step = 360 / $maxColors;
        $colors = [];

        for ($i = 0; $i < $count; $i++) {
            $hue = ($i % $maxColors) * $step;
            $colors[] = "hsl($hue, 65%, 45%)";
        }

        return $colors;
    }

    /**
     * Load visitor-infos for this page.
     * -----------------------
     *
     * @param Request $request
     * @param $visitToken
     * @return array|false
     */
    private function getVisits(Request $request, $visitToken): bool|array
    {
        if (!$request->has('admin')) {
            return false;
        }

        $allVisits = Visits::getVisits($visitToken);
        $uuids = $allVisits->pluck('visit_uuid')->unique()->toArray();
        $uuidData = Uuid::whereIn('uuid_uuid', $uuids)->get()->keyBy('uuid_uuid');
        $colorPalette = $this->generateDynamicColorPalette(count($uuids));
        $uuidToColor = [];

        $index = 0;
        foreach ($uuids as $uuid) {
            $uuidToColor[$uuid] = $colorPalette[$index];
            $index += 1;
        }

        $result = [];

        foreach ($allVisits as $visit) {
            $uuid = $visit->visit_uuid;
            $uuidInfo = $uuidData[$uuid] ?? null;

            $result[] = [
                'visit_token' => $visit->visit_token,
                'is_creator'  => $visit->is_creator,
                'uuid'        => $uuid,
                'created_at'  => $visit->created_at->setTimezone('Europe/Berlin')->format('d.m.Y - H:i'),
                'ip'          => $uuidInfo->uuid_ip ?? null,
                'user_agent'  => $uuidInfo->uuid_user_agent ?? null,
                'color'       => $uuidToColor[$uuid] ?? '#000000', // fallback
            ];
        }

        return $result;
    }


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * WTJ Share: add new code to database.
     * Return JsonResponse with the new share-url
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function wtj_add_code(Request $request) {
        $check = $this->validateRequestFields($request, ['code', 'access', 'uuid']);
        if ($check) {
            return $check;
        }

        $uuid = $request->get('uuid');
        $access = $request->get('access');
        if (!$uuid && $access) {
            if (!$this->validateHashedAccessCode($access)) {
                return response()->json('error', 401);
            }
            $uuid = Uuid::createFromRequest($request);
        } else {
            if (!Uuid::checkUuid($uuid)) {
                return response()->json('error');
            }
        }

        $code = $request->get('code');
        $randomToken = $this->getRandomToken('wtj_token');

        $entry = [
            'wtj_code' => $code,
            'wtj_token' => $randomToken,
        ];
        $save = WtjToken::create($entry);

        if (!$save) {
            return response()->json('save: error');
        }

        // save uuid as creator
        Visits::addCreator($randomToken, $uuid);

        $results = [
            'share_id' => $randomToken,
            'uuid' => $uuid,
        ];
        return response()->json($results);
    }

    public function wtj_add_return_code(Request $request) {
        $check = $this->validateRequestFields($request, ['share_url', 'code', 'markers']);
        if ($check) {
            return $check;
        }

        $uuid = $request->get('uuid');
        $access = $request->get('access');
        if (!$uuid && $access) {
            if (!$this->validateHashedAccessCode($access)) {
                return response()->json('error', 401);
            }
            $uuid = Uuid::createFromRequest($request);
        } else {
            if (!Uuid::checkUuid($uuid)) {
                return response()->json('error');
            }
        }

        $code = $request->get('code');
        $randomReturnToken = $this->getRandomToken('wtj_return_token');
        $marker = $request->get('markers');
        $markerString = $this->arrayToString($marker);
        $shareUrl = $request->get('share_url');
        $entry = [
            'wtj_token' => $shareUrl,
            'wtj_return_token' => $randomReturnToken,
            'wtj_code' => $code,
            'wtj_marker' => $markerString,
        ];
        $save = WtjToken::create($entry);

        if (!$save) {
            return response('', 500)->json('save: error');
        }

        Visits::addCreator($shareUrl . '/' . $randomReturnToken, $uuid);

        $results = [
            'share_id' => $shareUrl,
            'return_id' => $randomReturnToken,
        ];
        return response()->json($results);
    }

    public function wtj_get_code(Request $request, $share_id) {
        $loadCode = $this->loadCode($share_id);

        if ($loadCode === 404) {
            return response("<h1>Code nicht gefunden</h1><br>Der Code <b>$share_id</b> existiert nicht im System.", 404);
        }

        $hashedAccessCode = $this->generateHashedAccessCode();

        $parameter = [
            'share_id' => $share_id,
            'code' => $loadCode['code'],
            'return' => false,
            'hashed_access_code' => $hashedAccessCode,
            'visits' => $this->getVisits($request, $share_id),
        ];

        return view('original/webtigerjython', $parameter);
    }

    public function wtj_get_uuid(Request $request)
    {
        $check = $this->validateRequestFields($request, ['shareId', 'returnId', 'access', 'uuid']);
        if ($check) {
            return $check;
        }

        $access = $request->get('access');
        $shareId = $request->get('shareId');
        $returnId = $request->get('returnId');

        if (!$access) {
            return response()->json('error: no access', 401);
        }

        if (!$this->validateHashedAccessCode($access)) {
            return response()->json('error: access', 401);
        }

        $uuid = Uuid::createFromRequest($request);

        if ($returnId && $shareId) {
            Visits::addVisit($shareId . '/' . $returnId, $uuid);
        }
        elseif ($shareId) {
            Visits::addVisit($shareId, $uuid);
        }

        $results = [
            'uuid' => $uuid,
        ];

        return response()->json($results);
    }

    public function wtj_save_visit(Request $request)
    {
        $check = $this->validateRequestFields($request, ['shareId', 'returnId', 'access', 'uuid']);
        if ($check) {
            return $check;
        }

        $access = $request->get('access');
        $uuid = $request->get('uuid');
        $shareId = $request->get('shareId');
        $returnId = $request->get('returnId');

        if (!$access || !$uuid) {
            return response()->json('error: access && uuid', 401);
        }

        if (!$this->validateHashedAccessCode($access)) {
            return response()->json('error: access', 401);
        }

        if (!Uuid::checkUuid($uuid)) {
            return response()->json('error: uuid', 401);
        }

        if ($returnId && $shareId) {
            Visits::addVisit($shareId . '/' . $returnId, $uuid);
        }
        elseif ($shareId) {
            Visits::addVisit($shareId, $uuid);
        }

        $results = [
            'uuid' => $uuid,
        ];

        return response()->json($results);
    }

    public function wtj_get_return_code(Request $request, $share_id, $return_id) {
        $loadCode = $this->loadCode($share_id, $return_id);

        if ($loadCode === 404) {
            return response("<h1>Code nicht gefunden</h1><br>Der Code <b>$share_id/$return_id</b> existiert nicht im System.", 404);
        }

        $markers = $this->stringToArray($loadCode['entry_raw']['wtj_marker']);
        $hashedAccessCode = $this->generateHashedAccessCode();


        $parameter = [
            'share_id' => $share_id,
            'return_id' => $return_id,
            'code' => $loadCode['code'],
            'markers' => $markers,
            'return' => true,
            'hashed_access_code' => $hashedAccessCode,
            'visits' => $this->getVisits($request, $share_id . '/' . $return_id),
        ];

        return view('original/webtigerjython', $parameter);
    }

    public function admin(Request $request) {
        $correctPassword = $_ENV['ADMIN_LOG_PASSWORD'] ?? null;
        $inputPassword = $request->query('pw');

        if (!$correctPassword || $inputPassword !== $correctPassword) {
            return new Response('Zugriff verweigert: Ungültiges Passwort.', Response::HTTP_FORBIDDEN);
        }

//        $tokens = WtjToken::select('wtj_token', 'wtj_return_token', 'created_at')
//            ->withCount(
//                'token_visits'
//            )
//            ->selectRaw('(SELECT COUNT(*) FROM visits WHERE visit_token = CONCAT(wtj_tokens.wtj_token, "/", wtj_tokens.wtj_return_token)) AS combo_visits_count')
//            ->orderBy('created_at', 'desc')
//            ->get();

        $tokens = WtjToken::select('id', 'wtj_token', 'wtj_return_token', 'created_at')
            ->withCount('token_visits')
            ->orderBy('created_at', 'desc')
            // Die combo_visits_count Subquery bleibt teuer,
            // wird jetzt aber nur noch für z.B. 50 Einträge ausgeführt.
            ->selectRaw('(SELECT COUNT(*) FROM visits WHERE visit_token = CONCAT(wtj_tokens.wtj_token, "/", wtj_tokens.wtj_return_token)) AS combo_visits_count')
            ->paginate(1000);

        return view('admin', [
            'tokens' => $tokens,
            'count' => sizeof($tokens),
        ]);
    }

    public function wtj()
    {
        $hashedAccessCode = $this->generateHashedAccessCode();
        return view('original/webtigerjython', [
            'hashed_access_code' => $hashedAccessCode,
        ]);
    }
}
