<?php

define('APP_DEBUG', true);

include '../../../framework/app.php';

framework\App::start('Standard', [
    
    'default_dispatch_param_mode' => 1,
    
])->run();