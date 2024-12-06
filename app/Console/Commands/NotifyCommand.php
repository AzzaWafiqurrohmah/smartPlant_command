<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use Illuminate\Console\Command;

class NotifyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notify-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notification Command';
    protected $firebase;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebase = $firebaseService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->firebase->check();
        $this->info('succes!');
    }
}
