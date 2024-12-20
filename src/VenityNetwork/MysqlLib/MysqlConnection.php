<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use mysqli;
use mysqli_result;
use pocketmine\thread\log\AttachableThreadSafeLogger;
use VenityNetwork\MysqlLib\result\ChangeResult;
use VenityNetwork\MysqlLib\result\InsertResult;
use VenityNetwork\MysqlLib\result\Result;
use VenityNetwork\MysqlLib\result\SelectResult;
use function mysqli_set_opt;

class MysqlConnection{

    const MODE_SELECT = 0;
    const MODE_INSERT = 1;
    const MODE_CHANGE = 2;
    const MODE_GENERIC = 3;

    private mysqli $mysqli;

    public function __construct(
        protected string $host,
        protected string $user,
        protected string $password,
        protected string $db,
        protected int $port,
        protected MysqlThread $thread) {
    }

    public function getLogger() : AttachableThreadSafeLogger{
        return $this->thread->getLogger();
    }

    public function connect(): void{
        $this->close();
        $this->mysqli = @new mysqli($this->host, $this->user, $this->password, $this->db, $this->port);
        if($this->mysqli->connect_error) {
            throw new MysqlException("Connection Error: {$this->mysqli->connect_error} [{$this->mysqli->connect_errno}]");
        }
    }

    private function tryPing(): bool{
        try{
            $result = $this->mysqli->query("SELECT 'ping' as v");
            if(is_bool($result)){
                return $result;
            }
            $row = $result->fetch_assoc();
            if($row === null || !isset($row["v"]) || $row["v"] !== "ping"){
                $result->close();
                return false;
            }
            $result->close();
            return true;
        }catch(\Throwable $t){
            return false;
        }
    }

    public function checkConnection(): void{
        if(!isset($this->mysqli) || !$this->tryPing()) {
            $this->connect();
        }
    }

    public function close(): void{
        if(isset($this->mysqli)){
            @$this->mysqli->close();
            unset($this->mysqli);
        }
    }

    public function getMysqli(): mysqli{
        return $this->mysqli;
    }


    /**
     * @param string $query
     * @param int|string|float ...$args
     * @return InsertResult
     * @throws MysqlException
     */
    public function insert(string $query, ...$args): InsertResult{
        $ret = $this->query(self::MODE_INSERT, $query, ...$args);
        if(!$ret instanceof InsertResult) {
            throw new MysqlException("Expected InsertResult got " . serialize($ret));
        }
        return $ret;
    }

    /**
     * @param string $query
     * @param int|string|float ...$args
     * @return SelectResult
     * @throws MysqlException
     */
    public function select(string $query, ...$args): SelectResult{
        $ret = $this->query(self::MODE_SELECT, $query, ...$args);
        if(!$ret instanceof SelectResult) {
            throw new MysqlException("Expected SelectResult got " . serialize($ret));
        }
        return $ret;
    }

    /**
     * @param string $query
     * @param int|string|float ...$args
     * @return ChangeResult
     * @throws MysqlException
     */
    public function change(string $query, ...$args): ChangeResult{
        $ret = $this->query(self::MODE_CHANGE, $query, ...$args);
        if(!$ret instanceof ChangeResult) {
            throw new MysqlException("Expected ChangeResult got " . serialize($ret));
        }
        return $ret;
    }

    /**
     * @param string $query
     * @return Result
     * @throws MysqlException
     */
    public function generic(string $query): Result{
        $ret = $this->query(self::MODE_GENERIC, $query);
        if(!$ret instanceof Result) {
            throw new MysqlException("Expected Result got " . serialize($ret));
        }
        return $ret;
    }

    /**
     * @param int $mode
     * @param string $query
     * @param int|string|float ...$args
     * @return bool|ChangeResult|InsertResult|Result|SelectResult
     * @throws MysqlException
     */
    public function query(int $mode, string $query, ...$args) {
        $this->checkConnection();
        if(count($args) === 0) {
            $result = $this->mysqli->query($query);
            switch($mode) {
                case self::MODE_SELECT:
                    $ret = new SelectResult(Utils::mysqliResultToArray($result));
                    $result->close();
                    return $ret;
                case self::MODE_INSERT:
                case self::MODE_CHANGE:
                case self::MODE_GENERIC:
                    if($result instanceof mysqli_result) {
                        $result->close();
                    }
                    if($mode === self::MODE_INSERT) {
                        return new InsertResult($this->mysqli->affected_rows, $this->mysqli->insert_id);
                    }
                    if($mode === self::MODE_CHANGE) {
                        return new ChangeResult($this->mysqli->affected_rows);
                    }
                    return new Result();
            }
            $ar = Utils::argsToString($args);
            throw new MysqlException("Query Error: {$this->mysqli->error} [{$this->mysqli->errno}] (query=`{$query}`,args={$ar})");
        } else {
            $types = Utils::getTypesFromArray($args);
            $stmt = $this->mysqli->prepare($query);
            if($stmt === false) {
                $ar = Utils::argsToString($args);
                throw new MysqlException("Prepare Statement Error: {$this->mysqli->error} [{$this->mysqli->errno}] (query=`{$query}`,types={$types},args={$ar})");
            }
            if(!$stmt->bind_param($types, ...$args)) {
                $ar = Utils::argsToString($args);
                $err = new MysqlException("Prepare Statement bind_param Error: {$stmt->error} [{$stmt->errno}] (query=`{$query}`,types={$types},args={$ar})");
                $stmt->close();
                throw $err;
            }
            if(!$stmt->execute()) {
                $ar = Utils::argsToString($args);
                $err = new MysqlException("Prepare Statement execute Error: {$stmt->error} [{$stmt->errno}] (query=`{$query}`,types={$types},args={$ar})");
                $stmt->close();
                throw $err;
            }
            $result = $stmt->get_result();
            switch($mode) {
                case self::MODE_SELECT:
                    $ret = new SelectResult(Utils::mysqliResultToArray($result));
                    $stmt->close();
                    return $ret;
                case self::MODE_INSERT:
                    $ret = new InsertResult($stmt->affected_rows, $stmt->insert_id);
                    $stmt->close();
                    return $ret;
                case self::MODE_CHANGE:
                    $ret = new ChangeResult($stmt->affected_rows);
                    $stmt->close();
                    return $ret;
                case self::MODE_GENERIC:
                    $ret = new Result();
                    $stmt->close();
                    return $ret;
            }
            $stmt->close();
            return $result;
        }
    }
}