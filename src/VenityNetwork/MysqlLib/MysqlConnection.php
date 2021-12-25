<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use mysqli;
use mysqli_result;

class MysqlConnection{

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

    public function query(string $query, ?string $types = null, ...$args): bool|array{
        $this->checkConnection();
        if($types === null) {
            $result = $this->mysqli->query($query);
            if($result instanceof mysqli_result) {
                return Utils::mysqliResultToArray($result);
            }
            if($result !== false) {
                return $result;
            }
            $ar = Utils::argsToString($args);
            throw new MysqlException("Query Error: {$this->mysqli->error} [{$this->mysqli->errno}] (query=`{$query}`,types={$types},args={$ar})");
        } else {
            $stmt = $this->mysqli->prepare($query);
            if($stmt === false) {
                $ar = Utils::argsToString($args);
                throw new MysqlException("Prepare Statement Error: {$this->mysqli->error} [{$this->mysqli->errno}] (query=`{$query}`,types={$types},args={$ar})");
            }
            if(!$stmt->bind_param($types, ...$args)) {
                $ar = Utils::argsToString($args);
                throw new MysqlException("Prepare Statement bind_param Error: {$this->mysqli->error} [{$this->mysqli->errno}] (query=`{$query}`,types={$types},args={$ar})");
            }
            if(!$stmt->execute()) {
                $ar = Utils::argsToString($args);
                throw new MysqlException("Prepare Statement execute Error: {$this->mysqli->error} [{$this->mysqli->errno}] (query=`{$query}`,types={$types},args={$ar})");
            }
            $result = $stmt->get_result();
            if($result instanceof mysqli_result) {
                return Utils::mysqliResultToArray($result);
            }
            return $result;
        }
    }
}