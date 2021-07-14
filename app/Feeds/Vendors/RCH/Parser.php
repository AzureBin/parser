<?php

namespace App\Feeds\Vendors\RCH;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private const MAIN_DOMAIN = 'https://rchobbyexplosion.com';
    public const IN_STOCK = 'IN STOCK';
    public const YOUTUBE = 'youtube';

    private array $dims = [];
    private array $shorts = [];
    private ?array $attrs = null;
    private ?float $shipping_weight = null;
    private ?float $list_price = null;
    private ?string $avail = null;
    private string $mpn = '';
    private string $product = '';
    private ?string $upc = null;
    private string $desc = '';
    private array $video = [];

    public function beforeParse(): void
    {
        $this->filter( '#tab-description ul li' )->each( function ( ParserCrawler $c ) {
            if ( str_contains( $c->text(), 'Weight:' ) ) {
                if (preg_match('#\((.*?)\)#', $c->text(), $m)) {
                    $this->shipping_weight = (float) preg_replace("/[^0-9.]/", "", $m[1]);
                }
            }
            elseif ( str_contains( $c->text(), 'Length:' ) ) {
                if (preg_match('#\((.*?)\)#', $c->text(), $m)) {
                    $this->dims[ 'x' ] = (float) preg_replace("/[^0-9.]/", "", $m[1]);
                }
            }
            elseif ( str_contains( $c->text(), 'Width:' ) ) {
                if (preg_match('#\((.*?)\)#', $c->text(), $m)) {
                    $this->dims[ 'y' ] = (float) preg_replace("/[^0-9.]/", "", $m[1]);
                }
            }
        });

        $description = $this->getHtml('#tab-description');
        $description = preg_replace("/\r|\n/", "", $description);

        $technicalSpecsPattern = "/TECHNICAL SPECS:.*?<\/p><ul>(.*?)<\/ul>/mui";
        preg_match_all($technicalSpecsPattern, $description, $result);
        $attrs = [];
        if (isset($result[1][0])) {
            $crawler = new ParserCrawler($result[1][0]);
            $crawler->filter( 'li' )->each( function ( ParserCrawler $c ) use (&$attrs) {
                if ( str_contains( $c->text(), ':' ) ) {
                    [ $key, $val ] = explode( ':', $c->text() );
                    $attrs[ StringHelper::normalizeSpaceInString( $key ) ] = StringHelper::normalizeSpaceInString( $val );
                }
            });
        }
        $this->attrs = $attrs;
        /* $featurePattern = "/<p.*?>Features:.*?<\/p><ul.*?>(.*?)<\/ul>/mui"; */
        $featurePattern = "/<strong>Features:.*?<\/p><ul.*?>(.*?)<\/ul>/mui";
        preg_match_all($featurePattern, $description, $featureResult);
        $shorts = [];
        if (isset($featureResult[0][0])) {
            $crawler = new ParserCrawler($featureResult[0][0]);
            $crawler->filter( 'li' )->each( function ( ParserCrawler $c ) use (&$shorts) {
                $shorts[] = $c->text();
            });
        }
        $this->shorts = $shorts;

        $descriptionPattern = "/<p>.*?(?=(<b>FEATURES<\/b>:|Features:))/mui";
        preg_match_all($descriptionPattern, $description, $descriptionResult);
        if (isset($descriptionResult[0][0])) {
            $crawler = new ParserCrawler($descriptionResult[0][0]);
            $crawler->filter( 'p' )->each( function ( ParserCrawler $c ) {
                $this->desc .= $c->html();
            });
            $crawler->filter( 'div iframe' )->each( function ( ParserCrawler $c ) {
                if (str_contains( $c->attr('src'), self::YOUTUBE )) {
                    $this->video = [
                        [
                            'name' => $this->getProduct(),
                            'provider' => self::YOUTUBE,
                            'video' => $c->attr('src')
                        ]
                    ];
                }
            });
        }

        $this->filter( 'div.meta-wrapper' )->each( function ( ParserCrawler $c ) {
            if ( str_contains( $c->filter('dt')->text(), 'Availability:' ) ) {
                $stock = trim($c->filter('dd')->text());
                $this->avail = ($stock == self::IN_STOCK) ? self::DEFAULT_AVAIL_NUMBER : 0;
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
        return trim($this->desc);
    }

    public function getShortDescription(): array
    {
        return $this->shorts ?? [];
    }

    public function getAttributes(): ?array
    {
        return $this->attrs ?: null;
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
        return $this->avail;
    }

    public function getUpc(): ?string
    {
        return $this->getText('dd.productView-info-value.upc-val');
    }

    public function getVideos(): array
    {
        return $this->video;
    }
}