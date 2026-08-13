<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->daily()->withoutOverlapping();
Schedule::command('homa:reconcile-operations')->everyFiveMinutes()->withoutOverlapping();
