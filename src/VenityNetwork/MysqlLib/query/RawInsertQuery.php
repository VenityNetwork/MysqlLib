<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;
use VenityNetwork\MysqlLib\Utils;

class RawInsertQuery extends Query{

    public function execute(MysqlConnection $conn, array $params): mixed{
        $result = $conn->insert($params[0], Utils::getTypesFromArray($params[1]), ...$params[1]);
        return [$result->getAffectedRows(), $result->getInsertId()];
    }
}