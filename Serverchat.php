<?php

class Server
{
    private $serv;
    private $conn = null;
    private static $fd = null;

    public function __construct()
    {
        $this->initDb();
        $this->serv = new swoole_websocket_server("127.0.0.1", 9502);
        $this->serv->set(array(
            'worker_num' => 8,
            'daemonize' => false,
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'debug_mode' => 1,
        ));

        $this->serv->on('Open', array($this, 'onOpen'));
        $this->serv->on('Message', array($this, 'onMessage'));
        $this->serv->on('Close', array($this, 'onClose'));

        $this->serv->start();

    }

    public function onOpen($server, $req)
    {
        echo "用户{$frame->data}来了\n";
        // $server->push($req->fd, json_encode(33));
    }

    public function onMessage($server, $frame)
    {
        echo "received 用户{$frame->fd} message: {$frame->data}\n";
        $fd = $frame->fd;
        $mod = $fd % 2;
        if ($mod == 1) {
            $td = $fd + 1;
        } else {
            $td = $fd - 1;
        }
        $server->push($td, $frame->data);

    }

    public function onClose($server, $fd)
    {
        $this->unBind($fd);
        echo "connection close: " . $fd;
    }

    /*******************/
    public function initDb()
    {
        $conn = mysqli_connect("127.0.0.1", "root", "");
        if (!$conn) {
            die('Could not connect: ' . mysql_error());
        } else {
            mysqli_select_db($conn, "test");
        }
        $this->conn = $conn;
    }

    public function add($fid, $tid, $content)
    {
        $sql = "insert into msg (fid,tid,content) values ($fid,$tid,'$content')";
        if ($this->conn->query($sql)) {
            $id = $this->conn->insert_id;
            $data = $this->loadHistory($fid, $tid, $id);
            return $data;
        }
    }

    public function bind($uid, $fd)
    {
        $sql = "insert into fd (uid,fd) values ($uid,$fd)";
        if ($this->conn->query($sql)) {
            return true;
        }
    }

    public function getFd($uid)
    {
        $sql = "select * from fd where uid=$uid limit 1";
        $row = "";
        if ($query = $this->conn->query($sql)) {
            $data = mysqli_fetch_assoc($query);
            $row = $data['fd'];
        }
        return $row;
    }

    public function unBind($fd, $uid = null)
    {
        if ($uid) {
            $sql = "delete from fd where uid=$uid";
        } else {
            $sql = "delete from fd where fd=$fd";
        }
        if ($this->conn->query($sql)) {
            return true;
        }
    }

    public function loadHistory($fid, $tid, $id = null)
    {
        $and = $id ? " and id=$id" : '';
        $sql = "select * from msg where ((fid=$fid and tid = $tid) or (tid=$fid and fid = $tid))" . $and;
        $data = [];
        if ($query = $this->conn->query($sql)) {
            while ($row = mysqli_fetch_assoc($query)) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

// 启动服务器
$server = new Server();
