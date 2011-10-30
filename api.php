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

global $JSON_CACHE_DIR, $WORK_DIR, $JSON_CACHE_LIFETIME, $COMPILE_JS,
       $JSON_UPDATE_PROBARILITY, $API_BASEURI, $KEEP_RSS_TEMPORARY_FILES;

// デフォルト値のセット
// 詳細は config.php.example 参照
$JSON_CACHE_DIR = dirname(__file__) . DIRECTORY_SEPARATOR . 'json';
$WORK_DIR = dirname(__file__) . DIRECTORY_SEPARATOR . 'work';
$JSON_CACHE_LIFETIME = 3600;
$COMPILE_JS = true;
$JSON_UPDATE_PROBARILITY = 1.0;
$API_BASEURI = NULL;
$KEEP_RSS_TEMPORARY_FILES = false;

// config.php を読み込む
@include 'config.php';

// cli 起動時はコマンドラインオプションも読み込む
if (php_sapi_name() == 'cli') {
    foreach (getopt('c:w:l:j:p:b:k:') as $opt => $arg) {
	switch ($opt) {
	case 'c':
	    $JSON_CACHE_DIR = $arg;
	    break;
	case 'w':
	    $WORK_DIR = $arg;
	    break;
	case 'l':
	    $JSON_CACHE_LIFETIME = (float)$arg;
	    break;
	case 'j':
	    $COMPILE_JS = ($arg == 'false' ? false : true);
	    break;
	case 'p':
	    $JSON_UPDATE_PROBARILITY = (float)$arg;
	    break;
	case 'b':
	    $API_BASEURI = ($arg == 'false' ? '' : ($arg == 'null' ? NULL : $arg));
	    break;
	case 'k':
	    $KEEP_RSS_TEMPORARY_FILES = ($arg == 'false' ? false : true);
	    break;
	}
    }
}

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

function minify_script() {
    $src = 'tinywidget.min.js.in';
    $dest = 'tinywidget.min.js';

    if (file_exists($dest)) {
	// タイムスタンプを比較
	$mtime = filemtime($dest);
	if (filemtime($src) <= $mtime && filemtime(__file__) <= $mtime) {
	    return;
	}
    }

    // ロックする
    $lock_path = $GLOBALS['WORK_DIR'] . DIRECTORY_SEPARATOR . 'minify.lock';
    $lock = fopen($lock_path, 'w+');
    if (!flock($lock, LOCK_EX)) {
	exit(1);
    }
    fwrite($lock, "0\n");	// For NFS
    fflush($lock);

    // もう一度タイムスタンプをチェック
    clearstatcache();
    if (file_exists($dest)) {
	$mtime = filemtime($dest);
	if (filemtime($src) <= $mtime && filemtime(__file__) <= $mtime) {
	    @unlink($lock_path);
	    fclose($lock);
	    return;
	}
    }

    // 現在の設定値を読み込む
    $lines = @file($dest);
    if ($lines === FALSE) {
	@unlink($lock_path);
	fclose($lock);
	return;
    }
    $opts = array();
    foreach ($lines as $line) {
	if (preg_match('/^AZlink\.TinyWidget\.(\w+)=(.*);$/', $line, $mo)) {
	    $opts[$mo[1]] = json_decode($mo[2]);
	}
    }

    // PHP の設定と比較
    $changed = false;

    if (is_null($GLOBALS['API_BASEURI'])) {
	if (isset($_SERVER['PHP_SELF'])) {
	    $re = '/' . preg_quote(basename(__file__), '/') . '$/';
	    $baseuri = preg_replace($re, '', $_SERVER['PHP_SELF']);
	} else {
	    $baseuri = '';
	}
    } else {
	$baseuri = $GLOBALS['API_BASEURI'];
    }

    if (!isset($opts['baseuri']) || $opts['baseuri'] != $baseuri) {
	$changed = true;
    }

    if (!isset($opts['jsonUpdateProbability']) ||
	$opts['jsonUpdateProbability'] != $GLOBALS['JSON_UPDATE_PROBARILITY']) {
	$changed = true;
    }

    if ($changed) {
	$ctx = file($src);
	for ($i = 0; $i < count($ctx); ++$i) {
	    $ctx[$i] = rtrim($ctx[$i]);
	}
	$ctx[] = "AZlink.TinyWidget.baseuri=" . json_encode($baseuri) . ";";
	$ctx[] = "AZlink.TinyWidget.jsonUpdateProbability=" . json_encode($GLOBALS['JSON_UPDATE_PROBARILITY']) . ";";
	file_put_contents($dest, implode("\n", $ctx));
    } else {
	touch($dest);
    }

    @unlink($lock_path);
    fclose($lock);
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
    if (!mkdir_p($GLOBALS['WORK_DIR'])) {
	forbidden();
	exit(1);
    }
    $rss_path = $GLOBALS['WORK_DIR'] . DIRECTORY_SEPARATOR . rawurlencode($node);
    $fh = fopen($rss_path, "wb");
    if (!$fh) {
	forbidden();
	exit(1);
    }

    // ロック
    if (!flock($fh, LOCK_EX)) {
	forbidden();
	exit(1);
    }
    rewind($fh);
    fwrite($fh, "0");

    // flock してからファイルが更新されていれば出力して終わり
    if (put_json_if_available($json_path)) {
	fclose($fh);
	exit(0);
    }

    // CURL で RSS を読み込む
    $ch = curl_init("http://www.amazon.co.jp/rss/{$node}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
    $ctx = curl_exec($ch);
    if ($ctx === FALSE) {
	curl_close($ch);
	@unlink($rss_path);
	fclose($fh);
	forbidden();
	exit(1);
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code != 200) {
	@unlink($rss_path);
	fclose($fh);
	forbidden();
	exit(1);
    }

    // json を出力
    if (!mkdir_p(dirname($json_path))) {
	forbidden();
	exit(1);
    }
    $json = json_encode(parse_rss_string($ctx));
    file_put_contents($json_path, $json);

    // ロック解除
    if ($GLOBALS['KEEP_RSS_TEMPORARY_FILES']) {
	ftruncate($fh, 0);
	rewind($fh);
	fwrite($fh, $ctx);
    } else {
	@unlink($rss_path);
    }
    fclose($fh);

    // json を出力
    header('Content-Type: application/json');
    echo $json;
}

// 

/*
 * MAIN
 */

if ($GLOBALS['COMPILE_JS']) {
    minify_script();
}

// node パラメータがあれば JSON 出力
if (isset($_GET['node']) && $_GET['node']) {
    json_response($_GET['node']);
}

// vim:ts=8:sw=4:
