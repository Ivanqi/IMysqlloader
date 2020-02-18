<?php
return [
    'fail_queue_name' => '%s_log_fail_job',
    'commo_queue_name' => '%s_common_log_job',
    'next_fail_queue_name' => [
        5 => '%s_5min_log_fail_job',
        10 => '%s_10min_log_fail_job',
        15 => '%s_15min_log_fail_job',
    ],
    'queue_max_timeout' => 5
];