<?php

use Fitness\LlmLog\Repositories\Contracts\LlmLogRepository as LlmLogRepositoryContract;
use Fitness\LlmLog\Repositories\LlmLogRepository;

return [

    /*
    | Repository 介面 => 實作，由 LlmLogServiceProvider::bindInstances() 綁定。
    */
    'binds' => [
        LlmLogRepositoryContract::class => LlmLogRepository::class,
    ],

    /*
    | 單例服務 abstract => class，由 LlmLogServiceProvider::registerSingletons() 註冊。
    */
    'singletons' => [],

];
