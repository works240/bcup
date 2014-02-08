<?php 
// thumbinfo2video($nicono)
//  $nicono:動画ID
//  ニコ動APIから動画情報を取得し、配列にして返す。
//  テスト用に、$nowに設定したタイムスタンプより投稿日時が古いものは、除外することができる。
//
function thumbinfo2video($nicono) {
	global $nn;
	global $now;

	$video = array();
	$video['nicono'] = $nicono;

	$xml = $nn->loadXML("http://www.nicovideo.jp/api/getthumbinfo/{$nicono}");
//var_dump($xml);

	if ( $xml === false ) {
		printf("simplexml_load_file(%s): error\n", $nicono);
	} else {
		if ( isset($xml->thumb) ) {
			$info = $xml->thumb;
			$video['nicono'] = (string) $info->video_id;
			$video['title'] = (string) $info->title;
			$video['watch_url'] = (string) $info->watch_url;
			$video['postdate'] = date(DATEFORMAT, strtotime((string) $info->first_retrieve));
			$video['time'] = length2time( (string) $info->length );
			$video['length'] = (string) $info->length;
			$video['view'] = (int) $info->view_counter;
			$video['comme'] = (int) $info->comment_num;
			$video['mylist'] = (int) $info->mylist_counter;
			$video['userid'] = (string) $info->user_id;
			$video['niconame'] = getNicoName((string) $info->user_id);
			$video['descript'] = (string) $info->description;
			$video['thumbnail'] = getThumb($video['nicono'], (string) $info->thumbnail_url);
			$video['tag'] = tags2str($info->tags);
			$video['delete_at'] = 'NULL';
		} elseif ( isset($xml->error) ) {
			if ( $xml->error->code == 'DELETED' ) {
				$video['delete_at'] = date(DATEFORMAT);
			}
		}
	}
	
	return $video;
}	

