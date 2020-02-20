<?php
namespace App\Exception;

class InsertStatementExtensionException extends \Exception
{
    const NO_EXISTS_METHOD = '访问不存在方法';

    public function __construct(string $mssages = "", $code = 0,  Exception $previous = null)
    {
        parent::__construct($mssages, $code, $previous);
    }
}