<?php

namespace App\Feeds\Vendors\RCH;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'div.category-top-filter nav.pagination ul.pagination-list li.pagination-item a.pagination-link' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'li.product article.card figure.card-figure a:first-child' ];

    protected array $first = [
        'https://rchobbyexplosion.com/discount-remote-control-trucks/',
        'https://rchobbyexplosion.com/discount-remote-control-cars/',
        'https://rchobbyexplosion.com/discount-remote-control-drones-helicopters/',
        'https://rchobbyexplosion.com/electric-remote-control-boats/',
        'https://rchobbyexplosion.com/electric-remote-control-airplanes/'
    ];

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() );
    }
}