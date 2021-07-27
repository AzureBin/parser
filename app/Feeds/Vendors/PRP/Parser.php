<?php
declare(strict_types=1);

namespace App\Feeds\Vendors\PRP;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;

class Parser extends HtmlParser
{
    public function getProduct(): string
    {
         return $this->getText('#centercolumn > h1');
    }

    public function getDescription(): string
    {
       return '<ul>' . $this->getHtml( '#tabs-1 > ul' ) . '</ul>';
    }

    public function getShortDescription(): array
    {
        return $this->getContent('#tabs-2');
    }

    public function getImages(): array
    {
        $images = $this->getSrcImages('#gallery_nav > a > img');
        return count($images) > 0 ? $images : $this->getSrcImages('#pageGraphic');
    }

    public function isGroup(): bool
    {
        // return $this->exists('#addToCart > .product');
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
            $fi->setOptions(['color' => $this->extractColor($node)]);

            // get price
            $fi->setCostToUs($this->extractPrice($node));

            // get dimensions
            $dimensions = $this->extractDimensions($node);
            $fi->setDimX($dimensions[0] ?? null);
            $fi->setDimY($dimensions[1] ?? null);
            $fi->setDimZ($dimensions[2] ?? null);

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

    public function getOptions(): array
    {
        return ['color' => $this->extractColor($this->node)];
    }

    public function getDimX(): ?float
    {
        return $this->extractDimensions($this->node)[0] ?? null;
    }

    public function getDimY(): ?float
    {
        return $this->extractDimensions($this->node)[1] ?? null;
    }

    public function getDimZ(): ?float
    {
        return $this->extractDimensions($this->node)[2] ?? null;
    }

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

    private function extractDimensions(ParserCrawler $node): array
    {
        $dimensionsStr = $node->filter('.productDescription')->text();
        $matches = [];
        preg_match("@(\d+(?:\.\d{1,2})?)\"\sx\s(\d+(?:\.\d{1,2})?)\"(?:\sx\s(\d+(?:\.\d{1,2})?)\")?@", $dimensionsStr, $matches);

        return array_map('floatval', array_slice($matches, 1));
    }
}
