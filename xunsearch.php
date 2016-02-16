<?php
/**
 * @package xunsearch for wordpress
 * @version 1.0
 */
/*
Plugin Name: xunsearch for wordpress
Plugin URI: http://wordpress.org/plugins/xunsearch_for_wordpress/
Description: 整合xunsearch到wordpress中，用来解决在大型系统中，搜索占用大的问题
Version: 1.0
Author URI: http://www.fatescript.com/
*/

require_once(WP_PLUGIN_DIR.'/'. dirname(plugin_basename(__FILE__)).'/libraries/lib/XS.php');

add_action('init', 'xunsearch_init', 11);


// xusearch 初始化
function xunsearch_init()
{
    if ($_GET['searchapi'])
    {
        xunsearch_valid_token();

        $type = $_GET['searchapi'];

        if (!is_file(WP_PLUGIN_DIR.'/'. dirname(plugin_basename(__FILE__)).'/libraries/app/' . $type . '.ini'))
        {
            header('HTTP/1.1 403 Forbidden');
            exit('MISS xunsearch ini File');
        }

        $xs = new XS($type);

        $rs = xunsearch_query($xs);

        if (empty($rs))
        {   
            header('HTTP/1.1 204 No Content');
            exit('No Content');
        }
        else
        {
            header("Content-Type: application/json; charset=utf-8");
            exit(json_encode($rs));
        }
    }
}

//验证请求合法性
function xunsearch_valid_token()
{
    $token = get_option('xunsearch_token', 'tmtpost');

    if ($_GET['token'] != $token)
    {
        header('HTTP/1.1 401 Unauthorized');
        exit('xunsearch token is incorrent');
    }
    return TRUE;
}


