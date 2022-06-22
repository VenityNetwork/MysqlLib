<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\snooze\SleeperNotifier;
use VenityNetwork\MysqlLib\query\RawChangeQuery;
use VenityNetwork\MysqlLib\query\RawGenericQuery;
use VenityNetwork\MysqlLib\query\RawInsertQuery;
use VenityNetwork\MysqlLib\query\RawSelectQuery;
use function igbinary_unserialize;
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


    public static function init(PluginBase $plugin, MysqlCredentials $credentials, int $threads = 2, bool $ignoreError = false): MysqlLib{
        return new self($plugin, $credentials, $threads, $ignoreError);
    }
    /** @var MysqlThread[] */
    private array $thread = [];
    private array $threadTasksCount = [];
    private array $onSuccess = [];
    private array $onFail = [];
    private int $nextId = 0;
    private int $previousThread = -1;

    private function __construct(private PluginBase $plugin, MysqlCredentials $credentials, int $threads, private bool $ignoreError) {
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
        $this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void {
            $this->triggerGarbageCollector();
        }), 20 * 1800);
    }

    public function triggerGarbageCollector() {
        foreach($this->thread as $t) {
            $t->triggerGarbageCollector();
        }
    }

    public function waitAll() {
        foreach($this->thread as $k => $thread) {
            while(($this->threadTasksCount[$k]) > 0) {
                $this->handleResponse($k);
                usleep(1000);
            }
        }
    }

    public function close() {
        $this->waitAll();
        foreach($this->thread as $thread){
            $thread->close();
            Server::getInstance()->getTickSleeper()->removeNotifier($thread->getSleeperNotifier());
        }
    }

    private function checkVersion() {
        $this->rawSelect("SELECT VERSION() as v", [], static function(array $rows) {
            Server::getInstance()->getLogger()->notice("DB Version = " . $rows[0]["v"]);
        }, static function(string $error) {
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
        while(($response = $this->thread[$thread]->fetchResponse()) !== null) {
            $this->threadTasksCount[$thread]--;
            $response = igbinary_unserialize($response);
            if($response instanceof MysqlResponse) {
                $id = $response->getId();
                try{
                    if($response->isError()) {
                        if(isset($this->onFail[$id])) {
                            if($this->ignoreError) {
                                $this->ignoreError(fn() => ($this->onFail[$id])($response->getErrorMessage()));
                            }else{
                                ($this->onFail[$id])($response->getErrorMessage());
                            }
                        }
                    } else {
                        if(isset($this->onSuccess[$id])) {
                            if($this->ignoreError) {
                                $this->ignoreError(fn() => ($this->onSuccess[$id])($response->getResult()));
                            }else{
                                ($this->onSuccess[$id])($response->getResult());
                            }
                        }
                    }
                }finally{
                    unset($this->onSuccess[$id]);
                    unset($this->onFail[$id]);
                }
            }
        }
    }

    /**
     * @param string $query
     * @param array $args
     * @param callable|null $onSuccess - function(mixed $result) : void {}
     * @param callable|null $onFail - function(string $errorMessage) : void {}
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
     * @param array $args
     * @param callable|null $onSuccess
     * @param callable|null $onFail
     * @return void
     */
    public function rawSelect(string $query, array $args = [], callable $onSuccess = null, callable $onFail = null) {
        $this->query(RawSelectQuery::class, [$query, $args], $onSuccess, $onFail);
    }

    public function rawSelectOne(string $query, array $args = [], callable $onSuccess = null, callable $onFail = null) {
	    if($onSuccess !== null) {
		    $onSuccess = static function(array $rows) use ($onSuccess) {
			    $onSuccess($rows[0] ?? null);
		    };
	    }
        $this->rawSelect($query, $args, $onSuccess, $onFail);
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
     * @param array $args
     * @param callable|null $onSuccess
     * @param callable|null $onFail
     * @return void
     */
    public function rawChange(string $query, array $args = [], callable $onSuccess = null, callable $onFail = null) {
        $this->query(RawChangeQuery::class, [$query, $args], $onSuccess, $onFail);
    }

    /**
     * @param string $query
     * @param array $args
     * @param callable|null $onSuccess - function(int $affected_rows, int $insert_id) : void {}
     * @param callable|null $onFail
     * @return void
     */
    public function rawInsert(string $query, array $args = [], callable $onSuccess = null, callable $onFail = null) {
	    if($onSuccess !== null) {
		    $onSuccess = static function(array $result) use ($onSuccess) {
			    $onSuccess($result[0], $result[1]);
		    };
	    }
        $this->query(RawInsertQuery::class, [$query, $args], $onSuccess, $onFail);
    }

    private function ignoreError(callable $cb) {
        try {
            $cb();
        }catch(\Throwable $t) {
            Server::getInstance()->getLogger()->logException($t);
        }
    }
}

MysqlLib::detectPackaged();