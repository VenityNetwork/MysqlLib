<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\query;

use VenityNetwork\MysqlLib\MysqlConnection;

abstract class Query{

    abstract public function execute(MysqlConnection $conn, array $params) : mixed;
}