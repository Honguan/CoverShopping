<?php
// .env 讀取設定
function load_env($env_path)
{
    if (!file_exists($env_path)) return;
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = array_map('trim', explode('=', $line, 2));
        if (!getenv($name)) {
            putenv("{$name}={$value}");
        }
    }
}

// 資料庫連線設定範例
function create_connection()
{
    load_env(__DIR__ . '/.env');
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $port = getenv('DB_PORT');

    // 僅允許本地連線
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        die('只允許本地資料庫連線');
    }

    $link = mysqli_connect($host, $user, $pass, '', $port)
        or die("無法建立資料連接: " . mysqli_connect_error());
    mysqli_set_charset($link, "utf8");
    return $link;
}

function execute_sql($link, $database, $sql)
{
    mysqli_select_db($link, $database)
        or die("開啟資料庫失敗: " . mysqli_error($link));

    $result = mysqli_query($link, $sql);

    return $result;
}
