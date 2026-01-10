<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;

abstract class Controller extends \Illuminate\Routing\Controller
{
    use ApiResponse;
}
