<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use pocketmine\Server;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\utils\TextFormat;
use VenityNetwork\MysqlLib\query\RawChangeQuery;
use VenityNetwork\MysqlLib\query\RawGenericQuery;
use VenityNetwork\MysqlLib\query\RawSelectQuery;
use function unserialize;
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

    public function close() {
        $this->thread->close();
    }

    private function checkVersion() {
        $this->rawSelect("SELECT VERSION() as v", null, [], function(array $rows) {
            Server::getInstance()->getLogger()->notice("DB Version = " . $rows[0]["v"]);
        }, function(string $error) {
            Server::getInstance()->getLogger()->error("Mysql Error: " . $error);
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

    /**
     * @param string $query
     * @param array $args
     * @param callable|null $onSuccess ~ function(mixed $result) : void {}
     * @param callable|null $onFail ~ function(string $errorMessage) : void {}
     * @return void
     */
    public function query(string $query, array $args = [], ?callable $onSuccess = null, ?callable $onFail = null) {
        $this->nextId++;
        if($onSuccess !== null) {
            $this->onSuccess[$this->nextId] = $onSuccess;
        }
        if($onFail !== null) {
            $this->onFail[$this->nextId] = $onFail;
        }
        $this->thread->sendRequest(new MysqlRequest($this->nextId, $query, $args));
    }

    /**
     * @param string $query
     * @param string|null $types
     * @param array $args
     * @param callable|null $onSuccess
     * @param callable|null $onFail
     * @return void
     */
    public function rawSelect(string $query, ?string $types = null, array $args = [], callable $onSuccess = null, callable $onFail = null) {
        $this->query(RawSelectQuery::class, ["query" => $query, "types" => $types, "args" => $args], $onSuccess, $onFail);
    }

    /**
     * @param string $query
     * @param callable|null $onSuccess
     * @param callable|null $onFail
     * @return void
     */
    public function rawGeneric(string $query, callable $onSuccess = null, callable $onFail = null) {
        $this->query(RawGenericQuery::class, ["query" => $query], $onSuccess, $onFail);
    }

    /**
     * @param string $query
     * @param string|null $types
     * @param array $args
     * @param callable|null $onSuccess
     * @param callable|null $onFail
     * @return void
     */
    public function rawChange(string $query, ?string $types = null, array $args = [], callable $onSuccess = null, callable $onFail = null) {
        $this->query(RawChangeQuery::class, ["query" => $query, "types" => $types, "args" => $args], $onSuccess, $onFail);
    }
}

function nop() : void{

}

MysqlLib::detectPackaged();