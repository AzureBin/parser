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
        $current_url = $this->getAttr('meta[property="og:url"]', 'content');
        $url = "https://www.homecontrols.com/api/items";
        $data_url = explode('/', $current_url);
        if(array_key_exists(4,$data_url)){
            $temp_url = $data_url[3].'/'.$data_url[4];
        }else{
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

            $fi->setMpn($c['itemid']);
            $fi->setListPrice(floatval($c['onlinecustomerprice']));
            $fi->setCostToUs(floatval($c['onlinecustomerprice']));
            $fi->setShortdescr([$c['custitem604']]);
            $fi->setFulldescr($c['stockdescription']);
            $fi->setASIN($c['quantityavailable']?$c['quantityavailable']:self::DEFAULT_AVAIL_NUMBER);

            if (array_key_exists('custitem33',$c)){
                $fi->setProduct($c['custitem33']);
            } elseif (array_key_exists('custitem127',$c)) {
                $fi->setProduct($c['custitem127']);
            }

            $child[] = $fi;
        }

        return $child;
    }
}
