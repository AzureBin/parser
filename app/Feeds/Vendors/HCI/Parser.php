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
        return $this->getText('span.product-line-sku-value') != null ? $this->getText('span.product-line-sku-value') : 'NOSKU';
    }

    public function getProduct(): string
    {
        return trim($this->getAttr('meta[property="og:title"]', 'content'));
    }

    public function getListPrice(): ?float
    {
        if ($this->exists('span[itemprop="highPrice"]')) {
            return $this->getAttr('span[itemprop="highPrice"]', 'data-rate') != 0 ? floatval($this->getAttr('span[itemprop="highPrice"]', 'data-rate')) : 0.1;
        }
        return $this->getAttr('span[itemprop="price"]', 'data-rate') !== 0 ? floatval($this->getAttr('span[itemprop="price"]', 'data-rate')) : 0.1;
    }

    public function getCostToUs(): float
    {
        if ($this->exists('span[itemprop="lowPrice"]')) {
            return $this->getAttr('span[itemprop="lowPrice"]', 'data-rate') != 0.0 ? $this->getAttr('span[itemprop="lowPrice"]', 'data-rate') : 0.1;
        }elseif ($this->exists('span[itemprop="price"]')){
            return $this->getAttr('span[itemprop="price"]', 'data-rate') != 0.0 ? $this->getAttr('span[itemprop="price"]', 'data-rate'): 0.1;
        }else{
            return 0.1;
        }
    }

    public function getAttributes(): ?array
    {
        // amendment for comment right parsing  2
        $attribs = [];

        $this->filter('#product-details-information-tab-1 li')
            ->each(function (ParserCrawler $c) use (&$attribs) {
                if(str_contains($c->getText('li'),':') && $c->getText('li') != ''){
                    $temp = explode(':', $c->getText('li'));
                    if ($temp[1] != '')
                        $attribs[(string)$temp[0]] = (string)$temp[1];
                }
            });

        // bypass the validation if empty attrib exist
        return count($attribs) > 0 ? $attribs : array('index'=>'null');
    }

    public function getShortDescription(): array
    {
        // amendment for comment right parsing 1
        $shor_desc = [];

        $this->filter('#product-details-information-tab-1 li')
            ->each(function (ParserCrawler $c) use (&$shor_desc) {
                if(!str_contains($c->getText('li'),':') && $c->getText('li') != ''){
                    $shor_desc[] = $c->getText('li');
                }
            });

        return count($shor_desc) > 0 ? $shor_desc : ['null'];
    }

    public function getDescription(): string
    {
        return trim($this->getAttr('meta[property="og:description"]', 'content'));
    }

    public function getBrand(): ?string
    {
        return trim($this->getAttr('meta[property="og:provider_name"]', 'content'));
    }


    public function getAvail(): ?int
    {
        // amendment 3
        $check_stock = $this->getAttr('meta[property="og:availability"]', 'content');
        if($check_stock === "InStock" || $check_stock === "PreOrder"){
            return self::DEFAULT_AVAIL_NUMBER;
        }else{
            return 0;
        }
    }

    public function getImages(): array
    {
        // amendment 4
        if($this->exists('meta[property="og:image"]')){
            return [$this->getAttr('meta[property="og:image"]', 'content')];
        }else{
            return [$this->getAttr('img.center-block', 'src')];
        }
    }

    public function isGroup(): bool
    {
        return $this->exists('.product-views-option-tile-picker');
    }

    public function getChildProducts(FeedItem $parent_fi): array
    {
        $child = [];
        $current_url = $this->getAttr('meta[property="og:url"]', 'content');
        $url = "https://www.homecontrols.com/api/items";
        $data_url = explode('/', $current_url);
        if (array_key_exists(4, $data_url)) {
            $temp_url = $data_url[3] . '/' . $data_url[4];
        } else {
            $temp_url = $data_url[3];
        }
        $params = [
            'country' => 'US',
            'currency' => 'USD',
            'fieldset' => 'details',
            'language' => 'en',
            'url' => $temp_url
        ];

        $data = $this->getVendor()->getDownloader()->get($url, $params)->getJSON();

        $children_data = $data['items'][0]['matrixchilditems_detail'];

        foreach ($children_data as $index => $c) {

            $fi = clone $parent_fi;

            $fi->setMpn('' . $c['itemid']);
            $fi->setListPrice(floatval($c['onlinecustomerprice']));
            $fi->setCostToUs(floatval($c['onlinecustomerprice']));
            $fi->setRAvail(intval($c['quantityavailable']));
            // amendment 4
            $fi->setASIN(array_key_exists('quantityavailable', $c) ? $c['quantityavailable'] : self::DEFAULT_AVAIL_NUMBER);
            // amendment 5
            if (array_key_exists('custitem33', $c)) {
                $fi->setProduct($this->getText('.product-views-option-tile-label') . $c['custitem33']);
            } elseif (array_key_exists('custitem127', $c)) {
                $fi->setProduct($this->getText('.product-views-option-tile-label') . $c['custitem127']);
            }

            $child[] = $fi;
        }

        return $child;
    }

    public function getOptions(): array
    {
        $child = [];

        $child_lists = $this->filter('div.product-views-option-tile-container label');
        $child_lists->each(function (ParserCrawler $c) use (&$child) {
            $child[str_replace(':','',$this->getText('.product-views-option-tile-label'))] = $c->getText('label');
        });

        return count($child) > 0 ? $child : array('index'=>'null');
    }
}
