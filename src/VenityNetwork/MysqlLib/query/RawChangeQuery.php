<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;

class RawChangeQuery extends Query{

    public function execute(MysqlConnection $conn, array $params): mixed{
        $conn->query($params[0], $params[1], ...$params[2]);
        return $conn->getMysqli()->affected_rows;
    }
}