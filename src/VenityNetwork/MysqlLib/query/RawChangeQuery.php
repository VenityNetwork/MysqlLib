<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;

class RawChangeQuery extends Query{

    public function execute(MysqlConnection $conn, array $params): mixed{
        return $conn->query(MysqlConnection::MODE_CHANGE, $params[0], $params[1], ...$params[2])->getAffectedRows();
    }
}