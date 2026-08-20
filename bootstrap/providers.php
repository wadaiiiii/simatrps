<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\WeeklyAssessmentAiServiceProvider;

return [
    AppServiceProvider::class,
    WeeklyAssessmentAiServiceProvider::class,
    FortifyServiceProvider::class,
];
