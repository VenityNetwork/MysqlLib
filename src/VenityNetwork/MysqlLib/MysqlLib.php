<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use pocketmine\Server;
use pocketmine\snooze\SleeperNotifier;
use VenityNetwork\MysqlLib\query\RawChangeQuery;
use VenityNetwork\MysqlLib\query\RawGenericQuery;
use VenityNetwork\MysqlLib\query\RawSelectQuery;
use function unserialize;
use function usleep;
use const PTHREADS_INHERIT_NONE;

class MysqlLib{

    /** @var bool */
    private static bool $packaged;

    public static function isPackaged() : bool{
        return self::$packaged;
    }

    public static function detectPackaged() : void{
        self::$packaged = __CLASS__ !== 'VenityNetwork\\MysqlLib\\MysqlLib';
    }


    public static function init(MysqlCredentials $credentials, int $threads = 1): MysqlLib{
        return new self($credentials, $threads);
    }
    /** @var MysqlThread[] */
    private array $thread = [];
    private array $threadTasksCount = [];
    private array $onSuccess = [];
    private array $onFail = [];
    private int $nextId = 0;
    private int $previousThread = -1;

    private function __construct(MysqlCredentials $credentials, int $threads) {
        for($i = 0; $i < $threads; $i++){
            $notifier = new SleeperNotifier();
            Server::getInstance()->getTickSleeper()->addNotifier($notifier, function() use ($i) {
                $this->handleResponse($i);
            });
            $t = new MysqlThread(Server::getInstance()->getLogger(), $notifier, $credentials);
            $t->start(PTHREADS_INHERIT_NONE);
            while(!$t->running) {
                usleep(1000);
            }
            Server::getInstance()->getLogger()->debug("Started MysqlThread (".($i+1) . "/" . $threads . ")");
            $this->thread[$i] = $t;
            $this->threadTasksCount[$i] = 0;
        }
        $this->checkVersion();
    }

    public function close() {
        foreach($this->thread as $thread){
            $thread->close();
        }
    }

    private function checkVersion() {
        $this->rawSelect("SELECT VERSION() as v", null, [], function(array $rows) {
            Server::getInstance()->getLogger()->notice("DB Version = " . $rows[0]["v"]);
        }, function(string $error) {
            Server::getInstance()->getLogger()->error("Mysql Error: " . $error);
        });
    }

    private function selectThread() : int {
        $thread = null;
        $currentTask = -1;
        foreach($this->threadTasksCount as $k => $v) {
            if($v >= $currentTask && $this->previousThread !== $k) {
                $thread = $k;
                $currentTask = $v;
            }
        }
        if($thread === null) {
            foreach($this->threadTasksCount as $k => $v) {
                if($v > $currentTask) {
                    $thread = $k;
                    $currentTask = $v;
                }
            }
        }
        $this->previousThread = $thread;
        return $thread;
    }

    private function handleResponse(int $thread) {
        $this->threadTasksCount[$thread]--;
        while(($response = $this->thread[$thread]->fetchResponse()) !== null) {
            $response = unserialize($response);
            if($response instanceof MysqlResponse) {
                $id = $response->getId();
                if($response->isError()) {
                    if(isset($this->onFail[$id])) {
                        ($this->onFail[$id])($response->getErrorMessage());
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
        $t = $this->selectThread();
        $this->thread[$t]->sendRequest(new MysqlRequest($this->nextId, $query, $args));
        $this->threadTasksCount[$t]++;
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
        $this->query(RawSelectQuery::class, [$query, $types, $args], $onSuccess, $onFail);
    }

    public function rawSelectOne(string $query, ?string $types = null, array $args = [], callable $onSuccess = null, callable $onFail = null) {
        $onSuccess = function(array $rows) use ($onSuccess) {
            $onSuccess($rows[0] ?? null);
        };
        $this->rawSelect($query, $types, $args, $onSuccess, $onFail);
    }

    /**
     * @param string $query
     * @param callable|null $onSuccess
     * @param callable|null $onFail
     * @return void
     */
    public function rawGeneric(string $query, callable $onSuccess = null, callable $onFail = null) {
        $this->query(RawGenericQuery::class, [$query], $onSuccess, $onFail);
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
        $this->query(RawChangeQuery::class, [$query, $types, $args], $onSuccess, $onFail);
    }
}

MysqlLib::detectPackaged();