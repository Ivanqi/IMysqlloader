<?php declare(strict_types=1);

namespace App\Process;

use Swoft\Log\Helper\CLog;
use Swoft\Process\Annotation\Mapping\Process;
use Swoft\Process\Contract\ProcessInterface;
use Swoole\Coroutine;
use Swoole\Process\Pool;
use App\ProcessRepositories\TopicsProcessRepositories;
/**
 * Class TopicsProcess
 *
 * @since 2.0
 * @Process(workerId={3})
 */
class TopicsProcess implements ProcessInterface
{
    private static $maxTimes;
    public function __construct()
    {
        self::$maxTimes = config('kafka_config.queue_max_times');
    }
    /**
     * @param Pool $pool
     * @param int  $workerId
     */
    public function run(Pool $pool, int $workerId): void
    { 
        $topicsProcessRepositories = TopicsProcessRepositories::getInstance();

        while (true) {
            for ($i = 0; $i < self::$maxTimes; $i++) {
                $topicsProcessRepositories->topicHandler();
            }
            
            Coroutine::sleep(0.1);
        }
    }
}