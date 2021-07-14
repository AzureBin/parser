<?php

namespace App\Feeds\Vendors\HMC;

use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor{

    // amendment one
    public const CATEGORY_LINK_CSS_SELECTORS = [
        '.header-menu-level1 li a',
        '.facets-category-cell-title a',
        '.global-views-pagination-next a'];

    public const PRODUCT_LINK_CSS_SELECTORS = ['.facets-item-cell-grid-details a'];

    protected const CHUNK_SIZE = 30;

    protected array $first = ['https://www.homecontrols.com/'];

}
