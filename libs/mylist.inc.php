<?php 
function mylist2videos($nn, $event) {
	$videos = array();

	foreach ( explode(',', $event['event_mylists']) as $mlid ) {
		$video = array();
		foreach ( mylist_j2a($nn, $mlid) as $mlitem ) {
//var_dump($mlitem); echo "\n";
			$item_data = $mlitem->item_data;
			$video['nicono'] = $item_data->video_id;
			$video['title'] = $item_data->title;
			$video['postdate'] = $item_data->first_retrieve;
			$video['time'] = $item_data->length_seconds;
			$video['view'] = $item_data->view_counter;
			$video['comme'] = $item_data->num_res;
			$video['mylist'] = $item_data->mylist_counter;
			$video['memo'] = $mlitem->description;

			$video['mylist_id'] = $mlid;

			$videos[$video['nicono']] = $video;
		}
	}

	return $videos;
}

function mylist_j2a($nn, $mlid) {
	$mylist = array();

	if ($mylist_src = $nn->getFile( 'http://www.nicovideo.jp/mylist/'.$mlid ) ) {
		if (preg_match("/Mylist\.preload\({$mlid}\,\s*?\[(\{.*?\})\]\);$/m", $mylist_src, $matches1)) {
			$mylist_src = $matches1[1];
			while ( preg_match('/(\{\"item_type\".*?)(,\{\"item_type\".*$|$)/m', $mylist_src, $matches2) ) {
				$mylist[] = json_decode($matches2[1]);
				$mylist_src = $matches2[2];
			}
		}
	}

	return $mylist;
}
?>
