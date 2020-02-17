<?php declare(strict_types=1);

namespace App\Common;

class InsertStatementExtension
{
    public static function makeInsertIgoreSql(array $data, string $table): string
    {
        if (!is_array($data)) return '';

        $keyStr = '';
        $valStr = '';
        $sqlTemplate = 'INSERT IGNORE INTO %s (%s) VALUES (%s)';
        foreach ($data as $key => $val) {
            $keyStr .= "`{$key}`,";
            $valStr .= "'{$val}',";
        }

        $sql = sprintf($sqlTemplate, $table, trim($keyStr, ', '), trim($valStr, ', '));
        unset($data);
        return $sql;
    }

    public static function makeReplaceIntoSql(array $data, string $table): string
    {
        if (!is_array($data)) return '';

        $keyStr = '';
        $valStr = '';
        $sqlTemplate = 'REPLACE INTO %s (%s) VALUES (%s)';
        foreach ($data as $key => $val) {
            $keyStr .= "`{$key}`,";
            $valStr .= "'{$val}',";
        }

        $sql = sprintf($sqlTemplate, $table, trim($keyStr, ', '), trim($valStr, ', '));
        unset($data);
        return $sql;
    }

    public static function makeDuplicateInsertSql(array $data, string $table): string
    {
        if (!is_array($data)) return '';

        $keyStr = '';
        $valStr = '';
        $updateStr = '';

        $sqlTemplate = 'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s';
        foreach ($data as $key => $val) {
            $keyStr .= "`{$key}`,";
            $valStr .= "'{$val}',";
            $updateStr .= "`$key` = '$val',";
        }
        $sql = sprintf($sqlTemplate, $table, trim($keyStr, ', '), trim($valStr, ', '), trim($updateStr, ","));
        unset($data);
        return $sql;
    }

    // 处理二维数组
    public static function makeMultiInsertIgoreSql(array $data, string $table)
    {
        if (!is_array($data)) return '';

        $firstIndex = 0;
        $keyStr = '';
        $valStr = '';
        $sqlTemplate = 'INSERT IGNORE INTO %s (%s) VALUES %s';

        $firstData = $data[$firstIndex];
        $key = array_keys($firstData);

        for ($i = 0; $i < count($key); $i++) {
            $keyStr .= "`{$key[$i]}`,";
        }

        foreach ($data as $k => $v) {
            $tmpValStr = '(';
            foreach ($v as $key => $val) {
                if (!isset($firstData[$key])) continue;
                $tmpValStr .= "'{$val}',";
            }
            $tmpValStr = substr($tmpValStr, 0, -1) . '),';
            $valStr .= $tmpValStr;            
        }

        $sql = sprintf($sqlTemplate, $table, trim($keyStr, ','), trim($valStr, ','));
        unset($data);
        unset($firstData);
        return $sql;
    }

    // 处理二维数组
    public static function makeMultiReplaceIntoSql(array $data, string $table)
    {
        if (!is_array($data)) return '';

        $firstIndex = 0;
        $keyStr = '';
        $valStr = '';
        $sqlTemplate = 'REPLACE INTO %s (%s) VALUES %s';

        $firstData = $data[$firstIndex];
        $key = array_keys($firstData);

        for ($i = 0; $i < count($key); $i++) {
            $keyStr .= "`{$key[$i]}`,";
        }

        foreach ($data as $k => $v) {
            $tmpValStr = '(';
            foreach ($v as $key => $val) {
                if (!isset($firstData[$key])) continue;
                $tmpValStr .= "'{$val}',";
            }
            $tmpValStr = substr($tmpValStr, 0, -1) . '),';
            $valStr .= $tmpValStr;            
        }

        $sql = sprintf($sqlTemplate, $table, trim($keyStr, ','), trim($valStr, ','));
        unset($data);
        unset($firstData);
        return $sql;
    }

    // 处理二维数组
    public static function makeMutilDuplicateInsertSql(array $data, string $table)
    {
        if (!is_array($data)) return '';

        $firstIndex = 0;
        $keyStr = '';
        $valStr = '';
        $updateStr = '';
        $sqlTemplate = 'INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s';

        $firstData = $data[$firstIndex];
        $key = array_keys($firstData);

        for ($i = 0; $i < count($key); $i++) {
            $keyStr .= "`{$key[$i]}`,";
            $updateStr .= "`{$key[$i]}` = values(`{$key[$i]}`),";
        }

        foreach ($data as $k => $v) {
            $tmpValStr = '(';
            foreach ($v as $key => $val) {
                if (!isset($firstData[$key])) continue;
                $tmpValStr .= "'{$val}',";
            }
            $tmpValStr = substr($tmpValStr, 0, -1) . '),';
            $valStr .= $tmpValStr;            
        }

        $sql = sprintf($sqlTemplate, $table, trim($keyStr, ','), trim($valStr, ','), trim($updateStr, ","));
        unset($data);
        unset($firstData);
        return $sql;
    }
}