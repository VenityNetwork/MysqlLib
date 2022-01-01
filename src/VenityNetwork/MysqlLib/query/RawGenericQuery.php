<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;

class RawGenericQuery extends Query{

    public function execute(MysqlConnection $conn, array $params): mixed{
        $conn->query(MysqlConnection::MODE_GENERIC, $params[0]);
        return true;
    }
}