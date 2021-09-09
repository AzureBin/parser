<?php

namespace App\Feeds\Vendors\STG;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Data;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ '.body > .container > ul > li > ul > li > a', '.pagination-link--next' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ '.product > .card > .card-figure > a' ];

    protected array $first = [ 'https://stealthgearusa.com/sitemap/categories/' ];

    public function getProductsLinks(Data $data, string $url): array
    {
        $links = [];
        return [...$links, ...parent::getProductsLinks($data, $url)];
    }

    protected function isValidFeedItem(FeedItem $fi): bool
    {
        return $fi->getProductcode() !== $this->getPrefix() . ' ';
    }
}
