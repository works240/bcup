<?php 
function extractkey_all ( $subject, $key ) {
//	$subject = preg_replace("/　/", ' ', $subject);
	preg_match_all("/【[　\s]*?{$key}[　\s]*?[:：]([^【】]*?)】/iu", $subject, $matches);
	
	return $matches;
}

function extracttheme( $msg ){
// theme取得
	$theme = '';

	$msg = mb_convert_kana($msg, 'KVas');
	$matches = extractkey_all($msg, '[TＴｔ][hＨｈ][eＥｅ][mＭｍ][eＥｅ]');

	foreach ( $matches[1] as $t ) {
		$t=trim($t);
#		printf("extract-theme: [%s]\n", $t);
		$theme = $t;
	}

	return $theme;
}

function extractname( $msg ){
// name取得
	$name = array();

	$matches = extractkey_all($msg, '[nｎＮ][aａＡ][mｍＭ][eｅＥ]');

	foreach ( $matches[1] as $n ) {
#		printf("extract-name: [%s]\n", $n);
		$name[] = $n;
	}

	return implode(' ', $name);
}

function extractyosen( $msg ){
// 予選動画取得
	$y_nicono = array();

	$msg = mb_convert_kana($msg, 'KVas');
	$matches = extractkey_all($msg, '予選');

	foreach ( $matches[1] as $y ) {
		$y=trim($y);
#		printf("extract-yosen: [%s]\n", $y);
		$y_nicono[] = $y;
	}

	return implode(' ', $y_nicono);
}

// length2time 上映時間 "hh:mm:ss"形式を秒に変換
function length2time( $src ){
	$hh = 0;
	$mm = 0;
	$ss = 0;

	if (preg_match('/(\d+):(\d+):(\d+)/', $src, $matches)) {
		$hh = (int) $matches[1];
		$mm = (int) $matches[2];
		$ss = (int) $matches[3];
	} elseif (preg_match('/(\d+):(\d+)/', $src, $matches)) {
		$mm = (int) $matches[1];
		$ss = (int) $matches[2];
	}
	
	return $hh * 3600 + $mm * 60 + $ss;
}

//
function parseTmplate ($template, $values) {
	preg_match_all("/#([a-z0-9_\-]*?)#/iu", $template, $matches);
	
	$output = $template;
	foreach ( $matches[1] as $key ) {
		if ( isset($values[$key] ) && !is_null( $values[$key]) ) {
			$output = preg_replace("/#$key#/", $values[$key], $output);
		}
	}

	return $output;
}?>
