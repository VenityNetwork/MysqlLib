<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;

class RawInsertQuery extends Query{

    public function execute(MysqlConnection $conn, array $params): mixed{
        $result = $conn->insert($params[0], $params[1], ...$params[2]);
        return [$result->getAffectedRows(), $result->getInsertId()];
    }
}