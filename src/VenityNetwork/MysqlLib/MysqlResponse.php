<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

class MysqlResponse{

    public function __construct(
        private int $id,
        private mixed $result,
        private bool $error
    ){
    }

    public function getId(): int{
        return $this->id;
    }

    public function getResult(): mixed{
        return $this->result;
    }

    public function isError(): bool{
        return $this->error;
    }
}