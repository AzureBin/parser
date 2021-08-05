<?php

namespace App\Feeds\Vendors\PRP;

use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const PRODUCT_LINK_CSS_SELECTORS = [ '#categories > div > ul > li > a' ];
    protected array $first = [ 'http://www.prat-usa.com' ];
}
