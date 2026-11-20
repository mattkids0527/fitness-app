<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\AppVersion;
use Inertia\Inertia;
use Inertia\Response;

class VersionController extends Controller
{
    /**
     * 顯示設定頁的「版本」分頁：目前部署的版號與更新日期。
     */
    public function show(AppVersion $version): Response
    {
        return Inertia::render('settings/Version', [
            'version' => $version->current(),
        ]);
    }
}
