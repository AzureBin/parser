<?php

namespace App\Feeds\Vendors\STG;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;
use Exception;
use Symfony\Component\DomCrawler\Crawler;

class Parser extends HtmlParser
{
    private array $attributes = [];
    private ?float $weight = null;
    private ?string $brand = null;
    private string $desc = '';
    private array $short_desc = [];

    private function cartesian( array $input ): array
    {
        $result = [ [] ];

        foreach ( $input as $key => $values ) {
            $append = [];

            foreach ( $result as $product ) {
                foreach ( $values as $item ) {
                    $product[ $key ] = $item;
                    $append[] = $product;
                }
            }

            $result = $append;
        }

        return $result;
    }

    private function extractWeight( string $str ): float
    {
        return (float)explode( '|', $str )[ 1 ] / 453.592;
    }

    private function hasSku(): bool
    {
        return $this->filter( '.trustpilot-widget' )->count() > 0;
    }

    private function sanitizeAttributeValue( string $value ): string
    {
        if ( StringHelper::existsMoney( $value ) ) {
            $pos = strpos( $value, '+$' );
            if ( $pos === false ) {
                $pos = strpos( $value, '+ $' );
            }
            $value = substr( $value, 0, $pos - 1 );
        }

        return $value;
    }

    private function replaceAttributeFromDesc( string $key, string $value ): void
    {
        $this->desc = preg_replace( '/<li.*?' . str_replace( '/', '\/', $key . '\s*:.\s*' . $value ) . '<\/li>/i', '', $this->desc );
    }

    private function parseShortDesc(): array
    {
        $short_desc = [];

        // check is this keyword 'Specifications'?
        $this->filter( '.productView-description p' )->each( function ( ParserCrawler $node ) use ( &$short_desc ) {
            // if found 'Specifications:'
            if ( $node->text() === 'Specifications:' ) {
                $this->filter( '.productView-description ul li' )->each( function ( ParserCrawler $node ) use ( &$short_desc ) {
                    // while correspond ':'
                    if ( str_contains( $node->text(), ':' ) ) {
                        if ( str_starts_with( $node->text(), 'Brand:' ) ) {
                            // get Brand
                            $this->brand = explode( 'Brand:', $node->text() )[ 1 ];
                        }
                        else if ( str_starts_with( $node->text(), 'Weight:' ) ) {
                            // get Weight
                            $this->weight = $this->extractWeight( $node->text() );
                        }
                        else {
                            // get attributes
                            $attr_key_value = array_map( static fn( $el ) => StringHelper::normalizeSpaceInString( $el ), explode( ':', $node->text() ) );
                            if ( StringHelper::isNotEmpty( $attr_key_value[ 1 ] ) ) {
                                $this->replaceAttributeFromDesc( $attr_key_value[ 0 ], $attr_key_value[ 1 ] );
                                $this->attributes[ $attr_key_value[ 0 ] ] = $attr_key_value[ 1 ];
                            }
                            else {
                                $short_desc[] = $attr_key_value[ 0 ] . ':';
                            }

                        }
                    }
                    else {
                        $short_desc[] = $node->text();
                    }
                } );
            }
        } );

        // or gather standard shortDescription (ul li)
        if ( count( $short_desc ) < 1 ) {
            $this
                ->filter( '.productView-description ul li' )
                ->each( function ( ParserCrawler $node ) use ( &$short_desc ) {
                    $text = $node->text();
                    if ( str_contains( $text, ':' ) ) {
                        // get attributes
                        $text = str_replace( [ '"', '::marker' ], '', $text );
                        $attr_key_value = array_map( static fn( $el ) => StringHelper::normalizeSpaceInString( $el ), explode( ':', $text ) );

                        if ( StringHelper::isNotEmpty( $attr_key_value[ 1 ] ) ) {
                            $this->replaceAttributeFromDesc( $attr_key_value[ 0 ], $attr_key_value[ 1 ] );
                            $this->attributes[ $attr_key_value[ 0 ] ] = $attr_key_value[ 1 ];
                        }
                        else {
                            $short_desc[] = $attr_key_value[ 0 ] . ':';
                        }
                    }
                    else {
                        $short_desc[] = $text;
                    }
                } );
        }

        if ( stripos( $this->getHtml( '.productView-description' ), 'includes:' ) !== false
            || stripos( $this->getHtml( '.productView-description' ), 'Accessories:' ) !== false ) {
            return [];
        }

        return $short_desc;
    }

