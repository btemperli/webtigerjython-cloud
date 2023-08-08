<?php

namespace App\Http\Controllers;

use App\Model\WtjToken;
use Illuminate\Http\Request;

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
            return response()->json('error');
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

        $results = ['share_id' => $randomToken];
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
            return response($share_id . " not found.", 404);
        }

        $parameter = [
            'share_id' => $share_id,
            'code' => $loadCode['code'],
            'return' => false,
        ];

        return view('original/webtigerjython', $parameter);
    }

    public function wtj_get_return_code(Request $request, $share_id, $return_id) {
        $code = '\"# This is my presaved code...\\r\\n\\r\\ndef helloWorld():\\r\\n    print(\\\"hello world\\\")\\r\\n    \\r\\nhelloWorld()\"';
        // todo: get code from the database.

        $loadCode = $this->loadCode($share_id, $return_id);

        if ($loadCode === 404) {
            return response($share_id . "/". $return_id . " not found.", 404);
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

    public function wtj()
    {
        return view('original/webtigerjython');
    }
}
