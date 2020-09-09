<?php
/**
 * a auto call back demo
 * author: twogunss
 */
header("Content-type:text/html;charset=utf-8");
ini_set('display_errors', true);
error_reporting(E_ERROR);
set_time_limit(0);    //设置php永不超时
ini_set('memory_limit', '-1');

// need to put yourself
$encodingAesKey = "XXXXXXXXXX";
$token = "XXXXXXXXXXXXXX";
$appId = "XXXXXXXXXXXX";

// step1: check
$checkResult = checkSignature($token);
if ($checkResult) {
    if (!empty($_GET["echostr"])) {
        $nonce = $_GET["echostr"];
        print($nonce);
        return;
    }
} else {
    print("error");
    exit();
}

// auto reply arr
$auto_reply_event_arr = array(
    'subscribe' => "关注后回复", // event
);

$auto_reply_arr = array(
    'key1' => 'about key1',
    'key2' => 'about key2',
);

include_once "wxBizMsgCrypt.php";

$timeStamp = $_GET["timestamp"];
$nonce = $_GET["nonce"];
$msg_sign = $_GET['msg_signature'];

$encryptMsg = $GLOBALS["HTTP_RAW_POST_DATA"];
if (empty($encryptMsg)) {
    file_put_contents('./wx.log', 'post empty', FILE_APPEND);
    return;
}

$pc = new WXBizMsgCrypt($token, $encodingAesKey, $appId);

// ================>第三方收到公众号平台发送的消息
$xml_tree = new DOMDocument();
$xml_tree->loadXML($encryptMsg);
$array_e = $xml_tree->getElementsByTagName('Encrypt');
$encrypt = $array_e->item(0)->nodeValue;

$format = "<xml><ToUserName><![CDATA[toUser]]></ToUserName><Encrypt><![CDATA[%s]]></Encrypt></xml>";
$from_xml = sprintf($format, $encrypt);
$msg = '';
// get msg
$errCode = $pc->decryptMsg($msg_sign, $timeStamp, $nonce, $from_xml, $msg);
if ($errCode == 0) {
    sendBackToWX($msg);
    header("HTTP/1.0 200 OK");
} else {
    print($errCode . "\n");
    file_put_contents('./wx.log', $errCode, FILE_APPEND);
}

/**
 * check sign
 *
 * @param $token
 * @return bool
 */
function checkSignature($token)
{
    $signature = $_GET["signature"];
    $timestamp = $_GET["timestamp"];
    $nonce = $_GET["nonce"];

    $tmpArr = array($token, $timestamp, $nonce);
    sort($tmpArr, SORT_STRING);
    $tmpStr = implode($tmpArr);
    $tmpStr = sha1($tmpStr);

    if ($tmpStr == $signature) {
        return true;
    } else {
        return false;
    }
}

/**
 * back to wx
 * @param $msg
 */
function sendBackToWX($msg)
{
    // get msg format xml
    $xml_tree = new DOMDocument();
    $xml_tree->loadXML($msg);
    $array_m = $xml_tree->getElementsByTagName('MsgType');
    $msgType = $array_m->item(0)->nodeValue;

    // neet to change from => to , to => from
    $array_from = $xml_tree->getElementsByTagName('FromUserName');
    $to_user_name = $array_from->item(0)->nodeValue;
    $array_to = $xml_tree->getElementsByTagName('ToUserName');
    $from_user_name = $array_to->item(0)->nodeValue;
    global $auto_reply_arr;
    global $auto_reply_event_arr;

    if ($msgType == 'event') {
        // event
        $array_event = $xml_tree->getElementsByTagName('Event');
        $event = $array_event->item(0)->nodeValue;
        // it's only have subscribe , if you need other ,please set $auto_reply_arr
        if (array_key_exists($event, $auto_reply_event_arr)) {
            echo textMessage($from_user_name, $to_user_name, $auto_reply_event_arr[$event]);
        }
        return;
    }
    if ($msgType != 'text') {
        return;
    }
    // by key to reply
    $array_c = $xml_tree->getElementsByTagName('Content');
    $content = $array_c->item(0)->nodeValue;
    if (empty($content)) {
        return;
    }
    // text key
    foreach ($auto_reply_arr as $key => $value) {
        if (strpos($content, $key) !== false) {
            // like %key%
            echo textMessage($from_user_name, $to_user_name, $value);
            return;
        }
    }
}

/**
 * back to msg
 * @param $fromUserName
 * @param $toUserName
 * @param $message
 * @return string
 */
function textMessage($fromUserName, $toUserName, $message)
{
    $data = '<xml>
          <ToUserName><![CDATA[' . $toUserName . ']]></ToUserName>
          <FromUserName><![CDATA[' . $fromUserName . ']]></FromUserName>
          <CreateTime>' . time() . '</CreateTime>
          <MsgType><![CDATA[text]]></MsgType>
          <Content><![CDATA[' . $message . ']]></Content>
          <FuncFlag>0</FuncFlag>
        </xml>';
    return $data;
}
