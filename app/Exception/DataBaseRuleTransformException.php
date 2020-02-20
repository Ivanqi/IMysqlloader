<?php
namespace App\Exception;

class DataBaseRuleTransformException extends \Exception
{
    const NO_RULE = '不存在对应的规则';
    const PARAMETER_ERROR = '输入的参数和需要的不符，请重新检查';
    const TYPE_ERROR = '类型不符合，请重新检查';
    const EMPTY_DATA = '空数据';

    public function __construct(string $mssages = "", $code = 0,  Exception $previous = null)
    {
        parent::__construct($mssages, $code, $previous);
    }
}