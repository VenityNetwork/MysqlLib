<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;
use VenityNetwork\MysqlLib\Utils;

class RawChangeQuery extends Query{

    public function execute(MysqlConnection $conn, array $params): mixed{
        return $conn->change($params[0], Utils::getTypesFromArray($params[1]), ...$params[1])->getAffectedRows();
    }
}