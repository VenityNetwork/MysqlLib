<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

class MysqlResponse{

    public function __construct(
        private int $id,
        private array $result,
        private bool $error
    ){
    }

    public function getId(): int{
        return $this->id;
    }

    public function getResult(): array{
        return $this->result;
    }

    public function isError(): bool{
        return $this->error;
    }
}