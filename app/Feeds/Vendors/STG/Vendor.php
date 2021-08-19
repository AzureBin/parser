<?php

namespace App\Feeds\Vendors\STG;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Feeds\Utils\ParserCrawler;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ '.body > .container > ul > li > ul > li > a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ '.product > .card > .card-figure > a' ];
    protected array $first = [ 'https://stealthgearusa.com/sitemap/categories/' ];

    public function getProductsLinks(Data $data, string $url): array
    {
        $links = [];

        $crawler = new ParserCrawler($data->getData());
        if ($crawler->exists('.pagination')) {
            $page = 2;

            while (true) {
                $pagination_url = "$url?sort=bestselling&page=$page";
                $pager_data = $this->getDownloader()->get($pagination_url);
                if (!str_contains($pager_data->getData(), '<li class="product">')) {
                    break;
                }
                $links = [...$links, ...parent::getProductsLinks($pager_data, $pagination_url)];
                $page++;
            }
        }

        return [...$links, ...parent::getProductsLinks($data, $url)];
    }

    protected function isValidFeedItem(FeedItem $fi): bool
    {
        return $fi->getProductcode() !== $this->getPrefix() . 'NOSKU';
    }

}
