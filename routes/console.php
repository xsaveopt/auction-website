<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:finalize-ended-auctions')->everyMinute();
Schedule::command('app:notify-ending-soon')->everyMinute();
