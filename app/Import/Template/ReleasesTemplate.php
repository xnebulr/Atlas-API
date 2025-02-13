<?php

namespace AtlasAPI\Import\Template;

use AtlasAPI\Import\ReleasesImport;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Container\ContainerInterface;
use RuntimeException;
use simplehtmldom\HtmlWeb;

class ReleasesTemplate
{
    private ContainerInterface $container;
    private Psr16Adapter $Psr16Adapter;

    public function __construct(ContainerInterface $container, Psr16Adapter $Psr16Adapter)
    {
        $this->container = $container;
        $this->Psr16Adapter = $Psr16Adapter;
    }

    public function getRelease($url, $category): array
    {
        $releases = new ReleasesImport($this->container);
        $html = (new HtmlWeb())->load($url . $category);

        if (!$html) {
            throw new RuntimeException('An error occurred trying to load ' . $url . $category);
        }

        $posts = $html->find('div.grid__cell');
        $items = [];

        foreach ($posts as $post) {
            $postKey = hash('sha256',
                $category . $post->find('h2', 0)->plaintext . $post->find('a', 0)->href);

            if (!$this->Psr16Adapter->has($postKey)) {
                $item = $this->templateReleases($post, $url);

                if ($releases->getFieldByURL('url', $item['url'])) {
                    if (!count($items)) {
                        echo "[RELEASES] Nothing new found\n";
                    }
                    break;
                }

                $items[] = $item;
                $this->Psr16Adapter->set($postKey, $item, 31556952);

                echo '[RELEASES] New entry found: ' . $item['title'] . "\n";
            } else {
                $item = $this->Psr16Adapter->get($postKey);

                if ($releases->getFieldByURL('url', $item['url'])) {
                    if (!count($items)) {
                        echo "[RELEASES] Nothing new found\n";
                    }
                    break;
                }

                $items[] = $item;
                echo '[RELEASES] Loading from cache: ' . $item['title'] . "\n";
            }
        }

        $db_count = $releases->getItemCount();
        $count = count($items);
        $result = [];
        foreach ($items as $item) {
            $item['id'] = $count + $db_count;
            $result[] = $item;
            --$count;
        }

        return $result;
    }

    public function templateReleases($post, $url): array
    {
        // URL
        $item['url'] = $post->find('a', 0)->href;

        // Check and add the missing URL part if necessary
        $parsed_url = parse_url($item['url']);
        if (!isset($parsed_url['scheme'])) {
            $item['url'] = rtrim($url, '/') . '/' . ltrim($item['url'], '/');
        }
        $item['url'] = str_replace('http://', 'https://', $item['url']);

        // Post
        $post_html = (new HtmlWeb())->load($item['url']);

        if (!$post_html) {
            throw new RuntimeException('An error occurred trying to load ' . $item['url']);
        }

        // Title
        $search = ['&#8217;', '&#8211;', ' View Article', '&nbsp;', '’', '–', '\u00a0'];
        $replace = ['\'', '–', '', '', '\'', '-', ''];
        $item['title'] = $post->find('h2', 0)->plaintext;
        $item['title'] = str_replace($search, $replace, $item['title']);

        // Timestamp
        $item['timestamp'] = $post_html->find('meta[property=article:published_time]', 0)->content ?? 0;

        if (!$item['timestamp']) {
            $item['timestamp'] = $post_html->find('span.date', 0)->plaintext ?? 0;
        }

        $item['timestamp'] = strtotime($item['timestamp']);

        // Platforms
        // PC
        $item['platforms']['pc'] = (int)false;

        if ($post->find('div.platform--pc')) {
            $pc = $post->find('div.platform--pc', 0)->plaintext;

            if ($pc === 'PC') {
                $item['platforms']['pc'] = (int)true;
            }
        }

        // NINTENDO SWITCH
        $item['platforms']['nintendo-switch'] = (int)false;

        if ($post->find('div.platform--switch')) {
            $switch = $post->find('div.platform--switch', 0)->plaintext;

            if ($switch === 'Nintendo Switch') {
                $item['platforms']['nintendo-switch'] = (int)true;
            }
        }

        // PS4 & PS5
        $item['platforms']['ps4'] = (int)false;
        $item['platforms']['ps5'] = (int)false;

        if ($post->find('div.platform--ps4')) {
            $ps = $post->find('div.platform--ps4');

            foreach ($ps as $ps_) {
                $ps_ = $ps_->plaintext;

                if ($ps_ === 'PS4') {
                    $item['platforms']['ps4'] = (int)true;
                }

                if ($ps_ === 'PS5') {
                    $item['platforms']['ps5'] = (int)true;
                }

                if ($ps_ === 'Nintendo Switch') {
                    $item['platforms']['nintendo-switch'] = (int)true;
                }
            }
        }

        // XBOX ONE & XBOX SERIES X/S
        $item['platforms']['xbox-one'] = (int)false;
        $item['platforms']['xbox-series'] = (int)false;
        $item['platforms']['xbox-game-pass'] = (int)false;

        if ($post->find('div.platform--xbox')) {
            $xbox = $post->find('div.platform--xbox');

            foreach ($xbox as $xbox_) {
                $xbox_ = $xbox_->plaintext;

                if ($xbox_ === 'Xbox One') {
                    $item['platforms']['xbox-one'] = (int)true;
                }

                if ($xbox_ === 'Xbox Series X/S') {
                    $item['platforms']['xbox-series'] = (int)true;
                }

                if ($xbox_ === 'Xbox Game Pass') {
                    $item['platforms']['xbox-game-pass'] = (int)true;
                }
            }
        }

        // MICROSOFT STORE
        $item['platforms']['ms-store'] = (int)false;

        if ($post->find('div.platform--pc[style=margin-left:0;background-color:grey;]')) {
            $item['platforms']['ms-store'] = (int)true;
        }

        // Excerpt
        $search = ['&#8217;', '&#8211;', "\r\n             Read more", "\r\n          Read more", "&nbsp;", "’", "–", "\xE2\x80\xA6", "&#8230;", "            ", "           "];
        $replace = ['’', '-', '', '', '', '\'', '-', '...', '...', '', ''];
        $item['excerpt'] = $post->find('p', 0)->plaintext;
        $excerpt = str_replace($search, $replace, $item['excerpt']);
        $item['excerpt'] = preg_replace('/\xc2\xa0/', ' ', $excerpt);

        // Image
        $search = ['http://'];
        $replace = ['https://'];
        $item['image'] = $post_html->find('meta[property=og:image]', 0)->content;
        $item['image'] = str_replace($search, $replace, $item['image']);

        // Body
        $search = ['src=\"/wp-content', '&#8217;', '&#8211;', '&nbsp;', '’', '–', '\u00a0', "'", "\t", '     ', 'href=\"/', "\\\\'", "\t"];
        $replace = ['src=\"https://www.nomanssky.com/wp-content', '\'', '–', '', '\'', '-', '', '\'', '', '', 'href=\"https://www.nomanssky.com/', '\'', ''];
        $item['body'] = $post_html->find('//div[@class="box box--fill-height"]', 0)->innertext ?? '';
        $item['body'] = str_replace($search, $replace, $item['body']);
        $search = ['/<h1 class=\"text--heading-centered.*<\/h1>/', '/<div class=\"post-meta text--centered[\s\S].*\s<\/div>/'];
        $replace = ['', ''];
        $item['body'] = preg_replace($search, $replace, $item['body']);

        return $item;
    }
}