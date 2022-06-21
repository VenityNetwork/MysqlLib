<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use ClassLoader;
use pocketmine\Server;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\thread\Thread;
use Threaded;
use Throwable;
use VenityNetwork\MysqlLib\query\Query;
use function class_exists;
use function floor;
use function gc_enable;
use function gettype;
use function ini_set;
use function is_array;
use function is_bool;
use function microtime;
use function igbinary_serialize;
use function igbinary_unserialize;
use function sleep;

class MysqlThread extends Thread{

    private Threaded $requests;
    private Threaded $responses;
    private MysqlConnection $connection;
    private string $credentials;
    public bool $running = true;

    public function __construct(protected \AttachableThreadedLogger $logger, protected SleeperNotifier $notifier, MysqlCredentials $credentials){
        $this->requests = new Threaded;
        $this->responses = new Threaded;
        $this->credentials = igbinary_serialize($credentials);

        if(!MysqlLib::isPackaged()){
            if(($virion = Server::getInstance()->getPluginManager()->getPlugin("DEVirion")) !== null){
                $cl = $virion->getVirionClassLoader();
                $this->setClassLoaders([Server::getInstance()->getLoader(), $cl]);
            }
        }
    }

    public function getLogger(): \AttachableThreadedLogger{
        return $this->logger;
    }

    public function isSafeRunning() : bool {
        return $this->synchronized(function() : bool {
            return $this->running;
        });
    }

    public function onRun(): void{
        ini_set("memory_limit", "256M");
        gc_enable();
        /** @var MysqlCredentials $cred */
        $cred = igbinary_unserialize($this->credentials);
        $this->connection = new MysqlConnection($cred->getHost(), $cred->getUser(), $cred->getPassword(), $cred->getDb(), $cred->getPort(), $this);
        while($this->isSafeRunning()){
            if(!$this->checkConnection()) {
                sleep(5);
                continue;
            }
            try{
                $this->processRequests();
            } catch(Throwable $t) {
                $this->logger->logException($t);
                $this->connection->close();
            }
            $this->synchronized(function() {
                $this->wait();
            });
        }
        $this->logger->info("MysqlThread closed.");
        $this->connection->close();
        $this->synchronized(function() {
            $this->running = false;
        });
    }

    private function checkConnection(): bool{
        try{
            $this->connection->checkConnection();
            return true;
        }catch(Throwable $e){
            $this->logger->logException($e);
        }
        return false;
    }

    private function readRequests() : ?string{
        return $this->synchronized(function() : ?string{
            return $this->requests->shift();
        });
    }

    private function processRequests() {
        while(($request = $this->readRequests()) !== null) {
            $request = igbinary_unserialize($request);
            if($request instanceof MysqlRequest) {
                $start = microtime(true);
                $ar = Utils::argsToString($request->getParams());
                $queryClass = $request->getQuery();
                if(class_exists($queryClass)){
                    try{
                        $query = new ($queryClass)();
                        if($query instanceof Query){
                            $result = $query->execute($this->connection, $request->getParams());
                            if(is_array($result)){
                                $resultDebug = "(array) " . Utils::argsToString($result);
                            }else{
                                $resultDebug = "(" . gettype($result) . ")" . (is_bool($result) ? ($result ? "TRUE" : "FALSE") : $result);
                            }
                            $this->logger->debug("Query succeed in " . floor((microtime(true) - $start) * 1000) . "ms (query={$request->getQuery()},id={$request->getId()},params=$ar,result=`$resultDebug`)");

                            $this->sendResponse($request->getId(), $result);
                            continue;
                        }
                        throw new MysqlException("Query must instanceof ".Query::class." got " . $query::class);
                    } catch(Throwable $t) {
                        $this->sendResponse($request->getId(), null, true, $t->getMessage());
                        $this->logger->error("Query error (query={$queryClass},id={$request->getId()},params=$ar)");
                        $this->logger->logException($t);
                        // reconnect when error to avoid deadlock transaction
                        $this->connection->close();
                        return;
                    }
                }
                $this->sendResponse($request->getId(), null, true, "Unknown query={$request->getQuery()}");
            }elseif($request === "gc"){
                gc_enable();
                gc_collect_cycles();
                gc_mem_caches();
            }
        }
    }

    public function sendRequest(MysqlRequest $request) {
        $this->synchronized(function() use ($request) {
            $this->requests[] = igbinary_serialize($request);
            $this->notifyOne();
        });
    }

    public function fetchResponse() : ?string {
        return $this->synchronized(function() : ?string {
            return $this->responses->shift();
        });
    }

    private function sendResponse(int $id, mixed $response, bool $error = false, string $errorMessage = "") {
        $this->synchronized(function() use ($id, $response, $error, $errorMessage) : void {
            $this->responses[] = igbinary_serialize(new MysqlResponse($id, $response, $error, $errorMessage));
            $this->notifier->wakeupSleeper();
        });
    }

    public function getThreadName(): string{
        return "MysqlLib";
    }

    public function close(){
        if(!$this->isSafeRunning()) {
            return;
        }
        $this->synchronized(function() {
            $this->running = false;
            $this->notify();
        });
        $this->quit();
    }

    public function quit(): void{
        $this->close();
        parent::quit();
    }

    public function triggerGarbageCollector(){
        $this->synchronized(function() : void {
            $this->requests[] = igbinary_serialize("gc");
            $this->notifyOne();
        });
    }

    public function getSleeperNotifier(): SleeperNotifier{
        return $this->notifier;
    }
}