<?php

namespace App\Feeds\Vendors\PRP;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;

class Parser extends HtmlParser
{
    private ?array $dimensions = null;
    private array $attributes = [];

    private function extractMpn(ParserCrawler $node): string
    {
        // Remove everything except direct text (e.g. html tags with content)
        return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $node->filter('li.partNo')->html());
    }

    private function extractUPC(ParserCrawler $node): ?string
    {
        $upc = $node->filter('li.partNo > .upc')->text();
        return $upc !== '' ? $upc : null;
    }

    private function extractPrice(ParserCrawler $node): float
    {
        // Extract price and remove '$' sign
        return (float) substr($node->filter('li.specialPrice')->text(), 1);
    }

    private function extractColor(ParserCrawler $node): string
    {
        $alt = $node->filter('.productImage > img')->attr('alt');
        $matches = [];
        preg_match("@Color\s([a-zA-Z]+)\s@", $alt, $matches);

        return $matches[1];
    }

    private function getDimensions(ParserCrawler $node): array
    {
        if ($this->dimensions === null) {
            $dimensions_str = $node->filter('.productDescription')->text();
            $matches = [];
            preg_match("@(\d+(?:\.\d{1,2})?)\"\sx\s(\d+(?:\.\d{1,2})?)\"(?:\sx\s(\d+(?:\.\d{1,2})?)\")?@", $dimensions_str, $matches);

            $this->dimensions = array_map('floatval', array_slice($matches, 1));
        }

        return $this->dimensions;
    }

    public function getProduct(): string
    {
         return $this->getText('#centercolumn > h1');
    }

    public function getDescription(): string
    {
        $whole_desc = $this->getHtml('#tabs-2');
        $not_need_part = '<ul>' . $this->getHtml('#tabs-2 > ul:first-child') . '</ul>';

        return str_replace($not_need_part, '', $whole_desc);
    }

    public function getShortDescription(): array
    {
        $output = [];
        $ul_descriptions = $this->getContent( '#tabs-1 > ul > li, #tabs-2 > ul:first-child > li' );
        $p_descriptions  = $this->getContent('#tabs-1 > p');

        if (count($ul_descriptions) > 0){
            foreach ($p_descriptions as $value) {
                $pos = strpos($value, ':');
                if ($pos === false) {
                    $output[] = $value;
                } else {
                    $this->attributes[str_replace("\xc2\xa0", '', substr($value, 0, $pos))] = substr($value, $pos + 2);
                }
            }
        }

        if (count($p_descriptions) > 0){
            $output[] = $p_descriptions[0];
        }

        return $output;
    }

    public function getImages(): array
    {
        $images = $this->getSrcImages('#gallery_nav > a > img');
        return count($images) > 0 ? $images : $this->getSrcImages('#pageGraphic');
    }

    public function isGroup(): bool
    {
         return $this->filter('#addToCart > .product')->count() > 1;
    }

    public function getChildProducts(FeedItem $parent_fi): array
    {
        $children = [];

        $this->filter('#addToCart > .product')->each(function (ParserCrawler $node) use (&$parent_fi, &$children) {
            $fi = clone $parent_fi;

            // get mpn & upc
            $fi->setMpn($this->extractMpn($node));
            $fi->setUpc($this->extractUPC($node));

            // get color
            $color = $this->extractColor($node);
            $fi->setAttributes(array_merge(['Color' => $color], $this->attributes));

            // get price
            $fi->setCostToUs($this->extractPrice($node));

            // get dimensions
            $dimensions = $this->getDimensions($node);
            $dim_x = $dimensions[0] ?? null;
            $dim_y = $dimensions[1] ?? null;
            $dim_z = $dimensions[2] ?? null;
            $fi->setDimX($dim_x);
            $fi->setDimY($dim_y);
            $fi->setDimZ($dim_x);

            // generate child product name
            $fi->setProduct('Color: ' . $color . '. Size: ' .  $dim_x . '"X' . $dim_y);

            $fi->setRAvail(self::DEFAULT_AVAIL_NUMBER);
            $children[] = $fi;
        });

        return $children;
    }

    public function getMpn(): string
    {
        return $this->extractMpn($this->filter('.product'));
    }

    public function getUpc(): ?string
    {
        return $this->isGroup() ? null : $this->extractUPC($this->filter('.product'));
    }

    public function getCostToUs(): float
    {
        return $this->extractPrice($this->node);
    }

    public function getAvail(): ?int
    {
        return self::DEFAULT_AVAIL_NUMBER;
    }

    public function getAttributes(): ?array
    {
        $attributes = [
            'Color' => $this->extractColor($this->node)
        ];

        return array_merge($attributes, $this->attributes);
    }

    public function getDimX(): ?float
    {
        return $this->getDimensions($this->node)[0] ?? null;
    }

    public function getDimY(): ?float
    {
        return $this->getDimensions($this->node)[1] ?? null;
    }

    public function getDimZ(): ?float
    {
        return $this->getDimensions($this->node)[2] ?? null;
    }
}
