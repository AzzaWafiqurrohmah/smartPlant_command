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
                        $this->conditionUpdate($deviceName, 'Aku Sehat');
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

    public function checkNotif($data, $deviceName)
    {
        $conditions = [
            [
                'key' => 'temperature',
                'min' => 25,
                'max' => 33,
                'minMessage' => 'Suhu terlalu dingin!',
                'maxMessage' => 'Suhu terlalu panas!',
            ],
            [
                'key' => 'humidity',
                'min' => 50,
                'max' => 80,
                'minMessage' => 'Tanamanmu kurang air!',
                'maxMessage' => 'Tanamanmu kelebihan air!',
            ],
            [
                'key' => 'intensity',
                'min' => 1156,
                'max' => 1818,
                'minMessage' => 'Tempat ini terlalu redup!',
                'maxMessage' => 'Tempat ini terlalu terang!',
            ],
        ];

        $onUpdate = 0;

        foreach ($conditions as $condition) {
            $nowVal = $data[$condition['key']];
            $onUpdate += $this->evaluateCondition(
                $data['name'],
                $deviceName,
                $nowVal,
                $condition['min'],
                $condition['max'],
                $condition['minMessage'],
                $condition['maxMessage']
            );
        }

        return $onUpdate > 0 ? 1 : 0;
    }

    private function evaluateCondition($name, $deviceName, $nowVal, $minVal, $maxVal, $minMessage, $maxMessage)
    {
        $updated = 0;

        if ($nowVal < $minVal) {
            $this->handleAlert($name, $deviceName, $minMessage);
            $updated = 1;
        }

        if ($nowVal > $maxVal) {
            $this->handleAlert($name, $deviceName, $maxMessage);
            $updated = 1;
        }

        return $updated;
    }

    private function handleAlert($name, $deviceName, $message)
    {
        $this->sendNotif($name, $message, $deviceName);
        $this->conditionUpdate($deviceName, $message);
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
