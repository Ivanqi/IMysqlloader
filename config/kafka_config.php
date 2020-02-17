<?php
return [
    'kafka_consmer_addr' => env('KAFKA_CONSUMER_ADDR', 'localhost:9092'),
    'kafka_consumer_topic_prefix' => env('KAFKA_CONSUMER_TOPIC_PREFIX', 'icleaner'),
    'kafka_consumer_group' => env('KAFKA_CONSUMER_GROUP', 'IMysqlLoaderGroup'),
    'kafka_topic_rule' => '%s_%s_%s',
    'kafka_consumer_time' => 15000,
    'kafka_consumer_fail_job' => 'icleaner_%s_%s_consumer_fail_obj',
];