<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

class MysqlRequest{

    public function __construct(
        private int $id,
        private string $query,
        private array $params = []
    ) {
    }

    public function getId(): int{
        return $this->id;
    }

    public function getQuery(): string{
        return $this->query;
    }

    public function getParams(): array{
        return $this->params;
    }
}