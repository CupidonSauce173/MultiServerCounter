<?php

namespace luca28pet\MultiServerCounter;

use luca28pet\MultiServerCounter\PMQuery\PMQuery;
use pocketmine\event\Listener;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use JsonException;

use function class_exists;
use function count;
use function json_encode;

class Main extends PluginBase implements Listener{

    private int $cachedPlayers = 0;
    private int $cachedMaxPlayers = 0;

    private string $serverToQuery = '';

    static Main $instance;

    /**
     * @throws JsonException
     */
    public function onEnable() : void
    {
        if(!class_exists(PMQuery::class)){
            $this->getLogger()->error('PMQuery virion not found. Please use the phar from poggit.pmmp.io or use DEVirion');
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
        $this->saveDefaultConfig();
        $servers = (array)$this->getConfig()->get('servers-to-query');
        $this->serverToQuery = json_encode($servers, JSON_THROW_ON_ERROR);

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(){
            $this->getServer()->getAsyncPool()->submitTask(new UpdatePlayersTask($this->serverToQuery));
        }), (int)$this->getConfig()->get('update-players-interval') * 20);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    function onLoad(): void
    {
        self::$instance = $this;
    }

    static function getInstance(): self
    {
        return self::$instance;
    }

    public function getCachedPlayers() : int{
        return $this->cachedPlayers;
    }

    public function setCachedPlayers(int $cachedPlayers) : void{
        $this->cachedPlayers = $cachedPlayers;
    }

    public function getCachedMaxPlayers() : int{
        return $this->cachedMaxPlayers;
    }

    public function setCachedMaxPlayers(int $maxPlayers) : void{
        $this->cachedMaxPlayers = $maxPlayers;
    }

    public function queryRegenerate(QueryRegenerateEvent $event) : void{
        $event->getQueryInfo()->setPlayerCount($this->cachedPlayers + count($this->getServer()->getOnlinePlayers()));
        $event->getQueryInfo()->setMaxPlayerCount($this->cachedMaxPlayers + $this->getServer()->getMaxPlayers());
    }

}