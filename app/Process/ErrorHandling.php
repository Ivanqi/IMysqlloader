<?php declare(strict_types=1);
namespace App\Process;

use Swoft\Log\Helper\CLog;
use Swoft\Process\Annotation\Mapping\Process;
use Swoft\Process\Contract\ProcessInterface;
use Swoole\Coroutine;
use Swoole\Process\Pool;
use App\ProcessRepositories\ErrorHandlingRepositories;

/**
 * Class ErrorHandling
 *
 * @since 2.0
 * @Process(workerId={2})
 */

class ErrorHandling implements ProcessInterface
{
     /**
     * @param Pool $pool
     * @param int  $workerId
     */
    public function run(Pool $pool, int $workerId): void
    { 
        $errorHandlingRepositories = ErrorHandlingRepositories::getInstance();
        while (true) {
            // if ($errorHandlingRepositories->check5minFailQueue()) {
            //     $errorHandlingRepositories->handle5minFailMessage();
            // }
            // $errorHandlingRepositories->handleFailMessage();
            Coroutine::sleep(0.1);
        }
    }
}