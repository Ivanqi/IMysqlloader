<?php declare(strict_types=1);
namespace App\Process;

use Swoft\Log\Helper\CLog;
use Swoft\Process\Annotation\Mapping\Process;
use Swoft\Process\Contract\ProcessInterface;
use Swoole\Coroutine;
use Swoole\Process\Pool;
use Swoft\Redis\Redis;
use App\Common\DataBaseHandleFunc;

/**
 * Class ErrorHandling
 *
 * @since 2.0
 *
 * @Process(workerId={3})
 */

class ErrorHandling implements ProcessInterface
{
    private static $projectID;
    private static $projectName;
    private static $queueName;
    private static $queueNameBy5min;
    private static $commonFailQueueName;
    private static $appLog;
    private static $dbHandleFuncInstance;
    private static $failQueueTimer = 0;
    private static $failQueueTimerKeyBy5min;
    private static $timeStampBy5min = 300;
    public static $maxTimeout;

    public function __construct()
    {
        self::$projectID = (int) config('project_config.project_id');
        self::$projectName = config('project_config.project_name');
        self::$commonFailQueueName = config('fail_logjob.commo_queue_name');
        self::$queueNameBy5min = config('fail_logjob.5min_fail_queue_name');
        self::$appLog = config('app_log_' . self::$projectID);
        self::$dbHandleFuncInstance = DataBaseHandleFunc::getInstance(self::$projectName, self::$projectID);
        self::$failQueueTimerKeyBy5min = config('queue_max_timeout.5min_fail_queue_timer');
        self::$maxTimeout = config('fail_logjob.queue_max_timeout');
    }

     /**
     * @param Pool $pool
     * @param int  $workerId
     */
    public function run(Pool $pool, int $workerId): void
    { 
        while (true) {
            if (self::check5minFailQueue()) {
                self::handle5minFailMessage();
            }
            self::handleFailMessage();
            Coroutine::sleep(0.1);
        }
    }

    private static function check5minFailQueue(): bool
    {
        $now = time();
        if (!Redis::EXISTS(self::$failQueueTimerKeyBy5min)){
            self::setTimeStampBy5min($now);
            return false;
        }

        if (!self::$failQueueTimer) {
            self::$failQueueTimer = Redis::GET(self::$failQueueTimerKeyBy5min);
            if (!self::$failQueueTimer) {
                self::setTimeStampBy5min($now);
                return false;
            }
        }
        
        if ($now > self::$failQueueTimer) {
            self::setTimeStampBy5min($now);
            return true;
        } else {
            return false;
        }
    }

    private static function setTimeStampBy5min(int $now): void
    {
        Redis::SETNX(self::$failQueueTimerKeyBy5min, $now + self::$timeStampBy5min);
        self::$failQueueTimer = $now + self::$timeStampBy5min;
    }

    private static function commonHandleMessage(string $logData): void
    {
        $logDataJson = json_decode($logData, true);
        $firstKey = array_key_first($logDataJson);

        if (!isset(self::$appLog[$firstKey])) {
            self::commonFail(self::$queueNameBy5min, self::$commonFailQueueName, $logData);
            CLog::error(__CLASS__.": APP LOG 配置中不存在对应的Record: ". $firstKey);
        } else {
            $data = $logDataJson[$firstKey];
            if (!self::insertData($data, $firstKey, self::$appLog[$firstKey])) {
                self::commonFail(self::$queueNameBy5min, self::$commonFailQueueName, $logData);
            }
            unset($data);
        }
        unset($logDataJson);
    }

    private static function handleFailMessage(): void
    {
        $logData = Redis::BRPOPLPUSH(self::$queueName, self::$commonFailQueueName, self::$maxTimeout);
        if ($logData) {
            self::commonHandleMessage($logData);
            unset($logData);
        }
    }

    private static function handle5minFailMessage()
    {
        $len = Redis::LLEN(self::$queueNameBy5min);
        for ($i = 0; $i < $len; $i++) {
            $logData = Redis::BRPOPLPUSH(self::$queueNameBy5min, self::$commonFailQueueName, self::$maxTimeout);
            if ($logData) {
                self::commonHandleMessage($logData);
                unset($logData);
            }
        }
    }

    private static function insertData(array $payload, string $recordName, $recordConfig): bool
    {
        $backgrouds = $recordConfig['backgrouds'];
        $tableName = $recordConfig['table'];
        $saveMode = $recordConfig['save_mode'];
        $flag = true;
        try {
            foreach($backgrouds as $bg) {
                $ret = self::$dbHandleFuncInstance->insertData($bg, $saveMode, $tableName, $payload);
                if ($ret == false) $flag = false;
            }
            return $flag;
        } catch(\Exception $e) {
            CLog::error(__CLASS__ . ':' . $e->getMessage());
            return false;
        }
    }

    private static function commonFail(string $queueName1, strig $queueName2, string $data): void
    {
        Redis::PUSH($queueName1, $data);
        Redis::LREM($queueName2, $data);
    }
}