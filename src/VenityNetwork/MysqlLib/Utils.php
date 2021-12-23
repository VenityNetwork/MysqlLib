<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use mysqli_result;

class Utils{

    public static function argsToString(array $args): string{
        $ar = "";
        foreach($args as $k => $v) {
            $ar .= "$k:$v;";
        }
        return $ar;
    }

    public static function mysqliResultToArray(mysqli_result $result): array{
        $rows = [];
        while(($row = $result->fetch_assoc()) !== null) {
            $rows[] = $row;
        }
        return $rows;
    }
}