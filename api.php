<?php

/*
 * Copyright (c) 2011 sakuratan.biz
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom
 * the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */

/*
 * 設定
 */

global $JSON_CACHE_DIR, $WORK_DIR, $JSON_CACHE_LIFETIME,
       $JSON_UPDATE_PROBARILITY, $KEEP_RSS_TEMPORARY_FILES;

// デフォルト値を読み込む
require_once 'config.php.defaults';

// config.php （があれば）読み込む
@include 'config.php';

// 

/*
 * HTML 文字列からタグ等を取り除く
 */
function cleanup_html_string($s) {
    return trim(html_entity_decode(preg_replace('/<[^>]*?>/', '', $s), ENT_QUOTES, 'UTF-8'));
}

/*
 * intconma 形式の HTML 文字列を数値に変換
 */
function cleanup_html_number($s) {
    return (float)cleanup_html_string(str_replace(',', '', $s));
}

/*
 * YYYY/MM/DD 形式の HTML 文字列を ISO-8601 形式に変換
 */
function cleanup_html_date($s) {
    return str_replace('/', '-', cleanup_html_string($s));
}

/*
 * Amazon の画像オプションを削除
 */
function reset_image_flags($s) {
    return cleanup_html_string(preg_replace('/\._.*?_(\.[^\.]*)$/', '\\1', $s));
}

/*
 * Amazon の RSS をパース
 */
function parse_rss_string($ctx) {
    $rss = simplexml_load_string($ctx);

    $items = array();
    foreach ($rss->channel->item as $item) {
	$datum = array();

	// asin は guid から調べる
	$guid = (string)($item->guid);
	$datum['asin'] = preg_replace('/^.*_/', '', $guid);

	// title
	if ($item->title) {
	    $datum['title'] = preg_replace('/^#\d*:\s*/', '', (string)($item->title));
	} else {
	    $datum['title'] = '';
	}

	if ($item->description) {
	    $html = (string)($item->description);

	    // 最初の img タグが画像
	    if (preg_match('/<img\s+src="(.*?)"/', $html, $mo)) {
		$datum['image'] = reset_image_flags($mo[1]);
	    }

	    foreach (preg_split(',<br\s*/?>,', $html) as $line) {
		// 作者とか
		if (preg_match(',<span class="riRssContributor">\s*(.*)\s*</span>,', $line)) {
		    foreach (preg_split(',<span class=["\']byLinePipe["\']>\s*\|\s*</span>,', $line) as $s) {
			if (!isset($datum['info'])) {
			    $datum['info'] = array();
			}
			$datum['info'][] = cleanup_html_string($s);
		    }
		    continue;
		}

		// 新品の値段
		if (preg_match('/(?:新品|ダウンロード)&#65306;/u', $line)) {
		    if (preg_match('|<strike>\s*￥\s*([\d,]+)\s*</strike>|u', $line, $mo)) {
			$datum['list_price'] = cleanup_html_number($mo[1]);
		    }

		    if (preg_match('|<b>\s*￥\s*([\d,]+)\s*-\s*￥\s*([\d,]+)\s*</b>|u', $line, $mo)) {
			$datum['lower_new_price'] = cleanup_html_number($mo[1]);
			$datum['upper_new_price'] = cleanup_html_number($mo[2]);
		    } elseif (preg_match('|<b>\s*￥\s*([\d,]+)\s*</b>|u', $line, $mo)) {
			$datum['new_price'] = cleanup_html_number($mo[1]);
			if (!isset($datum['list_price']) && isset($datum['new_price'])) {
			    $datum['list_price'] = $datum['new_price'];
			}
		    }
		    continue;
		}

		// 中古品の値段
		if (preg_match('|中古品を見る.*<span class="price">\s*￥\s*([\d,]+)\s*</span>|u', $line, $mo)) {
		    $datum['used_price'] = cleanup_html_number($mo[1]);
		    continue;
		}

		// ゲームのプラットフォーム
		if (preg_match(',<b>プラットフォーム\s*:\s*</b>(.*)$,', $line, $mo)) {
		    $datum['platform'] = cleanup_html_string($mo[1]);
		    if (preg_match('/<img src="(.*?)"/', $mo[1], $mo)) {
			$datum['platform_image'] = cleanup_html_string($mo[1]);
		    }
		}

		// 発売日かリリース日
		if (preg_match(',発売日\s*:\s*(\d+/\d+/\d+),u', $line, $mo)) {
		    $datum['date'] = cleanup_html_date($mo[1]);
		    continue;
		} elseif (preg_match(',出版年月\s*:\s*(\d+/\d+/\d+),u', $line, $mo)) {
		    $datum['date'] = cleanup_html_date($mo[1]);
		    continue;
		}
	    }
	}

	$items[] = $datum;
    }

    return array(
	'items' => $items,
	'expire' => gmdate('r', time() + $GLOBALS['JSON_CACHE_LIFETIME']),
	'probarility' => $GLOBALS['JSON_UPDATE_PROBARILITY'],
    );
}

