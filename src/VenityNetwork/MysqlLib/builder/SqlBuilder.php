<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib\builder;

use VenityNetwork\MysqlLib\Utils;
use function array_map;
use function implode;

class SqlBuilder{

    public function table(string $table) {

    }

    public function get(array $columns = null) {
        if($columns === null) {
            $select = "*";
        }else{
            $select = implode(", ", array_map(fn($c) => Utils::addBacktick($c), $columns));
        }
    }
}