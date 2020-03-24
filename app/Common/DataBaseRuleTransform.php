<?php declare(strict_types=1);
namespace App\Common;
use App\Exception\DataBaseRuleTransformException;

class DataBaseRuleTransform
{
    private $databaseRule = [];
    private static $_instance;
    private $dbIndex = 'backgrouds';
    private $dbTemplate = 'template';
    private $dbParameter = 'parameter';
    private $dbLastDefault = 'last_default';
    private $dbSeparator = 'separator';
    private $dbParameterCheck = 'parameterCheck';

    public function __construct()
    {
        $this->databaseRule = config('database_rule.' . $this->dbIndex);
    }

    public static function getInstance():  \App\Common\DataBaseRuleTransform
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function getDBName($mode)
    {
        if (!isset($this->databaseRule[$mode])) {
            throw new \DataBaseRuleTransformException(DataBaseRuleTransformException::NO_RULE);
        }
        $args = func_get_args();
        array_shift($args);
        
        $numargs = count($args);
        $modeConfig = $this->databaseRule[$mode];
        $parameter = $modeConfig[$this->dbParameter];
        $separator = $modeConfig[$this->dbSeparator];
        $arameterCheck = $modeConfig[$this->dbParameterCheck];
        if (isset($modeConfig[$this->dbLastDefault])) {
            $parameter--;
        }

        if ($numargs != $parameter) throw new \DataBaseRuleTransformException(DataBaseRuleTransformException::PARAMETER_ERROR);

        $name = '';
        for ($i = 0; $i < $parameter; $i++) {
            $arg  = array_shift($args);
            if (!$arameterCheck[$i]($arg)) throw new \DataBaseRuleTransformException(DataBaseRuleTransformException::TYPE_ERROR);
            $name .= $arg . $separator;
        }

        if (isset($modeConfig[$this->dbLastDefault])) {
            $name .= $modeConfig[$this->dbLastDefault];
        } else {
            $name = substr($name, 0, -1);
        }

        if (empty($name)) throw new \DataBaseRuleTransformException(DataBaseRuleTransformException::EMPTY_DATA);

        return $name;
    }
}