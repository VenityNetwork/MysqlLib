<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use pmmp\thread\ThreadSafeArray;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\thread\log\AttachableThreadSafeLogger;
use pocketmine\thread\Thread;
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

    private const GC_CODE = "gc";

    private ThreadSafeArray $requests;
    private ThreadSafeArray $responses;
    private string $credentials;
    public bool $running = true;
    private bool $busy = false;

    public function __construct(protected AttachableThreadSafeLogger $logger, protected SleeperHandlerEntry $sleeperEntry, MysqlCredentials $credentials){
        $this->requests = new ThreadSafeArray();
        $this->responses = new ThreadSafeArray();
        $this->credentials = igbinary_serialize($credentials);
    }

    public function getLogger(): AttachableThreadSafeLogger{
        return $this->logger;
    }

    public function onRun(): void{
        ini_set("memory_limit", "256M");
        gc_enable();
        $notifier = $this->sleeperEntry->createNotifier();
        /** @var MysqlCredentials $cred */
        $cred = igbinary_unserialize($this->credentials);
        $connection = new MysqlConnection($cred->getHost(), $cred->getUser(), $cred->getPassword(), $cred->getDb(), $cred->getPort(), $this);
        while($this->running){
            if(!$this->checkConnection($connection)) {
                sleep(5);
                continue;
            }
            $this->busy = true;
            try{
                $this->processRequests($connection, $notifier);
            } catch(Throwable $t) {
                $this->logger->logException($t);
                $connection->close();
            }
            $this->busy = false;
            $this->synchronized(function() {
                $this->wait();
            });
        }
        $this->logger->info("MysqlThread closed.");
        $connection->close();
        $this->synchronized(function() {
            $this->running = false;
        });
    }

    private function checkConnection(MysqlConnection $connection): bool{
        try{
            $connection->checkConnection();
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

    private function processRequests(MysqlConnection $connection, SleeperNotifier $notifier): void{
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
                            $result = $query->execute($connection, $request->getParams());
                            if(is_array($result)){
                                $resultDebug = "(array) " . Utils::argsToString($result);
                            }else{
                                $resultDebug = "(" . gettype($result) . ")" . (is_bool($result) ? ($result ? "TRUE" : "FALSE") : $result);
                            }
                            $this->logger->debug("Query succeed in " . floor((microtime(true) - $start) * 1000) . "ms (query={$request->getQuery()},id={$request->getId()},params=$ar,result=`$resultDebug`)");

                            $this->sendResponse($notifier, $request->getId(), $result);
                            continue;
                        }
                        throw new MysqlException("Query must instanceof ".Query::class." got " . $query::class);
                    } catch(Throwable $t) {
                        $this->sendResponse($notifier, $request->getId(), null, true, $t->getMessage());
                        $this->logger->error("Query error (query={$queryClass},id={$request->getId()},params=$ar)");
                        $this->logger->logException($t);
                        // reconnect when error to avoid deadlock transaction
                        $connection->close();
                        return;
                    }
                }
                $this->sendResponse($notifier, $request->getId(), null, true, "Unknown query={$request->getQuery()}");
            }elseif($request === self::GC_CODE){
                gc_enable();
                gc_collect_cycles();
                gc_mem_caches();
            }
        }
    }

    public function sendRequest(MysqlRequest $request): void{
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

    private function sendResponse(SleeperNotifier $notifier, int $id, mixed $response, bool $error = false, string $errorMessage = ""): void{
        $this->synchronized(function() use ($notifier, $id, $response, $error, $errorMessage) : void {
            $this->responses[] = igbinary_serialize(new MysqlResponse($id, $response, $error, $errorMessage));
            $notifier->wakeupSleeper();
        });
    }

    public function getThreadName(): string{
        return "MysqlLib";
    }

    public function close(): void{
        if(!$this->running) {
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

    public function triggerGarbageCollector(): void{
        $this->synchronized(function() : void {
            $this->requests[] = igbinary_serialize(self::GC_CODE);
            $this->notifyOne();
        });
    }

    public function getSleeperEntry(): SleeperHandlerEntry{
        return $this->sleeperEntry;
    }

    public function isBusy(): bool{
        return $this->busy;
    }
}