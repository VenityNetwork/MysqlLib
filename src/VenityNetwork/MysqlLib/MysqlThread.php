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
use function gettype;
use function is_array;
use function microtime;
use function serialize;
use function sleep;
use function unserialize;

class MysqlThread extends Thread{

    private Threaded $requests;
    private Threaded $responses;
    private MysqlConnection $connection;
    private string $credentials;
    public bool $running = true;

    public function __construct(protected \AttachableThreadedLogger $logger, protected SleeperNotifier $notifier, MysqlCredentials $credentials){
        $this->requests = new Threaded;
        $this->responses = new Threaded;
        $this->credentials = serialize($credentials);

        if(!MysqlLib::isPackaged()){
            /** @noinspection PhpUndefinedMethodInspection */
            /** @noinspection NullPointerExceptionInspection */
            /** @var ClassLoader $cl */
            $cl = Server::getInstance()->getPluginManager()->getPlugin("DEVirion")->getVirionClassLoader();
            $this->setClassLoaders([Server::getInstance()->getLoader(), $cl]);
        }
    }

    public function getLogger(): \AttachableThreadedLogger{
        return $this->logger;
    }

    public function onRun(): void{
        /** @var MysqlCredentials $cred */
        $cred = unserialize($this->credentials);
        $this->connection = new MysqlConnection($cred->getHost(), $cred->getUser(), $cred->getPassword(), $cred->getDb(), $cred->getPort(), $this);
        while($this->running){
            $this->checkConnection();
            try{
                $this->processRequests();
            } catch(Throwable $t) {
                $this->logger->logException($t);
                $this->connection->close();
            }
            $this->wait();
        }
        $this->logger->info("MysqlThread closed.");
        $this->connection->close();
    }

    private function checkConnection() {
        while($this->running){
            try{
                $this->connection->checkConnection();
                break;
            }catch(Throwable $e){
                $this->logger->logException($e);
                sleep(5);
            }
        }
    }

    private function processRequests() {
        while(($request = $this->requests->shift()) !== null) {
            $request = unserialize($request);
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
                                $resultDebug = "(" . gettype($result) . ")" . $result;
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
            }
        }
    }

    public function sendRequest(MysqlRequest $request) {
        $this->requests[] = serialize($request);
        $this->notify();
    }

    public function fetchResponse() : ?string {
        return $this->responses->shift();
    }

    private function sendResponse(int $id, mixed $response, bool $error = false, string $errorMessage = "") {
        $this->responses[] = serialize(new MysqlResponse($id, $response, $error, $errorMessage));
        $this->notifier->wakeupSleeper();
    }

    public function getThreadName(): string{
        return "MysqlLib";
    }

    public function close(){
        $this->running = false;
        $this->notify();
        $this->quit();
    }
}