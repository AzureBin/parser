<?php

namespace App\Feeds\Vendors\RCH;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private const MAIN_DOMAIN = 'https://rchobbyexplosion.com';

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
        $this->filter( '#tab-description ul li' )->each( function ( ParserCrawler $c ) {
            if ( str_contains( $c->text(), 'Weight:' ) ) {
                if (preg_match('#\((.*?)\)#', $c->text(), $m)) {
                    // dd($m);
                    $this->shipping_weight = (float) preg_replace("/[^0-9.]/", "", $m[1]);
                }
                // $this->shipping_weight = str_replace("Weight:", "", $c->text());
            }
            elseif ( str_contains( $c->text(), 'Length:' ) ) {
                if (preg_match('#\((.*?)\)#', $c->text(), $m)) {
                    // dd($m);
                    $this->dims[ 'x' ] = (float) preg_replace("/[^0-9.]/", "", $m[1]);
                }
                // $this->dims[ 'x' ] = str_replace("Length:", "", $c->text());
            }
            elseif ( str_contains( $c->text(), 'Width:' ) ) {
                if (preg_match('#\((.*?)\)#', $c->text(), $m)) {
                    // dd($m);
                    $this->dims[ 'y' ] = (float) preg_replace("/[^0-9.]/", "", $m[1]);
                }
            }
        });

        $this->filter( 'meta' )->each( function ( ParserCrawler $c ) {
            if ($c->attr('itemprop') == 'mpn')
            {
                $this->mpn = $c->attr('content');
            }
        });
    }

    public function getMpn(): string
    {
        return $this->mpn;
    }

    public function getProduct(): string
    {
        return $this->product ?: $this->getText( 'h1.productView-title' );
    }

    public function getCostToUs(): float
    {
        return StringHelper::getMoney( $this->getMoney( 'span.price.price--withoutTax' ) );
    }

    public function getImages(): array
    {
        return $this->getAttrs('.productView-thumbnail-link', 'href');
    }

    public function getDimX(): ?float
    {
        return $this->dims[ 'x' ] ?? null;
    }

    public function getDimY(): ?float
    {
        return $this->dims[ 'y' ] ?? null;
    }

    public function getDescription(): string
    {
        return trim($this->getText('#tab-description'));
    }

    public function getShortDescription(): array
    {
        if ( $this->exists( '#tab-description p' ) ) {
            return $this->getContent( '#tab-description p' );
        }
        return [];
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
        return self::DEFAULT_AVAIL_NUMBER;
    }

    public function getUpc(): ?string
    {
        return $this->getText('dd.productView-info-value.upc-val');
    }
}