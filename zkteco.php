<?php

define("USHRT_MAX", 65535);
define("CMD_ACK_OK", 2000);        # Return value for order perform successfully
define("CMD_ACK_UNAUTH",2005);     # Connection unauthorized
define("CMD_PREPARE_DATA", 1500);  # Prepares to transmit the data
define("CMD_DATA", 1501);          # Transmit a data packet

define("CMD_CONNECT", 1000);       # Connections requests
define("CMD_AUTH", 1102);          # Connections authorizations
define("CMD_EXIT", 1001);          # Disconnection requests
define("CMD_UNLOCK", 31);            # unlock door
define("CMD_TESTVOICE",1017);      # Play voice

class zkteco{

    var $password;
    var $session_id;
    var $reply_id;
    var $isconnect;
    var $socket; 

    public function __construct($ip, $port, $password = 0, $timeout = 3){
        $this->password = $password;
        $this->isconnect=false;

        $address = "udp://".$ip.":".$port;
        $this->socket = stream_socket_client($address, $errno, $errstr,$timeout);
        stream_set_timeout($this->socket,$timeout); 
    }


    //连接到门禁设备
    public function connect(){
        $command = CMD_CONNECT;
        $command_string = '';
        $this->session_id = 0;
        $this->reply_id = -1 + USHRT_MAX;

        
        $result = $this->sendCommand($command, $command_string);


        //身份认证
        if($result["code"] == CMD_ACK_UNAUTH){
            $command_string = $this->makeCommkey($this->password, $this->session_id);
            $result = $this->sendCommand(CMD_AUTH, $command_string);
        }
;
        //连接成功
        if($result["status"]){
            $this->isconnect = true;
            return ["status"=>true,"text"=>"连接成功"];
        
        }else if($result["code"] == CMD_ACK_UNAUTH){
           
           return ["status"=>false,"text"=>"身份验证失败"];
        }

        //连接失败
        return ["status"=>false,"text"=>"连接失败"];
    }

    //关闭连接
    public function disconnect(){
        $command = CMD_EXIT;
        $command_string = '';
        
        $result = $this->sendCommand($command, $command_string);
        

        //关闭连接成功
        //if($result["status"]){
        //    $this->isconnect = false;
        //    return ["status"=>true,"text"=>"关闭成功"];
        //}

        //return ["status"=>false,"text"=>"关闭失败"];

        //无需验证服务器返回，强制关闭连接
        fclose($this->socket);
        return ["status"=>true,"text"=>"关闭成功"];
    }

    //播放测试音频
    public function  playVoice($index=0){
        $command = CMD_TESTVOICE;
        $command_string = pack("I", $index);

        $result = $this->sendCommand($command, $command_string);

        //播放测试音频成功
        if($result["status"])
            return ["status"=>true,"text"=>"播放成功"];
        return ["status"=>false,"text"=>"播放失败"];
    }

    //开门
    public function  unlock($time=3){
        $command = CMD_UNLOCK;
        $command_string = pack("I", intval($time)*10);

        $result = $this->sendCommand($command, $command_string);

        //开门成功
        if($result["status"])
            return ["status"=>true,"text"=>"开门成功"];
        return ["status"=>false,"text"=>"开门失败"];
    }

    // 计算校验和
    private function createChkSum($buf){

        $l = count($buf);
        $chksum = 0;

        while ($l > 1){
            
            $chksum += unpack('v*', pack("C*",$buf[0], $buf[1]))[1];
           
            $buf = array_slice($buf, 2);
            
            if ($chksum > USHRT_MAX)
                $chksum -= USHRT_MAX;
            
            $l -= 2;

        }

        if ($l)
            $chksum = $chksum + array_pop($buf);
            
        while ($chksum > USHRT_MAX)
            $chksum -= USHRT_MAX;
        
        $chksum = ~$chksum;
        
        while ($chksum < 0)
            $chksum += USHRT_MAX;
        
        return pack('v*', $chksum);
    }

    //加密通信密码
    private function makeCommkey($key, $session_id, $ticks=50){
        $key = intval($key);
        $session_id = intval($session_id);
        
        $k = 0;
        for ($i=0; $i<32; $i++){
            if ($key & (1 << $i)){
                $k = ($k << 1 | 1);
            }else{
                $k = $k << 1;
            }
        }

        $k += $session_id;
        $k = pack('I', $k);
        $k = unpack("C*",$k);

        $k = pack(
            "c*",
            $k[1] ^ ord('Z'),
            $k[2] ^ ord('K'),
            $k[3] ^ ord('S'),
            $k[4] ^ ord('O')
            );

        $k = unpack("v*",$k);
        $k = pack("v*", $k[2], $k[1]);
        
        $B = 0xff & $ticks;
        $k = unpack("c*", $k);
        $k = pack(
            "c*",
            $k[1] ^ $B,
            $k[2] ^ $B,
            $B,
            $k[4] ^ $B);

        return $k;
    }

    //打包数据包
    private function createHeader($command, $session_id, $reply_id, $command_string){

        $buf = pack('v*', $command, $chksum, $session_id, $reply_id).$command_string;
        
        $buf = unpack("C*",$buf);

        //unpack 产生的数组下标从1开始，
        //array_slice 利用截断数组重排下标从0开始
        $buf = array_slice($buf, 0);

        
        $chksum = unpack('v*', ($this->createChkSum($buf)))[1];

        $reply_id += 1;
        if ($reply_id >= USHRT_MAX)
            $reply_id -= USHRT_MAX;

        $buf = pack('v*', $command, $chksum, $session_id, $reply_id).$command_string;

        return $buf;
    }
    

    //发送命令
    private function sendCommand($command, $command_string){
    
        //没有连接到设备
        if (!in_array($command, [CMD_CONNECT, CMD_AUTH]) && !$this->isconnect)
            return ["status"=>false, "code"=>-1];


        $buf = $this->createHeader($command, $this->session_id, $this->reply_id, $command_string);
        
        //发送数据并取回结果        
        fwrite($this->socket, $buf);
        $result = fread($this->socket, 1024);
        
        $result = unpack("v*",$result);

        $status = $result[1];
        $this->session_id = $result[3];
        $this->reply_id = $result[4];
        
        if (in_array($status, [CMD_ACK_OK, CMD_PREPARE_DATA, CMD_DATA])){

            return ["status"=>true,"code"=>$status];
        
        }else{

            return ["status"=>false,"code"=>$status];
        
        }
    }
 

}
?>