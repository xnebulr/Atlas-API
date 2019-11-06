<?php
if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

require __DIR__ . "/../../../lib/simple_html_dom.php";
header('Content-Type: application/json');
$latest_post_json = file_get_contents("latest.json");
$latest_post = json_decode($latest_post_json, true);

$items = array();
$url = 'https://www.nomanssky.com/';
$category = 'news';
$page = 1;
$post_count = 1;
$error_string = "error";

$latest_post_title = checkLatestPost($url, $category);

if (trim($latest_post_title) != trim($latest_post['title'])) {
    if (filesize("posts.json") == 0) {
        initializePosts($url, $category, $page, $post_count, $error_string);
    } else {
        updatePosts($url, $category, $page, $post_count, $error_string);
    }
} else {
    echo "\n\n----- No new posts found. -----\n\nLatest post found: " . trim($latest_post_title) . "\nLatest post saved: " . trim($latest_post['title']) . "\n\n";
}

function checkLatestPost($url, $category) {
    $html = file_get_html($url . $category);
    $posts = $html->find('article', 0);
    $title = $posts->find('h3', 0)->plaintext;
    return $title;
}

function initializePosts($url, $category, $page, $post_count, $error_string) {
    echo "\n\n----- Import started! -----\n\n\n";
    do {
        set_error_handler(
            function ($err_severity, $err_msg, $err_file, $err_line, array $err_context) {
                // do not throw an exception if the @-operator is used (suppress)
                if (error_reporting() === 0) return false;
                throw new ErrorException( $err_msg, 0, $err_severity, $err_file, $err_line );
            },
            E_WARNING
        );
        try {
            $html = file_get_html($url . $category . '/page/' . $page);
            $posts = $html->find('article');

            echo "Started import of page $page\n";

            foreach ($posts as $post) {
                $item['url'] = $post->find('a', 0)->href;
                $article_html = file_get_html($item['url']);
                $item['title'] = $article_html->find('h1', 0)->plaintext;
                $title_pattern = array("&#8217;", "&#8211;", " View Article", "&nbsp;", "’", "–", '\u00a0');
                $title_replace = array("\'", "–", "", "", "\'", "-", "");
                $title = str_replace($title_pattern, $title_replace, $item['title']);
                $item['title'] = $title;
                $item['timestamp'] = $article_html->find('meta[property=article:published_time]', 0)->content;
                $timestamp = strtotime($item['timestamp']);
                $item['timestamp'] = $timestamp;
                $item['teaser'] = $article_html->find('meta[property=og:description]', 0)->content;
                $content_pattern = array("&#8217;", "&#8211;", " View Article", "&nbsp;", "’", "–", "\xE2\x80\xA6", "&#8230;");
                $content_replace = array("\'", "-", "", "", "\'", "-", "...", "...");
                $content_replace = str_replace($content_pattern, $content_replace, $item['teaser']);
                $teaser = preg_replace('/\xc2\xa0/', ' ', $content_replace);
                $item['teaser'] = $teaser;
                $item['image'] = $article_html->find('meta[property=og:image]', 0)->content;
                $item['image_small'] = $post->find('.background--cover', 0)->style;
                $image_small_pattern = array("'", "background-image: url(", ");");
                $image_small_replace = array("", "", "");
                $image_small = str_replace($image_small_pattern, $image_small_replace, $item['image_small']);
                $item['image_small'] = $image_small;
                $item['content'] = $article_html->find('//div[@class="box box--fill-height"]', 0)->innertext;
                $content_pattern = array("src=\"/wp-content");
                $content_replace = array("src=\"https://www.nomanssky.com/wp-content");
                $content = str_replace($content_pattern, $content_replace, $item['content']);
                $content_pattern = array("&#8217;", "&#8211;", "&nbsp;", "’", "–", '\u00a0', "'", "\t", "href=\"/");
                $content_replace = array("\'", "–", "", "\'", "-", "", "\'", "", "href=\"https://www.nomanssky.com/");
                $content = str_replace($content_pattern, $content_replace, $content);
                $content_pattern = array("\\\\'");
                $content_replace = array("\'");
                $content = str_replace($content_pattern, $content_replace, $content);
                $item['content'] = $content;
                $items[] = $item;

                echo "Added post: $title\n";
                $post_count++;
            }
            echo "Completed import of page $page\n\n";
            $page++;
        } catch (Exception $e) {
            $export = fopen("posts.json", "w") or die("Unable to open file!");
            fwrite($export, json_encode(array_reverse($items)));

            $latest_post = fopen("latest.json", "w") or die("Unable to open file!");
            fwrite($latest_post, json_encode($items[0]));

            handler();

            $page_count = $page - 1;
            $post_count = $post_count - 1;
            echo "\n----- Import successful! With a total of $page_count pages containing $post_count posts -----\n\n";
            echo $e->getMessage();
            break;
        }

        restore_error_handler();
    } while (!(strpos($item["title"], $error_string)));
}

