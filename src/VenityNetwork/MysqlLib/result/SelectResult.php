<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\result;

class SelectResult extends Result{

    public function __construct(private readonly array $rows) {
    }

    public function getRows(): array{
        return $this->rows;
    }

    public function getOneRow(): ?array{
        return $this->rows[0] ?? null;
    }

    public function toArray(): array{
        return $this->rows;
    }
}