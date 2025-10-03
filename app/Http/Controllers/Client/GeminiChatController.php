<?php

namespace App\Http\Controllers\Client;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\GeminiAI\GeminiAiService;

class GeminiChatController extends Controller
{
    public function __construct(
        private GeminiAiService $ai
    ) {}

    public function chat(Request $req)
    {
        $v = $req->validate([
            'message' => ['required', 'string', 'max:5000']
        ]);

        $result = $this->ai->chat($v['message']);
        return response()->json($result);
    }
}
