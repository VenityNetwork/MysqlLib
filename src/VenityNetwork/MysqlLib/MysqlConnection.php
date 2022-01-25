<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use mysqli;
use mysqli_result;
use VenityNetwork\MysqlLib\result\ChangeResult;
use VenityNetwork\MysqlLib\result\InsertResult;
use VenityNetwork\MysqlLib\result\Result;
use VenityNetwork\MysqlLib\result\SelectResult;

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

    public function getLogger() : \AttachableThreadedLogger{
        return $this->thread->getLogger();
    }

    public function connect() {
        $this->close();
        $this->mysqli = @new mysqli($this->host, $this->user, $this->password, $this->db, $this->port);
        if($this->mysqli->connect_error) {
            throw new MysqlException("Connection Error: {$this->mysqli->connect_error} [{$this->mysqli->connect_errno}]");
        }
    }

    public function checkConnection() {
        if(!isset($this->mysqli) || !$this->mysqli->ping()) {
            $this->connect();
        }
    }

    public function close() {
        if(isset($this->mysqli)){
            @$this->mysqli->close();
        }
    }

    public function getMysqli(): mysqli{
        return $this->mysqli;
    }


    /**
     * @return InsertResult
     */
    public function insert(string $query, ...$args) {
        return $this->query(self::MODE_INSERT, $query, ...$args);
    }

    /**
     * @return SelectResult
     */
    public function select(string $query, ...$args) {
        return $this->query(self::MODE_SELECT, $query, ...$args);
    }

    /**
     * @return ChangeResult
     */
    public function change(string $query, ...$args) {
        return $this->query(self::MODE_CHANGE, $query, ...$args);
    }

    /**
     * @return Result
     */
    public function generic(string $query) {
        return $this->query(self::MODE_GENERIC, $query);
    }

    /**
     * @param int $mode
     * @param string $query
     * @param ...$args
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
            throw new MysqlException("Query Error: {$this->mysqli->error} [{$this->mysqli->errno}] (query=`{$query}`,types={$types},args={$ar})");
        } else {
            $types = Utils::getTypesFromArray($args);
            $stmt = $this->mysqli->prepare($query);
            if($stmt === false) {
                $ar = Utils::argsToString($args);
                throw new MysqlException("Prepare Statement Error: {$this->mysqli->error} [{$this->mysqli->errno}] (query=`{$query}`,types={$types},args={$ar})");
            }
            if(!$stmt->bind_param($types, ...$args)) {
                $stmt->close();
                $ar = Utils::argsToString($args);
                throw new MysqlException("Prepare Statement bind_param Error: {$this->mysqli->error} [{$this->mysqli->errno}] (query=`{$query}`,types={$types},args={$ar})");
            }
            if(!$stmt->execute()) {
                $stmt->close();
                $ar = Utils::argsToString($args);
                throw new MysqlException("Prepare Statement execute Error: {$this->mysqli->error} [{$this->mysqli->errno}] (query=`{$query}`,types={$types},args={$ar})");
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