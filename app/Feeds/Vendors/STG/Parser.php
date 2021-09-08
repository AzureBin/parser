<?php

namespace App\Feeds\Vendors\STG;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Link;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;
use DOMElement;
use Symfony\Component\DomCrawler\Crawler;

class Parser extends HtmlParser
{
    private array $attributes = [];
    private ?float $weight = null;
    private ?string $brand = null;

    private function cartesian(array $input): array
    {
        $result = [[]];

        foreach ($input as $key => $values) {
            $append = [];

            foreach ($result as $product) {
                foreach ($values as $item) {
                    $product[$key] = $item;
                    $append[] = $product;
                }
             }

            $result = $append;
        }

        return $result;
    }

    private function extractWeight(string $str): float
    {
        return (float) explode('|', $str)[1] / 453.592;
    }

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

    private function sanitizeDescription(Crawler $nodes): string
    {
        $description = '';

        foreach ($nodes as $node) {
            /** @var DOMElement $node */
            if ($node->nodeName === 'p') {
                if (str_ends_with($node->textContent, ':')) {
                    if ($node->nextElementSibling->nodeName !== 'ul' && $node->textContent !== 'Description:') {
                        $description .= $node->ownerDocument->saveHTML($node);
                    }
                } else {
                    $description .= $node->ownerDocument->saveHTML($node);
                }
            } elseif ($node->nodeName !== 'ul' && $node->nodeName !== 'script') {
                $description .= $node->ownerDocument->saveHTML($node);
            }
        }

        return $description;
    }

    public function isGroup(): bool
    {
        $attributes = $this->filter('div[data-product-option-change] .form-select[required], div[data-product-option-change] .form-radio[required]');
        return (count($attributes) > 0 && $this->hasSku());
    }

    public function getChildProducts(FeedItem $parent_fi): array
    {
        $children = [];
        $downloader = $this->vendor->getDownloader();
        $xsrfToken = $downloader->getCookie('XSRF-TOKEN');
        $productId = $this->filter('input[name="product_id"]')->first()->attr('value');
        $attributes = [];
        $this
            ->filter('div[data-product-option-change] .form-select[required], div[data-product-option-change] .form-radio[required]')
            ->each(static function(Crawler $node) use (&$attributes) {
                if ($node->nodeName() === 'select') {
                    $values = $node
                        ->filter('option[data-product-attribute-value]')
                        ->each(static fn(Crawler $node) => $node->attr('value'));
                    foreach ($values as $value) {
                        $attributes[substr($node->attr('name'), 10, -1)][] = $value;
                    }
                } else {
                    $value = $node->attr('value');
                    $attributes[substr($node->attr('name'), 10, -1)][] = $value;
                }
            });

        $host = substr($this->getUri(), 0, strpos($this->getUri(), '/', 8));
        $endpoint = $host . '/remote/v1/product-attributes/' . $productId;
        $downloader->setHeader('x-xsrf-token', $xsrfToken);
        $links = [];

        foreach ($this->cartesian($attributes) as $variationParams) {
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
                $fi->setImages([str_replace('{:size}', '1280x1280', $json['image']['data'])]);
            }
            $fi->setCostToUs($json['price']['without_tax']['value']);
            $fi->setRAvail($json['instock'] ? self::DEFAULT_AVAIL_NUMBER : 0);

            $fiAttributes = [];
            $productName = '';
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

            if (count($fiAttributes) > 0) {
                foreach ($fiAttributes as $key => $value) {
                    if ($productName !== '') {
                        $productName .= ' ';
                    }
                    $productName .= $key . ': ' . $value . '.';
                }
            }

            if ($productName !== '') {
                $fi->setProduct($productName);
            }

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
        $nodes = $this->filter('.productView-description > div > .productView-description > *');

        if (count($nodes) === 1 && $nodes->getNode(0)->nodeName === 'table') {
            $nodes = (new Crawler($nodes->getNode(0)))->filter('td > *');
            return $this->sanitizeDescription($nodes);
        }

        return $this->sanitizeDescription($nodes);
    }

    public function getShortDescription(): array
    {
        $shortDesc = [];

        // check is this key word 'Specifications'?
        $this->filter('.productView-description p')->each(function(ParserCrawler $node) use (&$shortDesc) {
            // if found 'Specifications:'
            if ($node->text() === 'Specifications:') {
                $this->filter('.productView-description ul li')->each(function(ParserCrawler $node) use (&$shortDesc) {
                    // while correspond ':'
                    if (str_contains($node->text(), ':')) {
                        if (str_starts_with($node->text(), 'Brand:')) {
                            // get Brand
                            $this->brand = explode('Brand:', $node->text())[1];
                        } else if (str_starts_with($node->text(), 'Weight:')) {
                            // get Weight
                            $this->weight = $this->extractWeight($node->text());
                        } else {
                            // get attributes
                            $attr_key_value = explode(':', $node->text());
                            $this->attributes[$attr_key_value[0]] = $attr_key_value[1];
                        }
                    } else {
                        $shortDesc[] = $node->text();
                    }
                });
            }
       });

        // or gather standard shortDescription (ul li)
        if (count($shortDesc) < 1) {
            $this
                ->filter('.productView-description ul li')
                ->each(function(ParserCrawler $node) use(&$shortDesc) {
                    $text = $node->text();
                    if (str_contains($text, ':')){
                        // get attributes
                        $text = str_replace(['"', '::marker'], '', $text);
                        $attr_key_value = explode(':', $text);
                        $this->attributes[$attr_key_value[0]] = $attr_key_value[1];
                    } else {
                        $shortDesc[] = $text;
                    }
                });
        }

        return $shortDesc;
   }

    public function getImages(): array
    {
        $images = $this
            ->filter('.productView-thumbnail-link')
            ->each(static fn (Crawler $node) => $node->attr('data-image-gallery-zoom-image-url'));

        if (count($images) === 0) {
            return [$this->getAttr('.productView-image--default', 'data-zoom-target')];
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
        $stock_status = $this->filter('meta[property="og:availability"]')->eq(0)->attr('content');
        return $stock_status === 'instock'
            ? self::DEFAULT_AVAIL_NUMBER
            : 0;
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

         return $attributes;
    }

    public function getAttributes(): ?array
    {
        return (count($this->attributes) > 0) ? $this->attributes : null;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }
}
