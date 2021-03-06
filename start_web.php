<?php
use Workerman\Worker;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Connection\TcpConnection;

include __DIR__ . '/vendor/autoload.php';

$web = new Worker('http://0.0.0.0:2123');
$web->name = 'web';

const WEB_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'web';

$web->onMessage = function (TcpConnection $connection, Request $request) {
    $path = $request->path();
    if ($path === '/') {
        $connection->send(exec_php_file(WEB_PATH.'/index.html'));
        return;
    }
    $file = realpath(WEB_PATH. $path);
    if (false === $file) {
        $connection->send(new Response(404, array(), '<h3>404 Not Found</h3>'));
        return;
    }
    if (strpos($file, WEB_PATH) !== 0) {
        $connection->send(new Response(400));
        return;
    }
    if (\pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $connection->send(exec_php_file($file));
        return;
    }

    $if_modified_since = $request->header('if-modified-since');
    if (!empty($if_modified_since)) {
        $info = \stat($file);
        $modified_time = $info ? \date('D, d M Y H:i:s', $info['mtime']) . ' ' . \date_default_timezone_get() : '';
        if ($modified_time === $if_modified_since) {
            $connection->send(new Response(304));
            return;
        }
    }
    $connection->send((new Response())->withFile($file));
};

function exec_php_file($file) {
    \ob_start();
    // Try to include php file.
    try {
        include $file;
    } catch (\Exception $e) {
        echo $e;
    }
    return \ob_get_clean();
}

if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
