<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;

class RawChangeQuery extends Query{

    public function execute(MysqlConnection $conn, array $params): mixed{
        $conn->query($params["query"], $params["types"], ...$params["args"]);
        return $conn->getMysqli()->affected_rows;
    }
}