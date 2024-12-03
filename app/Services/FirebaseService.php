<?php

namespace App\Services;

use DateTime;
use DateTimeZone;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected $database;
    protected $factory;

    public function __construct()
    {
        $this->factory = (new Factory)
            ->withServiceAccount(storage_path('firebase/firebase_credentials.json'))
            ->withDatabaseUri(env('FIREBASE_DATABASE_URL'));

        $this->database = $this->factory->createDatabase();

    }

    public function getDatabase()
    {
        $reference = $this->database->getReference('iot');
        $data = $reference->getValue();

        return $data;
    }

    public function check(){
        $data = $this->getDatabase();
        $notified = $data['notified'];

        if($notified == 0){
            $this->checkNotif($data);
        } else {
            $remaining = $this->checkNotified($notified);
            if ($remaining >= 5){
                $onUpdate = $this->checkNotif($data);
                if($onUpdate == 0){
                    $this->notifyUpdate(true);
                }
            }
        }
    }

    public function checkNotified($notified){
        $date_now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $date_notif = DateTime::createFromFormat('H:i', $notified, new DateTimeZone('Asia/Jakarta'));
        $interval = $date_notif->diff($date_now);
        $remaining = ($interval->h * 60) + $interval->i;
        return $remaining;
    }

    public function checkNotif($data){
        $onUpdate = 0;
        $min_tmpt = 30;
        $min_hum = 30;
        $min_intens = 24;
        
        $now_tmpt = $data['temperature'];
        $now_hum = $data['humidity'];
        $now_intens = $data['intensity'];

        if($now_tmpt <= $min_tmpt){
            $onUpdate = 1;
            $this->sendNotif('Aku kepanasan');
        }

        if($now_hum <= $min_hum){
            $onUpdate = 1;
            echo 'air';
            $this->sendNotif('Aku kurang air');
        }

        if($now_intens <= $min_intens){
            $onUpdate = 1;
            $this->sendNotif('Aku kurang cahaya matahari');
        }

        return $onUpdate;
    }

    public function sendNotif($messageWords){
        $messaging = $this->factory->createMessaging();
        $topic = 'smartPlant';

        $message = CloudMessage::new()
            ->withNotification(Notification::create('Tanamanmu', $messageWords))
            ->withData([])
            ->toTopic($topic);

        try {
            $messaging->send($message);
            echo 'bisa';
        } catch (MessagingException $e) {
            echo 'GAGAL';
            echo $e->getMessage();
        }

        echo $messageWords;
        $this->notifyUpdate(false);
    }

    public function notifyUpdate($notified){
        $date_now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $notifyTime = $date_now->format('H:i');

        if($notified){
            $notifyTime = 0;
        }

        $reference = $this->database->getReference('iot');
        $reference->update(['notified' => $notifyTime]);
    }
}
