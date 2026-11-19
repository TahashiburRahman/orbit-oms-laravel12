<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('releases:notify')->everyMinute()->withoutOverlapping();

