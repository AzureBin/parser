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
    private array $desc = [];

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

        $this->desc = $this->filter( '#tab-description p' )->each( function ( ParserCrawler $c ) {
            return $c->filter( 'strong' )->each( function ( ParserCrawler $d ) {
                if ( str_contains( $d->text(), 'FEATURES:' ) ) {
                    $features = $d->parents()->nextAll();
                    $features->filter( 'ul li' )->each( function ( ParserCrawler $e ) {
                        array_push($this->shorts, $e->text());
                    });
                    foreach ($features as $node) {
                        $node->parentNode->removeChild($node);
                    }
                }
                if ( str_contains( $d->text(), 'TECHNICAL SPECS:' ) ) {
                    $technicalSpecs = $d->parents()->nextAll();
                    $technicalSpecs->filter( 'ul li' )->each( function ( ParserCrawler $e ) {
                        if ( str_contains( $e->text(), ':' ) ) {
                            [ $key, $val ] = explode( ':', $e->text() );
                            $this->attrs[$key] = $val;
                        }
                    });
                    foreach ($technicalSpecs as $node) {
                        $node->parentNode->removeChild($node);
                    }
                }
            });
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
        // return trim($this->desc);
        return trim($this->getText('#tab-description'));
    }

    public function getShortDescription(): array
    {
        return $this->shorts;
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
        return StringHelper::getMoney( $this->getMoney( 'span.price.price--non-sale' ) );
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