// video2eventvideo($video, $event, $mylist_video, $admin_msg)
//  $video:thumbinfo2videoで取得した動画情報の連想配列
//  $event:イベント情報の連想配列
//  $mylist_video:マイリストから取得した動画情報
//  $admin_msg:eventvideoに設定されている管理者指示
//  eventvideoの更新項目を、配列にして返す。
//
function video2eventvideo($video, $event, $mylist_video, $y_event = array()) {
	global $db;
	global $now;

	$eventvideo = array();

	$eventvideo['eventcode'] = $event['eventcode'];
	$eventvideo['nicono'] = $video['nicono'];
	$videolist = $db->getVideolist($eventvideo['nicono'], $eventvideo['eventcode']);
	
	if ( $mylist_video['nicono'] == $eventvideo['nicono'] ) {
		$eventvideo['memo'] = ( $mylist_video['memo'] ) ? $mylist_video['memo'] : 'NULL';
	}
	$eventvideo['y_eventcode'] = $event['y_eventcode'];
	
	if ( !is_null($videolist[0]['view_e']) ) { $eventvideo['view_e'] = $videolist[0]['view_e']; }
	if ( !is_null($videolist[0]['comme_e']) ) { $eventvideo['comme_e'] = $videolist[0]['comme_e']; }
	if ( !is_null($videolist[0]['mylist_e']) ) { $eventvideo['mylist_e'] = $videolist[0]['mylist_e']; }
	if ( !is_null($videolist[0]['username']) ) { $eventvideo['username'] =  $videolist[0]['username']; }
	if ( !is_null($videolist[0]['theme']) ) { $eventvideo['theme'] =  $videolist[0]['theme']; }
	if ( !is_null($videolist[0]['y_nicono']) ) { $eventvideo['y_nicono'] =  $videolist[0]['y_nicono']; }
	if ( !is_null($videolist[0]['title']) ) { $eventvideo['title_e'] =  $videolist[0]['title']; }
	if ( !is_null($videolist[0]['admin_msg']) ) { $eventvideo['admin_msg'] =  $videolist[0]['admin_msg']; }
	if ( !is_null($videolist[0]['yosenp']) ) { $eventvideo['yosenp'] =  $videolist[0]['yosenp']; }
	if ( !is_null($videolist[0]['point']) ) { $eventvideo['point'] =  $videolist[0]['point']; }
	if ( !is_null($videolist[0]['ngmemo']) ) { $eventvideo['ngmemo'] =  $videolist[0]['ngmemo']; }
	
	if ( is_null($event['reg_vote_end']) || $now <= strtotime($event['reg_vote_end']) ) {
		if ( !$event['event_mylists'] ) {
			$eventvideo['view_e'] = $video['view'];
			$eventvideo['comme_e'] = $video['comme'];
			$eventvideo['mylist_e'] = $video['mylist'];
		}
	}
	if ( is_null($event['reg_event_end']) || $now <= strtotime($event['reg_event_end']) ) {
		if ($username = extractname( $video['descript'] )) {
			$eventvideo['username'] = $username;
		} else {
			$eventvideo['username'] = 'NULL';
		}
		if ($theme = extracttheme( $video['descript'] )) {
			$eventvideo['theme'] = $theme;
		} else {
			$eventvideo['theme'] = 'NULL';
		}
		if ($y_nicono = extractyosen( $video['descript'] )) {
			$eventvideo['y_nicono'] = $y_nicono;
		} else {
			$eventvideo['y_nicono'] = 'NULL';
		}
		$eventvideo['title_e'] = $video['title'];
	}

	if ( $eventvideo['admin_msg'] ) {
		if ( $admin_set_username = extractname( $eventvideo['admin_msg'] ) ) {
			$eventvideo['username'] = $admin_set_username;
		}
		if ( $admin_set_theme = extracttheme( $eventvideo['admin_msg'] ) ) {
			$eventvideo['theme'] = $admin_set_theme;
		}
		if ( $admin_set_y_nicono = extractyosen( $eventvideo['admin_msg'] ) ) {
			$eventvideo['y_nicono'] = $admin_set_y_nicono;
		}
	}

	$ng_array = array();
//	遅刻の判定(フライングは自動判定しない)
	if ( isset($event['reg_post_end']) && $video['postdate'] ) {
		if ( $video['postdate'] > $event['reg_post_end'] ) {
			$ng_array[] = 'pl';
		} else {
			$ng_array[] = 'pp';
		}
	}
//	再生時間不足の判定
	if ( isset($event['reg_time_min']) && $video['time'] ) {
		if ( $video['time'] < $event['reg_time_min'] ) {
			$ng_array[] = 'ts';
		} else {
			$ng_array[] = 'tt';
		}
	}
//	再生時間超過の判定
	if ( isset($event['reg_time_max']) && $video['time'] ) {
		if ( $video['time'] > $event['reg_time_max'] ) {
			$ng_array[] = 'tl';
		} else {
			$ng_array[] = 'tt';
		}
	}
//	タイトルの判定(イベント終了日時が設定されている場合、終了後は判定しない)
	if ( is_null($event['reg_event_end']) || $now <= strtotime($event['reg_event_end']) || $eventvideo['title_e']  ) {
		if ( isset($event['reg_title']) && $eventvideo['title_e'] ) {
			if ( chkTitle($eventvideo['title_e'], $event) === false ) {
				$ng_array[] = 'ti';
			} else {
				$ng_array[] = 'it';
			}
		}
	}
//	テーマの判定(イベント終了日時が設定されている場合、終了後は判定しない)
	if ( is_null($event['reg_event_end']) || $now <= strtotime($event['reg_event_end']) || $eventvideo['theme'] ) {
		if ( isset($event['reg_theme_must']) ) {
			if ( $theme_row = chkTheme($db, $eventvideo['theme'], $event) === false ) {
				$ng_array[] = 'th';
			} else {
				$ng_array[] = 'ht';
			}
		}
	}
//	予選動画の判定
	if ( $eventvideo['y_eventcode'] || $eventvideo['y_nicono'] != 'NULL' ) {
		if ( $eventvideo['y_eventcode'] && $eventvideo['y_nicono'] && $eventvideo['y_nicono'] != 'NULL' ) {
			if ( count(explode(' ',$eventvideo['y_nicono'])) > 1 ) {
	// 1つの本選動画に、複数の予選動画は指定できません。
				$ng_array[] = 'ym';
				$eventvideo['y_nicono'] = 'NULL';
				$eventvideo['yosenp'] = 'NULL';
			} elseif ( $yosen_vl = $db->getVideolist($eventvideo['y_nicono'], $eventvideo['y_eventcode']) ) {
				if ( $yosen_vl[0]['ngcode'] || $yosen_vl[0]['delete_at'] ) {
	// 指定された予選動画は、無効です。
					$ng_array[] = 'y8';
					$eventvideo['yosenp'] = 'NULL';
				} else {
					if ( $eventvideo['theme'] != $yosen_vl[0]['theme'] ) {
	// 予選ポイントを有効にするには、予選と本選のテーマは同じにして下さい。
						$ng_array[] = 'yh';
						$eventvideo['yosenp'] = 'NULL';
					}
					if ( !cmpTitle($eventvideo['title_e'], $yosen_vl[0]['title'], $event, $y_event) ) {
	// 予選ポイントを有効にするには、予選と本選のタイトルは同じにして下さい。
						$ng_array[] = 'yt';
						$eventvideo['yosenp'] = 'NULL';
					}
					if ( $videolist[0]['userid'] && $videolist[0]['userid'] != $yosen_vl[0]['userid'] ) {
	// 予選と本選の投稿者が一致しません。確認して下さい。
						$ng_array[] = 'yu';
						$eventvideo['yosenp'] = 'NULL';
					}
					if ( $eventvideo['yosenp'] != 'NULL' ) {
						$ng_array[] = 'yy';
					}
	// 予選動画が有効なら、予選ポイントを設定(上限100)
					if ( strpos($eventvideo['ngmemo'], 'yy') || in_array('yy', $ng_array) ) {
						$eventvideo['yosenp'] = ( $yosen_vl[0]['point'] > 100 ) ? 100 : $yosen_vl[0]['point'];
					}
				}
			} else {
	// 指定された予選動画は、登録されていません。
				$ng_array[] = 'y9';
				$eventvideo['yosenp'] = 'NULL';
			}
		} elseif ( $eventvideo['y_nicono'] && $eventvideo['y_nicono'] != 'NULL' ) {
	// このイベントに予選はありません。
//			$ng_array[] = 'y0';
//			$eventvideo['y_nicono'] = 'NULL';
//			$eventvideo['yosenp'] = 'NULL';
		} elseif ( !$eventvideo['y_nicono'] || $eventvideo['y_nicono'] == 'NULL' ) {
	// 予選の指定はありません。
			$eventvideo['y_nicono'] = 'NULL';
			$eventvideo['yosenp'] = 'NULL';
		}
	}
	
	if ( !empty($ng_array) ) {
		sort($ng_array);
		$eventvideo['auto_ng'] = implode(",", $ng_array);
	}

	if ( $eventvideo['admin_msg'] || is_null($event['reg_vote_end']) || $now <= strtotime($event['reg_vote_end'])) {
		if ( $event['reg_point_type'] == 'mylist' ) {
			$eventvideo['point'] = $eventvideo['mylist_e'];
		} elseif ( $event['reg_point_type'] == 'mylist+yosenp' ) {
			$eventvideo['point'] = $eventvideo['mylist_e'];
			if ( $eventvideo['yosenp'] ) {
				$eventvideo['point'] += $eventvideo['yosenp'];
			}
		}
	}

	return $eventvideo;
}	
	
