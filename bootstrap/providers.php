<?php

use App\Providers\AppServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    DomainServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
];
