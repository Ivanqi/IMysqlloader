<?php declare(strict_types=1);

namespace App\Common;
use App\Exception\InsertStatementExtensionException;

class InsertStatementExtension
{
    const normal = 'normal';

    private $insertTemplate = [];
    private $singleInsertFunc = [];
    private $multiInsertFunc = [];
    private $saveMode = '';

    private static $_instance;

    public function __construct()
    {
        $this->insertTemplate = config('insert_statment_config.insert_template');
        $this->singleInsertFunc = config('insert_statment_config.single_insert_func');
        $this->multiInsertFunc = config('insert_statment_config.multi_insert_func');
    }

    public static function getInstance():  \App\Common\InsertStatementExtension
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

   
    public function makeSingleInsertSql(array $data, string $table, string $mode = self::normal): string
    {
        if (!isset($this->singleInsertFunc[$mode])) {
            throw new \InsertStatementExtensionException(InsertStatementExtensionException::NO_EXISTS_METHOD);
        }
        $this->saveMode = $mode;
        return call_user_func_array([__CLASS__, $this->singleInsertFunc[$mode]], [$data, $table]);
    }

    public function makeMultiInsertSql(array $data, string $table, string $mode = self::normal): string
    {
        if (!isset($this->multiInsertFunc[$mode])) {
            throw new \InsertStatementExtensionException(InsertStatementExtensionException::NO_EXISTS_METHOD);
        }
        $this->saveMode = $mode;
        return call_user_func_array([__CLASS__, $this->multiInsertFunc[$mode]], [$data, $table]);
    }

    private function makeInsertIgoreSql(array $data, string $table): string
    {
        if (!is_array($data)) return '';

        $keyStr = '';
        $valStr = '';
        foreach ($data as $key => $val) {
            $keyStr .= "`{$key}`,";
            $valStr .= "'{$val}',";
        }

        $sql = sprintf($this->insertTemplate[$this->saveMode], $table, trim($keyStr, ', '), '(' . trim($valStr, ', ') . ')' );
        unset($data);
        return $sql;
    }

    private function makeReplaceIntoSql(array $data, string $table): string
    {
        if (!is_array($data)) return '';

        $keyStr = '';
        $valStr = '';
        foreach ($data as $key => $val) {
            $keyStr .= "`{$key}`,";
            $valStr .= "'{$val}',";
        }

        $sql = sprintf($this->insertTemplate[$this->saveMode], $table, trim($keyStr, ', '), '(' . trim($valStr, ', ') . ')');
        unset($data);
        return $sql;
    }

    private function makeDuplicateInsertSql(array $data, string $table): string
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
        $sql = sprintf($this->insertTemplate[$this->saveMode], $table, trim($keyStr, ', '), '(' . trim($valStr, ', ') . ')', trim($updateStr, ","));
        unset($data);
        return $sql;
    }

    private function makeMultiInsertIgoreSql(array $data, string $table)
    {
        if (!is_array($data)) return '';

        $firstIndex = 0;
        $keyStr = '';
        $valStr = '';

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

        $sql = sprintf($this->insertTemplate[$this->saveMode], $table, trim($keyStr, ','), trim($valStr, ','));
        unset($data);
        unset($firstData);
        return $sql;
    }

    private function makeMultiReplaceIntoSql(array $data, string $table)
    {
        if (!is_array($data)) return '';

        $firstIndex = 0;
        $keyStr = '';
        $valStr = '';

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

        $sql = sprintf($this->insertTemplate[$this->saveMode], $table, trim($keyStr, ','), trim($valStr, ','));
        unset($data);
        unset($firstData);
        return $sql;
    }

    private function makeMutilDuplicateInsertSql(array $data, string $table)
    {
        if (!is_array($data)) return '';

        $firstIndex = 0;
        $keyStr = '';
        $valStr = '';
        $updateStr = '';

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

        $sql = sprintf($this->insertTemplate[$this->saveMode], $table, trim($keyStr, ','), trim($valStr, ','), trim($updateStr, ","));
        unset($data);
        unset($firstData);
        return $sql;
    }
}