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

if (typeof(AZlink) == 'undefined') {
    AZlink = {};
}

if (typeof(AZlink.TinyWidget) == 'undefined') {
    AZlink.TinyWidget = {};

    (function(widget) {
	/*
	 * baseuri を探す
	 */
	var baseuri = (function() {
	    var ret, scripts;
	    if (document.getElementsByTagName)
		scripts = document.getElementsByTagName('script');
	    else if (document.scripts)
		scripts = document.scripts;
	    if (scripts)
		ret = scripts[scripts.length-1].src
		    .replace(/[#\?].*$/, '').replace(/[^\/]*$/, '');
	    return ret;
	})();

	/*
	 * サブルーチン／ユーティリティクラス
	 */

	// 文字列を HTML エンティティでエスケープ
	function entity(s) {
	    return String(s).replace(/[<>&"']/g, function(s) {
		switch (s) {
		case '<':
		    return '&lt;';
		    break;
		case '>':
		    return '&gt;';
		    break;
		case '&':
		    return '&amp;';
		    break;
		case '"':
		    return '&quot;';
		    break;
		case "'":
		    return '&#39;';
		    break;
		default:
		    break;
		}
	    });
	}

	// JSON 用 Ajax コール
	function ajax_json(url, callback) {
	    var xhr;
	    if (XMLHttpRequest) {
		xhr = new XMLHttpRequest();
	    } else {
		try {
		    xhr = new ActiveXObject('MSXML2.XMLHTTP.6.0');
		} catch (e) {
		    try {
			xhr = new ActiveXObject('MSXML2.XMLHTTP.3.0');
		    } catch (e) {
			xhr = new ActiveXObject('MSXML2.XMLHTTP');
		    }
		}
	    }

	    xhr.onreadystatechange = function() {
		if (xhr.readyState == 4) {
		    if (callback) {
			var retval = false;
			if (xhr.status == 200) {
			    try {
				retval = eval('(' + xhr.responseText + ')');
			    } catch (e) {
			    }
			}
			callback(retval, xhr.status);
		    }
		}
	    }

	    xhr.open("GET", url);
	    xhr.send();
	}

	// 画像 URL modifier
	function AmazonImageUrl(url) {
	    this.url = url;
	    this.country = null;
	    this.flags = null;
	}

	AmazonImageUrl.prototype.toString = function() {
	    var suffix = '';
	    if (this.country) {
		suffix += '.' + this.country;
	    }
	    if (this.flags) {
		suffix += '.';
		if (typeof(this.flags) == 'string') {
		    suffix += this.flags;
		} else {
		    suffix += '_' + this.flags.join('_') + '_';
		}
	    }
	    if (!suffix) {
		return this.url;
	    }
	    return this.url.replace(/(?:\.[^\/\.]+)*(\.[^\/\.]+)$/, suffix + '$1');
	}

	/*
	 * フォーマッタ
	 */

	function append_text(node, text) {
	    node.appendChild(document.createTextNode(text));
	}

	function append_br(node) {
	    node.appendChild(document.createElement('br'));
	}

	function int_conma(num) {
	    if (typeof(num) != 'string')
		num = String(num);
	    for (;;) {
		var t = num.replace(/^([+-]?\d+)(\d\d\d)/,"$1,$2");
		if (t == num)
		    break;
		num = t;
	    }
	    return num;
	}

	// item を HTML にフォーマット
	function add_item(target, items, index, opts) {
	    var item = items[index];

	    var anchor = document.createElement('a');
	    anchor.href = item.link;
	    anchor.className = 'azlink-widget-associate-link';

	    if (opts.onClickHook) {
		anchor.onclick = function() {
		    return (opts.onClickHook)(item);
		}
	    }

	    var img = document.createElement('img');
	    img.src = item.image;
	    img.alt = '';
	    img.title = item.title;
	    img.className = 'azlink-widget-image';

	    anchor.appendChild(img);
	    append_br(anchor);

	    var span = document.createElement('span');
	    span.className = 'azlink-widget-title';
	    if (opts.order) {
		var b = document.createElement('b');
		append_text(b, (index + 1) + '位');
		span.appendChild(b);
		append_text(span, ' ');
	    }
	    append_text(span, item.title);

	    anchor.appendChild(span);

	    if (!opts.type || opts.type != 'detail') {
		target.appendChild(anchor);
		return;
	    }

	    var nodes = [];
	    nodes.push(anchor);

	    if (item.info) {
		for (var i = 0; i < item.info.length; ++i) {
		    var span = document.createElement('span');
		    span.className = 'azlink-widget-iteminfo';
		    append_text(span, item.info[i]);
		    nodes.push(span);
		}
	    }

	    if (item.platform) {
		var span = document.createElement('span');
		span.className = 'azlink-widget-iteminfo';
		append_text(span, item.platform);
		nodes.push(span);

		/*
		 * 画像のサイズが合わないのでとりあえず無効に
		if (item.platform_image) {
		    var img = document.createElement('img');
		    img.src = item.platform_image;
		    nodes.push(img);
		}
		*/
	    }

	    if (item.date) {
		var span = document.createElement('span');

		var today = new Date(), dp = item.date.split('-');
		var dt = new Date(parseInt(dp[0]), parseInt(dp[1]) - 1, parseInt(dp[2]));
		if (dt > today) {
		    span.className = 'azlink-widget-date azlink-widget-new-item';
		} else {
		    span.className = 'azlink-widget-date';
		}
		var ymd = item.date.replace(/-/g, '/');
		append_text(span, ymd);
		nodes.push(span);
	    }

	    var price = null;
	    if (item.lower_new_price && item.upper_new_price) {
		price = int_conma(item.lower_new_price) + '円 - ' +
			int_conma(item.upper_new_price) + '円';
	    } else if (item.new_price && item.used_price) {
		if (item.new_price < item.used_price) {
		    price = int_conma(item.new_price) + '円から';
		} else if (item.new_price != item.used_price) {
		    price = int_conma(item.used_price) + '円から（新品'+
			    int_conma(item.new_price) + '円）';
		} else {
		    price = int_conma(item.new_price) + '円';
		}
	    } else if (item.new_price) {
		price = int_conma(item.new_price) + '円';
	    } else if (item.used_price) {
		price = int_conma(item.used_price) + '円から';
	    } else if (item.list_price) {
		price = int_conma(item.list_price) + '円';
	    }
	    if (price) {
		var span = document.createElement('span');
		span.className = 'azlink-widget-price';
		append_text(span, price);
		nodes.push(span);
	    }

	    for (var i = 0; i < nodes.length; ++i) {
		if (i > 0)
		    append_text(target, ' ');
		target.appendChild(nodes[i]);
	    }
	}

	// サイドバー
	function sidebar_generator(items, opts) {
	    var wrapper = document.createElement('div');
	    wrapper.className = 'azlink-widget azlink-sidebar-widget';

	    for (var i = 0; i < items.length; ++i) {
		if (opts._brReqd && i > 0)
		    append_br(wrapper);

		var elem = document.createElement('div');
		elem.className = 'azlink-widget-item azlink-sidebar-widget-item';
		if (i == 0)
		    elem.className += ' azlink-widget-first-item azlink-sidebar-widget-first-item';
		if (i == items.length - 1)
		    elem.className += ' azlink-widget-last-item azlink-sidebar-widget-last-item';
		add_item(elem, items, i, opts);
		wrapper.appendChild(elem);
	    }

	    return wrapper;
	}

	// バナー
	function banner_generator(items, opts) {
	    var table = document.createElement('table');
	    table.className = 'azlink-widget azlink-banner-widget';
	    var tbody = document.createElement('tbody');
	    table.appendChild(tbody);
	    var wrapper = document.createElement('tr');
	    tbody.appendChild(wrapper);

	    for (var i = 0; i < items.length; ++i) {
		var elem = document.createElement('td');
		elem.className = 'azlink-widget-item azlink-banner-widget-item';
		if (i == 0)
		    elem.className += ' azlink-widget-first-item azlink-banner-widget-first-item';
		if (i == items.length - 1)
		    elem.className += ' azlink-widget-last-item azlink-banner-widget-last-item';
		add_item(elem, items, i, opts);
		wrapper.appendChild(elem);
	    }

	    return table;
	}

	/*
	 * API
	 */

	function rand_int(range) {
	    if (range < 1)
		return 0;
	    for (;;) {
		var d = Math.random();
		var ret = Math.floor(d * range);
		if (ret != range)
		    return ret;
	    }
	}

	function node_random() {
	    var types = [
		'bestsellers',
		'new-releases',
		'movers-and-shakers',
		'most-gifted',
		'most-wished-for'
	    ];
	    var categories = [
		//'diy',
		'dvd',
		'toys',
		//'automotive',
		'videogames',
		//'beauty',
		//'shoes',
		//'sporting-goods',
		'software',
		//'hpc',
		//'baby',
		//'kitchen',
		'electronics',
		//'office-products',
		'watch',
		//'apparel',
		'books',
		'musical-instruments',
		//'english-books',
		'music'
		//'food-beverage'
	    ];

	    return types[rand_int(types.length)] + '/' +
		   categories[rand_int(categories.length)];
	}

	widget.api = function(opts) {
	    if (typeof(baseuri) == 'undefined')
		return;

	    // node パラメータは必須
	    var node;
	    if (!(opts.node)) {
		node = node_random();
	    } else if (typeof(opts.node) == 'string') {
		node = opts.node;
	    } else {
		node = opts.node[rand_int(opts.node.length)];
	    }

	    // imageFlags のデフォルトは 'AA160'
	    if (typeof(opts.imageFlags) == 'undefined')
		opts.imageFlags = [ 'AA160' ];

	    var dest;
	    var scripts = document.getElementsByTagName('script');
	    if (scripts && scripts.length) {
		dest = scripts[0].parentNode;
	    } else if (document.body) {
		dest = document.body;
	    } else if (document.documentElement) {
		dest = document.documentElement;
	    } else {
		throw 'Missing script or body element';
	    }

	    var json_callback = function(retval, status) {
		var items = (retval && retval.items) ? retval.items : [];

		if (typeof(opts.numItems) != 'undefined')
		    items.splice(opts.numItems);

		for (var i = 0; i < items.length; ++i) {
		    var item = items[i];
		    var url = ['http://www.amazon.co.jp/exec/obidos/ASIN',item.asin];
		    if (opts.associateId)
			url.push(opts.associateId);
		    url.push('ref=nosim/');
		    item.link = url.join('/');

		    if (typeof(item.image) == 'undefined') {
			item.image = new AmazonImageUrl('http://ecx.images-amazon.com/images/G/09/nav2/dp/no-image-no-ciu.gif');
		    } else {
			/*
			var obj = new AmazonImageUrl(item.image);
			if (!item.image.match(/\.gif$/)) {
			    item.image.country = '09';
			}
			item.image = obj;
			*/
			item.image = new AmazonImageUrl(item.image);
		    }
		    if (opts.imageFlags)
			item.image.flags = opts.imageFlags;
		}

		(opts.onload)(items, opts);
	    }

	    /*
	     * NOTE
	     * json_url が 404 なら api_url を呼び出す。
	     * JSON の取得に成功した場合、expire を経過していれば
	     * probability の確率で api_url を呼び出してファイルを
	     * 更新させる。
	     * cron を使わなくて済むようにするのが目的。
	     */

	    var json_url = baseuri + 'json/' + node + '.js';
	    var api_url = baseuri + 'api.php?node=' + encodeURIComponent(node);

	    ajax_json(json_url, function(retval, status) {
		if (status == 404) {
		    ajax_json(api_url, json_callback);

		} else {
		    if (retval && retval.expire) {
			var expire = new Date(retval.expire),
			    now = new Date(),
			    // Fallback for v1.0.2
			    probability = (typeof(retval.probability) == 'undefined' ? 1.0 : parseFloat(retval.probability));
			if (expire.getTime() < now.getTime() &&
			    Math.random() <= probability) {
			    ajax_json(api_url, json_callback);
			    return;
			}
		    }
		    json_callback(retval, status);
		}
	    });
	}

	/*
	 * 主処理
	 */

	var index = 0;

	// ブログパーツを埋め込む
	function embed_blogparts(opts) {
	    if (typeof(baseuri) == 'undefined')
		return;

	    // ブログパーツ置換用スタブ要素を document.write
	    var id = 'azlink-widget-embed-stub-' + (index++);
	    document.write('<span id="' + id + '" style="display:none"></span>');

	    // API を呼び出す
	    opts.onload = function(items, opts) {
		var stub = document.getElementById(id);
		var parentNode = stub.parentNode;

		var dest = (opts.generator)(items, opts);
		if (dest) {
		    if (typeof(dest) == 'string') {
			var tmp = document.createElement('div');
			tmp.innerHTML = dest;
			while (tmp.childNodes.length > 0) {
			    var child = tmp.firstChild;
			    tmp.removeChild(child);
			    parentNode.insertBefore(child, stub);
			}
		    } else {
			parentNode.insertBefore(dest, stub);
		    }
		}
		parentNode.removeChild(stub);
	    }

	    widget.api(opts);
	}

	widget.sidebar = function(opts) {
	    opts = opts ? opts : {};
	    opts.generator = sidebar_generator;
	    embed_blogparts(opts);
	}

	widget.banner = function(opts) {
	    opts = opts ? opts : {};
	    opts.generator = banner_generator;
	    embed_blogparts(opts);
	}
    })(AZlink.TinyWidget);
}
