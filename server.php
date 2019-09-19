<?php
$config = [
    'reactor_num' => 2,
    'worker_num' => 2,
    'backlog' => 256
];
if(!extension_loaded('swoole')) {
    echo 'swoole is not loaded.';
    echo PHP_EOL;
    exit;
}
$serv = new swoole_websocket_server("0.0.0.0", 9502);
$serv->set($config);
$counting = 0;
$serv->on('Open', function($server, $req) {
    echo "connection open: ".$req->fd;
    echo PHP_EOL;
});

$serv->on('Message', function($server, $frame) {
    global $counting;
    echo "message: ".$frame->data.', counting: ' . $counting;
    echo PHP_EOL;
    $counting++;
    $_fd = $frame->fd;
    $_data = json_decode($frame->data, true);
    if(json_last_error() === 0 && is_array($_data)) {
        if(isset($_data['fd']) && !empty($_data['fd']) && $_data['fd'] > 0) {
            # 推送到指定的 fd
            $_fd = $_data['fd'];
            if(!empty($_data['res'])) {
                $frame->res = $_data['res'];
            }
        } else {
            if(!empty($_data[0])) {
                # 执行 shell
                echo $_data[0] . PHP_EOL;
                $arr_allow = array();
                $arr_allow[] = 'svn up';
                $arr_allow[] = 'git';
                $arr_allow[] = 'uptime';
                $arr_allow[] = 'netstat';
                $can_next = TRUE;
                foreach($_data as $k=>$v) {
                    if(!is_string($v)) {
                        $can_next = FALSE;
                    }
                    if(stripos($v, '&&') !== FALSE) {
                        # 丢弃 &&
                        $can_next = FALSE;
                    }
                    if(stripos($v, ';') !== FALSE) {
                        # 丢弃 ;
                        $can_next = FALSE;
                    }
                }
                if($can_next && in_array($_data[0], $arr_allow)) {
                    if($_data[0] == 'cmd') {
                        array_shift($_data);
                    }
                    $_cmd = implode(' ', $_data);
                    echo 'run: ' . $_cmd . PHP_EOL;
                    $res = shell_exec($_cmd);
                    if(!is_array($res)) {
                        $res = array($res);
                    }
                    $frame->res = $res;
                }
            }
        }
    }
    echo 'push to: ' . $frame->fd . '->' . $_fd;
    echo PHP_EOL;
    $frame->counting = $counting;
    $server->push($_fd, json_encode($frame, JSON_UNESCAPED_UNICODE));
});

$serv->on('Close', function($server, $fd) {
    echo "connection close: ".$fd;
    echo PHP_EOL;
});

$serv->start();
