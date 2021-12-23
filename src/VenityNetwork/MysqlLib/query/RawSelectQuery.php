<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;

class RawSelectQuery extends Query{

    public function execute(MysqlConnection $conn, array $params): mixed{
        return $conn->query($params["query"], $params["types"], ...$params["args"]);
    }
}