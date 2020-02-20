<?php
namespace App\Exception;

class DataBaseHandleFuncException extends \Exception
{
    const NO_RULE = '不存在对应的规则';
    const NO_METHOD = '不存在对应的方法';
    const DATA_FORMAT_EXCEPTION = '数据格式异常';
    const DATA_WRITE_FAILED = '数据写入失败';

    public function __construct(string $mssages = "", $code = 0,  Exception $previous = null)
    {
        parent::__construct($mssages, $code, $previous);
    }
}