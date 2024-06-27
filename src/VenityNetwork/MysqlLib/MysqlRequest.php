<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

class MysqlRequest{

    public function __construct(
        private readonly int    $id,
        private readonly string $query,
        private readonly array  $params = []
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