<?php

namespace App\Feeds\Vendors\HCI;

use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor{

    public const CATEGORY_LINK_CSS_SELECTORS = ['.header-menu-level1 > li > a', '.facets-category-cell-title a'];
    public const PRODUCT_LINK_CSS_SELECTORS = ['.facets-item-cell-grid-details a'];

    protected const CHUNK_SIZE = 30;

    public array $first = ['https://www.homecontrols.com'];

}