function xunsearch_query($xs)
{

// 支持的 GET 参数列表
// q: 查询语句
// m: 开启模糊搜索，其值为 yes/no
// f: 只搜索某个字段，其值为字段名称，要求该字段的索引方式为 self/both
// s: 排序字段名称及方式，其值形式为：xxx_ASC 或 xxx_DESC
// p: 显示第几页，每页数量为 XSSearch::PAGE_SIZE 即 10 条
// ie: 查询语句编码，默认为 UTF-8
// oe: 输出编码，默认为 UTF-8
// xml: 是否将搜索结果以 XML 格式输出，其值为 yes/no

    $eu = '';
$__ = array('q', 'm', 'f', 's', 'offset', 'limit', 'ie', 'oe', 'syn', 'xml', 'offset');
foreach ($__ as $_) {
    $$_ = isset($_GET[$_]) ? $_GET[$_] : '';
}
// input encoding
if (!empty($ie) && !empty($q) && strcasecmp($ie, 'UTF-8')) {
    $q = XS::convert($q, $cs, $ie);
    $eu .= '&ie=' . $ie;
}

// output encoding
if (!empty($oe) && strcasecmp($oe, 'UTF-8')) {

    function xs_output_encoding($buf)
    {
        return XS::convert($buf, $GLOBALS['oe'], 'UTF-8');
    }
    ob_start('xs_output_encoding');
    $eu .= '&oe=' . $oe;
} else {
    $oe = 'UTF-8';
}

// recheck request parameters
$q = get_magic_quotes_gpc() ? stripslashes($q) : $q;
$f = empty($f) ? '_all' : $f;
${'m_check'} = ($m == 'yes' ? ' checked' : '');
${'syn_check'} = ($syn == 'yes' ? ' checked' : '');
${'f_' . $f} = ' checked';
${'s_' . $s} = ' selected';

// base url
$bu = $_SERVER['SCRIPT_NAME'] . '?q=' . urlencode($q) . '&m=' . $m . '&f=' . $f . '&s=' . $s . $eu;

// other variable maybe used in tpl
$count = $total = $search_cost = 0;
$docs = $related = $corrected = $hot = array();
$error = $pager = '';
$total_begin = microtime(true);

// perform the search
try {
    $search = $xs->search;
    $search->setCharset('UTF-8');

    if (empty($q)) {
        // just show hot query
        $hot = $search->getHotQuery();
    } else {
        // fuzzy search
        $search->setFuzzy($m === 'yes');

        // synonym search
        $search->setAutoSynonyms($syn === 'yes');

        // set query
        if (!empty($f) && $f != '_all') {
            $search->setQuery($f . ':(' . $q . ')');
        } else {
            $search->setQuery($q);
        }

        // set sort
        if (($pos = strrpos($s, '_')) !== false) {
            $sf = substr($s, 0, $pos);
            $st = substr($s, $pos + 1);
            $search->setSort($sf, $st === 'ASC');
        }

        // set offset, limit
        $offset = $offset ? $offset : 0;
        $limit  = $limit  ? $limit  : 10;
        $search->setLimit($limit, $offset);

        // get the result
        $search_begin = microtime(true);
        $docs = $search->search();

        //get tags and dig
        // get_tags_and_dig($docs);

        $search_cost = microtime(true) - $search_begin;

        // get other result
        $count = $search->getLastCount();
        $total = $search->getDbTotal();

        if ($xml !== 'yes') {
            // try to corrected, if resul too few
            if ($count < 1 || $count < ceil(0.001 * $total)) {
                $corrected = $search->getCorrectedQuery();
            }
            // get related query
            $related = $search->getRelatedQuery();
        }
    }
} catch (XSException $e) {
    $error = strval($e);
}

// calculate total time cost
$total_cost = microtime(true) - $total_begin;


// XML OUPUT
if ($xml === 'yes' && !empty($q)) {
    header("Content-Type: text/xml; charset=$oe");
    echo "<?xml version=\"1.0\" encoding=\"$oe\" ?>\n";
    echo "<xs:result count=\"$count\" total=\"$total\" cost=\"$total_cost\" xmlns:xs=\"http://www.xunsearch.com\">\n";
    if ($error !== '') {
        echo "  <error><![CDATA[" . $error . "]]></error>\n";
    }
    foreach ($docs as $doc) {
        echo "  <doc index=\"" . $doc->rank() . "\" percent=\"" . $doc->percent() . "%\">\n";
        foreach ($doc as $k => $v) {
            echo "    <$k>";
            echo is_numeric($v) ? $v : "\n      <![CDATA[" . $v . "]]>\n    ";
            echo "</$k>\n";
        }
        echo "  </doc>\n";
    }
    echo "</xs:result>\n";
    exit(0);
}
else if (!empty($q) && $error != '')
{
    $response = array(
        'status'   => 0,
        'msg'      => $error
    );
    return $response;
}
else if (!empty($q))
{
    $response = array();
    $response['status']       = 1;
    $response['cost']         = $total_cost;
    $response['total']        = $count;
 
    foreach ($docs as $key => $doc)
    {
        foreach ($doc as $k=>$v)
        {
            $response['items'][$key][$k] = $v;
            $response['items'][$key]['percent'] = $doc->percent();
            $response['items'][$key]['rank'] = $doc->rank();
        }

        $cats = filter_category_by_id($response['items'][$key]['post_id']);

        $response['items'][$key]['category'] = NULL;
        if(is_array($cats))
        {   
            foreach ($cats as $k=>$cat)
            {
                $response['items'][$key]['category'][] = array(
                    'cat_name'  => $cat->name,
                    'cat_id'    => $cat->term_id,
                    'cat_slug'  => $cat->slug,
                );
            }
        }

        $tags_em = $search->highlight($doc->tags);

        //处理标签
        $tag_names      = explode(',', $tags_em);
        $tag_slugs      = explode(',', $doc['slugs']);

        if ($tag_names[0])
        {
            foreach ($tag_names as $tag_key=>$tag_v)
            {
                $response['items'][$key]['tag'][] = array(
                    'tag_name'   => $tag_v,
                    'tag_slug'   => $tag_slugs[$tag_key]
                );
            }
        }

        $response['items'][$key]['post_title']   = $search->highlight($doc->post_title); 
        $response['items'][$key]['post_excerpt'] = $search->highlight($doc->post_excerpt); 
        $response['items'][$key]['author'] = $search->highlight($doc->author);
        $response['items'][$key]['post_time'] = date('Y-m-d H:i',$doc->post_time);

        unset($response['items'][$key]['category_id'],$response['items'][$key]['tags'],$response['items'][$key]['slugs']);

    }
    return $response;   
}

}

