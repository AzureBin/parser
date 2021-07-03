<?php

namespace App\Feeds\Vendors\HCI;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{

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
        if ($this->exists('span[itemprop="lowPrice"]')) {
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
        $child = [];

        $child_lists = $this->filter('div.product-views-option-tile-container label');
        $child_lists->each(function (ParserCrawler $c) use (&$child) {
            $child[] = $c->getText('label');
        });

        return $child;
    }


    public function isGroup(): bool
    {
        return $this->exists('.product-views-option-tile-picker');
    }

    public function getChildProducts(FeedItem $parent_fi): array
    {
        $child = [];

        $child_lists = $this->filter('div.product-views-option-tile-container label');

        $child_lists->each(function (ParserCrawler $c) use ($parent_fi, &$child,&$child_lists) {
            $fi = clone $parent_fi;
            $fi->setMpn($this->getText('span[itemprop="sku"]'));
            $fi->setProduct($c->getText('label'));
            $fi->setListPrice(StringHelper::getMoney(trim($this->getAttr('span[itemprop="price"]', 'data-rate'))));
            $child[] = $fi;
        });

        return array_values($child);
    }

}
