<?php

namespace App\Feeds\Vendors\RCH;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [
        'div.category-top-filter nav.pagination ul.pagination-list li.pagination-item a.pagination-link',
        'ul.nav-bar-ul li a',
        'ul.navList li.navList-item div.cat-wrapper a.navList-action',
        'ul.navList-SubCat li.navList-item .cat-wrapper .cat-name a.navList-action'
    ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'li.product article.card figure.card-figure a:first-child' ];

    protected array $first = [
        // 'https://rchobbyexplosion.com/discount-remote-control-trucks/'
        // 'https://rchobbyexplosion.com/rc-batteries/'
        'https://rchobbyexplosion.com/'
    ];

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() ) && !empty( $fi->getImages() ) && !empty( $fi->getAttributes() );
    }
}