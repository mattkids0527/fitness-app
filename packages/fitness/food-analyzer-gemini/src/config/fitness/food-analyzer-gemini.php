<?php

return [

    /*
    | Google AI Studio 的 API 金鑰（申請步驟見模組 README）；沒填時視為設定不齊全，不呼叫 Gemini。
    */
    'api_key' => env('GEMINI_API_KEY'),

    /*
    | 使用的模型 ID；預設值依 M4 模型實測結果（見 vibe-coding/milestones/M4-photo-analysis.md）。
    */
    'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),

    /*
    | 思考程度（Gemini 3 以後的 thinkingLevel：minimal、low、medium、high）；越高越慢，空值用模型預設。
    */
    'thinking_level' => env('GEMINI_THINKING_LEVEL', 'low'),

    /*
    | 取樣溫度；營養估算要穩定，設低一點。
    */
    'temperature' => 0.2,

    /*
    | 連線逾時秒數（整體逾時用 food-analyzer 的 timeout）。
    */
    'connect_timeout' => 5,

];
