<?php

namespace App\Feeds\Vendors\HMC;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

// task 1,2,3,4,5,6,7,8,9,10,11,12,13,14,16,17
class Parser extends HtmlParser
{
    public function getMpn(): string
    {
        return $this->getText('span.product-line-sku-value') !== null ? $this->getText('span.product-line-sku-value') : "";
    }

    public function getProduct(): string
    {
        return trim($this->getAttr('meta[property="og:title"]', 'content'));
    }

    public function getListPrice(): ?float
    {
        if ($this->exists('span[itemprop="highPrice"]')) {
            return $this->getAttr('span[itemprop="highPrice"]', 'data-rate') !== 0.0 ? StringHelper::getFloat($this->getAttr('span[itemprop="highPrice"]', 'data-rate')) : 0;
        }
        return $this->getAttr('span[itemprop="price"]', 'data-rate') !== 0.0 ? StringHelper::getFloat($this->getAttr('span[itemprop="price"]', 'data-rate')) : 0;
    }

    public function getCostToUs(): float
    {
        if ($this->exists('span[itemprop="lowPrice"]')) {
            return $this->getAttr('span[itemprop="lowPrice"]', 'data-rate') !== 0.0 ? StringHelper::getFloat($this->getAttr('span[itemprop="lowPrice"]', 'data-rate')) : 0;
        } elseif ($this->exists('span[itemprop="price"]')) {
            return $this->getAttr('span[itemprop="price"]', 'data-rate') !== 0.0 ? StringHelper::getFloat($this->getAttr('span[itemprop="price"]', 'data-rate')) : 0;
        } else {
            return 0;
        }
    }

    public function getAttributes(): ?array
    {
        $attribs = [];

        $this->filter('#product-details-information-tab-1 li')
            ->each(function (ParserCrawler $c) use (&$attribs) {
                if (str_contains($c->getText('li'), ':') && !empty($c->getText('li'))) {
                    $temp = explode(':', $c->getText('li'));
                    if ($temp[1] !== '' && !str_contains($temp[0], 'Manufacturer Part') && !str_contains($temp[0], 'MPN')) {
                        $attribs[(string)$temp[0]] = trim((string)$temp[1]);
                    }
                }
            });

        return count($attribs) > 0 ? $attribs : [];
    }

    public function getShortDescription(): array
    {
        $shor_desc = [];

        $this->filter('#product-details-information-tab-1 li')
            ->each(function (ParserCrawler $c) use (&$shor_desc) {
                if (!str_contains($c->getText('li'), ':') && $c->getText('li') != '') {
                    $shor_desc[] = preg_replace('/[$0-9]/','',$c->getText('li'));
                }
            });

        $this->filter('#product-details-information-tab-content-container-0 ul:nth-of-type(1) li')
            ->each(function (ParserCrawler $c) use (&$shor_desc) {
                if (!str_contains($c->text(), ':') && $c->text() !== '') {
                    $shor_desc[] = preg_replace('/[$0-9]/','',$c->text());
                }
            });

        return count($shor_desc) > 0 ? $shor_desc : [];
    }

    public function getDescription(): string
    {
        $description = '';
        $this->filter('#product-details-information-tab-content-container-0 h2 ~ p')
            ->each(function (ParserCrawler $c) use (&$description){
                if(!str_contains($c->text(),'strong')){
                    $description = $description.$c->text();
                }
            });
        return $description;
    }

    public function getBrand(): ?string
    {
        return trim($this->getAttr('meta[property="og:provider_name"]', 'content'));
    }

    public function getAvail(): ?int
    {
        $check_stock = $this->getAttr('meta[property="og:availability"]', 'content');
        if ($check_stock === "InStock" || $check_stock === "PreOrder") {
            return self::DEFAULT_AVAIL_NUMBER;
        } else {
            return 0;
        }
    }

    public function getImages(): array
    {
        $images = [];

        if ($this->exists('ul.bxslider li')) {
            $this->filter('ul.bxslider li')
                ->each(function (ParserCrawler $c) use (&$images) {
                    $images[] = $c->getAttr('img', 'src');
                });
        } else {
            $images[] = $this->getAttr('meta[property="og:image"]', 'content');
        }

        return $images;
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

            if (array_key_exists('onlinecustomerprice', $c) && $c['onlinecustomerprice'] !== 0.0) {
                $fi->setListPrice(StringHelper::getFloat($c['onlinecustomerprice']));
                $fi->setCostToUs(StringHelper::getFloat($c['onlinecustomerprice']));
            } else {
                $fi->setListPrice(0);
                $fi->setCostToUs(0);
            }

            $fi->setASIN(array_key_exists('quantityavailable', $c) ? $c['quantityavailable'] : 0);

            $temp_imgs = [];

            if (array_key_exists('custitem33', $c)) {
                $fi->setProduct(trim(str_replace(':', '', $this->getText('.product-views-option-tile-label'))) . ': ' . trim($c['custitem33']));
                if ((count($c['itemimages_detail']) > 0) && (array_key_exists($c['custitem33'], $c['itemimages_detail']['media']))) {
                    foreach ($c['itemimages_detail']['media'] as $key => $value) {
                        if ($c['parent'] === 'LV420806xS') {
                            if ($key === 'Light Almond' && array_key_exists('040.jpg420806xS_media', $value)) {
                                $temp_imgs[] = $value['040.jpg420806xS_media'][$key]['urls'][0]['url'];
                            } else {
                                $temp_imgs[] = $value['urls'][0]['url'];
                            }
                        } elseif ($c['parent'] === 'SO901502x') {
                            if ($key === 'Black') {
                                $temp_imgs[] = $value['urls'][0]['url'];
                            } else {
                                $temp_imgs[] = $value['url'];
                            }
                        } else {
                            $temp_imgs[] = $c['itemimages_detail']['media'][$c['custitem33']]['urls'][0]['url'];
                        }
                    }

                }
            } elseif (array_key_exists('custitem127', $c)) {
                $fi->setProduct(trim(str_replace(':', '', $this->getText('.product-views-option-tile-label'))) . ': ' . trim($c['custitem127']));
                if ((count($c['itemimages_detail']) > 0) && array_key_exists($c['custitem127'], $c['itemimages_detail']['media'])) {
                    $temp_imgs[] = $c['itemimages_detail']['media'][$c['custitem127']]['urls'][0]['url'];
                }
            }

            $fi->setImages(array_unique($temp_imgs));
            if(array_key_exists('custitem481',$data['items'][0])){
                $temp_brand = explode('/', $data['items'][0]['custitem481']);
                $fi->setBrandName($temp_brand[1]);
            }

            $child[] = $fi;
        }

        return $child;
    }

    public function getOptions(): array
    {
        $child = [];

        if (!$this->isGroup()) {

            $child_lists = $this->filter('div.product-views-option-tile-container label');
            $child_lists->each(function (ParserCrawler $c) use (&$child) {
                $child[str_replace(':', '', $this->getText('.product-views-option-tile-label'))] = $c->getText('label');
            });
        }

        return $child;
    }

    public function getProductFiles(): array
    {
        $pdf_list = [];

        $this->filter('div[data-id="product-details-information-2"] p a')
            ->each(function (ParserCrawler $c) use (&$pdf_list) {
                $pdf_list[] = ['name' => $c->text(), 'link' => 'https://www.homecontrols.com' . $c->attr('href')];
            });

        return $pdf_list;
    }

}