function updatePosts($url, $category, $page, $post_count, $error_string) {
    echo "\n\n----- Import started! -----\n\n\n";
    $html = file_get_html($url . $category . '/page/' . $page);
    $posts = $html->find('article', 0);

    $item['url'] = $posts->find('a', 0)->href;
    $article_html = file_get_html($item['url']);
    $item['title'] = $article_html->find('h1', 0)->plaintext;
    $title_pattern = array("&#8217;", "&#8211;", " View Article", "&nbsp;", "’", "–", '\u00a0');
    $title_replace = array("\'", "–", "", "", "\'", "-", "");
    $title = str_replace($title_pattern, $title_replace, $item['title']);
    $item['title'] = $title;
    $item['timestamp'] = $article_html->find('meta[property=article:published_time]', 0)->content;
    $timestamp = strtotime($item['timestamp']);
    $item['timestamp'] = $timestamp;
    $item['teaser'] = $article_html->find('meta[property=og:description]', 0)->content;
    $content_pattern = array("&#8217;", "&#8211;", " View Article", "&nbsp;", "’", "–", "\xE2\x80\xA6", "&#8230;");
    $content_replace = array("\'", "-", "", "", "\'", "-", "...", "...");
    $content_replace = str_replace($content_pattern, $content_replace, $item['teaser']);
    $teaser = preg_replace('/\xc2\xa0/', ' ', $content_replace);
    $item['teaser'] = $teaser;
    $item['image'] = $article_html->find('meta[property=og:image]', 0)->content;
    $item['image_small'] = $posts->find('.background--cover', 0)->style;
    $image_small_pattern = array("'", "background-image: url(", ");");
    $image_small_replace = array("", "", "");
    $image_small = str_replace($image_small_pattern, $image_small_replace, $item['image_small']);
    $item['image_small'] = $image_small;
    $item['content'] = $article_html->find('//div[@class="box box--fill-height"]', 0)->innertext;
    $content_pattern = array("src=\"/wp-content");
    $content_replace = array("src=\"https://www.nomanssky.com/wp-content");
    $content = str_replace($content_pattern, $content_replace, $item['content']);
    $content_pattern = array("&#8217;", "&#8211;", "&nbsp;", "’", "–", '\u00a0', "'", "\t", "href=\"/");
    $content_replace = array("\'", "–", "", "\'", "-", "", "\'", "", "href=\"https://www.nomanssky.com/");
    $content = str_replace($content_pattern, $content_replace, $content);
    $content_pattern = array("\\\\'");
    $content_replace = array("\'");
    $content = str_replace($content_pattern, $content_replace, $content);
    $item['content'] = $content;
    $items[] = $item;

    echo "Added post: $title\n";

    $export_content = file_get_contents('posts.json');
    $export = fopen("posts.json", "w") or die("Unable to open file!");
    $tempArray = json_decode($export_content, true);
    array_unshift($tempArray, $item);
    fwrite($export, json_encode($tempArray));

    $latest_post = fopen("latest.json", "w") or die("Unable to open file!");
    fwrite($latest_post, json_encode($item)) . ',';

    handler();
    sendNotification();

    echo "\n\n----- Import successful! -----\n\n";
}

function handler() {
    include_once(__DIR__ . "/../../../../public/atlas/v1/news/main.php");
    $News = new News;
    $News->mainSql();
}

function sendNotification() {
    $output = shell_exec('/usr/bin/nodejs '.__DIR__.'/../../notifications/send_notification_news.js');
    echo $output;
}