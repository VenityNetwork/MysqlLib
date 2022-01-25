<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;

class RawChangeQuery extends Query{

    public function execute(MysqlConnection $conn, array $params): mixed{
        return $conn->change($params[0], ...$params[1])->getAffectedRows();
    }
}