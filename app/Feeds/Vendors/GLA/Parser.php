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

    public function beforeParse(): void
    {
        $this->mpn = $this->getAttr('article.product-detail', 'id');
        $this->avail = $this->getText( '.item button.add-to-cart' ) == 'Add to Cart' ? 1 : 0; 
        $this->filter( 'div.description ul li' )->each( function ( ParserCrawler $c ) {
            if ( stripos( $c->text(), 'Add to Cart' ) !== false ) {
                $this->avail = StringHelper::getFloat( $c->text() );
            }
            elseif ( stripos( $c->text(), 'Sold Out' ) !== false ) {
                $this->avail = 0;
            }
            else {
                $this->shorts[] = StringHelper::normalizeSpaceInString( $c->text() );
            }
        } );
    }

    public function getMpn(): string
    {
        return $this->mpn;
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
        return [ 'https:' .$this->getAttr( 'div.image-container.primary-image-container img', 'src' ) ];
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
}