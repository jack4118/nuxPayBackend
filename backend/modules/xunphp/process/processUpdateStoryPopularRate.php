<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$currentDate = date("Y-m-d H:i:s");
$db->where('status', 'active');
$db->where('expires_at', $currentDate, '<=');
$xun_story = $db->get('xun_story');

$liked_story = $db->get('xun_story_favourite');

//extract total supporters' data
foreach($xun_story as $story){
    $supporters[$story['id']] = $story['total_supporters'];
    $total_supporters += $story['total_supporters'];
}

//extract story likes data
foreach($liked_story as $story){
    $liked_id[] = $story['story_id'];
}
//count story's like
$story_like_count = array_count_values($liked_id);

//collect total like, and extract supporters and like count to another array
foreach($supporters as $key => $value){
    $story_info[$key]['supporters'] = $value;
    $story_info[$key]['like_count'] = $story_like_count[$key]; 
    $total_like_counts += $story_like_count[$key];
}
//performing calculation to get popular rate
foreach($story_info as $key=>$value){
    if($total_supporters === 0){
        $supporters_pct = 0;
    }else{
            $supporters_pct = bcdiv($value['supporters'], $total_supporters, 30);
    }
    if($total_like_counts == 0){
            $like_counts_pct = 0;
    }else{
            $like_counts_pct = bcdiv($value['like_count'], $total_like_counts, 30);
    }
    $supporters = bcmul($supporters_pct, 55, 30);
    $like_count = bcmul($like_counts_pct, 20, 30);
    $updateData[$key]['popular_rate'] = bcadd($supporters, $like_count, 30);
}

//update table
foreach($updateData as $key => $value){
    $db->where('id', $key);
    $db->update('xun_story', $value);
}

//update popular_id in xun_story
$db->rawQuery('set @new_rank := 0');
$db->rawQuery('UPDATE xun_story set popular_id  = @new_rank := @new_rank +1 where status = "active" order by popular_rate desc');

?>