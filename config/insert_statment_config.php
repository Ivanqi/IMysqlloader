<?php
return [
    'insert_template' => [
        'normal' => 'INSERT IGNORE INTO %s (%s) VALUES %s',
        'replace' => 'REPLACE INTO %s (%s) VALUES %s',
        'update' => 'INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s'
    ],
    'single_insert_func' => [
        'normal' => 'makeInsertWithReplaceIntoSql',
        'replace' => 'makeInsertWithReplaceIntoSql',
        'update' => 'makeDuplicateInsertSql'
    ],
    'multi_insert_func' => [
        'normal' => 'makeMultiInsertIgoreSql',
        'replace' => 'makeMultiReplaceIntoSql',
        'update' => 'makeMutilDuplicateInsertSql'
    ]
];