// 

/*
 * mkdir -p
 */
function mkdir_p($dir, $mode=0777) {
    if (!is_dir($dir)) {
	if (!mkdir($dir, $mode, true)) {
	    return FALSE;
	}
    }
    return TRUE;
}

// 

/*
 * 403 Forbidden
 */
function forbidden() {
    header('HTTP/1.1 403 Forbidden');
    header('Status: 403 Forbidden');
    echo '<html><head><title>403 Forbidden</title></head>';
    echo '<body><h1>403 Forbidden</h1></body></html>';
}

/*
 * $json_path が存在し生存期間内なら出力する
 */
function put_json_if_available($json_path) {
    $ctx = file_get_contents($json_path);
    if ($ctx === FALSE)
	return FALSE;

    $data = @json_decode($ctx);
    if (!$data || !($data->expire))
	return FALSE;

    if (strtotime($data->expire) < time())
	return FALSE;

    header('Content-Type: application/json');
    file_put_contents($ctx);
    return TRUE;
}

/*
 * JSON レスポンスを出力する
 */
function json_response($node) {
    // パラメータチェックとか
    $node = trim($node, '/');
    $json_path = $GLOBALS['JSON_CACHE_DIR'] . DIRECTORY_SEPARATOR . $node . '.js';
    if (basename($json_path) == '.js') {
	forbidden();
	trigger_error("Invalid node {$node}", E_USER_ERROR);
    }

    // json があれば出力して終わり
    if (put_json_if_available($json_path)) {
	exit(0);
    }

    // rss を読み込む準備
    if (!mkdir_p($GLOBALS['WORK_DIR'], $GLOBALS['DIR_PERMS'])) {
	forbidden();
	exit(1);
    }

    // RSS の読み込みはシリアライズする
    $lock = fopen($GLOBALS['WORK_DIR'] . DIRECTORY_SEPARATOR . 'rss.lock', 'wb');
    if (!$lock) {
	forbidden();
	exit(1);
    }

    // ロック
    if (!flock($lock, LOCK_EX)) {
	forbidden();
	exit(1);
    }
    // for NFS
    rewind($lock);
    fwrite($lock, "0");
    fflush($lock);

    // flock してからファイルが更新されていれば出力して終わり
    if (put_json_if_available($json_path)) {
	fclose($lock);
	exit(0);
    }

    // 出力ファイル (RSS) をオープン
    $rss_path = $GLOBALS['WORK_DIR'] . DIRECTORY_SEPARATOR . rawurlencode($node);
    $fh = fopen($rss_path, "wb");
    if (!$fh) {
	forbidden();
	exit(1);
    }

    // 一応ロックする
    if (!flock($fh, LOCK_EX)) {
	forbidden();
	exit(1);
    }
    rewind($fh);

    // CURL で RSS を読み込む
    $ch = curl_init("http://www.amazon.co.jp/rss/{$node}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
    $ctx = curl_exec($ch);
    if ($ctx === FALSE) {
	curl_close($ch);
	@unlink($rss_path);
	forbidden();
	exit(1);
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code != 200) {
	@unlink($rss_path);
	forbidden();
	exit(1);
    }

    // json を出力
    if (!mkdir_p(dirname($json_path), $GLOBALS['DIR_PERMS'])) {
	@unlink($rss_path);
	forbidden();
	exit(1);
    }
    $json = json_encode(parse_rss_string($ctx));
    file_put_contents($json_path, $json);
    chmod($json_path, $GLOBALS['FILES_PERMS']);

    // ロック解除
    if ($GLOBALS['KEEP_RSS_TEMPORARY_FILES']) {
	fwrite($fh, $ctx);
	chmod($rss_path, $GLOBALS['FILES_PERMS']);
    } else {
	@unlink($rss_path);
    }
    fclose($fh);
    fclose($lock);

    // json を出力
    header('Content-Type: application/json');
    echo $json;
}

// 

/*
 * MAIN
 */

// node パラメータがあれば JSON 出力
if (isset($_GET['node']) && $_GET['node']) {
    umask(0);
    json_response($_GET['node']);
}

// vim:ts=8:sw=4:
