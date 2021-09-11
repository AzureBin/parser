<?php

namespace App\Feeds\Vendors\STG;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ '.body > .container > ul > li > ul > li > a', '.pagination-link--next' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ '.product > .card > .card-figure > a' ];

    protected array $first = [ 'https://stealthgearusa.com/sitemap/categories/' ];

    protected function isValidFeedItem( FeedItem $fi ): bool
    {
        if ( $fi->isGroup() ) {
            $fi->setChildProducts( array_values(
                array_filter( $fi->getChildProducts(), static fn( FeedItem $item ) => !empty( $item->getMpn() ) && count( $item->getImages() ) )
            ) );
            return count( $fi->getChildProducts() );
        }
        return !empty( $fi->getMpn() ) && count( $fi->getImages() );
    }
}
