<?php declare(strict_types=1);
namespace App\Process;

use Swoft\Log\Helper\CLog;
use Swoft\Process\Annotation\Mapping\Process;
use Swoft\Process\Contract\ProcessInterface;
use Swoole\Coroutine;
use Swoole\Process\Pool;
use Swoft\Redis\Redis;


/**
 * Class ErrorHandling
 *
 * @since 2.0
 *
 * @Process(workerId={3})
 */

class ErrorHandling implements ProcessInterface
{
    public function __construct()
    {

    }

     /**
     * @param Pool $pool
     * @param int  $workerId
     */
    public function run(Pool $pool, int $workerId): void
    { 
        while (true) {
            
            Coroutine::sleep(1);
        }
    }
}