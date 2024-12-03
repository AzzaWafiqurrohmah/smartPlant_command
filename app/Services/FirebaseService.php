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
        foreach ($data as $deviceName => $item){
            $notified = $item['notified'];

            if($notified == 0){
                $this->checkNotif($item, $deviceName);
            } else {
                $remaining = $this->checkNotified($notified);
                if ($remaining >= 5){
                    $onUpdate = $this->checkNotif($item, $deviceName);
                    if($onUpdate == 0){
                        $this->notifyUpdate(true, $deviceName);
                    }
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

    public function checkNotif($data, $deviceName){
        $onUpdate = 0;
        $min_tmpt = 30;
        $min_hum = 30;
        $min_intens = 24;
        
        $now_tmpt = $data['temperature'];
        $now_hum = $data['humidity'];
        $now_intens = $data['intensity'];
        $name = $data['name'];

        if($now_tmpt >= $min_tmpt){
            $onUpdate = 1;
            $this->sendNotif($name, 'Suhu terlalu panas', $deviceName);
            $this->conditionUpdate($deviceName, 'Suhu terlalu panas');
        }

        if($now_hum <= $min_hum){
            $onUpdate = 1;
            $this->sendNotif($name, 'Tanaman kurang air', $deviceName);
            $this->conditionUpdate($deviceName, 'Perlu disiram');
        }

        if($now_intens <= $min_intens){
            $onUpdate = 1;
            $this->sendNotif($name, 'Aku kurang cahaya matahari', $deviceName);
            $this->conditionUpdate($deviceName, 'Kekurangan cahaya matahari');
        }

        return $onUpdate;
    }

    public function sendNotif($name, $messageWords, $deviceName){
        $messaging = $this->factory->createMessaging();
        $topic = 'smartPlant';

        $message = CloudMessage::new()
            ->withNotification(Notification::create($name, $messageWords))
            ->withData([])
            ->toTopic($topic);

        try {
            $messaging->send($message);
        } catch (MessagingException $e) {
            echo $e->getMessage();
        }

        $this->notifyUpdate(false, $deviceName);
    }

    public function notifyUpdate($notified, $deviceName){
        $date_now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $notifyTime = $date_now->format('H:i');

        if($notified){
            $notifyTime = 0;
        }

        $reference = $this->database->getReference('iot' . '/' . $deviceName);
        $reference->update(['notified' => $notifyTime]);
    }

    public function conditionUpdate($deviceName, $message){
        $reference = $this->database->getReference('iot' . '/' . $deviceName);
        $reference->update(['condition' => $message]);
    }
} 
