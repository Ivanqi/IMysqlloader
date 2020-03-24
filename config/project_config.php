<?php
return [
    'project_id' => env('PROJECT_ID', 0),
    'project_name' => env('PROJECT_NAME', 'project'),
    'project_type' => env('PROJECT_TYPE', 'admin,common'),
    'project_type_delimiter' => ',', 
    'db_pool_name' => env('DB_POOL_NAME', 'db.pool'),
    'data_max_chunk_limit' => env('DATA_MAX_CHUNK_LIMIT', 500)
];