// tags2str($tags)
//  $tags:動画のタグ(xmlを展開した配列)
//  タグの配列をスペース区切りの文字列にして返す
//
function tags2str($tags) {
	$tag_str = '';
	
	foreach ($tags->tag as $tag) {
		$tag_str .= $tag;
		if ( $tag->attributes()->lock == '1') {
			$tag_str .= "★";
		}
		$tag_str .= ' ';
	}
	
	return $tag_str;
}

// getNicoName($user_id)
//  $user_id:ニコニコ動画のユーザーID
//  ニコニコ動画のユーザー名を取得
//
function getNicoName($user_id) {
	global $nn;
	
	$noconame = '';
	
	if ($src = $nn->getFile( 'http://ext.nicovideo.jp/thumb_user/'.$user_id ) ) {
		if (preg_match("/<a href=\"http:\/\/www\.nicovideo\.jp\/user\/{$user_id}\" target=\"_blank\"><strong>(.*?)<\/strong><\/a>/iu", $src, $matches)) {
			$nicono = $matches[1];
		} else {
			$nicono = "!!未公開!!";
		}
    } else {
		$nicono = '';
	}

	return $nicono;
}

// getThumb($nicono, $thumbnail_url)
//  $nicono:動画ID
//  $thumbnail_url:サムネイルURL
//  動画のサムネイル画像を取得
//  既にファイルサイズ>0の画像ファイルが取得できている場合は、上書きしない
//
function getThumb($nicono, $thumbnail_url) {
	global $nn;

	$thumb_file = THUMB_DIR.DS."{$nicono}.jpg";
	if (file_exists($thumb_file) && filesize($thumb_file) > 0) {
		$thumb_name = "{$nicono}.jpg";
	} else {
		if ( $thumb = $nn->getFile( $thumbnail_url ) ) {
			if (file_put_contents($thumb_file, $thumb)) {
				$thumb_name = "{$nicono}.jpg";
			} else {
				$thumb_name = 'dummy.jpg';
			}
		} else {
			$thumb_name = '';
		}
	}
	
	return $thumb_name;
}


