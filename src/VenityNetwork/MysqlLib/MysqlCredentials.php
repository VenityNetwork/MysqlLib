<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

class MysqlCredentials{

    public function __construct(
        private string $host,
        private string $user,
        private string $password,
        private string $db,
        private int $port
    ) {
    }

    public function getHost(): string{
        return $this->host;
    }

    public function getUser(): string{
        return $this->user;
    }

    public function getPassword(): string{
        return $this->password;
    }

    public function getDb(): string{
        return $this->db;
    }

    public function getPort(): int{
        return $this->port;
    }

    public function __toString() {
        return "host={$this->host},user={$this->user},db={$this->db},port={$this->port}";
    }
}