    private function sanitizeDescription( ParserCrawler $nodes ): string
    {
        $description = '';
        $contains_includes = stripos( $this->getHtml( '.productView-description' ), 'includes:' ) !== false
            || stripos( $this->getHtml( '.productView-description' ), 'Accessories:' ) !== false;

        foreach ( $nodes as $node ) {
            if ( $node->nodeName !== 'script' ) {
                if ( $node->nodeName === 'p' ) {
                    try {
                        $valid_bool = ( $contains_includes || $node->nextElementSibling->nodeName !== 'ul' );
                    } catch ( Exception ) {
                        $valid_bool = true;
                    }
                    if ( $valid_bool && str_ends_with( $node->textContent, ':' ) ) {
                        if ( $node->textContent !== 'Description:' ) {
                            $description .= $node->ownerDocument->saveHTML( $node );
                        }
                    }
                    else if ( $valid_bool || $node->textContent !== 'Details:' ) {
                        $description .= $node->ownerDocument->saveHTML( $node );
                    }
                }
                else if ( ( $contains_includes || $node->nodeName !== 'ul' ) ) {
                    $description .= $node->ownerDocument->saveHTML( $node );
                }
            }
        }

        return $description;
    }

    public function parseContent( Data $data, array $params = [] ): array
    {
        if ( empty( $data->getData() ) ) {
            $data = $this->getVendor()->getDownloader()->get( $params[ 'url' ] );
        }

        if ( empty( $data->getData() ) ) {
            return [];
        }

        return parent::parseContent( $data, $params );
    }

    public function beforeParse(): void
    {
        $nodes = $this->filter( '.productView-description > div > .productView-description > *' );

        if ( count( $nodes ) === 1 && $nodes->getNode( 0 )->nodeName === 'table' ) {
            $nodes = ( new ParserCrawler( $nodes->getNode( 0 ) ) )->filter( 'td > *' );
        }

        $this->desc = $this->sanitizeDescription( $nodes );
        $this->short_desc = $this->parseShortDesc();
    }

