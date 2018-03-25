<?php
/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);
ini_set('default_socket_timeout', -1);
use \GatewayWorker\Lib\Gateway;
use Workerman\Lib\Timer;
require_once 'mysql-master/src/Connection.php';
/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * 
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id) {
        // 向当前client_id发送数据 
        Gateway::sendToClient($client_id, "Hello $client_id\n");
        // 向所有人发送
        //Gateway::sendToAll("$client_id login\n");
    }
    public static $db = null;
    public static function onWorkerStart($worker){
        self::$db = new Workerman\MySQL\Connection('172.26.0.17', '3306', 'root', 'SYxhcd2018', 'xihuchongding','utf8');
    }
   /**
    * 当客户端发来消息时触发
    * @param int $client_id 连接id
    * @param mixed $message 具体消息
    */
   public static function onMessage($client_id, $message) {
    // 向所有人发送 
        $messInfo = json_decode($message,true);
         var_dump($messInfo);
        $group = 'userCurrentLink'.$messInfo['examTag'];
        $groupManage = 'manageCurrentLink'.$messInfo['examTag'];
        $groupSuccess = 'userReward'.$messInfo['examTag'];
        $groupTemp = 'userTemp'.$messInfo['examTag'];
        $redis = new Redis();
        $redis->pconnect('120.92.162.194','6379');
        $redis->auth('lfsl92i52s021_jtjs97x8_27');
        $redis->setOption(Redis::OPT_READ_TIMEOUT,100);
        switch ($messInfo['protocol']) {
            case '1001':
                $cTag = $messInfo['cTag'];
                $cStatus = $messInfo['cStatus'];
                $arr = array();
                    if($cTag == '101' || $cTag == '102'){
                        $account = $messInfo['account'];
                        if($account == '' || $account == null){
                            $arrR = array();
                            $arrR['protocol'] = 1002;
                            $arrR['cTag'] = $cTag;
                            $arrR['loginStatus'] = 'failure';
                            Gateway::sendToClient($client_id, json_encode($arrR));
                        }else{
                            $userInfo_json = $redis->get('user_'.$account);
                            $userInfo = json_decode($userInfo_json,true);
                            //$userInfo = self::$db->query("select * from xhcd_user where user_account='".$account."'");
                            $arr['protocol'] = 1002;
                            $arr['cTag'] = $cTag;
                            if(empty($userInfo)){
                                $arr['loginStatus'] = 'failure';
                            }else{
                                $arr['loginStatus'] = 'success';
                                //mysql：$examList = self::$db->query("select * from xhcd_companyexam where ce_online = 1");
                                $examList = $redis->hGetAll('companyExam');
                                if(empty($examList)){
                                    $arr['citemList'] = '';
                                }else{
                                    $_SESSION['uaccount'] = $userInfo['user_account'];
                                    $_SESSION['uopenid'] = $userInfo['user_openid'];
                                    $_SESSION['unickname'] = $userInfo['user_nickname'];
                                    $_SESSION['sex'] = $userInfo['user_sex'];
                                    $_SESSION['tag'] = $userInfo['user_tag'];
                                    $_SESSION['clientid'] = $client_id;
                                    //                                 Gateway::bindUid($client_id, $userInfo['user_id']);
                                    foreach ($examList as $k=>$v){
                                        $examCompany = $redis->hGetAll($k.'_company');
                                        $itemList[$k][] = $examCompany['ce_desc'];
                                        $itemList[$k][] = date("m月d日 H:i",$examCompany['ce_startdate']);
                                        $itemList[$k][] = $examCompany['ce_address'];
                                        $itemList[$k][] = $examCompany['ce_listimg'];
                                        $itemList[$k][] = $examCompany['ce_logo'];
                                    }
                                    $arr['citemList'] = json_encode($itemList);
                                }
                            }
                        }
                    }else if($cTag == '103'){
                        $code = $messInfo['code'];
                        if($code == '' || $code == null){
                            $arrR = array();
                            $arrR['protocol'] = 1002;
                            $arrR['cTag'] = $cTag;
                            $arrR['loginStatus'] = 'failure';
                            Gateway::sendToClient($client_id, json_encode($arrR));
                        }else{
                            //self::$db->query("insert into xhcd_user(user_nickname,user_account,user_sex,user_tag,user_regtime) values('nk_".$code."','".$code."',1,3,".time().")");
                            $userInfo_json = $redis->get('user_'.$code);
                            $userInfo = json_decode($userInfo_json,true);
                            //$userInfo = self::$db->query("select * from xhcd_user where user_account='".$code."'");
                            $arr['protocol'] = 1002;
                            $arr['cTag'] = $cTag;
                            if(empty($userInfo)){
                                $arr['loginStatus'] = 'failure';
                            }else{
                                $_SESSION['uaccount'] = $userInfo['user_account'];
                                $_SESSION['uopenid'] = $userInfo['user_openid'];
                                $_SESSION['unickname'] = $userInfo['user_nickname'];
                                $_SESSION['sex'] = $userInfo['user_sex'];
                                $_SESSION['tag'] = $userInfo['user_tag'];
                                $_SESSION['clientid'] = $client_id;
                                $onlineClients = Gateway::getClientSessionsByGroup($group);
                                $onlineClientsSuccess = Gateway::getClientSessionsByGroup($groupSuccess);
                                if (isset($onlineClients[$client_id]) && !empty($onlineClients[$client_id])){
                                    Gateway::leaveGroup($client_id, $group);
                                }
                                if (isset($onlineClientsSuccess[$client_id]) && !empty($onlineClientsSuccess[$client_id])){
                                    Gateway::leaveGroup($client_id, $groupSuccess);
                                }
                                $arr['loginStatus'] = 'success';
                                //mysq:$examList = self::$db->query("select * from xhcd_companyexam where ce_online = 1 and ce_isover = 0 ");
                                $examList = $redis->hGetAll('companyExam');
                                foreach ($examList as $k => $v){
                                    if ($v > 0){
                                        unset($examList[$k]);
                                    }
                                }
                                if(empty($examList)){
                                    $arr['citemList'] = '';
                                }else{
                                    //                                 Gateway::bindUid($client_id, $userInfo['user_id']);
                                    foreach ($examList as $k=>$v){
                                        $examCompany = $redis->hGetAll($k.'_company');
                                        $itemList[$k][] = $examCompany['ce_desc'];
                                        $itemList[$k][] = date("m月d日 H:i",$examCompany['ce_startdate']);
                                        $itemList[$k][] = $examCompany['ce_address'];
                                        $itemList[$k][] = $examCompany['ce_listimg'];
                                        $itemList[$k][] = $examCompany['ce_logo'];
                                    }
                                    $arr['citemList'] = json_encode($itemList);
                                }
                            }
                        }
                    }
                    Gateway::sendToClient($client_id, json_encode($arr));
                break;
            case '1003':
                if(!isset($_SESSION['uopenid'])){
                    $arr = array();
                    $arr['protocol'] = 1002;
                    $arr['cTag'] = $cTag;
                    $arr['loginStatus'] = 'failure';
                    Gateway::sendToClient($client_id, json_encode($arr));
                }else{
                    $cTag = $messInfo['cTag'];
                    $cStatus = $messInfo['cStatus'];
                    $examTag = $messInfo['examTag'];
                    $array = array();
                    $itemList = array();
                    /**mysql:
                    $examItemList = self::$db->query("select * from xhcd_exam as ex left join xhcd_item as ite on ex.exam_itemid = ite.item_id where ex.exam_tag='".$examTag."'");
                    $itemInfo = array();
                    foreach ($examItemList as $k=>$v){
                        $itemInfo['1'] = $v['item_content'];
                        $itemInfo['2'] = json_decode($v['item_option'],true)[0];
                        $itemInfo['3'] = json_decode($v['item_option'],true)[1];
                        $itemInfo['4'] = json_decode($v['item_option'],true)[2];
                        $itemInfo['5'] = json_decode($v['item_option'],true)[3];
                        $itemInfo['6'] = $v['item_answer'];
                        $itemList[$v['exam_itemsortid']] = $itemInfo;
                    }
                    */
                    $examList = $redis->hGetAll('examItem_'.$examTag);
                    foreach ($examList as $k => $v){
                        $examInfo = $redis->hGetAll('item_'.$v);
                        $itemInfo = array();
                            $itemInfo['1'] = $examInfo['item_content'];
                            $itemInfo['2'] = json_decode($examInfo['item_option'],true)[0];
                            $itemInfo['3'] = json_decode($examInfo['item_option'],true)[1];
                            $itemInfo['4'] = json_decode($examInfo['item_option'],true)[2];
                            $itemInfo['5'] = json_decode($examInfo['item_option'],true)[3];
                            $itemInfo['6'] = $examInfo['item_answer'];
                            $itemList[$k] = $itemInfo;
                    }
                    if($cTag == '101' && $cStatus=='login'){
                        Gateway::joinGroup($client_id, $groupManage);
                        $array['protocol'] = 1004;
                        $array['cTag'] = $cTag;
                        $array['citemList'] = json_encode($itemList);
                        $array['citemNum'] = count($examList);
                        $array['examTag'] = $examTag;
                        $companyDesc = self::$db->query("select company_des from xhcd_company where company_tag='".$examTag."'");
                        $array['companyDesc'] = $companyDesc[0]['company_des'];
                        Gateway::sendToClient($client_id, json_encode($array));
                    }else if($cTag == '102' && $cStatus=='login'){
                        Gateway::joinGroup($client_id, $groupManage);
                        $array['protocol'] = 1004;
                        $array['cTag'] = $cTag;
                        $array['citemList'] = json_encode($itemList);
                        $array['citemNum'] = count($examList);
                        $array['examTag'] = $examTag;
                        Gateway::sendToClient($client_id, json_encode($array));
                    }else if($cTag == '103' && $cStatus=='login'){
                        $array['protocol'] = 1004;
                        $array['cTag'] = $cTag;
                        //$examStatus = self::$db->query("select ce_isover from xhcd_companyexam where ce_tag='".$examTag."'");
                        $examStatus = $redis->hGet('companyExam',$examTag);
                        
                        //if($examStatus[0]['ce_isover'] == 0){
                        if($examStatus== 0){
                            Gateway::joinGroup($client_id, $group);
                            //Gateway::joinGroup($client_id, $groupSuccess);
                            $array['citemList'] = json_encode($itemList);
                            $array['citemNum'] = count($examList);
                            $array['examStatus'] = '1';
                        }else if($examStatus > 0){
                            $array['citemList'] = json_encode($itemList);
                            $array['citemNum'] = count($examList);
                            $array['examStatus'] = 0;
                        }
                        $array['examTag'] = $examTag;
                        Gateway::sendToClient($client_id, json_encode($array));
                    }
                }
                break;
            case '1005':
                    if(!isset($_SESSION['uopenid'])){
                        $arr = array();
                        $arr['protocol'] = 1002;
                        $arr['cTag'] = $cTag;
                        $arr['loginStatus'] = 'failure';
                        Gateway::sendToClient($client_id, json_encode($arr));
                    }else{
                        $cTag = $messInfo['cTag'];
                        $cStatus = $messInfo['cStatus'];
                        $examTag = $messInfo['examTag'];
                        $countDown = 15;
                        if($cTag == '101' && $cStatus == 'next'){
                             $onlineClients = Gateway::getClientSessionsByGroup($group);
                             $onlineClientsM = Gateway::getClientSessionsByGroup($groupManage); 
                             $arr['protocol'] = 1006;
                             $arr['cTag'] = 'all';
                             $arr['examTag'] = $examTag;
                             $arr['cStatus'] = 15;
                             foreach ($onlineClients as $k => $v){
                                 Gateway::sendToClient($k, json_encode($arr));
                             }
                             foreach ($onlineClientsM as $k => $v){
                                 Gateway::sendToClient($k, json_encode($arr));
                             }
                             $timer_itemid = Timer::add(15, function ()use(&$onlineClientsM,&$onlineClients,&$timer_itemid,&$countDown,&$examTag){
                                 $arr['protocol'] = 1006;
                                 $arr['cTag'] = 'all';
                                 $arr['examTag'] = $examTag;
                                 $countDown = $countDown- 15;
                                 if($countDown > 0 ){
                                    //$arr['cStatus'] = $countDown--;
                                     $arr['cStatus'] = $countDown;
                                 }else{
                                     $arr['cStatus'] = 0;
                                     Timer::del($timer_itemid);
                                 }
                                 foreach ($onlineClients as $k => $v){
                                    Gateway::sendToClient($k, json_encode($arr));
                                 }
                                 foreach ($onlineClientsM as $k => $v){
                                    Gateway::sendToClient($k, json_encode($arr));
                                 }
                             }); 
                        }
                    }
                    break;
            case '2001':
                if(!isset($_SESSION['uopenid'])){
                    $arr = array();
                    $arr['protocol'] = 1002;
                    $arr['cTag'] = $cTag;
                    $arr['loginStatus'] = 'failure';
                    Gateway::sendToClient($client_id, json_encode($arr));
                }else{
                    $cTag = $messInfo['cTag'];
                    $cStatus = $messInfo['cStatus'];
                    $examTag = $messInfo['examTag'];
                    $redis->set($examTag.'_now_a',0);
                    $redis->set($examTag.'_now_b',0);
                    $redis->set($examTag.'_now_c',0);
                    $redis->set($examTag.'_now_d',0);
                    if($cTag == '101' && $cStatus == 'start'){
                        //mysql:self::$db->query("update xhcd_companyexam set ce_isover = 1 where ce_tag='".$examTag."'");
                        //$redis->hGet('companyExam',$examTag);
                        $redis->hSet('companyExam',$examTag,1);
                        $array = array();
                        $arr = array();
                        $onlineClients = Gateway::getClientSessionsByGroup($group);
                        $onlineClientsM = Gateway::getClientSessionsByGroup($groupManage); 
                        $array['cStatus'] = 3;
                        $array['protocol'] = 2002;
                        $array['cTag'] = 'all';
                        $array['examTag'] = $examTag;
                        foreach ($onlineClients as $k => $v){
                            Gateway::sendToClient($k, json_encode($array));
                        }
                        foreach ($onlineClientsM as $k => $v){
                            Gateway::sendToClient($k, json_encode($array));
                        }
                        $timeVal = 3;
                        $timer_id = Timer::add(3, function ()use(&$onlineClientsM,&$onlineClients,&$addNum,&$timer_id,&$timeVal,&$examTag){
                            $timeVal = $timeVal- 3;
                            if($timeVal > 0 ){
                                //$array['cStatus'] = $timeVal--;
                                $array['cStatus'] = $timeVal;
                                $array['protocol'] = 2002;
                                $array['cTag'] = 'all';
                                $array['examTag'] = $examTag;
                            }else{
                                $array['protocol'] = 2002;
                                $array['cTag'] = 'all';
                                $array['examTag'] = $examTag;
                                $array['cStatus'] = 'start';
                                Timer::del($timer_id);
                            }
                            //$onlineClients = Gateway::getAllClientSessions();
                            foreach ($onlineClients as $k => $v){
                                Gateway::sendToClient($k, json_encode($array));
                            }
                             foreach ($onlineClientsM as $k => $v){
                                Gateway::sendToClient($k, json_encode($array));
                            } 
                        });
                    }
                }
              break;
            case '2005':
                if(!isset($_SESSION['uopenid'])){
                    $arr = array();
                    $arr['protocol'] = 1002;
                    $arr['cTag'] = $cTag;
                    $arr['loginStatus'] = 'failure';
                    Gateway::sendToClient($client_id, json_encode($arr));
                }else{
                    $cTag = $messInfo['cTag'];
                    $cStatus = $messInfo['cStatus'];
                    $examTag = $messInfo['examTag'];
                    if($cTag == '103' && $cStatus == 'answerQ'){
                        //$userAnswerStatus = self::$db->query("select * from xhcd_over where over_authid=".$_SESSION['uid']." and over_etag='".$examTag."'");
                        $userAnswerStatus = $redis->hGet('over_'.$examTag,$_SESSION['uopenid']);
                        if (!$userAnswerStatus){
                            $itemId = $messInfo['itemId'];
                            $itemAnswer = strtolower($messInfo['itemAnswer']);
                            $redis->incr($examTag.'_now_'.$itemAnswer);
                            //mysql:$itemRightAnswer = self::$db->query("select * from xhcd_item where item_id = (select exam_itemid from xhcd_exam where exam_tag='".$examTag."' and exam_itemsortid=". $itemId.")");
                            $itemInID = $redis->hGet('examItem_'.$examTag,$itemId);
                            $itemRightAnswer = $redis->hGetAll('item_'.$itemInID);
                            //mysql:$hasWriteAnswer = self::$db->query("select * from xhcd_answer where answer_uid=".$_SESSION['uid']." and answer_tag='".$examTag."' and answer_itemrank=".$itemId);
                            $hasWriteAnswer = $redis->hGet($examTag.'_answer_'.$_SESSION['uopenid'],$itemId);
                            $answerInfo = array();
                            if(empty($hasWriteAnswer)){
                                $answerInfo['answer_openid'] = $_SESSION['uopenid'];
                                $answerInfo['answer_tag'] = $examTag;
                                $answerInfo['answer_itemrank'] = $itemId;
                                $answerInfo['answer_option'] = $itemAnswer;
                                $answerInfo['answer_time'] = time();
                                $answerInfo['answer_isright'] = $itemRightAnswer['item_answer'];
                                $answer_itemrank = $itemId;
                                $answer_option = strtolower($messInfo['itemAnswer']);
                                $answer_time = time();
                                $answer_rightanswer = $itemRightAnswer['item_answer'];
                                if($itemRightAnswer['item_answer'] == $itemAnswer){
                                    $answerInfo['answer_isright'] = 1;
                                    Gateway::joinGroup($client_id, $groupSuccess);
                                    Gateway::joinGroup($client_id, $groupTemp);
                                }else{
                                    $answerInfo['answer_isright'] = 0;
                                    Gateway::leaveGroup($client_id, $groupSuccess);
                                    //mysql:$userAnswerStatus = self::$db->query("update xhcd_over set over_status = 1 where over_authid=".$_SESSION['uid']." and over_etag='".$examTag."'");
                                    $redis->hSet('over_'.$examTag,$_SESSION['uopenid'],1);
                                }
                                $_SESSION['currentItemAnswer'] = $answerInfo['answer_isright'];
                                $redis->hSet($examTag.'_answer_'.$_SESSION['uopenid'],$itemId,json_encode($answerInfo));
                                //self::$db->query("insert into xhcd_answer (answer_uid,answer_tag,answer_itemrank,answer_option,answer_time,answer_isright,answer_rightanswer) values (".$_SESSION['uid'].",'".$answer_tag."',".$answer_itemrank.",'".$answer_option."',".$answer_time.",".$answer_isright.",'".$answer_rightanswer."')");
                            }
                        }
                    }
                }
              break;
            case '2003':
                if(!isset($_SESSION['uopenid'])){
                    $arr = array();
                    $arr['protocol'] = 1002;
                    $arr['cTag'] = $cTag;
                    $arr['loginStatus'] = 'failure';
                    Gateway::sendToClient($client_id, json_encode($arr));
                }else{
                    $itemStatus = $messInfo['itemStatus'];
                    $cStatus = $messInfo['cStatus'];
                    $examTag = $messInfo['examTag'];
                    $itemId = $messInfo['itemId'];
                    $arr = array();
                    $arr102 = array();
                    $arr103 = array();
                    if($cStatus == 'closeitem' && $itemStatus == 'over'){
                        //mysql:$answerList = self::$db->query("select * from xhcd_answer where answer_tag='".$examTag."' and answer_itemrank=".$itemId);
                        //$answerList_json = $redis->hGet($examTag.'_answer_'.$_SESSION['uid'],$itemId);
                        //mysql:$answerThisItem = self::$db->query("select * from xhcd_item where item_id = (select exam_itemid from xhcd_exam where exam_tag='".$examTag."' and exam_itemsortid=". $itemId.")");
                        $itemInID = $redis->hGet('examItem_'.$examTag,$itemId);
                        $itemRightAnswer = $redis->hGetAll('item_'.$itemInID);
                        $onlineClients = Gateway::getClientSessionsByGroup($group);
                        $onlineClientsM = Gateway::getClientSessionsByGroup($groupManage);
                        $onlineClientsT = Gateway::getClientSessionsByGroup($groupTemp);
                        $onlineClientsS = Gateway::getClientSessionsByGroup($groupSuccess);
                        if($itemId == 1){
                            foreach ($onlineClients as $k => $v){
                                $arr103['cTag'] = '103';
                                $arr103['currentItem'] = $itemId;
                                $arr103['rightAnswer'] = $itemRightAnswer['item_answer'];
                                $arr103['protocol'] = 2004;
                                //mysql:$currentUserInfo = self::$db->query("select * from xhcd_answer where answer_uid=".$v['uid'] . " and answer_tag='".$examTag."' and answer_itemrank=".$itemId);
                                $currentUserInfo_json = $redis->hGet($examTag.'_answer_'.$v['uopenid'],$itemId);
                                $currentUserInfo = json_decode($currentUserInfo_json,true);
                                if($currentUserInfo['answer_isright'] == 1){
                                    $arr103['status'] = 'success';
                                }else{
                                    $arr103['status'] = 'failure';
                                    //$_SESSION['returnData'] = 'error';
                                    //mysql:self::$db->query("insert into xhcd_over (over_authid,over_etag,over_case,over_status) values(".$v['uid'].",'".$examTag."','error','1')");
                                    //$redis->hSet('over_'.$examTag,$v['uid'],1);
                                }
                                Gateway::sendToClient($k,json_encode($arr103));
                                unset($arr103);
                            }
                        }else{
                            foreach ($onlineClientsT as $k => $v){
                                $arr103['cTag'] = '103';
                                $arr103['currentItem'] = $itemId;
                                $arr103['rightAnswer'] = $itemRightAnswer['item_answer'];
                                $arr103['protocol'] = 2004;
                                //mysql:$currentUserInfo = self::$db->query("select * from xhcd_answer where answer_uid=".$v['uid'] . " and answer_tag='".$examTag."' and answer_itemrank=".$itemId);
                                $currentUserInfo_json = $redis->hGet($examTag.'_answer_'.$v['uopenid'],$itemId);
                                $currentUserInfo = json_decode($currentUserInfo_json,true);
                                if($currentUserInfo['answer_isright'] == 1){
                                    $arr103['status'] = 'success';
                                }else{
                                    $arr103['status'] = 'failure';
                                    //$_SESSION['returnData'] = 'error';
                                    //mysql:self::$db->query("insert into xhcd_over (over_authid,over_etag,over_case,over_status) values(".$v['uid'].",'".$examTag."','error','1')");
                                    //$redis->hSet('over_'.$examTag,$v['uid'],1);
                                }
                                Gateway::sendToClient($k,json_encode($arr103));
                                unset($arr103);
                            }
                            foreach ($onlineClientsT as $k=>$v){
                                if (!isset($onlineClientsS[$k]) || empty($onlineClientsS[$k])){
                                    Gateway::leaveGroup($k, $groupTemp);
                                }
                            }
                        }
                        $answerArr['a'] = $redis->get($examTag.'_now_a');
                        $answerArr['b'] = $redis->get($examTag.'_now_b');
                        $answerArr['c'] = $redis->get($examTag.'_now_c');
                        $answerArr['d'] = $redis->get($examTag.'_now_d');
                        foreach ($onlineClientsM as $k =>$v ){
                            if($v['tag'] == '1'){
                                //mysql:$itemNum = self::$db->query("select count(exam_id) as itemNum from xhcd_exam where exam_tag='".$examTag."'");
                                $examList = $redis->hGetAll('examItem_'.$examTag);
                                $itemNum = count($examList);
                                if($itemNum == $itemId){
                                    $arr['examStatus'] = $itemStatus;
                                }
                                $arr['cTag'] = '101';
                                $arr['currentItem'] = $itemId;
                                $arr['result'] = json_encode($answerArr);
                                $arr['rightAnswer'] = $itemRightAnswer['item_answer'];
                                $arr['protocol'] = 2004;
                                Gateway::sendToClient($k,json_encode($arr));
                                unset($arr);
                            }else if($v['tag'] == '2'){
                                $arr102['cTag'] = '102';
                                $arr102['currentItem'] = $itemId;
                                $arr102['result'] = json_encode($answerArr);
                                $arr102['rightAnswer'] = $itemRightAnswer['item_answer'];
                                $arr102['protocol'] = 2004;
                                Gateway::sendToClient($k,json_encode($arr102));
                                unset($arr102);
                            }
                        }
                    }
                }
                break;
            case '2007':
                if(!isset($_SESSION['uopenid'])){
                    $arr = array();
                    $arr['protocol'] = 1002;
                    $arr['cTag'] = $cTag;
                    $arr['loginStatus'] = 'failure';
                    Gateway::sendToClient($client_id, json_encode($arr));
                }else{
                    $cTag = $messInfo['cTag'];
                    $cStatus = $messInfo['cStatus'];
                    $examTag = $messInfo['examTag'];
                    $currentItem = $messInfo['currentItem'];
                    $arr = array();
                    $arr_count = array();
                    if($cTag == '101' && $cStatus == 'next'){
                        $redis->set($examTag.'_now_a',0);
                        $redis->set($examTag.'_now_b',0);
                        $redis->set($examTag.'_now_c',0);
                        $redis->set($examTag.'_now_d',0);
                        $arr['protocol'] = 2008;
                        $arr['cTag'] = $cTag;
                        $arr['cStatus'] = $examTag;
                        $arr['examTag'] = $examTag;
                        $arr['currentItem'] = $currentItem;
                        $arr['count'] = 'start';
                        /* $arr_count['protocol'] = 1006;
                        $arr_count['cTag'] = 'all';
                        $arr_count['examTag'] = $examTag;
                        $arr_count['cStatus'] = 'start'; */
                        $onlineClients = Gateway::getClientSessionsByGroup($groupSuccess);
                        $onlineClientsM = Gateway::getClientSessionsByGroup($groupManage);
                        //$onlineClients = Gateway::getAllClientSessions();
                        foreach ($onlineClients as $k => $v){
                            Gateway::sendToClient($k, json_encode($arr));
                        }
                        foreach ($onlineClientsM as $k => $v){
                            Gateway::sendToClient($k, json_encode($arr));
                        }
                        
                        $arr_count['cStatus'] = 15;
                        $arr_count['protocol'] = 1006;
                        $arr_count['cTag'] = 'all';
                        $arr_count['examTag'] = $examTag;
                        foreach ($onlineClients as $k => $v){
                            Gateway::sendToClient($k, json_encode($arr_count));
                        }
                        foreach ($onlineClientsM as $k => $v){
                            Gateway::sendToClient($k, json_encode($arr_count));
                        }
                         $countDown = 15;
                        $timer_itemid = Timer::add(15, function ()use(&$timer_itemid,&$countDown,&$examTag){
                            $countDown = $countDown- 15;
                            $arr_count['protocol'] = 1006;
                            $arr_count['cTag'] = 'all';
                            $arr_count['examTag'] = $examTag;
                            if($countDown > 0 ){
                                //$arr_count['cStatus'] = $countDown--;
                                $arr_count['cStatus'] = $countDown;
                            }else{
                                $arr_count['cStatus'] = 0;
                                Timer::del($timer_itemid);
                            }
                            $onlineClients = Gateway::getAllClientSessions();
                            foreach ($onlineClients as $k => $v){
                                Gateway::sendToClient($k, json_encode($arr_count));
                            }
                        }); 
                    }else if($cTag == '101' && $cStatus == 'now'){
                        $arr['protocol'] = 2008;
                        $arr['cTag'] = $cTag;
                        $arr['cStatus'] = $examTag;
                        $arr['examTag'] = $examTag;
                        $arr['currentItem'] = $currentItem;
                        $arr['count'] = 'start';
                        /* $arr_count['protocol'] = 1006;
                        $arr_count['cTag'] = 'all';
                        $arr_count['examTag'] = $examTag;
                        $arr_count['cStatus'] = 'start'; */
                        $onlineClients = Gateway::getAllClientSessions();
                        foreach ($onlineClients as $k => $v){
                            Gateway::sendToClient($k, json_encode($arr));
                            //Gateway::sendToClient($k, json_encode($arr_count));
                        }
                    }
                }
                break;
            case '2009':
                if(!isset($_SESSION['uopenid'])){
                    $arr = array();
                    $arr['protocol'] = 1002;
                    $arr['cTag'] = $cTag;
                    $arr['loginStatus'] = 'failure';
                    Gateway::sendToClient($client_id, json_encode($arr));
                }else{
                    $status = $messInfo['status'];
                    $examTag = $messInfo['examTag'];
                    $arr = array();
                    $arr1 = array();
                    if($status == 'finish'){
                        //mysql:self::$db->query("update xhcd_companyexam set ce_isover = 2 where ce_tag='".$examTag."'");
                        $redis->hSet('companyExam',$examTag,2);
                        //self::$db->query("update xhcd_over set over_status = 2 where over_authid=".$_SESSION['uid']." and over_etag='".$examTag."' and over_status = 0");
                        //$awardTotalNum = self::$db->query("select count(*) as awardNum  from xhcd_over where over_status = 2 and over_etag = '".$examTag."'");
                        $onlineClients = Gateway::getClientSessionsByGroup($group);
                        $onlineClientsM = Gateway::getClientSessionsByGroup($groupManage);
                        $onlineClientsSuccess = Gateway::getClientSessionsByGroup($groupSuccess);
                        foreach ($onlineClientsSuccess as $k => $v){
                            if (!empty($v)){
                                $arr['protocol'] = 2010;
                                if($v['tag'] == 3){
                                    $arr['cTag'] = '103';
                                    //$answerStatus = self::$db->query("select * from xhcd_over where over_authid=".$v['uid']." and over_etag = '".$examTag."'");
                                    $answerStatus = $redis->hGet('over_'.$examTag,$v['uopenid']);
                                    if($answerStatus > 0){
                                        $arr['awardStatus'] = 'failure';
                                    }else{
                                        $arr['awardStatus'] = 'success';
                                        //mysql:self::$db->query("insert into xhcd_checklog (checklog_uid,checklog_rtag) values (".$v['uid'].",'".$examTag."')");
                                        $redis->hSet('checklog_'.$examTag,$v['uopenid'],$examTag);
                                    }
                                    $arr['awardId'] = $examTag;
                                }
                                Gateway::sendToClient($k, json_encode($arr));
                                unset($arr);
                            }
                        }
                        //$rightPersonNum = self::$db->query("select count(checklog_id) as rightNum from xhcd_checklog where checklog_rtag = '".$examTag."'");
                        $rightPersonList = $redis->hGetAll('checklog_'.$examTag);
                        $rightPersonNum = count($rightPersonList);
                        foreach ($onlineClientsM as $k =>$v){
                            $arr1['protocol'] = 2010;
                            if($v['tag'] == 1){
                                $arr1['cTag'] = '101';
                                $arr1['awardNum'] = $rightPersonNum;
                            }else if($v['tag'] == 2){
                                $arr1['cTag'] = '102';
                                $arr1['awardNum'] = $rightPersonNum;
                            }
                            Gateway::sendToClient($k, json_encode($arr1));
                            unset($arr1);
                        }
                    }
                }
                break;
            default:
            break;
        }
   }
   
   /**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
   public static function onClose($client_id) {
       // 向所有人发送 
       GateWay::sendToAll("$client_id logout");
   }
}
