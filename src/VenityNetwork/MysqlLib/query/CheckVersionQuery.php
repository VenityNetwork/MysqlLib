<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;

class CheckVersionQuery extends Query{

    public function execute(MysqlConnection $conn, array $params): mixed{
        return $conn->query('SELECT VERSION() as v')[0]["v"];
    }
}