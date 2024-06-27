<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

class MysqlResponse{

    public function __construct(
        private readonly int   $id,
        private readonly mixed $result,
        private readonly bool  $error,
        private readonly string $errorMessage
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

    public function getErrorMessage(): string{
        return $this->errorMessage;
    }
}