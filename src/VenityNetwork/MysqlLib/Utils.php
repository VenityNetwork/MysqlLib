<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use mysqli_result;
use function json_encode;

class Utils{

    public static function argsToString(array $args): string{
        return json_encode($args);
    }

    public static function mysqliResultToArray(mysqli_result $result): array{
        $rows = [];
        while(($row = $result->fetch_assoc()) !== null) {
            $rows[] = $row;
        }
        return $rows;
    }
}