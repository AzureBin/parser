<?php

namespace App\Feeds\Vendors\GLA;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'div.small-12.medium-4.columns.pages a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'article.item.collection-product a:first-child' ];

    protected array $first = [ 'https://www.gelliarts.com/collections/products' ];

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() );
    }
}