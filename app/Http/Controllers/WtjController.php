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
        if (!$request->has('code')) {
            return response()->json('error', 500);
        }

        if (!$request->has('access')) {
            return response()->json('error', 500);
        }

        if (!$request->has('uuid')) {
            return response()->json('error', 500);
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
        if (!$request->has('share_url')) {
            return response('', 500)->json('error');
        }

        if (!$request->has('code')) {
            return response('', 500)->json('error');
        }

        if (!$request->has('markers')) {
            return response('', 500)->json('error');
        }

        $code = $request->get('code');
        $randomReturnToken = $this->getRandomToken('wtj_return_token');
        $marker = $request->get('markers');
        $markerString = $this->arrayToString($marker);
        $entry = [
            'wtj_token' => $request->get('share_url'),
            'wtj_return_token' => $randomReturnToken,
            'wtj_code' => $code,
            'wtj_marker' => $markerString,
        ];
        $save = WtjToken::create($entry);

        if (!$save) {
            return response('', 500)->json('save: error');
        }

        $results = [
            'share_id' => $request->get('share_url'),
            'return_id' => $randomReturnToken,
        ];
        return response()->json($results);
    }

    public function wtj_get_code(Request $request, $share_id) {
        $loadCode = $this->loadCode($share_id);

        if ($loadCode === 404) {
            return response("<h1>Code nicht gefunden</h1><br>Der Code <b>$share_id</b> existiert nicht im System.", 404);
        }

        $parameter = [
            'share_id' => $share_id,
            'code' => $loadCode['code'],
            'return' => false,
        ];

        return view('original/webtigerjython', $parameter);
    }

    public function wtj_get_return_code(Request $request, $share_id, $return_id) {
        $loadCode = $this->loadCode($share_id, $return_id);

        if ($loadCode === 404) {
            return response("<h1>Code nicht gefunden</h1><br>Der Code <b>$share_id/$return_id</b> existiert nicht im System.", 404);
        }

        $markers = $this->stringToArray($loadCode['entry_raw']['wtj_marker']);

        $parameter = [
            'share_id' => $share_id,
            'return_id' => $return_id,
            'code' => $loadCode['code'],
            'markers' => $markers,
            'return' => true,
        ];

        return view('original/webtigerjython', $parameter);
    }

    public function admin(Request $request) {
        $correctPassword = $_ENV['ADMIN_LOG_PASSWORD'] ?? null;
        $inputPassword = $request->query->get('pw');

        if (!$correctPassword || $inputPassword !== $correctPassword) {
            return new Response('Zugriff verweigert: Ungültiges Passwort.', Response::HTTP_FORBIDDEN);
        }

        $tokens = WtjToken::select('wtj_token', 'wtj_return_token', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

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
