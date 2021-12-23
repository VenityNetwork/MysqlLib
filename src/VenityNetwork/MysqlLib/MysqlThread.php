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

    public function onRun(): void{
        /** @var MysqlCredentials $cred */
        $cred = unserialize($this->credentials);
        $this->connection = new MysqlConnection($cred->getHost(), $cred->getUser(), $cred->getPassword(), $cred->getDb(), $cred->getPort());
        while(!$this->isKilled){
            $this->checkConnection();
            $this->processRequests();
            $this->wait();
        }
    }

    private function checkConnection() {
        while(true){
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
                $query = new ($request->getQuery())();
                if($query instanceof Query) {
                    try{
                        $result = $query->execute($this->connection, $request->getParams());
                        if(is_array($result)){
                            $resultDebug = "(array) " . Utils::argsToString($result);
                        } else {
                            $resultDebug = "(".gettype($result).")" . $result;
                        }
                        $this->logger->debug("Query succeed in " . floor((microtime(true) - $start) * 1000) . "ms (query={$request->getQuery()},id={$request->getId()},params=$ar,result=`$resultDebug`)");

                        $this->sendResponse($request->getId(), $result);
                        continue;
                    } catch(Throwable $t) {
                        $this->logger->error("Query error (query={$request->getQuery()},id={$request->getId()},params=$ar)");
                        $this->logger->logException($t);
                    }
                }
                $this->sendResponse($request->getId(), null, true);
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

    private function sendResponse(int $id, mixed $response, bool $error = false) {
        $this->responses[] = serialize(new MysqlResponse($id, $response, $error));
        $this->notifier->wakeupSleeper();
    }

    public function getThreadName(): string{
        return "MysqlLib";
    }
}