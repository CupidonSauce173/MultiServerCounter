<?php

namespace luca28pet\MultiServerCounter;

use luca28pet\MultiServerCounter\PMQuery\PMQuery;
use luca28pet\MultiServerCounter\PMQuery\PMQueryException;
use pocketmine\scheduler\AsyncTask;
use JsonException;
use function explode;
use function json_decode;

class UpdatePlayersTask extends AsyncTask{

    private string $serversData;

    public function __construct(string $serversData){
        $this->serversData = $serversData;
    }

    /**
     * @throws JsonException
     */
    public function onRun() : void{
        $res = ['count' => 0, 'maxPlayers' => 0, 'errors' => []];
        $serversConfig = json_decode($this->serversData, true, 512, JSON_THROW_ON_ERROR);
        foreach($serversConfig as $serverConfigString){
            $serverData = explode(':', $serverConfigString);
            $ip = $serverData[0];
            $port = (int) $serverData[1];
            try{
                $qData = PMQuery::query($ip, $port);
            }catch(PMQueryException $e){
                $res['errors'][] = 'Failed to query '.$serverConfigString.': '.$e->getMessage();
                continue;
            }
            $res['count'] += $qData['Players'];
            $res['maxPlayers'] += $qData['MaxPlayers'];
        }
        $this->setResult($res);
    }

    public function onCompletion() : void{
        $res = $this->getResult();
        foreach($res['errors'] as $e){
            Main::getInstance()->getLogger()->warning($e);
        }
        Main::getInstance()->setCachedPlayers($res['count']);
        Main::getInstance()->setCachedMaxPlayers($res['maxPlayers']);
    }

}