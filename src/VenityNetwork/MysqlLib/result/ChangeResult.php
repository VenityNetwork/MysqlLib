<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\result;

class ChangeResult extends Result{

    public function __construct(
        private readonly int $affected_rows) {
    }

    public function getAffectedRows(): int{
        return $this->affected_rows;
    }
}