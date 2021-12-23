<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use mysqli;
use mysqli_result;
use function implode;
use function sleep;

class MysqlConnection{

    private mysqli $mysqli;

    public function __construct(
        protected string $host,
        protected string $user,
        protected string $password,
        protected string $db,
        protected int $port) {
    }

    /**
     * @throws MysqlException
     */
    public function connect() {
        $this->close();
        $this->mysqli = new mysqli();
        if(!$this->mysqli->connect($this->host, $this->user, $this->password, $this->db, $this->port)) {
            throw new MysqlException("Connection Error: " . $this->mysqli->connect_error);
        }
    }

    /**
     * @throws MysqlException
     */
    public function checkConnection() {
        if(!isset($this->mysqli) || !$this->mysqli->ping()) {
            $this->connect();
        }
    }

    public function close() {
        if(isset($this->mysqli)){
            $this->mysqli->close();
        }
    }

    /**
     * @throws MysqlException
     */
    public function query(string $query, string $types = null, ...$args): bool|array{
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