<?php

declare(strict_types=1);

namespace VenityNetwork\MysqlLib;

use mysqli_result;
use function array_map;
use function explode;
use function gettype;
use function implode;
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

    public static function getTypesFromArray(array $param): string{
        $ret = "";
        foreach($param as $p) {
            $ret .= self::getType($p);
        }
        return $ret;
    }

    /**
     * @throws MysqlException
     */
    public static function getType($param): string{
        if(is_string($param)){
            return "s";
        }
        if(is_float($param)){
            return "d";
        }
        if(is_int($param)){
            return "i";
        }
        throw new MysqlException("Unsupported type: " . gettype($param));
    }

    public static function addBacktick(string $str): string{
        return implode(".", array_map(fn($c) => "`" . $c . "`", explode(".", $str)));
    }
}