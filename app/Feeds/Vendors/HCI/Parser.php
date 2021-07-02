<?php

namespace App\Feeds\Vendors\HCI;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser {

    public function getMpn(): string
    {
        return $this->getText('.product-line-sku-value');
    }

    public function getProduct(): string
    {
        return trim($this->getAttr('meta[property="og:title"]', 'content'));
    }

    public function getListPrice(): ?float
    {
        if ($this->exists('span[itemprop="highPrice"]')) {
            return StringHelper::getMoney(trim($this->getAttr('span[itemprop="highPrice"]', 'data-rate')));
        }
        return StringHelper::getMoney(trim($this->getAttr('span[itemprop="price"]', 'data-rate')));
    }

    public function getShortDescription(): array
    {
        return array($this->getAttr('meta[name="description"]', 'content'));
    }

    public function getDescription(): string
    {
        return trim($this->getAttr('meta[property="og:description"]', 'content'));
    }

    public function getBrand(): ?string
    {
        return trim($this->getAttr('meta[property="og:provider_name"]', 'content'));
    }

    public function getCostToUs(): float
    {
        if($this->exists('span[itemprop="lowPrice"]')){
            return StringHelper::getMoney(trim($this->getAttr('span[itemprop="lowPrice"]', 'data-rate')));
        }
        return StringHelper::getMoney(trim($this->getAttr('span[itemprop="price"]', 'data-rate')));
    }

    public function getAvail(): ?int
    {
        return self::DEFAULT_AVAIL_NUMBER;
    }

    public function getImages(): array
    {
        return array(trim($this->getAttr('meta[property="og:image"]', 'content')));
    }

    public function getOptions(): array
    {
        $options = [];
        $option_lists = $this->filter( '.custcol1-controls product-views-option-tile-container > label' );

        if ( !$option_lists->count() ) {
            return $options;
        }

        $option_lists->each( function ( ParserCrawler $list ) use ( &$options ) {
            $label = $list->filter( 'label-custcol1' );
            if ( $label->count() === 0 ) {
                return;
            }
            $name = trim( $label->text(), ' : ' );
            $options[ $name ] = [];
            $list->filter( 'input' )->each( function ( ParserCrawler $option ) use ( &$options, $name ) {
                $options[ $name ][] = trim( $option->text(), '  ' );
            } );
        } );

        return $options;
    }
}