function chkTitle($title, $event) {
	$title_ok = false;

	if ( isset($event['reg_title']) ) {
		foreach ( explode(',', $event['reg_title']) as $regexp ) {
			if ( preg_match($regexp, $title) ) {
				$title_ok = true;
				break;
			}
		}
	} else {
		$title_ok = true;
	}

	return $title_ok;
}

function chkTheme($db, $theme, $event) {
	$theme_ok = true;
	$theme_array = array();

	if ( isset($theme) && $theme != '' ) {
		$theme_list = $db->getTheme( $event['eventcode'] );
		foreach ( $theme_list as $theme_row ) {
			if ( $theme_row['theme'] == $theme  ) {
				$theme_array = $theme_row;
				break;
			} elseif ( isset($theme_row['theme_regexp']) && preg_match($theme_row['theme_regexp'], $theme) ) {
				$theme_array = $theme_row;
				break;
			}
		}
		if ( empty($theme_array) ) {
			$theme_ok = false;
		}
	} elseif ( $event['reg_theme_must'] ) {
		$theme_ok = false;
	}

	return ( $theme_ok ? $theme_array : false );
}

function cmpTitle($title, $y_title, $event, $y_event) {
	$ex_text = "/【MMD】|【[^【】]*配布】|【未完成】/iu";

//	$title = mb_convert_kana(html_entity_decode($title), 'KVas');
	$title = preg_replace('/\s/u', '', mb_convert_kana(html_entity_decode($title), 'KVas'));
	$title = preg_replace($ex_text, '', $title);
	if ( isset($event['reg_title']) ) {
		foreach ( explode(',', $event['reg_title']) as $regexp ) {
			if ( $title = preg_replace($regexp, '', $title) ) {
				break;
			}
		}
	}
	
//	$y_title = mb_convert_kana(html_entity_decode($y_title), 'KVas');
	$y_title = preg_replace('/\s/u', '', mb_convert_kana(html_entity_decode($y_title), 'KVas'));
	$y_title = preg_replace($ex_text, '', $y_title);
	if ( isset($y_event['reg_title']) ) {
		foreach ( explode(',', $y_event['reg_title']) as $regexp ) {
			if ( $y_title = preg_replace($regexp, '', $y_title) ) {
				break;
			}
		}
	}
	
//	if (!($y_title == $title)) { echo "$y_title : $title\n"; }
	return ($y_title == $title);
}
?>