    public function isGroup(): bool
    {
        return $this->exists( '[name*="attribute"]' );
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $children = [];
        $xsrf_token = $this->getVendor()->getDownloader()->getCookie( 'XSRF-TOKEN' );
        $product_id = $this->filter( 'input[name="product_id"]' )->first()->attr( 'value' );
        $attributes = [];
        $this->filter( 'div[data-product-option-change] .form-select[required], div[data-product-option-change] .form-radio[required]' )
            ->each( static function ( Crawler $node ) use ( &$attributes ) {
                if ( $node->nodeName() === 'select' ) {
                    $values = $node
                        ->filter( 'option[data-product-attribute-value]' )
                        ->each( static fn( Crawler $node ) => $node->attr( 'value' ) );
                    foreach ( $values as $value ) {
                        $attributes[ substr( $node->attr( 'name' ), 10, -1 ) ][] = $value;
                    }
                }
                else {
                    $value = $node->attr( 'value' );
                    $attributes[ substr( $node->attr( 'name' ), 10, -1 ) ][] = $value;
                }
            } );

        $host = substr( $this->getUri(), 0, strpos( $this->getUri(), '/', 8 ) );
        $endpoint = $host . '/remote/v1/product-attributes/' . $product_id;
        $this->getVendor()->getDownloader()->setHeader( 'x-xsrf-token', $xsrf_token );
        $links = [];

        foreach ( $this->cartesian( $attributes ) as $variation_params ) {
            $params = [];
            foreach ( $variation_params as $key => $value ) {
                $params[ "attribute[$key]" ] = $value;
            }
            $links[] = new Link( $endpoint, 'POST', $params );
        }

        foreach ( $this->getVendor()->getDownloader()->fetch( $links, true ) as $response ) {
            $json = $response[ 'data' ]->getJSON();
            if ( array_key_exists( 'data', $json ) ) {
                $json = $json[ 'data' ];
            }
            else {
                continue;
            }

            $fi = clone $parent_fi;

            if ( $json[ 'sku' ] === null ) {
                continue;
            }

            if ( isset( $json[ 'image' ][ 'data' ] ) ) {
                $fi->setImages( [ str_replace( '{:size}', '1280x1280', $json[ 'image' ][ 'data' ] ) ] );
            }
            $fi->setCostToUs( $json[ 'price' ][ 'without_tax' ][ 'value' ] );
            $fi->setRAvail( $json[ 'instock' ] ? self::DEFAULT_AVAIL_NUMBER : 0 );

            $mpn = $json[ 'sku' ];

            $fi_attributes = [];
            $product_name = '';
            foreach ( $response[ 'link' ][ 'params' ] as $key => $value ) {
                $node = $this->filter( "[name='$key']" );
                if ( $node->nodeName() === 'select' ) {
                    $attribute_key = substr( $node->siblings()->text(), 0, -10 );
                    $attribute_value = $node->filter( "[value='$value']" )->text();
                }
                else {
                    $node = $node->filter( "[value='$value']" );
                    $attribute_key = substr( $node->siblings()->first()->text(), 0, -10 );
                    $attribute_value = $node->attr( 'data-option-label' );
                }

                $mpn .= '-' . $value;
                $fi_attributes[ $attribute_key ] = $this->sanitizeAttributeValue( $attribute_value );
            }

            $fi->setMpn( StringHelper::normalizeSpaceInString( $mpn ) );

            if ( count( $fi_attributes ) > 0 ) {
                foreach ( $fi_attributes as $key => $value ) {
                    if ( $product_name !== '' ) {
                        $product_name .= ' ';
                    }
                    $product_name .= $key . ': ' . $value . '.';
                }
            }

            if ( $product_name !== '' ) {
                $fi->setProduct( $product_name );
            }

            $children[] = $fi;
        }

        return $children;
    }

    public function getProduct(): string
    {
        $url = $this->filter('.productView-reviewLink a')->attr('href');
        $x = 100;

        return $this->getText( '.productView-title' );
    }

    public function getDescription(): string
    {
        return $this->desc;
    }

    public function getShortDescription(): array
    {
        return $this->short_desc;
    }

    public function getImages(): array
    {
        $images = $this
            ->filter( '.productView-thumbnail-link' )
            ->each( static fn( Crawler $node ) => $node->attr( 'data-image-gallery-zoom-image-url' ) );

        if ( count( $images ) === 0 ) {
            return [ $this->getAttr( '.productView-image--default', 'data-zoom-target' ) ];
        }

        return $images;
    }

    public function getMpn(): string
    {
        return $this->hasSku() ? $this->filter( '.trustpilot-widget' )->first()->attr( 'data-sku' ) : '';
    }

    public function getCostToUs(): float
    {
        return $this->getMoney( '.productView-price' );
    }

    public function getAvail(): ?int
    {
        $stock_status = $this->filter( 'meta[property="og:availability"]' )->eq( 0 )->attr( 'content' );
        return $stock_status === 'instock'
            ? self::DEFAULT_AVAIL_NUMBER
            : 0;
    }

    public function getAttributes(): ?array
    {
        return ( count( $this->attributes ) > 0 ) ? $this->attributes : null;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function getVideos(): array
    {
        $videos[] = $this->getAttrs( '.productView-description iframe', 'src' );
        return array_map( static function ( $value ) {
            return str_replace( 'embed/', 'watch?v=', $value );
        }, $videos );
    }
}
