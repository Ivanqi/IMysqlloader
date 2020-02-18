<?php declare(strict_types=1);

namespace App\Common;

class InsertStatementExtension
{
    const normal = 'normal';
    const replace = 'replace';
    const update = 'update';

    private static $insertTemplate = [
        self::normal => 'INSERT IGNORE INTO %s (%s) VALUES %s',
        self::replace => 'REPLACE INTO %s (%s) VALUES %s',
        self::update => 'INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s'
    ];

    private static $singleInsertFunc = [
        self::normal => 'makeInsertIgoreSql',
        self::replace => 'makeReplaceIntoSql',
        self::update => 'makeDuplicateInsertSql'
    ];

    private static $multiInsertFunc = [
        self::normal => 'makeMultiInsertIgoreSql',
        self::replace => 'makeMultiReplaceIntoSql',
        self::update => 'makeMutilDuplicateInsertSql'
    ];
    private static $saveMode = '';

   
    public static function makeSingleInsertSql(array $data, string $table, string $mode = self::normal): string
    {
        if (!isset(self::$singleInsertFunc[$mode])) {
            throw new \Exception('访问不存在方法');
        }
        self::$saveMode = $mode;
        return call_user_func_array([__CLASS__, self::$singleInsertFunc[$mode]], [$data, $table]);
    }

    public static function makeMultiInsertSql(array $data, string $table, string $mode = self::normal): string
    {
        if (!isset(self::$multiInsertFunc[$mode])) {
            throw new \Exception('访问不存在方法');
        }
        self::$saveMode = $mode;
        return call_user_func_array([__CLASS__, self::$multiInsertFunc[$mode]], [$data, $table]);
    }

    private static function makeInsertIgoreSql(array $data, string $table): string
    {
        if (!is_array($data)) return '';

        $keyStr = '';
        $valStr = '';
        foreach ($data as $key => $val) {
            $keyStr .= "`{$key}`,";
            $valStr .= "'{$val}',";
        }

        $sql = sprintf(self::$insertTemplate[self::$saveMode], $table, trim($keyStr, ', '), '(' . trim($valStr, ', ') . ')' );
        unset($data);
        return $sql;
    }

    private static function makeReplaceIntoSql(array $data, string $table): string
    {
        if (!is_array($data)) return '';

        $keyStr = '';
        $valStr = '';
        foreach ($data as $key => $val) {
            $keyStr .= "`{$key}`,";
            $valStr .= "'{$val}',";
        }

        $sql = sprintf(self::$insertTemplate[self::$saveMode], $table, trim($keyStr, ', '), '(' . trim($valStr, ', ') . ')');
        unset($data);
        return $sql;
    }

    private static function makeDuplicateInsertSql(array $data, string $table): string
    {
        if (!is_array($data)) return '';

        $keyStr = '';
        $valStr = '';
        $updateStr = '';

        foreach ($data as $key => $val) {
            $keyStr .= "`{$key}`,";
            $valStr .= "'{$val}',";
            $updateStr .= "`$key` = '$val',";
        }
        $sql = sprintf(self::$insertTemplate[self::$saveMode], $table, trim($keyStr, ', '), '(' . trim($valStr, ', ') . ')', trim($updateStr, ","));
        unset($data);
        return $sql;
    }

    // 处理二维数组
    private static function makeMultiInsertIgoreSql(array $data, string $table)
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
    private static function makeMultiReplaceIntoSql(array $data, string $table)
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
    private static function makeMutilDuplicateInsertSql(array $data, string $table)
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