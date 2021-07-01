<?php

namespace App\Feeds\Vendors\GLA;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private const MAIN_DOMAIN = 'https://www.gelliarts.com/collections/products';

    private array $dims = [];
    private array $shorts = [];
    private ?array $attrs = null;
    private ?float $shipping_weight = null;
    private ?float $list_price = null;
    private ?int $avail = null;
    private string $mpn = '';
    private string $product = '';
    private ?string $upc = null;

    public function beforeParse(): void
    {
        $this->avail = 0;
        $this->filter( 'div.description ul li' )->each( function ( ParserCrawler $c ) {
            if ( str_contains( $c->text(), 'UPC' ) ) {
                $this->upc = str_replace("UPC", "", $c->text());
            }
            else {
                $this->shorts[] = StringHelper::normalizeSpaceInString( $c->text() );
            }
        });
    }

    public function getMpn(): string
    {
        return $this->getAttr('article.product-detail', 'id');
    }

    public function getProduct(): string
    {
        return $this->product ?: $this->getText( 'h1.title' );
    }

    public function getCostToUs(): float
    {
        return StringHelper::getMoney( $this->getMoney( 'span.price.sell-price' ) );
    }

    public function getImages(): array
    {
        return array_merge(['https:' . $this->getAttr('.primary-images a', 'href')], $this->getSrcImages('.secondary-image'));
    }

    public function getDimX(): ?float
    {
        return $this->dims[ 'x' ] ?? null;
    }

    public function getDimY(): ?float
    {
        return $this->dims[ 'y' ] ?? null;
    }

    public function getShortDescription(): array
    {
        return $this->shorts;
    }

    public function getAttributes(): ?array
    {
        return $this->attrs ?? null;
    }

    public function getListPrice(): ?float
    {
        return $this->list_price;
    }

    public function getShippingWeight(): ?float
    {
        return $this->shipping_weight;
    }

    public function getAvail(): ?int
    {
        return $this->avail;
    }

    public function getUpc(): ?string
    {
        return $this->upc;
    }
}