<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\result;

class InsertResult extends Result{

    public function __construct(
        private readonly int $affected_rows,
        private readonly int $insert_id){
    }

    public function getAffectedRows(): int{
        return $this->affected_rows;
    }

    public function getInsertId(): int{
        return $this->insert_id;
    }
}