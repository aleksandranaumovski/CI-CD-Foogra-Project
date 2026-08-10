<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // Gives every controller $this->authorize() and $this->validate().
    use AuthorizesRequests, ValidatesRequests;
}
