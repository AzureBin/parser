<?php

namespace App\Feeds\Vendors\STG;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Helpers\StringHelper;
use Symfony\Component\DomCrawler\Crawler;

class Parser extends HtmlParser
{
    private function hasSku(): bool
    {
        return $this->filter('.trustpilot-widget')->count() > 0;
    }

    private function sanitizeAttributeValue(string $value): string
    {
        if (StringHelper::existsMoney($value)) {
            $pos = strpos($value, '+$');
            if ($pos === false) {
                $pos = strpos($value, '+ $');
            }
            $value = substr($value, 0, $pos - 1);
        }

        return $value;
    }

    public function parseContent(Data $data, array $params = []): array
    {
        if (preg_match('/sku: null/', $data->getData()) === 0) {
            // omit this
            // no way currently to omit as parseContent must return [key => FeedItem]
        }

        return parent::parseContent($data, $params);
    }

    public function isGroup(): bool
    {
        $attributes = $this->filter('div[data-product-option-change] .form-select[required], div[data-product-option-change] .form-radio[required]');

        if (count($attributes) > 0 && $this->hasSku()) {
            return true;
        } else {
            return false;
        }
    }

    public function getChildProducts(FeedItem $parent_fi): array
    {
        $children = [];
        $downloader = $this->vendor->getDownloader();
        $xsrfToken = $downloader->getCookie('XSRF-TOKEN');
        $productId = $this->filter('input[name="product_id"]')->first()->attr('value');
        $matches = [];
        preg_match('/"in_stock_attributes":\[(.+)\]/', $this->node->html(), $matches);
        $inStockAttributes = explode(',', $matches[1]);
        $attributes = [];
        $this
            ->filter('div[data-product-option-change] .form-select[required], div[data-product-option-change] .form-radio[required]')
            ->each(static function(Crawler $node) use (&$attributes, $inStockAttributes) {
                if ($node->nodeName() === 'select') {
                    $values = $node
                        ->filter('option[data-product-attribute-value]')
                        ->each(static fn(Crawler $node) => $node->attr('value'));
                    foreach ($values as $value) {
                        if (in_array($value, $inStockAttributes)) {
                            $attributes[substr($node->attr('name'), 10, -1)][] = $value;
                        }
                    }
                } else {
                    $value = $node->attr('value');
                    if (in_array($value, $inStockAttributes)) {
                        $attributes[substr($node->attr('name'), 10, -1)][] = $value;
                    }
                }

            });

        $host = substr($this->getUri(), 0, strpos($this->getUri(), '/', 8));
        $endpoint = $host . '/remote/v1/product-attributes/' . $productId;
        $downloader->setHeader('x-xsrf-token', $xsrfToken);
        $links = [];

        $cartesian = function (array $input): array {
            $result = [[]];

            foreach ($input as $key => $values) {
                $append = [];

                foreach($result as $product) {
                    foreach($values as $item) {
                        $product[$key] = $item;
                        $append[] = $product;
                    }
                }

                $result = $append;
            }

            return $result;
        };

        foreach ($cartesian($attributes) as $variationParams) {
            $params = [];
            foreach ($variationParams as $key => $value) {
                $params["attribute[$key]"] = $value;
            }
            $links[] = new Link($endpoint, 'POST', $params);
        }

        foreach ($downloader->fetch($links, true) as $response) {
            $json = $response['data']->getJSON();
            if (array_key_exists('data', $json)) {
                $json = $json['data'];
            } else {
                continue;
            }

            $fi = clone $parent_fi;

            if ($json['sku'] === null) {
                continue;
            }

            $fi->setMpn($json['sku']);
            if (isset($json['image']['data'])) {
                $fi->setImages([$json['image']['data'], ...$parent_fi->images]);
            }
            $fi->setCostToUs($json['price']['without_tax']['value']);
            $fi->setRAvail($json['instock'] ? self::DEFAULT_AVAIL_NUMBER : 0);

            $fiAttributes = [];
            foreach ($response['link']['params'] as $key => $value) {
                $node = $this->filter("[name='$key']");
                if ($node->nodeName() === 'select') {
                    $attributeKey = substr($node->siblings()->text(), 0, -10);
                    $attributeValue = $node->filter("[value='$value']")->text();
                } else {
                    $node = $node->filter("[value='$value']");
                    $attributeKey = substr($node->siblings()->first()->text(), 0, -10);
                    $attributeValue = $node->attr('data-option-label');
                }

                $fiAttributes[$attributeKey] = $this->sanitizeAttributeValue($attributeValue);
            }

            $fi->setAttributes(count($fiAttributes) > 0 ? $fiAttributes : null);

            $children[] = $fi;
        }

        return $children;
    }

    public function getProduct(): string
    {
        return $this->getText('.productView-title');
    }

    public function getDescription(): string
    {
        return $this->getText('.productView-description p');
    }

    public function getShortDescription(): array
    {
        return $this->getContent('.productView-description');
    }

    public function getImages(): array
    {
        $images = $this
            ->filter('.productView-thumbnail-link')
            ->each(static fn (Crawler $node) => $node->attr('data-image-gallery-zoom-image-url'));

        if (count($images) === 0) {
            return [$this->filter('.productView-image--default')->first()->attr('data-zoom-target')];
        }

        return $images;
    }

    public function getMpn(): string
    {
        return $this->hasSku()
            ? $this->filter('.trustpilot-widget')->first()->attr('data-sku')
            : 'NOSKU';
    }

    public function getCostToUs(): float
    {
        return $this->getMoney('.productView-price');
    }

    public function getAvail(): ?int
    {
        return self::DEFAULT_AVAIL_NUMBER;
    }

    public function getOptions(): array
    {
        $attributes = [];
        $this
            ->filter('div[data-product-option-change] > div[data-product-attribute="input-checkbox"]')
            ->each(static function(Crawler $node) use (&$attributes) {
                $labels = $node->filter('label');
                if ($labels->last()->text() !== 'I agree to the terms listed above') {
                    $key = $labels->first()->text();
                    if (str_contains($key, ':')) {
                        $key = substr($key, 0, strpos($key, ':'));
                    }
                    $attributes[rtrim($key)] = $labels->last()->text();
                }
            });

        $this
            ->filter('div[data-product-option-change] .form-select[required], div[data-product-option-change] .form-radio[required]')
            ->each(function (Crawler $node) use (&$attributes) {
                if ($node->nodeName() === 'input') {
                    $key = substr($node->siblings()->first()->text(), 0, -10);
                    if (!array_key_exists($key, $attributes)) {
                        $attributes[$key] = [];
                    }

                    $attributes[$key][] = $this->sanitizeAttributeValue($node->attr('data-option-label'));
                } else {
                    $key = substr($node->siblings()->text(), 0, -10);
                    $attributes[$key] = $node
                        ->filter('option[data-product-attribute-value]')
                        ->each(fn(Crawler $node) => $this->sanitizeAttributeValue($node->text()));
                }
            });

        return $attributes;
    }
}
