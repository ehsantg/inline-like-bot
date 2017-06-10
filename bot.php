<?php
/*
Bot => @inlinelike_bot
Author => Ehsan Noureddini
Email => me@ehsann.info
*/
define('API_KEY', API_KEY);
define('WELCOME_MSG',"
این ربات به شما این اجازه را می دهد که بتوانید پست هایی با دکمه لایک زیر آن ، ایجاد نمایید.
➖➖➖➖➖➖➖➖➖➖
ددر بخش ارسال پیام که هستید ، تایپ کنید <code>@inlinelike_bot یه چیزی</code>را بنویسید و سپس روی <b>ارسال</b> کلیک کنید.
برای مثال همینجا بنویسید: <i>@inlinelike_bot من رو لایک کن </i>
");
function makeHTTPRequest($method,$datas=[]){
    $url = "https://api.telegram.org/bot".API_KEY."/".$method;
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($datas));
    $res = curl_exec($ch);
    if(curl_error($ch)){
        var_dump(curl_error($ch));
    }else{
        return json_decode($res);
    }
}
$data = file_get_contents("php://input");
$update = json_decode($data, true);
$dsn = 'mysql:dbname=DB_NAME;host=localhost;charset=utf8';
$user = DB_USER;
$password = DB_PASS;
$pdo = new PDO($dsn, $user, $password);
if($update['message']['text']=="/start"){
    $chat_id=$update['message']['from']['id'];

    makeHTTPRequest('sendMessage',[
        'chat_id'=>$update['message']['from']['id'],
        'text'=>WELCOME_MSG,
        'parse_mode'=>'HTML',
    ]);
}
if(isset($update['inline_query'])){
    $chat_id = $update['inline_query']['from']['id'];
    makeHTTPRequest('sendMessage',[
        'chat_id'=>"@testt12",
        'text'=>json_encode($update),
        'parse_mode'=>'HTML',
    ]);
    $inlineQueryID = $update['inline_query']['id'];
    makeHTTPRequest('answerInlineQuery',[
        'inline_query_id'=>$inlineQueryID,
        'results' => json_encode([[
            'type' => 'article',
            'id' => base64_encode(1),
            'title' => 'Send?',
            'input_message_content' => ['parse_mode' => 'HTML', 'message_text' => $update['inline_query']['query']],
            'reply_markup' => [
                'inline_keyboard'=>[
                    [
                        ['text'=> "❤",'callback_data'=>'like']
                    ]
                ]]
        ]])
    ]);
}

if(isset($update['callback_query']) && $update['callback_query']['data']=="like"){
    $alert = "شما این رو ❤ دارید :)";
    $callBackQueryID = $update['callback_query']['id'];
    $callBackQueryChatID = $update['callback_query']['message']['chat']['id'];
    if($callBackQueryChatID==""){ //inline callbackquery
        $callBackQueryChatID=$update['callback_query']['from']['id'];
    }
    $callBackQueryMessageID = $update['callback_query']['message']['message_id'];
    if($callBackQueryMessageID==""){
        $callBackQueryMessageID=$update['callback_query']['inline_message_id'];
    }

    $userID = $update['callback_query']['from']['id'];
    $firstName = $update['callback_query']['from']['first_name'];
    $lastName = $update['callback_query']['from']['last_name'];
    $userName = $update['callback_query']['from']['username'];


    // insert like
    $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
    $st = $pdo->prepare("INSERT INTO likes(`chat_id`,`query_id`,`message_id`,`user_id`) VALUES(:chat_id,:query_id,:message_id,:user_id)");
    $st->bindParam(":chat_id",$callBackQueryChatID);
    $st->bindParam(":query_id",$callBackQueryID);
    $st->bindParam(":message_id", $callBackQueryMessageID);
    $st->bindParam(":user_id", $userID);
    $exec = $st->execute();
    if(!$exec){
        $alert = "You already liked this post";
    }
    else{
        //select likes
        $st= $pdo->prepare("select count(*) as like_count from likes where message_id=:message_id");
//    $st->bindParam(":chat_id",$callBackQueryChatID);
        $st->bindParam(":message_id", $callBackQueryMessageID);
        $st->execute();
        $like_count = $st->fetch()['like_count']+1; // +1 for hearts
//    makeHTTPRequest('sendMessage',[
//        'chat_id'=>'@testt12',
//        'text'=>$like_count,
//    ]);
        if(intval($like_count)<10){
            $likes = str_repeat('❤',$like_count);
        }
        else{
            $likes = $like_count . ' ' . '❤';
        }
        makeHTTPRequest("editMessageReplyMarkup",[
//        'chat_id'=>$callBackQueryChatID,
//        'message_id'=>$callBackQueryMessageID,
            'inline_message_id'=>$callBackQueryMessageID,
            'reply_markup' => json_encode([
                'inline_keyboard'=>[
                    [
//                    ['text'=> $like_count . '  ❤','callback_data'=>'like']
                        ['text'=> $likes,'callback_data'=>'like']
                    ]
                ]]),
        ]);
    }
    makeHTTPRequest("answerCallbackQuery", [
        'callback_query_id' => $callBackQueryID,
        'text' => $alert,
//            'show_alert' => true,
    ] );

}



?>