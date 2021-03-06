<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;

class RawGenericQuery extends Query{

    public function execute(MysqlConnection $conn, array $params): mixed{
        $conn->generic($params[0]);
        return true;
    }
}