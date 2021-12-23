<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use pocketmine\Server;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\utils\TextFormat;
use VenityNetwork\MysqlLib\query\CheckVersionQuery;
use function unserialize;
use function var_dump;
use const PTHREADS_INHERIT_NONE;

class MysqlLib{

    /** @var bool */
    private static $packaged;

    public static function isPackaged() : bool{
        return self::$packaged;
    }

    public static function detectPackaged() : void{
        self::$packaged = __CLASS__ !== 'VenityNetwork\\MysqlLib\\MysqlLib';
    }


    public static function init(MysqlCredentials $credentials): MysqlLib{
        return new self($credentials);
    }
    private MysqlThread $thread;
    private array $onSuccess = [];
    private array $onFail = [];
    private int $nextId = 0;

    private function __construct(MysqlCredentials $credentials) {
        $notifier = new SleeperNotifier();
        Server::getInstance()->getTickSleeper()->addNotifier($notifier, function() {
            $this->handleResponse();
        });
        $this->thread = new MysqlThread(Server::getInstance()->getLogger(), $notifier, $credentials);
        $this->thread->start(PTHREADS_INHERIT_NONE);
        $this->checkVersion();
    }

    private function checkVersion() {
        $this->query(CheckVersionQuery::class, [], function(string $version) {
            Server::getInstance()->getLogger()->info(TextFormat::GREEN . "Database Version = " . $version);
        }, function() {
            Server::getInstance()->getLogger()->error("Failed to check MYSQL version information");
        });
    }

    private function handleResponse() {
        while(($response = $this->thread->fetchResponse()) !== null) {
            $response = unserialize($response);
            if($response instanceof MysqlResponse) {
                $id = $response->getId();
                if($response->isError()) {
                    if(isset($this->onFail[$id])) {
                        ($this->onFail[$id])();
                    }
                } else {
                    if(isset($this->onSuccess[$id])) {
                        ($this->onSuccess[$id])($response->getResult());
                    }
                }
                unset($this->onSuccess[$id]);
                unset($this->onFail[$id]);
            }
        }
    }

    public function query(string $query, array $args = [], callable $onSuccess = null, callable $onFail = null) {
        $this->nextId++;
        if($onSuccess !== null) {
            $this->onSuccess[$this->nextId] = $onSuccess;
        }
        if($onFail !== null) {
            $this->onFail[$this->nextId] = $onFail;
        }
        $this->thread->sendRequest(new MysqlRequest($this->nextId, $query, $args));
    }
}

function nop() : void{

}

MysqlLib::detectPackaged();