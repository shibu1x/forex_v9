<?php

namespace App\Models\Concept;

use Carbon\Carbon;

class Util
{
    public static function convertCarbon(int $time): Carbon
    {
        return Carbon::createFromFormat('!Ymd', strval($time));
    }
}
