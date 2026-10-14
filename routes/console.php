<?php

use Illuminate\Support\Facades\Schedule;

// Housekeeping for the scheduler container: drop week-old failed jobs.
Schedule::command('queue:prune-failed --hours=168')->daily()->name('prune-failed-jobs');
